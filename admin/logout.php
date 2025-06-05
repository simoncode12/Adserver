<?php
require_once '../config/app.php';
require_once '../includes/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: login.php');
exit;
?>
