<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Config\Exception\ConfigurationErrorException;
use TikiManager\Manager\UpdateManager;
use TikiManager\Command\TikiManagerCommand;

class Console
{
    private $application;
    private $dispatcher;

    public function init()
    {
        try {
            Environment::getInstance()->load();
        } catch (ConfigurationErrorException $e) {
            try {
                if ($io = App::get('io')) {
                    $io->error($e->getMessage());
                    exit(1);
                }
                throw new \RuntimeException($e->getMessage());
            } catch (\Throwable $ioError) {
                throw new \RuntimeException($e->getMessage() . "\n" . $ioError->getMessage());
            }
        }

        $this->application = new Application();
        $this->application->setAutoExit(false);
        $this->dispatcher = new EventDispatcher();
        $this->setApplicationName();
        $this->addDefaultGlobalOptions();
        $this->setupCommands();
        $this->setupEventListeners();
    }

    private function setApplicationName()
    {
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

        $this->application->setName($banner);
    }

    private function setupCommands()
    {
        $this->application->add(new \TikiManager\Command\CreateInstanceCommand());
        $this->application->add(new \TikiManager\Command\AccessInstanceCommand());
        $this->application->add(new \TikiManager\Command\DeleteInstanceCommand());
        $this->application->add(new \TikiManager\Command\CopySshKeyCommand());
        $this->application->add(new \TikiManager\Command\WatchInstanceCommand());
        $this->application->add(new \TikiManager\Command\DetectInstanceCommand());
        $this->application->add(new \TikiManager\Command\EditInstanceCommand());
        $this->application->add(new \TikiManager\Command\BlankInstanceCommand());
        $this->application->add(new \TikiManager\Command\VerifyInstanceCommand());
        $this->application->add(new \TikiManager\Command\UpdateInstanceCommand());
        $this->application->add(new \TikiManager\Command\UpgradeInstanceCommand());
        $this->application->add(new \TikiManager\Command\RestoreInstanceCommand());
        $this->application->add(new \TikiManager\Command\CloneInstanceCommand());
        $this->application->add(new \TikiManager\Command\CloneAndUpgradeInstanceCommand());
        $this->application->add(new \TikiManager\Command\CloneAndRedactInstanceCommand());
        $this->application->add(new \TikiManager\Command\BackupInstanceCommand());
        $this->application->add(new \TikiManager\Command\DeleteBackupCommand());
        $this->application->add(new \TikiManager\Command\FixPermissionsInstanceCommand());
        $this->application->add(new \TikiManager\Command\ListInstanceCommand());
        $this->application->add(new \TikiManager\Command\MaintenanceInstanceCommand());
        $this->application->add(new \TikiManager\Command\ConsoleInstanceCommand());
        $this->application->add(new \TikiManager\Command\StatsInstanceCommand());
        $this->application->add(new \TikiManager\Command\ImportInstanceCommand());
        $this->application->add(new \TikiManager\Command\InstanceInfoCommand());
        $this->application->add(new \TikiManager\Command\SetupSchedulerCronInstanceCommand());
        $this->application->add(new \TikiManager\Command\RevertInstanceCommand());
        $this->application->add(new \TikiManager\Command\ApplyProfileCommand());
        $this->application->add(new \TikiManager\Command\ListPatchCommand());
        $this->application->add(new \TikiManager\Command\ApplyPatchCommand());
        $this->application->add(new \TikiManager\Command\DeletePatchCommand());
        $this->application->add(new \TikiManager\Command\ManagerInfoCommand());
        $this->application->add(new \TikiManager\Command\ManagerUpdateCommand());
        $this->application->add(new \TikiManager\Command\ManagerTestSendEmailCommand());
        $this->application->add(new \TikiManager\Command\MonitorInstanceCommand());
        $this->application->add(new \TikiManager\Command\CheckRequirementsCommand());
        $this->application->add(new \TikiManager\Command\ResetManagerCommand());
        $this->application->add(new \TikiManager\Command\ReportManagerCommand());
        $this->application->add(new \TikiManager\Command\SetupUpdateCommand());
        $this->application->add(new \TikiManager\Command\SetupWatchManagerCommand());
        $this->application->add(new \TikiManager\Command\SetupBackupManagerCommand());
        $this->application->add(new \TikiManager\Command\SetupCloneManagerCommand());
        $this->application->add(new \TikiManager\Command\ViewDatabaseCommand());
        $this->application->add(new \TikiManager\Command\DeleteDatabaseCommand());
        $this->application->add(new \TikiManager\Command\ClearCacheCommand());
        $this->application->add(new \TikiManager\Command\ClearLogsCommand());
        $this->application->add(new \TikiManager\Command\TikiVersionCommand());
        $this->application->add(new \TikiManager\Command\CreateTemporaryUserInstanceCommand());
        $this->application->add(new \TikiManager\Command\TagAddOrEditCommand());
        $this->application->add(new \TikiManager\Command\TagListCommand());
        $this->application->add(new \TikiManager\Command\TagDeleteCommand());
        $this->application->add(new \TikiManager\Command\CheckoutCommand());
        $this->application->add(new \TikiManager\Command\InstanceBisectStartCommand());
        $this->application->add(new \TikiManager\Command\InstanceBisectBadCommand());
        $this->application->add(new \TikiManager\Command\InstanceBisectGoodCommand());
        $this->application->add(new \TikiManager\Command\InstanceBisectResetCommand());
        $this->application->add(new \TikiManager\Command\BackupIgnoreAddCommand());
        $this->application->add(new \TikiManager\Command\BackupIgnoreListCommand());
        $this->application->add(new \TikiManager\Command\BackupIgnoreRemoveCommand());
    }

    private function addDefaultGlobalOptions()
    {
        try {
            // Check if is run as root
            $this->application->getDefinition()->addOption(new InputOption(
                'no-root-check',
                null,
                InputOption::VALUE_NONE,
                'Do not perform root user check'
            ));

            // Check for updates
            $this->application->getDefinition()->addOption(new InputOption(
                'no-update-check',
                null,
                InputOption::VALUE_NONE,
                'Do not check for updates'
            ));
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    private function setupEventListeners()
    {
        try {
            $this->dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                // check if tiki manager is being run as root (and add a warning)
                $input = $event->getInput();

                $skipRootCheck = $input->getOption('no-root-check') || Environment::getInstance()->get('NO_ROOT_CHECK') == '1';
                $quiet = $input->getOption('quiet');

                if ($skipRootCheck || $quiet) {
                    return; //skip
                }

                // root user check
                if (extension_loaded('posix')) {
                    $userInfo = posix_getpwuid(posix_geteuid());
                    if ($userInfo['name'] === 'root') {
                        $io = App::get('io');
                        $io->warning('You are running Tiki Manager as root. This is not an ideal situation.' . PHP_EOL .
                        'Ex: If later, you run as a normal user, you may have issues with file permissions.');
                    }
                }
            });

            $this->dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                //Check if there are an update available (offline)
                $command = $event->getCommand();
                $input = $event->getInput();

                $noUpdateCheck = $input->getOption('no-update-check') || Environment::getInstance()->get('NO_UPDATE_CHECK') == '1';
                $quiet = $input->getOption('quiet');

                if ($noUpdateCheck || $quiet || $command->getName() == 'manager:update') {
                    return; //skip
                }

                $updater = UpdateManager::getUpdater();
                if ($updater->hasUpdateAvailable(false)) {
                    $io = App::get('io');
                    $io->warning('A new version is available. Run `manager:update` to update.');
                }
            });

            $this->dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                //Run hooks
                $command = $event->getCommand();
                $input = $event->getInput();
                if ($command instanceof TikiManagerCommand && !$input->getParameterOption('skip-hooks', false)) {
                    $command->getCommandHook()->execute('pre');
                }
            });

            $this->dispatcher->addListener(ConsoleEvents::ERROR, function (ConsoleErrorEvent $event) {
                $io = App::get('io');

                $error = $event->getError();
                $io->error($error->getMessage());
                trim_output($error);

                $command = $event->getCommand();
                $input = $event->getInput();
                if ($command instanceof TikiManagerCommand && !$input->getParameterOption('skip-hooks', false)) {
                    $command->getCommandHook()->execute('errors');
                }

                exit($event->getExitCode());
            });

            $this->dispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {
                $command = $event->getCommand();
                $input = $event->getInput();
                if ($command instanceof TikiManagerCommand && !$input->getParameterOption('skip-hooks', false)) {
                    $command->getCommandHook()->execute('post');
                }
            });

            $this->application->setDispatcher($this->dispatcher);
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    public function run()
    {
        $output = new ConsoleOutput();
        $input = new ArgvInput();

        try {
            $this->application->run($input, $output);
        } catch (\Throwable $e) {
            $output->writeln('<comment>A error was encountered while running a command</comment>');
            $this->application->renderThrowable($e, $output);
        }

        $output->writeln('');

        if ($input->getFirstArgument() === null) {
            $output->writeln('<fg=cyan>To run a specific command (with default values) type: php tiki-manager.php instance:list</>');
            $output->writeln('<fg=cyan>To get more help on a specific command, use the following pattern: php tiki-manager.php instance:list --help</>');
            $output->writeln('');
        }
    }
}
