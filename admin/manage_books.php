<?php
// Include session config and check
include('../includes/session_config.php');
include('../includes/session_check.php');

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf() {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}


// Require valid session - redirects to login if expired
requireValidSession();

include('../config/db.php');

// ✅ Check page access for sales users
$user_role_id = $_SESSION['role_id'] ?? 0;
$can_access = false;

if ($user_role_id == 1) {
    // Admin always has access
    $can_access = true;
} elseif ($user_role_id == 2) {
    // Sales user - check page_access_settings
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'manage_books'";
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


$username = $_SESSION['username'];

// Handle delete action (POST only now)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_book' && verify_csrf() && is_numeric($_POST['book_id'])) {
    $delete_id = intval($_POST['book_id']);
    
    // Delete from inventory first (due to foreign key)
    $delete_inv = $conn->prepare("DELETE FROM inventory WHERE book_id = ?");
    $delete_inv->bind_param("i", $delete_id);
    $delete_inv->execute();
    $delete_inv->close();
    
    // Delete from books
    $delete_book = $conn->prepare("DELETE FROM books WHERE book_id = ?");
    $delete_book->bind_param("i", $delete_id);
    if ($delete_book->execute()) {
        $delete_message = "Book deleted successfully!";
        $delete_message_type = "success";
    } else {
        $delete_message = "Error deleting book: " . $conn->error;
        $delete_message_type = "error";
    }
    $delete_book->close();
} elseif (isset($_GET['delete'])) {
    // Legacy redirect - remove after testing
    $delete_message = "Delete action updated to POST form. Use the new delete button.";
    $delete_message_type = "info";
}


// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$grade_filter = isset($_GET['grade']) ? trim($_GET['grade']) : '';

// Build the query with filters
$query = "SELECT b.*, COALESCE(SUM(i.quantity), 0) as total_quantity 
          FROM books b 
          LEFT JOIN inventory i ON b.book_id = i.book_id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%$search%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= "sss";
}

if (!empty($category_filter)) {
    $query .= " AND b.category = ?";
    $params[] = &$category_filter;
    $types .= "s";
}

if (!empty($grade_filter)) {
    $query .= " AND b.grade = ?";
    $params[] = &$grade_filter;
    $types .= "s";
}

$query .= " GROUP BY b.book_id ORDER BY b.book_id DESC";

// Prepare and execute
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get unique categories for filter
$cat_query = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category";
$cat_result = $conn->query($cat_query);
$categories = [];
while ($cat_row = $cat_result->fetch_assoc()) {
    $categories[] = $cat_row['category'];
}

// Get unique grades for filter
$grade_query = "SELECT DISTINCT grade FROM books WHERE grade IS NOT NULL AND grade != '' ORDER BY grade";
$grade_result = $conn->query($grade_query);
$grades = [];
while ($grade_row = $grade_result->fetch_assoc()) {
    $grades[] = $grade_row['grade'];
}

// Count total books
$count_query = "SELECT COUNT(*) as total FROM books";
$count_result = $conn->query($count_query);
$total_books = $count_result->fetch_assoc()['total'];

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
  <title>Manage Books - Book HUb</title>
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
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
    }
    .stat-icon {
      font-size: 2.5em;
      margin-right: 20px;
    }
    .stat-content h3 {
      margin: 0 0 5px 0;
      font-size: 2em;
      font-weight: bold;
    }
    .stat-content p {
      margin: 0;
      opacity: 0.9;
    }
    .filter-section {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
      margin-bottom: 25px;
    }
    .filter-form {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: flex-end;
    }
    .filter-group {
      flex: 1;
      min-width: 200px;
    }
    .filter-group label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    .filter-group input,
    .filter-group select {
      width: 100%;
      padding: 10px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
      box-sizing: border-box;
    }
    .filter-group input:focus,
    .filter-group select:focus {
      outline: none;
      border-color: #003366;
    }
    .btn-filter {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
      border: none;
      padding: 12px 25px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-filter:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 51, 102, 0.3);
    }
    .btn-reset {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 25px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }
    .btn-reset:hover {
      background: #5a6268;
      transform: translateY(-2px);
    }
    .table-section {
      background: white;
      border-radius: 15px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    .table-header {
      background: linear-gradient(135deg, #003366 0%, #005580 100%);
      color: white;
      padding: 20px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }
    .table-header h2 {
      margin: 0;
      font-size: 1.5em;
    }
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
    .books-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95em;
    }
    .books-table thead {
      background: #f8f9fa;
    }
    .books-table th {
      padding: 15px;
      text-align: left;
      font-weight: 600;
      color: #333;
      border-bottom: 2px solid #e0e0e0;
      white-space: nowrap;
    }
    .books-table td {
      padding: 15px;
      border-bottom: 1px solid #e0e0e0;
      vertical-align: middle;
    }
    .books-table tbody tr:hover {
      background: #f8f9fa;
    }
    .books-table tbody tr:last-child td {
      border-bottom: none;
    }
    .book-title {
      font-weight: 600;
      color: #003366;
    }
    .book-author {
      color: #666;
      font-size: 0.9em;
    }
    .category-badge {
      display: inline-block;
      padding: 5px 12px;
      background: #e3f2fd;
      color: #003366;
      border-radius: 20px;
      font-size: 0.85em;
      font-weight: 500;
    }
    .grade-badge {
      display: inline-block;
      padding: 5px 12px;
      background: #fff3e0;
      color: #e65100;
      border-radius: 20px;
      font-size: 0.85em;
      font-weight: 500;
    }
    .price {
      font-weight: 600;
      color: #28a745;
    }
    .quantity-badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.85em;
      font-weight: 500;
    }
    .quantity-badge.in-stock {
      background: #d4edda;
      color: #155724;
    }
    .quantity-badge.low-stock {
      background: #fff3cd;
      color: #856404;
    }
    .quantity-badge.out-of-stock {
      background: #f8d7da;
      color: #721c24;
    }
    .action-btns {
      display: flex;
      gap: 8px;
    }
    .btn-action {
      padding: 6px 12px;
      border: none;
      border-radius: 6px;
      font-size: 0.85em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }
    .btn-edit {
      background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
      color: white;
    }
    .btn-edit:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(255, 193, 7, 0.4);
    }
    .btn-delete {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
    }
    .btn-delete:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.4);
    }
    .btn-add {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }
    .btn-print {
      background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
      color: white;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }
    .btn-print:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
    }
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #666;
    }
    .empty-state-icon {
      font-size: 4em;
      margin-bottom: 20px;
      opacity: 0.5;
    }
    .empty-state h3 {
      margin: 0 0 10px 0;
      color: #333;
    }
    .empty-state p {
      margin: 0 0 20px 0;
    }
    .message {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    .message.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .message.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    /* Modal Styles */
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
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
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
    }
    @keyframes modalSlide {
      to { transform: scale(1); }
    }
    .modal-header {
      font-size: 1.4em;
      font-weight: 600;
      color: #003366;
      margin-bottom: 15px;
      text-align: center;
    }
    .modal-body {
      color: #333;
      line-height: 1.5;
      margin-bottom: 25px;
    }
    .modal-buttons {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
    }
    .btn-modal-cancel {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-modal-cancel:hover {
      background: #5a6268;
      transform: translateY(-2px);
    }
    .btn-modal-confirm {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-modal-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
    }
    .modal-close {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 1.8em;
      color: #999;
      cursor: pointer;
      transition: color 0.3s ease;
    }
    .modal-close:hover {
      color: #dc3545;
    }
    @media (max-width: 768px) {
      .filter-form {
        flex-direction: column;
      }
      .filter-group {
        width: 100%;
      }
      .table-header {
        flex-direction: column;
        align-items: flex-start;
      }

      /* ===========================
   RESPONSIVE PORTRAIT VIEW
=========================== */
@media (max-width: 1024px) and (orientation: portrait) {

  /* Dashboard container padding */
  .dashboard-container {
    padding: 10px;
  }

  /* Page header adjustments */
  .page-header h1 {
    font-size: 1.8em;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .dashboard-title-logo {
    width: 40px;
    height: 40px;
  }

  /* Stats grid stacks vertically */
  .stats-grid {
    grid-template-columns: 1fr; /* one column */
    gap: 15px;
  }

  /* Filter form stacks vertically */
  .filter-form {
    flex-direction: column;
    gap: 10px;
  }

  .filter-group {
    width: 100%;
  }

  /* Table container horizontal scroll */
  .table-container {
    overflow-x: auto;
  }

  .books-table th, 
  .books-table td {
    padding: 10px;
    font-size: 0.85em;
  }

  /* Action buttons wrap */
  .action-btns {
    flex-direction: column;
    gap: 5px;
  }

  .btn-add, .btn-filter, .btn-reset {
    width: 100%;
    text-align: center;
  }

  /* Badge resizing */
  .category-badge,
  .grade-badge,
  .quantity-badge {
    font-size: 0.75em;
    padding: 4px 8px;
  }
}

    }
    /* Print styles */
    @media print {
      .navbar, .sidebar, #sidebar-toggle, footer, .filter-section, .btn-print, .action-btns {
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
      .table-container {
        max-height: none !important;
        overflow: visible !important;
      }
      .table-section {
        box-shadow: none !important;
      }
      .page-header {
        margin-bottom: 15px;
      }
      .stats-grid {
        margin-bottom: 15px;
      }
      .stat-card {
        break-inside: avoid;
      }
    }
  </style>
  <script>
    function printBooksReport() {
      // Get the table
      const table = document.querySelector('.books-table');
      
      // Clone the table to modify it for printing
      const tableClone = table.cloneNode(true);
      
      // Remove the Actions column (last column) from both header and body
      const headerRow = tableClone.querySelector('thead tr');
      headerRow.deleteCell(-1); // Remove last header cell
      
      const bodyRows = tableClone.querySelectorAll('tbody tr');
      bodyRows.forEach(row => row.deleteCell(-1)); // Remove last body cell for each row
      
      // Get currency symbol from the page
      const currencySymbol = '<?php echo $currency_symbol; ?>';
      
      // Create a new window with just the table
      const printWindow = window.open('', '_blank', 'width=800,height=600');
      
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Books Report - Print View</title>
          <style>
            body {
              font-family: Arial, sans-serif;
              padding: 20px;
              margin: 0;
            }
            h1 {
              color: #003366;
              text-align: center;
              margin-bottom: 20px;
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
              font-size: 12px;
            }
            th, td {
              border: 1px solid #333;
              padding: 8px;
              text-align: left;
            }
            th {
              background: #f0f0f0;
              font-weight: bold;
            }
            tr:nth-child(even) {
              background: #f9f9f9;
            }
            .price {
              color: #28a745;
              font-weight: bold;
            }
            .badge {
              padding: 3px 8px;
              border-radius: 10px;
              font-size: 0.85em;
            }
            .in-stock { background: #d4edda; color: #155724; }
            .low-stock { background: #fff3cd; color: #856404; }
            .out-of-stock { background: #f8d7da; color: #721c24; }
            @media print {
              body { padding: 0; }
              th { background: #eee !important; -webkit-print-color-adjust: exact; }
            }
          </style>
        </head>
        <body>
          <h1>📚 Books Report</h1>
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
          <img src="../images/manage book.jpg" alt="manage book" class="dashboard-title-logo">
          Manage Books
        </h1>
        <p class="page-subtitle">View, search, edit and manage all books in the inventory</p>
      </div>

      <?php if (isset($delete_message)): ?>
        <div class="message <?php echo $delete_message_type; ?>">
          <?php echo $delete_message; ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📚</div>
          <div class="stat-content">
            <h3><?php echo $total_books; ?></h3>
            <p>Total Books</p>
          </div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
          <div class="stat-icon">📦</div>
          <div class="stat-content">
            <h3><?php 
              $total_qty = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM inventory")->fetch_assoc()['total'];
              echo $total_qty;
            ?></h3>
            <p>Total Stock</p>
          </div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
          <div class="stat-icon">🏷️</div>
          <div class="stat-content">
            <h3><?php echo count($categories); ?></h3>
            <p>Categories</p>
          </div>
        </div>
      </div>

      <div class="filter-section">
        <form method="GET" action="" class="filter-form">
          <div class="filter-group">
            <label for="search">🔍 Search</label>
            <input type="text" id="search" name="search" placeholder="Search by title, author, or ISBN" value="<?php echo htmlspecialchars($search); ?>">
          </div>
          <div class="filter-group">
            <label for="category">📂 Category</label>
            <select id="category" name="category">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category_filter == $cat) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label for="grade">🎓 Grade</label>
            <select id="grade" name="grade">
              <option value="">All Grades</option>
              <?php foreach ($grades as $grd): ?>
                <option value="<?php echo htmlspecialchars($grd); ?>" <?php echo ($grade_filter == $grd) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($grd); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn-filter">🔎 Filter</button>
          <a href="manage_books.php" class="btn-reset">↺ Reset</a>
          <a href="add_book.php" class="btn-add">➕ Add New Book</a>
        </form>
      </div>

      <div class="table-section">
        <div class="table-header">
          <h2>📋 Books List</h2>
          <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="printBooksReport()" class="btn-print">🖨️ Print</button>
          </div>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
          <div class="table-container">
            <table class="books-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Title</th>
                  <th>Author</th>
                  <th>ISBN</th>
                  <th>Category</th>
                  <th>Grade</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php $counter = 1; while ($book = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $counter++; ?></td>
                    <td>
                      <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                    </td>
                    <td class="book-author"><?php echo htmlspecialchars($book['author'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($book['isbn'] ?: '-'); ?></td>
                    <td>
                      <?php if ($book['category']): ?>
                        <span class="category-badge"><?php echo htmlspecialchars($book['category']); ?></span>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($book['grade']): ?>
                        <span class="grade-badge"><?php echo htmlspecialchars($book['grade']); ?></span>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                    <td class="price"><?php echo $currency_symbol; ?><?php echo number_format($book['price'], 2); ?></td>
                    <td>
                      <?php 
                        $qty = intval($book['total_quantity']);
                        $badge_class = 'in-stock';
                        if ($qty == 0) {
                          $badge_class = 'out-of-stock';
                        } elseif ($qty < 10) {
                          $badge_class = 'low-stock';
                        }
                      ?>
                      <span class="quantity-badge <?php echo $badge_class; ?>">
                        <?php echo $qty; ?>
                      </span>
                    </td>
                    <td>
                      <div class="action-btns">
                        <a href="edit_book.php?id=<?php echo $book['book_id']; ?>" class="btn-action btn-edit">✏️ Edit</a>
                        <button class="btn-action btn-delete delete-book-btn" data-id="<?php echo $book['book_id']; ?>" data-title="<?php echo htmlspecialchars($book['title']); ?>">🗑️ Delete</button>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>No Books Found</h3>
            <p>No books match your search criteria. Try adjusting your filters or add a new book.</p>
            <a href="add_book.php" class="btn-add">➕ Add New Book</a>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <?php include('../includes/footer.php'); ?>

  <!-- Delete Book Modal -->
  <div id="deleteBookModal" class="modal-overlay">
    <div class="modal-content">
      <span class="modal-close">&times;</span>
      <div class="modal-header">🗑️ Delete Book</div>
      <div class="modal-body">
        <p>Are you sure you want to permanently delete <strong id="modalBookTitle"></strong>?</p>
        <p style="color: #dc3545; font-weight: 500;">This will also remove it from inventory. This action cannot be undone.</p>
      </div>
      <form id="deleteBookForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_book">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="book_id" id="modalBookId" value="">
      </form>
      <div class="modal-buttons">
        <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
        <button type="button" class="btn-modal-confirm" onclick="confirmDeleteBook()">Delete Book</button>
      </div>
    </div>
  </div>

  <script>
    // Modal functions
    function showDeleteModal(id, title) {
      document.getElementById('modalBookId').value = id;
      document.getElementById('modalBookTitle').textContent = title;
      document.getElementById('deleteBookModal').style.display = 'flex';
      document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    function closeModal() {
      document.getElementById('deleteBookModal').style.display = 'none';
      document.body.style.overflow = ''; // Restore scrolling
    }

    function confirmDeleteBook() {
      document.getElementById('deleteBookForm').submit();
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Delete buttons
      document.querySelectorAll('.delete-book-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const id = this.dataset.id;
          const title = this.dataset.title;
          showDeleteModal(id, title);
        });
      });

      // Modal close handlers
      const modal = document.getElementById('deleteBookModal');
      const closeBtn = document.querySelector('.modal-close');
      closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
      });

      // ESC key close
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
          closeModal();
        }
      });
    });
  </script>

</body>
</html>
