<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/clean.php";

$instances = Instance::getInstances();

echo "Which instances do you want to install a profile on?\n";

foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( ">>> " );
$selection = getEntries( $instances, $selection );

if( ! $repository = readline( 'Repository: [profiles.tikiwiki.org] ' ) ) {
	$repository = 'profiles.tikiwiki.org';
}

while( ! $profile = trim( readline( 'Profile: ' ) ) );

foreach( $selection as $instance )
{
	info( "Installing profile on {$instance->name}" );
	$instance->getApplication()->installProfile( $repository, $profile );
}

perform_archive_cleanup();

?>
