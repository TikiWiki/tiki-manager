<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Command\Helper\CommandHelper;

class TikiVersionCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('tiki:versions')
            ->setDescription('List Tiki versions')
            ->addOption('vcs', null, InputOption::VALUE_OPTIONAL, 'Filter versions by Version Control System')
            ->setHelp('This command allows you to list all Tiki versions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $vcsOption = $input->getOption('vcs');
        $vcsOption = $vcsOption ? strtoupper($vcsOption) : '';
        if (! in_array($vcsOption, ['SVN', 'GIT', 'SRC'])) {
            $vcsOption = '';
        }

        $versions = CommandHelper::getVersions($vcsOption);

        // unset blank
        unset($versions[-1]);

        $versionsInfo = CommandHelper::getVersionsInfo($versions);
        if (isset($versionsInfo)) {
            $this->io->newLine();
            CommandHelper::renderVersionsTable($output, $versionsInfo);
        } else {
            $output->writeln('<comment>No versions available to list.</comment>');
        }

        return 0;
    }
}
