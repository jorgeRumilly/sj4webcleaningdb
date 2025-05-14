<?php

require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/Sj4webCleaningDbRunner.php';

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
//        $this->context->smarty->assign([
//            'tables_config'    => $this->getTablesConfig(),
//            'enabled_tables'   => json_decode(Configuration::get('SJ4WEB_CLEANINGDB_ENABLED_TABLES'), true) ?? [],
//            'retention_values' => json_decode(Configuration::get('SJ4WEB_CLEANINGDB_RETENTION'), true) ?? [],
//        ]);

        $this->content .= $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'sj4webcleaningdb/views/templates/admin/sj4web_cleaning_db/configure.tpl');
        $this->content .= $this->renderForm();
//      $this->content .= $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'sj4webcleaningdb/views/templates/admin/sj4web_cleaning_db/tables-configure.tpl');
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

            $this->confirmations[] = $this->trans('Configuration enregistrée.', [], 'Modules.Sj4webCleaningDb.Admin');
        }

        if (Tools::isSubmit('submit_sj4web_cleaning_run')) {
            (new Sj4webCleaningDbRunner())->runFromConfig();
            $this->confirmations[] = $this->trans('Nettoyage exécuté. Consultez les logs.', [], 'Modules.Sj4webCleaningDb.Admin');
        }

        if (Tools::isSubmit('submit_sj4web_cleaning_optimize_only')) {
            (new Sj4webCleaningDbRunner())->optimizeTables();
            $this->confirmations[] = $this->trans('Optimisation seule exécutée.', [], 'Modules.Sj4webCleaningDb.Admin');
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
                    'title' => $this->trans('Configuration du nettoyage BDD', [], 'Modules.Sj4webCleaningDb.Admin'),
                    'icon'  => 'icon-database'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Activer le nettoyage automatique', [], 'Modules.Sj4webCleaningDb.Admin'),
                        'name' => 'cleaning_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on',  'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Sj4webCleaningDb.Admin')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Sj4webCleaningDb.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Activer l’optimisation des tables', [], 'Modules.Sj4webCleaningDb.Admin'),
                        'name' => 'optimize_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on',  'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Sj4webCleaningDb.Admin')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Sj4webCleaningDb.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Sauvegarder les tables avant suppression', [], 'Modules.Sj4webCleaningDb.Admin'),
                        'name' => 'enable_backup',
                        'is_bool' => true,
                        'desc' => $this->trans('Crée une copie des lignes supprimées dans une table `_save_` avant suppression.', [], 'Modules.Sj4webCleaningDb.Admin'),
                        'values' => [
                            ['id' => 'on',  'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Sj4webCleaningDb.Admin')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Sj4webCleaningDb.Admin')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Enregistrer la configuration', [], 'Modules.Sj4webCleaningDb.Admin'),
                    'name' => 'submit_sj4web_cleaning_config'
                ],
                'buttons' => [
                    [
                        'title' => $this->trans('Nettoyer maintenant', [], 'Modules.Sj4webCleaningDb.Admin'),
                        'name'  => 'submit_sj4web_cleaning_run',
                        'type'  => 'submit',
                        'class' => 'btn btn-danger float-end',
                        'icon'  => 'process-icon-refresh',
                    ],
                    [
                        'title' => $this->trans('Optimiser les tables maintenant', [], 'Modules.Sj4webCleaningDb.Admin'),
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
                'label' => $this->trans('Nettoyer la table "%s"', [$table], 'Modules.Sj4webCleaningDb.Admin'),
                'name'  => 'enabled_tables_' . $table,
                'is_bool' => true,
                'values' => [
                    [
                        'id'    => 'on',
                        'value' => 1,
                        'label' => $this->trans('Oui', [], 'Modules.Sj4webCleaningDb.Admin'),
                    ],
                    [
                        'id'    => 'off',
                        'value' => 0,
                        'label' => $this->trans('Non', [], 'Modules.Sj4webCleaningDb.Admin'),
                    ],
                ],
                'desc' => $this->trans('Active le nettoyage de la table "%s".', [$table], 'Modules.Sj4webCleaningDb.Admin'),
            ];

            //, 'name' => $info['label']
            if ($info['clean_type'] === 'date') {
                $fields_form['form']['input'][] = [
                    'type' => 'text',
                    'label' => $this->trans('Durée de conservation pour "%s"', [$table], 'Modules.Sj4webCleaningDb.Admin'),
                    'name'  => 'retention_days[' . $table . ']',
                    'class' => 'fixed-width-sm',
                    'suffix' => $this->trans('jours', [], 'Modules.Sj4webCleaningDb.Admin'),
                    'desc'   => $this->trans('Toutes les lignes antérieures à ce délai seront supprimées (si la table est cochée).', [], 'Modules.Sj4webCleaningDb.Admin'),
                    'min' => 0,
                    'step' => 1
                ];
            }
        }

        $fields_form['form']['input'][] = [
            'type' => 'html',
            'name' => 'html_data',
            'html_content' => '<p>&nbsp;</p><h3>'.$this->trans('Archivage / Suppression des logs', [], 'Modules.Sj4webCleaningDb.Admin').'</h3>',
        ];
        $fields_form['form']['input'][] = [
            'type' => 'text',
            'label' => $this->trans('Durée de conservation des logs (mois)', [], 'Modules.Sj4webCleaningDb.Admin'),
            'name' => 'log_retention_months',
            'class' => 'fixed-width-sm',
            'desc' => $this->trans('Les fichiers de logs plus anciens seront automatiquement supprimés.', [], 'Modules.Sj4webCleaningDb.Admin'),
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
        return [
            'connections'        => ['label' => 'Connections', 'clean_type' => 'date'],
            'connections_source' => ['label' => 'Connections source', 'clean_type' => 'orphan'],
            'guest'              => ['label' => 'Guests', 'clean_type' => 'orphan'],
            'cart'               => ['label' => 'Paniers', 'clean_type' => 'date'],
            'cart_product'       => ['label' => 'Produits panier', 'clean_type' => 'orphan'],
            'pagenotfound'       => ['label' => 'Pages 404', 'clean_type' => 'date'],
            'statssearch'        => ['label' => 'Recherches', 'clean_type' => 'date']
        ];
    }
}
