<?php
    $page_title = 'Restoring an instance';
    require dirname(__FILE__) . "/layout/head.php";
    require dirname(__FILE__) . "/layout/nav.php";
    $instances = TikiManager\Application\Instance::getInstances();

    $blank = false;
foreach ($instances as $instance) {
    if (! $instance->getApplication()) {
        $blank = true;
    }
}
?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-list restore center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <?php if (! $blank) : ?>
            <h3>You need at least one blank instance to be able to restore.</h3>
        <?php elseif (!empty($instances)) : ?>
            <h3>Which instance do you want to restore from?</h3>

            <div id="accordion">
            <?php foreach (TikiManager\Application\Instance::getInstances(true) as $instance) : ?>
                <?php $version = $instance->getLatestVersion() ?>
                <div class="card">
                    <div class="card-header" id="heading-<?php echo html("{$instance->id}") ?>">
                        <a href="" class="collapsed" data-toggle="collapse" data-target="#collapse-<?php echo html("{$instance->id}") ?>" aria-expanded="true" aria-controls="collapse-<?php echo html("{$instance->id}") ?>">
                        <?php echo html($instance->name) ?> <span>&nbsp;<?php echo html("({$version->type}, {$version->branch})") ?></span>
                        </a>
                    </div>

                    <div id="collapse-<?php echo html("{$instance->id}") ?>" class="collapse" aria-labelledby="heading-<?php echo html("{$instance->id}") ?>" data-parent="#accordion">
                        <div class="card-body">
                        <?php if (!empty($instance->getArchives())) : ?>
                            <h3>Which backup do you want to restore?</h3>
                            <ul class="archive">
                                <?php foreach ($instance->getArchives() as $filename) : ?>
                                <li data-id="<?php echo html("{$instance->id}") ?>">
                                    <a href="javascript:void(0);">
                                        <span class="file"><?php echo html($filename) ?></span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <h3>This instance doesnt have backups.</h3>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <h3>Which instance do you want to restore to?</h3>
            <ul class="destination clearfix">
            <?php foreach ($instances as $instance) : ?>
                <?php if (! $instance->getApplication()) : ?>
                    <li title="Restore this instance" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>">
                        <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <h3>Instance list is empty.</h3>
        <?php endif; ?>

        <p class="clearfix">
            <a href="<?php echo html(url('')) ?>" class="back btn btn-secondary">
                <span class="fa fa-angle-double-left"></span>
                Back to list
            </a>
            <button class="restore btn btn-success" data-toggle="modal" data-target="#trimModal" data-id="" data-name="" data-sourceid="" data-backup="" data-type="restore" data-backdrop="static" disabled>
                <span class="fa fa-undo"></span> Restore
            </button>
        </p>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
