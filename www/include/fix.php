<?php $page_title = 'Fixing an instance'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-list center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (!empty(Instance::getInstances())): ?>
			<ul class="clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
					<?php $version = $instance->getLatestVersion() ?>
					<li>
						<a href="" title="Fix this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="fix">
							<?php echo html( $instance->name ) ?> <span>&nbsp;<?php echo html( "{$instance->app} ({$version->type}, {$version->branch})" ) ?></span>
						</a>
						<div class="buttons fa">
							<a href="" class="fa-wrench" title="Fix this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="fix"></a>
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
		</p>

	</div>
</div>

<?php require "include/layout/modal.php"; ?>
<?php require "include/layout/footer.php"; ?>
