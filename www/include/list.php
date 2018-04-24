<?php $page_title = 'Instance List'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-main-list center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (!empty(Instance::getInstances())): ?>
			<ul class="clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php
					$version = $instance->getLatestVersion();
					$lock = (md5_file(TRIMPATH . '/scripts/maintenance.htaccess') == md5_file($instance->getWebPath('.htaccess')));
					$blank = (! $instance->getApplication())
				?>
				<li>
					<a href="<?php echo html( url( "view/{$instance->id}" ) ) ?>" <?php if ($blank) { echo "class='nolock'"; } ?>>
						<?php echo html( $instance->name ) ?>&nbsp;
						<span><?php
							if ($blank) { echo "(blank)"; }
							else { echo html( "{$instance->app} ({$version->type}, {$version->branch})" ); }
						?></span>
					</a>

					<?php if (! $blank): ?>
					<div class="lock">
						<?php if ($lock): ?>
						<a href="javascript:void(0);" title="This instance is locked. Click to unlock." data-id="<?php echo html( "{$instance->id}" ) ?>"><span class="fa fa-lock"></span></a>
						<?php else: ?>
						<a href="javascript:void(0);" title="This instance is unlocked. Click to lock." data-id="<?php echo html( "{$instance->id}" ) ?>"><span class="fa fa-unlock"></span></a>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<div class="contact">
						<span class="left">contact: <a href="mailto:<?php echo html( "{$instance->contact}" ) ?>"><?php echo html( "{$instance->contact}" ) ?></a></span>
						<?php if (! $blank): ?><span class="right">url: <a href="<?php echo html( "{$instance->weburl}" ) ?>" target="_blank"><?php echo html( "{$instance->weburl}" ) ?></a></span><?php endif; ?>
					</div>

					<div class="buttons fa">
<!--
							<a href="#" class="fa-check" title="Check this instance"></a>
							<a href="#" class="fa-arrow-up" title="Upgrade this instance"></a>
-->
					<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
						<a href="" class="fa-eye" title="Watch this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="watch"></a>
						<a href="" class="fa-refresh" title="Update this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="update"></a>
						<a href="" class="fa-floppy-o" title="Backup this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="backup"></a>
						<a href="" class="fa-wrench" title="Fix this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="fix"></a>
					<?php endif; ?>
						<a href="<?php echo html( url( "edit/{$instance->id}" ) ) ?>" class="fa-pencil" title="Edit this instance"></a>
						<a href="" class="fa-times" title="Delete this instance" data-toggle="modal" data-target="#deleteInstance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>"></a>
					</div>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<h3>Instance list is empty.</h3>
		<?php endif; ?>

		<p class="clearfix">
			<a href="#" class="new btn btn-primary" disabled>Create a new instance</a>
			<a href="<?php echo html( url( 'blank' ) ) ?>" class="blank btn btn-primary">Create a blank instance</a>
			<a href="<?php echo html( url( 'import' ) ) ?>" class="new btn btn-primary" disabled>Import a tiki instance</a>
		</p>
	</div>
</div>

<?php require "include/layout/modal.php"; ?>
<?php require "include/layout/footer.php"; ?>
