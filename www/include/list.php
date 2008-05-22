<html>
<head><title>TRIM Web Administration : Instance List</title></head>
<body>
<h1>Instance List</h1>
<form method="post" action="<?php echo html( url( 'delete' ) ) ?>">
<ul>
<?php foreach( Instance::getInstances() as $instance ): ?>
	<li>
		<input type="checkbox" name="instance[]" value="<?php echo html( $instance->id ) ?>"/>
		<a href="<?php echo html( url( "view/{$instance->id}" ) ) ?>"><?php echo html( $instance->name ) ?></a>
	</li>
<?php endforeach; ?>
</ul>
<p><input type="submit" value="Delete"/></p>
</form>
</body>
</html>
