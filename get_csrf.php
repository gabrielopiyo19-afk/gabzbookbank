<?php
header('Content-Type: application/json');
require_once 'app_init.php';
require_role('student', true);
echo json_encode(['status' => 'success', 'csrf_token' => csrf_token()]);
