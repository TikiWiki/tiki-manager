<?php
	$version = $instance->getLatestVersion();
	$all = Instance::getInstances();
	while(key($all) != $instance->id) {
		$prev = current($all);
		next($all);
	}
	$next = next($all);
?>

<?php $page_title = 'Instance : ' . html( $instance->name ); ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-instance-view center">
		<h1><?php echo TITLE; ?></h1>
		<div class="nav">
			<h2><?php echo $page_title; ?></h2>
			<?php if ($prev): ?>
			<a class="left btn btn-default" href="<?php echo html( url( "view/{$prev->id}" ) ) ?>" title="Previous instance">
				<span class="fa fa-arrow-left"></span>
			</a>
			<?php endif; ?>
			<?php if ($next): ?>
			<a class="right btn btn-default" href="<?php echo html( url( "view/{$next->id}" ) ) ?>" title="Next instance">
				<span class="fa fa-arrow-right"></span>
			</a>
			<?php endif; ?>
		</div>

		<table class="table table-bordered">
			<tr>
				<th>Contact</th>
				<td><a href="mailto:<?php echo html( $instance->contact ) ?>"><?php echo html( $instance->contact ) ?></a></td>
			</tr>
			<tr>
				<th>Web URL</th>
				<td><a href="<?php echo html( $instance->weburl ) ?>" target="_blank"><?php echo html( $instance->weburl ) ?></a></td>
			</tr>
			<tr>
				<th>Web Root</th>
				<td><?php echo html( $instance->webroot ) ?></td>
			</tr>
			<tr>
				<th>Work Directory</th>
				<td><?php echo html( $instance->tempdir ) ?></td>
			</tr>
			<tr>
				<th>PHP Interpreter</th>
				<td><?php echo html( $instance->phpexec ) ?></td>
			</tr>
			<tr>
				<th>Application</th>
				<td><?php
					if (! $instance->getApplication()) {
						echo "(blank instance)";
					} else {
						echo html( "{$instance->app} ({$version->type}, {$version->branch})" );
					}
				?></td>
			</tr>
			<tr>
				<th>Backup owner</th>
				<td><?php echo html( $instance->getProp('backup_user') ) ?></td>
			</tr>
			<tr>
				<th>Backup group</th>
				<td><?php echo html( $instance->getProp('backup_group') ) ?></td>
			</tr>
			<tr>
				<th>Backup file permissions</th>
				<td><?php echo html( decoct($instance->getProp('backup_perm')) ) ?></td>
			</tr>
			<tr>
				<th>Last update</th>
				<td>
					<?php echo html( $version->date ) ?>
					<a href="#" class="fa update" title="Update now">
						<span class="fa-refresh"></span>
					</a>
				</td>
			</tr>
			<tr>
				<th class="top">Additional folders to backup</th>
				<td>
					<ul>
						<li style="display:none"></li>
						<?php foreach( $instance->getExtraBackups() as $path ): ?>
						<li><?php echo html( $path ) ?></li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		</table>

		<p class="clearfix">
			<a href="<?php echo html( url( '' ) ) ?>" class="left btn btn-default">
				<span class="fa fa-angle-double-left"></span>
				Back to list
			</a>
			<a href="<?php echo html( url( "edit/{$instance->id}" ) ) ?>" class="right edit btn btn-primary">
				<span class="fa fa-pencil"></span> Edit
			</a>
		<?php if ($instance->getApplication()): ?>
			<a href="javascript:void(0);" class="right btn btn-primary" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="fix">
				<span class="fa fa-wrench"></span> Fix
			</a>
			<a href="javascript:void(0);" class="right btn btn-primary" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="backup">
				<span class="fa fa-floppy-o"></span> Backup
			</a>
			<a href="javascript:void(0);" class="right btn btn-primary" data-toggle="modal" data-target="#trimModal" data-id="<?php echo html( "{$instance->id}" ) ?>" data-name="<?php echo html( "{$instance->name}" ) ?>" data-type="update">
				<span class="fa fa-refresh"></span> Update
			</a>
		<?php endif; ?>
		</p>

		<div class="archives">
			<h2>Archives</h2>
			<?php if (!empty($instance->getArchives())): ?>
				<ul>
					<?php foreach( $instance->getArchives() as $filename ): ?>
					<li><?php echo html( $filename ) ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else: ?>
				<h3>Archive list is empty.</h3>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php require "include/layout/modal.php"; ?>
<?php require "include/layout/footer.php"; ?>
