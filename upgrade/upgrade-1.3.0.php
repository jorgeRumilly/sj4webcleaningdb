<?php
/**
 * Upgrade 1.3.0 : suppression par lots (taille de lot et durée maximale configurables).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module)
{
    $defaults = [
        'SJ4WEB_CLEANINGDB_BATCH_SIZE' => 5000,
        'SJ4WEB_CLEANINGDB_MAX_MINUTES' => 25,
    ];
    foreach ($defaults as $key => $value) {
        if (Configuration::get($key) === false) {
            Configuration::updateValue($key, $value);
        }
    }

    return true;
}
