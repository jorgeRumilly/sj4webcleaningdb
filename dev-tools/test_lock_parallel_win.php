<?php

// test_lock_parallel.php - Version compatible Windows

$instances = 5; // Nombre de processus à lancer
$delay = 1; // Secondes entre chaque lancement
$script = __DIR__ . '/cli/run_cleaning.php';
$logFile = __DIR__ . '/tests/lock_test_windows.log';

// Crée le dossier logs si nécessaire
if (!is_dir(__DIR__ . '/tests')) {
    mkdir(__DIR__ . '/tests', 0755, true);
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Test démarré\n", FILE_APPEND);

for ($i = 1; $i <= $instances; $i++) {
    $cmd = "php \"$script\" >> \"$logFile\" 2>&1";
    pclose(popen("start /B " . $cmd, "r")); // Lance un processus en arrière-plan
    file_put_contents($logFile, "[" . date('H:i:s') . "] Instance $i lancée\n", FILE_APPEND);
    sleep($delay);
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lancement terminé\n", FILE_APPEND);

echo "Lancement de $instances instances terminé. Voir $logFile\n";
