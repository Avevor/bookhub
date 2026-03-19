<?php
session_start();
include('../config/db.php');

// ✅ Restrict access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// ✅ Check page access for sales users
$user_role_id = $_SESSION['role_id'] ?? 0;
$can_access = false;

if ($user_role_id == 1) {
    // Admin always has access
    $can_access = true;
} elseif ($user_role_id == 2) {
    // Sales user - check page_access_settings with access_type = 'page'
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'manage_settings' AND access_type = 'page'";
    $access_result = $conn->query($access_sql);
    if ($access_result && $access_result->num_rows > 0) {
        $access_row = $access_result->fetch_assoc();
        $can_access = $access_row['sales_enabled'] == 1;
    } else {
        // Default to false if no settings found
        $can_access = false;
    }
}

if (!$can_access) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}


$success_message = '';
$error_message = '';
$active_tab = $_GET['tab'] ?? 'general';

// Get accessible tabs for sales users
$accessible_tabs = [];
if ($user_role_id != 1) {
    // Sales user - check tab permissions for Settings page
    $tabs_sql = "SELECT access_key FROM page_access_settings 
                 WHERE parent_page = 'manage_settings' AND access_type = 'tab' AND sales_enabled = 1";
    $tabs_result = $conn->query($tabs_sql);
    if ($tabs_result && $tabs_result->num_rows > 0) {
        while ($tab_row = $tabs_result->fetch_assoc()) {
            $accessible_tabs[] = $tab_row['access_key'];
        }
    }
    // If no specific tab permissions set, allow all tabs when page is accessible
    if (empty($accessible_tabs)) {
        $accessible_tabs = ['general', 'grades', 'categories', 'display', 'access', 'users'];
    }
} else {
    // Admin has access to all tabs
    $accessible_tabs = ['general', 'grades', 'categories', 'display', 'access', 'users'];
}

// Check if the requested tab is accessible, if not redirect to first accessible tab
if (!in_array($active_tab, $accessible_tabs) && !empty($accessible_tabs)) {
    $active_tab = $accessible_tabs[0];
}

// Handle user management
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = intval($_POST['role_id'] ?? 2);
    
    if ($username && $email && $password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, role_id, status) 
                VALUES ('$username', '$email', '$hashed_password', $role_id, 'Active')";
        if ($conn->query($sql)) {
            $success_message = "User added successfully!";
            $active_tab = 'users';
        } else {
            $error_message = "Error adding user: " . $conn->error;
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Handle user deletion
if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);
    // Prevent deleting yourself
    if ($user_id != $_SESSION['user_id']) {
        $sql = "DELETE FROM users WHERE user_id = $user_id";
        if ($conn->query($sql)) {
            $success_message = "User deleted successfully!";
            $active_tab = 'users';
        } else {
            $error_message = "Error deleting user.";
        }
    } else {
        $error_message = "You cannot delete your own account.";
    }
}

// Handle form submission for system settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {

    $tab = $_POST['tab'] ?? 'general';
    
    if ($tab == 'general') {
        // General/Shop settings
        $shop_name = $conn->real_escape_string($_POST['shop_name'] ?? '');
        $shop_address = $conn->real_escape_string($_POST['shop_address'] ?? '');
        $shop_phone = $conn->real_escape_string($_POST['shop_phone'] ?? '');
        $shop_email = $conn->real_escape_string($_POST['shop_email'] ?? '');
        $currency = $conn->real_escape_string($_POST['currency'] ?? 'USD');
        
        $settings = [
            'shop_name' => $shop_name,
            'shop_address' => $shop_address,
            'shop_phone' => $shop_phone,
            'shop_email' => $shop_email,
            'currency' => $currency
        ];
    } elseif ($tab == 'grades') {
        // Grades settings
        $grades = $conn->real_escape_string($_POST['grades'] ?? '');
        $settings = ['grades' => $grades];
    } elseif ($tab == 'categories') {
        // Categories settings
        $categories = $conn->real_escape_string($_POST['categories'] ?? '');
        $settings = ['categories' => $categories];
    } elseif ($tab == 'display') {
        // Display settings
        $show_stats = isset($_POST['show_stats']) ? '1' : '0';
        $settings = ['show_stats' => $show_stats];
    }
    
    $all_success = true;
    foreach ($settings as $key => $value) {
        $sql = "INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('$key', '$value') 
                ON DUPLICATE KEY UPDATE setting_value = '$value'";
        if (!$conn->query($sql)) {
            $all_success = false;
        }
    }
    
    if ($all_success) {
        $success_message = "Settings updated successfully!";
        $active_tab = $tab;
    } else {
        $error_message = "Error updating some settings.";
    }
}

// Get current settings
$settings_sql = "SELECT * FROM system_settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values if not set
$shop_name = $settings['shop_name'] ?? 'School Bookshop';
$shop_address = $settings['shop_address'] ?? '';
$shop_phone = $settings['shop_phone'] ?? '';
$shop_email = $settings['shop_email'] ?? '';
$currency = $settings['currency'] ?? 'USD';
$grades_setting = $settings['grades'] ?? 'Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12';
$categories_setting = $settings['categories'] ?? 'Fiction,Non-Fiction,Science,Mathematics,History,Geography,English,Art,Music,Physical Education';
$grades_list = array_map('trim', explode(',', $grades_setting));
$categories_list = array_map('trim', explode(',', $categories_setting));
$show_stats = $settings['show_stats'] ?? '1';

// Get system stats
$total_books = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_sales = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];

// Get all users
$users_sql = "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id ORDER BY u.created_at DESC";
$users_result = $conn->query($users_sql);

// Create page_access_settings table if it doesn't exist
// Create or update page_access_settings table with enhanced schema
$table_check = $conn->query("SHOW TABLES LIKE 'page_access_settings'");
if ($table_check->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS page_access_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_name VARCHAR(100) NOT NULL,
        page_display_name VARCHAR(255) NOT NULL,
        access_type ENUM('page', 'tab', 'action') DEFAULT 'page',
        parent_page VARCHAR(100) DEFAULT NULL,
        access_key VARCHAR(100) DEFAULT NULL,
        admin_enabled TINYINT(1) DEFAULT 1,
        sales_enabled TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_access (page_name, access_type, parent_page, access_key)
    )");
    
    // Default page permissions
    $default_pages = [
        // Pages
        ['admin_dashboard', 'Dashboard', 'page', NULL, NULL, 1, 1],
        ['manage_books', 'Manage Books', 'page', NULL, NULL, 1, 1],
        ['add_book', 'Add Book', 'page', NULL, NULL, 1, 1],
        ['sell_books', 'Sell Books', 'page', NULL, NULL, 1, 1],
        ['manage_inventory', 'Inventory', 'page', NULL, NULL, 1, 0],
        ['view_sales', 'View Sales', 'page', NULL, NULL, 1, 1],
        ['manage_payments', 'Manage Payments', 'page', NULL, NULL, 1, 1],
        ['manage_suppliers', 'Suppliers', 'page', NULL, NULL, 1, 0],
        ['manage_settings', 'Settings', 'page', NULL, NULL, 1, 0],
        
        // Settings page tabs
        ['settings_general', 'General', 'tab', 'manage_settings', 'general', 1, 1],
        ['settings_grades', 'Grades', 'tab', 'manage_settings', 'grades', 1, 1],
        ['settings_categories', 'Categories', 'tab', 'manage_settings', 'categories', 1, 1],
        ['settings_display', 'Display', 'tab', 'manage_settings', 'display', 1, 1],
        ['settings_access', 'Access', 'tab', 'manage_settings', 'access', 1, 1],
        ['settings_users', 'Users', 'tab', 'manage_settings', 'users', 1, 1],
        
        // Settings page actions
        ['settings_add_user', 'Add User', 'action', 'manage_settings', 'add_user', 1, 1],
        ['settings_delete_user', 'Delete User', 'action', 'manage_settings', 'delete_user', 1, 1],
        
        // Manage Books actions
        ['books_add', 'Add Book', 'action', 'manage_books', 'add', 1, 1],
        ['books_edit', 'Edit Book', 'action', 'manage_books', 'edit', 1, 1],
        ['books_delete', 'Delete Book', 'action', 'manage_books', 'delete', 1, 0],
        
        // Sell Books actions
        ['sell_create', 'Create Sale', 'action', 'sell_books', 'create', 1, 1],
        ['sell_view_receipt', 'View Receipt', 'action', 'sell_books', 'receipt', 1, 1],
    ];
    
    foreach ($default_pages as $page) {
        $stmt = $conn->prepare("INSERT IGNORE INTO page_access_settings (page_name, page_display_name, access_type, parent_page, access_key, admin_enabled, sales_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssii", $page[0], $page[1], $page[2], $page[3], $page[4], $page[5], $page[6]);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Table exists, check if we need to add columns for enhanced access control
    $columns_check = $conn->query("SHOW COLUMNS FROM page_access_settings LIKE 'access_type'");
    if ($columns_check->num_rows == 0) {
        // Add new columns for enhanced access control
        $conn->query("ALTER TABLE page_access_settings ADD COLUMN access_type ENUM('page', 'tab', 'action') DEFAULT 'page' AFTER page_display_name");
        $conn->query("ALTER TABLE page_access_settings ADD COLUMN parent_page VARCHAR(100) DEFAULT NULL AFTER access_type");
        $conn->query("ALTER TABLE page_access_settings ADD COLUMN access_key VARCHAR(100) DEFAULT NULL AFTER parent_page");
        $conn->query("ALTER TABLE page_access_settings ADD UNIQUE KEY unique_access (page_name, access_type, parent_page, access_key)");
        
        // Add default tabs for Settings page if not exist
        $settings_tabs = [
            ['settings_general', 'General', 'tab', 'manage_settings', 'general', 1, 1],
            ['settings_grades', 'Grades', 'tab', 'manage_settings', 'grades', 1, 1],
            ['settings_categories', 'Categories', 'tab', 'manage_settings', 'categories', 1, 1],
            ['settings_display', 'Display', 'tab', 'manage_settings', 'display', 1, 1],
            ['settings_access', 'Access', 'tab', 'manage_settings', 'access', 1, 1],
            ['settings_users', 'Users', 'tab', 'manage_settings', 'users', 1, 1],
        ];
        
        foreach ($settings_tabs as $tab) {
            $stmt = $conn->prepare("INSERT IGNORE INTO page_access_settings (page_name, page_display_name, access_type, parent_page, access_key, admin_enabled, sales_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssii", $tab[0], $tab[1], $tab[2], $tab[3], $tab[4], $tab[5], $tab[6]);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Handle access control settings save (bulk save from access control form)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_all_access'])) {
    $access_settings = $_POST['access'] ?? [];
    
    // First, disable ALL pages
    $reset_sql = "UPDATE page_access_settings SET sales_enabled = 0";
    $conn->query($reset_sql);
    
    // Then enable only the pages that were checked
    if (!empty($access_settings)) {
        $all_success = true;
        foreach ($access_settings as $page_name => $enabled) {
            $sales_enabled = $enabled ? 1 : 0;
            $update_sql = "UPDATE page_access_settings SET sales_enabled = ? WHERE page_name = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("is", $sales_enabled, $page_name);
            if (!$update_stmt->execute()) {
                $all_success = false;
            }
            $update_stmt->close();
        }
        
        if ($all_success) {
            $success_message = "Access settings updated successfully!";
        } else {
            $error_message = "Error updating some access settings.";
        }
    } else {
        $success_message = "Access settings updated successfully!";
    }
    $active_tab = 'access';
}

// Get current access settings
$access_sql = "SELECT * FROM page_access_settings ORDER BY page_display_name";
$access_result = $conn->query($access_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Settings - Book Hub</title>
  <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
  <style>
    .settings-container { max-width: 1000px; margin: 0 auto; }
    .tab-navigation { display: flex; gap: 5px; margin-bottom: 0; background: #f8f9fa; padding: 10px 10px 0 10px; border-radius: 10px 10px 0 0; border-bottom: 2px solid #003366; }
    .tab-btn { padding: 12px 25px; border: none; background: #e0e0e0; color: #666; font-size: 1em; font-weight: 600; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
    .tab-btn:hover { background: #d0d0d0; }
    .tab-btn.active { background: #003366; color: white; }
    .tab-content { display: none; background: white; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); overflow: hidden; }
    .tab-content.active { display: block; }
    .tab-header { background: linear-gradient(135deg, #003366 0%, #005580 100%); color: white; padding: 20px 25px; }
    .tab-header h2 { margin: 0; font-size: 1.3em; display: flex; align-items: center; gap: 10px; }
    .tab-body { padding: 30px; }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 0.95em; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #003366; }
    .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .btn-save { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 14px 40px; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; }
    .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .stats-overview { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
    .stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-align: center; }
    .stat-box .number { font-size: 2.2em; font-weight: bold; }
    .badge-admin { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8em; }
    .badge-sales { background: #28a745; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8em; }
    .badge-inactive { background: #6c757d; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8em; }
    .btn-action { padding: 6px 12px; border: none; border-radius: 6px; font-size: 0.85em; font-weight: 600; cursor: pointer; }
    .btn-edit { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; }
    .btn-delete { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
    
    /* Access Control Grid */
    .access-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .access-card { background: #fff; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; transition: all 0.3s ease; }
    .access-card.enabled { border-color: #28a745; background: #f0fff4; }
    .access-card.disabled { border-color: #dc3545; background: #fff5f5; }
    .access-card-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
    .page-icon { font-size: 2em; }
    .page-info h4 { margin: 0 0 5px 0; color: #333; font-size: 1.1em; }
    .page-name { font-size: 0.85em; color: #666; }
    .access-card-body { display: flex; justify-content: space-between; align-items: center; }
    .role-badge { display: flex; flex-direction: column; gap: 4px; }
    .badge-label { font-size: 0.75em; color: #666; text-transform: uppercase; font-weight: 600; }
    .badge-status { font-size: 0.85em; color: #28a745; font-weight: 600; }
    .admin-badge .badge-status { color: #dc3545; }
    
    /* Toggle Switch */
    .toggle-container { display: flex; align-items: center; gap: 10px; }
    .toggle-switch { position: relative; width: 50px; height: 26px; display: inline-block; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.4s; border-radius: 34px; }
    .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: 0.4s; border-radius: 50%; }
    .toggle-switch input:checked + .toggle-slider { background-color: #28a745; }
    .toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }
    .toggle-label { font-size: 0.9em; font-weight: 600; color: #666; }
    
    /* Access Actions */
    .access-actions { display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
    .btn-save-all { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 14px 35px; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: all 0.3s; }
    .btn-save-all:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4); }
    .btn-bulk { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; transition: all 0.3s; }
    .btn-bulk:hover { background: #5a6268; }
    .btn-reset-form { background: #17a2b8; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; transition: all 0.3s; }
    .btn-reset-form:hover { background: #138496; }
    
    .empty-state { text-align: center; padding: 50px 20px; }
    .empty-state-icon { font-size: 4em; margin-bottom: 20px; }
    .empty-state h3 { color: #333; margin: 0 0 10px 0; }
    .empty-state p { color: #666; }
    
    .sales-table { width: 100%; border-collapse: collapse; }
    .sales-table thead { background: #f8f9fa; }
    .sales-table th { padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #e0e0e0; }
    .sales-table td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
    .sales-table tbody tr:hover { background: #f8f9fa; }
    
    @media (max-width: 768px) { .tab-navigation { flex-wrap: wrap; } .tab-btn { flex: 1; min-width: 120px; justify-content: center; } .form-row { grid-template-columns: 1fr; } .stats-overview { grid-template-columns: 1fr; } .access-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<?php include('../includes/header.php'); ?>
<?php include('../includes/sidebar.php'); ?>

<div class="dashboard-container">
  <div class="main-content">
    <div class="page-header">
      <h1><img src="../images/settings.jpg" alt="Settings" class="dashboard-title-logo">System Settings</h1>
      <p class="page-subtitle">Configure your bookshop settings</p>
    </div>

    <?php if ($success_message): ?><div class="alert alert-success">✅ <?php echo $success_message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-error">❌ <?php echo $error_message; ?></div><?php endif; ?>

    <div class="settings-container">
      <?php if ($show_stats == '1'): ?>
      <div class="stats-overview" style="margin-bottom: 25px;">
        <div class="stat-box"><div class="number"><?php echo $total_books; ?></div><div class="label">📚 Total Books</div></div>
        <div class="stat-box" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);"><div class="number"><?php echo $total_users; ?></div><div class="label">👥 Total Users</div></div>
        <div class="stat-box" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);"><div class="number"><?php echo $total_sales; ?></div><div class="label">💰 Total Sales</div></div>
      </div>
      <?php endif; ?>

      <div class="tab-navigation">
        <?php if (in_array('general', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'general' ? 'active' : ''; ?>" onclick="switchTab('general')">🏪 General</button>
        <?php endif; ?>
        <?php if (in_array('grades', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'grades' ? 'active' : ''; ?>" onclick="switchTab('grades')">🎓 Grades</button>
        <?php endif; ?>
        <?php if (in_array('categories', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'categories' ? 'active' : ''; ?>" onclick="switchTab('categories')">🏷️ Categories</button>
        <?php endif; ?>
        <?php if (in_array('display', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'display' ? 'active' : ''; ?>" onclick="switchTab('display')">🖥️ Display</button>
        <?php endif; ?>
        <?php if (in_array('access', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'access' ? 'active' : ''; ?>" onclick="switchTab('access')">🔐 Access</button>
        <?php endif; ?>
        <?php if (in_array('users', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">👥 Users</button>
        <?php endif; ?>
      </div>

      <!-- General Tab -->
      <div id="general" class="tab-content <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
        <div class="tab-header"><h2>🏪 Shop Information</h2></div>
        <div class="tab-body">
          <form method="POST" action="">
            <input type="hidden" name="tab" value="general">
            <div class="form-row">
              <div class="form-group">
                <label>Shop Name *</label>
                <input type="text" name="shop_name" value="<?php echo htmlspecialchars($shop_name); ?>" required>
              </div>
              <div class="form-group">
                <label>Currency *</label>
                <select name="currency" required>
                  <option value="USD" <?php echo $currency == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                  <option value="EUR" <?php echo $currency == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                  <option value="GBP" <?php echo $currency == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                  <option value="GHS" <?php echo $currency == 'GHS' ? 'selected' : ''; ?>>GHS (₵)</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Shop Address</label>
              <textarea name="shop_address" rows="2"><?php echo htmlspecialchars($shop_address); ?></textarea>
            </div>
            <button type="submit" name="update_settings" class="btn-save">💾 Save Settings</button>
          </form>
        </div>
      </div>

      <!-- Display Tab -->
      <div id="display" class="tab-content <?php echo $active_tab == 'display' ? 'active' : ''; ?>">
        <div class="tab-header"><h2>🖥️ Display Settings</h2></div>
        <div class="tab-body">
          <form method="POST" action="">
            <input type="hidden" name="tab" value="display">
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <input type="checkbox" name="show_stats" value="1" <?php echo $show_stats == '1' ? 'checked' : ''; ?> style="width:20px;height:20px;">
                <span style="font-weight:600;">Show Statistics Cards</span>
              </label>
            </div>
            <button type="submit" name="update_settings" class="btn-save">💾 Save Settings</button>
          </form>
        </div>
      </div>

      <!-- Grades Tab -->
      <div id="grades" class="tab-content <?php echo $active_tab == 'grades' ? 'active' : ''; ?>">
        <div class="tab-header"><h2>🎓 Grades</h2></div>
        <div class="tab-body">
          <form method="POST" action="">
            <input type="hidden" name="tab" value="grades">
            <div class="form-group">
              <label>Available Grades</label>
              <textarea name="grades" rows="4"><?php echo htmlspecialchars($grades_setting); ?></textarea>
            </div>
            <button type="submit" name="update_settings" class="btn-save">💾 Save Grades</button>
          </form>
        </div>
      </div>

      <!-- Categories Tab -->
      <div id="categories" class="tab-content <?php echo $active_tab == 'categories' ? 'active' : ''; ?>">
        <div class="tab-header"><h2>🏷️ Categories</h2></div>
        <div class="tab-body">
          <form method="POST" action="">
            <input type="hidden" name="tab" value="categories">
            <div class="form-group">
              <label>Available Categories</label>
              <textarea name="categories" rows="4"><?php echo htmlspecialchars($categories_setting); ?></textarea>
            </div>
            <button type="submit" name="update_settings" class="btn-save">💾 Save Categories</button>
          </form>
        </div>
      </div>

      <!-- Access Control Tab -->
      <div id="access" class="tab-content <?php echo $active_tab == 'access' ? 'active' : ''; ?>">
        <div class="tab-header"><h2>🔐 Page Access Control</h2></div>
        <div class="tab-body">
          <p style="margin-bottom:20px;color:#666;">Control which pages, tabs, and actions Sales users can access. Toggle the switches and click "Save All Changes" to update permissions.</p>
          
          <?php 
          if ($access_result && $access_result->num_rows > 0):
              // Organize access settings by type
              $access_result->data_seek(0);
              $pages = [];
              $tabs = [];
              $actions = [];
              while ($row = $access_result->fetch_assoc()) {
                  if ($row['access_type'] == 'page') {
                      $pages[] = $row;
                  } elseif ($row['access_type'] == 'tab') {
                      $tabs[$row['parent_page']][] = $row;
                  } elseif ($row['access_type'] == 'action') {
                      $actions[$row['parent_page']][] = $row;
                  }
              }
          ?>
          <form method="POST" action="" id="accessControlForm">
            <input type="hidden" name="tab" value="access">
            
            <div class="access-section">
              <h3 style="margin-bottom:15px;">📄 Page Access</h3>
              <p style="color:#666;font-size:0.9em;margin-bottom:15px;">Control which main pages Sales users can access</p>
              <div class="access-grid">
                <?php foreach ($pages as $page): ?>
                <div class="access-card <?php echo $page['sales_enabled'] ? 'enabled' : 'disabled'; ?>">
                  <div class="access-card-header">
                    <div class="page-icon"><?php echo $page['sales_enabled'] ? '✅' : '❌'; ?></div>
                    <div class="page-info">
                      <h4><?php echo htmlspecialchars($page['page_display_name']); ?></h4>
                      <span class="page-name">Page</span>
                    </div>
                  </div>
                  <div class="access-card-body">
                    <div class="role-badge admin-badge">
                      <span class="badge-label">Admin</span>
                      <span class="badge-status">✓ Always</span>
                    </div>
                    <div class="toggle-container">
                      <label class="toggle-switch">
                        <input type="checkbox" name="access[<?php echo htmlspecialchars($page['page_name']); ?>]" 
                               value="1" <?php echo $page['sales_enabled'] ? 'checked' : ''; ?>
                               onchange="updateCardStyle(this)">
                        <span class="toggle-slider"></span>
                      </label>
                      <span class="toggle-label"><?php echo $page['sales_enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                    </div>
                  </div>
                  
                  <?php 
                  // Show tabs for this page
                  if (isset($tabs[$page['page_name']]) && count($tabs[$page['page_name']]) > 0): 
                  ?>
                  <div class="nested-access" style="margin-top:15px;padding-top:15px;border-top:1px dashed #ddd;">
                    <div class="nested-label" style="font-size:0.8em;color:#666;margin-bottom:10px;">📑 Tabs:</div>
                    <?php foreach ($tabs[$page['page_name']] as $tab): ?>
                    <div class="nested-item" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding:8px;background:#f8f9fa;border-radius:6px;">
                      <span style="font-size:0.9em;"><?php echo htmlspecialchars($tab['page_display_name']); ?></span>
                      <label class="toggle-switch" style="transform:scale(0.8);">
                        <input type="checkbox" name="access[<?php echo htmlspecialchars($tab['page_name']); ?>]" 
                               value="1" <?php echo $tab['sales_enabled'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  
                  <?php 
                  // Show actions for this page
                  if (isset($actions[$page['page_name']]) && count($actions[$page['page_name']]) > 0): 
                  ?>
                  <div class="nested-access" style="margin-top:15px;padding-top:15px;border-top:1px dashed #ddd;">
                    <div class="nested-label" style="font-size:0.8em;color:#666;margin-bottom:10px;">⚡ Actions:</div>
                    <?php foreach ($actions[$page['page_name']] as $action): ?>
                    <div class="nested-item" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding:8px;background:#f8f9fa;border-radius:6px;">
                      <span style="font-size:0.9em;"><?php echo htmlspecialchars($action['page_display_name']); ?></span>
                      <label class="toggle-switch" style="transform:scale(0.8);">
                        <input type="checkbox" name="access[<?php echo htmlspecialchars($action['page_name']); ?>]" 
                               value="1" <?php echo $action['sales_enabled'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            
            <div class="access-actions">
              <button type="submit" name="save_all_access" class="btn-save-all">💾 Save All Changes</button>
              <button type="button" onclick="enableAll()" class="btn-bulk">✓ Enable All</button>
              <button type="button" onclick="disableAll()" class="btn-bulk">✗ Disable All</button>
              <button type="reset" onclick="resetForm()" class="btn-reset-form">↺ Reset</button>
            </div>
          </form>
          <?php else: ?>
          <div class="empty-state">
            <div class="empty-state-icon">🔐</div>
            <h3>No Access Settings Found</h3>
            <p>The page access settings table may not be initialized. Please refresh the page to create default settings.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>


      <!-- Users Tab -->
      <div id="users" class="tab-content <?php echo $active_tab == 'users' ? 'active' : ''; ?>">
        <div class="tab-header"><h2>👥 User Management</h2></div>
        <div class="tab-body">
          <form method="POST" action="" style="margin-bottom:40px;">
            <input type="hidden" name="tab" value="users">
            <h3>➕ Add New User</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required>
              </div>
              <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
              </div>
              <div class="form-group">
                <label>Role *</label>
                <select name="role_id" required>
                  <option value="1">Admin</option>
                  <option value="2" selected>Sales</option>
                </select>
              </div>
            </div>
            <button type="submit" name="add_user" class="btn-save">➕ Add User</button>
          </form>
          
          <h3>Existing Users (<?php echo $users_result->num_rows; ?>)</h3>
          <table class="sales-table">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if ($users_result && $users_result->num_rows > 0): ?>
                <?php while ($user = $users_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo $user['user_id']; ?></td>
                  <td><?php echo htmlspecialchars($user['username']); ?></td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td><span class="badge <?php echo $user['role_id'] == 1 ? 'badge-admin' : 'badge-sales'; ?>"><?php echo $user['role_id'] == 1 ? 'Admin' : 'Sales'; ?></span></td>
                  <td><span class="badge <?php echo $user['status'] == 'Active' ? 'badge-sales' : 'badge-inactive'; ?>"><?php echo $user['status']; ?></span></td>
                  <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                  <td>
                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                      <a href="?tab=users&delete_user=<?php echo $user['user_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this user?')">🗑️</a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
function switchTab(tabName) {
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tabName).classList.add('active');
  event.target.classList.add('active');
}

// Access Control Functions
function updateCardStyle(checkbox) {
  const card = checkbox.closest('.access-card');
  const label = card.querySelector('.toggle-label');
  const icon = card.querySelector('.page-icon');
  
  if (checkbox.checked) {
    card.classList.remove('disabled');
    card.classList.add('enabled');
    label.textContent = 'Enabled';
    icon.textContent = '✅';
  } else {
    card.classList.remove('enabled');
    card.classList.add('disabled');
    label.textContent = 'Disabled';
    icon.textContent = '❌';
  }
}

function enableAll() {
  document.querySelectorAll('.access-card input[type="checkbox"]').forEach(cb => {
    cb.checked = true;
    updateCardStyle(cb);
  });
}

function disableAll() {
  document.querySelectorAll('.access-card input[type="checkbox"]').forEach(cb => {
    cb.checked = false;
    updateCardStyle(cb);
  });
}

function resetForm() {
  document.querySelectorAll('.access-card input[type="checkbox"]').forEach(cb => {
    // Reset to original state would require page reload, 
    // so we'll just visually update based on current checked state
    updateCardStyle(cb);
  });
}
</script>


</body>
</html>
