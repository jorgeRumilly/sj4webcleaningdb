<?php
// Script de test pour simuler des exécutions parallèles de run_cleaning.php
// Objectif : tester le verrouillage et observer les comportements concurrents

$instances = 5; // Nombre de processus à lancer
$delay = 1;     // Délai entre chaque lancement (secondes)
$modulePath = __DIR__ . '/../cli/run_cleaning.php';
$logFile = __DIR__ . '/../tests/lock_test.log';
$lockFile = __DIR__ . '/../cli/.lock/cleaning.lock';

if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

file_put_contents($logFile, "[TEST " . date('Y-m-d H:i:s') . "] Lancement du test avec {$instances} processus.\n", FILE_APPEND);

for ($i = 0; $i < $instances; $i++) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        echo "Erreur lors du fork\n";
        exit(1);
    } elseif ($pid === 0) {
        // Processus enfant
        $result = shell_exec("php " . escapeshellarg($modulePath));
        $stamp = date('Y-m-d H:i:s');
        $content = file_exists($lockFile) ? trim(file_get_contents($lockFile)) : 'Aucun verrou actif.';
        file_put_contents($logFile, "[{$stamp}] Instance {$i} – Lock: {$content}\n{$result}\n", FILE_APPEND);
        exit(0);
    } else {
        // Processus parent : petite pause
        sleep($delay);
    }
}

echo "Test lancé avec {$instances} processus. Vérifie le fichier : {$logFile}\n";
?>