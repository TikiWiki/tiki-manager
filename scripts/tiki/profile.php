<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

use TikiManager\Application\Instance;
use TikiManager\Helpers\Archive;

include_once dirname(__FILE__) . '/../../src/env_setup.php';

$instances = Instance::getInstances();

info("Note: Only Tiki instances can have profiles applied.\n");

$selection = selectInstances($instances, "Which instances do you want to apply a profile on?\n");

$repository = promptUser('Repository', 'profiles.tiki.org');

$profile = promptUser('Profile');

foreach ($selection as $instance) {
    info("Applying profile on {$instance->name}");

    $instance->getApplication()->installProfile($repository, $profile);
    Archive::performArchiveCleanup($instance->id, $instance->name);
}
// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
