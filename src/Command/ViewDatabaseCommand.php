<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ViewDatabaseCommand extends TikiManagerCommand
{
    const SQLITE = 'sqlite3';

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('database:view')
            ->setDescription('View database')
            ->setHelp('This command allows you to view the database content');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databaseFile = $_ENV['DB_FILE'];

        $sqliteVersion = shell_exec(self::SQLITE.' --version');
        if ($sqliteVersion) {
            passthru('sqlite3 ' . $databaseFile);
        } else {
            $output->writeln('<error>' . self::SQLITE . ' is not available, please install and try again.</error>');
        }

        return 0;
    }
}
