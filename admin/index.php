<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

if (!empty($_SESSION['admin_authenticated'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
