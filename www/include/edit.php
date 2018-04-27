<?php

if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
	$instance->name = $_POST['name'];
	$instance->contact = $_POST['contact'];
	$instance->weburl = rtrim( $_POST['weburl'], '/' );
	$instance->webroot = rtrim( $_POST['webroot'], '/' );
	$instance->tempdir = rtrim( $_POST['tempdir'], '/' );
	$instance->phpexec = rtrim( $_POST['phpexec'], '/' );
    $instance->backup_user = trim( $_POST['backup_user'] );
    $instance->backup_group = trim( $_POST['backup_group'] );
    $instance->backup_perm = octdec( $_POST['backup_perm'] );
	$instance->save();

	$locations = explode( "\n", $_POST['backups'] );
	$locations = array_map( 'trim', $locations );
	$instance->setExtraBackups( $locations );

	header( "Location: " . html( url( "view/{$instance->id}" ) ) );
	exit;
}

?>

<?php $page_title = 'Edit instance : ' . html( $instance->name ); ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-edit center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<form method="post" action="<?php echo html( $_SERVER['REQUEST_URI'] ) ?>">
			<div class="form-group">
				<table class="table table-bordered">
					<tr>
						<th>Name</th>
						<td><input type="text" name="name" class="form-control" value="<?php echo html( $instance->name ) ?>"/></td>
					</tr>
					<tr>
						<th>Contact</th>
						<td><input type="text" name="contact" class="form-control" value="<?php echo html( $instance->contact ) ?>"/></td>
					</tr>
					<tr>
						<th>Web URL</th>
						<td><input type="text" name="weburl" class="form-control" value="<?php echo html( $instance->weburl ) ?>"/></td>
					</tr>
					<tr>
						<th>Web Root</th>
						<td><input type="text" name="webroot" class="form-control" value="<?php echo html( $instance->webroot ) ?>"/></td>
					</tr>
					<tr>
						<th>Work Directory</th>
						<td><input type="text" name="tempdir" class="form-control" value="<?php echo html( $instance->tempdir ) ?>"/></td>
					</tr>
					<tr>
						<th>PHP Interpreter</th>
						<td><input type="text" name="phpexec" class="form-control" value="<?php echo html( $instance->phpexec ) ?>"/></td>
					</tr>
					<tr>
						<th>Backup owner</th>
						<td><input type="text" name="backup_user" class="form-control" value="<?php echo html( $instance->getProp('backup_user') ) ?>"/></td>
					</tr>
					<tr>
						<th>Backup group</th>
						<td><input type="text" name="backup_group" class="form-control" value="<?php echo html( $instance->getProp('backup_group') ) ?>"/></td>
					</tr>
					<tr>
						<th>Backup file permissions</th>
						<td><input type="text" name="backup_perm" class="form-control" value="<?php echo html( decoct($instance->getProp('backup_perm')) ) ?>"/></td>
					</tr>
					<tr>
						<th class="top">Additional folders to backup<span>(One per line)</span></th>
						<td><textarea cols="50" rows="6" name="backups" class="form-control"><?php echo html( implode( "\n", $instance->getExtraBackups() ) ) ?></textarea></td>
					</tr>
				</table>
				<p>
					<a href="<?php echo html( url( "view/{$instance->id}" ) ) ?>" class="cancel btn btn-danger"><span class="fa fa-angle-double-left"></span> Cancel</a>
					<button type="submit" class="save btn btn-primary">Save</button>
				</p>
			</div>
		</form>

	</div>
</div>

<?php require "include/layout/footer.php"; ?>
