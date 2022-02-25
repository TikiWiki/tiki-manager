<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */
require_once 'src/Libs/Helpers/functions.php';

try {
    $pharPath = Phar::running(false);
    $isPhar = isset($pharPath) && !empty($pharPath);

    if (!$isPhar
        && !file_exists(__DIR__ . '/vendor/autoload.php')
        && file_exists(__DIR__ . '/composer.json')
    ) {
        if (!$composer = detectComposer(__DIR__)) {
            print('Downloading composer.phar...' . PHP_EOL);
            $composer = installComposer(__DIR__);
        }
        installComposerDependencies(__DIR__, $composer);
    }
} catch (Exception $e) {
    print($e->getMessage());
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TikiManager\Config\Environment;
use TikiManager\Manager\UpdateManager;

Environment::getInstance()->load();

$application = new Application();
$banner = <<<TXT

88888888888 8888888 888    d8P  8888888      888b     d888
    888       888   888   d8P     888        8888b   d8888
    888       888   888  d8P      888        88888b.d88888
    888       888   888d88K       888        888Y88888P888  8888b.  88888b.   8888b.   .d88b.   .d88b.  888d888
    888       888   8888888b      888        888 Y888P 888     "88b 888 "88b     "88b d88P"88b d8P  Y8b 888P"
    888       888   888  Y88b     888        888  Y8P  888 .d888888 888  888 .d888888 888  888 88888888 888
    888       888   888   Y88b    888        888   "   888 888  888 888  888 888  888 Y88b 888 Y8b.     888
    888     8888888 888    Y88b 8888888      888       888 "Y888888 888  888 "Y888888  "Y88888  "Y8888  888
                                                                                           888
                                                                                      Y8b d88P
                                                                                       "Y88P"
TXT;
$application->setName($banner);

$application->add(new \TikiManager\Command\CreateInstanceCommand());
$application->add(new \TikiManager\Command\AccessInstanceCommand());
$application->add(new \TikiManager\Command\DeleteInstanceCommand());
$application->add(new \TikiManager\Command\EnableWebManagerCommand());
$application->add(new \TikiManager\Command\BlockWebManagerCommand());
$application->add(new \TikiManager\Command\CopySshKeyCommand());
$application->add(new \TikiManager\Command\WatchInstanceCommand());
$application->add(new \TikiManager\Command\DetectInstanceCommand());
$application->add(new \TikiManager\Command\EditInstanceCommand());
$application->add(new \TikiManager\Command\BlankInstanceCommand());
$application->add(new \TikiManager\Command\CheckInstanceCommand());
$application->add(new \TikiManager\Command\VerifyInstanceCommand());
$application->add(new \TikiManager\Command\UpdateInstanceCommand());
$application->add(new \TikiManager\Command\UpgradeInstanceCommand());
$application->add(new \TikiManager\Command\RestoreInstanceCommand());
$application->add(new \TikiManager\Command\CloneInstanceCommand());
$application->add(new \TikiManager\Command\CloneAndUpgradeInstanceCommand());
$application->add(new \TikiManager\Command\BackupInstanceCommand());
$application->add(new \TikiManager\Command\DeleteBackupCommand());
$application->add(new \TikiManager\Command\FixPermissionsInstanceCommand());
$application->add(new \TikiManager\Command\ListInstanceCommand());
$application->add(new \TikiManager\Command\MaintenanceInstanceCommand());
$application->add(new \TikiManager\Command\ConsoleInstanceCommand());
$application->add(new \TikiManager\Command\StatsInstanceCommand());
$application->add(new \TikiManager\Command\ImportInstanceCommand());
$application->add(new \TikiManager\Command\SetupSchedulerCronInstanceCommand());

$application->add(new \TikiManager\Command\ApplyProfileCommand());

$application->add(new \TikiManager\Command\ManagerInfoCommand());
$application->add(new \TikiManager\Command\ManagerUpdateCommand());
$application->add(new \TikiManager\Command\CheckRequirementsCommand());
$application->add(new \TikiManager\Command\ResetManagerCommand());
$application->add(new \TikiManager\Command\ReportManagerCommand());
$application->add(new \TikiManager\Command\SetupUpdateCommand());
$application->add(new \TikiManager\Command\SetupWatchManagerCommand());
$application->add(new \TikiManager\Command\SetupBackupManagerCommand());
$application->add(new \TikiManager\Command\SetupCloneManagerCommand());

$application->add(new \TikiManager\Command\ViewDatabaseCommand());
$application->add(new \TikiManager\Command\DeleteDatabaseCommand());

$application->add(new \TikiManager\Command\ClearCacheCommand());

$application->add(new \TikiManager\Command\ClearLogsCommand());

$application->add(new \TikiManager\Command\TikiVersionCommand());

if (extension_loaded('posix')) {
    $userInfo = posix_getpwuid(posix_geteuid());
    if ($userInfo['name'] === 'root') {
        $io = \TikiManager\Config\App::get('io');
        $io->warning('You are running Tiki Manager as root. This is not an ideal situation. ' . PHP_EOL .
            'Ex: If later, you run as a normal user, you may have issues with file permissions.');
    }
}

// this should be moved to a custom src/Console/Application (like composer)
$dispatcher = new EventDispatcher();
$dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
    //Check if there are an update available (offline)
    $command = $event->getCommand();
    $updater = UpdateManager::getUpdater();
    if ($command->getName() != 'manager:update' && $updater->hasUpdateAvailable(false)) {
        $io = \TikiManager\Config\App::get('io');
        $io->warning('A new version is available. Run `manager:update` to update.');
    }
});
$dispatcher->addListener(ConsoleEvents::ERROR, function (ConsoleErrorEvent $event) {
    $io = \TikiManager\Config\App::get('io');

    $error = $event->getError();
    $io->error($error->getMessage());
    trim_output($error);

    exit($event->getExitCode());
});
$application->setDispatcher($dispatcher);

$application->run();
