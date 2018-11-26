<?php

function web_backup($instance)
{
    info('Checking permissions before backup...');
    $app = $instance->getApplication();
    $app->fixPermissions();

    $backup = new Backup($instance);

    if ($instance->detectDistribution() === 'ClearOS') {
        $backup->setArchiveSymlink(dirname($instance->webroot) . '/backup');
    }

//    $access = $backup->getAccess();
    $backupDir = rtrim(BACKUP_FOLDER, '/') . "/{$instance->id}-{$instance->name}";
    $archiveDir = rtrim(ARCHIVE_FOLDER, '/') . "/{$instance->id}-{$instance->name}";
    $app->removeTemporaryFiles();

    // the extrabackups query returns an error on a mac
    $targets = $backup->getTargetDirectories();
    foreach ($targets as $key => $val) {
        if (strpos($val[1], 'command not found')) {
            unset($targets[$key]);
        }
    }

    info('Downloading files locally...');
    $copyResult = $backup->copyDirectories($targets, $backupDir);

    info('Creating manifest...');
    $backup->createManifest($copyResult, $backupDir);

    info('Creating database dump...');
    $backup->createDatabaseDump($app, $backupDir);

    info('Creating archive...');
    $archive = $backup->createArchive($archiveDir, $backupDir);
    if ($archive === null) {
        echo color("\nError: Snapshot creation failed.\n", 'red');
        exit(1);
    }

    return $archive;
}
