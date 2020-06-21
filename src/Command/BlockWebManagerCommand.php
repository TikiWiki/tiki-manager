<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Config\App;

class BlockWebManagerCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('webmanager:block')
            ->setDescription('Block webmanager login related process')
            ->setHelp('This command helps performing some operations related with the tiki-manager login process via web manager.')
            ->addOption('reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $info = App::get('info');

        if ($input->getOption('reset')) {
            $info->resetLoginAttempts();
            $output->writeln('<info>WebManager login attempts were reset.</info>');
        }
    }
}
