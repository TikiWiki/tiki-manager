<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Style\TikiManagerStyle;

abstract class TikiManagerCommand extends Command
{
    /** @var InputInterface */
    protected $input;

    /** @var TikiManagerStyle */
    protected $io;

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
