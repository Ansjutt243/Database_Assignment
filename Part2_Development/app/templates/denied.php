<?php render('header', ['title' => 'Access denied']); ?>
<section class="card">
  <h2>Access denied</h2>
  <p>Your role (<?= e(Auth::ROLE_LABELS[Auth::role()] ?? '') ?>) is not allowed to open this page.</p>
  <p>For example, helpdesk operators cannot open reports, and office managers cannot log jobs.</p>
  <p><a class="btn" href="<?= e(url('main.php')) ?>">Back to Main Control Page</a></p>
</section>
<?php render('footer'); ?>
