<?php

class TableCleanerHelper
{


    /**
     * Optimize a table by running ANALYZE, CHECK, and OPTIMIZE commands
     * @param Db $db
     * @param string $table
     * @throws Exception
     */
    public static function optimizeAnalyse($db, $table)
    {
        try {
            $db->execute("OPTIMIZE TABLE `$table`");
            $db->execute("ANALYZE TABLE `$table`");
            $db->execute("SELECT COUNT(*) FROM `$table`");
//            $stmt1 = $db->query("ANALYZE TABLE `$table`");
//            $stmt1->fetchAll();
//            $stmt2 = $db->query("CHECK TABLE `$table`");
//            $stmt2->fetchAll();
//            $stmt3 = $db->query("OPTIMIZE TABLE `$table`");
//            $stmt3->fetchAll();
        } catch (Exception $e) {
            throw new Exception('Error optimizing table: ' . $e->getMessage());
        }
    }

    /**
     * Calcule la taille d'une table (data + index) en Mo.
     *
     * @param Db     $db       Instance de connexion PrestaShop (Db::getInstance()).
     * @param string $table    Nom de la table (sans backticks).
     * @param string $database Nom de la base (ex: _DB_NAME_).
     *
     * @return float Taille en Mo, arrondie à 2 décimales.
     */
    public static function getTableSize(Db $db, string $table, string $database): float
    {
        $sql = '
        SELECT DATA_LENGTH, INDEX_LENGTH
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = "' . pSQL($database) . '"
          AND TABLE_NAME = "' . pSQL($table) . '"
        LIMIT 1';

        $rows = $db->executeS($sql);

        if (!empty($rows[0])) {
            $dataLength  = (int) $rows[0]['DATA_LENGTH'];
            $indexLength = (int) $rows[0]['INDEX_LENGTH'];
            return round(($dataLength + $indexLength) / 1048576, 2); // en Mo
        }

        return 0.0;
    }


//    /**
//     * Get the size of a table in MB
//     * @param Db $db
//     * @param string $table
//     * @param string $database
//     * @return float
//     * @throws PrestaShopDatabaseException
//     */
//    public static function getTableSize($db, string $table, string $database): float
//    {
//        $sql = "
//        SELECT data_length, index_length
//        FROM information_schema.tables
//        WHERE table_schema = '" . pSQL($database) . "'
//          AND table_name = '" . pSQL($table) . "'
//        LIMIT 1";
//
//        $rows = $db->executeS($sql);
//
//        if (!empty($rows[0])) {
//            $data = $rows[0];
//            $data_length = ($data['data_length']) ?? $data['DATA_LENGTH'];
//            $index_length = ($data['index_length']) ?? $data['INDEX_LENGTH'];
//            return round(((float)((int) $data_length + (int)$index_length) / 1048576), 2);
//        }
//
//        return 0.0;
//    }

    /**
     * Force un rebuild physique de la table via ALTER TABLE ENGINE=InnoDB
     * @param Db $db
     * @param string $table
     * → utile pour réellement purger l'espace disque sous InnoDB
     *
     */
    public static function forceRebuild($db, $table): void
    {
        try {
            $sql = "ALTER TABLE `{$table}` ENGINE=InnoDB";
            $db->execute($sql);
        } catch (Exception $e) {
            throw new Exception('Error forcing rebuild of table: ' . $e->getMessage());
        }
    }

    /**
     * Tente un FLUSH TABLE sécurisé (non bloquant).
     * Ne fait rien si la table semble utilisée.
     * @param Db $db
     * @param string $table Nom complet (avec préfixe)
     * @return bool true si flush exécuté, false sinon
     */
    public static function safeFlushTable(Db $db, string $table): bool
    {
        try {
            // Test léger pour détecter si la table répond
            $sql = "SELECT 1 FROM `$table` LIMIT 1";
            $db->execute($sql); // Si erreur ici, la table est verrouillée

            // Si la requête passe sans bloquer → flush safe
            $db->execute("FLUSH TABLE `$table`");

            return true;
        } catch (Exception $e) {
            // On ne fait rien si bloquant ou erreur (concurrent ou autre)
            // On peut logguer si besoin
            return false;
        }
    }
}
