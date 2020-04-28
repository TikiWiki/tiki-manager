<?php

use TikiManager\Config\App;
use TikiManager\Application\Backup;

function web_backup($instance)
{
    $io = App::get('io');

    $io->writeln('Checking permissions before backup...');
    $app = $instance->getApplication();
    $app->fixPermissions();

    $backup = new Backup($instance);

    if ($instance->detectDistribution() === 'ClearOS') {
        $backup->setArchiveSymlink(dirname($instance->webroot) . '/backup');
    }

//    $access = $backup->getAccess();
    $backupDir = rtrim($_ENV['BACKUP_FOLDER'], '/') . "/{$instance->id}-{$instance->name}";
    $archiveDir = rtrim($_ENV['ARCHIVE_FOLDER'], '/') . "/{$instance->id}-{$instance->name}";
    $app->removeTemporaryFiles();

    // the extrabackups query returns an error on a mac
    $targets = $backup->getTargetDirectories();
    foreach ($targets as $key => $val) {
        if (strpos($val[1], 'command not found')) {
            unset($targets[$key]);
        }
    }

    $io->writeln('Copying files...');
    $copyResult = $backup->copyDirectories($targets, $backupDir);

    $io->writeln('Creating manifest...');
    $backup->createManifest($copyResult, $backupDir);

    $io->writeln('Creating database dump...');
    $backup->createDatabaseDump($app, $backupDir);

    $io->writeln('Creating archive...');
    $archive = $backup->createArchive($archiveDir, $backupDir);
    if ($archive === null) {
        $io->error("\nError: Snapshot creation failed.\n");
        exit(1);
    }

    return $archive;
}
