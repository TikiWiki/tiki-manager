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
use TikiManager\Access\Access;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;

class CreateInstanceCommand extends TikiManagerCommand
{
    use InstanceConfigure;

    /**
     * Setup the command configuration. Parameters and options are added here.
     */
    protected function configure()
    {
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
                'backup-user',
                'bu',
                InputOption::VALUE_REQUIRED,
                'Instance backup user'
            )
            ->addOption(
                'backup-group',
                'bg',
                InputOption::VALUE_REQUIRED,
                'Instance backup group'
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
            );
    }

    /**
     * Execute command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($output->getVerbosity() == OutputInterface::VERBOSITY_DEBUG || $_ENV['TRIM_DEBUG']) {
            $this->io->title('Tiki Manager Info');
            $mock_instance = new Instance();
            $mock_access = Access::getClassFor('local');
            $mock_access = new $mock_access($mock_instance);
            $mock_discovery = new Discovery($mock_instance, $mock_access);

            CommandHelper::displayInfo($mock_discovery);
            $this->io->newLine();
        }

        $this->io->title('New Instance Setup');

        try {
            $instance = new Instance();
            $this->setupAccess($instance);
            $this->setupInstance($instance);
            $this->setupApplication($instance);
            $this->setupDatabase($instance);
            $this->install($instance);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return -1;
        }
    }
}
