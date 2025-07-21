<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Traits\SendEmail;

class ManagerTestSendEmailCommand extends TikiManagerCommand
{
    use SendEmail;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('manager:test-send-email')
            ->setDescription('Test send email')
            ->setHelp('This command allows you to check if Tiki Manager can send emails')
            ->addArgument(
                'to',
                InputArgument::REQUIRED,
                'The email address to send the test message'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $emailTo = $input->getArgument('to');
        $transport = $this->getCurrentTransportName();

        $this->io->note('Email settings can be configured in .env file');

        $this->io->writeln(sprintf('Using %s', strtolower($transport)));

        $subject = 'Tiki Manager Email Test';
        $body = 'Email test from Tiki Manager';
        $this->sendEmail($emailTo, $subject, $body);

        $this->io->success('Email sent. Check your mailbox.');

        return 0;
    }
}
