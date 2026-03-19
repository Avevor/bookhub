<?php
// Define constant to allow includes
if (!defined('INCLUDED_FROM_APPLICATION')) {
    define('INCLUDED_FROM_APPLICATION', true);
}

// Include session config
include('../includes/session_config.php');

// Include session check
include('../includes/session_check.php');

// Set response type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Update the last activity time
if (function_exists('updateSessionActivity')) {
    updateSessionActivity();
} else {
    $_SESSION['last_activity'] = time();
}

echo json_encode(['success' => true, 'message' => 'Session refreshed']);
exit();
?>
