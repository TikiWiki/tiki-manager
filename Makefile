default:
	echo Valid options are : instance, viewdb, check, watch, update, access, backup, fix, detect, enablewww

.PHONY: backup

instance:
	php5 -d memory_limit=256M scripts/addinstance.php

viewdb:
	sqlite data/trim.db

check:
	php5 -d memory_limit=256M scripts/check.php

watch:
	php5 -d memory_limit=256M scripts/setupwatch.php

update:
	php5 -d memory_limit=256M scripts/update.php

access:
	php5 -d memory_limit=256M scripts/access.php

backup:
	php5 -d memory_limit=256M scripts/backup.php

fix:
	php5 -d memory_limit=256M scripts/fixperms.php

detect:
	php5 scripts/detect.php

enablewww:
	php5 scripts/enablewww.php
