<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $instance->name = $_POST['name'];
    $instance->contact = $_POST['contact'];
    $instance->weburl = rtrim($_POST['weburl'], '/');
    $instance->webroot = rtrim($_POST['webroot'], '/');
    $instance->tempdir = rtrim($_POST['tempdir'], '/');
    $instance->phpexec = rtrim($_POST['phpexec'], '/');
    $instance->backup_user = trim($_POST['backup_user']);
    $instance->backup_group = trim($_POST['backup_group']);
    $instance->backup_perm = octdec($_POST['backup_perm']);
    $instance->save();

    $locations = explode("\n", $_POST['backups']);
    $locations = array_map('trim', $locations);
    $instance->setExtraBackups($locations);

    header("Location: " . html(url("view/{$instance->id}")));
    exit;
}

?>

<?php $page_title = 'Edit instance : ' . html($instance->name); ?>
<?php require dirname(__FILE__) . "/layout/head.php"; ?>
<?php require dirname(__FILE__) . "/layout/nav.php"; ?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-edit center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <form method="post" action="<?php echo html($_SERVER['REQUEST_URI']) ?>">
            <fieldset>
                <div class="form-group">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="name">Instance Name</label></th>
                                <td>
                                    <input type="text" name="name" id="name" class="form-control" value="<?php echo html($instance->name) ?>"/>
                                    <input type="hidden" name="phpexec" id="phpexec" class="form-control" value="<?php echo html($instance->phpexec) ?>"/>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="contact">Contact</label></th>
                                <td><input type="text" name="contact" id="contact" class="form-control" value="<?php echo html($instance->contact) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="weburl">Web URL</label></th>
                                <td><input type="text" name="weburl" id="weburl" class="form-control" value="<?php echo html($instance->weburl) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="webroot">Web Root</label></th>
                                <td><input type="text" name="webroot" id="webroot" class="form-control" value="<?php echo html($instance->webroot) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tempdir">Work Directory</label></th>
                                <td><input type="text" name="tempdir" id="tempdir" class="form-control" value="<?php echo html($instance->tempdir) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_user">Backup owner</label></th>
                                <td><input type="text" name="backup_user" id="backup_user" class="form-control" value="<?php echo html($instance->getProp('backup_user')) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_group">Backup group</label></th>
                                <td><input type="text" name="backup_group" id="backup_group" class="form-control" value="<?php echo html($instance->getProp('backup_group')) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_perm">Backup file permissions</label></th>
                                <td><input type="text" name="backup_perm" id="backup_perm" class="form-control" value="<?php echo html(decoct($instance->getProp('backup_perm'))) ?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row" class="top"><label for="backups">Additional folders to backup<span>(One per line)</span></label></th>
                                <td><textarea cols="50" rows="6" name="backups" id="backups" class="form-control"><?php echo html(implode("\n", $instance->getExtraBackups())) ?></textarea></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <p>
                        <a href="<?php echo html(url("view/{$instance->id}")) ?>" class="cancel btn btn-secondary"><span class="fa fa-angle-double-left"></span> Cancel</a>
                        <button type="submit" class="save btn btn-primary">Save</button>
                    </p>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
