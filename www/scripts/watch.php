<?php
$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (isset($_POST['id'])) {
    if ($instance = TikiManager\Application\Instance::getInstance((int) $_POST['id'])) {
        $log = '';
        $email = $instance->contact;
        $version = $instance->getLatestVersion();

        if ($version->hasChecksums()) {
            $result = $version->performCheck($instance);
            if (count($result['new']) || count($result['mod']) || count($result['del'])) {
                $log .= "{$instance->name} ({$instance->weburl})\n";

                foreach ($result['new'] as $file => $hash) {
                    $log .= "+ $file\n";
                }
                foreach ($result['mod'] as $file => $hash) {
                    $log .= "o $file\n";
                }
                foreach ($result['del'] as $file => $hash) {
                    $log .= "- $file\n";
                }

                $log .= "\n\n";
            }
        }
        if (empty($log)) {
            info("Nothing found.");
        } else {
            warning("Potential intrusions detected.");
            error($log);
        }
    } else {
        die("Unknown instance.");
    }
}
