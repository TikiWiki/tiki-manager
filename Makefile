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
	@echo "  access, backup, blank, clean, check, clone, cloneandupgrade,"
	@echo "  convert, copysshkey, delete, detect, editinstance, enablewww, "
	@echo "  fix, instance, profile, report, restore, update, upgrade,"
	@echo "  verify, viewdb, watch"

help: default

.PHONY: backup

# Use this to add a remote installation
instance:
	@$(PHP) scripts/addinstance.php

editinstance:
	@$(PHP) scripts/editinstance.php

blank:
	@$(PHP) scripts/addinstance.php blank

viewdb:
ifndef SQLITE_VERSION
	@$(error $(SQLITE) is not available, please install and try again)
endif
	@$(SQLITE) data/trim.db

check:
	@$(PHP) scripts/check.php

verify:
	@$(PHP) scripts/check.php

watch:
	@$(PHP) scripts/setupwatch.php

# Use this to update version within the same branch, no major versions changes
update:
	@$(PHP) scripts/update.php

# Use this to update major releases
upgrade:
	@$(PHP) scripts/update.php switch

convert:
	@$(PHP) scripts/tiki/convert.php

access:
	@$(PHP) scripts/access.php

backup:
	@$(BASH) scripts/backup.sh $(PHP)

restore:
	@$(PHP) scripts/restore.php

fix:
	@$(PHP) scripts/tiki/fixperms.php

detect:
	@$(PHP) scripts/detect.php

enablewww:
	@$(PHP) scripts/enablewww.php

delete:
	@$(PHP) scripts/delete.php

profile:
	@$(PHP) scripts/tiki/profile.php

report:
	@$(PHP) scripts/tiki/report.php

copysshkey:
	@$(PHP) scripts/copysshkey.php

clone:
	@$(PHP) scripts/clone.php clone

cloneandupgrade:
	@$(PHP) scripts/clone.php upgrade

clean:
	@echo 'WARNING!'
	@echo "You are about to delete all state, backup, cache, and log files!"
	@unset answer;\
		while [ "$$answer" != "yes" -a "$$answer" != "no" ]; do\
			read -p "Are you sure (yes, no)? " answer;\
		done;\
	if [ "$$answer" = "yes" ]; then\
		rm -rf cache/* backup/* logs/* data/trim.db;\
	fi
