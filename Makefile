# Change paths to executables here
PHP = php5 -d memory_limit=256M
SQLITE = sqlite

# No changes should be required from here
default:
	@echo Valid options are : instance, viewdb, check, watch, update, upgrade, convert, access, backup, restore, fix, detect, enablewww, delete, profile

.PHONY: backup

instance:
	$(PHP) scripts/addinstance.php

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
	$(PHP) scripts/convert.php

access:
	$(PHP) scripts/access.php

backup:
	$(PHP) scripts/backup.php

restore:
	$(PHP) scripts/restore.php

fix:
	$(PHP) scripts/fixperms.php

detect:
	$(PHP) scripts/detect.php

enablewww:
	$(PHP) scripts/enablewww.php

delete:
	$(PHP) scripts/delete.php

profile:
	$(PHP) scripts/profile.php

report:
	$(PHP) scripts/report.php

