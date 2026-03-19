<?php
session_start();
include('../config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// Check access - allow only Sales role (role_id = 2) and Admin (role_id = 1)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}

// Get receipt number from URL - accept both 'receipt' and 'receipt_number' parameters
$receipt_number = isset($_GET['receipt_number']) ? $conn->real_escape_string($_GET['receipt_number']) : '';
if (empty($receipt_number) && isset($_GET['receipt'])) {
    $receipt_number = $conn->real_escape_string($_GET['receipt']);
}

if (empty($receipt_number)) {
    header("Location: sales_view_sales.php");
    exit();
}

// Get first payment record for this receipt to get sale info
$sale_info_sql = "SELECT p.*, u.username 
                  FROM payments p 
                  LEFT JOIN users u ON p.buyer_id = u.user_id 
                  WHERE p.receipt_number = '$receipt_number' 
                  LIMIT 1";
$sale_info_result = $conn->query($sale_info_sql);

if (!$sale_info_result || $sale_info_result->num_rows == 0) {
    header("Location: sales_view_sales.php");
    exit();
}

$sale_info = $sale_info_result->fetch_assoc();

// Build sale_data array for compatibility
$sale_data = [
    'receipt_number' => $sale_info['receipt_number'],
    'buyer_name' => $sale_info['buyer_name'],
    'grade' => '',
    'payment_method' => $sale_info['payment_method'],
    'notes' => $sale_info['notes'] ?? '',
    'total_amount' => 0,
    'books_sold' => 0,
    'sale_time' => $sale_info['payment_date']
];

// Get shop settings
$settings_sql = "SELECT * FROM system_settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$shop_name = $settings['shop_name'] ?? 'School Bookshop';
$shop_address = $settings['shop_address'] ?? '';
$shop_phone = $settings['shop_phone'] ?? '';
$shop_email = $settings['shop_email'] ?? '';
$currency = $settings['currency'] ?? 'USD';

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

// Get all items for this receipt
$recent_sales_sql = "SELECT p.*, b.title, b.author, b.price, b.grade as book_grade
                     FROM payments p 
                     JOIN books b ON p.book_id = b.book_id 
                     WHERE p.receipt_number = '" . $conn->real_escape_string($receipt_number) . "'
                     ORDER BY p.payment_id DESC";
$recent_sales_result = $conn->query($recent_sales_sql);

$sale_items = [];
$total_amount = 0;
while ($item = $recent_sales_result->fetch_assoc()) {
    $sale_items[] = $item;
    $total_amount += $item['total_amount'];
}

// Get grade from first book
if (!empty($sale_items)) {
    $sale_data['grade'] = $sale_items[0]['book_grade'] ?? '';
}

$sale_data['total_amount'] = $total_amount;
$sale_data['books_sold'] = count($sale_items);

// If no items found, redirect back
if (empty($sale_items)) {
    header("Location: sales_sell_books.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sale Receipt - <?php echo htmlspecialchars($shop_name); ?></title>
  <link rel="icon" href="../images/school.jpeg" type="image/jpeg" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Courier New', monospace;
      background: #f4f4f4;
      padding: 20px;
      line-height: 1.6;
    }
    
    .receipt-container {
      max-width: 400px;
      margin: 0 auto;
      background: white;
      padding: 30px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    
    .receipt-header {
      text-align: center;
      border-bottom: 2px dashed #333;
      padding-bottom: 20px;
      margin-bottom: 20px;
    }
    
    .shop-name {
      font-size: 1.5em;
      font-weight: bold;
      margin-bottom: 5px;
      text-transform: uppercase;
    }
    
    .shop-details {
      font-size: 0.85em;
      color: #666;
      margin-bottom: 10px;
    }
    
    .receipt-title {
      font-size: 1.2em;
      font-weight: bold;
      margin: 15px 0;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    
    .receipt-info {
      margin-bottom: 20px;
      font-size: 0.9em;
    }
    
    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 5px;
    }
    
    .info-label {
      font-weight: bold;
    }
    
    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      font-size: 0.9em;
    }
    
    .items-table th {
      border-top: 2px dashed #333;
      border-bottom: 2px dashed #333;
      padding: 8px 5px;
      text-align: left;
      font-weight: bold;
    }
    
    .items-table td {
      padding: 8px 5px;
      border-bottom: 1px dotted #ccc;
    }
    
    .items-table .qty {
      text-align: center;
      width: 40px;
    }
    
    .items-table .price, .items-table .total {
      text-align: right;
      width: 70px;
    }
    
    .total-section {
      border-top: 2px dashed #333;
      border-bottom: 2px dashed #333;
      padding: 15px 0;
      margin: 20px 0;
    }
    
    .total-row {
      display: flex;
      justify-content: space-between;
      font-size: 1.3em;
      font-weight: bold;
    }
    
    .payment-info {
      margin: 20px 0;
      font-size: 0.9em;
    }
    
    .receipt-footer {
      text-align: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 2px dashed #333;
      font-size: 0.85em;
      color: #666;
    }
    
    .thank-you {
      font-size: 1.2em;
      font-weight: bold;
      margin-bottom: 10px;
      text-transform: uppercase;
    }
    
    .barcode {
      text-align: center;
      margin: 20px 0;
      font-family: 'Libre Barcode 39', monospace;
      font-size: 2em;
    }
    
    .actions {
      text-align: center;
      margin-top: 30px;
      padding: 20px;
    }
    
    .btn {
      padding: 12px 30px;
      margin: 5px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 1em;
      text-decoration: none;
      display: inline-block;
    }
    
    .btn-print {
      background: #003366;
      color: white;
    }
    
    .btn-back {
      background: #6c757d;
      color: white;
    }
    
    .btn:hover {
      opacity: 0.9;
    }
    
    @media print {
      body {
        background: white;
        padding: 0;
      }
      
      .receipt-container {
        box-shadow: none;
        max-width: 100%;
        padding: 20px;
      }
      
      .actions {
        display: none;
      }
      
      .receipt-container {
        page-break-after: always;
      }
    }
    
    @media (max-width: 480px) {
      .receipt-container {
        padding: 20px;
      }
      
      body {
        padding: 10px;
      }
    }
  </style>
</head>
<body>

  <div class="receipt-container">
    <!-- Receipt Header -->
    <div class="receipt-header">
      <div class="shop-name"><?php echo htmlspecialchars($shop_name); ?></div>
      <div class="shop-details">
        <?php if ($shop_address): ?>
          <?php echo htmlspecialchars($shop_address); ?><br>
        <?php endif; ?>
        <?php if ($shop_phone): ?>
          Tel: <?php echo htmlspecialchars($shop_phone); ?><br>
        <?php endif; ?>
        <?php if ($shop_email): ?>
          Email: <?php echo htmlspecialchars($shop_email); ?>
        <?php endif; ?>
      </div>
      <div class="receipt-title">Sales Receipt</div>
      <div class="barcode">*<?php echo $receipt_number; ?>*</div>
    </div>
    
    <!-- Receipt Info -->
    <div class="receipt-info">
      <div class="info-row">
        <span class="info-label">Receipt #:</span>
        <span><?php echo $receipt_number; ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Date:</span>
        <span><?php echo date('F d, Y H:i:s', strtotime($sale_data['sale_time'])); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Cashier:</span>
        <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Sales'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Customer:</span>
        <span><?php echo htmlspecialchars($sale_data['buyer_name']); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Grade:</span>
        <span><?php echo htmlspecialchars($sale_data['grade']); ?></span>
      </div>
    </div>
    
    <!-- Items Table -->
    <table class="items-table">
      <thead>
        <tr>
          <th class="qty">Qty</th>
          <th>Item</th>
          <th class="price">Price</th>
          <th class="total">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sale_items as $item): ?>
          <tr>
            <td class="qty"><?php echo $item['quantity']; ?></td>
            <td>
              <?php echo htmlspecialchars($item['title']); ?><br>
              <small style="color: #666;"><?php echo htmlspecialchars($item['author']); ?></small>
            </td>
            <td class="price"><?php echo $currency_symbol; ?><?php echo number_format($item['price'], 2); ?></td>
            <td class="total"><?php echo $currency_symbol; ?><?php echo number_format($item['total_amount'], 2); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    
    <!-- Total Section -->
    <div class="total-section">
      <div class="total-row">
        <span>TOTAL:</span>
        <span><?php echo $currency_symbol; ?><?php echo number_format($total_amount, 2); ?></span>
      </div>
    </div>

    
    <!-- Payment Info -->
    <div class="payment-info">
      <div class="info-row">
        <span class="info-label">Payment Method:</span>
        <span><?php echo htmlspecialchars($sale_data['payment_method']); ?></span>
      </div>
      <?php if ($sale_data['notes']): ?>
        <div class="info-row">
          <span class="info-label">Notes:</span>
          <span><?php echo htmlspecialchars($sale_data['notes']); ?></span>
        </div>
      <?php endif; ?>
      <div class="info-row">
        <span class="info-label">Items Sold:</span>
        <span><?php echo count($sale_items); ?> book(s)</span>
      </div>
    </div>
    
    <!-- Receipt Footer -->
    <div class="receipt-footer">
      <div class="thank-you">Thank You!</div>
      <p>Please keep this receipt for your records.</p>
      <p style="margin-top: 10px; font-size: 0.8em;">
        Goods sold are not returnable.<br>
        Exchange within 7 days with receipt.
      </p>
    </div>
  </div>
  
  <!-- Actions -->
  <div class="actions">
    <button class="btn btn-print" onclick="window.print()">🖨️ Print Receipt</button>
    <a href="sales_sell_books.php" class="btn btn-back">🛒 New Sale</a>
    <a href="sales_dashboard.php" class="btn btn-back">🏠 Dashboard</a>
  </div>

</body>
</html>
