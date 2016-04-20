# Change paths to executables here
PHP = php -d memory_limit=256M
SQLITE = sqlite3
BASH = bash

# No changes should be required from here
default:
	@echo Valid options are : instance, viewdb, check, watch, update, upgrade, convert, access, backup, restore, fix, detect, enablewww, delete, profile, report, copysshkey

.PHONY: backup

instance:
	$(PHP) scripts/addinstance.php

blank:
	$(PHP) scripts/addinstance.php blank

viewdb:
	$(SQLITE) data/trim.db

check:
	$(PHP) scripts/check.php

watch:
	$(PHP) scripts/setupwatch.php

update:
	$(PHP) scripts/update.php

upgrade:
	$(PHP) scripts/update.php switch

convert:
	$(PHP) scripts/tiki/convert.php

access:
	$(PHP) scripts/access.php

backup:
	$(BASH) scripts/backup.sh $(PHP)

restore:
	$(PHP) scripts/restore.php

fix:
	$(PHP) scripts/tiki/fixperms.php

detect:
	$(PHP) scripts/detect.php

enablewww:
	$(PHP) scripts/enablewww.php

delete:
	$(PHP) scripts/delete.php

profile:
	$(PHP) scripts/tiki/profile.php

report:
	$(PHP) scripts/tiki/report.php

copysshkey:
	$(PHP) scripts/copysshkey.php
