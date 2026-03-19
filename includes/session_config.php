<?php
/**
 * Session Configuration
 * Sets up session timeout and security settings
 */

// Define constant to allow includes
if (!defined('INCLUDED_FROM_APPLICATION')) {
    define('INCLUDED_FROM_APPLICATION', true);
}

// Session timeout in seconds (45 minutes)
$session_timeout = 2700; // 45 minutes

// Set session cookie parameters and ini settings ONLY before session starts
// These functions cannot be called when a session is already active
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => $session_timeout,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Set garbage collection lifetime
    ini_set('session.gc_maxlifetime', $session_timeout);
    
    // Start the session
    session_start();
}

// Set the session timeout duration in session for easy access
$_SESSION['session_timeout'] = $session_timeout;

// Store session start time if not set
if (!isset($_SESSION['session_start'])) {
    $_SESSION['session_start'] = time();
}
?>
