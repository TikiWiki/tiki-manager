<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceUpgrade;
use TikiManager\Command\Traits\SendEmail;
use TikiManager\Libs\Helpers\Checksum;
use TikiManager\Logger\ArrayHandler;

class UpdateInstanceCommand extends TikiManagerCommand
{
    use InstanceUpgrade;
    use SendEmail;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:update')
            ->setDescription('Update instance')
            ->setHelp('This command allows you update an instance')
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_OPTIONAL,
                'List of instance IDs to be updated, separated by comma (,)'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Instance branch to update'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email address to notify in case of failure. Use , (comma) to separate multiple email addresses.'
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
                'lag',
                null,
                InputOption::VALUE_REQUIRED,
                'Time delay commits by X number of days. Useful for avoiding newly introduced bugs in automated updates.'
            )
            ->addOption(
                'stash',
                null,
                InputOption::VALUE_NONE,
                'Saves your local modifications, and try to apply after update/upgrade'
            )
            ->addOption(
                'ignore-requirements',
                null,
                InputOption::VALUE_NONE,
                'Ignore version requirements. Allows to select non-supported branches, useful for testing.'
            )
            ->addOption(
                'no-maintenance',
                null,
                InputOption::VALUE_NONE,
                'Update will be performed without setting the website in maintenance mode.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $enableMaintenance = empty($input->getOption('no-maintenance'));

        if (isset($instancesInfo)) {
            $instancesOption = $input->getOption('instances');

            $auto = false;
            $switch = false;

            $argument = $input->getArgument('mode');
            if (isset($argument) && !empty($argument)) {
                if (is_array($argument)) {
                    $auto = $input->getArgument('mode')[0] == 'auto';
                    $switch = $input->getArgument('mode')[0] == 'switch';
                } else {
                    $switch = $input->getArgument('mode') == 'switch';
                }
            }

            if ($auto) {
                $instancesIds = array_slice($input->getArgument('mode'), 1);

                $selectedInstances = [];
                foreach ($instancesIds as $index) {
                    if (array_key_exists($index, $instances)) {
                        $selectedInstances[] = $instances[$index];
                    }
                }
            } else {
                $action = 'update';
                if ($switch) {
                    $action = 'upgrade';
                }

                if (empty($instancesOption)) {
                    $this->io->newLine();
                    CommandHelper::renderInstancesTable($output, $instancesInfo);

                    $this->io->newLine();
                    $this->io->writeln('<comment>In case you want to ' . $action . ' more than one instance, please use a comma (,) between the values</comment>');

                    $selectedInstances = $this->io->ask(
                        'Which instance(s) do you want to ' . $action . '?',
                        null,
                        function ($answer) use ($instances) {
                            return CommandHelper::validateInstanceSelection($answer, $instances);
                        }
                    );
                } else {
                    CommandHelper::validateInstanceSelection($instancesOption, $instances);
                    $instancesOption = explode(',', $instancesOption);
                    $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));
                }
            }

            $lag = $input->getOption('lag');
            if ($lag && (!is_numeric($lag) || $lag < 0)) {
                $this->io->error('Invalid option for --lag, must be a positive integer.');
                return 1;
            }

            $checksumCheck = $input->getOption('check');
            $skipReindex = $input->getOption('skip-reindex');
            $skipCache = $input->getOption('skip-cache-warmup');
            $liveReindex = filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            $logs = [];

            $vcsOptions = [
                'allow_stash' => $input->getOption('stash')
            ];

            $hookName = $this->getCommandHook();
            /** @var Instance $instance */
            foreach ($selectedInstances as $instance) {
                $instanceLogger = $this->logger->withName('instance_' . $instance->id);
                $arrHandler = new ArrayHandler(Logger::ERROR);
                $arrHandler->setFormatter($this->getFormatter());
                $instanceLogger->pushHandler($arrHandler);

                $instanceVCS = $instance->getVersionControlSystem();
                $instanceVCS->setLogger($instanceLogger);
                $instanceVCS->setVCSOptions($vcsOptions);

                // Ensure that the current phpexec is still valid;
                $instance->detectPHP();
                $phpVersion = CommandHelper::formatPhpVersion($instance->phpversion);

                $message = sprintf(
                    "Working on %s\nPHP version %s found at %s.",
                    $instance->name,
                    $phpVersion,
                    $instance->phpexec
                );
                $this->io->writeln('<fg=cyan>' .$message. '</>');

                if ($enableMaintenance) {
                    $instance->lock();
                }

                $app = $instance->getApplication();
                $version = $instance->getLatestVersion();
                $branch_name = $version->getBranch();

                $hookName->registerPostHookVars(['instance' => $instance]);

                $options = [
                    'checksum-check' => $checksumCheck,
                    'skip-reindex' => $skipReindex,
                    'skip-cache-warmup' => $skipCache,
                    'live-reindex' => $liveReindex,
                    'lag' => $lag,
                ];

                if ($switch) {
                    $this->runUpgrade($instance, $options, $instanceLogger);
                } else {
                    $app_branch = $app->getBranch();
                    if ($app_branch == $branch_name) {
                        $this->runUpdate($instance, $options, $instanceLogger);
                    } else {
                        $message = 'Tiki Application branch is different than the one stored in the Tiki Manager db.';
                        $instanceLogger->error($message);
                    }
                }

                if ($instanceLogs = $arrHandler->getLog()) {
                    $logs[] = sprintf('<h1>%s (id: %s)</h1>', $instance->name, $instance->id);
                    $logs[] = '<pre>' . implode("\n", $instanceLogs) . '</pre>';
                }

                if ($instance->isLocked()) {
                    $instance->unlock();
                }
            }

            $emails = $input->getOption('email') ?? '';
            $emails = array_filter(explode(',', $emails), function ($email) {
                return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            });

            if (!empty($logs) && !empty($emails)) {
                $logs = implode(PHP_EOL, $logs);
                try {
                    $this->sendEmail(
                        $emails,
                        '[Tiki-Manager] ' . $this->getName() . ' report failures',
                        $logs
                    );
                } catch (\RuntimeException $e) {
                    debug($e->getMessage());
                    $this->io->error($e->getMessage());
                }
            }

            if (!empty($logs)) {
                return 1;
            }
        } else {
            $this->io->writeln('<comment>No instances available to update/upgrade.</comment>');
        }

        return 0;
    }

    private function getFormatter()
    {
        $formatter = new LineFormatter();
        $formatter->ignoreEmptyContextAndExtra(true);
        $formatter->allowInlineLineBreaks(true);
        $formatter->includeStacktraces(true);

        return $formatter;
    }

    protected function runUpdate(Instance $instance, array $options, LoggerInterface $logger)
    {
        try {
            $app = $instance->getApplication();
            $filesToResolve = $app->performUpdate($instance, null, $options);
            $version = $instance->getLatestVersion();

            if ($options['checksum-check'] ?? false) {
                Checksum::handleCheckResult($instance, $version, $filesToResolve);
            }
        } catch (\Exception $e) {
            $logger->error('Failed to update instance!', [
                'instance' => $instance->name,
                'exception' => $e,
            ]);
            CommandHelper::setInstanceSetupError($instance->id, $e);
        }
    }

    protected function runUpgrade(Instance $instance, array $options, LoggerInterface $logger)
    {
        $branch = $this->input->getOption('branch');
        $skipRequirements = $this->input->getOption('ignore-requirements') ?? false;
        $selectedVersion = $this->getUpgradeVersion($instance, !$skipRequirements, $branch);

        $target = Version::buildFake($instance->vcs_type, $selectedVersion);

        try {
            $app = $instance->getApplication();
            $filesToResolve = $app->performUpdate($instance, $target, $options);
            $version = $instance->getLatestVersion();

            if ($options['checksum-check'] ?? false) {
                Checksum::handleCheckResult($instance, $version, $filesToResolve);
            }
        } catch (\Exception $e) {
            $logger->error('Failed to upgrade instance!', [
                'instance' => $instance->name,
                'exception' => $e,
            ]);
            CommandHelper::setInstanceSetupError($instance->id, $e);
        }
    }
}
