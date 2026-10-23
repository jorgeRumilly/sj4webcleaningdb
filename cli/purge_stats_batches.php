<?php
/**
 * Purge par lots des tables de statistiques PrestaShop (connections, connections_source, guest).
 *
 * Script AUTONOME (PDO) : il ne charge pas PrestaShop ni le module, donc il fonctionne même si le
 * module sj4webcleaningdb est en panne. Il lit seulement les identifiants dans app/config/parameters.php.
 *
 * SECURITE
 *  - Par défaut : simulation (aucune suppression). Il faut ajouter --execute pour supprimer.
 *  - Suppression par petits lots (--batch lignes), avec pause entre lots et mise en retrait automatique
 *    si le serveur MariaDB est chargé : le site n'est jamais bloqué longtemps.
 *  - Reprenable : on peut l'interrompre (Ctrl+C) ou le relancer, il reprend là où il en est.
 *  - Ne supprime JAMAIS les connexions des visiteurs liés à un client (guest.id_customer > 0).
 *  - Verrou (GET_LOCK) pour éviter deux exécutions en même temps.
 *  - NE PAS lancer entre 03h45 et 04h45 (cron sj4webcleaningdb à 04h00).
 *
 * LOGIQUE (identique au module corrigé)
 *   A. connections         : lignes plus anciennes que --days jours (hors visiteurs liés à un client)
 *   B. connections_source  : lignes orphelines (plus de connexion parente)
 *   C. guest               : visiteurs anonymes orphelins (plus aucune connexion)
 *
 * USAGE
 *   php purge_stats_batches.php                          # simulation, 90 jours
 *   php purge_stats_batches.php --days=30                # simulation avec 30 jours de rétention
 *   php purge_stats_batches.php --days=30 --execute      # suppression réelle
 *   php purge_stats_batches.php --days=30 --execute --max-minutes=20 --batch=5000 --sleep-ms=300
 *   php purge_stats_batches.php --execute --optimize     # + OPTIMIZE TABLE à la fin (récupère l'espace disque)
 *
 * OPTIONS
 *   --days=N          rétention des connexions en jours (défaut 90)
 *   --batch=N         lignes examinées par lot (défaut 5000)
 *   --sleep-ms=N      pause entre deux lots (défaut 200)
 *   --max-minutes=N   durée maximale de l'exécution (défaut 60) ; relancer pour continuer
 *   --max-threads=N   si Threads_running dépasse N, pause automatique (défaut 30)
 *   --tables=a,b,c    sous-ensemble parmi connections,connections_source,guest (défaut : les trois)
 *   --execute         supprime réellement
 *   --optimize        OPTIMIZE TABLE sur les tables touchées (reconstruction en ligne ; prévoir de la place disque
 *                     libre = taille de la table, à lancer hors heures de pointe)
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$opt = getopt('', ['days::', 'batch::', 'sleep-ms::', 'max-minutes::', 'max-threads::', 'tables::', 'execute', 'optimize', 'help']);
if (isset($opt['help'])) {
    echo "Voir l'en-tête du fichier pour l'usage.\n";
    exit(0);
}

$days = max(7, (int) ($opt['days'] ?? 90));          // garde-fou : jamais moins de 7 jours
$batch = max(500, min(50000, (int) ($opt['batch'] ?? 5000)));
$sleepMs = max(0, (int) ($opt['sleep-ms'] ?? 200));
$maxMinutes = max(1, (int) ($opt['max-minutes'] ?? 60));
$maxThreads = max(5, (int) ($opt['max-threads'] ?? 30));
$execute = isset($opt['execute']);
$optimize = isset($opt['optimize']);
$allSteps = ['connections', 'connections_source', 'guest'];
$steps = isset($opt['tables']) ? array_values(array_intersect($allSteps, explode(',', (string) $opt['tables']))) : $allSteps;
if (!$steps) {
    exit("Aucune table valide dans --tables.\n");
}

$root = dirname(__DIR__, 3);
$cfgFile = $root . '/app/config/parameters.php';
if (!is_file($cfgFile)) {
    exit("parameters.php introuvable : $cfgFile\n");
}
$cfg = (include $cfgFile)['parameters'];
$prefix = $cfg['database_prefix'];
$dsn = 'mysql:host=' . $cfg['database_host'] . ';dbname=' . $cfg['database_name'] . ';charset=utf8mb4';
if (!empty($cfg['database_port'])) {
    $dsn .= ';port=' . (int) $cfg['database_port'];
}
$pdo = new PDO($dsn, $cfg['database_user'], $cfg['database_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function out(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

function qv(PDO $pdo, string $sql, array $params = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/** Limite de durée par requête : MariaDB (max_statement_time, en s) ou MySQL (max_execution_time, en ms) ; ignoré sinon. */
function stmtTimeout(PDO $pdo, int $seconds): void
{
    foreach (['SET SESSION max_statement_time = ' . $seconds, 'SET SESSION max_execution_time = ' . ($seconds * 1000)] as $sql) {
        try {
            $pdo->exec($sql);
            return;
        } catch (PDOException $e) {
            // variable inconnue sur ce serveur : on essaie la suivante
        }
    }
}

if (!qv($pdo, "SELECT GET_LOCK('sj4web_purge_stats_batches', 0)")) {
    exit("Une autre exécution est déjà en cours (verrou pris). Abandon.\n");
}

$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
$t0 = time();
$deadline = $t0 + $maxMinutes * 60;
$conn = $prefix . 'connections';
$src = $prefix . 'connections_source';
$guest = $prefix . 'guest';

// Définition de chaque étape : table, clé primaire, condition de suppression, borne haute éventuelle de la clé primaire.
$defs = [
    'connections' => [
        'table' => $conn,
        'pk' => 'id_connections',
        'where' => "`date_add` < :cutoff AND NOT EXISTS (SELECT 1 FROM `$guest` g WHERE g.`id_guest` = `$conn`.`id_guest` AND g.`id_customer` > 0)",
        'params' => [':cutoff' => $cutoff],
        'upper_sql' => "SELECT MAX(`id_connections`) FROM `$conn` WHERE `date_add` < :cutoff",
        'upper_params' => [':cutoff' => $cutoff],
    ],
    'connections_source' => [
        'table' => $src,
        'pk' => 'id_connections_source',
        'where' => "NOT EXISTS (SELECT 1 FROM `$conn` c WHERE c.`id_connections` = `$src`.`id_connections`)",
        'params' => [],
        'upper_sql' => "SELECT MAX(`id_connections_source`) FROM `$src`",
        'upper_params' => [],
    ],
    'guest' => [
        'table' => $guest,
        'pk' => 'id_guest',
        'where' => "`id_customer` = 0 AND NOT EXISTS (SELECT 1 FROM `$conn` c WHERE c.`id_guest` = `$guest`.`id_guest`)",
        'params' => [],
        'upper_sql' => "SELECT MAX(`id_guest`) FROM `$guest`",
        'upper_params' => [],
    ],
];

out('Mode : ' . ($execute ? 'EXECUTION REELLE' : 'SIMULATION (aucune suppression)') . " | rétention connexions : {$days} j (avant {$cutoff}) | lot : {$batch} | max : {$maxMinutes} min");

$touched = [];
$summary = [];
foreach ($steps as $name) {
    $d = $defs[$name];
    $table = $d['table'];
    $pk = $d['pk'];
    $upper = qv($pdo, $d['upper_sql'], $d['upper_params']);
    if ($upper === null || $upper === false) {
        out("[$name] rien à examiner.");
        $summary[$name] = 0;
        continue;
    }
    $upper = (int) $upper;

    if (!$execute) {
        // Estimation (limitée à 120 s côté serveur pour ne pas surcharger la base).
        stmtTimeout($pdo, 120);
        try {
            $n = (int) qv($pdo, "SELECT COUNT(*) FROM `$table` WHERE `$pk` <= :up AND ({$d['where']})", $d['params'] + [':up' => $upper]);
            out("[$name] lignes qui seraient supprimées : " . number_format($n, 0, ',', ' '));
            $summary[$name] = $n;
        } catch (PDOException $e) {
            out("[$name] estimation interrompue (trop longue) : relancer avec --execute, la progression sera affichée.");
            $summary[$name] = null;
        }
        stmtTimeout($pdo, 0);
        continue;
    }

    stmtTimeout($pdo, 60); // aucun lot ne doit durer plus de 60 s
    $deleted = 0;
    $last = 0;
    $batches = 0;
    $stopped = false;
    $del = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` > :last AND `$pk` <= :max AND ({$d['where']})");
    $win = $pdo->prepare("SELECT MAX(`$pk`) FROM (SELECT `$pk` FROM `$table` WHERE `$pk` > :last AND `$pk` <= :up ORDER BY `$pk` LIMIT $batch) w");
    out("[$name] début (clés jusqu'à $upper)");
    while (true) {
        if (time() >= $deadline) {
            $stopped = true;
            out("[$name] durée maximale atteinte : arrêt propre (relancer pour continuer).");
            break;
        }
        // Mise en retrait si le serveur est chargé.
        $waits = 0;
        while ((int) $pdo->query("SHOW GLOBAL STATUS LIKE 'Threads_running'")->fetch(PDO::FETCH_NUM)[1] > $maxThreads && $waits < 30) {
            $waits++;
            sleep(2);
        }
        $win->execute([':last' => $last, ':up' => $upper]);
        $max = $win->fetchColumn();
        if ($max === null || $max === false) {
            break;
        }
        $del->execute($d['params'] + [':last' => $last, ':max' => (int) $max]);
        $deleted += $del->rowCount();
        $last = (int) $max;
        $batches++;
        if ($batches % 50 === 0) {
            $pct = round(100 * $last / max(1, $upper));
            out("[$name] $pct % parcouru, " . number_format($deleted, 0, ',', ' ') . ' lignes supprimées');
        }
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
        if ($last >= $upper) {
            break;
        }
    }
    stmtTimeout($pdo, 0);
    out("[$name] terminé" . ($stopped ? ' (partiel)' : '') . ' : ' . number_format($deleted, 0, ',', ' ') . ' lignes supprimées.');
    $summary[$name] = $deleted;
    if ($deleted > 0) {
        $touched[] = $table;
    }
    if ($stopped) {
        break; // on n'enchaîne pas sur l'étape suivante si le temps est écoulé
    }
}

if ($execute && $optimize && $touched) {
    foreach ($touched as $table) {
        if (time() >= $deadline + 3600) {
            break;
        }
        out("OPTIMIZE TABLE $table (peut durer plusieurs minutes ; reconstruction en ligne)...");
        $pdo->query("OPTIMIZE TABLE `$table`")->fetchAll();
        out("OPTIMIZE $table terminé.");
    }
}

qv($pdo, "SELECT RELEASE_LOCK('sj4web_purge_stats_batches')");
out('Résumé : ' . json_encode($summary) . ' | durée : ' . (time() - $t0) . ' s');
if (!$execute) {
    out('Simulation terminée. Ajouter --execute pour supprimer réellement.');
}
