<?php
// Include session config and check
include('../includes/session_config.php');
include('../includes/session_check.php');

// Require valid session - redirects to login if expired
requireValidSession();

include('../config/db.php');

// Check access - allow only Sales role (role_id = 2) and Admin (role_id = 1)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get stats
$today = date('Y-m-d');
$total_books = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
$today_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM payments WHERE DATE(payment_date) = '$today'")->fetch_assoc()['total'];
$total_sales_today = $conn->query("SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = '$today'")->fetch_assoc()['count'];
$low_stock = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 10")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales Dashboard - Book Hub</title>
  <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
  <style>
    .page-header {
      margin-bottom: 30px;
      border-bottom: 2px solid #e0e0e0;
      padding-bottom: 20px;
    }
    .page-header h1 {
      color: #003366;
      margin: 0 0 10px 0;
      font-size: 2.2em;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .dashboard-title-logo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
    }
    .page-subtitle {
      color: #666;
      margin: 0;
      font-size: 1.1em;
    }
    .welcome-banner {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
      padding: 25px;
      border-radius: 12px;
      margin-bottom: 30px;
    }
    .welcome-banner h2 {
      margin: 0 0 10px 0;
    }
    .card-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .card-link {
      text-decoration: none;
      color: inherit;
      display: block;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
      text-align: center;
      transition: transform 0.2s;
      cursor: pointer;
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    .card-content {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 15px;
      width: 100%;
    }
    .card-icon {
      font-size: 2.5em;
      opacity: 0.8;
    }
    .card-text {
      text-align: left;
    }
    .card-text h3 {
      margin: 0 0 5px 0;
      font-size: 2em;
      font-weight: bold;
      color: #003366;
    }
    .card-text p {
      margin: 0;
      color: #666;
      font-size: 1.1em;
      font-weight: 500;
    }
    .quick-actions {
      margin-top: 30px;
    }
    .quick-actions h2 {
      color: #003366;
      margin-bottom: 20px;
    }
    .action-buttons {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    .action-btn {
      padding: 15px 30px;
      border: none;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
    }
    .action-btn:hover {
      transform: translateY(-2px);
    }
    .action-btn.sell {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
    }
    .action-btn.books {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
    }
    .action-btn.add {
      background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
      color: white;
    }
    .action-btn.sales {
      background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
      color: white;
    }
  </style>
</head>
<body>

<?php include('../includes/header.php'); ?>
<?php include('../includes/sidebar.php'); ?>

<div class="dashboard-container">
  <div class="main-content">
    
    <div class="page-header">
      <h1>
        <img src="../images/user.png" alt="sales user logo" class="dashboard-title-logo">
        Sales User Dashboard
      </h1>
      <p class="page-subtitle">Welcome back, <strong><?php echo htmlspecialchars($username); ?></strong> 👋</p>
    </div>

    <div class="welcome-banner">
      <h2>📊 Today's Overview</h2>
      <p>Here's what's happening with your bookshop today.</p>
    </div>

    <div class="card-container">
      <a href="sales_manage_books.php" class="card-link">
        <div class="card">
          <div class="card-content">
            <div class="card-icon">📚</div>
            <div class="card-text">
              <h3><?php echo $total_books; ?></h3>
              <p>Total Books</p>
            </div>
          </div>
        </div>
      </a>
      <a href="sales_view_sales.php" class="card-link">
        <div class="card">
          <div class="card-content">
            <div class="card-icon">💰</div>
            <div class="card-text">
              <h3>$<?php echo number_format($today_sales, 2); ?></h3>
              <p>Today's Sales</p>
            </div>
          </div>
        </div>
      </a>
      <div class="card">
        <div class="card-content">
          <div class="card-icon">🧾</div>
          <div class="card-text">
            <h3><?php echo $total_sales_today; ?></h3>
            <p>Transactions Today</p>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-content">
          <div class="card-icon">⚠️</div>
          <div class="card-text">
            <h3><?php echo $low_stock; ?></h3>
            <p>Low Stock Items</p>
          </div>
        </div>
      </div>
    </div>

    <div class="quick-actions">
      <h2>⚡ Quick Actions</h2>
      <div class="action-buttons">
        <a href="sales_sell_books.php" class="action-btn sell">💰 Sell Books</a>
        <a href="sales_add_book.php" class="action-btn add">➕ Add Book</a>
        <a href="sales_manage_books.php" class="action-btn books">📚 View Books</a>
        <a href="sales_view_sales.php" class="action-btn sales">🧾 View Sales</a>
      </div>
    </div>

  </div>
</div>

<?php include('../includes/footer.php'); ?>

</body>
</html>
