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
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;
use TikiManager\Hooks\InstanceCreateHook;

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
                'run-as-user',
                null,
                InputOption::VALUE_OPTIONAL,
                'User to run commands as'
            )
            ->addOption(
                'revision',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Specific revision to checkout'
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                'Attempt to validate the instance by checking its URL.'
            )
            ->addOption(
                'copy-errors',
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle rsync errors: use "stop" to halt on errors or "ignore" to proceed despite errors'
            );
    }

    /**
     * Execute command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->printManagerInfo();

        $this->io->title('New Instance Setup');

        $instance = new Instance();
        $hookName = $this->getCommandHook();
        $instanceCreateHook = new InstanceCreateHook($hookName->getHookName(), $this->logger);

        $isNewInstance = true;

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
                $isNewInstance = false;
                $add = $this->io->confirm(
                    'An application was detected in [' . $instance->webroot . '], do you want add it to the list?:',
                    true
                );

                if (!$add) {
                    throw new \Exception('Unable to install. An application was detected in this instance.');
                }

                $instance = $this->importApplication($instance);
                $hookName->registerPostHookVars(['instance' => $instance]);

                $this->io->success('Please test your site at ' . $instance->weburl);
                return 0;
            }

            $this->setupApplication($instance);

            if ($instance->selection != 'blank : none') {
                $this->setupDatabase($instance);
            }

            $instance = $this->install($instance);
            $hookName->registerPostHookVars(['instance' => $instance]);

            if ($input->getOption('validate') && $instance->selection != 'blank : none') {
                CommandHelper::validateInstances([$instance], $instanceCreateHook);
            }

            $instance->updateState('success', $this->getName(), 'Instance created');

            return 0;
        } catch (\Exception $e) {
            /**
             * Stop the instance creation process if an error occurs.
             * Do not clean up the existing instance.
             * Clean up the instance which is being created.
             */
            if ($instance->getId() && $isNewInstance) {
                $instance->delete();
            }
            if ($isNewInstance) {
                $instance->cleanInstanceWebroot();
            }
            $this->io->error("Instance creation steps aborted: \n" . $e->getMessage());
            $hookName->registerFailHookVars([
                'error_message' => $this->io->getLastIOErrorMessage(),
                'error_code' => 'FAIL_OPERATION_CREATE_INSTANCE',
            ]);
            return $e->getCode() ?: -1;
        }
    }
}
