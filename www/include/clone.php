<?php $page_title = 'Cloning an instance'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-list clone center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (!empty(Instance::getInstances())): ?>
			<h3>Select the source instance</h3>
			<ul class="source clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
					<?php $version = $instance->getLatestVersion() ?>
					<li title="Clone this instance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="clone">
						<?php require "include/layout/url.php"; ?>
						<div class="buttons fa">
							<a href="javascript:void(0);" class="hide fa-check" title="Clone this instance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>"></a>
						</div>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			</ul>

			<h3>Select the destination instance</h3>
			<ul class="hide destination clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
					<?php $version = $instance->getLatestVersion() ?>
					<li title="Clone this instance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="clone">
						<?php require "include/layout/url.php"; ?>
						<div class="buttons fa">
							<a href="javascript:void(0);" class="hide fa-check" title="Clone this instance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>"></a>
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
			<button class="clone btn btn-success" data-toggle="modal" data-target="#trimModal" data-id="" data-name="" data-sourceid="" data-sourcename="" data-type="clone" disabled>
				<span class="fa fa-clone"></span> Clone
			</button>
		</p>

	</div>
</div>

<?php require "include/layout/modal.php"; ?>
<?php require "include/layout/footer.php"; ?>
