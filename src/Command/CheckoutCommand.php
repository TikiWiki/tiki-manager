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

class CheckoutCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:checkout')
            ->setDescription('Checkout a different branch or revision on an underlying Git controlled source.')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                'Instance ID (or name) to checkout.'
            )
            ->addOption(
                'folder',
                'f',
                InputOption::VALUE_REQUIRED,
                'Local folder containing a Git repository already checked out or a new folder to checkout. Use \'tiki\' for the main Tiki source or themes/XYZ to manage a version controlled theme, for example.'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Url of the Git repository, e.g. git@gitlab.com:tikiwiki/tiki.git. Only used if you checkout a fresh folder.'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Git branch to checkout.'
            )
            ->addOption(
                'revision',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Git commit hash of a specific revision to checkout.'
            )
            ->setHelp('This command allows you switch to a specific branch or revision of the main Tiki codebase or any other local checkouts of remote repositories. Only Git is supported.');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instances = array_filter($instances, function ($instance) {
            return $instance->vcs_type == 'git';
        });

        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($input->getOption('instance'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $output->writeln('<comment>Note: Only Tiki instances checked out by Git can be managed by this command</comment>');
            if (count($instances)==0) {
                throw new \RuntimeException('No instance available.');
            }
            $this->io->newLine();
            $answer = $this->io->ask('Which instance do you want to checkout', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances, CommandHelper::INSTANCE_SELECTION_SINGLE);
                return reset($selectedInstances)->getId();
            });

            $input->setOption('instance', $answer);
        }

        $instanceId = $input->getOption('instance');
        $instance = Instance::getInstance($instanceId);
        if ($app = $instance->getApplication()) {
            if ($instance->vcs_type != 'git') {
                throw new \RuntimeException('Selected Tiki instance is not a Git checkout.');
            }
        } else {
            throw new \RuntimeException('Unable to initialize Tiki application. Please check if selected instance is a Tiki instance.');
        }

        if (empty($input->getOption('folder'))) {
            $folders = $app->getLocalCheckouts();
            $folders[] = 'new';
            $folder = $this->io->choice('Folder', $folders);

            if ($folder == 'new') {
                $folder = $this->io->ask('Type the folder name where you want to checkout a Git branch. E.g. themes/XYZ', null, function ($answer) {
                    if (empty($answer)) {
                        throw new \RuntimeException('Folder cannot be empty');
                    }
                    return $answer;
                });
            }

            if (empty($input->getOption('url'))) {
                $url = $this->io->ask('What is the URL of the Git repository?', null, function ($answer) {
                    if (empty($answer)) {
                        throw new \RuntimeException('Git URL cannot be empty');
                    }
                    return $answer;
                });
                $input->setOption('url', $url);
            }
            $input->setOption('folder', $folder);
        }

        if (empty($input->getOption('branch'))) {
            $branch = $this->io->ask('What branch should be checked out?', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Branch name cannot be empty');
                }
                return $answer;
            });
            $input->setOption('branch', $branch);
        }

        if (empty($input->getOption('revision'))) {
            $revision = $this->io->ask('What revision should be checked out? Leave empty to checkout HEAD of the branch.', null, function ($answer) {
                return $answer;
            });
            $input->setOption('revision', $revision);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instanceId = $input->getOption('instance');
        $instance = Instance::getInstance($instanceId);

        if (! $instance) {
            throw new \RuntimeException(sprintf('Instance %s not found.', $instanceId));
        }

        if ($app = $instance->getApplication()) {
            if ($instance->vcs_type != 'git') {
                throw new \RuntimeException('Selected Tiki instance is not a Git checkout.');
            }
        } else {
            throw new \RuntimeException('Unable to initialize Tiki application. Please check if selected instance is a Tiki instance.');
        }

        $folder = $input->getOption('folder');
        $url = $input->getOption('url');
        $branch = $input->getOption('branch');
        $revision = $input->getOption('revision');

        if (empty($folder)) {
            throw new \RuntimeException('Folder name cannot be empty.');
        }

        if (empty($branch)) {
            throw new \RuntimeException('Branch name cannot be empty.');
        }

        if ($revision) {
            $this->io->writeln(sprintf('Checking out revision %s of %s into %s...', $revision, $branch, $folder));
        } else {
            $this->io->writeln(sprintf('Checking out %s into %s...', $branch, $folder));
        }

        if ($folder == 'tiki') {
            $folder = '.';
        }

        try {
            $vcs = $app->getVcsInstance();
            $access = $instance->getBestAccess('scripting');
            if ($access->fileExists($folder)) {
                $this->io->writeln('Folder exists, setting remote branch...');
                $vcs->setRepositoryUrl($url);
                $this->io->writeln($vcs->remoteSetUrl($folder, $url));
                $this->io->writeln($vcs->remoteSetBranch($folder, $branch));
                $isShallow = $vcs->isShallow($folder);
                $options = [];
                if ($isShallow && !$revision) {
                    $options['--depth'] = 1;
                }
                if ($isShallow && $revision) {
                    $this->io->writeln('Fetch the full history of the branch...');
                    $this->io->writeln($vcs->unshallow($folder));
                }
                $this->io->writeln('Fetching remote branch...');
                $this->io->writeln($vcs->fetch($folder, $branch, 'origin', $options));
                $this->io->writeln('Checking out...');
                $this->io->writeln($vcs->checkoutBranch($folder, $branch, $revision));
            } else {
                $this->io->writeln(sprintf('Folder doesn\'t exist, cloning %s...', $url));
                $vcs->setRepositoryUrl($url);
                $this->io->writeln($vcs->clone($branch, $folder));
                if ($revision) {
                    $this->io->writeln('Fetch the full history of the branch...');
                    $this->io->writeln($vcs->unshallow($folder));
                    $this->io->writeln('Checking out specific revision...');
                    $this->io->writeln($vcs->checkoutBranch($folder, $branch, $revision));
                }
            }
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
        }

        $this->io->writeln('Done.');

        return 0;
    }
}
