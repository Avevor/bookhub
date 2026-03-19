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
    // Sales user - check page_access_settings
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'view_sales'";
    $access_result = $conn->query($access_sql);
    if ($access_result && $access_result->num_rows > 0) {
        $access_row = $access_result->fetch_assoc();
        $can_access = $access_row['sales_enabled'] == 1;
    }
}

if (!$can_access) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}


// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_method = $_GET['payment_method'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for main sales query (with books join)
$where_clauses = ["p.status = 'Completed'"];
if ($date_from) {
    $where_clauses[] = "DATE(p.payment_date) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $where_clauses[] = "DATE(p.payment_date) <= '" . $conn->real_escape_string($date_to) . "'";
}
if ($payment_method) {
    $where_clauses[] = "p.payment_method = '" . $conn->real_escape_string($payment_method) . "'";
}
if ($search) {
    $search_term = $conn->real_escape_string($search);
    $where_clauses[] = "(p.buyer_name LIKE '%$search_term%' OR b.title LIKE '%$search_term%' OR p.receipt_number LIKE '%$search_term%')";
}

$where_sql = implode(' AND ', $where_clauses);

// Build query for summary (payments only, no books join)
$summary_where_clauses = ["status = 'Completed'"];
if ($date_from) {
    $summary_where_clauses[] = "DATE(payment_date) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $summary_where_clauses[] = "DATE(payment_date) <= '" . $conn->real_escape_string($date_to) . "'";
}
if ($payment_method) {
    $summary_where_clauses[] = "payment_method = '" . $conn->real_escape_string($payment_method) . "'";
}

$summary_where_sql = implode(' AND ', $summary_where_clauses);


// Get sales data
$sales_sql = "SELECT p.*, b.title, b.author, b.grade, b.category 
              FROM payments p 
              JOIN books b ON p.book_id = b.book_id 
              WHERE $where_sql
              ORDER BY p.payment_date DESC";
$sales_result = $conn->query($sales_sql);

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(DISTINCT receipt_number) as total_transactions,
    COUNT(*) as total_items,
    SUM(quantity) as total_books,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_sale
FROM payments 
WHERE $summary_where_sql";
$summary_result = $conn->query($summary_sql);
$summary = $summary_result->fetch_assoc();

// Get payment method breakdown
$methods_sql = "SELECT 
    payment_method,
    COUNT(DISTINCT receipt_number) as count,
    SUM(total_amount) as total
FROM payments 
WHERE $summary_where_sql
GROUP BY payment_method";
$methods_result = $conn->query($methods_sql);


// Get daily sales for chart
$daily_sql = "SELECT 
    DATE(payment_date) as date,
    COUNT(DISTINCT receipt_number) as transactions,
    SUM(total_amount) as revenue
FROM payments 
WHERE $summary_where_sql
GROUP BY DATE(payment_date)
ORDER BY date DESC
LIMIT 30";
$daily_result = $conn->query($daily_sql);

$daily_data = [];
while ($row = $daily_result->fetch_assoc()) {
    $daily_data[] = $row;
}

// Get currency from settings
$currency_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'currency'";
$currency_result = $conn->query($currency_sql);
$currency = 'USD';
if ($currency_result && $currency_result->num_rows > 0) {
    $currency = $currency_result->fetch_assoc()['setting_value'];
}

// Currency symbols mapping
$currency_symbols = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'GHS' => '₵',
    'NGN' => '₦',
    'ZAR' => 'R'
];
$currency_symbol = $currency_symbols[$currency] ?? '$';
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Sales - Book Hub</title>
  <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-box {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
    }

    .stat-box .icon {
      font-size: 2.5em;
      margin-bottom: 10px;
    }

    .stat-box .number {
      font-size: 2em;
      font-weight: bold;
      color: #003366;
    }

    .stat-box .label {
      color: #666;
      font-size: 0.9em;
      margin-top: 5px;
    }

    .filter-section {
      background: white;
      padding: 25px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .filter-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      align-items: end;
    }

    .form-group {
      margin-bottom: 0;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      font-size: 0.9em;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
    }

    .btn-filter {
      background: #003366;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1em;
    }

    .btn-filter:hover {
      background: #004488;
    }

    .btn-reset {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1em;
      text-decoration: none;
      display: inline-block;
    }

    .sales-table-container {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    .sales-table-header {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
      padding: 20px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .sales-table {
      width: 100%;
      border-collapse: collapse;
    }

    .sales-table th,
    .sales-table td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #e0e0e0;
    }

    .sales-table th {
      background: #f8f9fa;
      font-weight: 600;
      color: #333;
    }

    .sales-table tbody tr:hover {
      background: #f8f9fa;
    }

    .receipt-link {
      color: #003366;
      text-decoration: none;
      font-weight: 600;
    }

    .receipt-link:hover {
      text-decoration: underline;
    }

    .badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.85em;
      font-weight: 600;
    }

    .badge-success {
      background: #d4edda;
      color: #155724;
    }

    .payment-methods {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
    }

    .method-box {
      background: white;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .method-box .method-name {
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
    }

    .method-box .method-stats {
      font-size: 0.9em;
      color: #666;
    }

    .no-results {
      text-align: center;
      padding: 50px;
      color: #666;
    }

    .btn-print {
      background: #17a2b8;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
    }

    .btn-print:hover {
      background: #138496;
    }

    @media (max-width: 768px) {
      .filter-form {
        grid-template-columns: 1fr;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    
    /* Print styles */
    @media print {
      .navbar, .sidebar, #sidebar-toggle, footer, .filter-section, .btn-print {
        display: none !important;
      }
      .dashboard-container {
        margin: 0 !important;
        padding: 0 !important;
      }
      .main-content {
        margin: 0 !important;
        padding: 10px !important;
        width: 100% !important;
      }
      .sales-table-container {
        box-shadow: none !important;
      }
      .page-header {
        margin-bottom: 15px;
      }
      .stats-grid, .payment-methods {
        margin-bottom: 15px;
      }
      .stat-box, .method-box {
        break-inside: avoid;
      }
    }
  </style>
  <script>
    function printSalesReport() {
      // Get the table
      const table = document.querySelector('.sales-table');
      
      // Clone the table to modify it for printing
      const tableClone = table.cloneNode(true);
      
      // Remove the Status column (last column) from both header and body
      const headerRow = tableClone.querySelector('thead tr');
      headerRow.deleteCell(-1); // Remove last header cell
      
      const bodyRows = tableClone.querySelectorAll('tbody tr');
      bodyRows.forEach(row => row.deleteCell(-1)); // Remove last body cell for each row
      
      // Create a new window with just the table
      const printWindow = window.open('', '_blank', 'width=800,height=600');
      
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Sales Report - Print View</title>
          <style>
            body {
              font-family: Arial, sans-serif;
              padding: 20px;
              margin: 0;
            }
            h1 {
              color: #003366;
              text-align: center;
              margin-bottom: 10px;
            }
            .print-date {
              text-align: center;
              color: #666;
              margin-bottom: 20px;
              font-size: 0.9em;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              font-size: 11px;
            }
            th, td {
              border: 1px solid #333;
              padding: 6px;
              text-align: left;
            }
            th {
              background: #f0f0f0;
              font-weight: bold;
            }
            tr:nth-child(even) {
              background: #f9f9f9;
            }
            .badge {
              padding: 2px 6px;
              border-radius: 8px;
              font-size: 0.85em;
            }
            .badge-success { background: #d4edda; color: #155724; }
            @media print {
              body { padding: 0; }
              th { background: #eee !important; -webkit-print-color-adjust: exact; }
            }
          </style>
        </head>
        <body>
          <h1>💰 Sales Report</h1>
          <p class="print-date">Generated on: ${new Date().toLocaleString()}</p>
          ${tableClone.outerHTML}
        </body>
        </html>
      `);
      
      printWindow.document.close();
      printWindow.focus();
      setTimeout(() => {
        printWindow.print();
      }, 500);
    }
  </script>
</head>
<body>

  <?php include('../includes/header.php'); ?>
  <?php include('../includes/sidebar.php'); ?>

  <div class="dashboard-container">
    <div class="main-content">
      <div class="page-header">
        <h1>
          <img src="../images/sales.png" alt="View Sales" class="dashboard-title-logo">
          View Sales
        </h1>
        <p class="page-subtitle">Sales history and reports</p>
      </div>

      <!-- Statistics -->
      <div class="stats-grid">
        <div class="stat-box">
          <div class="icon">📊</div>
          <div class="number"><?php echo number_format($summary['total_transactions'] ?? 0); ?></div>
          <div class="label">Transactions</div>
        </div>
        <div class="stat-box">
          <div class="icon">📚</div>
          <div class="number"><?php echo number_format($summary['total_books'] ?? 0); ?></div>
          <div class="label">Books Sold</div>
        </div>
        <div class="stat-box">
          <div class="icon">💰</div>
          <div class="number"><?php echo $currency_symbol; ?><?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
          <div class="label">Total Revenue</div>
        </div>
        <div class="stat-box">
          <div class="icon">📈</div>
          <div class="number"><?php echo $currency_symbol; ?><?php echo number_format($summary['avg_sale'] ?? 0, 2); ?></div>
          <div class="label">Avg. Sale</div>

        </div>
      </div>

      <!-- Payment Methods Breakdown -->
      <?php if ($methods_result && $methods_result->num_rows > 0): ?>
      <div class="payment-methods">
        <?php while ($method = $methods_result->fetch_assoc()): ?>
          <div class="method-box">
            <div class="method-name"><?php echo htmlspecialchars($method['payment_method']); ?></div>
            <div class="method-stats">
              <?php echo $method['count']; ?> sales<br>
              <?php echo $currency_symbol; ?><?php echo number_format($method['total'], 2); ?>
            </div>
          </div>

        <?php endwhile; ?>
      </div>
      <?php endif; ?>

      <!-- Filters -->
      <div class="filter-section">
        <form method="GET" action="" class="filter-form">
          <div class="form-group">
            <label>From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
          </div>
          <div class="form-group">
            <label>To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <select name="payment_method">
              <option value="">All Methods</option>
              <option value="Cash" <?php echo $payment_method == 'Cash' ? 'selected' : ''; ?>>Cash</option>
              <option value="Credit Card" <?php echo $payment_method == 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
              <option value="Debit Card" <?php echo $payment_method == 'Debit Card' ? 'selected' : ''; ?>>Debit Card</option>
              <option value="Mobile Money" <?php echo $payment_method == 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
              <option value="Bank Transfer" <?php echo $payment_method == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
            </select>
          </div>
          <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" placeholder="Buyer, book, or receipt..." value="<?php echo htmlspecialchars($search); ?>">
          </div>
          <div class="form-group">
            <button type="submit" class="btn-filter">🔍 Filter</button>
            <a href="view_sales.php" class="btn-reset">Reset</a>
          </div>
        </form>
      </div>

      <!-- Sales Table -->
      <div class="sales-table-container">
        <div class="sales-table-header">
          <h2>Sales Records</h2>
          <div style="display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 0.9em;"><?php echo $sales_result ? $sales_result->num_rows : 0; ?> records found</span>
            <button onclick="printSalesReport()" class="btn-print">🖨️ Print</button>
          </div>
        </div>
        <div class="table-container">
          <table class="sales-table">
            <thead>
              <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Buyer</th>
                <th>Book</th>
                <th>Grade</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($sales_result && $sales_result->num_rows > 0): ?>
                <?php while ($sale = $sales_result->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <a href="receipt.php?receipt=<?php echo urlencode($sale['receipt_number']); ?>" class="receipt-link">
                        <?php echo htmlspecialchars($sale['receipt_number']); ?>
                      </a>
                    </td>
                    <td><?php echo date('M d, Y H:i', strtotime($sale['payment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($sale['buyer_name']); ?></td>
                    <td><?php echo htmlspecialchars($sale['title']); ?></td>
                    <td><?php echo htmlspecialchars($sale['grade']); ?></td>
                    <td><?php echo $sale['quantity']; ?></td>
                    <td><?php echo $currency_symbol; ?><?php echo number_format($sale['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($sale['payment_method']); ?></td>

                    <td><span class="badge badge-success">Completed</span></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="9" class="no-results">
                    <h3>No sales found</h3>
                    <p>Try adjusting your filters or date range.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <?php include('../includes/footer.php'); ?>

</body>
</html>
