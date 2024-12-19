<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;

class ImportInstanceCommand extends TikiManagerCommand
{
    use InstanceConfigure;

    private static $nonInteractive;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:import')
            ->setDescription('Import instance')
            ->setHelp('This command allows you to import instances not yet managed by Tiki Manager')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Instance connection type'
            )
            ->addOption(
                'host',
                'rh',
                InputOption::VALUE_REQUIRED,
                'Remote host name'
            )
            ->addOption(
                'port',
                'rp',
                InputOption::VALUE_REQUIRED,
                'Remote port number'
            )
            ->addOption(
                'user',
                'ru',
                InputOption::VALUE_REQUIRED,
                'Remote User'
            )
            ->addOption(
                'pass',
                'rrp',
                InputOption::VALUE_REQUIRED,
                'Remote password'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'Instance web url'
            )
            ->addOption(
                'name',
                'na',
                InputOption::VALUE_REQUIRED,
                'Instance name'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Instance contact email'
            )
            ->addOption(
                'webroot',
                'wr',
                InputOption::VALUE_REQUIRED,
                'Instance web root'
            )
            ->addOption(
                'tempdir',
                'td',
                InputOption::VALUE_REQUIRED,
                'Instance temporary directory'
            )
            ->addOption(
                'backup-user',
                'bu',
                InputOption::VALUE_REQUIRED,
                'Instance backup user: the local user that will be used as backup files owner.'
            )
            ->addOption(
                'backup-group',
                'bg',
                InputOption::VALUE_REQUIRED,
                'Instance backup group: the local group that will be used as backup files owner.'
            )
            ->addOption(
                'backup-permission',
                'bp',
                InputOption::VALUE_REQUIRED,
                'Instance backup permission'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Instance branch'
            )
            ->addOption(
                'repo-url',
                null,
                InputOption::VALUE_REQUIRED,
                'Repository URL'
            )
            ->addOption(
                'phpexec',
                null,
                InputOption::VALUE_REQUIRED,
                'PHP binary to be used to manage the instance'
            )
            ->addOption(
                'skip-phpcheck',
                null,
                InputOption::VALUE_NONE,
                'Skip PHP minimum requirements check'
            );

        self::$nonInteractive = false;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = new Instance();
        try {
            if ($input->isInteractive()) {
                $this->printManagerInfo();

                $this->io->title('Import an instance');
                $output->writeln('<comment>Answer the following to import a new Tiki Manager instance.</comment>');
            }

            $this->setupAccess($instance);
            $this->setupInstance($instance, true);

            $skipPhpCheck = $input->getOption('skip-phpcheck');
            if (! $skipPhpCheck && $this->isMissingPHPRequirements($instance, $this->logger)) {
                $error = 'Missing minimum requirements. Before installing Tiki, review the documentation in ' .
                    'https://doc.tiki.org/Requirements and confirm that your system meets the minimum requirements.';
                throw new \Exception($error);
            }

            if ($duplicated = $instance->hasDuplicate()) {
                $error = \sprintf(
                    'Instance `%s` (id: %s) has the same access and webroot.',
                    $duplicated->name,
                    $duplicated->id
                );
                throw new \Exception($error);
            }

            $instance->save();

            $this->importApplication($instance);

            $hookName = $this->getCommandHook();
            $hookName->registerPostHookVars(['instance' => $instance]);

            $this->io->success('Import completed, please test your site at ' . $instance->weburl);
            return 0;
        } catch (\Exception $e) {
            if ($instance instanceof Instance) {
                $instance->delete();
            }

            $this->io->error($e->getMessage());
            return $e->getCode() ?: -1;
        }
    }
}
