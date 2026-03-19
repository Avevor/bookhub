<?php
// Include session config and check
include('../includes/session_config.php');
include('../includes/session_check.php');

// Require valid session - redirects to login if expired
requireValidSession();

include('../config/db.php');
include('../includes/permission_helper.php');

$user_role_id = $_SESSION['role_id'] ?? 0;

// Check access - allow only Sales role (role_id = 2) and Admin (role_id = 1)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}

$username = $_SESSION['username'];

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Build the query with filters
$query = "SELECT b.*, COALESCE(SUM(i.quantity), 0) as total_quantity 
          FROM books b 
          LEFT JOIN inventory i ON b.book_id = i.book_id 
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%' OR b.isbn LIKE '%$search%')";
}

if (!empty($category_filter)) {
    $query .= " AND b.category = '$category_filter'";
}

$query .= " GROUP BY b.book_id ORDER BY b.book_id DESC";

$result = $conn->query($query);

// Get categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM books ORDER BY category");

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
  <title>Manage Books - Book Hub</title>
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
    .btn-add {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      padding: 12px 25px;
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
    .books-table th {
      position: sticky;
      top: 0;
      background: #f8f9fa;
      z-index: 10;
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
    .books-table th {
      position: sticky;
      top: 0;
      background: #f8f9fa;
      z-index: 10;
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
        <img src="../images/manage book.jpg" alt="manage book" class="dashboard-title-logo">Manage Books
      </h1>
      <p class="page-subtitle">View and search all books in the inventory</p>
    </div>


    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-content">
          <h3><?php 
            $total_books = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
            echo $total_books; 
          ?></h3>
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
          <h3><?php 
            $cat_count = $conn->query("SELECT COUNT(DISTINCT category) as count FROM books WHERE category IS NOT NULL AND category != ''")->fetch_assoc()['count'];
            echo $cat_count;
          ?></h3>
          <p>Categories</p>
        </div>
      </div>
    </div>

    <div class="filter-section">
      <form method="GET" action="" class="filter-form">
        <div class="filter-group">
          <label for="search">🔍 Search</label>
          <input type="text" id="search" name="search" placeholder="Search by title, author or ISBN" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
          <label for="category">🏷️ Category</label>
          <select id="category" name="category">
            <option value="">All Categories</option>
            <?php 
            // Reset pointer to beginning
            $categories_result->data_seek(0);
            while ($cat = $categories_result->fetch_assoc()): 
            ?>
              <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['category']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <button type="submit" class="btn-filter">🔎 Filter</button>
        <a href="sales_manage_books.php" class="btn-reset">↺ Reset</a>
      </form>
    </div>


    <div class="table-section">
      <div class="table-header">
        <h2>📋 Books List</h2>
        <span style="opacity: 0.9;"><?php echo $result->num_rows; ?> book(s) found</span>
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
                    <?php if (has_action_access($conn, $user_role_id, 'manage_books', 'edit')): ?>
                      <div class="action-btns">
                        <a href="sales_edit_book.php?id=<?php echo $book['book_id']; ?>" class="btn-action btn-edit">✏️ Edit</a>
                      </div>
                    <?php endif; ?>
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
          <a href="sales_add_book.php" class="btn-add">➕ Add New Book</a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>


<?php include('../includes/footer.php'); ?>

</body>
</html>
