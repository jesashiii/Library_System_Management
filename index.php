<?php
session_start();
require_once 'classes/Authentication.php';

// Check if user is already logged in
if (Authentication::isLoggedIn()) {
    // Redirect based on role
    Authentication::redirectToDashboard($_SESSION['role']);
} else {
    header('Location: login.php');
    exit();
}
?>
