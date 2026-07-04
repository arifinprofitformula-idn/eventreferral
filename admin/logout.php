<?php
require_once __DIR__ . '/../config.php';
start_secure_session();
session_destroy();
header('Location: /admin/login.php');
exit;
