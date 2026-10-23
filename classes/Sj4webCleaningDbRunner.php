<?php
require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/TableCleanerHelper.php';

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Sj4webCleaningDbRunner
{
    /** Nombre de lignes examinées par lot de suppression. */
    const DEFAULT_BATCH_SIZE = 5000;
    /** Durée maximale d'un passage (le verrou CLI expire à 30 min). */
    const DEFAULT_MAX_MINUTES = 25;
    /** Pause entre deux lots (microsecondes) pour laisser respirer la base partagée. */
    const BATCH_PAUSE_US = 150000;
    /** Au-delà de ce nombre de requêtes actives sur MariaDB, on temporise entre deux lots. */
    const MAX_THREADS_RUNNING = 40;
    /** OPTIMIZE TABLE seulement si au moins autant de lignes ont été supprimées dans la table. */
    const OPTIMIZE_MIN_DELETED = 100000;

    protected $db;
    protected $prefix;
    protected $enabledTables;
    protected $retentionDays;
    protected $logFile;
    protected $isCleaningEnabled;
    protected $isOptimizeEnabled;
    protected $originTag;

    protected $translator;

    protected $notificationEmail;

    /** @var int Timestamp au-delà duquel on arrête proprement les suppressions. */
    protected $deadline;
    /** @var int */
    protected $batchSize;

    public function __construct($originTag = null, $translator = null)
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;

        $this->enabledTables = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_ENABLED_TABLES'), true) ?? [];
        $this->retentionDays = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_RETENTION'), true) ?? [];

        $this->isCleaningEnabled = (bool)Configuration::get('SJ4WEB_CLEANINGDB_ENABLED');
        $this->isOptimizeEnabled = (bool)Configuration::get('SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED');

        $this->notificationEmail = (bool)Configuration::get('SJ4WEB_CLEANINGDB_MAIL_ENABLE');


        $this->batchSize = max(500, min(50000, (int) Configuration::get('SJ4WEB_CLEANINGDB_BATCH_SIZE') ?: self::DEFAULT_BATCH_SIZE));
        $maxMinutes = (int) Configuration::get('SJ4WEB_CLEANINGDB_MAX_MINUTES') ?: self::DEFAULT_MAX_MINUTES;
        $this->deadline = time() + max(1, $maxMinutes) * 60;

        $this->originTag = $originTag ?: $this->detectOriginTag();

        $logDir = _PS_MODULE_DIR_ . 'sj4webcleaningdb/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $this->logFile = $logDir . date('Y-m-d') . '.log';
        if ($translator) {
            $this->translator = $translator;
        } else {
            $this->translator = SymfonyContainer::getInstance()->get('translator');
        }

    }

    /**
     * Donne le tag d'origine de l'appel
     * @return string
     */
    private function detectOriginTag(): string
    {
        if (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR'])) {
            return '[CRON]';
        }
        if (defined('PS_ADMIN_DIR')) {
            return '[BO]';
        }
        return '[MANUEL]';
    }

    /**
     * Run the cleaning process based on the configuration
     * @return void
     */
    public function runFromConfig()
    {
        $tableSizesToLog = [];
        $tableReportMail = [];
        $now = date('Y-m-d H:i:s');

        if (!$this->isCleaningEnabled) {
            $this->logStructured('cleaning_disabled_globally', [], $now);
        } else {
            foreach ($this->enabledTables as $table) {
                $full = $this->prefix . $table;
                $beforeSize = TableCleanerHelper::getTableSize($this->db, $full, _DB_NAME_);
                $nbLines = 0;
                try {
                switch ($table) {
                    case 'connections':
                        $nbLines = $this->deleteOldConnection($full, $this->retentionDays[$table] ?? 90, $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                        break;
                    case 'pagenotfound':
                    case 'statssearch':
                        $nbLines = $this->deleteOldByDate($full, $this->retentionDays[$table] ?? 90, $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                        break;

                    case 'cart':
                        $nbLines = $this->deleteOldCarts($full, $this->retentionDays[$table] ?? 180, $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                        break;

                    case 'connections_source':
                        $nbLines = $this->deleteOrphans($full, $this->prefix . 'connections', 'id_connections', $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                        break;

                    case 'guest':
                        $nbLines = $this->deleteOrphansGuest($full, $this->prefix . 'connections', 'id_guest', $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                        break;

                    case 'cart_product':
                        $nbLines = $this->deleteOrphans($full, $this->prefix . 'cart', 'id_cart', $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                        break;

                    default:
                        $this->logStructured('no_action_defined', ['table' => $full,], $now);
                        $tableSizesToLog[$table] = ['before' => $beforeSize];
                }
                } catch (Exception $e) {
                    // Une table en erreur ne doit pas empêcher le nettoyage des suivantes.
                    $this->logStructured('table_error', ['table' => $full, 'error' => $e->getMessage()], $now);
                    $tableSizesToLog[$table] = ['before' => $beforeSize];
                }
                $tableReportMail[$table] = $nbLines;
            }
        }

        if ($this->isOptimizeEnabled) {
            // Reconstruire une table de plusieurs Go chaque nuit est inutile : seulement si le nettoyage a vraiment retiré des lignes.
            $this->optimizeTables($now, $tableReportMail);
        } else {
            $this->logStructured('optimization_disabled', [], $now);
        }

        foreach ($tableSizesToLog as $table => $info) {
            $full = $this->prefix . $table;
            $afterSize = TableCleanerHelper::getTableSize($this->db, $full, _DB_NAME_);
            $before = $this->formatFloat($info['before'], 2);
            $after = $this->formatFloat($afterSize, 2);
            $gain = $this->formatFloat($before - $after, 2);
            $this->logStructured('table_size_info', ['table' => $full, 'before' => $before, 'after' => $after, 'gain' => $gain,], $now);
        }

        if(!empty($tableReportMail) && $this->notificationEmail) {
            TableCleanerHelper::sendCleaningReportEmail($tableReportMail, $now, $this->translator);
        }
        $this->cleanupOldLogs();
    }

    /**
     * Copie dans la table d'archive save_* les lignes qui vont être supprimées (option de backup).
     * INSERT IGNORE : si une exécution précédente a copié des lignes sans pouvoir les supprimer,
     * les doublons sont ignorés au lieu de bloquer tout le nettoyage (erreur 1062).
     *
     * @param string $table
     * @param string $whereClause condition complète (plage de clés incluse)
     * @return void
     */
    private function backupTableData(string $table, string $whereClause): void
    {
        if (!(int)Configuration::get('SJ4WEB_CLEANINGDB_ENABLE_BACKUP')) {
            return;
        }

        $backupTable = $this->prefix . 'save_' . str_replace($this->prefix, '', $table);

        $this->db->execute("CREATE TABLE IF NOT EXISTS `$backupTable` LIKE `$table`");
        $this->db->execute("INSERT IGNORE INTO `$backupTable` SELECT * FROM `$table` WHERE $whereClause");
    }

    /**
     * Supprime par lots les lignes de $table qui répondent à $where.
     *
     * On parcourt la table par fenêtres de clé de $this->batchSize lignes : chaque DELETE ne touche donc qu'une
     * petite plage et ne bloque pas le site. Avec l'option de backup, la même plage est copiée avant d'être supprimée.
     * L'opération est reprenable : arrêtée par la limite de durée, elle reprend au passage suivant.
     *
     * @param string $table nom complet (avec préfixe)
     * @param string $key colonne de parcours (clé primaire, ou clé indexée pour cart_product)
     * @param string $where condition (utilise le nom complet de la table, pas d'alias)
     * @param string $upperSql requête SQL renvoyant (colonne m) la valeur maximale de $key à examiner
     * @return int nombre de lignes supprimées
     */
    protected function purgeInBatches(string $table, string $key, string $where, string $upperSql): int
    {
        $rows = $this->db->executeS($upperSql, true, false);
        $upper = !empty($rows[0]['m']) ? (int) $rows[0]['m'] : 0;
        if ($upper <= 0) {
            return 0;
        }

        $deleted = 0;
        $last = 0;
        while ($last < $upper) {
            if (time() >= $this->deadline) {
                $this->logStructured('purge_time_limit', ['table' => $table, 'deleted' => $deleted, 'last_key' => $last, 'upper_key' => $upper]);
                break;
            }
            $this->waitIfServerBusy();

            $win = $this->db->executeS(
                "SELECT MAX(`$key`) AS m FROM (SELECT `$key` FROM `$table` WHERE `$key` > " . (int) $last . " AND `$key` <= " . (int) $upper .
                " ORDER BY `$key` LIMIT " . (int) $this->batchSize . ') w',
                true,
                false
            );
            $max = !empty($win[0]['m']) ? (int) $win[0]['m'] : 0;
            if ($max <= 0) {
                break;
            }

            $range = "`$key` > " . (int) $last . " AND `$key` <= " . (int) $max . " AND ($where)";
            $this->backupTableData($table, $range);
            if ($this->db->execute("DELETE FROM `$table` WHERE $range")) {
                $deleted += (int) $this->db->Affected_Rows();
            }
            $last = $max;
            usleep(self::BATCH_PAUSE_US);
        }

        return $deleted;
    }

    /**
     * Temporise (max ~30 s) si MariaDB exécute déjà beaucoup de requêtes : on laisse la priorité aux visiteurs.
     */
    protected function waitIfServerBusy(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $res = $this->db->executeS("SHOW GLOBAL STATUS LIKE 'Threads_running'", true, false);
            $running = !empty($res[0]['Value']) ? (int) $res[0]['Value'] : 0;
            if ($running <= self::MAX_THREADS_RUNNING) {
                return;
            }
            sleep(2);
        }
    }

    /**
     * Optimise les tables.
     *
     * @param string|null $now
     * @param array|null $deletedByTable [table => lignes supprimées] : si fourni, seules les tables ayant perdu au moins
     *                                   OPTIMIZE_MIN_DELETED lignes sont optimisées. Sans argument (bouton manuel du BO),
     *                                   toutes les tables activées sont optimisées.
     */
    public function optimizeTables(string $now = null, ?array $deletedByTable = null)
    {
        $now = $now ?: date('Y-m-d H:i:s');

        foreach ($this->enabledTables as $table) {
            $full = $this->prefix . $table;
            if ($deletedByTable !== null && (int) ($deletedByTable[$table] ?? 0) < self::OPTIMIZE_MIN_DELETED) {
                continue;
            }
            try {
                // OPTIMIZE TABLE reconstruit déjà la table InnoDB et met à jour ses statistiques : pas de second rebuild.
                TableCleanerHelper::optimizeAnalyse($this->db, $full);
                $res_flush = TableCleanerHelper::safeFlushTable($this->db, $full);
                $this->logStructured('table_optimized', ['table' => $full, 'flush' => $res_flush ? 'OK' : 'KO'], $now);
            } catch (Exception $e) {
                $this->logStructured('optimize_error', ['table' => $full, 'error' => $e->getMessage()], $now);
            }
        }
    }

    /**
     * Clé de parcours d'une table (voir TableCleanerHelper::getTablesConfig).
     */
    protected function batchKey(string $table): string
    {
        $cfg = TableCleanerHelper::getTablesConfig();
        $short = str_replace($this->prefix, '', $table);

        return $cfg[$short]['batch_key'];
    }

    protected function deleteOldConnection(string $table, int $days, string $now): int
    {
        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $key = $this->batchKey($table);
        // On conserve les connexions des visiteurs liés à un client.
        $where = "`$table`.`date_add` < '" . pSQL($dateLimit) . "' AND NOT EXISTS (SELECT 1 FROM `{$this->prefix}guest` g WHERE g.`id_guest` = `$table`.`id_guest` AND g.`id_customer` > 0)";
        $upperSql = "SELECT MAX(`$key`) AS m FROM `$table` WHERE `date_add` < '" . pSQL($dateLimit) . "'";

        $count = $this->purgeInBatches($table, $key, $where, $upperSql);
        $this->logStructured('rows_deleted_by_age', ['table' => $table, 'days' => $days, 'deleted' => $count, 'date_limit' => $dateLimit], $now);
        return $count;
    }

    /**
     * Delete old records by date
     * @param string $table
     * @param int $days
     * @param string $now
     */
    protected function deleteOldByDate(string $table, int $days, string $now): int
    {
        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $key = $this->batchKey($table);
        $where = "`$table`.`date_add` < '" . pSQL($dateLimit) . "'";
        $upperSql = "SELECT MAX(`$key`) AS m FROM `$table` WHERE `date_add` < '" . pSQL($dateLimit) . "'";

        $count = $this->purgeInBatches($table, $key, $where, $upperSql);
        $this->logStructured('rows_deleted_by_age', ['table' => $table, 'days' => $days, 'deleted' => $count, 'date_limit' => $dateLimit], $now);
        return $count;
    }

    /**
     * Delete orphaned records
     * @param string $table
     * @param string $parentTable
     * @param string $foreignKey
     * @param string $now
     */
    protected function deleteOrphans(string $table, string $parentTable, string $foreignKey, string $now): int
    {
        $key = $this->batchKey($table);
        $where = "NOT EXISTS (SELECT 1 FROM `$parentTable` p WHERE p.`$foreignKey` = `$table`.`$foreignKey`)";
        $upperSql = "SELECT MAX(`$key`) AS m FROM `$table`";

        $count = $this->purgeInBatches($table, $key, $where, $upperSql);
        $this->logStructured('orphans_deleted', ['table' => $table, 'foreign_key' => $foreignKey, 'deleted' => $count,], $now);
        return $count;
    }

    /**
     * Delete orphaned guest records
     * @param string $table
     * @param string $parentTable
     * @param string $foreignKey
     * @param string $now
     */
    protected function deleteOrphansGuest(string $table, string $parentTable, string $foreignKey, string $now): int
    {
        $key = $this->batchKey($table);
        $where = "`$table`.`id_customer` = 0 AND NOT EXISTS (SELECT 1 FROM `$parentTable` p WHERE p.`$foreignKey` = `$table`.`$foreignKey`)";
        $upperSql = "SELECT MAX(`$key`) AS m FROM `$table`";

        $count = $this->purgeInBatches($table, $key, $where, $upperSql);
        $this->logStructured('orphans_deleted', ['table' => $table, 'foreign_key' => $foreignKey, 'deleted' => $count,], $now);
        return $count;
    }

    /**
     * Delete old carts
     * @param string $table
     * @param int $days
     * @param string $now
     */
    protected function deleteOldCarts(string $table, int $days, string $now): int
    {
        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $key = $this->batchKey($table);
        $where = "NOT EXISTS (SELECT 1 FROM `{$this->prefix}orders` o WHERE o.`id_cart` = `$table`.`id_cart`)
              AND `$table`.`date_add` < '" . pSQL($dateLimit) . "'";
        $upperSql = "SELECT MAX(`$key`) AS m FROM `$table` WHERE `date_add` < '" . pSQL($dateLimit) . "'";

        $count = $this->purgeInBatches($table, $key, $where, $upperSql);
        $this->logStructured('old_carts_deleted', ['table' => $table, 'days' => $days, 'deleted' => $count,], $now);
        return $count;
    }

    /**
     * Clean up old log files
     * @return void
     */
    protected function cleanupOldLogs(): void
    {
        $retention = (int)Configuration::get('SJ4WEB_CLEANINGDB_LOG_RETENTION');
        if ($retention <= 0) {
            return;
        }

        $cutoff = new DateTime("-{$retention} months");
        $logDir = _PS_MODULE_DIR_ . 'sj4webcleaningdb/logs/';
        foreach (glob($logDir . '*.log') as $file) {
            if (preg_match('#(\d{4}-\d{2}-\d{2})\.log$#', basename($file), $m)) {
                $logDate = DateTime::createFromFormat('Y-m-d', $m[1]);
                if ($logDate && $logDate < $cutoff) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Ajoute une entrée de log structurée dans le fichier (format JSON sérialisé)
     * @param string $type
     * @param array $context
     * @param string|null $now
     */
    protected function logStructured(string $type, array $context, ?string $now = null): void
    {
        $now = $now ?: date('Y-m-d H:i:s');

        // Post-traitement : on convertit tous les float en string formatée
        foreach ($context as $k => $v) {
            if (is_float($v)) {
                $context[$k] = number_format($v, 2, '.', '');
            }
        }

        $entry = [
            'timestamp' => $now,
            'origin' => $this->originTag,
            'type' => $type,
            'context' => $context
        ];

        file_put_contents($this->logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }


    /**
     * Formatage propre des valeurs flottantes (arrondi + nettoyage binaire)
     *
     * @param float|int|string $value
     * @param int $precision
     * @return float
     */
    protected function formatFloat($value, int $precision = 2): float
    {
        return (float) number_format((float) $value, $precision, '.', '');
    }

    /**
     * Run a fake execution for testing purposes
     * @return string
     */
    public function runFake():void
    {
        // Méthode de test pour simuler l'exécution sans toucher à la base de données
        $this->logStructured('fake_run', ['message' => 'This is a fake run for testing purposes.'], date('Y-m-d H:i:s'));
        echo "Fake run completed. Check the log file for details.\n";
    }

}
