<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

function perform_instance_installation(Instance $instance)
{
    if (! $app = $instance->findApplication()) {
        echo "No applications were found on remote host.\n";
        echo "Which one do you want to install? (none to skip - blank instance)\n";

        $apps = Application::getApplications($instance);
        foreach ($apps as $key => $app)
            echo "[$key] {$app->getName()}\n";

        $selection = promptUser('>>> ', '');
        $selection = getEntries($apps, $selection);
        if (empty($selection))
            die(error('No instance to install.'));

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
                }
                else {
                    echo sprintf("[%3d] %s : %s\n",
                        $key, $version->type, $version->branch);
                }
            }
        }
        
        echo "[ -1] blank : none\n";

        $matches = array();
        preg_match('/(\d+)(\d{2})(\d{2})$/', $instance->phpversion, $matches);

        if (count($matches) == 4) {
            info(sprintf('TRIM detected PHP release: %d.%d.%d',
                $matches[1], $matches[2], $matches[3]));
        }

        if ($found_incompatibilities) {
            echo "If some versions are not offered, it's likely because the host " .
                "server doesn't meet the requirements for that version (ex: PHP version is too old)\n";
        }

        $input = promptUser('>>> ', '');
        if ($input > -1) $selection = getEntries($versions, $input);

        if ((empty($selection) && empty($input)) || ($input < 0))
            die(error('No version to install.  This is a blank instance.'));

        if (empty($selection))
            $version = Version::buildFake('svn', $input);
        else
            $version = reset($selection);

        info("Installing application...");
        echo color("If for any reason the installation fails (ex: wrong setup.sh parameters for tiki), " .
           "you can use 'make access' to complete the installation manually.\n", 'yellow');
        $app->install($version);

        if ($app->requiresDatabase())
            perform_database_setup($instance);
    }
}

function perform_database_connectivity_test(Instance $instance, Database $database = null)
{
    info('Testing database connectivity...');

    $command = sprintf('mysql -u %s%s -h %s -e \'SELECT 1\' ' .
        ' 2>> /tmp/trim.output >> /tmp/trim.output; echo $?',
        escapeshellarg($database->user),
        empty($database->pass) ?
            '' : ' -p' . escapeshellarg($database->pass),
        escapeshellarg($database->host));

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
    info(sprintf("Performing database %s...",
        ($remoteBackupFile) ? 'restore' : 'setup'));

    $db = null;
    $adapter = new Database_Adapter_Dummy();

    $access = $instance->getBestAccess('scripting');

    if (! $remoteBackupFile && ! ($access instanceof ShellPrompt))
        die(error('Can not setup database in non-interactive mode.'));

    if ($remoteBackupFile) {

        $remoteFile = "{$instance->webroot}/db/local.php";
        $localFile = $access->downloadFile($remoteFile);

        if (filesize($localFile)) {

            include $localFile;

            $db = new Database($instance, $adapter);
            $db->type = $db_tiki;
            $db->host = $host_tiki;
            $db->user = $user_tiki;
            $db->pass = $pass_tiki;
            $db->dbname = $dbs_tiki;

            if (! perform_database_connectivity_test($instance, $db)) {
                $db = null;
                if (! ($access instanceof ShellPrompt)) {
                    die(error(
                        'Can not prompt for database credentials in ' .
                        'non-interactive mode.'
                    ));
                }
            }
        }
        unlink($localFile);
    }

    if ($db === null) {
        warning(
            'WARNING: Creating databases and users requires ' .
            'root privileges on MySQL.'
        );
        $type = strtolower(
            promptUser(
                'Should a new database and user be created now (both)?',
                'yes', array('yes', 'no')
            )
        );

        do {
            $db = new Database($instance, $adapter);
            $db->host = strtolower(
                promptUser('Database host', 'localhost'));
            $db->user = strtolower(
                promptUser('Database user', 'root'));
            print 'Database password : ';
            $db->pass = getPassword(true); print "\n";
        }
        while (! perform_database_connectivity_test($instance, $db));

        if (strtolower($type{0}) == 'n')
            $db->dbname = promptUser('Database name', 'tiki_db');
        else {
            $adapter = new Database_Adapter_Mysql(
                $db->host, $db->user, $db->pass);
            $db_new = new Database($instance, $adapter);
            $db_new->host = $db->host;

            $prefix = promptUser(
                'Prefix to use for username and database', 'tiki');

            $db_new->createAccess($prefix);
            $db = $db_new;
        }

        $types = $db->getUsableExtensions();

        if (count($types) == 1)
            $db->type = reset($types);
        else {
            echo "Which extension should be used?\n";
            foreach ($types as $key => $name)
                echo "[$key] $name\n";

            $selection = promptUser('>>> ', '0');
            if (array_key_exists( $selection, $types))
                $db->type = $types[$selection];
            else
                $db->type = reset($types);
        }
    }
    
    if ($remoteBackupFile)
        $instance->getApplication()->restoreDatabase($db, $remoteBackupFile);
    else
        $instance->getApplication()->setupDatabase($db);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
