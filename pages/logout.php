<?php
// Define constant to allow includes
if (!defined('INCLUDED_FROM_APPLICATION')) {
    define('INCLUDED_FROM_APPLICATION', true);
}

// Include session config to ensure proper session handling
include('../includes/session_config.php');

// Set cache control headers to prevent caching of protected pages
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: 0');

// Get session cookie parameters to properly clear the cookie
$cookieParams = session_get_cookie_params();

// Clear the session cookie
setcookie(
    session_name(),
    '',
    time() - 42000,
    $cookieParams['path'],
    $cookieParams['domain'],
    $cookieParams['secure'],
    $cookieParams['httponly']
);

// Unset all session variables and destroy the session
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
