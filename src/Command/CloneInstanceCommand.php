<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;

class CloneInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:clone')
            ->setDescription('Clone instance')
            ->setHelp('This command allows you make another identical copy of Tiki')
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io = new SymfonyStyle($input, $output);
            $helper = $this->getHelper('question');

            $clone = false;
            $cloneUpgrade = false;
            $offset = 0;

            $argument = $input->getArgument('mode');
            if (isset($argument) && ! empty($argument)) {
                if (is_array($argument)) {
                    $clone = $input->getArgument('mode')[0] == 'clone' ? true : false;
                    $cloneUpgrade = $input->getArgument('mode')[0] == 'upgrade' ? true : false;
                } else {
                    $cloneUpgrade = $input->getArgument('mode') == 'upgrade' ? true : false;
                }
            }

            if ($clone == false && $cloneUpgrade == false) {
                $clone = true;
            } else {
                $offset = 1;
            }

            $arguments = array_slice($input->getArgument('mode'), $offset);
            if (! empty($arguments[0])) {
                $selectedSourceInstances = getEntries($instances, $arguments[0]);
            } else {
                $io->newLine();
                $output->writeln('<comment>NOTE: Clone operations are only available on Local and SSH instances.</comment>');

                $io->newLine();
                $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                $question = CommandHelper::getQuestion('Select the source instance', null);
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedSourceInstances = $helper->ask($input, $output, $question);
            }

            $instances_pruned = [];
            foreach ($instances as $instance) {
                if ($instance->getId() == $selectedSourceInstances[0]->getId()) {
                    continue;
                }
                $instances_pruned[$instance->getId()] = $instance;
            }
            $instances = $instances_pruned;

            $instancesInfo = CommandHelper::getInstancesInfo($instances);
            if (isset($instancesInfo)) {
                if (! empty($arguments[1])) {
                    $selectedDestinationInstances = getEntries($instances, $arguments[1]);
                } else {
                    $io->newLine();
                    $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                    $question = CommandHelper::getQuestion('Select the destination instance(s)', null);
                    $question->setValidator(function ($answer) use ($instances) {
                        return CommandHelper::validateInstanceSelection($answer, $instances);
                    });

                    $selectedDestinationInstances = $helper->ask($input, $output, $question);
                }

                $upgrade_version = [];
                if ($cloneUpgrade) {
                    if (! empty($arguments[2])) {
                        $upgrade_version = Version::buildFake('svn', $arguments[2]);
                    } else {
                        $upgrade_version = $this->getUpgradeVersion($selectedSourceInstances[0], $helper, $input, $output);
                    }
                }

                $output->writeln('<fg=cyan>Creating snapshot of: ' . $selectedSourceInstances[0]->name . '</>');
                $archive = $selectedSourceInstances[0]->backup();
                if ($archive === null) {
                    $output->writeln('<error>Error: Snapshot creation failed.</error>');
                    exit(-1);
                }

                foreach ($selectedDestinationInstances as $destinationInstance) {
                    $output->writeln('<fg=cyan>Initiating clone of ' . $selectedSourceInstances[0]->name . ' to ' . $destinationInstance->name . '</>');

                    $destinationInstance->app = $selectedSourceInstances[0]->app; // Required to setup database connection
                    $databaseConfig = CommandHelper::setupDatabaseConnection($destinationInstance, $input, $output);
                    $destinationInstance->setDatabaseConfig($databaseConfig);

                    $destinationInstance->lock();
                    $destinationInstance->restore($selectedSourceInstances[0]->app, $archive, true);

                    if ($cloneUpgrade) {
                        $output->writeln('<fg=cyan>Upgrading to version ' . $upgrade_version->branch . '</>');
                        $app = $destinationInstance->getApplication();
                        $app->performUpgrade($destinationInstance, $upgrade_version, false);
                    }
                    $destinationInstance->unlock();
                }

                $output->writeln('<fg=cyan>Deleting archive...</>');
                $access = $selectedSourceInstances[0]->getBestAccess('scripting');
                $access->shellExec("rm -f " . $archive);
            } else {
                $output->writeln('<comment>No instances available as destination.</comment>');
            }
        } else {
            $output->writeln('<comment>No instances available to clone/clone and upgrade.</comment>');
        }
    }

    /**
     * Get version to update instance to
     *
     * @param $instance
     * @param $helper
     * @param $input
     * @param $output
     * @return mixed
     */
    private function getUpgradeVersion($instance, $helper, $input, $output)
    {
        $found_incompatibilities = false;
        $instance->detectPHP();

        $app = $instance->getApplication();
        $versions = $app->getVersions();

        foreach ($versions as $key => $version) {
            preg_match('/(\d+\.|trunk)/', $version->branch, $matches);
            if (array_key_exists(0, $matches)) {
                if ((($matches[0] >= 13) || ($matches[0] == 'trunk')) &&
                    ($instance->phpversion < 50500)) {
                    // Nothing to do, this match is incompatible...
                    $found_incompatibilities = true;
                } else {
                    $output->writeln('[' . $key . '] ' . $version->type . ' : ' . $version->branch);
                }
            }
        }

        $output->writeln('[ -1] blank : none');

        $matches = [];
        preg_match('/(\d+)(\d{2})(\d{2})$/', $instance->phpversion, $matches);

        if (count($matches) == 4) {
            $output->writeln('<fg=cyan>We detected PHP release: ' . $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '</>');
        }

        if ($found_incompatibilities) {
            $output->writeln('<comment>If some versions are not offered, it\'s likely because the host</comment>');
            $output->writeln('<comment>server doesn\'t meet the requirements for that version (ex: PHP version is too old)</comment>');
        }

        $question = CommandHelper::getQuestion('Which version do you want to update to', null, '?');
        $selectedVersion = $helper->ask($input, $output, $question);
        $versionEntries = getEntries($versions, $selectedVersion);

        return reset($versionEntries);
    }
}
