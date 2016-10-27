<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";

define( 'ARG_MODE_CLONE', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'clone' );
define( 'ARG_MODE_CLONE_UPDATE', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'update' );
define( 'ARG_MODE_CLONE_UPGRADE', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'upgrade' );
define( 'ARG_MODE_MIRROR', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'mirror' );

if ( ! ARG_MODE_CLONE && ! ARG_MODE_CLONE_UPDATE && ! ARG_MODE_CLONE_UPGRADE && ! ARG_MODE_MIRROR ) {
	echo color("No mode supplied (clone, update, upgrade, or mirror).\n", 'red');
	exit( 1 );
}

exit( 0 );
