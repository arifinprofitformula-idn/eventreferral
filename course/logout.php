<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

unset(
    $_SESSION['lms_user_id'],
    $_SESSION['lms_user_role'],
    $_SESSION['lms_user_name'],
    $_SESSION['lms_user_email'],
    $_SESSION['lms_roles'],
    $_SESSION['lms_csrf_token']
);

header('Location: /course/login.php');
exit;
