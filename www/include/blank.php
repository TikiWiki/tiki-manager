<?php

use TikiManager\Access\Access;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $name = $contact = $webroot = $weburl = $tempdir = '';

    $type = $_POST['type'];
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $webroot = $_POST['webroot'];
    $weburl = $_POST['weburl'];
    $tempdir = $_POST['tempdir'];
    $backup_user = $_POST['backup_user'];
    $backup_group = $_POST['backup_group'];
    $backup_perm = $_POST['backup_perm'];

    $instance = new Instance;
    $instance->type = $type;
    $access = Access::getClassFor($instance->type);
    $access = new $access($instance);
    $discovery = new Discovery($instance, $access);

    if ($type == 'local') {
        $access->host = 'localhost';
        $access->user = $discovery->detectUser();
    }

    $instance->name = $name;
    $instance->contact = $contact;
    $instance->webroot = rtrim($webroot, '/');
    $instance->weburl = rtrim($weburl, '/');
    $instance->tempdir = rtrim($tempdir, '/');
    $instance->backup_user = trim($backup_user);
    $instance->backup_group = trim($backup_group);
    $instance->backup_perm = octdec($backup_perm);
    $instance->save();
    $access->save();
    $instance->detectPHP();

    header("Location: " . url("list"));
    exit;
}

?>

<?php
    $page_title = 'Creating a blank instance';
    require dirname(__FILE__) . "/layout/head.php";
    require dirname(__FILE__) . "/layout/nav.php";

    $instance = new Instance;
    $instance->type = 'local';
    $access = Access::getClassFor($instance->type);
    $access = new $access($instance);
    $discovery = new Discovery($instance, $access);

    $name = 'localhost';
    $weburl = "http://$name";
    $tempdir = $_ENV['TRIM_TEMP'];

    $access->host = 'localhost';
    $access->user = $discovery->detectUser();
    $backup_user = $access->user;
    $backup_perm = 0770;

switch ($discovery->detectDistro()) {
    case "ClearOS":
        $backup_group = 'apache';
        $webroot = ($access->user == 'root' || $access->user == 'apache') ?
            "/var/www/virtual/{$access->host}/html/" : "/home/{$access->user}/public_html/";
        break;
    case "Windows":
        $backup_user = $backup_group = "Administrator";
        $webroot = 'C:\\www\\';
        break;
    default:
        $backup_group = @posix_getgrgid(posix_getegid())['name'];
        $webroot = ($access->user == 'root' || $access->user == 'apache') ?
            '/var/www/html/' : "/home/{$access->user}/public_html/";
}
?>

<div class="container">
    <div class="trim-instance-new center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <form method="post" action="<?php echo html($_SERVER['REQUEST_URI']) ?>">
            <fieldset>
                <div class="form-group">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="type">Connection Type</label></th>
                                <td>
                                    <select class="form-control" id="type" disabled>
                                        <option value="ftp">FTP</option>
                                        <option value="local" selected>Local</option>
                                        <option value="ssh">SSH</option>
                                    </select>
                                    <input type="hidden" name="type" class="form-control" value="local"/>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="name">Instance name</label></th>
                                <td><input type="text" name="name" id="name" class="form-control" value="<?php echo $name; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="contact">Contact email</label></th>
                                <td><input type="text" name="contact" id="contact" class="form-control" value=""/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="webroot">Web root</label></th>
                                <td><input type="text" name="webroot" id="webroot" class="form-control" value="<?php echo $webroot; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="weburl">Web URL</label></th>
                                <td><input type="text" name="weburl" id="weburl" class="form-control" value="<?php echo $weburl; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tempdir">Work directory</label></th>
                                <td><input type="text" name="tempdir" id="tempdir" class="form-control" value="<?php echo $tempdir; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_user">Backup owner</label></th>
                                <td><input type="text" name="backup_user" id="backup_user" class="form-control" value="<?php echo $backup_user; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_group">Backup group</label></th>
                                <td><input type="text" name="backup_group" id="backup_group" class="form-control" value="<?php echo $backup_group; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_perm">Backup file permissions</label></th>
                                <td><input type="text" name="backup_perm" id="backup_perm" class="form-control" value="<?php echo decoct($backup_perm); ?>"/></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <p>
                        <a href="<?php echo html(url("")) ?>" class="cancel btn btn-secondary"><span class="fa fa-angle-double-left"></span> Cancel</a>
                        <button type="submit" class="save btn btn-primary">Save</button>
                    </p>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
