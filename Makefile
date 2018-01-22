#
# Change paths to executables here
##
PHP = php -d memory_limit=256M
SQLITE = sqlite3
SQLITE_VERSION := $(shell $(SQLITE) --version 2> /dev/null)
BASH = bash
-include data/trim.cfg


# No changes should be required from here
default:
	@echo "Valid options are:"
	@echo "  access, backup, blank, clean, check, cli, clone, cloneandupgrade,"
	@echo "  convert, copysshkey, delete, detect, editinstance, enablewww, "
	@echo "  fix, instance, profile, report, restore, update, upgrade,"
	@echo "  verify, viewdb, watch"

help: default

.PHONY: backup

# Use this to add a remote installation
instance:
	@$(PHP) scripts/addinstance.php $(ARGS)

editinstance:
	@$(PHP) scripts/editinstance.php $(ARGS)

blank:
	@$(PHP) scripts/addinstance.php blank $(ARGS)

viewdb:
ifndef SQLITE_VERSION
	@$(error $(SQLITE) is not available, please install and try again)
endif
	@$(SQLITE) data/trim.db

check:
	@$(PHP) scripts/check.php $(ARGS)

verify:
	@$(PHP) scripts/check.php $(ARGS)

watch:
	@$(PHP) scripts/setupwatch.php $(ARGS)

# Use this to update version within the same branch, no major versions changes
update:
	@$(PHP) scripts/update.php $(ARGS)

# Use this to update major releases
upgrade:
	@$(PHP) scripts/update.php switch

convert:
	@$(PHP) scripts/tiki/convert.php $(ARGS)

access:
	@$(PHP) scripts/access.php $(ARGS)

backup:
	@$(BASH) scripts/backup.sh "$(PHP)" "$(ARGS)"

restore:
	@$(PHP) scripts/restore.php $(ARGS)

fix:
	@$(PHP) scripts/tiki/fixperms.php $(ARGS)

cli:
	@$(PHP) scripts/tiki/cli.php $(ARGS)

detect:
	@$(PHP) scripts/detect.php $(ARGS)

enablewww:
	@$(PHP) scripts/enablewww.php $(ARGS)

delete:
	@$(PHP) scripts/delete.php $(ARGS)

profile:
	@$(PHP) scripts/tiki/profile.php $(ARGS)

report:
	@$(PHP) scripts/tiki/report.php $(ARGS)

copysshkey:
	@$(PHP) scripts/copysshkey.php $(ARGS)

clone:
	@$(PHP) scripts/clone.php clone $(ARGS)

cloneandupgrade:
	@$(PHP) scripts/clone.php upgrade $(ARGS)

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

clean-files:
	@echo 'WARNING!'
	@echo "You are about to delete backup, cache, and log files!"
	@unset answer;\
		while [ "$$answer" != "yes" -a "$$answer" != "no" ]; do\
			read -p "Are you sure (yes, no)? " answer;\
		done;\
	if [ "$$answer" = "yes" ]; then\
		find backup -type l -exec readlink -f {} \; | xargs rm -f\
		rm -rf cache/* backup/* logs/*;\
	fi

list-make-vars:
	$(foreach v, $(shell sed -En '/^\w+\s*=/s/\s*=.*$$//p' Makefile), \
	  $(info $(v) = $($(v))))
	@echo
