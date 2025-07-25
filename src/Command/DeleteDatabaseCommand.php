<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;

class DeleteDatabaseCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('database:delete')
            ->setDescription('Delete database')
            ->setHelp('This command allows you to delete the database file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $question = CommandHelper::getQuestion('Do you want to delete database? [y,n]');
        $question->setNormalizer(function ($value) {
            return (strtolower($value[0]) == 'y') ? true : false;
        });
        $confirm = $helper->ask($input, $output, $question);

        if (!$confirm) {
            $output->writeln('<info>Operation canceled.</info>');
            return 0;
        }

        $databaseFile = $_ENV['DB_FILE'];

        $logger = new ConsoleLogger($output);
        $result = CommandHelper::removeFiles($databaseFile, $logger);

        if ($result === false) {
            return 1;
        }

        $output->writeln('<info>Database file deleted</info>');
        return 0;
    }
}
