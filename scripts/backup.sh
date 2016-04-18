#/bin/bash

echo $1
which cpulimit > /dev/null
RETVAL=$?


if [ $RETVAL -eq 0 ]; then
	echo using cpulimit
	cpulimit -l10 $1 scripts/backup.php
else
	echo dont  use cpulimit
	$1 scripts/backup.php
fi
