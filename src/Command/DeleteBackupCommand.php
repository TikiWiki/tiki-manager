<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use TikiManager\Command\Helper\CommandHelper;

class DeleteBackupCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('backups:delete')
            ->setDescription('Delete backups folder')
            ->setHelp('This command allows you to clear backups folder');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $question = new ChoiceQuestion(
            'Do you confirm you want to delete backups folder?',
            ['confirm', 'cancel'],
            null
        );
        $question->setErrorMessage('Option %s is invalid.');
        $helper = $this->getHelper('question');
        $option = $helper->ask($input, $output, $question);

        if ($option != 'confirm') {
            $output->writeln('<info>Operation canceled.</info>');
            return 0;
        }

        $backupPath = $_ENV['BACKUP_FOLDER'];
        $logger = new ConsoleLogger($output);
        $result = CommandHelper::clearFolderContents($backupPath, $logger);

        if ($result === null) {
            return 1;
        }

        $output->writeln('<info>Backups deleted</info>');
    }
}
