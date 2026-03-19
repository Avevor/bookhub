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

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$message = '';
$success_message = '';

// Get active tab
$active_tab = $_GET['tab'] ?? 'single';

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

// Get cart items from session 
$cart = $_SESSION['cart'] ?? [];

// Handle add to cart for single book sale 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $book_id = intval($_POST['book_id']);
    $quantity = intval($_POST['quantity']);
    
    if ($book_id > 0 && $quantity > 0) {
        // Check available quantity 
        $check_qty = $conn->query("SELECT COALESCE(SUM(quantity), 0) as qty FROM inventory WHERE book_id = $book_id")->fetch_assoc()['qty'];
        
        $current_cart_qty = isset($cart[$book_id]) ? $cart[$book_id] : 0;
        
        if (($current_cart_qty + $quantity) <= $check_qty) { 
            if (isset($cart[$book_id])) {
                $cart[$book_id] += $quantity;
            } else {
                $cart[$book_id] = $quantity;
            }
            $_SESSION['cart'] = $cart; 
            $success_message = "Book added to cart!";
        } else {
            $message = "Not enough stock available!";
        }
    }
}

// Handle remove from cart  
if (isset($_GET['remove'])) {   
    $remove_id = intval($_GET['remove']);
    if (isset($cart[$remove_id])) {
        unset($cart[$remove_id]);
        $_SESSION['cart'] = $cart;
    }
}

// Handle clear cart  
if (isset($_GET['clear'])) {   
    $_SESSION['cart'] = [];
    $cart = [];
}

// Handle checkout for single book sale  
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout']) && !empty($cart)) {
    $buyer_name = $conn->real_escape_string($_POST['customer_name'] ?? 'Walk-in Customer');
    
    // Generate unique receipt number    
    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Ensure receipt number is unique    
    $check_receipt = $conn->query("SELECT receipt_number FROM payments WHERE receipt_number = '$receipt_number'");
    while ($check_receipt && $check_receipt->num_rows > 0) {        
        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);        
        $check_receipt = $conn->query("SELECT receipt_number FROM payments WHERE receipt_number = '$receipt_number'");    
    }
    
    $conn->begin_transaction();   
    $total_amount = 0;   
    $books_sold = 0;
    
    try {      
        foreach ($cart as $book_id => $qty) {         
            $book_id = intval($book_id);         
            $qty = intval($qty);         
            
            // Get book price and check inventory        
            $price_result = $conn->query("SELECT price, title FROM books WHERE book_id = $book_id");        
            $book = $price_result->fetch_assoc();         
            
            // Check available inventory        
            $inventory_result = $conn->query("SELECT quantity FROM inventory WHERE book_id = $book_id");        
            $inventory = $inventory_result->fetch_assoc();         
            
            if ($inventory['quantity'] < $qty) {           
                $conn->rollback();           
                $message = "Not enough stock for '" . $book['title'] . "'! Available: " . $inventory['quantity'];           
                break;       
            } 
            
            $price = $book['price'];
            $book_total = $price * $qty;
            $total_amount += $book_total;
            
            // Insert payment record for this book
            $payment_sql = "INSERT INTO payments (receipt_number, book_id, buyer_id, buyer_name, quantity, total_amount, payment_method, status, payment_date) 
                           VALUES ('$receipt_number', $book_id, $user_id, '$buyer_name', $qty, $book_total, 'Cash', 'Completed', NOW())";
            
            if (!$conn->query($payment_sql)) {
                throw new Exception("Error inserting payment: " . $conn->error);
            }
            
            // Update inventory
            $conn->query("UPDATE inventory SET quantity = quantity - $qty, last_updated = NOW() WHERE book_id = $book_id");
            
            // Log inventory history
            $history_sql = "INSERT INTO inventory_history (book_id, quantity_change, notes, updated_by) 
                           VALUES ($book_id, -$qty, 'Sold to $buyer_name', '{$_SESSION['username']}')";
            $conn->query($history_sql);
            
            $books_sold++;
        }

        
        if ($books_sold > 0) {
            $conn->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            $cart = [];
            
            // Redirect to receipt
            header("Location: sales_receipt.php?receipt_number=$receipt_number");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error processing payment: " . $e->getMessage();
    }
}

// Get selected grade for sell by grade
$selected_grade = $_GET['grade'] ?? '';

// Get books by grade if selected
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

// Handle sell by grade form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sell_by_grade'])) {
    $grade = $conn->real_escape_string($_POST['grade'] ?? '');
    $buyer_name = $conn->real_escape_string($_POST['buyer_name'] ?? '');
    $payment_method = $conn->real_escape_string($_POST['payment_method'] ?? 'Cash');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    
    $selected_books = $_POST['selected_books'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    if (empty($selected_books)) {
        $message = "Please select at least one book to sell.";
    } elseif (empty($buyer_name)) {
        $message = "Buyer name is required.";
    } else {
        // Generate unique receipt number
        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Ensure receipt number is unique
        $check_receipt = $conn->query("SELECT receipt_number FROM payments WHERE receipt_number = '$receipt_number'");
        while ($check_receipt->num_rows > 0) {
            $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $check_receipt = $conn->query("SELECT receipt_number FROM payments WHERE receipt_number = '$receipt_number'");
        }

        $conn->begin_transaction();
        $total_sale_amount = 0;
        $books_sold_count = 0;
        $sale_errors = [];
        
        try {
            foreach ($selected_books as $book_id) {
                $book_id = intval($book_id);
                $quantity = intval($quantities[$book_id] ?? 1);
                
                if ($quantity <= 0) continue;
                
                // Check available inventory
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
                        $sale_errors[] = "Insufficient stock for '$book_title'! Available: $available_qty";
                        continue;
                    }
                    
                    $book_total = $quantity * $price;
                    $total_sale_amount += $book_total;
                    $books_sold_count++;
                    
                    // Insert payment record
                    $payment_sql = "INSERT INTO payments (receipt_number, book_id, buyer_id, buyer_name, quantity, total_amount, payment_method, status, notes, payment_date) 
                                   VALUES ('$receipt_number', $book_id, $user_id, '$buyer_name', $quantity, $book_total, '$payment_method', 'Completed', '$notes', NOW())";
                    $conn->query($payment_sql);

                    // Update inventory
                    $conn->query("UPDATE inventory SET quantity = quantity - $quantity, last_updated = NOW() WHERE book_id = $book_id");
                    
                    // Log inventory history
                    $history_sql = "INSERT INTO inventory_history (book_id, quantity_change, notes, updated_by) 
                                   VALUES ($book_id, -$quantity, 'Sold to $buyer_name', '{$_SESSION['username']}')";
                    $conn->query($history_sql);
                }
            }

            
            if (empty($sale_errors)) {
                $conn->commit();
                // Redirect to receipt page with sale details
                $sale_data = [
                    'receipt_number' => $receipt_number,
                    'buyer_name' => $buyer_name,
                    'grade' => $grade,
                    'payment_method' => $payment_method,
                    'notes' => $notes,
                    'total_amount' => $total_sale_amount,
                    'books_sold' => $books_sold_count,
                    'sale_time' => date('Y-m-d H:i:s')
                ];
                $_SESSION['last_sale'] = $sale_data;
                header("Location: sales_receipt.php?receipt_number=$receipt_number");
                exit();

            } else {
                $conn->rollback();
                $message = "Sale failed: " . implode(", ", $sale_errors);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error processing sale: " . $e->getMessage();
        }
    }
}

// Get all available books with inventory for single book sale
$all_books_result = $conn->query("
    SELECT b.book_id, b.title, b.price, b.category, b.grade, COALESCE(SUM(i.quantity), 0) as quantity 
    FROM books b 
    LEFT JOIN inventory i ON b.book_id = i.book_id 
    GROUP BY b.book_id 
    HAVING quantity > 0
    ORDER BY b.title
");

// Calculate cart total for single book sale
$cart_total = 0;
$cart_items = [];
foreach ($cart as $book_id => $qty) {
    $result = $conn->query("SELECT book_id, title, price, grade FROM books WHERE book_id = $book_id");
    if ($book = $result->fetch_assoc()) {
        $cart_items[] = [
            'book_id' => $book_id,
            'title' => $book['title'],
            'price' => $book['price'],
            'grade' => $book['grade'],
            'quantity' => $qty,
            'subtotal' => $book['price'] * $qty
        ];
        $cart_total += $book['price'] * $qty;
    }
}

// Get recent sales
$sales_sql = "SELECT p.*, b.title, b.author, b.grade, b.price 
              FROM payments p 
              JOIN books b ON p.book_id = b.book_id 
              WHERE p.status = 'Completed' 
              ORDER BY p.payment_date DESC 
              LIMIT 10";
$sales_result = $conn->query($sales_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sell Books - Book Hub</title>
  <link rel="icon" href="../images/school.jpeg" type="image/jpeg" />
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

    /* Tab Styles */
    .tabs-container {
      margin-bottom: 30px;
    }
    
    .tabs {
      display: flex;
      border-bottom: 2px solid #e0e0e0;
      gap: 5px;
    }
    
    .tab-btn {
      padding: 12px 25px;
      background: #f8f9fa;
      border: none;
      border-radius: 8px 8px 0 0;
      cursor: pointer;
      font-size: 1em;
      font-weight: 600;
      color: #666;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .tab-btn:hover {
      background: #e9ecef;
      color: #003366;
    }
    
    .tab-btn.active {
      background: #003366;
      color: white;
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }

    .content-grid { display: grid; grid-template-columns: 1fr 400px; gap: 25px; }
    @media (max-width: 1024px) { .content-grid { grid-template-columns: 1fr; } }
    .books-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .section-title { color: #003366; margin: 0 0 20px 0; font-size: 1.3em; display: flex; align-items: center; gap: 10px; }
    .search-box { margin-bottom: 20px; }
    .search-box input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; }
    .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; max-height: 500px; overflow-y: auto; }
    .book-card { border: 2px solid #e0e0e0; border-radius: 10px; padding: 15px; text-align: center; transition: all 0.3s; }
    .book-card:hover { border-color: #003366; box-shadow: 0 4px 15px rgba(0,51,102,0.2); }
    .book-title { font-weight: 600; color: #003366; margin-bottom: 5px; font-size: 0.95em; }
    .book-info { font-size: 0.85em; color: #666; margin-bottom: 10px; }
    .book-price { font-size: 1.2em; font-weight: bold; color: #28a745; margin-bottom: 10px; }
    .book-qty { font-size: 0.85em; color: #666; margin-bottom: 10px; }
    .add-form { display: flex; gap: 5px; justify-content: center; }
    .add-form input { width: 60px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; text-align: center; }
    .add-form button { background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
    .cart-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 20px; }
    .cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .cart-title { color: #003366; margin: 0; font-size: 1.3em; }
    .cart-clear { color: #dc3545; text-decoration: none; font-size: 0.9em; }
    .cart-items { max-height: 300px; overflow-y: auto; margin-bottom: 20px; }
    .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
    .cart-item:last-child { border-bottom: none; }
    .cart-item-info h4 { margin: 0 0 5px 0; font-size: 0.95em; color: #003366; }
    .cart-item-info p { margin: 0; font-size: 0.85em; color: #666; }
    .cart-item-actions { display: flex; align-items: center; gap: 10px; }
    .cart-item-actions a { color: #dc3545; text-decoration: none; }
    .cart-total { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    .cart-total h3 { margin: 0 0 10px 0; color: #003366; }
    .cart-total .amount { font-size: 2em; font-weight: bold; color: #28a745; }
    .checkout-form { border-top: 2px solid #eee; padding-top: 20px; }
    .checkout-form h4 { margin: 0 0 15px 0; color: #003366; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9em; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; box-sizing: border-box; }
    .btn-checkout { width: 100%; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 15px; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; }
    .btn-checkout:disabled { background: #ccc; cursor: not-allowed; }
    .empty-cart { text-align: center; padding: 40px 20px; color: #666; }
    .empty-cart-icon { font-size: 3em; opacity: 0.5; }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .alert-success { padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

    /* Sell by Grade Styles */
    .sell-form-section {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .grade-selection { margin-bottom: 25px; }
    .grade-selection label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; }
    .grade-selection select { width: 100%; max-width: 300px; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; }
    .books-list { background: white; border-radius: 10px; padding: 20px; margin-bottom: 25px; border: 2px solid #e0e0e0; }
    .book-item { display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #e0e0e0; }
    .book-item:last-child { border-bottom: none; }
    .book-checkbox { width: 18px; height: 18px; margin-right: 12px; cursor: pointer; }
    .book-details { flex: 1; }
    .book-details .title { font-weight: 600; color: #333; margin-bottom: 3px; }
    .book-details .author { font-size: 0.85em; color: #666; }
    .book-stock { font-size: 0.85em; color: #28a745; margin-right: 15px; min-width: 80px; }
    .quantity-input { width: 60px; padding: 6px; border: 2px solid #e0e0e0; border-radius: 5px; text-align: center; }
    .select-all-bar { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; margin-bottom: 15px; border-bottom: 2px solid #003366; }
    .select-all-bar label { font-weight: 600; color: #003366; cursor: pointer; display: flex; align-items: center; gap: 8px; }
    .total-summary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px; margin-bottom: 25px; text-align: center; }
    .total-summary .label { font-size: 0.9em; opacity: 0.9; }
    .total-summary .amount { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
    .total-summary .count { font-size: 1.1em; }
    .btn-sell { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 15px 40px; border-radius: 8px; font-size: 1.2em; font-weight: 600; cursor: pointer; width: 100%; }
    .no-books { text-align: center; padding: 40px; color: #666; }

    /* Recent Sales */
    .recent-sales { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; margin-top: 30px; }
    .recent-sales-header { background: linear-gradient(135deg, #003366 0%, #005580 100%); color: white; padding: 20px 25px; }
    .recent-sales-header h2 { margin: 0; font-size: 1.5em; }
    .sales-table { width: 100%; border-collapse: collapse; }
    .sales-table th, .sales-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; }
    .sales-table th { background: #f8f9fa; font-weight: 600; color: #333; }
    .badge-success { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 0.8em; }
  </style>
</head>
<body>

<?php include('../includes/header.php'); ?>
<?php include('../includes/sidebar.php'); ?>

<div class="dashboard-container">
  <div class="main-content">
    
    <div class="page-header">
      <h1>
        <img src="../images/sell book.jpg" alt="Sell Books" class="dashboard-title-logo">
        Sell Books
      </h1>
      <p class="page-subtitle">Select books to add to cart or sell by grade</p>
    </div>

    <?php if ($message): ?>
      <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
      <div class="alert-success">✅ <?php echo $success_message; ?></div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <div class="tabs-container">
      <div class="tabs">
        <a href="?tab=single" class="tab-btn <?php echo $active_tab == 'single' ? 'active' : ''; ?>">
          🛒 Single Book Sale
        </a>
        <a href="?tab=by_grade" class="tab-btn <?php echo $active_tab == 'by_grade' ? 'active' : ''; ?>">
          📚 Sell by Grade
        </a>
      </div>
    </div>

    <!-- Tab Content: Single Book Sale -->
    <div class="tab-content <?php echo $active_tab == 'single' ? 'active' : ''; ?>" id="single">
      <div class="content-grid">
        <!-- Books Section -->
        <div class="books-section">
          <h2 class="section-title">📚 Available Books</h2>
          <div class="search-box">
            <input type="text" id="searchBooks" placeholder="🔍 Search by title, category, grade..." oninput="debouncedFilterBooks()">
            <div id="searchResults" style="font-size: 0.9em; color: #666; margin-top: 5px; min-height: 20px;"></div>
          </div>
          <div class="books-grid" id="booksGrid">
            <div id="noResults" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666; display: none;">
              <div style="font-size: 3em; margin-bottom: 10px;">🔍</div>
              <p>No books match your search. Try different keywords.</p>
            </div>
            <?php while ($book = $all_books_result->fetch_assoc()): ?>
              <div class="book-card" data-search="<?php echo strtolower($book['title'] . ' ' . ($book['category'] ?? '') . ' ' . ($book['grade'] ?? '')); ?>">
                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                <div class="book-info"><?php echo htmlspecialchars($book['category']); ?> - <?php echo htmlspecialchars($book['grade']); ?></div>
                <div class="book-price"><?php echo $currency_symbol; ?><?php echo number_format($book['price'], 2); ?></div>
                <div class="book-qty">Stock: <?php echo $book['quantity']; ?></div>
                <form method="POST" class="add-form">
                  <input type="hidden" name="tab" value="single">
                  <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                  <input type="number" name="quantity" min="1" max="<?php echo $book['quantity']; ?>" value="1">
                  <button type="submit" name="add_to_cart">Add</button>
                </form>
              </div>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Cart Section -->
        <div class="cart-section">
          <div class="cart-header">
            <h2 class="cart-title">🛒 Cart</h2>
            <?php if (!empty($cart)): ?>
              <a href="?tab=single&clear=1" class="cart-clear">Clear All</a>
            <?php endif; ?>
          </div>
          
          <?php if (!empty($cart_items)): ?>
            <div class="cart-items">
              <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                  <div class="cart-item-info">
                    <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                    <p><?php echo htmlspecialchars($item['grade']); ?> | <?php echo $currency_symbol; ?><?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></p>
                  </div>
                  <div class="cart-item-actions">
                    <span style="font-weight:600;"><?php echo $currency_symbol; ?><?php echo number_format($item['subtotal'], 2); ?></span>
                    <a href="?tab=single&remove=<?php echo $item['book_id']; ?>">❌</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            
            <div class="cart-total">
              <h3>Total</h3>
              <div class="amount"><?php echo $currency_symbol; ?><?php echo number_format($cart_total, 2); ?></div>
            </div>
            
            <form method="POST" class="checkout-form">
              <input type="hidden" name="tab" value="single">
              <h4>Customer Details</h4>
              <div class="form-group">
                <label>Customer Name</label>
                <input type="text" name="customer_name" placeholder="Walk-in Customer" required>
              </div>
              <button type="submit" name="checkout" class="btn-checkout">💰 Complete Sale</button>
            </form>
          <?php else: ?>
            <div class="empty-cart">
              <div class="empty-cart-icon">🛒</div>
              <p>Cart is empty</p>
              <p>Add books from the left to start a sale</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Tab Content: Sell by Grade -->
    <div class="tab-content <?php echo $active_tab == 'by_grade' ? 'active' : ''; ?>" id="by_grade">
      <div class="sell-form-section">
        <h2 style="margin-top: 0; color: #003366; margin-bottom: 25px;">📚 Sell by Grade</h2>
        
        <!-- Grade Selection -->
        <div class="grade-selection">
          <form method="GET" action="" id="gradeForm">
            <input type="hidden" name="tab" value="by_grade">
            <label for="grade">Select Grade *</label>
            <select name="grade" id="grade" required onchange="document.getElementById('gradeForm').submit()">
              <option value="">-- Choose a Grade --</option>
              <?php foreach ($grades_list as $grade): ?>
                <?php if (trim($grade)): ?>
                  <option value="<?php echo htmlspecialchars(trim($grade)); ?>" <?php echo $selected_grade == trim($grade) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(trim($grade)); ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <?php if ($selected_grade): ?>
          <form method="POST" action="" id="sellForm">
            <input type="hidden" name="tab" value="by_grade">
            <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selected_grade); ?>">
            
            <?php if (empty($books_by_grade)): ?>
              <div class="no-books">
                <h3>No books available for <?php echo htmlspecialchars($selected_grade); ?></h3>
                <p>All books in this grade are out of stock.</p>
              </div>
            <?php else: ?>
              <!-- Books List -->
              <div class="books-list">
                <div class="select-all-bar">
                  <label>
                    <input type="checkbox" id="selectAll" checked onchange="toggleSelectAll()">
                    Select All Books
                  </label>
                  <span style="color: #666; font-size: 0.9em;"><?php echo count($books_by_grade); ?> books available</span>
                </div>
                
                <?php foreach ($books_by_grade as $book): ?>
                  <div class="book-item">
                    <input type="checkbox" 
                           name="selected_books[]" 
                           value="<?php echo $book['book_id']; ?>" 
                           class="book-checkbox"
                           id="book-<?php echo $book['book_id']; ?>"
                           checked
                           onchange="updateTotal()">
                    
                    <div class="book-details">
                      <div class="title"><?php echo htmlspecialchars($book['title']); ?></div>
                      <div class="author">by <?php echo htmlspecialchars($book['author']); ?> | <?php echo htmlspecialchars($book['category']); ?></div>
                    </div>
                    
                    <div class="book-price" style="margin-right: 15px;"><?php echo $currency_symbol; ?><?php echo number_format($book['price'], 2); ?></div>

                    <div class="book-stock">Stock: <?php echo $book['stock']; ?></div>
                    
                    <input type="number" 
                           name="quantities[<?php echo $book['book_id']; ?>]" 
                           class="quantity-input" 
                           value="1" 
                           min="1" 
                           max="<?php echo $book['stock']; ?>"
                           onchange="updateTotal()">
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Total Summary -->
              <div class="total-summary">
                <div class="label">Total Amount</div>
                <div class="amount" id="totalAmount"><?php echo $currency_symbol; ?>0.00</div>
                <div class="count"><span id="selectedCount">0</span> books selected</div>
              </div>

              <!-- Customer Information -->
              <div class="form-group">
                <label for="buyer_name">Buyer Name *</label>
                <input type="text" name="buyer_name" id="buyer_name" placeholder="Enter buyer name" required>
              </div>

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

              <button type="submit" name="sell_by_grade" class="btn-sell">
                💰 Complete Sale
              </button>
            <?php endif; ?>
          </form>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #666;">
            <p>Please select a grade to view available books for sale.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Sales -->
    <div class="recent-sales">
      <div class="recent-sales-header">
        <h2>📋 Recent Sales</h2>
      </div>
      <table class="sales-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Book</th>
            <th>Grade</th>
            <th>Qty</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($sales_result && $sales_result->num_rows > 0): ?>
            <?php while ($sale = $sales_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo date('M d, Y H:i', strtotime($sale['payment_date'])); ?></td>
                <td><?php echo htmlspecialchars($sale['title']); ?></td>
                <td><?php echo htmlspecialchars($sale['grade']); ?></td>
                <td><?php echo $sale['quantity']; ?></td>
                <td><?php echo $currency_symbol; ?><?php echo number_format($sale['total_amount'], 2); ?></td>
                <td><span class="badge-success">Completed</span></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 30px; color: #666;">
                No recent sales found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
// Store book prices and stocks for sell by grade
const booksData = <?php echo json_encode($books_by_grade); ?>;

function toggleSelectAll() {
  const selectAllCheckbox = document.getElementById('selectAll');
  const bookCheckboxes = document.querySelectorAll('.book-checkbox');
  
  bookCheckboxes.forEach(checkbox => {
    checkbox.checked = selectAllCheckbox.checked;
  });
  
  updateTotal();
}

function updateTotal() {
  let totalAmount = 0;
  let selectedCount = 0;
  
  booksData.forEach(book => {
    const checkbox = document.getElementById('book-' + book.book_id);
    const quantityInput = document.querySelector('input[name="quantities[' + book.book_id + ']"]');
    
    if (checkbox && checkbox.checked) {
      const quantity = parseInt(quantityInput.value) || 1;
      const maxStock = parseInt(book.stock);
      
      // Validate quantity
      if (quantity > maxStock) {
        quantityInput.value = maxStock;
        alert('Maximum available stock for ' + book.title + ' is ' + maxStock);
      }
      
      const validQuantity = Math.min(quantity, maxStock);
      totalAmount += book.price * validQuantity;
      selectedCount++;
    }
  });
  
  document.getElementById('totalAmount').textContent = '<?php echo $currency_symbol; ?>' + totalAmount.toFixed(2);
  document.getElementById('selectedCount').textContent = selectedCount;
}

let searchTimeout;

function filterBooks(searchTerm = '') {
  const books = document.querySelectorAll('#booksGrid .book-card');
  const noResultsDiv = document.getElementById('noResults');
  const resultsDiv = document.getElementById('searchResults');
  let visibleCount = 0;

  books.forEach(book => {
    const searchData = book.getAttribute('data-search') || '';
    if (searchTerm.trim() === '' || searchData.includes(searchTerm.toLowerCase().trim())) {
      book.style.display = 'block';
      visibleCount++;
    } else {
      book.style.display = 'none';
    }
  });

  if (visibleCount === 0 && searchTerm.trim() !== '') {
    noResultsDiv.style.display = 'block';
  } else {
    noResultsDiv.style.display = 'none';
  }

  resultsDiv.textContent = `${visibleCount} book${visibleCount !== 1 ? 's' : ''} found`;
}

function debouncedFilterBooks() {
  clearTimeout(searchTimeout);
  const searchTerm = document.getElementById('searchBooks').value;
  searchTimeout = setTimeout(() => filterBooks(searchTerm), 300);
}

// Form validation for sell by grade
document.getElementById('sellForm')?.addEventListener('submit', function(e) {
  const checkedBooks = document.querySelectorAll('.book-checkbox:checked');
  
  if (checkedBooks.length === 0) {
    e.preventDefault();
    alert('Please select at least one book to sell.');
    return false;
  }
  
  let hasError = false;
  booksData.forEach(book => {
    const checkbox = document.getElementById('book-' + book.book_id);
    const quantityInput = document.querySelector('input[name="quantities[' + book.book_id + ']"]');
    
    if (checkbox && checkbox.checked) {
      const quantity = parseInt(quantityInput.value) || 0;
      if (quantity > book.stock) {
        hasError = true;
        alert('Quantity for ' + book.title + ' exceeds available stock (' + book.stock + ')');
      }
      if (quantity <= 0) {
        hasError = true;
        alert('Please enter a valid quantity for ' + book.title);
      }
    }
  });
  
  if (hasError) {
    e.preventDefault();
    return false;
  }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  filterBooks();  // Show all books initially
  updateTotal();
});
</script>

<?php include('../includes/footer.php'); ?>

</body>
</html>
