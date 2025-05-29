<?php

class Sj4WebLockManager
{

    protected $file;
    protected $maxAge;

    public function __construct($file, $maxAge = 3600)
    {
        $this->file = $file;
        $this->maxAge = $maxAge;
        if(!is_dir($dir = dirname($file))) {
            mkdir($dir, 0755, true);
        }
    }

    public function acquire():bool
    {
        if(file_exists($this->file)) {
            $content = trim(@file_get_contents($this->file));
            if(preg_match('#^(\d+):(.+)$#', $content, $m)) {
                $pid = (int)$m[1];
                $timestamp = strtotime($m[2]);
                // Vérifie si le processus est toujours actif (si POSIX dispo)
                // Sinon, on s'appuie uniquement sur l'âge du lock
                $running = function_exists('posix_kill') && $pid > 0 && posix_kill($pid, 0);
                $expired = (time() - $timestamp) > $this->maxAge;

                if($running && !$expired) {
                    // Le lock est toujours valide
                    return false; // Déjà verrouillé
                }
                // Supprime le lock obsolète
                @unlink($this->file);
            }
        }
        file_put_contents($this->file, getmypid() . ':' . date('Y-m-d H:i:s'));

        // Suppression du lock à la fin du script
        register_shutdown_function([$this, 'release']);

        return true; // Lock acquis
    }

    /**
     * Libère le lock en supprimant le fichier
     * Appelé automatiquement à la fin du script
     */
    public function release()
    {
        if(file_exists($this->file)) {
            @unlink($this->file);
        }
    }

}