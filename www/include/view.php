<?php $version = $instance->getLatestVersion() ?>
<html>
<head><title>TRIM Web Administration : <?php echo html( $instance->name ) ?></title></head>
<body>
	<h1>Instance : <?php echo html( $instance->name ) ?></h1>
	<table>
		<tr>
			<th>Contact</th>
			<td><a href="mailto:<?php echo html( $instance->contact ) ?>"><?php echo html( $instance->contact ) ?></a></td>
		</tr>
		<tr>
			<th>Web URL</th>
			<td><?php echo html( $instance->weburl ) ?></td>
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
			<td><?php echo html( "{$instance->app} ({$version->type}, {$version->branch})" ) ?></td>
		</tr>
		<tr>
			<th>Last update</th>
			<td><?php echo html( $version->date ) ?></td>
		</tr>
	</table>
	<p><a href="<?php echo html( url( "edit/{$instance->id}" ) ) ?>">Edit</a></p>
	<h2>Archives</h2>
	<ul>
		<?php foreach( $instance->getArchives() as $filename ): ?>
		<li><?php echo html( $filename ) ?></li>
		<?php endforeach; ?>
	</ul>
</body>
</html>
