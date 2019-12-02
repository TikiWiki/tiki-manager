<?php

use TikiManager\Libs\Requirements\Requirements;

$page_title = 'Requirements';
require dirname(__FILE__) . "/layout/head.php";
require dirname(__FILE__) . "/layout/nav.php";

$osReq = Requirements::getInstance();
$requirements = $osReq->getRequirements();
?>

<div class="container">
    <?php require dirname(__FILE__) . "/layout/notifications.php"; ?>
    <div class="trim-instance-list center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>
        <?php if(! empty($requirements)) : ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <td>Name</td>
                        <td>Tags</td>
                        <td>Status</td>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requirements as $key => $requirement) : ?>
                        <tr>
                            <td><?= $requirement['name'] ?></td>
                            <td><?= $osReq->getTags($key) ?></td>
                            <td><?php if ($osReq->check($key)) :
                                ?> OK <?php
                                else :
                                    ?> Missing <?php
                                endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <h3>Requirements list is empty</h3>
        <?php endif; ?>

        <p class="clearfix">
            <?php require dirname(__FILE__) . "/layout/back.php"; ?>
        </p>
    </div>
</div>

<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
