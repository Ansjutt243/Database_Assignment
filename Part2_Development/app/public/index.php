<?php
/**
 * Login screen (Design Document Figure 4).
 * 5 failed attempts lock the account; the message never says which field
 * was wrong.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

if (Auth::user()) {
    redirect('main.php');
}

$error = null;
$username = '';
if (is_post()) {
    Csrf::check();
    $username = input('username');
    $error = Auth::attempt($username, $_POST['password'] ?? '');
    if ($error === null) {
        redirect('main.php');
    }
}

render('header', ['title' => 'Sign in']);
?>
<section class="card login">
  <h2>Sign in to the Helpdesk System</h2>
  <form method="post" autocomplete="off">
    <?= Csrf::field() ?>
    <label for="username">Username <span class="req">*</span></label>
    <input id="username" name="username" value="<?= e($username) ?>" required maxlength="30" autofocus>

    <label for="password">Password <span class="req">*</span></label>
    <input id="password" name="password" type="password" required maxlength="72">

    <div class="actions"><button class="btn btn-primary" style="width:100%">Log in</button></div>
    <?php if ($error): ?><div class="flash flash-error" style="margin-top:12px"><?= e($error) ?></div><?php endif; ?>
  </form>
  <p class="hint">Account locks after 5 failed attempts.<br>
     Your role (Operator / Technician / Manager / Admin) decides which menu options you can use.</p>
</section>
<?php render('footer'); ?>
