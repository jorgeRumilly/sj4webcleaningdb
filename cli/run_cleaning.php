<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix PrestaShop CLI (évite warning FrontController)
if (php_sapi_name() === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

// Chemin vers la racine du site (3 niveaux au-dessus de /modules/sj4webcleaningdb/cli/)
$rootPath = dirname(__DIR__, 3);
include_once $rootPath . '/vendor/autoload.php';
include_once $rootPath . '/config/config.inc.php';
include_once $rootPath . '/config/autoload.php';

require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/Sj4webCleaningDbRunner.php';
require_once _PS_MODULE_DIR_ . 'sj4webcleaningdb/classes/Sj4webLockManager.php';

if (!Module::isEnabled('sj4webcleaningdb')) {
    die("[ERROR] Module sj4webcleaningdb is not enabled.\n");
}

// Facultatif : sécurisation basique si appelé en HTTP (même si improbable)
if (php_sapi_name() !== 'cli') {
    die("Ce script ne peut être exécuté que via la ligne de commande.\n");
}
echo "Starting sj4webcleaningdb CLI...\n";

$logDir = _PS_MODULE_DIR_ . 'sj4webcleaningdb/logs';
$logFile = $logDir . '/cli_cleaning.log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
echo "[" . date('Y-m-d H:i:s') . "] Démarrage traitement CLI\n";
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Démarrage traitement CLI\n", FILE_APPEND);

/* Gestion du lock */
$lockFile = __DIR__ . '/.lock/cleaning.lock';
$lock = new Sj4WebLockManager($lockFile, 1800); // 30 m de lock max
if (!$lock->acquire()) {
    echo "[INFO] Un processus est déjà en cours. Arrêt.\n";
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Processus déjà en cours. Abandon.\n", FILE_APPEND);
    exit;
}

$module = Module::getInstanceByName('sj4webcleaningdb');

echo "[INFO] Running DB cleaning via CLI...\n";
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Traitement lancé.\n", FILE_APPEND);

try {
    $runner = new Sj4webCleaningDbRunner('[CLI]', $module->getTranslator());
    $runner->runFromConfig();
//    $runner->runFake();
    echo "[OK] Cleaning completed.\n";
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Traitement terminé OK.\n", FILE_APPEND);
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERREUR : " . $e->getMessage() . "\n", FILE_APPEND);

    // Envoi mail simple
    $subject = 'Erreur nettoyage BDD via CLI';
    $body = "Une erreur est survenue lors du traitement CLI du module sj4webcleaningdb :\n\n" . $e->getMessage();
    $to = Configuration::get('PS_SHOP_EMAIL');

    @Mail::Send(
        (int)Configuration::get('PS_LANG_DEFAULT'),
        'alert',
        $subject,
        ['{message}' => nl2br($body)],
        $to,
        null,
        null,
        null,
        null,
        null,
        _PS_MODULE_DIR_ . 'sj4webcleaningdb/mails/',
        false,
        null,
        null
    );
    exit(1);
} finally {
    $lock->release(); // ✅ propre, immédiat, sûr
}
