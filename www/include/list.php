<?php
    $page_title = 'Instance List';
    require dirname(__FILE__) . "/layout/head.php";
    require dirname(__FILE__) . "/layout/nav.php";
    $instances = TikiManager\Application\Instance::getInstances();
?>

<div class="container">
    <div class="trim-instance-main-list center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <?php if (!empty($instances)) : ?>
            <ul class="clearfix">
            <?php foreach ($instances as $instance) : ?>
                <?php
                    $version = $instance->getLatestVersion();
                    $lock = (md5_file(TRIMPATH . '/scripts/maintenance.htaccess') == md5_file($instance->getWebPath('.htaccess')));
                    $blank = (! $instance->getApplication());
                ?>
                <li data-href="<?php echo html(url("view/{$instance->id}")) ?>">
                    <div class="url">
                        <?php if (! $blank) : ?>
                        <div class="lock">
                            <?php if ($lock) : ?>
                            <a href="javascript:void(0);" title="This instance is locked. Click to unlock." data-id="<?php echo html("{$instance->id}") ?>"><span class="fa fa-lock"></span></a>
                            <?php else : ?>
                            <a href="javascript:void(0);" title="This instance is unlocked. Click to lock." data-id="<?php echo html("{$instance->id}") ?>"><span class="fa fa-unlock"></span></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (! $blank) : ?>
                            <a href="<?php echo html("{$instance->weburl}") ?>" title="Visit website" target="_blank"><?php echo html($instance->name) ?></a>
                            <span>&nbsp;<?php echo html("({$version->type}, {$version->branch})"); ?></span>
                        <?php else : ?>
                            <span class="blanks"><?php echo html($instance->name) ?></span>
                            <span>&nbsp;(blank)</span>
                        <?php endif; ?>
                    </div>

                    <div class="contact">
                        <span class="left">contact: <a href="mailto:<?php echo html("{$instance->contact}") ?>"><?php echo html("{$instance->contact}") ?></a></span>
                        <span class="right">last update: <b><?php echo html("{$version->date}") ?></b></span>
                    </div>

                    <div class="buttons fa">
<!--
                            <a href="#" class="fa-check" title="Check this instance"></a>
                            <a href="#" class="fa-arrow-up" title="Upgrade this instance"></a>
-->
                    <?php if ($instance->getApplication() instanceof TikiManager\Application\Tiki) : ?>
                        <a href="" class="fa-eye" title="Watch this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="watch" data-backdrop="static"</a>
                        <a href="" class="fa-repeat" title="Update this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="update" data-backdrop="static"></a>
                        <a href="" class="fa-floppy-o" title="Backup this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="backup" data-backdrop="static"></a>
                        <a href="" class="fa-wrench" title="Fix this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="fix" data-backdrop="static"></a>
                    <?php endif; ?>
                        <a href="<?php echo html(url("edit/{$instance->id}")) ?>" class="fa-pencil" title="Edit this instance"></a>
                        <a href="" class="fa-times" title="Delete this instance" data-toggle="modal" data-target="#deleteInstance" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>"></a>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <h3>Instance list is empty.</h3>
        <?php endif; ?>

        <p class="clearfix">
            <a href="#" class="new btn btn-primary disabled">Create a new instance</a>
            <a href="<?php echo html(url('blank')) ?>" class="blank btn btn-primary">Create a blank instance</a>
            <a href="<?php echo html(url('import')) ?>" class="new btn btn-primary">Import a tiki instance</a>
        </p>
    </div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
