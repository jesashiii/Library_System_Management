<?php
session_start();
require_once 'classes/Authentication.php';

Authentication::logout();
header('Location: login.php');
exit();
?>
