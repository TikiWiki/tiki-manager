<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Helpers;

use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;

class Checksum
{

    /**
     * @param Instance $instance
     * @param Version $version
     * @param array $array
     * @param SymfonyStyle $io
     */
    public static function handleCheckResult(Instance $instance, Version $version, $array, $io)
    {
        if (! $_ENV['INTERACTIVE']) {
            return; // skip
        }
        // $new, $mod, $del
        extract($array);

        // New {{{
        $input = 'p';
        $newFlat = array_keys($new);


        while ($input != 's' && count($new)) {
            $io->writeln("New files found on remote host:");
            foreach ($newFlat as $key => $file) {
                $io->writeln("\t[$key] $file");
            }

            $io->newLine(2);

            do {
                $io->writeln("<comment>What do you want to do about it?</comment>");
                $io->listing([
                    "(P)rint list again",
                    "(V)iew files",
                    "(D)elete files",
                    "(A)dd files to valid list",
                    "(S)kip"
                ]);
                $input = $io->ask('(a0 to add file 0. Or a0-3 to add files 0 to 3)');
            } while ((strlen($input) == 0) || (stripos('pvdas', $input{0}) === false));

            $op = strtolower($input{0});
            $files = getEntries($newFlat, $input);

            switch ($op) {
                case 'd':
                    $access = $instance->getBestAccess('filetransfer');

                    query('BEGIN TRANSACTION');

                    foreach ($files as $file) {
                        $io->writeln("<fg=red>-- $file</>");

                        $access->deleteFile($file);
                        $newFlat = array_diff($newFlat, (array)$file);
                        unset($new[$file]);
                    }

                    query('COMMIT');
                    break;

                case 'a':
                    $app = $instance->getApplication();

                    query('BEGIN TRANSACTION');

                    foreach ($files as $file) {
                        $io->writeln("<info>++ $file</info>");

                        $version->recordFile($new[$file], $file, $app);
                        $newFlat = array_diff($newFlat, (array)$file);
                        unset($new[$file]);
                    }

                    query('COMMIT');
                    break;

                case 'v':
                    $access = $instance->getBestAccess('filetransfer');

                    foreach ($files as $file) {
                        $localName = $access->downloadFile($file);
                        passthru($_ENV['EDITOR'] . " $localName");
                    }
                    break;
            }
        }

        // Modified {{{
        $input = 'p';
        $modFlat = array_keys($mod);

        while ($input != 's' && count($mod)) {
            $io->writeln("Modified files were found on remote host:");

            foreach ($modFlat as $key => $file) {
                $io->writeln("\t[$key] $file");
            }

            $io->newLine(2);

            $input = 'z';
            while (stripos('pvcerus', $input{0}) === false) {
                $io->writeln("<comment>What do you want to do about it?</comment>");
                $io->listing([
                    "(P)rint list again",
                    "(V)iew files",
                    "(C)ompare files with versions in repository",
                    "(E)dit files in place",
                    "(R)eplace with version in repository",
                    "(U)pdate hash to accept file version",
                    "(S)kip"
                ]);
                $input = $io->ask('(e.g. v0 to view file 0)');
            }

            $op = strtolower($input{0});
            $files = getEntries($modFlat, $input);

            switch ($op) {
                case 'v':
                    $access = $instance->getBestAccess('filetransfer');

                    foreach ($files as $file) {
                        $localName = $access->downloadFile($file);
                        passthru($_ENV['EDITOR'] . " $localName");
                    }
                    break;

                case 'e':
                    $app = $instance->getApplication();
                    $access = $instance->getBestAccess('filetransfer');

                    foreach ($files as $file) {
                        $localName = $access->downloadFile($file);
                        passthru($_ENV['EDITOR'] . " $localName");

                        if ($io->confirm('Confirm file replacement?', false)) {
                            $io->writeln("== $file\n");

                            $hash = md5_file($localName);
                            if ($mod[$file] != $hash) {
                                $version->replaceFile($hash, $file, $app);
                            }

                            $access->uploadFile($localName, $file);
                            $modFlat = array_diff($modFlat, (array)$file);
                            unset($mod[$file]);
                        }
                    }
                    break;

                case 'c':
                    $app = $instance->getApplication();
                    $access = $instance->getBestAccess('filetransfer');

                    foreach ($files as $file) {
                        $realFile = $app->getSourceFile($version, $file);
                        $serverFile = $access->downloadFile($file);

                        $diff = $_ENV['DIFF'];
                        passthru("$diff $realFile $serverFile");
                    }
                    break;

                case 'r':
                    $app = $instance->getApplication();
                    $access = $instance->getBestAccess('filetransfer');

                    foreach ($files as $file) {
                        $io->writeln("== $file");

                        $realFile = $app->getSourceFile($version, $file);
                        $access->uploadFile($realFile, $file);

                        $hash = md5_file($realFile);
                        if ($mod[$file] != $hash) {
                            $version->replaceFile($hash, $file, $app);
                        }

                        $modFlat = array_diff($modFlat, (array)$file);
                        unset($mod[$file]);
                    }
                    break;

                case 'u':
                    $app = $instance->getApplication();

                    query('BEGIN TRANSACTION');

                    foreach ($files as $file) {
                        $io->writeln("<info>++ $file</info>");

                        $version->replaceFile($mod[$file], $file, $app);
                        $modFlat = array_diff($modFlat, (array)$file);
                        unset($mod[$file]);
                    }

                    query('COMMIT');
                    break;
            }
        }

        // Deleted {{{
        $input = 'p';
        $delFlat = array_keys($del);

        while ($input != 's' && count($del)) {
            $io->writeln("<comment>Deleted files were found on remote host:</comment>");
            foreach ($delFlat as $key => $file) {
                $io->writeln("\t[$key] $file");
            }

            $io->newLine(2);

            $input = 'z';
            while (stripos('drs', $input{0}) === false) {
                $io->writeln("<comment>What do you want to do about it?</comment>");
                $io->listing([
                    "(R)estore version in repository",
                    "(D)elete hash to accept file removal",
                    "(S)kip"
                ]);
                $input = $io->ask('(e.g. r0 to restore file 0)');
            }

            $op = strtolower($input{0});
            $files = getEntries($delFlat, $input);

            switch ($op) {
                case 'r':
                    $app = $instance->getApplication();
                    $access = $instance->getBestAccess('filetransfer');

                    foreach ($files as $file) {
                        $io->writeln(">> $file");

                        $realFile = $app->getSourceFile($version, $file);
                        $access->uploadFile($realFile, $file);

                        $hash = md5_file($realFile);
                        if ($del[$file] != $hash) {
                            $version->replaceFile($hash, $file, $app);
                        }

                        $delFlat = array_diff($delFlat, (array)$file);
                        unset($del[$file]);
                    }
                    break;

                case 'd':
                    query('BEGIN TRANSACTION');
                    foreach ($files as $file) {
                        $io->writeln("<fg=red>-- $file</>");

                        $version->removeFile($file);

                        $delFlat = array_diff($delFlat, (array)$file);
                        unset($del[$file]);
                    }
                    query('COMMIT');
                    break;
            }
        }
    }
}
