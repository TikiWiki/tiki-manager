<?php

namespace TikiManager\Command;

use Monolog\Handler\BufferHandler;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Logger\ConsoleLogger;
use TikiManager\Style\TikiManagerStyle;

abstract class TikiManagerCommand extends Command
{
    /** @var InputInterface */
    protected $input;

    /** @var TikiManagerStyle */
    protected $io;

    /** @var Logger */
    protected $logger;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new Logger('tiki_manager');
        $this->logger->pushHandler(new PsrHandler(new ConsoleLogger($output)));
    }

    /**
     * @inheritDoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        Environment::getInstance()->setIO($input, $output);
        $this->io = App::get('io');

        $this->input = $input;

        return parent::run($input, $output);
    }
}
