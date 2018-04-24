<header>
	<nav class="navbar navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="<?php echo html( url( '' ) ) ?>">TRIM Web Admin</a>
			</div>

<?php if( isset( $_SESSION['active'] ) ) { ?>
			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Instances <span class="caret"></span></a>
						<ul class="dropdown-menu">
							<li><a href="<?php echo html( url( 'list' ) ) ?>">List instances</a></li>
<!--							<li><a href="#">Create a new instance</a></li>-->
							<li><a href="<?php echo html( url( 'blank' ) ) ?>">Create a blank instance</a></li>
<!--							<li><a href="<?php echo html( url( 'import' ) ) ?>">Import a tiki instance</a></li>-->
							<li><a href="<?php echo html( url( 'delete' ) ) ?>">Delete an instance</a></li>
						</ul>
					</li>
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Manage <span class="caret"></span></a>
						<ul class="dropdown-menu">
<!--							<li><a href="#">Check an instance</a></li>-->
							<li><a href="<?php echo html( url( 'update' ) ) ?>">Update an instance</a></li>
<!--							<li><a href="#">Upgrade an instance</a></li>-->
							<li><a href="<?php echo html( url( 'fix' ) ) ?>">Fix an instance</a></li>
							<li><a href="<?php echo html( url( 'watch' ) ) ?>">Watch an instance</a></li>
						</ul>
					</li>
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Backups <span class="caret"></span></a>
						<ul class="dropdown-menu">
							<li><a href="<?php echo html( url( 'backup' ) ) ?>">Backup an instance</a></li>
							<li><a href="<?php echo html( url( 'restore' ) ) ?>">Restore an instance</a></li>
							<li><a href="<?php echo html( url( 'clone' ) ) ?>">Clone an instance</a></li>
<!--							<li><a href="#">Clone and upgrade</a></li>-->
							<li><a href="<?php echo html( url( 'manage' ) ) ?>">Manage backups</a></li>
						</ul>
					</li>
<!--
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Misc <span class="caret"></span></a>
						<ul class="dropdown-menu">
							<li><a href="#">Access</a></li>
							<li><a href="#">Clean</a></li>
							<li><a href="#">Convert</a></li>
							<li><a href="#">CopySSHKey</a></li>
							<li><a href="#">Detect</a></li>
							<li><a href="#">Profile</a></li>
							<li><a href="#">Report</a></li>
							<li><a href="#">ViewDB</a></li>
						</ul>
					</li>
-->
				</ul>
				<ul class="nav navbar-nav">
					<li><a href="<?php echo html( url( 'logout' ) ) ?>">Log out</a></li>
				</ul>
			</div>
<?php } ?>
		</div>
	</nav>
</header>
