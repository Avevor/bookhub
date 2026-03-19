<?php
/**
 * Session Check
 * Validates session timeout and redirects to login if expired
 */

// Include direct access protection
include_once __DIR__ . '/direct_access_protection.php';

// Include session config to get timeout settings
include_once __DIR__ . '/session_config.php';

/**
 * Check if session is valid and not expired
 * Returns true if session is valid, false otherwise
 */
function isSessionValid() {
    global $session_timeout;
    
    // Check if user_id is set
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check if last_activity is set
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check if session has expired
    $elapsed = time() - $_SESSION['last_activity'];
    if ($elapsed >= $session_timeout) {
        return false;
    }
    
    // Session is valid - update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require valid session - redirects to login if not valid
 * Optionally shows a message about session expiration
 */
function requireValidSession($showMessage = true) {
    if (!isSessionValid()) {
        // Store the reason for redirect
        if ($showMessage && isset($_SESSION['last_activity'])) {
            $_SESSION['session_expired'] = true;
        }
        
        // Clear session data but keep message
        $expiredMessage = $_SESSION['session_expired'] ?? false;
        session_unset();
        session_destroy();
        
        if ($expiredMessage) {
            // Redirect with expired parameter
            header("Location: ../pages/login.php?session_expired=1");
        } else {
            header("Location: ../pages/login.php");
        }
        exit();
    }
}

/**
 * Update session last activity timestamp
 * Should be called on each page load for active users
 */
function updateSessionActivity() {
    $_SESSION['last_activity'] = time();
}
?>
