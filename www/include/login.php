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

<?php $page_title = 'Login'; ?>
<?php require "include/layout/head.php"; ?>
<?php require "include/layout/nav.php"; ?>

<div class="container">
	<div class="trim-login center">
		<h1><?php echo TITLE; ?></h1>
		<h2><?php echo $page_title; ?></h2>

		<div class="row">
			<div class="col-sm-6 col-sm-offset-3">
				<form method="post" action="<?php echo html( $_SERVER['REQUEST_URI'] ) ?>">
					<fieldset>
						<div class="form-group">
							<label for="username">Username:</label>
							<input id="username" class="form-control" type="text" name="user"/>
						</div>
						<div class="form-group">
							<label for="password">Password:</label>
							<input id="password" class="form-control" type="password" name="pass"/>
						</div>
						<div class="form-group center">
							<button class="btn btn-primary" type="submit">Submit</button>
						</div>
					</fieldset>
				</form>
			</div>
		</div>

	</div>
</div>

<?php require "include/layout/footer.php"; ?>
