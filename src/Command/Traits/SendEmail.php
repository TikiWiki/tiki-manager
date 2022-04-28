<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command\Traits;

use Laminas\Mail\Exception\RuntimeException;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Sendmail;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mail\Transport\TransportInterface;
use TikiManager\Config\Environment;

trait SendEmail
{
    /**
     * @return TransportInterface
     */
    protected function getMailer()
    {
        $smtpHost = Environment::get('SMTP_HOST');
        $smtpPort = Environment::get('SMTP_PORT', 25);
        $smtpUser = Environment::get('SMTP_USER');
        $smtpPass = Environment::get('SMTP_PASS');
        $smtpAuth = Environment::get('SMTP_AUTH', 'plain');
        $smtpName = Environment::get('SMTP_NAME', 'localhost');

        // Create the Transport
        if ($smtpHost && $smtpPort && $smtpAuth) {
            $transport = new Smtp();
            $options = new SmtpOptions([
                'name' => $smtpName,
                'host' => $smtpHost,
                'port' => $smtpPort,
                'connection_class' => strtolower($smtpAuth),
                'connection_config' => [
                    'username' => $smtpUser,
                    'password' => $smtpPass,
                ]
            ]);
            $transport->setOptions($options);
        }

        return $transport ?? new Sendmail();
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

        if (!$from) {
            throw new \RuntimeException('Unable to determine FROM_EMAIL_ADDRESS required to send emails. Please check README.md file.');
        }

        try {
            $mailer = $this->getMailer();

            // Create a message
            $mailMsg = new Message();
            $mailMsg
                ->setFrom($from)
                ->setSubject($subject)
                ->addTo($to)
                ->setBody($message);

            // Send the message
            $mailer->send($mailMsg);
        } catch (RuntimeException $e) {
            throw new \RuntimeException('Unable to send email notification.' . PHP_EOL . $e->getMessage());
        }
    }
}
