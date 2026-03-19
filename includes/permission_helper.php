<?php
/**
 * Permission Helper Functions
 * Provides functions to check page, tab, and action permissions
 */

// Define constant before including direct access protection
if (!defined('INCLUDED_FROM_APPLICATION')) {
    define('INCLUDED_FROM_APPLICATION', true);
}

// Include direct access protection
include_once __DIR__ . '/direct_access_protection.php';

/**
 * Check if user has access to a specific page
 * @param mysqli $conn Database connection
 * @param int $user_role_id User's role ID (1=admin, 2=sales)
 * @param string $page_name The page name to check
 * @return bool True if user has access
 */
function has_page_access($conn, $user_role_id, $page_name) {
    // Admin always has access
    if ($user_role_id == 1) {
        return true;
    }
    
    // If no connection or invalid parameters, deny access for safety
    if (!$conn || !is_object($conn)) {
        return false;
    }
    
    // Check if sales user has access to this page
    $sql = "SELECT sales_enabled FROM page_access_settings 
            WHERE page_name = ? AND access_type = 'page'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
$stmt->bind_param("s", $page_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    return $row['sales_enabled'] == 1;
}
$result->free();
$stmt->close();
    
    // Default: allow access if no specific settings found (for backward compatibility)
    return true;
}

/**
 * Check if user has access to a specific tab within a page
 * @param mysqli $conn Database connection
 * @param int $user_role_id User's role ID
 * @param string $parent_page The parent page name
 * @param string $tab_key The tab key to check
 * @return bool True if user has access
 */
function has_tab_access($conn, $user_role_id, $parent_page, $tab_key) {
    // Admin always has access
    if ($user_role_id == 1) {
        return true;
    }
    
    // If no connection, deny access
    if (!$conn || !is_object($conn)) {
        return false;
    }
    
    // First check if user has access to the parent page
    if (!has_page_access($conn, $user_role_id, $parent_page)) {
        return false;
    }
    
    // Check if sales user has access to this tab
    $sql = "SELECT sales_enabled FROM page_access_settings 
            WHERE parent_page = ? AND access_key = ? AND access_type = 'tab'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $parent_page, $tab_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['sales_enabled'] == 1;
    }
    
    // Default to true if no specific tab permission is set
    return true;
}

/**
 * Check if user has access to a specific action
 * @param mysqli $conn Database connection
 * @param int $user_role_id User's role ID
 * @param string $parent_page The parent page name
 * @param string $action_key The action key to check
 * @return bool True if user has access
 */
function has_action_access($conn, $user_role_id, $parent_page, $action_key) {
    // Admin always has access
    if ($user_role_id == 1) {
        return true;
    }
    
    // If no connection, deny access
    if (!$conn || !is_object($conn)) {
        return false;
    }
    
    // First check if user has access to the parent page
    if (!has_page_access($conn, $user_role_id, $parent_page)) {
        return false;
    }
    
    // Check if sales user has access to this action
    $sql = "SELECT sales_enabled FROM page_access_settings 
            WHERE parent_page = ? AND access_key = ? AND access_type = 'action'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $parent_page, $action_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['sales_enabled'] == 1;
    }
    
    // Default to true if no specific action permission is set
    return true;
}

/**
 * Get all accessible tabs for a page (for a specific user role)
 * @param mysqli $conn Database connection
 * @param int $user_role_id User's role ID
 * @param string $parent_page The parent page name
 * @return array Array of accessible tab keys
 */
function get_accessible_tabs($conn, $user_role_id, $parent_page) {
    $tabs = array();
    
    // If no connection, return empty array
    if (!$conn || !is_object($conn)) {
        return $tabs;
    }
    
    // Admin has access to all tabs
    if ($user_role_id == 1) {
        $sql = "SELECT access_key FROM page_access_settings 
                WHERE parent_page = ? AND access_type = 'tab'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $tabs;
        }
        $stmt->bind_param("s", $parent_page);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $tabs[] = $row['access_key'];
        }
        return $tabs;
    }
    
    // Check each tab for sales user
    $sql = "SELECT access_key FROM page_access_settings 
            WHERE parent_page = ? AND access_type = 'tab' AND sales_enabled = 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $tabs;
    }
    $stmt->bind_param("s", $parent_page);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tabs[] = $row['access_key'];
    }
    
    return $tabs;
}

/**
 * Check if user can perform an action, redirect to access denied if not
 * @param mysqli $conn Database connection
 * @param int $user_role_id User's role ID
 * @param string $parent_page The parent page name
 * @param string $action_key The action key to check
 */
function require_action_access($conn, $user_role_id, $parent_page, $action_key) {
    if (!has_action_access($conn, $user_role_id, $parent_page, $action_key)) {
        echo "<div style='text-align:center;padding:50px;'>";
        echo "<h2 style='color:red;'>Access Denied 🚫</h2>";
        echo "<p>You don't have permission to perform this action.</p>";
        echo "<a href='javascript:history.back()' class='btn btn-primary'>Go Back</a>";
        echo "</div>";
        exit();
    }
}

/**
 * Check if user can view a tab, redirect to access denied if not
 * @param mysqli $conn Database connection
 * @param int $user_role_id User's role ID
 * @param string $parent_page The parent page name
 * @param string $tab_key The tab key to check
 */
function require_tab_access($conn, $user_role_id, $parent_page, $tab_key) {
    if (!has_tab_access($conn, $user_role_id, $parent_page, $tab_key)) {
        echo "<div style='text-align:center;padding:50px;'>";
        echo "<h2 style='color:red;'>Access Denied 🚫</h2>";
        echo "<p>You don't have permission to view this section.</p>";
        echo "<a href='javascript:history.back()' class='btn btn-primary'>Go Back</a>";
        echo "</div>";
        exit();
    }
}
