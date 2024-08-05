<?php

namespace TikiManager\Command;

use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Hooks\TikiCommandHook;
use TikiManager\Style\TikiManagerStyle;

abstract class TikiManagerCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var TikiManagerStyle */
    protected $io;

    public function __construct(string $name = null, LoggerInterface $logger = null)
    {
        parent::__construct($name);
        $this->logger = $logger ?? App::get('Logger');
    }

    /**
     * Configure options that are available in all commands
     */
    protected function configure()
    {
        $this->addOption(
            'skip-hooks',
            null,
            InputOption::VALUE_NONE,
            'Disable hook execution'
        );
    }

    /**
     * @inheritDoc
     */
    public function run(InputInterface $input, OutputInterface $output):int
    {
        Environment::getInstance()->setIO($input, $output);
        $this->io = App::get('io');

        $this->input = $input;
        $this->output = $output;

        return parent::run($input, $output);
    }

    /**
     * Get Command Hook
     *
     * @return TikiCommandHook
     */
    public function getCommandHook(): TikiCommandHook
    {
        $hookHandler = App::get('HookHandler');
        $hookHandler->setLogger($this->logger);

        return $hookHandler->getHook($this->getName());
    }
}
