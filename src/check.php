<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

function handleCheckResult($instance, $version, $array)
{
    // $new, $mod, $del
    extract($array);

    // New {{{
    $input = 'p';
    $newFlat = array_keys($new);

    while ($input != 's' && count($new)) {
        echo "New files found on remote host:\n";
        foreach ($newFlat as $key => $file)
            echo "\t[$key] $file\n";

        echo "\n\n";

        do {
            echo "\tWhat do you want to do about it?\n" .
                "\t(P)rint list again\n\t(V)iew files\n" .
                "\t(D)elete files\n\t(A)dd files to valid list\n" .
                "\t(S)kip\n(a0 to add file 0. Or a0-3 to add files 0 to 3)\n";
            $input = promptUser('>>> ');
        } while((strlen($input) == 0) || (stripos('pvdas', $input{0}) === false));

        $op = strtolower($input{0});
        $files = getEntries($newFlat, $input);

        switch($op) {
        case 'd':
            $access = $instance->getBestAccess('filetransfer');

            query('BEGIN TRANSACTION');

            foreach ( $files as $file) {
                echo color("-- $file\n", 'red');

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
                echo color("++ $file\n", 'green');

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
                passthru(EDITOR . " $localName");
            }
            break;
        }
    } // }}}

    // Modified {{{
    $input = 'p';
    $modFlat = array_keys($mod);

    while ($input != 's' && count($mod)) {
        echo "Modified files were found on remote host:\n";

        foreach ($modFlat as $key => $file)
            echo "\t[$key] $file\n";

        echo "\n\n";

        $input = 'z';
        while (stripos('pvcerus', $input{0}) === false) {
            echo "\tWhat do you want to do about it? \n" .
                "\t(P)rint list again\n\t(V)iew files\n" .
                "\t(C)ompare files with versions in repository\n" .
                "\t(E)dit files in place\n" .
                "\t(R)eplace with version in repository\n" .
                "\t(U)pdate hash to accept file version\n" .
                "\t(S)kip\n(e.g. v0 to view file 0)\n";
            $input = promptUser('>>> ');
        }

        $op = strtolower($input{0});
        $files = getEntries($modFlat, $input);

        switch ($op) {
        case 'v':
            $access = $instance->getBestAccess('filetransfer');

            foreach ($files as $file) {
                $localName = $access->downloadFile($file);
                passthru(EDITOR . " $localName");
            }
            break;

        case 'e':
            $app = $instance->getApplication();
            $access = $instance->getBestAccess('filetransfer');

            foreach ($files as $file) {
                $localName = $access->downloadFile($file);
                passthru(EDITOR . " $localName");

                if ('yes' == promptUser(
                    'Confirm file replacement?', false, array('yes', 'no'))) {
                    echo "== $file\n";

                    $hash = md5_file($localName);
                    if ($mod[$file] != $hash)
                        $version->replaceFile($hash, $file, $app);

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

                $diff = DIFF;
                passthru("$diff $realFile $serverFile");
            }
            break;

        case 'r':
            $app = $instance->getApplication();
            $access = $instance->getBestAccess('filetransfer');

            foreach ($files as $file) {
                echo "== $file\n";

                $realFile = $app->getSourceFile($version, $file);
                $access->uploadFile($realFile, $file);

                $hash = md5_file($realFile);
                if ($mod[$file] != $hash)
                    $version->replaceFile($hash, $file, $app);

                $modFlat = array_diff($modFlat, (array)$file);
                unset($mod[$file]);
            }
            break;

        case 'u':
            $app = $instance->getApplication();

            query('BEGIN TRANSACTION');

            foreach ($files as $file) {
                echo color("++ $file\n", 'green');

                $version->replaceFile($mod[$file], $file, $app);
                $modFlat = array_diff($modFlat, (array)$file);
                unset($mod[$file]);
            }

            query('COMMIT');
            break;
        }
    } // }}}

    // Deleted {{{
    $input = 'p';
    $delFlat = array_keys($del);

    while ($input != 's' && count($del )) {
        echo "Deleted files were found on remote host:\n";

        foreach ($delFlat as $key => $file)
            echo "\t[$key] $file\n";

        echo "\n\n";

        $input = 'z';
        while (stripos('drs', $input{0}) === false) {
            echo "\tWhat do you want to do about it? \n" .
                "\t(R)estore version in repository\n" .
                "\t(D)elete hash to accept file removal\n" .
                "\t(S)kip\n(e.g. r0 to restore file 0)\n";
            $input = promptUser('>>> ');
        }

        $op = strtolower($input{0});
        $files = getEntries($delFlat, $input);

        switch($op) {
        case 'r':
            $app = $instance->getApplication();
            $access = $instance->getBestAccess('filetransfer');

            foreach ($files as $file) {
                echo ">> $file\n";

                $realFile = $app->getSourceFile($version, $file);
                $access->uploadFile($realFile, $file);

                $hash = md5_file($realFile);
                if ($del[$file] != $hash)
                    $version->replaceFile($hash, $file, $app);

                $delFlat = array_diff($delFlat, (array)$file);
                unset($del[$file]);
            }
            break;

        case 'd':
            $app = $instance->getApplication();
            $access = $instance->getBestAccess( 'filetransfer' );

            foreach ($files as $file) {
                echo color("-- $file\n", 'red');

                $version->removeFile($file);

                $delFlat = array_diff($delFlat, (array)$file);
                unset($del[$file]);
            }
            break;
        }
    } // }}}
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
