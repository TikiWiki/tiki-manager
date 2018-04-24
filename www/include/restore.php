<?php
$blank = false;
foreach( Instance::getInstances() as $instance ) {
	if (! $instance->getApplication()) $blank = true;
}
?>

<?php $page_title = 'Restoring an instance'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-list restore center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (! $blank): ?>
			<h3>You need at least one blank instance to be able to restore.</h3>
		<?php elseif (!empty(Instance::getInstances())): ?>
			<h3>Which instance do you want to restore from?</h3>
			<div class="panel-group" id="accordion-source" role="tablist" aria-multiselectable="true">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
					<?php $version = $instance->getLatestVersion() ?>
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="heading-<?php echo html( "{$instance->id}" ) ?>">
							<a class="panel-title collapsed" role="button" data-toggle="collapse" data-parent="#accordion-source" href="#collapse-<?php echo html( "{$instance->id}" ) ?>" aria-expanded="true" aria-controls="collapse-<?php echo html( "{$instance->id}" ) ?>" title="Restore this instance" data-id="<?php echo html( "{$instance->id}" ) ?>">
								<?php echo html( $instance->name ) ?> <span>&nbsp;<?php echo html( "{$instance->app} ({$version->type}, {$version->branch})" ) ?></span>
							</a>
	  					</div>
						<div id="collapse-<?php echo html( "{$instance->id}" ) ?>" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading-<?php echo html( "{$instance->id}" ) ?>">
							<div class="panel-body">
							<?php if (!empty($instance->getArchives())): ?>
								<h3>Which backup do you want to restore?</h3>
								<ul class="archive">
									<?php foreach( $instance->getArchives() as $filename ): ?>
									<li>
										<a href="javascript:void(0);" data-id="<?php echo html( "{$instance->id}" ) ?>">
											<span class="file"><?php echo html( $filename ) ?></span>
											<span class="hide right fa fa-check"></span>
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

			<h3>Which instance do you want to restore to?</h3>
			<ul class="destination clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if (! $instance->getApplication()): ?>
					<li>
						<a href="javascript:void(0);" title="Restore this instance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>"><?php echo html( $instance->name ) ?></a>
						<div class="buttons fa">
							<a href="javascript:void(0);" class="hide fa-check" title="Restore this instance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>"></a>
						</div>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<h3>Instance list is empty.</h3>
		<?php endif; ?>

		<p class="clearfix">
			<a href="<?php echo html( url( '' ) ) ?>" class="back btn btn-default">
				<span class="fa fa-angle-double-left"></span>
				Back to list
			</a>
			<button class="restore btn btn-success" data-toggle="modal" data-target="#trimModal" data-id="" data-name="" data-sourceid="" data-backup="" data-type="restore" disabled>
				<span class="fa fa-undo"></span> Restore
			</button>
		</p>

	</div>
</div>

<?php require "include/layout/modal.php"; ?>
<?php require "include/layout/footer.php"; ?>
