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
use TikiManager\Application\Instance;
use TikiManager\Command\Traits\InstanceConfigure;

class CreateInstanceCommand extends TikiManagerCommand
{
    use InstanceConfigure;

    /**
     * Setup the command configuration. Parameters and options are added here.
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:create')
            ->setDescription('Creates a new instance (or take over an already installed Tiki)')
            ->setHelp('This command allows you to create a new instance')
            ->addOption(
                'blank',
                null,
                InputOption::VALUE_NONE,
                'Blank Instance'
            )
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
                'db-host',
                'dh',
                InputOption::VALUE_REQUIRED,
                'Instance database host'
            )
            ->addOption(
                'db-user',
                'du',
                InputOption::VALUE_REQUIRED,
                'Instance database user'
            )
            ->addOption(
                'db-pass',
                'dp',
                InputOption::VALUE_REQUIRED,
                'Instance database password'
            )
            ->addOption(
                'db-prefix',
                'dpx',
                InputOption::VALUE_REQUIRED,
                'Instance database prefix'
            )
            ->addOption(
                'db-name',
                'dn',
                InputOption::VALUE_REQUIRED,
                'Instance database name'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force deletion of target folder files.'
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
            )
            ->addOption(
                'revision',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Specific revision to checkout'
            );
    }

    /**
     * Execute command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->printManagerInfo();

        $this->io->title('New Instance Setup');

        $instance = new Instance();

        try {
            $this->setupAccess($instance);
            $this->setupInstance($instance);

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

            if ($this->detectApplication($instance)) {
                $add = $this->io->confirm(
                    'An application was detected in [' . $instance->webroot . '], do you want add it to the list?:',
                    true
                );

                if (!$add) {
                    throw new \Exception('Unable to install. An application was detected in this instance.');
                }

                $instance = $this->importApplication($instance);
                $this->getCommandHook()->registerPostHookVars(['instance' => $instance]);

                $this->io->success('Please test your site at ' . $instance->weburl);
                return 0;
            }

            $this->setupApplication($instance);

            if ($instance->selection != 'blank : none') {
                $this->setupDatabase($instance);
            }

            $instance = $this->install($instance);
            $this->getCommandHook()->registerPostHookVars(['instance' => $instance]);
            $instance->updateState('success', $this->getName(), 'Instance created');

            return 0;
        } catch (\Exception $e) {
            $instance->delete();

            $this->io->error($e->getMessage());
            return $e->getCode() ?: -1;
        }
    }
}
