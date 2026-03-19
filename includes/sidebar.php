<?php
// Check if user is logged in and get their role
$user_role_id = $_SESSION['role_id'] ?? 0;

// Include permission helper functions for consistent access control
// This ensures the sidebar uses the same access logic as the rest of the application
if (file_exists(__DIR__ . '/permission_helper.php')) {
    include_once __DIR__ . '/permission_helper.php';
}

// Get current page name
$current_url = $_SERVER['REQUEST_URI'];
$books_active = false;
$inventory_active = false;
$payments_active = false;
$suppliers_active = false;

if (strpos($current_url, 'manage_books.php') !== false ||
    strpos($current_url, 'add_book.php') !== false ||
    strpos($current_url, 'sell_books.php') !== false) {
    $books_active = true;
}

if (strpos($current_url, 'manage_inventory.php') !== false) {
    $inventory_active = true;
}

if (strpos($current_url, 'manage_payments.php') !== false ||
    strpos($current_url, 'view_sales.php') !== false) {
    $payments_active = true;
}

if (strpos($current_url, 'manage_suppliers.php') !== false) {
    $suppliers_active = true;
}

// Check page access permissions using the permission helper function
// This properly filters by access_type='page' to get correct page-level permissions
$can_access_dashboard = has_page_access($conn, $user_role_id, 'admin_dashboard');
$can_access_books = has_page_access($conn, $user_role_id, 'manage_books');
$can_access_inventory = has_page_access($conn, $user_role_id, 'manage_inventory');
$can_access_payments = has_page_access($conn, $user_role_id, 'view_sales');
$can_access_suppliers = has_page_access($conn, $user_role_id, 'manage_suppliers');
$can_access_settings = has_page_access($conn, $user_role_id, 'manage_settings');

// Determine base URL based on user role (Sales = sales-user folder, Admin = admin folder)
$base_url = ($user_role_id == 2) ? '/bookhub/sales-user/' : '/bookhub/admin/';

// Determine file prefix based on user role (Sales = sales_ prefix, Admin = no prefix)
$file_prefix = ($user_role_id == 2) ? 'sales_' : '';
?>


<button id="sidebar-toggle">☰</button>
<div class="sidebar" id="sidebar">
  <div class="sidebar-header"></div>
  <ul>
    <?php if ($can_access_dashboard): ?>
    <?php 
      // Admin dashboard is named admin_dashboard.php, sales is sales_dashboard.php
      $dashboard_file = ($user_role_id == 1) ? 'admin_dashboard.php' : 'sales_dashboard.php';
    ?>
    <li><a href="<?php echo $base_url . $dashboard_file; ?>" class="<?php echo (strpos($current_url, 'dashboard.php') !== false) ? 'active' : ''; ?>">🏠 Dashboard</a></li>
    <?php endif; ?>



    <?php if ($can_access_books): ?>
    <li>
      <a href="#" onclick="toggleSubmenu('books'); return false;" class="<?php echo $books_active ? 'active' : ''; ?>">📚 Books</a>
      <ul id="books-submenu" class="submenu" style="display:<?php echo $books_active ? 'block' : 'none'; ?>; list-style:none; padding-left:20px;">
        <li><a href="<?php echo $base_url . $file_prefix; ?>add_book.php" class="<?php echo (strpos($current_url, 'add_book.php') !== false) ? 'active' : ''; ?>">➕ Add Book</a></li>
        <li><a href="<?php echo $base_url . $file_prefix; ?>manage_books.php" class="<?php echo (strpos($current_url, 'manage_books.php') !== false) ? 'active' : ''; ?>">📋 Manage Books</a></li>
        <li><a href="<?php echo $base_url . $file_prefix; ?>sell_books.php" class="<?php echo (strpos($current_url, 'sell_books.php') !== false) ? 'active' : ''; ?>">💰 Sell Books</a></li>

      </ul>
    </li>
    <?php endif; ?>

    <?php if ($can_access_inventory): ?>
    <li><a href="/bookhub/admin/manage_inventory.php" class="<?php echo $inventory_active ? 'active' : ''; ?>">📦 Inventory</a></li>
    <?php endif; ?>

    <?php if ($can_access_payments): ?>
    <li>
      <a href="#" onclick="toggleSubmenu('payments'); return false;" class="<?php echo $payments_active ? 'active' : ''; ?>">💰 Sales & Payments</a>
      <ul id="payments-submenu" class="submenu" style="display:<?php echo $payments_active ? 'block' : 'none'; ?>; list-style:none; padding-left:20px;">
        <li><a href="<?php echo $base_url . $file_prefix; ?>view_sales.php" class="<?php echo (strpos($current_url, 'view_sales.php') !== false) ? 'active' : ''; ?>">📊 View Sales</a></li>

      </ul>
    </li>
    <?php endif; ?>

    <?php if ($can_access_suppliers): ?>
    <li><a href="/bookhub/admin/manage_suppliers.php" class="<?php echo $suppliers_active ? 'active' : ''; ?>">🏢 Suppliers</a></li>
    <?php endif; ?>

    <?php if ($can_access_settings): ?>
    <li><a href="/bookhub/admin/manage_settings.php" class="<?php echo (strpos($current_url, 'manage_settings.php') !== false) ? 'active' : ''; ?>">⚙️ Settings</a></li>
    <?php endif; ?>
  </ul>
</div>

<script>
function toggleSubmenu(id) {
  const submenu = document.getElementById(id + '-submenu');
  submenu.style.display = submenu.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  const mainContent = document.querySelector('.main-content');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      
      // Toggle button stays visible, adjust main content margin
      if (mainContent) {
        if (sidebar.classList.contains('collapsed')) {
          // Collapsed: small margin for toggle button visibility
          mainContent.style.marginLeft = '60px';
        } else {
          // Expanded: full sidebar width
          mainContent.style.marginLeft = '250px';
        }
      }
    });
  }
});
</script>
