<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Application\Patch;
use TikiManager\Command\Helper\CommandHelper;

class DeletePatchCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:patch:delete')
            ->setDescription('Delete a patch applied to an instance')
            ->addOption(
                'patch',
                'p',
                InputOption::VALUE_REQUIRED,
                'The patch ID reported from instance:patch:list command'
            )
            ->setHelp('This command allows you to delete a previously applied patch to an instance');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');

        if (empty($input->getOption('patch'))) {
            $rows = [];

            foreach ($instances as $instance) {
                $patches = Patch::getPatches($instance->getId());
                foreach ($patches as $patch) {
                    $rows[] = [
                        'id' => $patch->id,
                        'instance' => $instance->name,
                        'package' => $patch->package,
                        'url' => $patch->url
                    ];
                }
            }
            if ($rows) {
                $table = new Table($output);
                $headers = array_map(function ($headerValue) {
                    return ucwords($headerValue);
                }, array_keys($rows[0]));
                $table
                    ->setHeaders($headers)
                    ->setRows($rows);
                $table->render();
            } else {
                $this->io->writeln('<comment>No patches applied yet.</comment>');
            }
            
            $patch = $this->io->ask('What is the patch ID?', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Patch ID cannot be empty');
                }
                return $answer;
            });
            $input->setOption('patch', $patch);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $patchId = $input->getOption('patch');

        if (empty($patchId)) {
            throw new \RuntimeException('Patch ID cannot be empty.');
        }

        $patch = Patch::find($patchId);
        if (empty($patch)) {
            throw new \RuntimeException(sprintf('Patch %s not found.', $patchId));
        }

        $patch->delete();
        $this->io->writeln('Patch was removed from the list of applied patches. To actually remove the patch, you can restore a backup, update or upgrade without stashing changes, clone from another instance or revert the instance to its original state.');

        return 0;
    }
}
