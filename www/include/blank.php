<?php

if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
    $type = $user = $host = $pass = '';
    $name = $contact = $webroot = $tempdir = $weburl = '';

    $type = $_POST['type'];
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $webroot = $_POST['webroot'];
    $weburl = $_POST['weburl'];
    $tempdir = $_POST['tempdir'];
    $backup_user = $_POST['backup_user'];
    $backup_group = $_POST['backup_group'];
    $backup_perm = $_POST['backup_perm'];

    if ($type == 'local') {
        if (function_exists('posix_getpwuid')) {
            $user = posix_getpwuid(posix_geteuid())['name'];
        } elseif (!empty($_SERVER['USER'])) {
            $user = $_SERVER['USER'];
        } else {
            $user = '';
        }
        $pass = '';
        $host = 'localhost';
        $port = 0;
    }

    $instance = new Instance;
    $instance->name = $name;
    $instance->contact = $contact;
    $instance->webroot = rtrim($webroot, '/');
    $instance->weburl = rtrim($weburl, '/');
    $instance->tempdir = rtrim($tempdir, '/');
    $instance->backup_user = trim($backup_user);
    $instance->backup_group = trim($backup_group);
    $instance->backup_perm = octdec($backup_perm);
    $access = $instance->registerAccessMethod($type, $host, $user, $pass, $port);
    $instance->detectPHP();
	$instance->save();

    header( "Location: " . url( "list" ) );
	exit;
}

?>

<?php
    $page_title = 'Creating a blank instance';
    require "include/layout/head.php";
    require "include/layout/nav.php";

    $instance = new Instance;
    if (function_exists('posix_getpwuid')) {
        $user = posix_getpwuid(posix_geteuid())['name'];
    } elseif (!empty($_SERVER['USER'])) {
        $user = $_SERVER['USER'];
    } else {
        $user = '';
    }
    $instance->registerAccessMethod('local', 'localhost', $user, '', 0);

    $name = 'localhost';
    $weburl = "http://$name";
    $tempdir = TRIM_TEMP;
    $backup_user = @posix_getpwuid(posix_geteuid())['name'];

    switch ($instance->detectDistribution()) {
    case "ClearOS":
        $backup_group = 'allusers';
        $backup_perm = 02770;
        $host = preg_replace("/[\\\\\/?%*:|\"<>]+/", '-', $name);
        $webroot = ($user == 'root' || $user == 'apache') ?
            "/var/www/virtual/{$host}/html/" : "/home/$user/public_html/";
        break;
    default:
        $backup_group = @posix_getgrgid(posix_getegid())['name'];
        $backup_perm = 02750;
        $webroot = ($user == 'root' || $user == 'apache') ?
            '/var/www/html/' : "/home/$user/public_html/";
    }
?>

<div class="container">
	<div class="trim-instance-new center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<form method="post" action="<?php echo html( $_SERVER['REQUEST_URI'] ) ?>">
			<div class="form-group">
				<table class="table table-bordered">
					<tr>
                        <th>Connection Type</th>
                        <td>
                            <select class="form-control" disabled>
                                <option value="ftp">FTP</option>
                                <option value="local" selected>Local</option>
                                <option value="ssh">SSH</option>
                            </select>
						    <input type="hidden" name="type" class="form-control" value="local"/>
                        </td>
					</tr>
					<tr>
						<th>Instance name</th>
						<td><input type="text" name="name" class="form-control" value="<?php echo $name; ?>"/></td>
					</tr>
					<tr>
						<th>Contact email</th>
						<td><input type="text" name="contact" class="form-control" value=""/></td>
					</tr>
					<tr>
						<th>Web root</th>
						<td><input type="text" name="webroot" class="form-control" value="<?php echo $webroot; ?>"/></td>
					</tr>
					<tr>
						<th>Web URL</th>
						<td><input type="text" name="weburl" class="form-control" value="<?php echo $weburl; ?>"/></td>
					</tr>
					<tr>
						<th>Work directory</th>
						<td><input type="text" name="tempdir" class="form-control" value="<?php echo $tempdir; ?>"/></td>
					</tr>
					<tr>
						<th>Backup owner</th>
						<td><input type="text" name="backup_user" class="form-control" value="<?php echo $backup_user; ?>"/></td>
					</tr>
					<tr>
						<th>Backup group</th>
						<td><input type="text" name="backup_group" class="form-control" value="<?php echo $backup_group; ?>"/></td>
					</tr>
					<tr>
						<th>Backup file permissions</th>
						<td><input type="text" name="backup_perm" class="form-control" value="<?php echo decoct($backup_perm); ?>"/></td>
					</tr>
				</table>
				<p>
					<a href="<?php echo html( url( "" ) ) ?>" class="cancel btn btn-danger"><span class="fa fa-angle-double-left"></span> Cancel</a>
					<button type="submit" class="save btn btn-primary">Save</button>
				</p>
			</div>
		</form>

	</div>
</div>

<?php require "include/layout/footer.php"; ?>
