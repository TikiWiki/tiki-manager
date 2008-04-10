default:
	echo Valid options are : instance, viewdb, check, update

instance:
	php5 -d memory_limit=256M scripts/addinstance.php

viewdb:
	sqlite data/trim.db

check:
	php5 -d memory_limit=256M scripts/check.php

update:
	php5 -d memory_limit=256M scripts/update.php

detect:
	php5 scripts/detect.php
