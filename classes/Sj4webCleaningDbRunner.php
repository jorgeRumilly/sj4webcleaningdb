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

    public function __construct($originTag = null, $translator = null)
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;

        $this->enabledTables = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_ENABLED_TABLES'), true) ?? [];
        $this->retentionDays = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_RETENTION'), true) ?? [];

        $this->isCleaningEnabled = (bool)Configuration::get('SJ4WEB_CLEANINGDB_ENABLED');
        $this->isOptimizeEnabled = (bool)Configuration::get('SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED');

        $this->originTag = $originTag ?: $this->detectOriginTag();

        $logDir = _PS_MODULE_DIR_ . 'sj4webcleaningdb/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $this->logFile = $logDir . date('Y-m-d') . '.log';
        if($translator) {
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
        $entries = [];
        $tableSizesToLog = [];
        $now = date('Y-m-d H:i:s');

        if (!$this->isCleaningEnabled) {
            $entries[] = $this->originTag . " [$now] Nettoyage désactivé globalement.";
//            $entries[] = $this->originTag . " " . $this->translator->trans('[$now] Cleanup globally disabled.', [], 'Modules.Sj4webcleaningdb.Admin');
        } else {
            foreach ($this->enabledTables as $table) {
                $full = $this->prefix . $table;
                // $beforeSize = $this->getTableSize($full);
                $beforeSize = TableCleanerHelper::getTableSize($this->db, $full, _DB_NAME_);
                switch ($table) {
                    case 'connections':
                        $entries[] = $this->deleteOldConnection($full, $this->retentionDays[$table] ?? 90, $now);
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " [$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                        $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                        break;

                    case 'pagenotfound':
                    case 'statssearch':
                        $entries[] = $this->deleteOldByDate($full, $this->retentionDays[$table] ?? 90, $now);
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " [$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                    $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                        break;

                    case 'cart':
                        $entries[] = $this->deleteOldCarts($full, $this->retentionDays[$table] ?? 180, $now);
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " [$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                        $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                        break;

                    case 'connections_source':
                        $entries[] = $this->deleteOrphans($full, $this->prefix . 'connections', 'id_connections', $now);
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " [$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                        $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                        break;

                    case 'guest':
                        $entries[] = $this->deleteOrphansGuest($full, $this->prefix . 'connections', 'id_guest', $now);
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " " . "[$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                        $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                        break;

                    case 'cart_product':
                        $entries[] = $this->deleteOrphans($full, $this->prefix . 'cart', 'id_cart', $now);
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " [$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                        $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                        break;

                    default:
//                        $entries[] = $this->originTag . " [$now] Table $full | Aucun traitement défini.";
                        $entries[] = $this->originTag . " "  . $this->translator->trans('[%now%] Table %full% | No processing defined.', ['%now%'=> $now, '%full%' => $full], 'Modules.Sj4webcleaningdb.Admin');
                        $tableSizesToLog[$table] = ['index' => count($entries), 'before' => $beforeSize];
                        $entries[] = $this->originTag . " [$now] Table $full | Taille avant : {$beforeSize} Mo | Taille après : ... Mo";
//                        $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: ... MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $beforeSize], 'Modules.Sj4webcleaningdb.Admin');
                }
            }
        }

        if ($this->isOptimizeEnabled) {
            $entries = array_merge($entries, $this->optimizeTables($now));
        } else {
            $entries[] = $this->originTag . " [$now] Optimisation désactivée globalement.";
//            $entries[] = $this->originTag . " " . $this->translator->trans('[$now] Optimization globally disabled.', [], 'Modules.Sj4webcleaningdb.Admin');
        }

        foreach ($tableSizesToLog as $table => $info) {
            $full = $this->prefix . $table;
            // $afterSize = $this->getTableSize($full);
            $afterSize = TableCleanerHelper::getTableSize($this->db, $full, _DB_NAME_);
            $gain = round($info['before'] - $afterSize, 2);
            $entries[$info['index']] = $this->originTag . " [$now] Table $full | Taille avant : {$info['before']} Mo | Taille après : {$afterSize} Mo | Gain : {$gain} Mo";
//            $entries[$info['index']] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | Size before: %before% MB | Size after: %after% MB | Saved: %gain% MB', ['%now%'=> $now, '%full%' => $full, '%before%' => $info['before'], '%after%' => $afterSize, '%gain%' => $gain], 'Modules.Sj4webcleaningdb.Admin');
        }

        file_put_contents($this->logFile, implode("\n", $entries) . "\n", FILE_APPEND);

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
     * @return array
     */
    public function optimizeTables(string $now = null): array
    {
        $now = $now ?: date('Y-m-d H:i:s');
        $entries = [];

        foreach ($this->enabledTables as $table) {
            $full = $this->prefix . $table;
            try {
                TableCleanerHelper::optimizeAnalyse($this->db, $full);
                TableCleanerHelper::forceRebuild($this->db, $full);
                $res_flush = TableCleanerHelper::safeFlushTable($this->db, $full);
                $entries[] = sprintf('%s [%s] Table %s | Check + Analyse + Optimisation + Rebuild OK | Flush : %s', $this->originTag, $now, $full, $res_flush ? 'OK' : 'KO');
//                $entries[] = $this->translator->trans('%tag% [%now%] Table %full% | Check + Analyze + Optimize + Rebuild OK | Flush: %flush%', ['%tag%' => $this->originTag, '%now%' => $now, '%full%' => $full, '%flush%' => $res_flush ? 'OK' : 'KO'], 'Modules.Sj4webcleaningdb.Admin');
            } catch (Exception $e) {
                $entries[] = $this->originTag . " [$now] Table $full | Erreur OPTIMIZE : " . $e->getMessage();
//                $entries[] = $this->originTag . " " . $this->translator->trans('[%now%] Table %full% | OPTIMIZE error:', ['%now%'=> $now, '%full%' => $full], 'Modules.Sj4webcleaningdb.Admin') . $e->getMessage();
            }
        }

        file_put_contents($this->logFile, implode("\n", $entries) . "\n", FILE_APPEND);
        return $entries;
    }

    protected function deleteOldConnection(string $table, int $days, string $now): string
    {

        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $where = "`date_add` < '" . pSQL($dateLimit) . "' and `id_guest` NOT IN (SELECT DISTINCT `id_guest` FROM `{$this->prefix}guest` where id_customer > 0)";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;

        return $this->originTag . " [$now] Table $table | Suppression > $days jours | Supprimés : $count";
//        return $this->originTag . " " . $this->translator->trans('[%now%] Table %table% | Removed entries older than %days% days | Deleted: %count%', ['%now%'=> $now, '%table%' => $table, '%days%' => $days, '%count%' => $count], 'Modules.Sj4webcleaningdb.Admin');


    }

    /**
     * Delete old records by date
     * @param string $table
     * @param int $days
     * @param string $now
     * @return string
     */
    protected function deleteOldByDate(string $table, int $days, string $now): string
    {

        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $where = "`date_add` < '" . pSQL($dateLimit) . "'";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;

        return $this->originTag . " [$now] Table $table | Suppression > $days jours | Supprimés : $count";
//        return $this->originTag . " " . $this->translator->trans('[%now%] Table %table% | Suppression > %days% jours | Supprimés : %count%', ['%now%'=> $now, '%table%' => $table, '%days%' => $days, '%count%' => $count], 'Modules.Sj4webcleaningdb.Admin');


    }

    /**
     * Delete orphaned records
     * @param string $table
     * @param string $parentTable
     * @param string $foreignKey
     * @param string $now
     * @return string
     */
    protected function deleteOrphans(string $table, string $parentTable, string $foreignKey, string $now): string
    {
        $where = "`$foreignKey` NOT IN (SELECT DISTINCT `$foreignKey` FROM `$parentTable`)";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;

        return $this->originTag . " [$now] Table $table | Suppression orphelins ($foreignKey) | Supprimés : $count";
//        return $this->originTag . " " . $this->translator->trans('[%now%] Table %table% | Suppression orphelins (%foreignKey%) | Supprimés : %count%', ['%now%'=> $now, '%table%' => $table, '%foreignKey%' => $foreignKey, '%count%' => $count], 'Modules.Sj4webcleaningdb.Admin');


    }

    /**
     * Delete orphaned guest records
     * @param string $table
     * @param string $parentTable
     * @param string $foreignKey
     * @param string $now
     * @return string
     */
    protected function deleteOrphansGuest(string $table, string $parentTable, string $foreignKey, string $now): string
    {
        $where = "`$foreignKey` NOT IN (SELECT DISTINCT `$foreignKey` FROM `$parentTable`) and `id_customer` = 0";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;

        return $this->originTag . " [$now] Table $table | Suppression orphelins ($foreignKey) | Supprimés : $count";
//        return $this->originTag . " " . $this->translator->trans('[%now%] Table %table% | Removed orphans (%foreignKey%) | Deleted: %count%', ['%now%'=> $now, '%table%' => $table, '%foreignKey%' => $foreignKey, '%count%' => $count], 'Modules.Sj4webcleaningdb.Admin');


    }

    /**
     * Delete old carts
     * @param string $table
     * @param int $days
     * @param string $now
     * @return string
     */
    protected function deleteOldCarts(string $table, int $days, string $now): string
    {
        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $where = "`id_cart` NOT IN (SELECT DISTINCT `id_cart` FROM `{$this->prefix}orders`)
              AND `date_add` < '" . pSQL($dateLimit) . "'";

        $this->backupTableData($table, $where);
        $sql = "DELETE FROM `$table` WHERE $where";
        $count = $this->db->execute($sql) ? $this->db->Affected_Rows() : 0;

        return $this->originTag . " [$now] Table $table | Suppression paniers inactifs > $days jours | Supprimés : $count";
//        return $this->originTag . " " . $this->translator->trans('[%now%] Table %table% | Removed inactive carts older than %days% days | Deleted: %count%',['%now%'=> $now, '%table%' => $table,'%days%' => $days,'%count%' => $count,],'Modules.Sj4webcleaningdb.Admin');
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

}
