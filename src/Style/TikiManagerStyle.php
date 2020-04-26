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

        foreach ($messages as $message) {
            $message .= $this->appendFlush();
            parent::write($message, $newline, $type);
        }

        parent::writeln($this->appendFlush(), $type);
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $message) {
            parent::writeln($message, $type);
        }

        parent::writeln($this->appendFlush(), $type);
    }

    protected function appendFlush()
    {
        // By default php-fpm uses 4096B buffers.
        // This forces the buffer to get enough data to output.
        return PHP_SAPI != 'cli' ? str_pad('', 4 * 1024) : '';
    }
}
