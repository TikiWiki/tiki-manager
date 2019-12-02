<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence   Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Application\Tiki;

$page_title = 'Upgrade an instance';
require dirname(__FILE__) . '/layout/head.php';
require dirname(__FILE__) . '/layout/nav.php';
$instances = TikiManager\Application\Instance::getInstances(true);

$instanceSelected = intval($_POST['instance']);
if ($instanceSelected && $instance = TikiManager\Application\Instance::getInstance($instanceSelected)) {
    $tikiApplication = new Tiki($instance);
    $version = $instance->getLatestVersion();
    $branch_name = $version->getBranch();
    $branch_version = $version->getBaseVersion();

    $versions = $tikiApplication->getCompatibleVersions();

    $versionSel = [];
    $versions = [];
    $versions_raw = $tikiApplication->getVersions();


    $found_incompatibilities = false;
    foreach ($versions_raw as $key => $version) {
        $base_version = $version->getBaseVersion();

        $compatible = 0;
        $compatible |= $base_version >= 13;
        $compatible &= $base_version >= $branch_version;
        $compatible |= $base_version === 'trunk';
        $compatible |= $base_version === 'master';
        $found_incompatibilities |= !$compatible;

        if ($compatible) {
            $versions[] = $version;
        }
    }
}
?>
<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-list upgrade center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>
        <form id="form-upgrade" name="formUpgrade" method="post" action="<?php echo html(url('upgrade')) ?>">
            <?php if (!empty($instances)) : ?>
                <h3>Select the source instance</h3>
                <ul class="source clearfix">
                    <?php foreach ($instances as $instance) : ?>
                        <?php
                        if ($instance->type != "local") {
                            continue;
                        }
                        ?>
                        <li title="Upgrade this instance"
                            style="<?= ($instanceSelected == $instance->id) ? 'background-color:palegreen;' : '' ?>"
                            data-id="<?php echo html("{$instance->id}") ?>"
                            data-name="<?php echo html("{$instance->name}") ?>" data-type="upgrade">
                            <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <input name="instance" id="instance" type="hidden" value="<?= $instanceSelected ?>">

                <h3>
                    Select the version to upgrade <i id="loading-icon-branch"
                                                     class="fa fa-circle-o-notch fa-spin fa-fw cyan hide"></i>
                </h3>
                <select class="chosen-select form-control clearfix branch">
                    <option value="">--</option>
                    <?php
                    foreach ($versions as $version) {
                        if (!empty($version->type) && !empty($version->branch)) {
                            ?>
                            <option
                            value="<?php echo $version->branch; ?>"><?php echo $version->type . ' : ' . $version->branch; ?></option><?php
                        }
                    }
                    ?>
                </select>
                <p class="clearfix">

            <?php else : ?>
                <h3>Instance list is empty.</h3>
            <?php endif; ?>

            <p class="clearfix">
                <?php require dirname(__FILE__) . "/layout/back.php"; ?>
                <button class="upgrade btn btn-success" type="button" data-toggle="modal" data-target="#trimModal"
                        data-id="" data-name="" data-sourceid="" data-sourcename="" data-type="upgrade"
                        data-backdrop="static" disabled>
                    <span class="fa fa-upgrade"></span> Upgrade
                </button>
            </p>
        </form>
    </div>
</div>


<?php require dirname(__FILE__) . '/layout/modal.php'; ?>
<?php require dirname(__FILE__) . '/layout/footer.php'; ?>
