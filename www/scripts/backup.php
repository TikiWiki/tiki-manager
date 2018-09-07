<?php
$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
require TRIMPATH . '/src/clean.php';
ob_end_clean();

if (isset($_POST['id'])){
    if ( $instance = Instance::getInstance( (int) $_POST['id'] ) ) {
        info('Checking permissions before backup...');
        $app = $instance->getApplication();
        $app->fixPermissions();

        $backup = new Backup($instance);

        if ($instance->detectDistribution() === 'ClearOS') {
            $backup->setArchiveSymlink(dirname($instance->webroot) . '/backup');
        }

//        $access = $backup->getAccess();
        $backupDir = rtrim(BACKUP_FOLDER, '/') . "/{$instance->id}-{$instance->name}";
        $archiveDir = rtrim(ARCHIVE_FOLDER, '/') . "/{$instance->id}-{$instance->name}";
        $app->removeTemporaryFiles();

        // the extrabackups query returns an error on a mac
        $targets = $backup->getTargetDirectories();
        foreach( $targets as $key=>$val ) {
            if(strpos($val[1], 'command not found')) unset($targets[$key]);
        }

        info('Downloading files locally...');
        $copyResult = $backup->copyDirectories($targets, $backupDir);

        info('Creating manifest...');
        $backup->createManifest($copyResult, $backupDir);

        info('Creating database dump...');
        $backup->createDatabaseDump($app, $backupDir);

        info('Creating archive...');
        $backup->createArchive($archiveDir, $backupDir);

        // info('Backing up instance...');
//        $instance->backup();
//        perform_archive_cleanup($instance->id, $instance->name);
    } else {
        die( "Unknown instance." );
    }
}
?>
