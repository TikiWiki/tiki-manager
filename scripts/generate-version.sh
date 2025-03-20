#!/bin/sh
# Copyright (c) 2016, Avan.Tech, et. al.
# Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
# All Rights Reserved. See copyright.txt for details and a complete list of authors.
# Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

VERSION_FILE=".version"
FORMAT="${1:-json}"  # Default format is 'json'

# Extract version details
# Extract version details
COMMIT_HASH=$(git log -n 1 --format='%h')
COMMIT_DATE=$(git log -n 1 --format='%cI')

# Always include full commit log for human readability
{
    echo "Tiki Manager Version Information"
    echo "Generated on: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
    git log -n 1 --pretty=fuller
} | sed 's/^/# /' > "$VERSION_FILE"  # Prefix all lines with #

# Append machine-readable variables based on format
if [ "$FORMAT" = "env" ]; then
    {
        echo "TIKI_MANAGER_GIT_DATE=\"$COMMIT_DATE\""
        echo "TIKI_MANAGER_GIT_HASH=\"$COMMIT_HASH\""
    } >> "$VERSION_FILE"
else
    echo "{\"version\":\"$COMMIT_HASH\",\"date\":\"$COMMIT_DATE\"}" >> "$VERSION_FILE"
fi

exit 0
