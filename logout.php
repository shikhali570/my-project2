<?php
require_once __DIR__ . '/includes/bootstrap.php';
auth_logout();
flash_set('success', 'با موفقیت خارج شدید.');
redirect('login.php');
