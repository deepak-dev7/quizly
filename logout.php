<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();

header('Location: ' . BASE_URL . '/login.php');
@session_write_close();
exit;
