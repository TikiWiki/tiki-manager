<?php
if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
	if ( isset( $_POST['instance'] ) && is_array( $_POST['instance'] ) ) {
		foreach ( $_POST['instance'] as $id ) {
			if ( $instance = Instance::getInstance( (int) $id ) ) {
				ob_start();
				shell_exec('rm -f ' . $_POST['filename']);
				ob_end_clean();
            } else {
                die( "Unknown instance." );
            }
		}
	}

	header( 'Location: ' . url( 'manage' ) );
}
?>

<?php $page_title = 'Manage backups'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-list manage center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (!empty(Instance::getInstances())): ?>
			<h3>Select an instance</h3>
			<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
					<?php $version = $instance->getLatestVersion() ?>
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="heading-<?php echo html( "{$instance->id}" ) ?>">
							<a class="panel-title collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse-<?php echo html( "{$instance->id}" ) ?>" aria-expanded="true" aria-controls="collapse-<?php echo html( "{$instance->id}" ) ?>" data-id="<?php echo html( "{$instance->id}" ) ?>">
								<?php echo html( $instance->name ) ?> <span>&nbsp;<?php echo html( "{$instance->app} ({$version->type}, {$version->branch})" ) ?></span>
							</a>
	  					</div>
						<div id="collapse-<?php echo html( "{$instance->id}" ) ?>" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading-<?php echo html( "{$instance->id}" ) ?>">
							<div class="panel-body">
							<?php if (!empty($instance->getArchives())): ?>
								<h3>Archive list</h3>
								<ul class="archive">
									<?php foreach( $instance->getArchives() as $filename ): ?>
									<li>
                    					<a href="javascript:void(0);" class="btn btn-danger" title="Delete this backup" data-toggle="modal" data-target="#deleteBackup" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$filename}" ) ?>">
											<span class="fa fa-times"></span>
											<span class="file"><?php echo html( $filename ) ?></span>
										</a>
									</li>
									<?php endforeach; ?>
								</ul>
							<?php else: ?>
								<h3>This instance doesnt have backups.</h3>
							<?php endif; ?>
							</div>
						</div>
  					</div>
				<?php endif; ?>
			<?php endforeach; ?>
			</div>

		<?php else: ?>
			<h3>Instance list is empty.</h3>
		<?php endif; ?>

		<p class="clearfix">
			<a href="<?php echo html( url( '' ) ) ?>" class="back btn btn-default">
				<span class="fa fa-angle-double-left"></span>
				Back to list
			</a>
		</p>

	</div>
</div>

<?php require "include/layout/modal.php"; ?>
<?php require "include/layout/footer.php"; ?>
