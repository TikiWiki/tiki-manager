#/bin/bash
# Copyright (c) 2016, Avan.Tech, et. al.
# Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
# All Rights Reserved. See copyright.txt for details and a complete list of authors.
# Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

which cpulimit > /dev/null 2>&1
RETVAL=$?

if [ $RETVAL -eq 0 ]; then
	echo "Detected cpulimit, throttling backup to: ${LIMIT}%"
    echo
	cpulimit -l${LIMIT} $1 scripts/backup.php
else
	$@ scripts/backup.php
fi

# vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
