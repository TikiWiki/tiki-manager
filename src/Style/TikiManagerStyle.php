<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Style;

use Symfony\Component\Console\Style\SymfonyStyle;

class TikiManagerStyle extends SymfonyStyle
{
    protected $lastIOErrorMessage;

    public function error($message)
    {
        $this->lastIOErrorMessage = $message;
        parent::error($message);
    }

    public function getLastIOErrorMessage()
    {
        return $this->lastIOErrorMessage;
    }

    public function info($text)
    {
        $this->writeln("<info>$text</info>");
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }

        $index = 0;
        $count = count($messages);
        foreach ($messages as $message) {
            //If it's last message to send, append flush if needed
            $message .= $count == ++$index ? $this->appendFlush() : '';
            parent::write($message, $newline, $type);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }

        $index = 0;
        $count = count($messages);
        foreach ($messages as $message) {
            //If it's last message to send, append flush if needed
            $message .= $count == ++$index ? $this->appendFlush() : '';
            parent::writeln($message, $type);
        }
    }

    protected function appendFlush()
    {
        // By default php-fpm uses 4096B buffers.
        // This forces the buffer to get enough data to output.
        return PHP_SAPI != 'cli' ? str_pad('', 4 * 1024) : '';
    }
}
