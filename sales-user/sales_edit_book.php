<?php
session_start();
include('../config/db.php');

// Restrict access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_role_id = $_SESSION['role_id'];
if ($user_role_id != 1 && $user_role_id != 2) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied</h2>";
    exit();
}

// Simplified permission check for sales
if ($user_role_id == 2) {
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'books_edit'";
    $access_result = $conn->query($access_sql);
    if (!$access_result || $access_result->num_rows == 0 || $access_result->fetch_assoc()['sales_enabled'] != 1) {
        echo "<h2 style='color:red;text-align:center;margin-top:50px;'>Access Denied</h2>";
        exit();
    }
}

$message = '';
$message_type = '';

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($book_id <= 0) {
    header("Location: sales_manage_books.php");
    exit();
}

// Fetch book data
$book_sql = "SELECT b.*, COALESCE(i.quantity, 0) as quantity FROM books b LEFT JOIN inventory i ON b.book_id = i.book_id WHERE b.book_id = ?";
$stmt = $conn->prepare($book_sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: sales_manage_books.php");
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

// Grades
$grades_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'grades'";
$grades_result = $conn->query($grades_sql);
$grades_setting = 'Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12';
if ($grades_result && $grades_result->num_rows > 0) {
    $grades_setting = $grades_result->fetch_assoc()['setting_value'];
}
$grades_list = array_map('trim', explode(',', $grades_setting));

// Currency
$currency_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
$currency_symbol = '$';
if ($currency_result && $currency_result->num_rows > 0) {
    $currency_setting = $currency_result->fetch_assoc()['setting_value'];
    $currency_symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'GHS' => '₵'];
    $currency_symbol = $currency_symbols[$currency_setting] ?? '$';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']) ?: null;
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $publisher = trim($_POST['publisher']);
    $single_grade = trim($_POST['single_grade'] ?? '');
    $selected_grades = $_POST['grades'] ?? [];
    
    $grades_to_update = [];
    if (!empty($single_grade)) {
        $grades_to_update = [$single_grade];
    } elseif (!empty($selected_grades)) {
        $grades_to_update = array_map('trim', $selected_grades);
    }
    
    if (empty($grades_to_update)) {
        $message = 'Please select grade(s)';
        $message_type = 'error';
    } elseif (empty($title) || $price <= 0 || empty($category)) {
        $message = 'Please fill required fields';
        $message_type = 'error';
    } else {
        // ISBN check
        if ($isbn !== null && $isbn !== $book['isbn']) {
            $check_stmt = $conn->prepare("SELECT book_id FROM books WHERE isbn = ? AND book_id != ?");
            $check_stmt->bind_param("si", $isbn, $book_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $message = 'ISBN already exists';
                $message_type = 'error';
            }
            $check_stmt->close();
        }
        
        if (empty($message)) {
            $first_grade = $grades_to_update[0];
            $update_sql = "UPDATE books SET title=?, author=?, isbn=?, price=?, description=?, category=?, grade=?, publisher=?, updated_at=NOW() WHERE book_id=?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sssdssssi", $title, $author, $isbn, $price, $description, $category, $first_grade, $publisher, $book_id);
            if ($stmt->execute()) {
                $message = 'Book updated successfully!';
                $message_type = 'success';
                // Refresh book
                $refresh_sql = "SELECT b.*, COALESCE(i.quantity, 0) as quantity FROM books b LEFT JOIN inventory i ON b.book_id = i.book_id WHERE b.book_id = ?";
                $refresh_stmt = $conn->prepare($refresh_sql);
                $refresh_stmt->bind_param("i", $book_id);
                $refresh_stmt->execute();
                $book = $refresh_stmt->get_result()->fetch_assoc();
                $refresh_stmt->close();
            } else {
                $message = 'Error updating book';
                $message_type = 'error';
            }
            $stmt->close();
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
    .form-container { max-width: 800px; margin: 0 auto; padding: 20px; }
    .form-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 30px; }
    .form-title { color: #003366; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e0e0e0; }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #003366; }
    .form-group textarea { resize: vertical; min-height: 100px; }
    .btn-submit { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; border: none; padding: 14px 30px; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(255,193,7,0.4); }
    .btn-cancel { background: #6c757d; color: white; border: none; padding: 14px 30px; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; margin-left: 10px; text-decoration: none; display: inline-block; }
    .btn-cancel:hover { background: #5a6268; transform: translateY(-2px); }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .required::after { content: ' *'; color: red; }
    .book-id-display { background: #f8f9fa; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; color: #666; font-size: 0.9em; }
    .grades-checkboxes { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0; max-height: 280px; overflow-y: auto; }
    @media (max-width: 768px) { .grades-checkboxes { grid-template-columns: repeat(2, 1fr); } }
    .grade-checkbox { display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; border: 2px solid transparent; }
    .grade-checkbox:hover { background: #e3f2fd; border-color: #003366; }
    .grade-checkbox input[type="checkbox"] { width: 18px; height: 18px; margin: 0; }
    .grades-select-all { display: flex; gap: 10px; margin: 10px 0; }
    .btn-small { padding: 6px 12px; font-size: 0.9em; border: none; border-radius: 4px; cursor: pointer; background: #003366; color: white; }
    .btn-small:hover { background: #005580; }
    .single-grade-quick { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
    .grade-controls { display: flex; align-items: center; gap: 20px; margin-bottom: 15px; }
    .all-grades-option label { display: flex; align-items: center; gap: 10px; font-weight: 600; color: #003366; cursor: pointer; }
    .all-grades-option input[type="checkbox"] { width: 20px; height: 20px; }
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
            <div class="form-group full-width">
              <label for="description">Description</label>
              <textarea id="description" name="description" placeholder="Enter book description"><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
            </div>
          </div>
          <button type="submit" class="btn-submit">Update Book</button>
          <a href="sales_manage_books.php" class="btn-cancel">Back to Books</a>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include('../includes/footer.php'); ?>
<script>
function toggleAllGrades() {
  var selectAll = document.getElementById('select_all_grades');
  var checkboxes = document.querySelectorAll('input[name="grades[]"]');
  checkboxes.forEach(function(cb) {
    cb.checked = selectAll.checked;
  });
}
function selectAllGrades(selected) {
  var checkboxes = document.querySelectorAll('input[name="grades[]"]');
  checkboxes.forEach(function(cb) {
    cb.checked = selected;
  });
  document.getElementById('select_all_grades').checked = selected;
}
document.getElementById('single_grade').addEventListener('change', function() {
  if (this.value) {
    var checkboxes = document.querySelectorAll('input[name="grades[]"]');
    checkboxes.forEach(function(cb) { cb.checked = false; });
  }
});
document.querySelector('form').addEventListener('submit', function(e) {
  var singleVal = document.getElementById('single_grade').value;
  var checkedCount = document.querySelectorAll('input[name="grades[]"]:checked').length;
  if (!singleVal && checkedCount == 0) {
    e.preventDefault();
    alert('Please select grade');
  }
});
</script>
</body>
</html>

