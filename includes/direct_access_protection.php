<?php
/**
 * Direct Access Protection
 * Prevents direct access to PHP files
 */

// Check if this file is being accessed directly (not included)
if (!defined('INCLUDED_FROM_APPLICATION')) {
    // Define constant to allow includes
    define('INCLUDED_FROM_APPLICATION', true);
    
    // If someone tries to access this file directly, show error and exit
    http_response_code(403);
    die('Direct access to this file is not allowed.');
}
?>
