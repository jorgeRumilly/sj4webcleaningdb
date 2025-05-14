<?php

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once __DIR__ . '/classes/Sj4webCleaningDbRunner.php';

$token = Tools::getValue('token');
$expected = Configuration::get('SJ4WEB_CLEANINGDB_CRON_TOKEN');

if (!$token || $token !== $expected) {
    header('HTTP/1.1 403 Forbidden');
    exit('❌ Accès interdit.');
}

// Exécuter le nettoyage
$runner = new Sj4webCleaningDbRunner('[CRON]');
$runner->runFromConfig();

echo '✅ Nettoyage terminé.';
