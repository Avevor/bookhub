<?php
session_start();
include('../config/db.php');
include('../includes/permission_helper.php');

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_inventory() {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ✅ Restrict access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// ✅ Restrict non-admins and non-sales users
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}

$user_role_id = $_SESSION['role_id'];

// Check if user has access to manage_inventory page
if (!has_page_access($conn, $user_role_id, 'manage_inventory')) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    echo "<p style='text-align:center;'>You don't have permission to access this page.</p>";
    exit();
}

// Get accessible tabs for sales users
$accessible_tabs = [];
if ($user_role_id != 1) {
    // Sales user - check each tab permission individually
    if (has_tab_access($conn, $user_role_id, 'manage_inventory', 'inventory')) {
        $accessible_tabs[] = 'inventory';
    }
    if (has_tab_access($conn, $user_role_id, 'manage_inventory', 'add-stock')) {
        $accessible_tabs[] = 'add-stock';
    }
    if (has_tab_access($conn, $user_role_id, 'manage_inventory', 'history')) {
        $accessible_tabs[] = 'history';
    }
} else {
    // Admin has access to all tabs
    $accessible_tabs = ['inventory', 'add-stock', 'history'];
}


// Get requested tab
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'inventory';

// Validate active_tab to prevent XSS and check permissions
if (!in_array($active_tab, $accessible_tabs)) {
    // Redirect to first accessible tab
    if (!empty($accessible_tabs)) {
        $active_tab = $accessible_tabs[0];
    } else {
        echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
        echo "<p style='text-align:center;'>You don't have permission to view any tabs on this page.</p>";
        exit();
    }
}

$username = $_SESSION['username'];
$message = '';
$message_type = '';

// Handle add stock (new shipment)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock'])) {
    // Check if user has access to add-stock tab
    if (!in_array('add-stock', $accessible_tabs)) {
        $message = "You don't have permission to add stock.";
        $message_type = "error";
    } else {
        $book_id = intval($_POST['book_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        
        if ($book_id && $quantity > 0) {
            // Check if inventory record exists
            $check_sql = "SELECT inventory_id, quantity FROM inventory WHERE book_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $book_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing inventory
                $update_sql = "UPDATE inventory SET quantity = quantity + ?, last_updated = NOW() WHERE book_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $quantity, $book_id);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                // Insert new inventory record
                $insert_sql = "INSERT INTO inventory (book_id, quantity) VALUES (?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ii", $book_id, $quantity);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
            
            // Log the inventory history
            $history_sql = "INSERT INTO inventory_history (book_id, quantity_change, notes, updated_by) VALUES (?, ?, ?, ?)";
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param("iisi", $book_id, $quantity, $notes, $username);
            $history_stmt->execute();
            $history_stmt->close();
            
            $message = "Stock added successfully! Added $quantity units.";
            $message_type = "success";
        } else {
            $message = "Please select a book and enter a valid quantity.";
            $message_type = "error";
        }
    }
}

// Handle remove stock (POST only now)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_stock' && verify_csrf_inventory() && is_numeric($_POST['book_id']) && is_numeric($_POST['qty'])) {
    // Check if user has access to inventory tab
    if (!in_array('inventory', $accessible_tabs)) {
        $message = "You don't have permission to remove stock.";
        $message_type = "error";
    } else {
        $book_id = intval($_POST['book_id']);
        $quantity = intval($_POST['qty']);
        
        if ($book_id && $quantity > 0) {
            // Check current quantity
            $check_sql = "SELECT quantity FROM inventory WHERE book_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $book_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $current_qty = $check_result->fetch_assoc()['quantity'];
                if ($current_qty >= $quantity) {
                    $update_sql = "UPDATE inventory SET quantity = quantity - ?, last_updated = NOW() WHERE book_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $quantity, $book_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Log the removal
                    $remove_qty = -$quantity;
                    $history_sql = "INSERT INTO inventory_history (book_id, quantity_change, notes, updated_by) VALUES (?, ?, ?, ?)";
                    $history_stmt = $conn->prepare($history_sql);
                    $notes = "Stock removed";
                    $history_stmt->bind_param("iisi", $book_id, $remove_qty, $notes, $_SESSION['user_id']);
                    $history_stmt->execute();
                    $history_stmt->close();
                    
                    $message = "Removed $quantity unit" . ($quantity > 1 ? 's' : '') . " from stock successfully!";
                    $message_type = "success";
                } else {
                    $message = "Cannot remove more stock than available ($current_qty available).";
                    $message_type = "error";
                }
            }
            $check_stmt->close();
        }
    }
} elseif (isset($_GET['remove_stock'])) {
    // Legacy info
    $message = "Stock removal updated to POST form. Use the new remove buttons.";
    $message_type = "info";
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

// Pre-selected book from URL
$pre_selected_book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;

// Build the query
$query = "SELECT b.book_id, b.title, b.author, b.category, b.grade, b.price,
          COALESCE(i.quantity, 0) as quantity, 
          COALESCE(i.last_updated, b.created_at) as last_updated
          FROM books b 
          LEFT JOIN inventory i ON b.book_id = i.book_id 
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%' OR b.category LIKE '%$search%')";
}

if ($filter == 'low_stock') {
    $query .= " AND COALESCE(i.quantity, 0) > 0 AND COALESCE(i.quantity, 0) < 10";
} elseif ($filter == 'out_of_stock') {
    $query .= " AND COALESCE(i.quantity, 0) = 0";
} elseif ($filter == 'in_stock') {
    $query .= " AND COALESCE(i.quantity, 0) > 0";
}

$query .= " ORDER BY i.quantity ASC, b.title ASC";

$result = $conn->query($query);

// Get statistics
$total_stock = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM inventory")->fetch_assoc()['total'];
$total_books = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity > 0 AND quantity < 10")->fetch_assoc()['count'];
$out_of_stock_count = $conn->query("SELECT COUNT(*) as count FROM books WHERE book_id NOT IN (SELECT book_id FROM inventory WHERE quantity > 0)")->fetch_assoc()['count'];
$out_of_stock_count += $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity = 0")->fetch_assoc()['count'];

// Get all books for the dropdown
$books_sql = "SELECT b.book_id, b.title, b.author, COALESCE(i.quantity, 0) as quantity 
              FROM books b 
              LEFT JOIN inventory i ON b.book_id = i.book_id 
              ORDER BY b.title ASC";
$books_result = $conn->query($books_sql);

// Check if inventory_history table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'inventory_history'");
if ($table_check->num_rows == 0) {
    $create_history_table = "CREATE TABLE IF NOT EXISTS inventory_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        quantity_change INT NOT NULL,
        notes TEXT,
        updated_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
    )";
    $conn->query($create_history_table);
}

// Get recent inventory history (only if user has access to history tab)
$history_result = null;
if (in_array('history', $accessible_tabs)) {
    $history_sql = "SELECT h.*, b.title, b.author 
                    FROM inventory_history h 
                    JOIN books b ON h.book_id = b.book_id 
                    ORDER BY h.created_at DESC 
                    LIMIT 20";
    $history_result = $conn->query($history_sql);
}

// Get currency setting
$currency_setting = 'USD';
$currency_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
if ($currency_result && $currency_result->num_rows > 0) {
    $currency_setting = $currency_result->fetch_assoc()['setting_value'];
}

// Currency symbol mapping
$currency_symbols = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'GHS' => '₵'
];
$currency_symbol = $currency_symbols[$currency_setting] ?? '$';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Inventory - Book Hub</title>
  <link rel="icon" href="../images/school.jpeg" type="image/jpeg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
  <style>
    .page-header { margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 20px; }
    .page-header h1 { color: #003366; margin: 0 0 10px 0; font-size: 2.2em; display: flex; align-items: center; gap: 15px; }
    .dashboard-title-logo { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
    .page-subtitle { color: #666; margin: 0; font-size: 1.1em; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); display: flex; align-items: center; }
    .stat-card.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stat-card.books { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
    .stat-card.low-stock { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    .stat-card.out-of-stock { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
    .stat-icon { font-size: 2.5em; margin-right: 20px; }
    .stat-content h3 { margin: 0 0 5px 0; font-size: 2em; font-weight: bold; }
    .stat-content p { margin: 0; opacity: 0.9; }
    .content-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 25px; }
    @media (max-width: 1200px) { .content-grid { grid-template-columns: 1fr; } }
    .form-section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); height: fit-content; }
    .form-section h2 { margin: 0 0 20px 0; color: #003366; font-size: 1.4em; display: flex; align-items: center; gap: 10px; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 0.95em; }
    .form-group select, .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box; transition: border-color 0.3s ease; }
    .form-group select:focus, .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #003366; }
    .form-group textarea { resize: vertical; min-height: 60px; }
    .btn-submit { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 14px 30px; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; width: 100%; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); }
    .filter-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); margin-bottom: 25px; }
    .filter-form { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
    .filter-group { flex: 1; min-width: 200px; }
    .filter-group label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; }
    .filter-group input, .filter-group select { width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box; }
    .btn-filter { background: linear-gradient(135deg, #003366 0%, #005580 100%); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .btn-reset { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
    .table-section { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden; }
    .table-header { background: linear-gradient(135deg, #003366 0%, #005580 100%); color: white; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    .table-header h2 { margin: 0; font-size: 1.5em; }
.table-container {
  overflow-x: auto;
  max-height: 500px;
  overflow-y: auto;
}
/* Custom scrollbar for table container */
.table-container::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.table-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}
.table-container::-webkit-scrollbar-thumb {
  background: #003366;
  border-radius: 4px;
}
.table-container::-webkit-scrollbar-thumb:hover {
  background: #005580;
}

.inventory-table th,
.history-table th {
  position: sticky;
  top: 0;
  background: #f8f9fa;
  z-index: 10;
}
    .inventory-table { width: 100%; border-collapse: collapse; font-size: 0.95em; }
    .inventory-table thead { background: #f8f9fa; }
    .inventory-table th { padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #e0e0e0; white-space: nowrap; }
    .inventory-table td { padding: 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle; }
    .inventory-table tbody tr:hover { background: #f8f9fa; }
    .book-title { font-weight: 600; color: #003366; }
    .book-author { color: #666; font-size: 0.9em; }
    .category-badge { display: inline-block; padding: 5px 12px; background: #e3f2fd; color: #003366; border-radius: 20px; font-size: 0.85em; font-weight: 500; }
    .stock-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.9em; font-weight: 600; }
    .stock-badge.in-stock { background: #d4edda; color: #155724; }
    .stock-badge.low-stock { background: #fff3cd; color: #856404; }
    .stock-badge.out-of-stock { background: #f8d7da; color: #721c24; }
    .price { font-weight: 600; color: #28a745; }
    .action-btns { display: flex; gap: 8px; }
    .btn-action { padding: 6px 12px; border: none; border-radius: 6px; font-size: 0.85em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
    .btn-add-stock { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
    .btn-remove-stock { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
    .empty-state { text-align: center; padding: 60px 20px; color: #666; }
    .empty-state-icon { font-size: 4em; margin-bottom: 20px; opacity: 0.5; }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    /* Modal Styles (same as manage_books) */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(5px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      animation: fadeIn 0.2s ease;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .modal-content {
      background: white;
      border-radius: 12px;
      padding: 30px;
      max-width: 450px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      transform: scale(0.8);
      animation: modalSlide 0.3s ease forwards;
      position: relative;
    }
    @keyframes modalSlide { to { transform: scale(1); } }
    .modal-header { font-size: 1.4em; font-weight: 600; color: #003366; margin-bottom: 15px; text-align: center; }
    .modal-body { color: #333; line-height: 1.5; margin-bottom: 25px; }
    .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; }
    .btn-modal-cancel { background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .btn-modal-cancel:hover { background: #5a6268; transform: translateY(-2px); }
    .btn-modal-confirm { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .btn-modal-confirm:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4); }
    .modal-close { position: absolute; top: 15px; right: 20px; font-size: 1.8em; color: #999; cursor: pointer; transition: color 0.3s ease; }
    .modal-close:hover { color: #dc3545; }
    .qty-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .qty-option { flex: 1; min-width: 80px; }
    .alert-section { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px 20px; margin-bottom: 25px; }
    .history-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
    .history-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #e0e0e0; }
    .history-table td { padding: 12px; border-bottom: 1px solid #e0e0e0; }
    .history-table .positive { color: #28a745; font-weight: 600; }
    .history-table .negative { color: #dc3545; font-weight: 600; }
    .tab-buttons { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .tab-btn { padding: 10px 20px; border: 2px solid #e0e0e0; background: white; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; color: #666; }
    .tab-btn:hover { border-color: #003366; color: #003366; }
    .tab-btn.active { background: #003366; color: white; border-color: #003366; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
  </style>
</head>
<body>

  <?php include('../includes/header.php'); ?>
  <?php include('../includes/sidebar.php'); ?>

  <div class="dashboard-container">
    <div class="main-content">
      
      <div class="page-header">
        <h1>
          <img src="../images/inventory.jpg" alt="inventory Logo" class="dashboard-title-logo">
          Manage Inventory
        </h1>
        <p class="page-subtitle">Track stock levels, add new shipments, and manage inventory</p>
      </div>

      <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <!-- Stats Overview -->
      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-icon">📦</div>
          <div class="stat-content">
            <h3><?php echo number_format($total_stock); ?></h3>
            <p>Total Stock</p>
          </div>
        </div>
        <div class="stat-card books">
          <div class="stat-icon">📚</div>
          <div class="stat-content">
            <h3><?php echo $total_books; ?></h3>
            <p>Total Books</p>
          </div>
        </div>
        <div class="stat-card low-stock">
          <div class="stat-icon">⚠️</div>
          <div class="stat-content">
            <h3><?php echo $low_stock_count; ?></h3>
            <p>Low Stock</p>
          </div>
        </div>
        <div class="stat-card out-of-stock">
          <div class="stat-icon">❌</div>
          <div class="stat-content">
            <h3><?php echo $out_of_stock_count; ?></h3>
            <p>Out of Stock</p>
          </div>
        </div>
      </div>

      <!-- Low Stock Alert -->
      <?php if ($low_stock_count > 0 || $out_of_stock_count > 0): ?>
        <div class="alert-section">
          <h3>⚠️ Inventory Alerts</h3>
          <ul class="alert-list">
            <?php if ($out_of_stock_count > 0): ?>
              <li><?php echo $out_of_stock_count; ?> book(s) are out of stock</li>
            <?php endif; ?>
            <?php if ($low_stock_count > 0): ?>
              <li><?php echo $low_stock_count; ?> book(s) have low stock (less than 10 units)</li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Tab Navigation - Only show tabs user has access to -->
      <div class="tab-buttons">
        <?php if (in_array('inventory', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>" onclick="switchTab('inventory')">📋 Inventory List</button>
        <?php endif; ?>
        <?php if (in_array('add-stock', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'add-stock' ? 'active' : ''; ?>" onclick="switchTab('add-stock')">➕ Add Stock</button>
        <?php endif; ?>
        <?php if (in_array('history', $accessible_tabs)): ?>
        <button class="tab-btn <?php echo $active_tab == 'history' ? 'active' : ''; ?>" onclick="switchTab('history')">📜 History</button>
        <?php endif; ?>
      </div>

      <!-- Inventory List Tab -->
      <?php if (in_array('inventory', $accessible_tabs)): ?>
      <div id="inventory" class="tab-content <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>">
        <div class="filter-section">
          <form method="GET" action="" class="filter-form">
            <input type="hidden" name="tab" value="inventory">
            <div class="filter-group">
              <label for="search">🔍 Search</label>
              <input type="text" id="search" name="search" placeholder="Search by title, author, or category" 
                     value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
              <label for="filter">📂 Filter</label>
              <select id="filter" name="filter">
                <option value="">All Books</option>
                <option value="in_stock" <?php echo $filter == 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low_stock" <?php echo $filter == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out_of_stock" <?php echo $filter == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
              </select>
            </div>
            <button type="submit" class="btn-filter">🔎 Filter</button>
            <a href="manage_inventory.php" class="btn-reset">↺ Reset</a>
          </form>
        </div>

        <div class="table-section">
          <div class="table-header">
            <h2>📋 Current Inventory</h2>
            <span style="opacity: 0.9;"><?php echo $result->num_rows; ?> book(s)</span>
          </div>
          
          <?php if ($result->num_rows > 0): ?>
            <div class="table-container">
              <table class="inventory-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Book</th>
                    <th>Category</th>
                    <th>Grade</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $counter = 1; while ($item = $result->fetch_assoc()): 
                    $qty = intval($item['quantity']);
                    $stock_class = 'in-stock';
                    if ($qty == 0) {
                      $stock_class = 'out-of-stock';
                    } elseif ($qty < 10) {
                      $stock_class = 'low-stock';
                    }
                  ?>
                    <tr>
                      <td><?php echo $counter++; ?></td>
                      <td>
                        <div class="book-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="book-author"><?php echo htmlspecialchars($item['author'] ?: '-'); ?></div>
                      </td>
                      <td>
                        <?php if ($item['category']): ?>
                          <span class="category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                        <?php else: ?>
                          -
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($item['grade'] ?: '-'); ?></td>
<td class="price"><?php echo $currency_symbol; ?><?php echo number_format($item['price'], 2); ?></td>
                      <td>
                        <span class="stock-badge <?php echo $stock_class; ?>">
                          <?php echo $qty; ?>
                        </span>
                      </td>
                      <td><?php echo date('M d, Y', strtotime($item['last_updated'])); ?></td>
                      <td>
                        <div class="action-btns">
                          <?php if (in_array('add-stock', $accessible_tabs)): ?>
                          <a href="?tab=add-stock&book_id=<?php echo $item['book_id']; ?>" class="btn-action btn-add-stock">➕ Add</a>
                          <?php endif; ?>
                          <?php if ($qty > 0): ?>
                            <button class="btn-action btn-remove-stock remove-stock-btn" data-id="<?php echo $item['book_id']; ?>" data-qty="<?php echo $qty; ?>" data-title="<?php echo htmlspecialchars($item['title']); ?>">➖ Remove</button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">📦</div>
              <h3>No Inventory Found</h3>
              <p>No books match your filter criteria.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Add Stock Tab -->
      <?php if (in_array('add-stock', $accessible_tabs)): ?>
      <div id="add-stock" class="tab-content <?php echo $active_tab == 'add-stock' ? 'active' : ''; ?>">
        <div class="content-grid">
          <div class="form-section">
            <h2>➕ Add Stock (New Shipment)</h2>
            <form method="POST" action="">
              <input type="hidden" name="add_stock" value="1">
              
              <div class="form-group">
                <label for="book_id">Select Book *</label>
                <select id="book_id" name="book_id" required onchange="updateCurrentStock(this)">
                  <option value="">-- Select a Book --</option>
                  <?php 
                  // Reset the books result pointer
                  $books_result->data_seek(0);
                  while ($book = $books_result->fetch_assoc()): 
                  ?>
                    <option value="<?php echo $book['book_id']; ?>" data-qty="<?php echo $book['quantity']; ?>">
                      <?php echo htmlspecialchars($book['title']); ?> (Current: <?php echo $book['quantity']; ?>)
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="quantity">Quantity to Add *</label>
                <input type="number" id="quantity" name="quantity" min="1" placeholder="Enter quantity" required>
              </div>
              
              <div class="form-group">
                <label for="notes">Notes (Optional)</label>
                <textarea id="notes" name="notes" placeholder="e.g., New shipment, Returned items"></textarea>
              </div>
              
              <button type="submit" class="btn-submit">➕ Add Stock</button>
            </form>
          </div>

          <div class="table-section">
            <div class="table-header">
              <h2>📚 All Books</h2>
            </div>
            <div class="table-container">
              <table class="inventory-table">
                <thead>
                  <tr>
                    <th>Book</th>
                    <th>Current Stock</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $books_result->data_seek(0);
                  while ($book = $books_result->fetch_assoc()): 
                  ?>
                    <tr>
                      <td>
                        <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                      </td>
                      <td>
                        <?php 
                        $qty = intval($book['quantity']);
                        $stock_class = $qty > 0 ? 'in-stock' : 'out-of-stock';
                        ?>
                        <span class="stock-badge <?php echo $stock_class; ?>"><?php echo $qty; ?></span>
                      </td>
                      <td>
                        <button type="button" class="btn-action btn-add-stock" onclick="selectBook(<?php echo $book['book_id']; ?>)">
                          Select
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- History Tab -->
      <?php if (in_array('history', $accessible_tabs)): ?>
      <div id="history" class="tab-content <?php echo $active_tab == 'history' ? 'active' : ''; ?>">
        <div class="table-section">
          <div class="table-header">
            <h2>📜 Inventory History</h2>
          </div>
          <?php if ($history_result && $history_result->num_rows > 0): ?>
            <div class="table-container">
              <table class="history-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Book</th>
                    <th>Change</th>
                    <th>Notes</th>
                    <th>Updated By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($history = $history_result->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo date('M d, Y H:i', strtotime($history['created_at'])); ?></td>
                      <td>
                        <div class="book-title"><?php echo htmlspecialchars($history['title']); ?></div>
                      </td>
                      <td class="<?php echo $history['quantity_change'] > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $history['quantity_change'] > 0 ? '+' : ''; ?><?php echo $history['quantity_change']; ?>
                      </td>
                      <td><?php echo htmlspecialchars($history['notes'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($history['updated_by'] ?: '-'); ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">📜</div>
              <h3>No History Yet</h3>
              <p>Inventory changes will be logged here.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <?php include('../includes/footer.php'); ?>

  <!-- Remove Stock Modal -->
  <div id="removeStockModal" class="modal-overlay">
    <div class="modal-content">
      <span class="modal-close">&times;</span>
      <div class="modal-header">➖ Remove Stock</div>
      <div class="modal-body">
        <p>Remove stock from <strong id="modalStockTitle"></strong></p>
        <p>Current stock: <strong id="modalCurrentQty"></strong></p>
        <div class="qty-group">
          <div class="qty-option">
            <label><input type="radio" name="remove_qty_option" value="1" checked> 1 unit</label>
          </div>
          <div class="qty-option">
            <label><input type="radio" name="remove_qty_option" value="all"> All (<span id="modalAllQty"></span>)</label>
          </div>
          <div class="qty-option">
            <label>Custom: <input type="number" id="custom_qty" min="1" max="999" style="width: 60px;"></label>
          </div>
        </div>
      </div>
      <form id="removeStockForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="remove_stock">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="book_id" id="modalStockId" value="">
        <input type="hidden" name="qty" id="modalRemoveQty" value="">
      </form>
      <div class="modal-buttons">
        <button type="button" class="btn-modal-cancel" onclick="closeStockModal()">Cancel</button>
        <button type="button" class="btn-modal-confirm" onclick="confirmRemoveStock()">Remove Stock</button>
      </div>
    </div>
  </div>

  <script>
    // Existing functions...
    function switchTab(tabName) {
      // Hide all tab contents
      const allContents = document.querySelectorAll('.tab-content');
      allContents.forEach(content => {
        content.classList.remove('active');
      });
      
      // Remove active class from all buttons
      const allButtons = document.querySelectorAll('.tab-btn');
      allButtons.forEach(btn => {
        btn.classList.remove('active');
      });
      
      // Show selected tab content
      document.getElementById(tabName).classList.add('active');
      
      // Add active class to clicked button
      event.target.classList.add('active');
    }
    
    function selectBook(bookId) {
      document.getElementById('book_id').value = bookId;
      // Switch to add-stock tab
      switchTab('add-stock');
    }
    
    function updateCurrentStock(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const qty = selectedOption.getAttribute('data-qty');
    }

    // Stock modal functions
    function showStockModal(id, title, currentQty) {
      document.getElementById('modalStockId').value = id;
      document.getElementById('modalStockTitle').textContent = title;
      document.getElementById('modalCurrentQty').textContent = currentQty;
      document.getElementById('modalAllQty').textContent = currentQty;
      document.getElementById('removeStockModal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
      // Reset form
      document.querySelector('input[name="remove_qty_option"][value="1"]').checked = true;
      document.getElementById('custom_qty').value = '';
      document.getElementById('modalRemoveQty').value = 1;
    }

    function closeStockModal() {
      document.getElementById('removeStockModal').style.display = 'none';
      document.body.style.overflow = '';
    }

    function confirmRemoveStock() {
      const allOption = document.querySelector('input[name="remove_qty_option"][value="all"]').checked;
      const customQty = document.getElementById('custom_qty').value;
      let qty = 1;
      
      if (allOption) {
        qty = parseInt(document.getElementById('modalCurrentQty').textContent);
      } else if (customQty && customQty > 0) {
        qty = parseInt(customQty);
      }
      
      if (qty > 0 && qty <= parseInt(document.getElementById('modalCurrentQty').textContent)) {
        document.getElementById('modalRemoveQty').value = qty;
        document.getElementById('removeStockForm').submit();
      } else {
        alert('Invalid quantity. Must be between 1 and current stock.');
      }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Remove stock buttons
      document.querySelectorAll('.remove-stock-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const id = this.dataset.id;
          const title = this.dataset.title;
          const qty = parseInt(this.dataset.qty);
          showStockModal(id, title, qty);
        });
      });

      // Stock modal close handlers
      const stockModal = document.getElementById('removeStockModal');
      const stockCloseBtn = document.querySelectorAll('.modal-close');
      stockCloseBtn.forEach(btn => btn.addEventListener('click', closeStockModal));
      stockModal.addEventListener('click', function(e) {
        if (e.target === stockModal) closeStockModal();
      });

      // ESC key for stock modal
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && stockModal.style.display === 'flex') {
          closeStockModal();
        }
      });

      // Qty option radio buttons
      document.querySelectorAll('input[name="remove_qty_option"]').forEach(radio => {
        radio.addEventListener('change', function() {
          if (this.value === 'all') {
            const currentQty = parseInt(document.getElementById('modalCurrentQty').textContent);
            document.getElementById('modalRemoveQty').value = currentQty;
          } else if (this.value === '1') {
            document.getElementById('modalRemoveQty').value = 1;
          }
        });
      });

      document.getElementById('custom_qty').addEventListener('input', function() {
        if (this.value) {
          document.querySelector('input[name="remove_qty_option"][value="1"]').checked = false;
          document.querySelector('input[name="remove_qty_option"][value="all"]').checked = false;
          document.getElementById('modalRemoveQty').value = this.value;
        }
      });
    });
  </script>

</body>
</html>
