<?php
    $page_title = 'Watching an instance';
    require dirname(__FILE__) . "/layout/head.php";
    require dirname(__FILE__) . "/layout/nav.php";
    $instances = TikiManager\Application\Instance::getInstances(true);
?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-list center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <?php if (!empty($instances)) : ?>
            <ul class="clearfix">
            <?php foreach ($instances as $instance) : ?>
                <li title="Watch this instance" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="watch" data-backdrop="static">
                    <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                    <div class="buttons fa">
                        <a href="javascript:void(0);" class="fa-eye" title="Watch this instance"></a>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <h3>Instance list is empty.</h3>
        <?php endif; ?>

        <p class="clearfix">
        <?php require dirname(__FILE__) . "/layout/back.php"; ?>
        </p>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
