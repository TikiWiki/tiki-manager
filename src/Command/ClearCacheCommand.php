<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;

class ClearCacheCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:clear')
            ->setDescription('Clears application\'s cache')
            ->setHelp('This command allows you to clear the application\'s cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cachePath = $_ENV['CACHE_FOLDER'];
        $logger = new ConsoleLogger($output);
        $result = CommandHelper::clearFolderContents($cachePath, $logger);

        if ($result === null) {
            return 1;
        }

        $output->writeln('<info>Cache cleared successfully.</info>');
        return 0;
    }
}
