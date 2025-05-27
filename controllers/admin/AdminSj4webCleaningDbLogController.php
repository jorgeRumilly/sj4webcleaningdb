<?php
require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/TableCleanerHelper.php';

class AdminSj4webCleaningDbLogController extends ModuleAdminController
{
    private $logDir;

    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->module = Module::getInstanceByName('sj4webcleaningdb');
        $this->logDir = _PS_MODULE_DIR_ . 'sj4webcleaningdb/logs/';
    }

    public function initContent()
    {
        parent::initContent();

        $selected = Tools::getValue('log_date', date('Y-m-d'));
        $logFiles = $this->getLogDates();

        if (empty($logFiles)) {
            $selected = null;
            $logContent = '';
            $logSummary = [];
        } else {
            if (!array_key_exists($selected, $logFiles)) {
                $selected = key($logFiles);
            }
            $logContent = $this->readLogLines($selected);
            $logSummary = $this->getLogSummaryWithGain($logContent);
        }

        $this->context->smarty->assign([
            'log_files'   => $logFiles,
            'log_date'    => $selected,
            'log_content' => $logContent,
            'log_summary' => $logSummary,
            'form_action' => self::$currentIndex . '&token=' . $this->token,
        ]);

        // Récupération des stats des tables
        $tables = TableCleanerHelper::getCleanableTables(); // méthode déjà existante
        $tableStats = TableCleanerHelper::getTableStats($tables);

        $this->context->smarty->assign('table_stats', $tableStats);

        $this->content .= $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'sj4webcleaningdb/views/templates/admin/sj4web_cleaning_db_log/info-db.tpl');
        $this->content .= $this->renderForm();
        $this->content .= $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'sj4webcleaningdb/views/templates/admin/sj4web_cleaning_db_log/logs.tpl');
        $this->context->smarty->assign([
            'content' => $this->content,
        ]);
    }

    private function getLogDates(): array
    {
        $dates = [];

        if (!is_dir($this->logDir)) {
            return $dates;
        }

        foreach (glob($this->logDir . '*.log') as $file) {
            $filename = basename($file);
            $date = str_replace('.log', '', $filename);
            $dates[$date] = $date;
        }

        krsort($dates); // tri inverse (logs récents en haut)
        return $dates;
    }

    protected function readLogLines(string $date): array
    {
        $file = $this->logDir . $date . '.log';
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $formatted = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);

            if (!is_array($entry) || !isset($entry['type'])) {
                $formatted[] = htmlspecialchars($line);
                continue;
            }

            $translated = $this->trans(
                $this->getLogTranslationKey($entry['type']),
                $entry['context'],
                'Modules.Sj4webcleaningdb.Admin'
            );

            $formatted[] = sprintf('%s [%s] %s',
                htmlspecialchars($entry['origin'] ?? ''),
                htmlspecialchars($entry['timestamp']),
                $translated
            );
        }

        return $formatted;
    }


    /**
     * Analyse un contenu de log et retourne un tableau synthétique par table
     * avec suppressions, optimisation, origine, tailles avant/après, et gain.
     *
     * @param array $logLines Contenu brut du log.
     * @return array Tableau associatif structuré par table.
     */
    protected function getLogSummaryWithGain(array $logLines): array
    {
        $summary = [];

        foreach ($logLines as $line) {
            // Skip already translated string lines
            if (!str_starts_with($line, '{')) {
                continue;
            }

            $entry = json_decode($line, true);
            if (!is_array($entry) || !isset($entry['type'])) {
                continue;
            }

            $ctx = $entry['context'];
            $type = $entry['type'];
            $table = $ctx['table'] ?? null;
            if (!$table) continue;

            $summary[$table] ??= ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0.0, 'after' => 0.0];

            if (!empty($entry['origin']) && !in_array($entry['origin'], $summary[$table]['tags'])) {
                $summary[$table]['tags'][] = trim($entry['origin'], '[]');
            }

            switch ($type) {
                case 'rows_deleted_by_age':
                case 'orphans_deleted':
                case 'old_carts_deleted':
                    $summary[$table]['delete'] += (int)($ctx['deleted'] ?? 0);
                    break;

                case 'table_optimized':
                    $summary[$table]['optimize'] = true;
                    break;

                case 'table_size_info':
                    $summary[$table]['before'] = (float)($ctx['before'] ?? 0.0);
                    $summary[$table]['after'] = (float)($ctx['after'] ?? 0.0);
                    break;
            }
        }

        foreach ($summary as &$row) {
            $row['gain'] = round($row['before'] - $row['after'], 2);
        }

        ksort($summary);
        return $summary;
    }


//    private function getLogSummaryWithGain(string $logContent): array
//    {
//        $summary = [];
//
//        foreach (explode("\n", $logContent) as $line) {
//            // Suppressions
//            if (preg_match('#(?:\[(CRON|BO|MANUEL)\]\s+)?\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+Table (\w+)\s+\| Suppression.*?\| Supprimés : (\d+)#', $line, $m)) {
//                [$tag, $table, $count] = [$m[1] ?? null, $m[2], (int)$m[3]];
//                if (!isset($summary[$table])) {
//                    $summary[$table] = ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0.00, 'after' => 0.00];
//                }
//                $summary[$table]['delete'] += $count;
//                if ($tag && !in_array($tag, $summary[$table]['tags'])) {
//                    $summary[$table]['tags'][] = $tag;
//                }
//            }
//
//            // Optimisation
//            if (preg_match('#(?:\[(CRON|BO|MANUEL)\]\s+)?\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+Table (\w+)\s+\| Optimisée#', $line, $m)) {
//                [$tag, $table] = [$m[1] ?? null, $m[2]];
//                if (!isset($summary[$table])) {
//                    $summary[$table] = ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0, 'after' => 0];
//                }
//                $summary[$table]['optimize'] = true;
//                if ($tag && !in_array($tag, $summary[$table]['tags'])) {
//                    $summary[$table]['tags'][] = $tag;
//                }
//            }
//
//            $line = str_replace('è', 'e', $line);
//            // Taille avant (garder la 1ère valeur seulement)
//            if (preg_match('#Table (\w+)\s+\| Taille avant : ([0-9.]+) Mo#', $line, $m)) {
//                $table = $m[1];
//                $val = (float) $m[2];
//                if (!isset($summary[$table]['before']) || $summary[$table]['before'] === 0.0) {
//                    $summary[$table]['before'] = $val;
//                }
//            }
//
//            // Taille après (écraser à chaque fois pour garder la dernière)
//            if (preg_match('#Table (\w+).*?Taille apres : ([0-9.]+) Mo#', $line, $m)) {
//                $summary[$m[1]]['after'] = (float) $m[2];
//            }
//        }
//
//        // Ajout du gain
//        foreach ($summary as $table => &$data) {
//            $data['gain'] = round($data['before'] - $data['after'], 2);
//        }
//
//        ksort($summary);
//        return $summary;
//    }

    protected function getLogTranslationKey(string $type): string
    {
        return match ($type) {
            'table_size_info' => 'Table "%table%" | Size before: %before% MB | Size after: %after% MB | Saved: %gain% MB',
            'rows_deleted_by_age' => 'Table "%table%" | Deleted rows older than %days% days: %deleted%',
            'orphans_deleted' => 'Table "%table%" | Orphan cleanup (%foreign_key%): %deleted% removed',
            'old_carts_deleted' => 'Table "%table%" | Inactive carts deleted (> %days% days): %deleted%',
            'optimization_disabled' => 'Optimization is globally disabled.',
            'table_optimized' => 'Table "%table%" | Optimization completed (Flush: %flush%)',
            'optimize_error'  => 'Table "%table%" | Optimization error: %error%',
            'no_action_defined' => 'Table "%table%" | No cleanup rule defined.',
            'cleaning_disabled_globally' => 'Cleanup is globally disabled.',
            default => '[Unknown log entry]'
        };
    }

    public function renderForm()
    {
        $logFiles = $this->getLogDates();
        $selected = Tools::getValue('log_date', date('Y-m-d'));

        $options = [];
        foreach ($logFiles as $date => $file) {
            $options[] = ['id_option' => $date, 'name' => $date];
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Log viewer', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'icon'  => 'icon-search'
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->trans('Log date', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name'  => 'log_date',
                        'options' => [
                            'query' => $options,
                            'id'    => 'id_option',
                            'name'  => 'name'
                        ],
                        'desc' => $this->trans('Choose the log date to display.', [], 'Modules.Sj4webcleaningdb.Admin'),
                    ]
                ],
                'submit' => [
                    'title' => $this->trans('Display logs', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'name'  => 'submit_view_logs'
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->token = $this->token;
        $helper->currentIndex = self::$currentIndex . '&configure=' . $this->module->name . '&controller=AdminSj4webCleaningDbLog';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->show_cancel_button = false;
        $helper->toolbar_scroll = false;

        $helper->fields_value = [
            'log_date' => $selected,
        ];

        return $helper->generateForm([$fields_form]);
    }

}
