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
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'manage_payments'";
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


$success_message = '';
$error_message = '';

// Get grades from settings
$grades_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'grades'";
$grades_result = $conn->query($grades_sql);
$grades_setting = 'Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12';
if ($grades_result && $grades_result->num_rows > 0) {
    $grades_setting = $grades_result->fetch_assoc()['setting_value'];
}
$grades_list = array_map('trim', explode(',', $grades_setting));

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

// Handle form submission for recording payment

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_payment'])) {
    $grade = $conn->real_escape_string($_POST['grade'] ?? '');
    $buyer_name = $conn->real_escape_string($_POST['buyer_name'] ?? '');
    $book_id = intval($_POST['book_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $payment_method = $conn->real_escape_string($_POST['payment_method'] ?? 'Cash');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    
    if (empty($buyer_name)) {
        $error_message = "Buyer name is required.";
    } elseif ($book_id <= 0) {
        $error_message = "Please select a book.";
    } elseif ($quantity <= 0) {
        $error_message = "Quantity must be at least 1.";
    } else {
        // Check inventory
        $inventory_sql = "SELECT i.quantity, b.price, b.title 
                          FROM inventory i 
                          JOIN books b ON i.book_id = b.book_id 
                          WHERE i.book_id = $book_id";
        $inventory_result = $conn->query($inventory_sql);
        
        if ($inventory_result && $inventory_result->num_rows > 0) {
            $inventory = $inventory_result->fetch_assoc();
            $available_qty = $inventory['quantity'];
            $price = $inventory['price'];
            $book_title = $inventory['title'];
            
            if ($quantity > $available_qty) {
                $error_message = "Insufficient stock for '$book_title'! Available: $available_qty";
            } else {
                // Generate unique receipt number
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Ensure receipt number is unique
                $check_receipt = $conn->query("SELECT receipt_number FROM payments WHERE receipt_number = '$receipt_number'");
                while ($check_receipt->num_rows > 0) {
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $check_receipt = $conn->query("SELECT receipt_number FROM payments WHERE receipt_number = '$receipt_number'");
                }
                
                $total_amount = $quantity * $price;
                
                // Insert payment
                $payment_sql = "INSERT INTO payments (receipt_number, book_id, buyer_id, buyer_name, quantity, total_amount, payment_method, status, notes, payment_date) 
                               VALUES ('$receipt_number', $book_id, NULL, '$buyer_name', $quantity, $total_amount, '$payment_method', 'Completed', '$notes', NOW())";
                
                if ($conn->query($payment_sql)) {
                    // Update inventory
                    $update_sql = "UPDATE inventory SET quantity = quantity - $quantity WHERE book_id = $book_id";
                    $conn->query($update_sql);
                    
                    $success_message = "Payment recorded successfully! Receipt: $receipt_number";
                    
                    // Store for receipt
                    $_SESSION['last_sale'] = [
                        'receipt_number' => $receipt_number,
                        'buyer_name' => $buyer_name,
                        'grade' => $grade,
                        'payment_method' => $payment_method,
                        'notes' => $notes,
                        'total_amount' => $total_amount,
                        'books_sold' => 1,
                        'sale_time' => date('Y-m-d H:i:s')
                    ];
                    
                    // Redirect to receipt
                    header("Location: receipt.php");
                    exit();
                } else {
                    $error_message = "Error recording payment: " . $conn->error;
                }
            }
        } else {
            $error_message = "Book not found or out of stock.";
        }
    }
}

// Get selected grade
$selected_grade = $_GET['grade'] ?? $_POST['grade'] ?? '';

// Get books by grade
$books_by_grade = [];
if ($selected_grade) {
    $books_sql = "SELECT b.book_id, b.title, b.author, b.price, b.category, i.quantity as stock 
                  FROM books b 
                  JOIN inventory i ON b.book_id = i.book_id 
                  WHERE b.grade = '" . $conn->real_escape_string($selected_grade) . "'
                  AND i.quantity > 0
                  ORDER BY b.title";
    $books_result = $conn->query($books_sql);
    while ($book = $books_result->fetch_assoc()) {
        $books_by_grade[] = $book;
    }
}

// Get recent payments
$payments_sql = "SELECT p.*, b.title, b.author, b.grade 
                 FROM payments p 
                 JOIN books b ON p.book_id = b.book_id 
                 ORDER BY p.payment_date DESC 
                 LIMIT 10";
$payments_result = $conn->query($payments_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Record Payment - Book Hub</title>
  <link rel="icon" href="../images/school.jpeg" type="image/jpeg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
  <style>
    .payment-form-section {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      font-size: 0.95em;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
      transition: border-color 0.3s ease;
      box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #003366;
      box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
    }

    .btn-record {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
      border: none;
      padding: 15px 40px;
      border-radius: 8px;
      font-size: 1.2em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-record:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 51, 102, 0.3);
    }

    .alert {
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .recent-payments {
      background: white;
      border-radius: 15px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .recent-payments-header {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
      padding: 20px 25px;
    }

    .payments-table {
      width: 100%;
      border-collapse: collapse;
    }

    .payments-table th,
    .payments-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #e0e0e0;
    }

    .payments-table th {
      background: #f8f9fa;
      font-weight: 600;
      color: #333;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.8em;
      font-weight: 600;
    }

    .badge-success {
      background: #d4edda;
      color: #155724;
    }

    .total-display {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 10px;
      padding: 20px;
      margin: 20px 0;
      text-align: center;
    }

    .total-display .label {
      font-size: 0.9em;
      opacity: 0.9;
    }

    .total-display .amount {
      font-size: 2em;
      font-weight: bold;
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
          <img src="../images/school.png" alt="Record Payment" class="dashboard-title-logo">
          Record Payment
        </h1>
        <p class="page-subtitle">Record a new book sale/payment</p>
      </div>

      <!-- Messages -->
      <?php if ($success_message): ?>
        <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
      <?php endif; ?>
      
      <?php if ($error_message): ?>
        <div class="alert alert-error">❌ <?php echo $error_message; ?></div>
      <?php endif; ?>

      <!-- Payment Form -->
      <div class="payment-form-section">
        <h2 style="margin-top: 0; color: #003366; margin-bottom: 25px;">💳 New Payment</h2>
        
        <form method="POST" action="" id="paymentForm">
          <!-- Grade Selection -->
          <div class="form-row">
            <div class="form-group">
              <label for="grade">Grade *</label>
              <select name="grade" id="grade" required onchange="this.form.submit()">
                <option value="">-- Select Grade --</option>
                <?php foreach ($grades_list as $grade): ?>
                  <?php if (trim($grade)): ?>
                    <option value="<?php echo htmlspecialchars(trim($grade)); ?>" <?php echo $selected_grade == trim($grade) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars(trim($grade)); ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="form-group">
              <label for="buyer_name">Buyer Name *</label>
              <input type="text" name="buyer_name" id="buyer_name" placeholder="Enter buyer name" required>
            </div>
          </div>

          <?php if ($selected_grade): ?>
            <?php if (empty($books_by_grade)): ?>
              <div style="text-align: center; padding: 30px; color: #666;">
                <p>No books available for <?php echo htmlspecialchars($selected_grade); ?></p>
              </div>
            <?php else: ?>
              <div class="form-row">
                <div class="form-group">
                  <label for="book_id">Book *</label>
                  <select name="book_id" id="book_id" required onchange="updateTotal()">
                    <option value="">-- Select Book --</option>
                    <?php foreach ($books_by_grade as $book): ?>
                    <option value="<?php echo $book['book_id']; ?>" data-price="<?php echo $book['price']; ?>" data-stock="<?php echo $book['stock']; ?>">
                        <?php echo htmlspecialchars($book['title']); ?> - <?php echo $currency_symbol; ?><?php echo number_format($book['price'], 2); ?> (Stock: <?php echo $book['stock']; ?>)
                      </option>

                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="quantity">Quantity *</label>
                  <input type="number" name="quantity" id="quantity" value="1" min="1" required onchange="updateTotal()">
                </div>
              </div>

              <div class="total-display">
                <div class="label">Total Amount</div>
                <div class="amount" id="totalAmount"><?php echo $currency_symbol; ?>0.00</div>
              </div>


              <div class="form-row">
                <div class="form-group">
                  <label for="payment_method">Payment Method *</label>
                  <select name="payment_method" id="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Debit Card">Debit Card</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="notes">Notes</label>
                  <input type="text" name="notes" id="notes" placeholder="Optional notes">
                </div>
              </div>

              <button type="submit" name="record_payment" class="btn-record">
                💾 Record Payment
              </button>
            <?php endif; ?>
          <?php else: ?>
            <div style="text-align: center; padding: 30px; color: #666;">
              <p>Please select a grade to view available books.</p>
            </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- Recent Payments -->
      <div class="recent-payments">
        <div class="recent-payments-header">
          <h2>📋 Recent Payments</h2>
        </div>
        <div class="table-container">
          <table class="payments-table">
            <thead>
              <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Buyer</th>
                <th>Book</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Method</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                <?php while ($payment = $payments_result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($payment['buyer_name']); ?></td>
                    <td><?php echo htmlspecialchars($payment['title']); ?></td>
                    <td><?php echo $payment['quantity']; ?></td>
                    <td><?php echo $currency_symbol; ?><?php echo number_format($payment['total_amount'], 2); ?></td>

                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                    <td><span class="badge badge-success">Completed</span></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                    No recent payments found.
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

  <script>
    function updateTotal() {
      const bookSelect = document.getElementById('book_id');
      const quantityInput = document.getElementById('quantity');
      const totalDisplay = document.getElementById('totalAmount');
      
      const selectedOption = bookSelect.options[bookSelect.selectedIndex];
      const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
      const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
      const quantity = parseInt(quantityInput.value) || 0;
      
      // Validate quantity against stock
      if (quantity > stock) {
        quantityInput.value = stock;
        alert('Maximum available stock is ' + stock);
        return updateTotal();
      }
      
      const total = price * quantity;
      totalDisplay.textContent = '<?php echo $currency_symbol; ?>' + total.toFixed(2);
    }

    
    // Form validation
    document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
      const bookSelect = document.getElementById('book_id');
      const quantityInput = document.getElementById('quantity');
      
      if (!bookSelect.value) {
        e.preventDefault();
        alert('Please select a book.');
        return false;
      }
      
      const selectedOption = bookSelect.options[bookSelect.selectedIndex];
      const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
      const quantity = parseInt(quantityInput.value) || 0;
      
      if (quantity > stock) {
        e.preventDefault();
        alert('Quantity exceeds available stock (' + stock + ')');
        return false;
      }
    });
  </script>

</body>
</html>
