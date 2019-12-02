<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Access\Access;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class ManagerInfoCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('manager:info')
            ->setDescription('Show Tiki Manager Info')
            ->setHelp('This command allows you to show Tiki Manager information');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instance = new Instance();

        $access = Access::getClassFor('local');
        $access = new $access($instance);
        $discovery = new Discovery($instance, $access);

        $io->title('Tiki Manager Info');
        CommandHelper::displayInfo($discovery, $io);
    }
}
