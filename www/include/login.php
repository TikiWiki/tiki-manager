<?php

use TikiManager\Config\App;

$info = App::get('info');
$isLoginLocked = $info->isLoginLocked();

$invalidLogin = $_SESSION['invalid_login'] ?: false;
unset($_SESSION['invalid_login']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isLoginLocked) {
    if ($_POST['user'] == USERNAME && $_POST['pass'] == PASSWORD) {
        $_SESSION['active'] = true;
        $info->resetLoginAttempts();
    } else {
        $_SESSION['invalid_login'] = true;
        $info->incrementLoginAttempts();
    }

    header("Location: {$_SERVER['REQUEST_URI']}");
    exit;
}

?>

<?php $page_title = 'Login'; ?>
<?php $loginDisabled = $isLoginLocked ? 'disabled="disabled"' : ''; ?>
<?php require dirname(__FILE__) . "/layout/head.php"; ?>
<?php require dirname(__FILE__) . "/layout/nav.php"; ?>

<div class="container">
    <div class="trim-login center">
        <h1><?php echo TITLE; ?></h1>
        <h2><?php echo $page_title; ?></h2>
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <form method="post" action="<?php echo html($_SERVER['REQUEST_URI']) ?>">
                    <?php if ($isLoginLocked) : ?>
                        <div class="alert alert-danger" role="alert">
                            <h6>Too many invalid login attempts</h6>
                            <small>Login is temporarily disabled, please contact an administrator to unlock the login process.</small>
                        </div>
                    <?php endif; ?>
                    <?php if ($invalidLogin && !$isLoginLocked) : ?>
                        <div class="alert alert-danger" role="alert">
                            <h6>Login failed</h6>
                            <small> Invalid username or password.</small>
                        </div>
                    <?php endif; ?>
                    <fieldset>
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input id="username" class="form-control" type="text" name="user" />
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input id="password" class="form-control" type="password" name="pass" />
                        </div>
                        <div class="form-group center">
                            <button class="btn btn-primary" <?php echo $loginDisabled; ?> type="submit">
                                Log in
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
