<?php

require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/Sj4webCleaningDbRunner.php';
require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/TableCleanerHelper.php';

class AdminSj4webCleaningDbController extends ModuleAdminController
{
    public function __construct()
    {
        $this->module = Module::getInstanceByName('sj4webcleaningdb');
        $this->table = 'sj4webcleaningdb';
        $this->className = 'Sj4webCleaningDbRunner';
        $this->lang = false;
        $this->bootstrap = true;
        $this->explicitSelect = false;

        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign([
            'cron_url' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/sj4webcleaningdb/cron.php?token=' . Configuration::get('SJ4WEB_CLEANINGDB_CRON_TOKEN'),
            'link_to_logs'   => $this->context->link->getAdminLink('AdminSj4webCleaningDbLog'),
            'table_dependencies' => [
                'cart'        => ['cart_product'],
                'connections' => ['connections_source', 'guest'],
            ],
        ]);

        $this->content .= $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'sj4webcleaningdb/views/templates/admin/sj4web_cleaning_db/configure.tpl');
        $this->content .= $this->renderForm();
        $this->context->smarty->assign(['content' => $this->content,]);
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submit_sj4web_cleaning_config')) {
            Configuration::updateValue('SJ4WEB_CLEANINGDB_ENABLED', (int) Tools::getValue('cleaning_enabled'));
            Configuration::updateValue('SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED', (int) Tools::getValue('optimize_enabled'));
            Configuration::updateValue('SJ4WEB_CLEANINGDB_ENABLE_BACKUP', (int) Tools::getValue('enable_backup'));
            Configuration::updateValue('SJ4WEB_CLEANINGDB_LOG_RETENTION', (int) Tools::getValue('log_retention_months'));

            // Reconstruire enabled_tables à partir des checkbox individuelles
            $enabledTables = [];
            foreach (array_keys($this->getTablesConfig()) as $table) {
                if (Tools::getValue('enabled_tables_' . $table)) {
                    $enabledTables[] = $table;
                }
            }

            // Injecter les dépendances forcées
            $dependencies = [
                'connections' => ['connections_source', 'guest'],
                'cart'        => ['cart_product'],
            ];

            foreach ($dependencies as $parent => $children) {
                if (in_array($parent, $enabledTables)) {
                    foreach ($children as $child) {
                        if (!in_array($child, $enabledTables)) {
                            $enabledTables[] = $child;
                        }
                    }
                }
            }

            Configuration::updateValue('SJ4WEB_CLEANINGDB_ENABLED_TABLES', json_encode($enabledTables));

            // Enregistrer les jours de rétention
            Configuration::updateValue('SJ4WEB_CLEANINGDB_RETENTION', json_encode(Tools::getValue('retention_days', [])));

            $this->confirmations[] = $this->trans('Configuration saved.', [], 'Modules.Sj4webcleaningdb.Admin');
        }

        if (Tools::isSubmit('submit_sj4web_cleaning_run')) {
            (new Sj4webCleaningDbRunner())->runFromConfig();
            $this->confirmations[] = $this->trans('Cleanup completed. Check the logs.', [], 'Modules.Sj4webcleaningdb.Admin');
        }

        if (Tools::isSubmit('submit_sj4web_cleaning_optimize_only')) {
            (new Sj4webCleaningDbRunner())->optimizeTables();
            $this->confirmations[] = $this->trans('Optimization only completed.', [], 'Modules.Sj4webcleaningdb.Admin');
        }
    }


    public function renderForm()
    {
        $tables = $this->getTablesConfig();
        $enabledTables = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_ENABLED_TABLES'), true) ?? [];
        $retentionValues = json_decode(Configuration::get('SJ4WEB_CLEANINGDB_RETENTION'), true) ?? [];

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Database cleanup settings', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'icon'  => 'icon-database'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable automatic cleanup', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name' => 'cleaning_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on',  'value' => 1, 'label' => $this->trans('Yes', [], 'Modules.Sj4webcleaningdb.Admin')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->trans('No', [], 'Modules.Sj4webcleaningdb.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable table optimization', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name' => 'optimize_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on',  'value' => 1, 'label' => $this->trans('Yes', [], 'Modules.Sj4webcleaningdb.Admin')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->trans('No', [], 'Modules.Sj4webcleaningdb.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Backup tables before deletion', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name' => 'enable_backup',
                        'is_bool' => true,
                        'desc' => $this->trans('Creates a copy of deleted rows in a `_save_` table before deletion.', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'values' => [
                            ['id' => 'on',  'value' => 1, 'label' => $this->trans('Yes', [], 'Modules.Sj4webcleaningdb.Admin')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->trans('No', [], 'Modules.Sj4webcleaningdb.Admin')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save configuration', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'name' => 'submit_sj4web_cleaning_config'
                ],
                'buttons' => [
                    [
                        'title' => $this->trans('Clean now', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name'  => 'submit_sj4web_cleaning_run',
                        'type'  => 'submit',
                        'class' => 'btn btn-danger float-end',
                        'icon'  => 'process-icon-refresh',
                    ],
                    [
                        'title' => $this->trans('Optimize tables now', [], 'Modules.Sj4webcleaningdb.Admin'),
                        'name'  => 'submit_sj4web_cleaning_optimize_only',
                        'type'  => 'submit',
                        'class' => 'btn btn-secondary float-end me-2',
                        'icon'  => 'process-icon-database',
                    ],
                ]
            ]
        ];

        foreach ($tables as $table => $info) {
            $fields_form['form']['input'][] = [
                'type' => 'switch',
                'label' => $this->trans('Clean table "%s"', [$table], 'Modules.Sj4webcleaningdb.Admin'),
                'name'  => 'enabled_tables_' . $table,
                'is_bool' => true,
                'values' => [
                    [
                        'id'    => 'on',
                        'value' => 1,
                        'label' => $this->trans('Yes', [], 'Modules.Sj4webcleaningdb.Admin'),
                    ],
                    [
                        'id'    => 'off',
                        'value' => 0,
                        'label' => $this->trans('No', [], 'Modules.Sj4webcleaningdb.Admin'),
                    ],
                ],
                'desc' => $this->trans('Enable cleanup for table "%s".', [$table], 'Modules.Sj4webcleaningdb.Admin'),
            ];

            //, 'name' => $info['label']
            if ($info['clean_type'] === 'date') {
                $fields_form['form']['input'][] = [
                    'type' => 'text',
                    'label' => $this->trans('Retention period for "%s"', [$table], 'Modules.Sj4webcleaningdb.Admin'),
                    'name'  => 'retention_days[' . $table . ']',
                    'class' => 'fixed-width-sm',
                    'suffix' => $this->trans('days', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'desc'   => $this->trans('All rows older than this delay will be deleted (if the table is selected).', [], 'Modules.Sj4webcleaningdb.Admin'),
                    'min' => 0,
                    'step' => 1
                ];
            }
        }

        $fields_form['form']['input'][] = [
            'type' => 'html',
            'name' => 'html_data',
            'html_content' => '<p>&nbsp;</p><h3>'.$this->trans('Log archiving / deletion', [], 'Modules.Sj4webcleaningdb.Admin').'</h3>',
        ];
        $fields_form['form']['input'][] = [
            'type' => 'text',
            'label' => $this->trans('Log retention period (months)', [], 'Modules.Sj4webcleaningdb.Admin'),
            'name' => 'log_retention_months',
            'class' => 'fixed-width-sm',
            'desc' => $this->trans('Les fichiers de logs plus anciens seront automatiquement supprimés.', [], 'Modules.Sj4webcleaningdb.Admin'),
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->token = $this->token;
        $helper->currentIndex = self::$currentIndex . '&configure=' . $this->module->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->show_cancel_button = false;
        $helper->toolbar_scroll = false;

        $helper->fields_value = [
            'cleaning_enabled' => (int) Configuration::get('SJ4WEB_CLEANINGDB_ENABLED'),
            'optimize_enabled' => (int) Configuration::get('SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED'),
            'enable_backup' => (int) Configuration::get('SJ4WEB_CLEANINGDB_ENABLE_BACKUP'),
            'log_retention_months' => (int) Configuration::get('SJ4WEB_CLEANINGDB_LOG_RETENTION') ?: 3,
        ];

        foreach ($tables as $table => $info) {
            if ($info['clean_type'] === 'date' && $table !== 'cart') {
                $helper->fields_value['retention_days[' . $table . ']'] = $retentionValues[$table] ?? 90;
            } else if($table === 'cart') {
                $helper->fields_value['retention_days[' . $table . ']'] = $retentionValues[$table] ?? 180;
            }
        }

        foreach ($tables as $table => $info) {
            $helper->fields_value['enabled_tables_' . $table] = in_array($table, $enabledTables);
        }

        return $helper->generateForm([$fields_form]);
    }

    private function getTablesConfig()
    {
        return TableCleanerHelper::getTablesConfig();
    }
}
