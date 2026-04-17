<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';

auth_logout();
header('Location: /admin/login.php');
exit;
