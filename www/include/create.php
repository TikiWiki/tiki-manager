<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;
use TikiManager\Config\Environment;

$page_title = 'Creating a new instance';
require dirname(__FILE__) . '/layout/head.php';
require dirname(__FILE__) . '/layout/nav.php';

$step = (int) $_POST['step'] ?? 0;
$nextStep = min($step + 1, 2);

$type = 'local'; // For now only supports local instances
$name = ! empty($_POST['name']) ? $_POST['name'] : 'localhost';
$contact = ! empty($_POST['contact']) ? $_POST['contact'] : '';
$webroot = ! empty($_POST['webroot']) ? $_POST['webroot'] : '';
$weburl = ! empty($_POST['weburl']) ? $_POST['weburl'] : "http://$name";
$tempdir = ! empty($_POST['tempdir']) ? $_POST['tempdir'] : $_ENV['INSTANCE_WORKING_TEMP'];
$backup_group = ! empty($_POST['backup_group']) ? $_POST['backup_group'] : '';
$backup_perm = ! empty($_POST['backup_perm']) ? octdec($_POST['backup_perm']) : 0770;
$branch  = ! empty($_POST['branch']) ? $_POST['branch'] : '';
$dbHost = ! empty($_POST['db_host']) ? $_POST['db_host'] : '';
$dbUser = ! empty($_POST['db_user']) ? $_POST['db_user'] : '';
$dbPass = ! empty($_POST['db_pass']) ? $_POST['db_pass'] : '';
$dbPrefix = ! empty($_POST['db_prefix']) ? $_POST['db_prefix'] : '';
$dbName= ! empty($_POST['db_name']) ? $_POST['db_name'] : '';
$dbCreated = isset($_POST['db_created']);

$instance = new Instance;
$instance->type = 'local';
$instance->name = $name;
$instance->weburl = $weburl;

$discovery = $instance->getDiscovery();

// Detect instance defaults
if ($step == 1) {
    $webroot = $discovery->detectWebroot();
    $tempdir = $discovery->detectTmp();

    $backupDir = Environment::get('BACKUP_FOLDER');

    // Backup folder is located in TikiManager running instance
    $tmInstance = new Instance();
    $tmInstance->type = 'local';
    list($backup_user, $backup_group, $backup_perm) = $tmInstance->getDiscovery()->detectBackupPerm($backupDir);
    $backup_perm = octdec($backup_perm);
}

$instance->webroot = $webroot;
$instance->phpexec = $discovery->detectPHP();
$instance->phpversion = $discovery->detectPHPVersion();

$tikiApplication = new Tiki($instance);
$versions = $tikiApplication->getCompatibleVersions();

$createInstance = false;
$emailError = false;
$branchError = false;
$prefixError = false;
// Validate and create
if ($step == 2) {
    $errors = [];

    if (! filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not valid';
        $emailError = true;
    }

    if (empty($branch)) {
        $errors[] = 'Instance version is not valid';
        $branchError = true;
    }

    if (empty($errors)) {
        $createInstance = true;
    }
}
?>

<?php if ($createInstance) : ?>
    <script src="vendor/components/jquery/jquery.min.js"></script>
    <script type="text/javascript">

        $(document).ready( function() {
            var modal = $('#createModal').modal({backdrop:'static'});
            modal.find('h4').text('Create instance');
            modal.find('.log').html('<span class="cyan">Creating new instance...</span>');
            var loading = "<i id=\"loading-icon\" class=\"fas fa-circle-notch fa-spin fa-fw cyan\"></i>\n" +
                "<span class=\"sr-only\">Loading...</span>";
            modal.find('.log').append(loading);
            modal.find('.modal-footer button').hide();

            var ansi = {
                '\\[36m': '<span class="cyan">',
                '\\[33m': '<span class="orange">',
                '\\[31m': '<span class="red">',
                '\\[0m': '</span>'
            };

            var replaceable_ascii = {
                27 : ''
            };
            var last_response_len = false;

            $.ajax({
                url: BASE_URL + '/scripts/create.php',
                xhrFields:{
                    onprogress: function (e) {
                        var this_response, response = e.currentTarget.response;
                        if (last_response_len === false) {
                            this_response = response;
                            last_response_len = response.length;
                        } else {
                            this_response = response.substring(last_response_len);
                            last_response_len = response.length;
                        }
                        log = this_response.trim();
                        var parsed_log = '';

                        for (var i = 0; i < log.length; i++) {
                            var char = log[i];

                            if (replaceable_ascii[log.charCodeAt(i)] !== undefined) {
                                char = replaceable_ascii[log.charCodeAt(i)];
                            }

                            parsed_log += char;
                        }

                        for (var key in ansi) {
                            parsed_log = parsed_log.replace(new RegExp(key, 'g'), ansi[key]);
                        }

                        modal.find('#loading-icon').before(parsed_log);
                        $(".log").scrollTop($(".log")[0].scrollHeight);
                    }
                },
                type: 'POST',
                data: {
                    type: 'local',
                    name: '<?=$_POST['name']?>',
                    contact: '<?=$_POST['contact']?>',
                    webroot: '<?=$_POST['webroot']?>',
                    weburl: '<?=$_POST['weburl']?>',
                    tempdir: '<?=$_POST['tempdir']?>',
                    backup_user: '<?=$_POST['backup_user']?>',
                    backup_group: '<?=$_POST['backup_group']?>',
                    backup_perm: '<?=$_POST['backup_perm']?>',
                    branch: '<?=$_POST['branch']?>',
                    dbHost: '<?=$_POST['db_host']?>',
                    dbUser: '<?=$_POST['db_user']?>',
                    dbPass: '<?=$_POST['db_pass']?>',
                    dbPrefix: '<?=$_POST['db_prefix']?>',
                    dbName: '<?=$_POST['db_name']?>',
                    dbCreated: '<?=$dbCreated?>',
                }
            }).done(function (log) {
                modal.find('#loading-icon').hide();
                modal.find('.log').append('\n<span class="cyan">Done!</span>');
                modal.find('.modal-footer button').show();
            }).fail(function (response) {
                modal.find('.log').append('\n<span class="red">Something went wrong while performing this operation!</span>')
                    .append('<span class="red">Status code: ' + response.status + ' (' + response.statusText + ')</span>')
                    .append('<span>' + response.responseText + '</span>');
            });
        });
    </script>
<?php endif; ?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-new center">
        <h1><?=TITLE?></h1>
        <h2><?=$page_title?></h2>
        <?php if (! empty($errors)) : ?>
            <div class="alert alert-danger" role="alert">
                <?php
                foreach ($errors as $error) {
                    echo html($error) . '<br/>';
                }
                ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?=html($_SERVER['REQUEST_URI']) ?>">
            <fieldset>
                <div class="form-group">
                    <table class="table table-bordered">
                        <tbody>
                        <tr>
                            <th scope="row">
                                <label for="type">Connection Type</label>
                            </th>
                            <td width="55%">
                                <select class="form-control chosen-select" id="type" name="type" disabled>
                                    <option value="ftp">FTP</option>
                                    <option value="local" selected>Local</option>
                                    <option value="ssh">SSH</option>
                                </select>
                                <input type="hidden" name="type" class="form-control" value="local"/>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="name">Instance name</label></th>
                            <td width="55%"><input type="text" name="name" id="name" class="form-control" value="<?=$name?>"/></td>
                        </tr>
                        <tr class="<?php echo $emailError ? 'alert-danger': '' ?>">
                            <th scope="row"><label for="contact">Contact email</label></th>
                            <td width="55%"><input type="text" name="contact" id="contact" class="form-control" value="<?=$contact?>"/></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="weburl">Web URL</label></th>
                            <td width="55%"><input type="text" name="weburl" id="weburl" class="form-control" value="<?=$weburl?>"/></td>
                        </tr>

                        <?php if ($step != 0) : ?>
                            <tr>
                                <th scope="row"><label for="webroot">Web root</label></th>
                                <td width="55%"><input type="text" name="webroot" id="webroot" class="form-control" value="<?=$webroot?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tempdir">Work directory</label></th>
                                <td width="55%"><input type="text" name="tempdir" id="tempdir" class="form-control" value="<?=$tempdir?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_user">Backup owner</label></th>
                                <td width="55%"><input type="text" name="backup_user" id="backup_user" class="form-control" value="<?=$backup_user?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_group">Backup group</label></th>
                                <td width="55%"><input type="text" name="backup_group" id="backup_group" class="form-control" value="<?=$backup_group?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="backup_perm">Backup file permissions</label></th>
                                <td width="55%"><input type="text" name="backup_perm" id="backup_perm" class="form-control" value="<?=decoct($backup_perm)?>"/></td>
                            </tr>
                            <tr class="<?php echo $branchError ? 'alert-danger' : ''; ?>">
                                <th scope="row"><label for="branch">Instance version</label></th>
                                <td width="55%">
                                    <select class="form-control chosen-select" id="branch" name="branch">
                                        <option value="">--</option>
                                        <?php
                                        foreach ($versions as $version) {
                                            if (! empty($version->type) && ! empty($version->branch)) {?>
                                                <option <?=($branch == $version->branch)?'selected':''?> value="<?=$version->branch?>"><?=$version->type . ' : ' . $version->branch?></option>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </fieldset>
            <?php if ($step != 0) : ?>
                <div class="new-section-header">
                    <h4>Database settings</h4>
                    <p>It is required a user with administrative privileges in order create users and databases.</p>
                </div>
                <fieldset>
                    <div class="form-group">
                        <div class="db_created">
                            <input type="checkbox" <?=($dbCreated)?'checked':''?> name="db_created" id="db_created" value="true"> Use an existing database
                        </div>
                        <table class="table table-bordered">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="db_host">Database hostname</label></th>
                                <td width="55%"><input type="text" name="db_host" id="db_host" class="form-control" value="<?=$dbHost?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="db_user">Database User <br><small class="db_create">with administrative privileges</small></label></th>
                                <td width="55%"><input type="text" name="db_user" id="db_user" class="form-control" value="<?=$dbUser?>"/></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="db_pass">Database Password</label></th>
                                <td width="55%"><input type="password" name="db_pass" id="db_pass" class="form-control" value="<?=$dbPass?>"/></td>
                            </tr>
                            <tr class="db_exists">
                                <th scope="row"><label for="db_name">Database Name</label></th>
                                <td width="55%"><input type="text" name="db_name" id="db_name" class="form-control" value="<?= $dbName?>"/></td>
                            </tr>
                            <tr class="db_create">
                                <th scope="row"><label for="db_prefix">Database Prefix</label></th>
                                <td width="55%"><input type="text" name="db_prefix" id="db_prefix" class="form-control" value="<?= $dbPrefix ?>"/></td>
                            </tr>
                            <tr class="db_create">
                                <th scope="row"><label for="db_user_preview">Database user</label></th>
                                <td width="55%"><input type="text" readonly="readonly" name="db_user_preview" id="db_user_preview" class="form-control" value="<?= !empty($dbPrefix) ? $dbPrefix . '_user' : '' ?>"/></td>
                            </tr>
                            <tr class="db_create">
                                <th scope="row"><label for="db_name_preview">Database name</label></th>
                                <td width="55%"><input type="text" readonly="readonly" name="db_name_preview" id="db_name_preview" class="form-control" value="<?= !empty($dbPrefix) ? $dbPrefix . '_tiki' : '' ?>"/></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </fieldset>
            <?php endif; ?>
            <div class="form-group">
                <p>
                    <a href="<?=html(url('')) ?>" class="cancel btn btn-secondary"><span class="fas fa-angle-double-left"></span> Cancel</a>
                    <input type="hidden" name="step" value="<?= $nextStep ?>">
                    <button type="submit" class="save btn btn-primary"><?= $nextStep < 2 ? 'Next' : 'Create'; ?>
                    </button>
                </p>
            </div>
        </form>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
