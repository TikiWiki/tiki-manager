<header>
    <nav class="navbar navbar-expand-lg fixed-top navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo html(url('')) ?>">Tiki Manager Web Admin</a>
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="navbar" class="collapse navbar-collapse">
<?php if (isset($_SESSION['active'])) { ?>
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Instances</a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="<?php echo html(url('list')) ?>">List instances</a>
                            <a class="dropdown-item" href="<?php echo html(url('create')) ?>">Create a new instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('blank')) ?>">Create a blank instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('import')) ?>">Import a tiki instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('delete')) ?>">Delete an instance</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Manage</a>
                        <div class="dropdown-menu">
<!--                            <a class="dropdown-item" href="#">Check an instance</a>-->
                            <a class="dropdown-item" href="<?php echo html(url('update')) ?>">Update an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('upgrade')) ?>">Upgrade an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('fix')) ?>">Fix an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('watch')) ?>">Watch an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('clone')) ?>">Clone an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('cloneupgrade')) ?>">Clone and Upgrade an instance</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Backups</a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="<?php echo html(url('backup')) ?>">Backup an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('restore')) ?>">Restore an instance</a>
                            <a class="dropdown-item" href="<?php echo html(url('manage')) ?>">Manage backups</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Help</a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="<?php echo html(url('requirements')) ?>">Check requirements</a>
                        </div>
                    </li>
<!--
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Misc</a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#">Access</a>
                            <a class="dropdown-item" href="#">Clean</a>
                            <a class="dropdown-item" href="#">Convert</a>
                            <a class="dropdown-item" href="#">CopySSHKey</a>
                            <a class="dropdown-item" href="#">Detect</a>
                            <a class="dropdown-item" href="#">Profile</a>
                            <a class="dropdown-item" href="#">Report</a>
                            <a class="dropdown-item" href="#">ViewDB</a>
                        </div>
                    </li>
-->
                </ul>
                <div class="navbar-nav">
                    <a class="nav-link" href="<?php echo html(url('logout')) ?>">Log out</a>
                </div>
<?php } ?>
            </div>
        </div>
    </nav>
</header>
