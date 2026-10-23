<?php
/**
 * Supprime les tables d'archive "save_*" créées par l'option de backup de sj4webcleaningdb.
 *
 * Script AUTONOME (PDO), CLI uniquement. Il lit les identifiants dans app/config/parameters.php.
 *
 * SECURITE
 *  - Par défaut : simulation. Affiche les tables, leur taille, et les DROP qui seraient exécutés.
 *  - Seules les tables dont le nom est EXACTEMENT {préfixe}save_<table> avec <table> parmi la liste blanche
 *    ci-dessous sont concernées. Aucune autre table ne peut être supprimée.
 *  - Refuse de supprimer tant que l'option de backup du module est active
 *    (SJ4WEB_CLEANINGDB_ENABLE_BACKUP = 1), sinon le prochain nettoyage les recréerait.
 *    (--ignore-backup-option permet de passer outre.)
 *  - Pour supprimer réellement : --execute --confirm=DROP-SAVE-TABLES
 *  - Suppression IRRÉVERSIBLE : les tables n'ont pas de sauvegarde. Faire un dump avant si le moindre doute.
 *  - Une table à la fois, avec pause, hors heures de pointe de préférence (le DROP d'une table de 20 Go libère
 *    beaucoup de disque d'un coup).
 *
 * USAGE
 *   php drop_save_tables.php
 *   php drop_save_tables.php --execute --confirm=DROP-SAVE-TABLES
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$opt = getopt('', ['execute', 'confirm::', 'ignore-backup-option', 'help']);
if (isset($opt['help'])) {
    echo "Voir l'en-tête du fichier pour l'usage.\n";
    exit(0);
}

$whitelist = ['connections', 'connections_source', 'guest', 'cart', 'cart_product', 'pagenotfound', 'statssearch'];

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
$pdo = new PDO($dsn, $cfg['database_user'], $cfg['database_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function out(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

$execute = isset($opt['execute']);
$confirmed = ($opt['confirm'] ?? '') === 'DROP-SAVE-TABLES';

// 1. Option de backup du module encore active ?
$st = $pdo->prepare('SELECT `value` FROM `' . $prefix . 'configuration` WHERE `name` = :n LIMIT 1');
$st->execute([':n' => 'SJ4WEB_CLEANINGDB_ENABLE_BACKUP']);
$backupOn = (int) $st->fetchColumn() === 1;
if ($backupOn) {
    out("ATTENTION : l'option de backup du module (SJ4WEB_CLEANINGDB_ENABLE_BACKUP) est ACTIVE.");
    out('Désactivez-la dans le BO (Paramètres avancés > DB Cleanup) avant de supprimer, sinon le prochain nettoyage recrée les tables.');
}

// 2. Tables candidates (liste blanche stricte)
$candidates = [];
foreach ($whitelist as $t) {
    $candidates[] = $prefix . 'save_' . $t;
}
$in = implode(',', array_fill(0, count($candidates), '?'));
$st = $pdo->prepare("SELECT table_name AS tname, ROUND((data_length + index_length) / 1048576) AS mb, table_rows AS trows
                     FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ($in) ORDER BY (data_length + index_length) DESC");
$st->execute($candidates);
$tables = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$tables) {
    out('Aucune table save_* à supprimer.');
    exit(0);
}

$total = 0;
foreach ($tables as $t) {
    $total += (int) $t['mb'];
    out(sprintf('  %-45s %8s Mo  ~%s lignes', $t['tname'], number_format((int) $t['mb'], 0, ',', ' '), number_format((int) $t['trows'], 0, ',', ' ')));
}
out('Total : ' . number_format($total, 0, ',', ' ') . ' Mo libérables.');

if (!$execute) {
    foreach ($tables as $t) {
        out('SIMULATION : DROP TABLE `' . $t['tname'] . '`');
    }
    out('Simulation terminée. Pour supprimer : --execute --confirm=DROP-SAVE-TABLES');
    exit(0);
}

if (!$confirmed) {
    exit("Refus : ajouter --confirm=DROP-SAVE-TABLES pour confirmer la suppression IRREVERSIBLE.\n");
}
if ($backupOn && !isset($opt['ignore-backup-option'])) {
    exit("Refus : option de backup active (voir plus haut). Désactivez-la, ou ajoutez --ignore-backup-option.\n");
}

$pdo->exec('SET SESSION lock_wait_timeout = 30');
foreach ($tables as $t) {
    $name = $t['tname'];
    if (!in_array($name, $candidates, true)) {
        continue; // double sécurité
    }
    $s = microtime(true);
    out("DROP TABLE `$name` ...");
    $pdo->exec('DROP TABLE `' . str_replace('`', '', $name) . '`');
    out(sprintf('  supprimée en %.1f s (%s Mo libérés)', microtime(true) - $s, number_format((int) $t['mb'], 0, ',', ' ')));
    sleep(5); // laisser la base respirer entre deux tables
}
out('Terminé.');
