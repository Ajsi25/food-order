<?php
require_once '../config/auth.php';
logout_admin();
header('Location: login.php');
exit;

