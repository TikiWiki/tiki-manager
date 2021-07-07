<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command\Traits;

use TikiManager\Config\Environment;

trait SendEmail
{
    /**
     * @return \Swift_Mailer
     */
    protected function getMailer()
    {
        $smtpHost = Environment::get('SMTP_HOST');
        $smtpPort = Environment::get('SMTP_PORT', 25);
        $smtpUser = Environment::get('SMTP_USER');
        $smtpPass = Environment::get('SMTP_PASS');

        // Create the Transport
        if ($smtpHost && $smtpPort) {
            $transport = (new \Swift_SmtpTransport($smtpHost, $smtpPort))
                ->setUsername($smtpUser)
                ->setPassword($smtpPass);
        } else {
            $transport = new \Swift_SendmailTransport();
        }

        // Create the Mailer using your created Transport
        return new \Swift_Mailer($transport);
    }

    /**
     * @param $to
     * @param $subject
     * @param $message
     * @param null $from
     * @return int
     */
    protected function sendEmail($to, $subject, $message, $from = null)
    {
        $from = $from ?: Environment::get('FROM_EMAIL_ADDRESS');

        if (!$from) {
            throw new \RuntimeException('Unable to determine FROM_EMAIL_ADDRESS required to send emails. Please check README.md file.');
        }

        try {
            $mailer = $this->getMailer();

            // Create a message
            $message = (new \Swift_Message($subject))
                ->setTo(is_array($to) ? $to : [$to])
                ->setBody($message);

            $message->setFrom($from);

            // Send the message
            return $mailer->send($message);
        } catch (\Swift_SwiftException $e) {
            throw new \RuntimeException('Unable to send email notification.' . PHP_EOL . $e->getMessage());
        }
    }


}