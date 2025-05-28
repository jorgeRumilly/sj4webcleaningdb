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
        } catch (Exception $e) {
            throw new Exception('Error optimizing table: ' . $e->getMessage());
        }
    }

    /**
     * Calcule la taille d'une table (data + index) en Mo.
     *
     * @param Db $db Instance de connexion PrestaShop (Db::getInstance()).
     * @param string $table Nom de la table (sans backticks).
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
            $dataLength = (int)$rows[0]['DATA_LENGTH'];
            $indexLength = (int)$rows[0]['INDEX_LENGTH'];
            return round(($dataLength + $indexLength) / 1048576, 2); // en Mo
        }

        return 0.0;
    }


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

    /**
     * Récupère les stats (taille, lignes) pour un tableau de tables.
     *
     * @param array $tables Noms de tables SANS préfixe
     * @return array Tableau associatif : [nom_table => ['rows' => int, 'size' => float]]
     */
    public static function getTableStats(array $tables): array
    {
        $prefix = _DB_PREFIX_;
        $dbName = _DB_NAME_;
        $stats = [];

        if (empty($tables)) {
            return $stats;
        }

        $tableList = array_map(function ($t) use ($prefix) {
            return $prefix . $t;
        }, $tables);

        $inList = implode("','", array_map('pSQL', $tableList));

        $sql = "
        SELECT 
            table_name, 
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            table_rows
        FROM information_schema.tables
        WHERE table_schema = '" . pSQL($dbName) . "'
        AND table_name IN ('$inList')
    ";

        $results = Db::getInstance()->executeS($sql);

        foreach ($results as $row) {
            $table = str_replace($prefix, '', ($row['table_name'] ?? $row['TABLE_NAME']));
            $stats[$table] = [
                'rows' => (int)($row['table_rows'] ?? $row['TABLE_ROWS']),
                'size' => (float)($row['size_mb'] ?? $row['SIZE_MB']),
            ];
        }

        return $stats;
    }

    /**
     * Liste des tables nettoyables sans le préfixe
     *
     * @return array
     */
    public static function getCleanableTables(): array
    {
        return array_keys(self::getTablesConfig());
    }

    /**
     * Liste des tables nettoyables avec le préfixe
     *
     * @return array
     */
    public static function getTablesConfig(): array
    {
        return [
            'connections' => ['label' => 'Connections', 'clean_type' => 'date'],
            'connections_source' => ['label' => 'Connections source', 'clean_type' => 'orphan'],
            'guest' => ['label' => 'Guests', 'clean_type' => 'orphan'],
            'cart' => ['label' => 'Paniers', 'clean_type' => 'date'],
            'cart_product' => ['label' => 'Produits panier', 'clean_type' => 'orphan'],
            'pagenotfound' => ['label' => 'Pages 404', 'clean_type' => 'date'],
            'statssearch' => ['label' => 'Recherches', 'clean_type' => 'date'],
//            'mail' => ['label' => 'Mails', 'clean_type' => 'date'],
//            'log' => ['label' => 'Logs', 'clean_type' => 'date'],
        ];
    }

    /**
     * Récupère la ou les adresses e-mail de destination pour l'envoi du rapport de nettoyage.
     * Si désactivé ou aucun e-mail trouvé, renvoie un tableau vide.
     *
     * @return string[] Tableau d'adresses e-mail
     */
    public static function getCleaningReportEmails(): array
    {

        $rawEmails = trim((string)Configuration::get('SJ4WEB_CLEANINGDB_MAIL_RECIPIENTS'));
        if (!$rawEmails) {
            $default = Configuration::get('PS_SHOP_EMAIL');
            return $default ? [$default] : [];
        }

        // Support de plusieurs e-mails séparés par virgule ou retour à la ligne
        $emails = preg_split('/[\s,]+/', $rawEmails, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($emails, function ($e) {
            return Validate::isEmail($e);
        });
    }

    /**
     * Envoie un email de rapport de nettoyage à la fin du processus.
     *
     * @param array $deletedByTable Tableau associatif [nom_table => nb_lignes_supprimées]
     * @param string|null $date Date du nettoyage (format 'Y-m-d H:i:s'), ou null pour maintenant
     */
    public static function sendCleaningReportEmail(array $deletedByTable, ?string $date = null, $translator = null): void
    {

        $recipients = self::getCleaningReportEmails();
        if (empty($recipients)) {
            return;
        }

        $shopName = Configuration::get('PS_SHOP_NAME');
        $psVersion = _PS_VERSION_;
        $dateText = $date ?: date('Y-m-d H:i:s');

        // Construction des lignes HTML/TXT
        $rowsHtml = '';
        $rowsTxt = '';
        foreach ($deletedByTable as $table => $deleted) {
            $escaped = htmlspecialchars($table);
            $rowsHtml .= "<tr><td>{$escaped}</td><td>{$deleted}</td></tr>\n";
            $rowsTxt .= "{$table}: {$deleted}\n";
        }

        // Données pour Mail::Send
        $templateVars = [
            '{shop_name}' => $shopName,
            '{date}' => $dateText,
            '{cleaning_date}' => $dateText, // au cas où
            '{rows}' => $rowsHtml,
            '{ps_version}' => $psVersion,
            '{cleaning_summary}' => $rowsHtml, // fallback
        ];

        $object = $shopName . ' ' . (($translator) ? $translator->trans('– Cleaning Report', [], 'Modules.Sj4webcleaningdb.Admin') : ' – Cleaning Report');

        foreach ($recipients as $email) {
            Mail::Send(
                (int)Context::getContext()->language->id,
                'cleaning_report',
                $object,
                $templateVars,
                $email,
                null, // nom destinataire
                Configuration::get('PS_SHOP_EMAIL'), Configuration::get('PS_SHOP_NAME'), // expéditeur
                null, null, // attachements
                _PS_MODULE_DIR_ . 'sj4webcleaningdb/mails/',
                false,
                (int)Context::getContext()->shop->id
            );
        }
    }

}
