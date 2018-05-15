@echo off
rem Change paths to executables here

set PHP=php -d memory_limit=256M
set SQLITE=sqlite3
setlocal enableextensions 
for /f "tokens=*" %%a in ( 
'%SQLITE% --version' 
) do ( 
set SQLITE_VERSION=%%a 
)

set PARM=%1
shift
if "%PARM%"=="" goto :help
goto :%PARM%

# No changes should be required from here
:default
:help
	echo Valid options are:
	echo   access, backup, blank, clean, check, cli, clone, cloneandupgrade,
	echo   convert, copysshkey, delete, detect, editinstance, enablewww, 
	echo   fix, instance, profile, report, restore, update, upgrade,
	echo   verify, viewdb, watch
	goto :eof

# Use this to add a remote installation
:instance
	%PHP% scripts/addinstance.php %*
	goto :eof
	
:editinstance
	%PHP% scripts/editinstance.php %*
	goto :eof
	
:blank
	%PHP% scripts/addinstance.php blank %*
	goto :eof
	
:viewdb
if "%SQLITE_VERSION%"=="" (
	echo Error: %SQLITE% is not available, please install and try again.
	exit
)
	%SQLITE% data\trim.db
	goto :eof
	
:check
	%PHP% scripts/check.php %*
	goto :eof
	
:verify
	%PHP% scripts/check.php %*
	goto :eof
	
:watch
	%PHP% scripts/setupwatch.php %*
	goto :eof
	
# Use this to update version within the same branch, no major versions changes
:update
	%PHP% scripts/update.php %*
	goto :eof
	
# Use this to update major releases
:upgrade
	%PHP% scripts/update.php switch
	goto :eof
	
:convert
	%PHP% scripts/tiki/convert.php %*
	goto :eof
	
:access
	%PHP% scripts/access.php %*
	goto :eof
	
:backup
	%PHP% scripts/backup.php %*
	goto :eof
	
:restore
	%PHP% scripts/restore.php %*
	goto :eof
	
:fix
	%PHP% scripts/tiki/fixperms.php %*
	goto :eof
	
:cli
	%PHP% scripts/tiki/cli.php %*
	goto :eof
	
:detect
	%PHP% scripts/detect.php %*
	goto :eof
	
:enablewww
	%PHP% scripts/enablewww.php %*
	goto :eof
	
:delete
	%PHP% scripts/delete.php %*
	goto :eof
	
:profile
	%PHP% scripts/tiki/profile.php %*
	goto :eof
	
:report
	%PHP% scripts/tiki/report.php %*
	goto :eof
	
:copysshkey
	%PHP% scripts/copysshkey.php %*
	goto :eof
	
:clone
	%PHP% scripts/clone.php clone %*
	goto :eof
	
:cloneandupgrade
	%PHP% scripts/clone.php upgrade %*
	goto :eof
	
:clean
	@echo WARNING!
	@echo You are about to delete all state, backup, cache, and log files!
	set /p ANSWR=Are you sure? 
	if /i %ANSWR%==yes (
		echo Erasing
		del /s /q cache\*.* backup\*.* logs\*.* data\trim.db
	) else (
	   echo Not erasing.
	)
	goto :eof
	
:clean-files
	@echo WARNING!
	@echo You are about to delete backup, cache, and log files!
	set /p ANSWR=Are you sure? 
	if /i %ANSWR%==yes (
		echo Erasing
		del /s /q cache\*.* backup\*.* logs\*.*
	) else (
	   echo Not erasing.
	)
	goto :eof
	
:shell
	%PHP% -d date.timezone=UTC ./vendor/bin/psysh src/psysh_conf.php
	goto :eof

:eof