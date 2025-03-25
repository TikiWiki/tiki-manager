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
use TikiManager\Application\Patch;
use TikiManager\Command\Helper\CommandHelper;

class ApplyPatchCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:patch:apply')
            ->setDescription('Apply a patch of Tiki source or 3rd party vendor source to an instance')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to apply the patch on, separated by comma (,)'
            )
            ->addOption(
                'package',
                'p',
                InputOption::VALUE_REQUIRED,
                'Composer package name or \'tiki\' if it is a MR to the Tiki codebase'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'Url of the patch, e.g. https://gitlab.com/tikiwiki/tiki/-/merge_requests/1374.patch'
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
                'warmup-include-modules',
                null,
                InputOption::VALUE_NONE,
                'Include modules in cache warmup (default is only templates and misc).'
            )
            ->addOption(
                'live-reindex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Live reindex, set instance maintenance off and after perform index rebuild.',
                true
            )
            ->setHelp('This command allows you to apply a vendor patch (merge request, pull request) to a 3rd party vendor package or to the Tiki source itself');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($input->getOption('instances'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $output->writeln('<comment>Note: Only Tiki instances can have patches applied</comment>');
            $this->io->newLine();
            $output->writeln('<comment>In case you want to check more than one instance, please use a comma (,) between the values</comment>');
            $answer = $this->io->ask('Which instance(s) do you want to apply patch to', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });

            $input->setOption('instances', $answer);
        }

        if (empty($input->getOption('package'))) {
            $package = $this->io->ask('What is the name of the composer package to be patched (note: type tiki to apply a Tiki patch)?', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Package name cannot be empty');
                }
                return $answer;
            });
            $input->setOption('package', $package);
        }

        if (empty($input->getOption('url'))) {
            $url = $this->io->ask('What is the URL of the patch?', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Patch URL cannot be empty');
                }
                return $answer;
            });
            $input->setOption('url', $url);
        }

        if (empty($input->getOption('skip-reindex'))) {
            $skipReindex = $this->io->confirm('Skip rebuilding index?', false);
            $input->setOption('skip-reindex', $skipReindex);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $package = $input->getOption('package');
        $url = $input->getOption('url');
        $skipReindex = $input->getOption('skip-reindex');
        $skipCache = $input->getOption('skip-cache-warmup');
        $warmupIncludeModules = $input->getOption('warmup-include-modules');
        $liveReindex = filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        if (!isset($instancesInfo)) {
            throw new \RuntimeException('No Tiki instances available to apply a patch-.');
        }

        if (empty($package)) {
            throw new \RuntimeException('Package name cannot be empty.');
        }

        if (empty($url)) {
            throw new \RuntimeException('Patch URL cannot be empty.');
        }

        $selectedInstances = CommandHelper::validateInstanceSelection($input->getOption('instances'), $instances);

        $hookName = $this->getCommandHook();
        foreach ($selectedInstances as $instance) {
            $patch = Patch::initialize($instance->getId(), $package, $url);
            if ($patch->exists()) {
                $this->io->warning(sprintf('Patch already applied to %s', $instance->name));
                continue;
            }
            $this->io->section(sprintf('Applying patch on %s to %s:', $patch->package, $instance->name));
            try {
                $result = $instance->getApplication()->applyPatch($patch, [
                    'skip-reindex' => $skipReindex,
                    'skip-cache-warmup' => $skipCache,
                    'warmup-include-modules' => $warmupIncludeModules,
                    'live-reindex' => $liveReindex
                ]);
                if ($result) {
                    $patch->save();
                    $this->io->writeln('Done.');
                }
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
                continue;
            }

            $hookName->registerPostHookVars([
                'instance' => $instance,
            ]);
        }

        $hookName->registerPostHookVars([
            'package' => $package,
            'url' => $url,
        ]);

        return 0;
    }
}
