<?php
    $page_title = 'Cloning an instance';
    require dirname(__FILE__) . "/layout/head.php";
    require dirname(__FILE__) . "/layout/nav.php";
    $instances = TikiManager\Application\Instance::getInstances(true);
?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-list clone center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>

        <?php if (!empty($instances)) : ?>
            <h3>Select the source instance</h3>
            <ul class="source clearfix">
            <?php foreach ($instances as $instance) : ?>
                <li title="Clone this instance" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="clone">
                    <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                </li>
            <?php endforeach; ?>
            </ul>

            <h3>Select the destination instance</h3>
            <ul class="hide destination clearfix">
            <?php foreach ($instances as $instance) : ?>
                <li title="Clone this instance" data-id="<?php echo html("{$instance->id}") ?>" data-name="<?php echo html("{$instance->name}") ?>" data-type="clone">
                    <?php require dirname(__FILE__) . "/layout/url.php"; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <h3>Instance list is empty.</h3>
        <?php endif; ?>

        <p class="clearfix">
        <?php require dirname(__FILE__) . "/layout/back.php"; ?>
            <button class="clone btn btn-success" data-toggle="modal" data-target="#trimModal" data-id="" data-name="" data-sourceid="" data-sourcename="" data-type="clone" data-backdrop="static" disabled>
                <span class="fa fa-clone"></span> Clone
            </button>
        </p>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/modal.php"; ?>
<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
