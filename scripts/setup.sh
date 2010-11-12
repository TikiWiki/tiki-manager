#!/bin/sh
# $Header: /cvsroot/tikiwiki/tiki/fixperms.sh,v 1.9.2.2 2008-02-07 21:59:14 lphuberdeau Exp $

# Copyright (c) 2002-2007, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
# All Rights Reserved. See copyright.txt for details and a complete list of authors.
# Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

# This file is a replacement for setup.sh
# in test in 1.9 version

DIRS="backups db dump img/wiki img/wiki_up img/trackers modules/cache temp temp/cache templates_c templates styles maps whelp mods files tiki_tests/tests"

if [ -d 'lib/Galaxia' ]; then
	DIRS=$DIRS" lib/Galaxia/processes"
fi

AUSER=nobody
AGROUP=nobody
VIRTUALS=""
USER=`whoami`

if [ -f /etc/debian_version ]; then
	AUSER=www-data
	AGROUP=www-data
elif [ -f /etc/redhat-release ]; then
	AUSER=apache
	AGROUP=apache
elif [ -f /etc/gentoo-release ]; then
	AUSER=apache
	AGROUP=apache
else
	UNAME=`uname | cut -c 1-6`
	if [ "$UNAME" = "CYGWIN" ]; then
		AUSER=SYSTEM
		AGROUP=SYSTEM
	fi
fi

COMMAND=fix

if [ "$COMMAND" = 'fix' ]; then
	AUSER=$USER
	AGROUP=$REPLY

	echo "Checking dirs : "
	for dir in $DIRS; do
		echo -n "  $dir ... "
		if [ ! -d $dir ]; then
			echo -n " Creating directory"
			mkdir -p $dir
		fi
	done

	echo -n "Fix global perms ..."
	chown -R $AUSER:$AGROUP .
	echo -n " chowned ..."

#	find . ! -regex '.*^\(devtools\).*' -type f -exec chmod 644 {} \;	
#	echo -n " files perms fixed ..."
#	find . -type d -exec chmod 755 {} \;
#	echo " dirs perms fixed ... done"

	chmod -R u=rwX,go=rX .

	echo " done."

	echo -n "Fix special dirs ..."
	if [ "$USER" = 'root' ]; then
		chmod -R g+w $DIRS
	else
		chmod -R go+w $DIRS
	fi

#	chmod 664 robots.txt tiki-install.php
	echo " done."

fi

exit 0

