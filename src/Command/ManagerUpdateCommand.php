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
use TikiManager\Manager\UpdateManager;

class ManagerUpdateCommand extends TikiManagerCommand
{
    protected function configure()
    {
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
        $checkOption = $input->getOption('check');
        $updater = UpdateManager::getUpdater();

        if (!$updater->hasVersion()) {
            $this->io->error('Tiki Manager is not versioned. Please update it manually.');
            return 1;
        }

        $this->io->title($updater->info());

        if (!$updater->hasUpdateAvailable(true)) {
            $this->io->success('Tiki Manager is running the latest version.');
            return 0;
        }

        $this->io->block(
            'New version available, run manager:update to update.',
            'INFO',
            'fg=black;bg=yellow',
            ' ',
            true
        );

        if ($checkOption ||
            !($input->getOption('yes'))
            && $input->isInteractive()
            && !$this->io->confirm('Do you want to update?', true)) {
            return 0;
        }

        try {
            $updater->update();
            $this->io->success('Tiki Manager updated');
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
