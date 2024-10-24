<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Manager\Update\Exception\TrackingInformationNotFoundException;
use TikiManager\Manager\Update\Git;
use TikiManager\Manager\UpdateManager;

class ManagerUpdateCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('manager:update')
            ->setDescription('Update Tiki Manager')
            ->addOption(
                'check',
                'c',
                InputOption::VALUE_NONE,
                'Will only verify if a new version is available.'
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Proceed with update.'
            )
            ->setHelp('This command allows to update Tiki Manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packagesInstallation = CommandHelper::isInstalledFromPackage();
        if ($packagesInstallation) {
            $this->io->newLine();
            $this->io->writeln('<comment>This instance of Tiki-Manager was installed using your operation system packages.</comment>');
            $this->io->writeln('<comment>Use you package manager to check for a new version and to update.</comment>');
            return 1;
        }

        $updater = UpdateManager::getUpdater();
        $updater->setLogger($this->logger);

        $update = $input->getOption('yes');
        $check = $input->getOption('check');

        if (!$updater->hasVersion()) {
            $this->io->error('Tiki Manager is not versioned. Please update it manually.');
            return 1;
        }

        $this->io->info($updater->info());

        try {
            if ($updater instanceof Git && $updater->isHeadDetached()) {
                $this->io->error('Tiki Manager can not be updated. Git HEAD is not currently on a branch.');
                return 1;
            }

            if (!$updater->hasUpdateAvailable(true)) {
                $this->io->success('Tiki Manager is running the latest version.');
                return 0;
            }
        } catch (TrackingInformationNotFoundException $e) {
            $this->io->error('Tiki Manager can not be updated.' . PHP_EOL . PHP_EOL . $e->getMessage());
            return 1;
        }

        $this->io->warning('New version available.');
        $update = $check ? false : ($update ?: $this->io->confirm('Do you want to update?', true));

        if ($update) {
            try {
                $updater->update();
                $this->io->success('Tiki Manager updated.');
                $this->io->info($updater->info());
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
                return 1;
            }
        }

        return 0;
    }
}
