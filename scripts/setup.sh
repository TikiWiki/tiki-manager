#!/bin/sh
# Copyright (c) 2016, Avan.Tech, et. al.
# Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
# All Rights Reserved. See copyright.txt for details and a complete list of authors.
# Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

# This file is a replacement for setup.sh in test in 1.9 version

DIRS=(
    "backups"
    "db"
    "dump"
    "files"
    "img/trackers"
    "img/wiki_up"
    "img/wiki"
    "maps"
    "mods"
    "modules/cache"
    "styles"
    "temp"
    "temp/cache"
    "templates_c"
    "templates"
    "tiki_tests/tests"
    "whelp"
)

if [ -d 'lib/Galaxia' ]; then
    DIRS=$DIRS" lib/Galaxia/processes"
fi

AUSER=$(grep -Eo -m1 '^(apache|www-data|httpd?)' /etc/passwd || echo 'nobody')
AGROUP=$(grep -Eo -m1 '^(apache|www-data|httpd?)' /etc/group || echo 'nobody')
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

echo "Checking dirs : "
for dir in $DIRS; do
    echo -n "  $dir ... "
    if [ ! -d $dir ]; then
        mkdir -vp $dir
    fi
done

echo -n "Changing `pwd` owner to ${AUSER}:${AGROUP} ..."
chown -R $AUSER:$AGROUP .
echo " chowned ..."

#   find . ! -regex '.*^\(devtools\).*' -type f -exec chmod 644 {} \;   
echo -n "Fixing permissions ..."
find . \( -type d -or -name "*.sh" \) -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
echo " ... done"

echo -n "Fix special dirs ..."
if [ "$USER" = 'root' ]; then
    chmod -R g+w "${DIRS[@]}"
    ls -ld "${DIRS[@]}"
else
    chmod -R go+w "${DIRS[@]}"
    ls -ld "${DIRS[@]}"
fi

echo " done."

exit 0

# vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
