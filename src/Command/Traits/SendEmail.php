<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command\Traits;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Libs\Host\Local as LocalHost;
use TikiManager\Libs\Host\Command;
use TikiManager\Style\TikiManagerStyle;

trait SendEmail
{
    private TransportInterface $currentMailerTransport;

    /**
     * Returns the name of the current mail transport being used.
     */
    protected function getCurrentTransportName(): string
    {
        if (!isset($this->currentMailerTransport)) {
            $this->getMailer();
        }

        return (new \ReflectionClass($this->currentMailerTransport))->getShortName();
    }

    /**
     * Initializes and returns a Symfony Mailer instance.
     *
     * This method checks for SMTP configuration in the environment.
     * - If SMTP credentials (host, user, pass) are provided, it uses EsmtpTransport with:
     *   - Username/password authentication
     *   - Local domain set for EHLO/HELO commands
     *   - TLS disabled by default (can be toggled via constructor)
     *   - Only the PlainAuthenticator is used unless overridden internally
     *
     * - If any SMTP setting is missing, it falls back to SendmailTransport,
     *   which relies on the system's sendmail binary.
     *
     * Note: This method sets `$this->currentMailerTransport` to be reused
     * for introspection or future reference.
     *
     * @return \Symfony\Component\Mailer\Mailer
     */
    protected function getMailer()
    {
        $smtpHost = Environment::get('SMTP_HOST');
        $smtpPort = Environment::get('SMTP_PORT', 25);
        $smtpUser = Environment::get('SMTP_USER');
        $smtpPass = Environment::get('SMTP_PASS');
        $smtpName = Environment::get('SMTP_NAME', 'localhost');

        if (empty($smtpUser) || empty($smtpPass) || empty($smtpHost)) {
            $this->currentMailerTransport = new SendmailTransport();
            return new Mailer($this->currentMailerTransport);
        }

        $transport = new EsmtpTransport($smtpHost, $smtpPort);
        $transport->setUsername($smtpUser);
        $transport->setPassword($smtpPass);
        $transport->setLocalDomain($smtpName);

        $this->currentMailerTransport = $transport;
        return new Mailer($this->currentMailerTransport);
    }

    /**
     * @param string|array $to
     * @param string $subject
     * @param string $message
     * @param string|null $from
     * @throws \RuntimeException
     * @void
     */
    protected function sendEmail($to, $subject, $message, $from = null): void
    {
        $from = $from ?: Environment::get('FROM_EMAIL_ADDRESS');
        $mailer = $this->getMailer();

        /**
         * Symfony Mailer requires every email to have a "From" or "Sender" header,
         * regardless of the transport being used. If neither is set, it throws a LogicException
         * during message preparation (e.g. via Message::getPreparedHeaders()).
         *
         * Even though sendmail (used via SendmailTransport) can fall back to a system default sender,
         * Symfony does not allow sending such emails unless a From address is explicitly defined in the Email object.
         *
         * To handle this:
         * - If FROM_EMAIL_ADDRESS is not set and we're using sendmail, we mimic a valid From address using the current
         *   system username (via `id -un` or `whoami`) to satisfy Symfony’s validation (e.g. user@localhost).
         * - This address is not actually used by sendmail to determine the envelope sender — it just satisfies the header requirement.
         * - If using SMTP, we throw an exception since a proper From address must be explicitly configured.
         */
        if (empty($from)) {
            if (!($this->currentMailerTransport instanceof SendmailTransport)) {
                throw new \RuntimeException('FROM_EMAIL_ADDRESS is not set and SMTP is required. Please check README.md.');
            }

            /** @var TikiManagerStyle $io */
            $io = App::get('io');
            $io->info('The value of FROM_EMAIL_ADDRESS is not set in the .env file. Using the default email from the system.');

            $cmd = ApplicationHelper::isWindows() ? 'echo %USERNAME%' : 'id -un';
            $localHost = new LocalHost();
            $command = new Command($cmd);
            $localHost->runCommand($command);
            $output = $command->getStdoutContent() ?: 'no-reply';
            $from = sprintf('%s@localhost', trim($output));
        }

        try {
            $email = (new Email())
                ->subject($subject)
                ->text($message);

            $email->from($from);

            $address = is_array($to) ? new Address(...$to) : new Address($to);

            $email->to($address);

            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Unable to send email notification.' . PHP_EOL . $e->getMessage());
        }
    }
}
