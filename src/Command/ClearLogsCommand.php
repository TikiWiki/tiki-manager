<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;

class ClearLogsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('logs:clear')
            ->setDescription('Clear logs folder')
            ->setHelp('This command allows you to clear the logs folder');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = CommandHelper::getQuestion('Do you want to clear logs folder? [y,n]');
        $question->setNormalizer(function ($value) {
            return (strtolower($value{0}) == 'y') ? true : false;
        });
        $confirm = $helper->ask($input, $output, $question);

        if (!$confirm) {
            $output->writeln('<info>Operation canceled.</info>');
            return 0;
        }

        $logsPath = $_ENV['TRIM_LOGS'];
        $logger = new ConsoleLogger($output);
        $result = CommandHelper::clearFolderContents($logsPath, $logger);

        if ($result === null) {
            return 1;
        }

        $output->writeln('<info>Logs cleared</info>');
    }
}
