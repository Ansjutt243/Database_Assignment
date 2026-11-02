<?php
require dirname(__DIR__) . '/src/bootstrap.php';

Auth::logout();
flash('info', 'You have been logged out.');
redirect('index.php');
