<?php

if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
	$instance->name = $_POST['name'];
	$instance->contact = $_POST['contact'];
	$instance->weburl = rtrim( $_POST['weburl'], '/' );
	$instance->webroot = rtrim( $_POST['webroot'], '/' );
	$instance->tempdir = rtrim( $_POST['tempdir'], '/' );
	$instance->phpexec = rtrim( $_POST['phpexec'], '/' );
	$instance->save();

	header( "Location: " . url( "view/{$instance->id}" ) );
	exit;
}

?>
<html>
<head><title>TRIM Web Administration : <?php echo html( $instance->name ) ?></title></head>
<body>
	<h1>Instance : <?php echo html( $instance->name ) ?></h1>
	<form method="post" action="<?php echo html( $_SERVER['REQUEST_URI'] ) ?>">
	<table>
		<tr>
			<th>Name</th>
			<td><input type="text" name="name" value="<?php echo html( $instance->name ) ?>"/></td>
		</tr>
		<tr>
			<th>Contact</th>
			<td><input type="text" name="contact" value="<?php echo html( $instance->contact ) ?>"/></td>
		</tr>
		<tr>
			<th>Web URL</th>
			<td><input type="text" name="weburl" value="<?php echo html( $instance->weburl ) ?>"/></td>
		</tr>
		<tr>
			<th>Web Root</th>
			<td><input type="text" name="webroot" value="<?php echo html( $instance->webroot ) ?>"/></td>
		</tr>
		<tr>
			<th>Work Directory</th>
			<td><input type="text" name="tempdir" value="<?php echo html( $instance->tempdir ) ?>"/></td>
		</tr>
		<tr>
			<th>PHP Interpreter</th>
			<td><input type="text" name="phpexec" value="<?php echo html( $instance->phpexec ) ?>"/></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" value="Save"/></td>
		</tr>
	</table>
	</form>
	<p><a href="<?php echo html( url( "view/{$instance->id}" ) ) ?>">&lt;&lt;&lt; Cancel</a>
</body>
</html>
