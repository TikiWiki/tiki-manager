<?php
$notifications = [];

$databasePath = $_ENV['DB_FILE'];
if (! is_writable($databasePath)) {
    $notifications[] = [
        'type'      => 'danger',
        'title'     => 'Missing permissions',
        'message'   => 'Tiki Manager can\'t write to database (' . $databasePath . '). Most changes done via web will fail.'
    ];
}
?>

<?php if(! empty($notifications)) : ?>
    <?php foreach($notifications as $notification) : ?>
        <div class="alert alert-<?= $notification['type'] ?>" role="<?= $notification['type'] ?>">
            <h5><?= $notification['title'] ?></h5>
            <?= $notification['message'] ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
