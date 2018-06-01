<?php $page_title = 'Watching an instance'; ?>
<?php require dirname(__FILE__) . "/layout/head.php"; ?>
<?php require dirname(__FILE__) . "/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-list center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (!empty(Instance::getInstances())): ?>
			<ul class="clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php if ($instance->getApplication() instanceof Application_Tiki): ?>
					<li title="Watch this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="watch">
						<?php require dirname(__FILE__) . "/layout/url.php"; ?>
						<div class="buttons fa">
							<a href="javascript:void(0);" class="fa-eye" title="Watch this instance"></a>
						</div>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<h3>Instance list is empty.</h3>
		<?php endif; ?>

		<p class="clearfix">
		<?php require dirname(__FILE__) . "/layout/back.php"; ?>
		</p>

	</div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
