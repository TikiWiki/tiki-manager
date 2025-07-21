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
use TikiManager\Libs\Host\Exception\CommandException;

class CreateTemporaryUserInstanceCommand extends TikiManagerCommand
{
    const GUEST_PREFIX = 'tikimanager';
    const GUEST_TTL = 86400;

    protected function configure()
    {
        $this
            ->setName('instance:users:temporary')
            ->setDescription('Create a temporary user with determined user groups on a Tiki instance.')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                'Instance ID (or name) to create a temporary user on.'
            )
            ->addOption(
                'groups',
                'g',
                InputOption::VALUE_OPTIONAL,
                'Group names to be added to the temporary user (case-sensitive).'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instanceId = $input->getOption('instance');
        if (empty($instanceId)) {
            $instances = CommandHelper::getInstances('tiki');
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (! isset($instancesInfo)) {
                return;
            }

            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $instanceId = $this->io->ask(
                'Which instance do you want to create the temporary user on?',
                null,
                function ($answer) use ($instances, $input) {
                    $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances, CommandHelper::INSTANCE_SELECTION_SINGLE);
                    $instanceId = reset($selectedInstances)->getId(); // first element ID (INSTANCE_SELECTION_SINGLE ensure that we have one and only one result)

                    $input->setOption('instance', $instanceId);

                    return $instanceId;
                }
            );
        }

        $groups = $input->getOption('groups');
        if (empty($groups)) {
            $groups = $this->io->ask(
                'Which user groups do you want to assign to this temporary user?',
                'Admins',
                function ($answer) use ($input) {
                    if (empty($answer)) {
                        throw new \RuntimeException('Invalid user groups selected.');
                    }

                    $input->setOption('groups', $answer);

                    return $answer;
                }
            );
        }

        $instance = Instance::getInstance($instanceId);
        if ($instance->getApplication()->getPref('auth_token_access') !== 'y') {
            $this->io->warning('Temporary users are not active on this Tiki instance (preference "auth_token_access").');
            $this->io->ask('Would you like to activate it?', 'y', function ($answer) {
                if (strtolower($answer) !== 'y') {
                    $this->io->text('Creation of temporary user was aborted');
                    exit(1);
                }
            });

            $instance->getApplication()->setPref('auth_token_access', 'y');
        }

        if (empty($instance->getApplication()->getPref('fallbackBaseUrl'))) {
            $this->io->warning('Fallback base url is empty on this Tiki instance (preference "fallbackBaseUrl").');
            $this->io->ask('Would you like use instance weburl "'. $instance->weburl . '" to define it?', 'y', function ($answer) {
                if (strtolower($answer) !== 'y') {
                    $this->io->text('Creation of temporary user was aborted');
                    exit(1);
                }
            });

            $instance->getApplication()->setPref('fallbackBaseUrl', $instance->weburl);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $allIinstances = CommandHelper::getInstances('tiki');
        $selectedInstances = CommandHelper::validateInstanceSelection($answer, $allInstances, CommandHelper::INSTANCE_SELECTION_SINGLE);
        $instance = reset($selectedInstances); // first element
        if (empty($instance)) {
            $this->io->warning('No Tiki instances available.');
            return 1;
        }

        if (empty($instance->contact)) {
            $this->io->error('Instance email not found. Please setup an instance email.');
            return 1;
        }

        try {
            CommandHelper::validateEmailInput($instance->contact);
        } catch (\RuntimeException $e) {
            $this->io->error('Instance email not valid. Please change the instance email.');
            return 1;
        }

        $access = $instance->getBestAccess('scripting');

        try {
            $command = $access->createCommand("{$instance->phpexec} console.php users:temporary", [
                '--emails=' . $instance->contact,
                '--groups=' . $input->getOption('groups'),
                '--expiry=' . self::GUEST_TTL,
                '--prefix=' . self::GUEST_PREFIX,
            ]);

            $data = $access->runCommand($command);

            if ($command->getReturn() !== 0) {
                $this->io->writeln(
                    '<error>' . $data->getStdoutContent() . '</error>'
                );
                return 1;
            }

            $this->io->info($data->getStdoutContent());
        } catch (CommandException $e) {
            $this->io->error('Something went wrong creating the temporary user. Please check the logs for more information.');
            $this->logger->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
