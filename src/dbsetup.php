<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

function perform_instance_installation(Instance $instance)
{
    if (! $app = $instance->findApplication()) {
        $apps = Application::getApplications($instance);
        $selection = getEntries($apps, 0);

        echo "Which version do you want to install? (none to skip - blank instance)\n";

        $app = reset($selection);
        $found_incompatibilities = false;

        $versions = $app->getVersions();
        foreach ($versions as $key => $version) {
            preg_match('/(\d+\.|trunk)/', $version->branch, $matches);
            if (array_key_exists(0, $matches)) {
                // TODO: This is Tiki-specific, not applicable to other apps (ex: wordpress).
                // Logic should be moved in to app-specific class.
                if ((($matches[0] >= 13) || ($matches[0] == 'trunk')) &&
                    ($instance->phpversion < 50500)) {
                    // Nothing to do, this match is incompatible...
                    $found_incompatibilities = true;
                } else {
                    echo sprintf(
                        "[%3d] %s : %s\n",
                        $key,
                        $version->type,
                        $version->branch
                    );
                }
            }
        }

        echo "[ -1] blank : none\n";

        $matches = [];
        preg_match('/(\d+)(\d{2})(\d{2})$/', $instance->phpversion, $matches);

        if (count($matches) == 4) {
            info(sprintf(
                'TRIM detected PHP release: %d.%d.%d',
                $matches[1],
                $matches[2],
                $matches[3]
            ));
        }

        if ($found_incompatibilities) {
            echo "If some versions are not offered, it's likely because the host " .
                "server doesn't meet the requirements for that version (ex: PHP version is too old)\n";
        }

        $input = promptUser('>>> ', '');
        if ($input > -1) {
            $selection = getEntries($versions, $input);
        }

        if ((empty($selection) && empty($input)) || ($input < 0)) {
            die(error('No version to install.  This is a blank instance.'));
        }

        if (empty($selection)) {
            $version = Version::buildFake('svn', $input);
        } else {
            $version = reset($selection);
        }

        info("Installing application...");
        echo color("If for any reason the installation fails (ex: wrong setup.sh parameters for tiki), " .
           "you can use 'make access' to complete the installation manually.\n", 'yellow');
        $app->install($version);

        if ($app->requiresDatabase()) {
            perform_database_setup($instance);
        }
    }
}

function perform_database_connectivity_test(Instance $instance, Database $database = null)
{
    info('Testing database connectivity...');

    $command = sprintf(
        'mysql -u %s%s -h %s -e \'SELECT 1\' ' .
        ' 2>> /tmp/trim.output >> /tmp/trim.output; echo $?',
        escapeshellarg($database->user),
        empty($database->pass) ?
            '' : ' -p' . escapeshellarg($database->pass),
        escapeshellarg($database->host)
    );

    $access = $instance->getBestAccess('scripting');
    $output = $access->shellExec($command);

    trim_output("REMOTE $output");

    if ($output) {
        warning(
            'WARNING: Unable to authenticate using the provided ' .
            'database credentials.'
        );

        return false;
    }

    return true;
}

function perform_database_setup(Instance $instance, $remoteBackupFile = null)
{
    info(sprintf(
        "Performing database %s...",
        ($remoteBackupFile) ? 'restore' : 'setup'
    ));

    $dbUser = null;
    $access = $instance->getBestAccess('scripting');

    if (! $remoteBackupFile && ! ($access instanceof ShellPrompt)) {
        die(error('Can not setup database in non-interactive mode.'));
    }

    if ($remoteBackupFile) {
        $remoteFile = "{$instance->webroot}/db/local.php";
        $localFile = $access->downloadFile($remoteFile);
        $dbUser = Database::createFromConfig($instance, $localFile);
        unlink($localFile);
    }

    if ($dbUser === null) {
        warning(
            'WARNING: Creating databases and users requires ' .
            'root privileges on MySQL.'
        );

        $dbRoot = new Database($instance);
        $valid = false;
        while (!$valid) {
            $dbRoot->host = strtolower(promptUser('Database host', $dbRoot->host ?: 'localhost'));
            $dbRoot->user = strtolower(promptUser('Database user', $dbRoot->user ?: 'root'));

            print 'Database password: ';
            $dbRoot->pass = getPassword(true);
            print "\n";
            $valid = $dbRoot->testConnection();
        }
        debug('Connected to MySQL with adminstrative privileges');

        $type = strtolower(promptUser(
            'Should a new database and user be created now (both)? ',
            'y',
            ['y', 'n']
        ));

        if (strtolower($type{0}) == 'n') {
            $dbUser = $dbRoot;
            $dbUser->dbname = promptUser('Database name', 'tiki_db');
        } else {
            $maxPrefixLength = $dbRoot->getMaxUsernameLength($instance) - 5;
            warning("Prefix is a string with maximum of {$maxPrefixLength} chars");

            $prefix = 'tiki';
            while (!is_object($dbUser)) {
                $prefix = promptUser('Prefix to use for username and database', $prefix);

                if (strlen($prefix) > $maxPrefixLength) {
                    error("Prefix is a string with maximum of {$maxPrefixLength} chars");
                    $prefix = substr($prefix, 0, $maxPrefixLength);
                    continue;
                }

                $username = "{$prefix}_user";
                if ($dbRoot->userExists($username)) {
                    error("User '$username' already exists, can't proceed.");
                    continue;
                }

                $dbname = "{$prefix}_db";
                if ($dbRoot->databaseExists($dbname)) {
                    warning("Database '$dbname' already exists");
                    if (promptUser('Continue?', 'y', ['y', 'n']) === 'n') {
                        continue;
                    }
                }

                try {
                    $dbUser = $dbRoot->createAccess($username, $dbname);
                } catch (DatabaseError $e) {
                    error("Can't setup database!");
                    error($e->getMessage());

                    if (promptUser('(a)abort, (r)retry:', 'a', ['a', 'r']) === 'a') {
                        error('Aborting');
                        return;
                    }
                }
            }
        }

        $types = $dbUser->getUsableExtensions();
        $type = getenv('MYSQL_DRIVER');
        $dbUser->type = $type;

        if (count($types) == 1) {
            $dbUser->type = reset($types);
        } elseif (empty($type)) {
            echo "Which extension should be used?\n";
            foreach ($types as $key => $name) {
                echo "[$key] $name\n";
            }

            $selection = promptUser('>>> ', '0');
            if (array_key_exists($selection, $types)) {
                $dbUser->type = $types[$selection];
            } else {
                $dbUser->type = reset($types);
            }
        }
    }

    if ($remoteBackupFile) {
        $instance->getApplication()->restoreDatabase($dbUser, $remoteBackupFile);
    } else {
        $instance->getApplication()->setupDatabase($dbUser);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
