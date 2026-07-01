<?php
require_once __DIR__ . '/../config.php';
start_secure_session();
session_destroy();
header('Location: login.php');
exit;
