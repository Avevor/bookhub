<?php
session_start();
include('../config/db.php');

// ✅ Restrict access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// ✅ Restrict non-admins
if ($_SESSION['role_id'] != 1) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied ❌</h2>";
    exit();
}

$message = '';
$message_type = '';

// Get book ID from URL
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    header("Location: manage_books.php");
    exit();
}

// Fetch book data
$book_sql = "SELECT b.*, COALESCE(i.quantity, 0) as quantity 
             FROM books b 
             LEFT JOIN inventory i ON b.book_id = i.book_id 
             WHERE b.book_id = ?";
$stmt = $conn->prepare($book_sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_books.php");
    exit();
}

$book = $result->fetch_assoc();
$result->free();
$stmt->close();

// Get grades from settings (for checkboxes)
$grades_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'grades'";
$grades_result = $conn->query($grades_sql);
$grades_setting = 'Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8,Grade 9';
if ($grades_result && $grades_result->num_rows > 0) {
    $grades_setting = $grades_result->fetch_assoc()['setting_value'];
}
if ($grades_result) $grades_result->free();
$grades_list = array_map('trim', explode(',', $grades_setting));

// Get currency setting
$currency_setting = 'USD';
$currency_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
if ($currency_result && $currency_result->num_rows > 0) {
    $currency_setting = $currency_result->fetch_assoc()['setting_value'];
}
if ($currency_result) $currency_result->free();

// Currency symbol mapping
$currency_symbols = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'GHS' => '₵'
];
$currency_symbol = $currency_symbols[$currency_setting] ?? '$';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn_input = trim($_POST['isbn']);
    $isbn = !empty($isbn_input) ? $isbn_input : null;
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $publisher = trim($_POST['publisher']);
    $quantity = intval($_POST['quantity']);
    $single_grade = trim($_POST['single_grade'] ?? '');
    $selected_grades = $_POST['grades'] ?? [];
    
    $grades_to_update = [];
    if (!empty($single_grade)) {
        $grades_to_update = [$single_grade];
    } elseif (!empty($selected_grades)) {
        $grades_to_update = array_map('trim', $selected_grades);
        $grades_to_update = array_filter($grades_to_update);
    }
    
    if (empty($grades_to_update)) {
        $message = 'Please select grade(s)';
        $message_type = 'error';
    } elseif (empty($title)) {
        $message = 'Title is required!';
        $message_type = 'error';
    } elseif ($price <= 0) {
        $message = 'Price must be greater than zero';
        $message_type = 'error';
    } elseif (empty($category)) {
        $message = 'Category is required';
        $message_type = 'error';
    } else {
        // Check ISBN (exclude current book)
        if ($isbn !== null && $isbn !== $book['isbn']) {
            $check_isbn = $conn->prepare("SELECT book_id FROM books WHERE isbn = ? AND book_id != ?");
            $check_isbn->bind_param("si", $isbn, $book_id);
            $check_isbn->execute();
            $isbn_result = $check_isbn->get_result();
            if ($isbn_result->num_rows > 0) {
                $message = 'ISBN already exists!';
                $message_type = 'error';
            }
            $isbn_result->free();
            $check_isbn->close();
        }
        
        if (empty($message)) {
            // Update existing book (first grade)
            $first_grade = $grades_to_update[0];
            $update_sql = "UPDATE books SET title=?, author=?, isbn=?, price=?, description=?, category=?, grade=?, publisher=?, updated_at=NOW() WHERE book_id=?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sssdssssi", $title, $author, $isbn, $price, $description, $category, $first_grade, $publisher, $book_id);
            
            if ($stmt->execute()) {
                // Add/update other grades (duplicate book rows)
                $dup_check_sql = "SELECT book_id FROM books WHERE title = ? AND author = ? AND COALESCE(isbn, '') = COALESCE(?, '') AND grade = ?";
                foreach (array_slice($grades_to_update, 1) as $add_grade) {
                    $dup_check_sql = "SELECT book_id FROM books WHERE title = ? AND author = ? AND COALESCE(isbn, '') = COALESCE(?, '') AND grade = ?";
                    $dup_check_stmt = $conn->prepare($dup_check_sql);
                    if ($dup_check_stmt) {
                        $isbn_or_null = $isbn ?? '';
                        $dup_check_stmt->bind_param("ssss", $title, $author, $isbn_or_null, $add_grade);
                        $dup_check_stmt->execute();
                        $dup_result = $dup_check_stmt->get_result();
                        if ($dup_result->num_rows == 0) {
                            $dup_sql = "INSERT INTO books (title, author, isbn, price, description, category, grade, publisher, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $dup_stmt = $conn->prepare($dup_sql);
                            $dup_stmt->bind_param("sssdsssss", $title, $author, $isbn, $price, $description, $category, $add_grade, $publisher, $book['image']);
                            $dup_stmt->execute();
                            $dup_stmt->close();
                        }
                        $dup_result->free();
                        $dup_check_stmt->close();
                    }
                }
                
                // Update inventory
                $check_inv_sql = "SELECT inventory_id FROM inventory WHERE book_id=?";
                $check_inv = $conn->prepare($check_inv_sql);
                $check_inv->bind_param("i", $book_id);
                $check_inv->execute();
                $inv_result = $check_inv->get_result();
                if ($inv_result->num_rows > 0) {
                    $update_inv_sql = "UPDATE inventory SET quantity=?, last_updated=NOW() WHERE book_id=?";
                    $update_inv = $conn->prepare($update_inv_sql);
                    $update_inv->bind_param("ii", $quantity, $book_id);
                    $update_inv->execute();
                    $update_inv->close();
                } else {
                    $insert_inv_sql = "INSERT INTO inventory (book_id, quantity) VALUES (?, ?)";
                    $insert_inv = $conn->prepare($insert_inv_sql);
                    $insert_inv->bind_param("ii", $book_id, $quantity);
                    $insert_inv->execute();
                    $insert_inv->close();
                }
                $inv_result->free();
                $check_inv->close();
                
                $message = 'Book updated successfully!';
                $message_type = 'success';
                // Refresh book data for display
                $refresh_sql = "SELECT b.*, COALESCE(i.quantity, 0) as quantity FROM books b LEFT JOIN inventory i ON b.book_id = i.book_id WHERE b.book_id = ?";
                $refresh_stmt = $conn->prepare($refresh_sql);
                $refresh_stmt->bind_param("i", $book_id);
                $refresh_stmt->execute();
                $refresh_result = $refresh_stmt->get_result();
                $book = $refresh_result->fetch_assoc();
                $refresh_result->free();
                $refresh_stmt->close();
            } else {
                $message = 'Error updating book: ' . $conn->error;
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Book - Book Hub</title>
  <link rel="icon" href="../images/school.jpeg" type="image/jpeg" />
  <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
  <style>
    .form-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    .form-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 30px;
    }
    .form-title {
      color: #003366;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid #e0e0e0;
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group.full-width {
      grid-column: 1 / -1;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
      box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #003366;
    }
    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }
    .btn-submit {
      background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
      color: white;
      border: none;
      padding: 14px 30px;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(255,193,7,0.4);
    }
    .btn-cancel {
      background: #6c757d;
      color: white;
      border: none;
      padding: 14px 30px;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
      margin-left: 10px;
      text-decoration: none;
      display: inline-block;
    }
    .btn-cancel:hover {
      background: #5a6268;
      transform: translateY(-2px);
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
    .required::after {
      content: ' *';
      color: red;
    }
    .book-id-display {
      background: #f8f9fa;
      padding: 10px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      color: #666;
      font-size: 0.9em;
    }
    /* Grade Selection */
    .grades-checkboxes {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-top: 15px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
      max-height: 280px;
      overflow-y: auto;
    }
    @media (max-width: 768px) {
      .grades-checkboxes {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    .grade-checkbox {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px;
      background: white;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s ease;
      border: 2px solid transparent;
    }
    .grade-checkbox:hover {
      background: #e3f2fd;
      border-color: #003366;
    }
    .grade-checkbox input[type="checkbox"] {
      width: 18px;
      height: 18px;
      margin: 0;
    }
    .grades-select-all {
      display: flex;
      gap: 10px;
      margin: 10px 0;
    }
    .btn-small {
      padding: 6px 12px;
      font-size: 0.9em;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      background: #003366;
      color: white;
    }
    .btn-small:hover {
      background: #005580;
    }
    .single-grade-quick {
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #e0e0e0;
    }
    .grade-controls {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 15px;
    }
    .all-grades-option label {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      color: #003366;
      cursor: pointer;
    }
    .all-grades-option input[type="checkbox"] {
      width: 20px;
      height: 20px;
    }
  </style>
</head>
<body>

<?php include('../includes/header.php'); ?>
<?php include('../includes/sidebar.php'); ?>

<div class="dashboard-container">
  <div class="main-content">
    <div class="form-container">
      <div class="form-card">
        <h1 class="form-title">✏️ Edit Book</h1>
        <div class="book-id-display">
          Book ID: <strong>#<?php echo $book_id; ?></strong> | 
          Created: <?php echo date('M d, Y', strtotime($book['created_at'])); ?> |
          Last Updated: <?php echo date('M d, Y H:i', strtotime($book['updated_at'])); ?>
        </div>
        
        <?php if (!empty($message)): ?>
          <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" action="">
          <div class="form-grid">
            <div class="form-group">
              <label for="title" class="required">Book Title</label>
              <input type="text" id="title" name="title" required 
                     value="<?php echo htmlspecialchars($book['title']); ?>" 
                     placeholder="Enter book title">
            </div>
            
            <div class="form-group">
              <label for="author">Author</label>
              <input type="text" id="author" name="author" 
                     value="<?php echo htmlspecialchars($book['author'] ?? ''); ?>" 
                     placeholder="Enter author name">
            </div>
            
            <div class="form-group">
              <label for="isbn">ISBN</label>
              <input type="text" id="isbn" name="isbn" 
                     value="<?php echo htmlspecialchars($book['isbn'] ?? ''); ?>" 
                     placeholder="Enter ISBN number">
            </div>
            
            <div class="form-group">
              <label for="price" class="required">Price (<?php echo $currency_symbol; ?>)</label>
              <input type="number" id="price" name="price" step="0.01" min="0" required 
                     value="<?php echo number_format($book['price'], 2); ?>" 
                     placeholder="Enter price">
            </div>
            
            <div class="form-group">
              <label for="category" class="required">Category</label>
              <input type="text" id="category" name="category" required 
                     value="<?php echo htmlspecialchars($book['category'] ?? ''); ?>" 
                     placeholder="e.g., Fiction, Science, History">
            </div>
            
            <!-- Grade Assignment -->
            <div class="form-group full-width">
              <label>Grade Assignment <span class="required">*</span></label>
              
              <div class="grade-controls">
                <div class="single-grade-quick">
                  <label style="font-size: 0.9em; color: #666;">Quick Single:</label>
                  <select id="single_grade" name="single_grade" style="width: 100%; margin-top: 5px;">
                    <option value="">Select single grade</option>
                    <?php foreach ($grades_list as $grade_option): ?>
                      <option value="<?php echo htmlspecialchars(trim($grade_option)); ?>" <?php echo ($book['grade'] == trim($grade_option)) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(trim($grade_option)); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="all-grades-option">
                  <label>
                    <input type="checkbox" id="select_all_grades" onchange="toggleAllGrades()" <?php echo (strpos($book['grade'], 'All') !== false) ? 'checked' : ''; ?>>
                    <span>Select All Grades</span>
                  </label>
                </div>
              </div>
              
              <div class="grades-select-all">
                <button type="button" onclick="selectAllGrades(true)" class="btn-small">Select All</button>
                <button type="button" onclick="selectAllGrades(false)" class="btn-small">Clear All</button>
              </div>
              
              <div id="grades_checkboxes" class="grades-checkboxes">
                <?php foreach ($grades_list as $grade_option): ?>
                  <?php if (trim($grade_option)): ?>
                    <label class="grade-checkbox">
                      <input type="checkbox" name="grades[]" value="<?php echo htmlspecialchars(trim($grade_option)); ?>" <?php echo ($book['grade'] == trim($grade_option)) ? 'checked' : ''; ?>>
                      <?php echo htmlspecialchars(trim($grade_option)); ?>
                    </label>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-group">
              <label for="publisher">Publisher</label>
              <input type="text" id="publisher" name="publisher" 
                     value="<?php echo htmlspecialchars($book['publisher'] ?? ''); ?>" 
                     placeholder="Enter publisher name">
            </div>
                          
            <div class="form-group">
              <label for="quantity">Stock Quantity</label>
              <input type="number" id="quantity" name="quantity" min="0" 
                     value="<?php echo intval($book['quantity']); ?>" 
                     placeholder="Enter quantity">
            </div>
                          
            <div class="form-group full-width">
              <label for="description">Description</label>
              <textarea id="description" name="description" placeholder="Enter book description"><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
            </div>
          </div>
          
          <button type="submit" class="btn-submit">💾 Update Book</button>
          <a href="manage_books.php" class="btn-cancel">← Back to Books</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
  // Grade JS - Exact same as add_book.php
  function toggleAllGrades() {
    const selectAllCheckbox = document.getElementById('select_all_grades');
    const checkboxes = document.querySelectorAll('input[name="grades[]"]');
    checkboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
  }

  function selectAllGrades(select) {
    const checkboxes = document.querySelectorAll('input[name="grades[]"]');
    checkboxes.forEach(cb => cb.checked = select);
    document.getElementById('select_all_grades').checked = select;
  }

  // Single vs Multi toggle
  document.getElementById('single_grade').addEventListener('change', function() {
    const singleVal = this.value;
    const multiSection = document.querySelector('.multi-grades-section') || document.querySelector('.grades-select-all');
    if (singleVal) {
      if (multiSection) multiSection.style.opacity = '0.5';
      const checkboxes = document.querySelectorAll('input[name="grades[]"]');
      checkboxes.forEach(cb => cb.checked = false);
    } else {
      if (multiSection) multiSection.style.opacity = '1';
    }
  });

  // Checkboxes change → clear single
  document.addEventListener('change', function(e) {
    if (e.target.name === 'grades[]') {
      const anyChecked = Array.from(document.querySelectorAll('input[name="grades[]"]:checked')).length > 0;
      if (anyChecked) {
        document.getElementById('single_grade').value = '';
      }
    }
  });

  // Form validation
  document.querySelector('form').addEventListener('submit', function(e) {
    const singleVal = document.getElementById('single_grade').value;
    const checkboxesChecked = document.querySelectorAll('input[name="grades[]"]:checked').length > 0;
    if (!singleVal && !checkboxesChecked) {
      e.preventDefault();
      alert('Please select a single grade OR at least one grade checkbox');
    }
  });
</script>

</body>
</html>
