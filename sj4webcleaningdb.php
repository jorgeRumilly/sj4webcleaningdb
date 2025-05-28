<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Sj4webCleaningDb extends Module
{
    public function __construct()
    {
        $this->name = 'sj4webcleaningdb';
        $this->tab = 'administration';
        $this->version = '1.1.3';
        $this->author = 'SJ4WEB.FR';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('SJ4WEB - PrestaShop DB Cleanup', [], 'Modules.Sj4webcleaningdb.Admin');
        $this->description = $this->trans('Cleans specific tables based on a retention period. Manual or CRON execution. Logs available.', [], 'Modules.Sj4webcleaningdb.Admin');

        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];

    }

    public function install()
    {
        return parent::install()
            && $this->registerTab()
            && $this->registerTab(true) // pour les logs
            && $this->installDefaultConfig();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->unregisterTab()
            && $this->unregisterTab(true)
            && $this->clearConfig();
    }

    protected function registerTab($logs = false)
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $logs ? 'AdminSj4webCleaningDbLog' : 'AdminSj4webCleaningDb';
        $tab->name = [];

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $logs
                ? $this->trans('DB Cleanup Logs', [], 'Modules.Sj4webcleaningdb.Admin')
                : $this->trans('DB Cleanup', [], 'Modules.Sj4webcleaningdb.Admin');
        }

        $tab->id_parent = (int) Tab::getIdFromClassName('AdminAdvancedParameters');
        $tab->module = $this->name;

        return $tab->add();
    }

    protected function unregisterTab($logs = false)
    {
        $idTab = Tab::getIdFromClassName($logs ? 'AdminSj4webCleaningDbLog' : 'AdminSj4webCleaningDb');
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
        }
        return true;
    }

    protected function installDefaultConfig()
    {
        $defaults = [
            'SJ4WEB_CLEANINGDB_ENABLED_TABLES'      => json_encode([]),
            'SJ4WEB_CLEANINGDB_RETENTION'           => json_encode([]),
            'SJ4WEB_CLEANINGDB_CRON_TOKEN'          => Tools::passwdGen(32),
            'SJ4WEB_CLEANINGDB_ENABLED'             => 1,
            'SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED'    => 1,
            'SJ4WEB_CLEANINGDB_ENABLE_BACKUP'       => 0,
            'SJ4WEB_CLEANINGDB_LOG_RETENTION'       => 3,
            'SJ4WEB_CLEANINGDB_MAIL_ENABLE'         => 0,
            'SJ4WEB_CLEANINGDB_MAIL_RECIPIENTS'     => '',
        ];
        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin('index.php?controller=AdminSj4webCleaningDb&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminSj4webCleaningDb'));
    }

    protected function clearConfig()
    {
        return Configuration::deleteByName('SJ4WEB_CLEANINGDB_ENABLED_TABLES')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_CRON_TOKEN')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_RETENTION')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_ENABLED')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_OPTIMIZE_ENABLED')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_ENABLE_BACKUP')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_LOG_RETENTION')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_MAIL_ENABLE')
            && Configuration::deleteByName('SJ4WEB_CLEANINGDB_MAIL_RECIPIENTS');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }
}
