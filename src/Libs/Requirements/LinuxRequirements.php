<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Requirements;

class LinuxRequirements extends Requirements
{
    const REQUIREMENTS = [
        'PHPSqlite' => [
            'name' => 'PHP SQLite extension',
            'tags' => 'php-pdo'
        ],
        'sqlite' => [
            'name' => 'SQLite package',
            'commands' => [
                'sqlite3'
            ],
            'tags' => 'sqlite3',
            'errorMessage' => 'SQLite command line tool not detected. Please make sure sqlite3 cli is installed. While this does not impact normal operations, will prevent you to be able to see/debug the internal db using "database:view"',
            'required' => false,
        ],
        'ssh' => [
            'name' => 'SSH tools',
            'commands' => [
                'ssh',
                'ssh-keygen'
            ],
            'tags' => 'ssh, ssh-keygen'
        ],
        'svn' => [
            'name'  =>  'SVN package',
            'commands' => [
                'svn',
            ],
            'tags' => 'svn'
        ],
        'fileSync' => [
            'name' => 'File synchronization package',
            'commands' => [
                'rsync',
            ],
            'tags' => 'rsync'
        ],
        'processPrioritizer' => [
            'name' => 'Process Prioritizer package',
            'commands' => [
                'nice',
            ],
            'tags' => 'nice'
        ],
        'scp' => [
            'name' => 'SCP package',
            'commands' => [
                'scp',
            ],
            'tags' => 'scp'
        ],
        'mysql' => [
            'name' => 'MYSQL package',
            'commands' => [
                'mysql',
                'mysqldump'
            ],
            'tags' => 'mysql, mysqldump'
        ],
        'fileCompression' => [
            'name' => 'File compression packages',
            'commands' => [
                'gzip',
                'bzip2',
                'tar',
                'unzip'
            ],
            'tags' => 'gzip, bzip2, tar, unzip'
        ]
    ];

    public function getDependencyPath($command)
    {
        $path = `which $command`;

        if (is_string($path) && ! empty($path)) {
            $path = trim($path);
        }

        return $path;
    }
}
