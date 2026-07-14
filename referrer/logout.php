<?php
require_once __DIR__ . '/../config.php';
start_secure_session();
unset($_SESSION['referrer_brand_id'], $_SESSION['referrer_whatsapp']);
header('Location: /referrer/login.php');
exit;
