<?php

use TikiManager\Application\Instance;
use TikiManager\Config\Environment as Env;

$notfound = false;

//step 0 - search
//step 1 - instance identification
//step 2 - instance configuration
//step 3 - Import

$step = (int) $_POST['step'] ?? 0;

if ($step == 1) {
    $instance = new Instance;
    $instance->webroot = $_POST['tikiwebroot'];
    if (!file_exists($instance->getWebPath('tiki-setup.php'))) {
        $notfound = true;
        $step = 0;
    }
}

if ($step == 3) {
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
    $access = $instance->getBestAccess();
    $discovery = $instance->getDiscovery();

    if ($type == 'local') {
        $access->host = 'localhost';
        $access->user = $discovery->detectUser();
    }

    $instance->name = $name;
    $instance->contact = $contact;
    $instance->weburl = rtrim($weburl, '/');
    $instance->webroot = rtrim($webroot, '/');
    $instance->tempdir = rtrim($tempdir, '/');
    $instance->backup_user = trim($backup_user);
    $instance->backup_group = trim($backup_group);
    $instance->backup_perm = octdec($backup_perm);
    $instance->save();
    $access->save();
    $instance->detectPHP();
    $instance->findApplication();

    header("Location: " . url("list"));
    exit;
}

$page_title = 'Importing a Tiki instance';
require dirname(__FILE__) . "/layout/head.php";
require dirname(__FILE__) . "/layout/nav.php";

$instance = new Instance;
$instance->type = 'local';
$instance->name = $name = ! empty($_POST['name']) ? $_POST['name'] : 'localhost';

$discovery = $instance->getDiscovery();
$instance->weburl = $weburl = ! empty($_POST['weburl']) ? $_POST['weburl'] : $discovery->detectWeburl();

if ($step > 0) {
    $instance->webroot = $webroot = $_POST['tikiwebroot'];
}

if ($step == 2) {
    $tempdir = $discovery->detectTmp() . DIRECTORY_SEPARATOR . Env::get('INSTANCE_WORKING_TEMP');
    $backupDir = Env::get('BACKUP_FOLDER');
    list($backup_user, $backup_group, $backup_perm) = $discovery->detectBackupPerm($backupDir);
    $backup_perm = octdec($backup_perm);
}

$class = $step === 0 ? '' : 'hide';
$nextStep = min($step + 1, 3);
?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-new center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <div class="container">
            <?php if ($notfound) : ?>
                <div class="alert alert-danger" role="alert">
                    No instances found at <?php echo $_POST['tikiwebroot']; ?>
                </div>
            <?php endif; ?>

            <?php if ($step != 0) : ?>
            <div class="alert alert-info" role="alert">
                Tiki instance found at <?php echo $_POST['tikiwebroot']; ?>
            </div>
            <?php endif; ?>

            <form method="post" action="<?php echo html($_SERVER['REQUEST_URI']) ?>">
                <fieldset>
                    <div class="form-group">
                        <table class="table table-bordered">
                            <tbody>

                            <tr class="<?php echo $class ?>">
                                <th scope="row"><label for="tikiwebroot">Tiki Web root</label></th>
                                <td>
                                    <input type="text" name="tikiwebroot" id="tikiwebroot" class="form-control"
                                           value="<?php echo $webroot; ?>"/>
                                </td>
                            </tr>

                            <?php if ($step != 0) : ?>
                            <tr>
                                <th scope="row"><label for="type">Connection Type</label></th>
                                <td>
                                    <select class="form-control chosen-select" id="type" disabled>
                                        <option value="ftp">FTP</option>
                                        <option value="local" selected>Local</option>
                                        <option value="ssh">SSH</option>
                                    </select>
                                    <input type="hidden" name="type" class="form-control" value="local"/>
                                    <input type="hidden" name="webroot" class="form-control"
                                           value="<?php echo $webroot; ?>"/>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="name">Instance name</label></th>
                                <td><input type="text" name="name" id="name" class="form-control"
                                           value="<?php echo $name; ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="contact">Contact email</label></th>
                                <td><input type="text" name="contact" id="contact" class="form-control" value=""/></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($step > 1) : ?>
                                <tr>
                                    <th scope="row"><label for="weburl">Web URL</label></th>
                                    <td><input type="text" name="weburl" id="weburl" class="form-control"
                                               value="<?php echo $weburl; ?>"/></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="tempdir">Work directory</label></th>
                                    <td><input type="text" name="tempdir" id="tempdir" class="form-control"
                                               value="<?php echo $tempdir; ?>"/></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="backup_user">Backup owner</label></th>
                                    <td><input type="text" name="backup_user" id="backup_user" class="form-control"
                                               value="<?php echo $backup_user; ?>"/></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="backup_group">Backup group</label></th>
                                    <td><input type="text" name="backup_group" id="backup_group" class="form-control"
                                               value="<?php echo $backup_group; ?>"/></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="backup_perm">Backup file permissions</label></th>
                                    <td><input type="text" name="backup_perm" id="backup_perm" class="form-control"
                                               value="<?php echo decoct($backup_perm); ?>"/></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group">
                        <p>
                            <a href="<?php echo html(url("")) ?>" class="cancel btn btn-danger"><span
                                        class="fas fa-angle-double-left"></span> Cancel</a>
                            <input type="hidden" name="step" value="<?= $nextStep ?>">
                            <button type="submit" class="save btn btn-primary"><?= $nextStep < 3 ? 'Next' : 'Import'; ?></button>
                        </p>
                    </div>
                </fieldset>
            </form>
        </div>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
