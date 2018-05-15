<?php
if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
	if ( isset( $_POST['instance'] ) && is_array( $_POST['instance'] ) ) {
		foreach ( $_POST['instance'] as $id ) {
			if ( $instance = Instance::getInstance( (int) $id ) ) {
				ob_start();
				$instance->delete();
				ob_end_clean();
            } else {
                die( "Unknown instance." );
            }
		}
	}

	header( 'Location: ' . url( '' ) );
}
?>

<?php $page_title = 'Delete an instance'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-list center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<?php if (!empty(Instance::getInstances())): ?>
			<ul class="clearfix">
			<?php foreach( Instance::getInstances() as $instance ): ?>
				<?php $version = $instance->getLatestVersion() ?>
				<li title="Delete this instance" data-toggle="modal" data-target="#deleteInstance" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>">
					<?php require "include/layout/url.php"; ?>
					<div class="buttons fa">
						<a href="javascript:void(0);" class="fa-times" title="Delete this instance"></a>
					</div>
				</li>
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
