<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../../src/env_setup.php";
include_once dirname(__FILE__) . "/../../src/clean.php";
include_once dirname(__FILE__) . "/../../src/check.php";
include_once dirname(__FILE__) . "/../../src/dbsetup.php";


$instances = Instance::getInstances();

echo "Note: Only instances running Tiki can have profiles applied.\n\n";

$selection = selectInstances( $instances, "Which instances do you want to apply a profile on?\n" );

echo "Working on ".$selection[0]->name."\n";
$app = $selection[0]->getApplication();

ob_start();
perform_instance_installation( $selection[0] );
$contents = $string = trim(preg_replace('/\s\s+/', ' ', ob_get_contents()));
ob_end_clean();
$ms = array();
preg_match('/(\d+\.|trunk)/', $contents, $ms);

foreach( $ms as $key => $version ){
	preg_match('/(\d+\.|trunk)/',$version, $matches);
	if (($matches[0] >= 13) || ($matches[0] == 'trunk')){
		echo "You can not do a profile in a tiki >= 13.x\n";
		exit;
	}
}

if( ! $repository = readline( 'Repository: [profiles.tiki.org] ' ) ) {
	$repository = 'profiles.tiki.org';
}

while( ! $profile = trim( readline( 'Profile: ' ) ) );

foreach( $selection as $instance )
{
	info( "Applying profile on {$instance->name}" );
	$instance->getApplication()->installProfile( $repository, $profile );
}

perform_archive_cleanup();
