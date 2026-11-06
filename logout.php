<?php
require_once 'app_init.php';
start_secure_session();
$_SESSION = [];
session_destroy();
header('Location: login.html');
exit;
