<?php
require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/TableCleanerHelper.php';

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Sj4webCleaningDbRunner
{
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

    public function __construct($originTag = null, $translator = null)
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;

        $this->enabledTables = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_ENABLED_TABLES'), true) ?? [];
        $this->retentionDays = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_RETENTION'), true) ?? [];

        $this->isCleaningEnabled = (bool)Configuration::get('SJ4WEB_CLEANINGDB_ENABLED');
        $this->isOptimizeEnabled = (bool)Configuration::get('SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED');

        $this->notificationEmail = (bool)Configuration::get('SJ4WEB_CLEANINGDB_MAIL_ENABLE');


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
                $tableReportMail[$table] = $nbLines;
            }
        }

        if ($this->isOptimizeEnabled) {
            $this->optimizeTables($now);
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
     * Backup table data before deletion
     * @param string $table
     * @param string $whereClause
     * @return void
     */
    private function backupTableData(string $table, string $whereClause): void
    {
        if (!(int)Configuration::get('SJ4WEB_CLEANINGDB_ENABLE_BACKUP')) {
            return;
        }

        $backupTable = $this->prefix . 'save_' . str_replace($this->prefix, '', $table);

        $this->db->execute("CREATE TABLE IF NOT EXISTS `$backupTable` LIKE `$table`");
        $this->db->execute("INSERT INTO `$backupTable` SELECT * FROM `$table` WHERE $whereClause");
    }

    /**
     * Optimize and analyze tables
     * @param string|null $now
     */
    public function optimizeTables(string $now = null)
    {
        $now = $now ?: date('Y-m-d H:i:s');

        foreach ($this->enabledTables as $table) {
            $full = $this->prefix . $table;
            try {
                TableCleanerHelper::optimizeAnalyse($this->db, $full);
                TableCleanerHelper::forceRebuild($this->db, $full);
                $res_flush = TableCleanerHelper::safeFlushTable($this->db, $full);
                $this->logStructured('table_optimized', ['table' => $full, 'flush' => $res_flush ? 'OK' : 'KO'], $now);
            } catch (Exception $e) {
                $this->logStructured('optimize_error', ['table' => $full, 'error' => $e->getMessage()], $now);
            }
        }
    }

    protected function deleteOldConnection(string $table, int $days, string $now): int
    {

        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $where = "`date_add` < '" . pSQL($dateLimit) . "' and `id_guest` NOT IN (SELECT DISTINCT `id_guest` FROM `{$this->prefix}guest` where id_customer > 0)";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;
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
        $where = "`date_add` < '" . pSQL($dateLimit) . "'";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;
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
        $where = "`$foreignKey` NOT IN (SELECT DISTINCT `$foreignKey` FROM `$parentTable`)";
        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;
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
        $where = "`$foreignKey` NOT IN (SELECT DISTINCT `$foreignKey` FROM `$parentTable`) and `id_customer` = 0";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;
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
        $where = "`id_cart` NOT IN (SELECT DISTINCT `id_cart` FROM `{$this->prefix}orders`)
              AND `date_add` < '" . pSQL($dateLimit) . "'";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;
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
