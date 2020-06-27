<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\Environment;

/**
 * Setup automatic instance clone using CRON
 * Class SetupCloneCommand
 * @package TikiManager\Command
 */
class SetupCloneManagerCommand extends TikiManagerCommand
{
    /**
     * Command configuration function
     */
    protected function configure()
    {
        $this
            ->setName('manager:setup-clone')
            ->setDescription('Setup a cronjob to perform instance clone')
            ->setHelp('This command allows you setup a cronjob to perform another identical copy of Tiki ')
            ->setAliases(['setup:clone'])
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption(
                'time',
                null,
                InputOption::VALUE_REQUIRED,
                'The time clone should be triggered'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source instance.'
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Destination instance(s).'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Select Branch.'
            )
            ->addOption(
                'skip-reindex',
                null,
                InputOption::VALUE_NONE,
                'Skip rebuilding index step.'
            )
            ->addOption(
                'skip-cache-warmup',
                null,
                InputOption::VALUE_NONE,
                'Skip generating cache step.'
            )
            ->addOption(
                'live-reindex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Live reindex, set instance maintenance off and after perform index rebuild.',
                true
            )
            ->addOption(
                'direct',
                'd',
                InputOption::VALUE_NONE,
                'Prevent using the backup step and rsync source to target.'
            )
            ->addOption(
                'keep-backup',
                null,
                InputOption::VALUE_NONE,
                'Source instance backup is not deleted before the process finished.'
            )
            ->addOption(
                'use-last-backup',
                null,
                InputOption::VALUE_NONE,
                'Use source instance last created backup.'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('time'))) {
            $answer = $this->io->ask('What time should it run at?', '00:00', function ($answer) {
                return CommandHelper::validateTimeInput($answer);
            });

            $input->setOption('time', implode(':', $answer));
        }
    }

    /**
     * Command handler function
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $time = $input->getOption('time');
        // Check if option (set in cli is also valid)
        list($hours, $minutes) = CommandHelper::validateTimeInput($time);

        //command line execute
        $managerPath = realpath(dirname(__FILE__) . '/../..');
        $cloneInstance = new CloneInstanceCommand();
        $cloneInstanceCommand = Environment::get('TIKI_MANAGER_EXECUTABLE') . ' ' . $cloneInstance->getName() . ' --no-interaction ';
        if ($input->getOption('check')) {
            $cloneInstanceCommand .= ' --check';
        }

        if ($source = $input->getOption('source')) {
            $cloneInstanceCommand .= ' --source=' . $source;
        }

        if ($target = $input->getOption('target')) {
            $cloneInstanceCommand .= ' --target=' . $target;
        }

        if ($branch = $input->getOption('branch')) {
            $cloneInstanceCommand .= ' --branch=' . $branch;
        }

        if ($skipReindex = $input->getOption('skip-reindex')) {
            $cloneInstanceCommand .= ' --skip-reindex=' . $skipReindex;
        }

        if ($skipCacheWarmup = $input->getOption('skip-cache-warmup')) {
            $cloneInstanceCommand .= ' --slip-cache-warmup=' . $skipCacheWarmup;
        }

        if ($liveReindex = is_null($input->getOption('live-reindex')) ? true : filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN)) {
            $cloneInstanceCommand .= ' --live-reindex=' . $liveReindex;
        }

        if ($direct = $input->getOption('direct')) {
            $cloneInstanceCommand .= ' --direct=' . $direct;
        }

        if ($keepBackup = $input->getOption('keep-backup')) {
            $cloneInstanceCommand .= ' --keep-backup=' . $keepBackup;
        }

        if ($useLastBackup = $input->getOption('use-last-backup')) {
            $cloneInstanceCommand .= ' --use-last-backup=' . $useLastBackup;
        }

        $entry = sprintf(
            "%d %d * * * cd %s && %s %s\n",
            $minutes,
            $hours,
            $managerPath,
            PHP_BINARY,
            $cloneInstanceCommand
        );

        file_put_contents($file = Environment::get('TEMP_FOLDER') . '/crontab', `crontab -l` . $entry);
        $this->io->error("Failed to edit contab file. Please add the following line to your crontab file: \n{$entry}");
        `crontab $file`;
    }
}
