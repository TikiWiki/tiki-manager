#
# Change paths to executables here
##
PHP = php -d memory_limit=256M
SQLITE = sqlite3
SQLITE_VERSION := $(shell $(SQLITE) --version 2> /dev/null)
BASH = bash

# No changes should be required from here
default:
	@echo "Valid options are:"
	@echo "  instance, viewdb, check, watch, update, upgrade, convert, access,"
	@echo "  backup, restore, fix, detect, enablewww, delete, profile, report,"
	@echo "  copysshkey, blank, clone, mirror, cloneandupdate, cloneandupgrade"

help: default

.PHONY: backup

# Use this to add a remote installation
instance:
	$(PHP) scripts/addinstance.php

blank:
	$(PHP) scripts/addinstance.php blank

viewdb:
ifndef SQLITE_VERSION
	$(error $(SQLITE) is not available, please install and try again)
endif
	$(SQLITE) data/trim.db

check:
	$(PHP) scripts/check.php

verify:
	$(PHP) scripts/check.php

watch:
	$(PHP) scripts/setupwatch.php

# Use this to update version within the same branch, no major versions changes
update:
	$(PHP) scripts/update.php

# Use this to update major releases
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

clone:
	$(PHP) scripts/clone.php clone

mirror:
	$(PHP) scripts/clone.php mirror

cloneandupdate:
	$(PHP) scripts/clone.php update

cloneandupgrade:
	$(PHP) scripts/clone.php upgrade
