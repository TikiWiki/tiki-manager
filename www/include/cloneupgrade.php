<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence   Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;

$page_title = 'Cloning and Upgrade an instance';
require dirname(__FILE__) . "/layout/head.php";
require dirname(__FILE__) . "/layout/nav.php";

$instances = TikiManager\Application\Instance::getInstances(true);
$instance = new Instance;
$instance->type = 'local';
$instance->phpversion = 50500;
$tikiApplication = new Tiki($instance);

$versions = $tikiApplication->getVersions();
?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-list cloneupgrade center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <?php if (!empty($instances)) : ?>
            <h3>Select the source instance</h3>
            <ul class="source clearfix">
                <?php foreach ($instances as $instance) : ?>
                    <li title="cloneupgrade this instance" data-id="<?php echo html("{$instance->id}") ?>"
                        data-name="<?php echo html("{$instance->name}") ?>" data-type="cloneupgrade">
                        <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h3>Select the destination instance</h3>
            <ul class="hide destination clearfix">
                <?php foreach ($instances as $instance) : ?>
                    <li title="cloneupgrade this instance" data-id="<?php echo html("{$instance->id}") ?>"
                        data-name="<?php echo html("{$instance->name}") ?>" data-type="cloneupgrade">
                        <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h3>Select the version to upgrade <i id="loading-icon-branch"
                                                 class="fa fa-circle-o-notch fa-spin fa-fw cyan hide"></i>
            </h3>
            <div>
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
            </div>
            <br>
        <?php else : ?>
            <h3>Instance list is empty.</h3>
        <?php endif; ?>

        <p class="clearfix">
            <?php require dirname(__FILE__) . "/layout/back.php"; ?>
            <button class="cloneupgrade btn btn-success" data-toggle="modal" data-target="#trimModal" data-id="" data-name=""
                    data-sourceid="" data-sourcename="" data-type="cloneupgrade" data-backdrop="static" disabled>
                <span class="fa fa-clone"></span> Clone and Upgrade
            </button>
        </p>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
