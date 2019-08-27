<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use TikiManager\Command\Helper\CommandHelper;

class ResetManagerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('manager:reset')
            ->setDescription('Reset Tiki Manager')
            ->setHelp('This command allows you to delete all state, backup, cache, and log files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>WARNING!</comment>');
        $output->writeln('<comment>You are about to delete all state, backup, cache, and log files!</comment>');

        $question = new ChoiceQuestion('Do you confirm you want to proceed?', ['confirm', 'cancel']);
        $question->setErrorMessage('Option %s is invalid.');
        $helper = $this->getHelper('question');
        $option = $helper->ask($input, $output, $question);

        if ($option == 'cancel') {
            $output->writeln('<info>Operation canceled.</info>');
            return 0;
        }

        $dirs = [
            $_ENV['TRIM_LOGS'],
            $_ENV['CACHE_FOLDER'],
            $_ENV['BACKUP_FOLDER'],
            $_ENV['ARCHIVE_FOLDER'],
        ];

        $databaseFile = $_ENV['DB_FILE'];

        $logger = new ConsoleLogger($output);
        $result = CommandHelper::clearFolderContents($dirs, $logger);

        if ($result === false) {
            return 1;
        }

        $result = CommandHelper::removeFiles($databaseFile, $logger);

        if ($result === false) {
            return 1;
        }

        $output->writeln('<info>Tiki Manager has been reset.</info>');
    }
}
