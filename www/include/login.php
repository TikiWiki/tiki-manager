<?php

if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
	if( $_POST['user'] == USERNAME && $_POST['pass'] == PASSWORD )
	{
		$_SESSION['active'] = true;
	}

	header( "Location: {$_SERVER['REQUEST_URI']}" );
	exit;
}

?>
<html>
<head><title>TRIM Web Administration Login</title></head>
<body>
<h1>TRIM Web Administration Login</h1>
<form method="post" action="<?php echo html( $_SERVER['REQUEST_URI'] ) ?>">
	<div>
		<label for="username">Username:</label>
		<input id="username" type="text" name="user"/>
	</div>
	<div>
		<label for="password">Password:</label>
		<input id="password" type="password" name="pass"/>
	</div>
	<div>
		<input type="submit"/>
	</div>
</form>
</body>
</html>
