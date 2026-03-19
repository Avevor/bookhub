<?php
// Include session config and check
include('../includes/session_config.php');
include('../includes/session_check.php');

// Require valid session - redirects to login if expired
requireValidSession();

include('../config/db.php');

// ✅ Restrict non-admins
if ($_SESSION['role_id'] != 1) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}

// Optional: Get user details
$username = $_SESSION['username'];

// Get counts for dashboard cards
$books_result = $conn->query("SELECT COUNT(*) as count FROM books");
$books_count = $books_result->fetch_assoc()['count'];
$books_result->free();

$inventory_result = $conn->query("SELECT SUM(quantity) as count FROM inventory");
$inventory_count = $inventory_result->fetch_assoc()['count'];
$inventory_result->free();

$payments_result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = CURDATE()");
$payments_count = $payments_result->fetch_assoc()['count'];
$payments_result->free();

$suppliers_result = $conn->query("SELECT COUNT(*) as count FROM suppliers");
$suppliers_count = $suppliers_result->fetch_assoc()['count'];
$suppliers_result->free();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Book Hub - Admin Dashboard</title>
  <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
</head>
<body>

  <?php include('../includes/header.php'); ?>
  <?php include('../includes/sidebar.php'); ?>

  <div class="dashboard-container">
    <div class="main-content">
      <h1>
        <img src="../images/adminicon.jpg" alt="admin Logo" class="dashboard-title-logo">
        Admin Dashboard
      </h1>
      <p>Welcome back, <strong><?php echo htmlspecialchars($username); ?></strong> 👋</p>

      <div class="card-container">
<a href="manage_books.php" class="card-link">
          <div class="card">
            <div class="card-content">
              <div class="card-icon">📚</div>
              <div class="card-text">
                <h3><?php echo $books_count; ?></h3>
                <p>Books</p>
              </div>
            </div>
          </div>
        </a>
        <a href="manage_inventory.php" class="card-link">
          <div class="card">
            <div class="card-content">
              <div class="card-icon">📦</div>
              <div class="card-text">
                <h3><?php echo $inventory_count; ?></h3>
                <p>Total Stock</p>
              </div>
            </div>
          </div>
        </a>
        <a href="manage_payments.php" class="card-link">
          <div class="card">
            <div class="card-content">
              <div class="card-icon">💰</div>
              <div class="card-text">
                <h3><?php echo $payments_count; ?></h3>
                <p>Today's Sales</p>
              </div>
            </div>
          </div>
        </a>
        <a href="manage_suppliers.php" class="card-link">
          <div class="card">
            <div class="card-content">
              <div class="card-icon">🏢</div>
              <div class="card-text">
                <h3><?php echo $suppliers_count; ?></h3>
                <p>Suppliers</p>
              </div>
            </div>
          </div>
        </a>
        <a href="view_sales.php" class="card-link">
          <div class="card">
            <div class="card-content">
              <div class="card-icon">📊</div>
              <div class="card-text">
                <h3>--</h3>
                <p>Reports</p>
              </div>
            </div>
          </div>
        </a>
        <a href="manage_settings.php" class="card-link">
          <div class="card">
            <div class="card-content">
              <div class="card-icon">⚙️</div>
              <div class="card-text">
                <h3>--</h3>
                <p>Settings</p>
              </div>
            </div>
          </div>
        </a>
      </div>
    </div>
  </div>

  <?php include('../includes/footer.php'); ?>

</body>
</html>
