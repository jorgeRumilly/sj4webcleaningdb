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
            $logContent = $this->readLog($selected);
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

    private function readLog(string $date): string
    {
        $file = $this->logDir . $date . '.log';
        if (!file_exists($file)) {
            return $this->trans('No log available for this date.', [], 'Modules.Sj4webcleaningdb.Admin');
        }

        return file_get_contents($file);
    }

    /**
     * Résume les lignes du fichier log en cumulant les suppressions
     * et en détectant si une optimisation a été faite pour chaque table.
     *
     * Les tags d'origine ([CRON], [BO], [MANUEL]) sont extraits s'ils existent.
     *
     * @param string $logContent Contenu brut du fichier .log
     * @return array Résumé des actions par table (delete, optimize, tag)
     */
    private function parseLogSummary(string $logContent): array
    {
        $summary = [];

        foreach (explode("\n", $logContent) as $line) {
            // Suppressions
            if (preg_match('#(?:\[(CRON|BO|MANUEL)\]\s+)?\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+Table (\w+)\s+\| Suppression.*?\| Supprimés : (\d+)#', $line, $m)) {
                [$tag, $table, $count] = [$m[1] ?? null, $m[2], (int)$m[3]];
                if (!isset($summary[$table])) {
                    $summary[$table] = ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0, 'after' => 0];
                }
                $summary[$table]['delete'] += $count;
                if ($tag && !in_array($tag, $summary[$table]['tags'])) {
                    $summary[$table]['tags'][] = $tag;
                }
            }

            // Optimisations
            if (preg_match('#(?:\[(CRON|BO|MANUEL)\]\s+)?\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+Table (\w+)\s+\| Optimisée#', $line, $m)) {
                [$tag, $table] = [$m[1] ?? null, $m[2]];
                if (!isset($summary[$table])) {
                    $summary[$table] = ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0, 'after' => 0];
                }
                $summary[$table]['optimize'] = true;
                if ($tag && !in_array($tag, $summary[$table]['tags'])) {
                    $summary[$table]['tags'][] = $tag;
                }
            }

            // Taille avant
            if (preg_match('#Table (\w+)\s+\| Taille avant : ([0-9.]+) Mo#', $line, $m)) {
                $table = $m[1];
                $val = (float) $m[2];
                $summary[$table]['before'] = ($summary[$table]['before'] ?? 0) + $val;
            }

            // Taille après
            if (preg_match('#Table (\w+)\s+\| Taille après : ([0-9.]+) Mo\b#', $line, $m)) {
                $table = $m[1];
                $val = (float) $m[2];
                $summary[$table]['after'] = ($summary[$table]['after'] ?? 0) + $val;
            }
        }

        ksort($summary);
        return $summary;
    }

    /**
     * Analyse un contenu de log et retourne un tableau synthétique par table
     * avec suppressions, optimisation, origine, tailles avant/après, et gain.
     *
     * @param string $logContent Contenu brut du log.
     * @return array Tableau associatif structuré par table.
     */
    private function getLogSummaryWithGain(string $logContent): array
    {
        $summary = [];

        foreach (explode("\n", $logContent) as $line) {
            // Suppressions
            if (preg_match('#(?:\[(CRON|BO|MANUEL)\]\s+)?\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+Table (\w+)\s+\| Suppression.*?\| Supprimés : (\d+)#', $line, $m)) {
                [$tag, $table, $count] = [$m[1] ?? null, $m[2], (int)$m[3]];
                if (!isset($summary[$table])) {
                    $summary[$table] = ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0.00, 'after' => 0.00];
                }
                $summary[$table]['delete'] += $count;
                if ($tag && !in_array($tag, $summary[$table]['tags'])) {
                    $summary[$table]['tags'][] = $tag;
                }
            }

            // Optimisation
            if (preg_match('#(?:\[(CRON|BO|MANUEL)\]\s+)?\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+Table (\w+)\s+\| Optimisée#', $line, $m)) {
                [$tag, $table] = [$m[1] ?? null, $m[2]];
                if (!isset($summary[$table])) {
                    $summary[$table] = ['delete' => 0, 'optimize' => false, 'tags' => [], 'before' => 0, 'after' => 0];
                }
                $summary[$table]['optimize'] = true;
                if ($tag && !in_array($tag, $summary[$table]['tags'])) {
                    $summary[$table]['tags'][] = $tag;
                }
            }

            $line = str_replace('è', 'e', $line);
            // Taille avant (garder la 1ère valeur seulement)
            if (preg_match('#Table (\w+)\s+\| Taille avant : ([0-9.]+) Mo#', $line, $m)) {
                $table = $m[1];
                $val = (float) $m[2];
                if (!isset($summary[$table]['before']) || $summary[$table]['before'] === 0.0) {
                    $summary[$table]['before'] = $val;
                }
            }

            // Taille après (écraser à chaque fois pour garder la dernière)
            if (preg_match('#Table (\w+).*?Taille apres : ([0-9.]+) Mo#', $line, $m)) {
                $summary[$m[1]]['after'] = (float) $m[2];
            }
        }

        // Ajout du gain
        foreach ($summary as $table => &$data) {
            $data['gain'] = round($data['before'] - $data['after'], 2);
        }

        ksort($summary);
        return $summary;
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
