<div class="url">
<?php
    $version = $instance->getLatestVersion();
    $blank = (! $instance->getApplication());
if (! $blank) :
    ?>
    <a href="<?php echo html("{$instance->weburl}") ?>" title="Visit website" target="_blank"><?php echo html($instance->name) ?></a>
    <span>&nbsp;<?php echo html("({$version->type}, {$version->branch})"); ?></span>
<?php else : ?>
    <span class="blanks"><?php echo html($instance->name) ?></span>
    <span>&nbsp;(blank)</span>
<?php endif; ?>
</div>
