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
            $logContent = [];
            $logRawLines = [];
            $logSummary = [];
        } else {
            if (!array_key_exists($selected, $logFiles)) {
                $selected = key($logFiles);
            }
            $logContent = $this->readLogLines($selected);
            $logRawLines = $this->getRawLinesFromLogContent($logContent);
            $logSummary = $this->getLogSummaryWithGain($logContent);
        }

        $this->context->smarty->assign([
            'log_files' => $logFiles,
            'log_date' => $selected,
            'log_raw_lines' => $logRawLines,
            'log_summary' => $logSummary,
            'log_content' => array_column($logContent, 'translated'),
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

    /**
     * Lit les lignes de log JSON et retourne un tableau associatif
     * contenant les données d'origine + le texte traduit.
     *
     * @param string $date Format YYYY-MM-DD
     * @return array
     */
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

            // Ligne invalide (non JSON), on la garde comme texte brut
            if (!is_array($entry) || !isset($entry['type'])) {
                $formatted[] = [
                    'raw' => $line,
                    'translated' => htmlspecialchars($line),
                    'type' => 'invalid',
                ];
                continue;
            }

            $context = [];
            foreach ($entry['context'] as $k => $v) {
                $context['%' . $k . '%'] = $v;
            }

            $translated = $this->trans(
                $this->getLogTranslationKey($entry['type']),
                $context,
                'Modules.Sj4webcleaningdb.Admin'
            );

            $formatted[] = [
                'raw' => $entry,
                'translated' => sprintf('%s [%s] %s',
                    htmlspecialchars($entry['origin'] ?? ''),
                    htmlspecialchars($entry['timestamp']),
                    $translated
                ),
                'type' => $entry['type'],
            ];
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
    /**
     * Résume les opérations de nettoyage depuis les logs (au format enrichi).
     *
     * @param array $logLines Tableau de lignes issues de readLogLines()
     * @return array Résumé par table
     */
    protected function getLogSummaryWithGain(array $logLines): array
    {
        $summary = [];

        foreach ($logLines as $entry) {
            if (!isset($entry['raw']) || !is_array($entry['raw'])) {
                continue;
            }

            $raw = $entry['raw'];
            $type = $raw['type'] ?? null;
            $ctx = $raw['context'] ?? [];
            $table = $ctx['table'] ?? null;
            if (!$type || !$table) {
                continue;
            }

            $summary[$table] ??= [
                'delete'   => 0,
                'optimize' => false,
                'tags'     => [],
                'before'   => 0.0,
                'after'    => 0.0,
            ];

            $origin = trim($raw['origin'] ?? '', '[]');
            if ($origin && !in_array($origin, $summary[$table]['tags'], true)) {
                $summary[$table]['tags'][] = $origin;
            }

            switch ($type) {
                case 'rows_deleted_by_age':
                case 'orphans_deleted':
                case 'old_carts_deleted':
                    $summary[$table]['delete'] += (int)($ctx['deleted'] ?? $ctx['deleted_count'] ?? 0);
                    break;

                case 'table_optimized':
                    $summary[$table]['optimize'] = true;
                    break;

                case 'table_size_info':
                    $summary[$table]['before'] += (float)($ctx['before'] ?? 0.0);
                    $summary[$table]['after']  += (float)($ctx['after'] ?? 0.0);
                    break;
            }
        }

        foreach ($summary as &$row) {
            $row['before'] = round($row['before'], 2);
            $row['after']  = round($row['after'], 2);
            $row['gain']   = round($row['before'] - $row['after'], 2);
        }

        ksort($summary);
        return $summary;
    }


    /**
     * Retourne la chaîne de traduction associée à un type de log structuré
     *
     * @param string $type
     * @return string
     */
    protected function getLogTranslationKey(string $type): string
    {
        switch ($type) {
            case 'table_size_info':
                return 'Table "%table%" | Size before: %before% MB | Size after: %after% MB | Saved: %gain% MB';
            case 'rows_deleted_by_age':
                return 'Table "%table%" | Deleted rows older than %days% days: %deleted%';
            case 'orphans_deleted':
                return 'Table "%table%" | Orphan cleanup (%foreign_key%): %deleted% removed';
            case 'old_carts_deleted':
                return 'Table "%table%" | Inactive carts deleted (> %days% days): %deleted%';
            case 'optimization_disabled':
                return 'Optimization is globally disabled.';
            case 'table_optimized':
                return 'Table "%table%" | Optimization completed (Flush: %flush%)';
            case 'optimize_error':
                return 'Table "%table%" | Optimization error: %error%';
            case 'no_action_defined':
                return 'Table "%table%" | No cleanup rule defined.';
            case 'cleaning_disabled_globally':
                return 'Cleanup is globally disabled.';
            default:
                return '[Unknown log entry]';
        }
    }

    /**
     * Extrait les lignes brutes (texte JSON ou autre) depuis le tableau enrichi du log.
     *
     * @param array $logContent
     * @return array
     */
    protected function getRawLinesFromLogContent(array $logContent): array
    {
        $rawLines = [];

        foreach ($logContent as $entry) {
            if (isset($entry['raw']) && is_array($entry['raw'])) {
                $rawLines[] = json_encode($entry['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (isset($entry['raw'])) {
                $rawLines[] = (string) $entry['raw'];
            }
        }

        return $rawLines;
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
                    'icon' => 'icon-search'
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->trans('Log date', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name' => 'log_date',
                        'options' => [
                            'query' => $options,
                            'id' => 'id_option',
                            'name' => 'name'
                        ],
                        'desc' => $this->trans('Choose the log date to display.', [], 'Modules.Sj4webcleaningdb.Admin'),
                    ]
                ],
                'submit' => [
                    'title' => $this->trans('Display logs', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'name' => 'submit_view_logs'
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->token = $this->token;
        $helper->currentIndex = self::$currentIndex . '&configure=' . $this->module->name . '&controller=AdminSj4webCleaningDbLog';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->show_cancel_button = false;
        $helper->toolbar_scroll = false;

        $helper->fields_value = [
            'log_date' => $selected,
        ];

        return $helper->generateForm([$fields_form]);
    }

}
