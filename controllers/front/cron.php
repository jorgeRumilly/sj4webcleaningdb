<?php

class Sj4webCleaningDbCronModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $token = Tools::getValue('token');
        $expected = Configuration::get('SJ4WEB_CLEANINGDB_CRON_TOKEN');

        if (!$token || $token !== $expected) {
            header('HTTP/1.1 403 Forbidden');
            exit('❌ Accès interdit.');
        }

        require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/Sj4webCleaningDbRunner.php';

        $runner = new Sj4webCleaningDbRunner('[CRON]',  $this->translator);
        $runner->runFromConfig();

        echo '✅ Nettoyage terminé.';
        exit;
    }
    public function init()
    {
        error_reporting(E_ALL & ~E_USER_DEPRECATED);
        parent::init();
    }

}