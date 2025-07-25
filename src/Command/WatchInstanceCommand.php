<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\SendEmail;
use TikiManager\Config\App;

class WatchInstanceCommand extends TikiManagerCommand
{
    use SendEmail;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:watch')
            ->setDescription('Perform an hash check')
            ->setHelp('This command allows you to perform the Hash check.')
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email address to contact.'
            )
            ->addOption(
                'exclude',
                'ex',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be excluded, separated by comma (,)'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $email = $input->getOption('email');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $question = CommandHelper::getQuestion('Email address to contact');
            $question->setValidator(function ($value) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please insert a valid email address.');
                }
                return $value;
            });
            $email = $helper->ask($input, $output, $question);
        }
        $input->setOption('email', $email);

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo) && empty($input->getOption('exclude'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $this->io->writeln('<comment>In case you want to ignore more than one instance, please use a comma (,) between the values</comment>');

            $answer = $this->io->ask('Which instance IDs (or names) should be ignored?', null, function ($answer) use ($instances) {
                $excludeInstance = '';
                if (!empty($answer)) {
                    $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                    $excludeInstance = implode(',', CommandHelper::getInstanceIds($selectedInstances));
                }
                return $excludeInstance;
            });

            $input->setOption('exclude', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getOption('email');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidOptionException('Email cannot be empty');
        }

        $excludedInstances = $input->getOption('exclude');

        $log = '';
        $instances = CommandHelper::getInstances('update');

        if (!empty($excludedInstances)) {
            $instancesToExclude = CommandHelper::getInstanceIds(
                CommandHelper::validateInstanceSelection(
                    $excludedInstances,
                    CommandHelper::getInstances('all', true),
                    CommandHelper::INSTANCE_SELECTION_ALLOW_EMPTY | CommandHelper::INSTANCE_SELECTION_IGNORE_INVALID
                )
            );

            foreach ($instances as $key => $instance) {
                if (in_array($instance->id, $instancesToExclude)) {
                    unset($instances[$key]);
                }
            }
        }

        $hook = $this->getCommandHook();
        foreach ($instances as $instance) {
            $version = $instance->getLatestVersion();

            if (!$version) {
                continue;
            }

            $versionError = false;
            $versionRevision = $version->revision;
            $tikiRevision = $instance->getRevision();

            if (empty($versionRevision)) {
                $log .= 'No revision detected for ' . $instance->name . PHP_EOL;
                $versionError = true;
            } elseif ($versionRevision != $tikiRevision) {
                $log .= 'Check ' . $instance->name . ' version conflict' . PHP_EOL;
                $log .= 'Expected revision ' . $versionRevision . ', found revision ' . $tikiRevision . ' on instance.' . PHP_EOL;
                $versionError = true;
            }

            $hook->registerPostHookVars([
                'instance' => $instance,
                'tikiRevision' => $tikiRevision,
                'versionError' => $versionError
            ]);

            if ($versionError) {
                $log .= 'Fix this error with Tiki Manager by running "tiki-manager instance:check" and choose instance "' . $instance->id . '.';
                $log .= PHP_EOL . PHP_EOL;

                continue;
            }

            if ($version->hasChecksums()) {
                $result = $version->performCheck($instance);

                if (count($result['new']) || count($result['mod']) || count($result['del'])) {
                    $log .= $instance->name . ' (' . $instance->weburl . ')' . PHP_EOL;

                    foreach ($result['new'] as $file => $hash) {
                        $log .= '+ ' . $file . PHP_EOL;
                    }
                    foreach ($result['mod'] as $file => $hash) {
                        $log .= 'o ' . $file . PHP_EOL;
                    }
                    foreach ($result['del'] as $file => $hash) {
                        $log .= '- ' . $file . PHP_EOL;
                    }

                    $log .= PHP_EOL . PHP_EOL;
                }
            }
        }

        if (empty($log)) {
            return Command::SUCCESS;
        }

        try {
            $this->sendEmail($email, '[Tiki-Manager] Potential intrusions detected', $log);
        } catch (\RuntimeException $e) {
            debug($e->getMessage());
            $this->io->warning('Could not send an e-mail report: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->io->success('Email sent, please check your inbox.');
        return Command::SUCCESS;
    }
}
