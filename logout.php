<?php
require_once __DIR__ . '/includes/bootstrap.php';
auth_logout();
session_start();
flash_set('success', 'با موفقیت خارج شدید.');
redirect('login.php');
