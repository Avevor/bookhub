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
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'add_book'";
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


$message = '';
$message_type = '';

// Get grades from settings
$grades_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'grades'";
$grades_result = $conn->query($grades_sql);
$grades_setting = 'Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12';
if ($grades_result && $grades_result->num_rows > 0) {
    $grades_setting = $grades_result->fetch_assoc()['setting_value'];
}
$grades_list = array_map('trim', explode(',', $grades_setting));

// Get categories from settings
$categories_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'categories'";
$categories_result = $conn->query($categories_sql);
$categories_setting = 'Fiction,Non-Fiction,Science,Mathematics,History,Geography,English,Art,Music,Physical Education';
if ($categories_result && $categories_result->num_rows > 0) {
    $categories_setting = $categories_result->fetch_assoc()['setting_value'];
}
$categories_list = array_map('trim', explode(',', $categories_setting));

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

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $isbn_input = trim($_POST['isbn']);
        // Convert empty ISBN to NULL for database (avoids duplicate key error with UNIQUE constraint)
        $isbn = !empty($isbn_input) ? $isbn_input : null;
        $price = floatval($_POST['price']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $publisher = trim($_POST['publisher']);
        $quantity = intval($_POST['quantity']);
        $single_grade = trim($_POST['single_grade'] ?? '');
        $selected_grades = $_POST['grades'] ?? [];
        $grades_to_add = [];
        
        if (!empty($single_grade)) {
            $grades_to_add = [$single_grade];
        } elseif (!empty($selected_grades)) {
            $grades_to_add = array_map('trim', $selected_grades);
            $grades_to_add = array_filter($grades_to_add); // Remove empty
        }

    // Handle image upload
    $image_path = null;
    
    // Check if webcam photo was captured (base64)
    if (!empty($_POST['webcam_image'])) {
        $webcam_image = $_POST['webcam_image'];
        // Remove the data:image prefix if present
        if (strpos($webcam_image, 'data:image') !== false) {
            $webcam_image = preg_replace('/^data:image\/\w+;base64,/', '', $webcam_image);
            $webcam_image = base64_decode($webcam_image);
            
            // Create unique filename
            $timestamp = time();
            $random = substr(md5(uniqid(rand(), true)), 0, 8);
            $filename = "book_{$timestamp}_{$random}.jpg";
            $target_path = "../uploads/photos/" . $filename;
            
            if (file_put_contents($target_path, $webcam_image)) {
                $image_path = $filename;
            }
        }
    }
    // Check if file was uploaded
    elseif (!empty($_FILES['book_image']['name']) && $_FILES['book_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/photos/";
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $_FILES['book_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $timestamp = time();
            $random = substr(md5(uniqid(rand(), true)), 0, 8);
            $new_filename = "book_{$timestamp}_{$random}." . $file_ext;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_path)) {
                $image_path = $new_filename;
            }
        }
    }
    
    // Validate required fields
    if (empty($title)) {
        $message = 'Title is required!';
        $message_type = 'error';
    } elseif ($price <= 0) {
        $message = 'Price must be greater than zero';
        $message_type = 'error';
    } elseif (empty($category)) {
        $message = 'Category is required';
        $message_type = 'error';
    } elseif (empty($grades_to_add)) {
                $message = 'Please select a single grade OR at least one grade checkbox';
                $message_type = 'error';
    } else {
        // Check if ISBN already exists
        if (!empty($isbn)) {
            $check_isbn = $conn->prepare("SELECT book_id FROM books WHERE isbn = ?");
            $check_isbn->bind_param("s", $isbn);
            $check_isbn->execute();
            $result = $check_isbn->get_result();
            if ($result->num_rows > 0) {
                $message = 'ISBN already exists!';
                $message_type = 'error';
                $check_isbn->close();
            } else {
                $check_isbn->close();
            }
        }
        
        if (empty($message)) {
            // Determine which grades to add the book to
            $grades_to_add = $grades_to_add; // Use calculated grades from single/multi logic (already set)
            
            $books_added = 0;
            $all_success = true;
            $inventory_added = false;
            
            foreach ($grades_to_add as $grade_item) {
                // Insert book for each grade (include image)
                $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, price, description, category, grade, publisher, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdsssss", $title, $author, $isbn, $price, $description, $category, $grade_item, $publisher, $image_path);
                
                if ($stmt->execute()) {
                    $book_id = $stmt->insert_id;
                    
                    // Add to inventory only once (not per grade) - quantity is total stock
                    if (!$inventory_added && $quantity > 0) {
                        $inv_stmt = $conn->prepare("INSERT INTO inventory (book_id, quantity) VALUES (?, ?)");
                        $inv_stmt->bind_param("ii", $book_id, $quantity);
                        $inv_stmt->execute();
                        $inv_stmt->close();
                        $inventory_added = true;
                    }
                    
                    $books_added++;
                } else {
                    $all_success = false;
                }
                $stmt->close();
            }
            
            if ($all_success) {
                if ($books_added > 1) {
                    $message = "Book added successfully to $books_added grades!";
                } else {
                    $message = 'Book added successfully!';
                }
                $message_type = 'success';
            } else {
                $message = 'Error adding some books. Please check the data and try again.';
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
  <title>Add Book - Book Hub</title>
  <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
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
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
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
    .all-grades-option {
      background: #e8f4fd;
      padding: 12px 15px;
      border-radius: 8px;
      border: 2px solid #003366;
      margin-bottom: 10px;
    }
    .all-grades-option label {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      color: #003366;
      cursor: pointer;
      margin: 0;
    }
    /* Grade Checkboxes Styles */
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
    .multi-grades-section {
      border-top: 1px solid #e0e0e0;
      padding-top: 15px;
    }
    
    /* Image Upload Styles */
    .image-upload-section {
      border: 2px dashed #ccc;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      background: #fafafa;
      margin-bottom: 15px;
    }
    .image-upload-section:hover {
      border-color: #003366;
      background: #f0f7ff;
    }
    .image-preview-container {
      margin-top: 15px;
      display: none;
    }
    .image-preview-container.has-image {
      display: block;
    }
    .image-preview {
      max-width: 200px;
      max-height: 200px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .image-buttons {
      margin-top: 15px;
      display: flex;
      gap: 10px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .btn-webcam {
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-webcam:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }
    .btn-remove-image {
      background: #dc3545;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 0.9em;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-remove-image:hover {
      background: #c82333;
    }
    .file-input-wrapper {
      margin-top: 10px;
    }
    .file-input-wrapper input[type="file"] {
      display: none;
    }
    .btn-upload {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-upload:hover {
      background: #5a6268;
    }
    .image-source-info {
      color: #666;
      font-size: 0.9em;
      margin-top: 8px;
    }
    
    /* Webcam Modal Styles */
    .webcam-modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      justify-content: center;
      align-items: center;
    }
    .webcam-modal.active {
      display: flex;
    }
    .webcam-modal-content {
      background: white;
      border-radius: 15px;
      padding: 25px;
      max-width: 500px;
      width: 90%;
      text-align: center;
    }
    .webcam-modal-title {
      color: #003366;
      margin-bottom: 20px;
      font-size: 1.5em;
    }
    .webcam-video-container {
      position: relative;
      width: 100%;
      max-width: 400px;
      margin: 0 auto;
      border-radius: 10px;
      overflow: hidden;
      background: #000;
    }
    .webcam-video-container video,
    .webcam-video-container canvas {
      width: 100%;
      display: block;
    }
    .webcam-controls {
      margin-top: 20px;
      display: flex;
      gap: 10px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .btn-capture {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-retake {
      background: #ffc107;
      color: #333;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: 600;
      cursor: pointer;
      display: none;
    }
    .btn-close-webcam {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: 600;
      cursor: pointer;
    }
    .webcam-loading {
      color: white;
      padding: 20px;
    }
    .webcam-error {
      color: #dc3545;
      padding: 15px;
      display: none;
    }
    .webcam-success {
      color: #28a745;
      padding: 15px;
      display: none;
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
          <h1 class="form-title">📚 Add New Book</h1>
          
          <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
              <?php echo $message; ?>
            </div>
          <?php endif; ?>
          
          <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-grid">
              <!-- Image Upload Section -->
              <div class="form-group full-width">
                <label>Book Image (Optional)</label>
                <div class="image-upload-section" id="imageUploadSection">
                  <div class="image-buttons" id="imageButtons">
                    <button type="button" class="btn-webcam" id="useWebcamBtn">📷 Take Photo</button>
                    <label for="bookImage" class="btn-upload">📁 Upload Image</label>
                    <input type="file" id="bookImage" name="book_image" accept="image/*" class="file-input-wrapper">
                  </div>
                  <p class="image-source-info">Take a photo with your camera or upload an image from your device</p>
                  <div class="image-preview-container" id="imagePreviewContainer">
                    <img id="imagePreview" class="image-preview" src="" alt="Book Preview">
                    <div class="image-buttons">
                      <button type="button" class="btn-remove-image" id="removeImageBtn">Remove Image</button>
                    </div>
                  </div>
                </div>
                <!-- Hidden field to store webcam image -->
                <input type="hidden" id="webcamImage" name="webcam_image" value="">
              </div>
              
              <div class="form-group">
                <label for="title" class="required">Book Title</label>
                <input type="text" id="title" name="title" required placeholder="Enter book title">
              </div>
              
              <div class="form-group">
                <label for="author">Author</label>
                <input type="text" id="author" name="author" placeholder="Enter author name">
              </div>
              
              <div class="form-group">
                <label for="isbn">ISBN</label>
                <input type="text" id="isbn" name="isbn" placeholder="Enter ISBN number">
              </div>
              
              <div class="form-group">
                <label for="price" class="required">Price (<?php echo $currency_symbol; ?>)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="Enter price">
              </div>
              
              <div class="form-group">
                <label for="category" class="required">Category</label>
                <select id="category" name="category" required>
                  <option value="">Select Category</option>
                  <?php foreach ($categories_list as $cat_option): ?>
                    <?php if (trim($cat_option)): ?>
                      <option value="<?php echo htmlspecialchars(trim($cat_option)); ?>">
                        <?php echo htmlspecialchars(trim($cat_option)); ?>
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group full-width">
                <label>Grade Assignment <span class="required">*</span></label>
                <div style="display: flex; gap: 20px; margin-bottom: 15px; align-items: center;">
                  <!-- Quick Single Grade - Left -->
                  <div style="flex: 1;">
                    <label>Quick Single:</label>
                    <select id="single_grade" name="single_grade" style="width: 100%;">
                      <option value="">Select grade</option>
                      <?php foreach ($grades_list as $grade_option): ?>
                        <?php if (trim($grade_option)): ?>
                          <option value="<?php echo htmlspecialchars(trim($grade_option)); ?>">
                            <?php echo htmlspecialchars(trim($grade_option)); ?>
                          </option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  
                  <!-- OR Select All Checkbox - Right -->
                  <div style="flex: 1; display: flex; align-items: center;">
                    <div class="all-grades-option">
                      <label>
                        <input type="checkbox" id="select_all_grades" onchange="toggleAllGrades()">
                        <span style="font-size: 1em;">Select All Grades</span>
                      </label>
                    </div>
                  </div>
                </div>
                
                <!-- Select/Clear Buttons -->
                <div class="grades-select-all" style="margin-bottom: 15px;">
                  <button type="button" onclick="selectAllGrades(true)" class="btn-small" style="background: #28a745;">Select All</button>
                  <button type="button" onclick="selectAllGrades(false)" class="btn-small" style="background: #dc3545;">Clear All</button>
                </div>
                
                <!-- Main Checkbox Grid - Full Width Below -->
                <div id="grades_checkboxes" class="grades-checkboxes">
                  <?php foreach ($grades_list as $grade_option): ?>
                    <?php if (trim($grade_option)): ?>
                      <label class="grade-checkbox">
                        <input type="checkbox" name="grades[]" value="<?php echo htmlspecialchars(trim($grade_option)); ?>">
                        <?php echo htmlspecialchars(trim($grade_option)); ?>
                      </label>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="form-group">
                <label for="publisher">Publisher</label>
                <input type="text" id="publisher" name="publisher" placeholder="Enter publisher name">
              </div>
                            
              <div class="form-group">
                <label for="quantity" class="required">Initial Quantity (Total Stock)</label>
                <input type="number" id="quantity" name="quantity" min="0" value="0" required placeholder="Enter total quantity">
              </div>
                            
              <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Enter book description"></textarea>
              </div>
            </div>
            
            <button type="submit" class="btn-submit">➕ Add Book</button>
            <a href="admin_dashboard.php" class="btn-cancel">Cancel</a>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Webcam Modal -->
  <div class="webcam-modal" id="webcamModal">
    <div class="webcam-modal-content">
      <h2 class="webcam-modal-title">📷 Capture Book Photo</h2>
      <div class="webcam-video-container" id="webcamContainer">
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display: none;"></canvas>
      </div>
      <div class="webcam-loading" id="webcamLoading">Initializing camera...</div>
      <div class="webcam-error" id="webcamError"></div>
      <div class="webcam-success" id="webcamSuccess">Photo captured! You can retake or use this photo.</div>
      <div class="webcam-controls">
        <button type="button" class="btn-capture" id="captureBtn">Capture Photo</button>
        <button type="button" class="btn-retake" id="retakeBtn">Retake Photo</button>
        <button type="button" class="btn-close-webcam" id="closeWebcamBtn">Cancel</button>
      </div>
    </div>
  </div>

  <?php include('../includes/footer.php'); ?>

  <script>
/* Grade Checkboxes JS - FIXED */
    function toggleAllGrades() {
      const cb = document.getElementById('select_all_grades');
      const checkboxes = document.querySelectorAll('input[name="grades[]"]');
      checkboxes.forEach(checkbox => checkbox.checked = cb.checked);
    }

    function selectAllGrades(select) {
      const checkboxes = document.querySelectorAll('input[name="grades[]"]');
      checkboxes.forEach(checkbox => checkbox.checked = select);
      document.getElementById('select_all_grades').checked = select;
    }

    // Single/Multi toggle
    document.addEventListener('DOMContentLoaded', function() {
      const singleGrade = document.getElementById('single_grade');
      const multiSection = document.querySelector('.multi-grades-section');
      singleGrade.addEventListener('change', function() {
        if (this.value) {
          multiSection.style.opacity = '0.5';
          multiSection.style.pointerEvents = 'none';
        } else {
          multiSection.style.opacity = '1';
          multiSection.style.pointerEvents = 'auto';
        }
      });

      // Checkboxes change → clear single
      document.querySelectorAll('input[name="grades[]"]').forEach(cb => {
        cb.addEventListener('change', function() {
          if (this.checked) singleGrade.value = '';
        });
      });
    });
    
    // Image upload handling
    const bookImageInput = document.getElementById('bookImage');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imageButtons = document.getElementById('imageButtons');
    const webcamImageInput = document.getElementById('webcamImage');
    const removeImageBtn = document.getElementById('removeImageBtn');
    
    bookImageInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          imagePreview.src = e.target.result;
          imagePreviewContainer.classList.add('has-image');
          imageButtons.style.display = 'none';
          webcamImageInput.value = ''; // Clear webcam image if file uploaded
        }
        reader.readAsDataURL(file);
      }
    });
    
    removeImageBtn.addEventListener('click', function() {
      imagePreview.src = '';
      imagePreviewContainer.classList.remove('has-image');
      imageButtons.style.display = 'flex';
      bookImageInput.value = '';
      webcamImageInput.value = '';
    });
    
    // Webcam functionality - Check if browser supports camera API
    const useWebcamBtn = document.getElementById('useWebcamBtn');
    const webcamModal = document.getElementById('webcamModal');
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('captureBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    const closeWebcamBtn = document.getElementById('closeWebcamBtn');
    const webcamLoading = document.getElementById('webcamLoading');
    const webcamError = document.getElementById('webcamError');
    const webcamSuccess = document.getElementById('webcamSuccess');
    
    let stream = null;
    
    // Check if browser supports mediaDevices API
    function isCameraSupported() {
      return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
    
    // Hide webcam button if camera is not supported or not on secure context
    function checkCameraAvailability() {
      const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
      if (!isCameraSupported() || !isSecure) {
        useWebcamBtn.style.display = 'none';
        document.querySelector('.image-source-info').textContent = 'Upload an image from your device (camera requires HTTPS or localhost)';
      }
    }
    
    // Check on page load
    checkCameraAvailability();

    
    // Start webcam with error handling
    async function startCamera() {
      webcamLoading.style.display = 'block';
      webcamError.style.display = 'none';
      
      // Check if camera API is supported
      if (!isCameraSupported()) {
        webcamLoading.style.display = 'none';
        webcamError.textContent = 'Camera not supported on this browser. Please use a modern browser (Chrome, Firefox, Edge, Safari) and ensure you are using HTTPS or localhost.';
        webcamError.style.display = 'block';
        return false;
      }
      
      // Check if we're on a secure context (HTTPS or localhost)
      const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
      if (!isSecure) {
        webcamLoading.style.display = 'none';
        webcamError.innerHTML = 'Camera access requires a secure connection.<br><br>Please use:<br>• HTTPS (recommended for production)<br>• Localhost (http://localhost/...)<br><br>Note: Camera access is blocked on non-secure HTTP for security reasons.';
        webcamError.style.display = 'block';
        return false;
      }
      
      try {
        // Request camera permission
        const constraints = { 
          video: { 
            width: { ideal: 1280, max: 1920 },
            height: { ideal: 720, max: 1080 },
            facingMode: 'environment'
          } 
        };
        
        stream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = stream;
        webcamLoading.style.display = 'none';
        return true;
      } catch (err) {
        webcamLoading.style.display = 'none';
        let errorMsg = 'Error accessing camera: ';
        
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
          errorMsg = 'Camera access denied. Please allow camera permissions when prompted, then try again.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
          errorMsg = 'No camera found on this device. Please connect a camera and try again.';
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
          errorMsg = 'Camera is already in use by another application. Please close other apps using the camera.';
        } else if (err.name === 'NotSupportedError') {
          errorMsg = 'Camera not supported on this browser or device.';
        } else if (err.name === 'OverconstrainedError') {
          errorMsg = 'Camera does not support the requested settings. Trying with default settings...';
          // Try with basic constraints
          try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            webcamLoading.style.display = 'none';
            return true;
          } catch (e) {
            errorMsg = 'Could not access camera with any available settings.';
          }
        } else {
          errorMsg += err.message || 'Unknown error';
        }
        
        webcamError.textContent = errorMsg;
        webcamError.style.display = 'block';
        return false;
      }
    }
    
    useWebcamBtn.addEventListener('click', async function() {
      webcamModal.classList.add('active');
      webcamSuccess.style.display = 'none';
      video.style.display = 'block';
      canvas.style.display = 'none';
      captureBtn.style.display = 'inline-block';
      retakeBtn.style.display = 'none';
      
      await startCamera();
    });
    
    captureBtn.addEventListener('click', function() {
      if (!video.videoWidth || !video.videoHeight) {
        webcamError.textContent = 'Camera not ready. Please wait and try again.';
        webcamError.style.display = 'block';
        return;
      }
      
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const context = canvas.getContext('2d');
      context.drawImage(video, 0, 0);
      
      video.style.display = 'none';
      canvas.style.display = 'block';
      captureBtn.style.display = 'none';
      retakeBtn.style.display = 'inline-block';
      webcamSuccess.style.display = 'block';
      
      // Stop the stream but keep the captured image
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
      
      // Store the captured image in the hidden input
      webcamImageInput.value = canvas.toDataURL('image/jpeg', 0.9);
      
      // Show preview in the main form
      imagePreview.src = canvas.toDataURL('image/jpeg', 0.9);
      imagePreviewContainer.classList.add('has-image');
      imageButtons.style.display = 'none';
    });
    
    retakeBtn.addEventListener('click', async function() {
      webcamSuccess.style.display = 'none';
      retakeBtn.style.display = 'none';
      video.style.display = 'block';
      canvas.style.display = 'none';
      captureBtn.style.display = 'inline-block';
      
      await startCamera();
    });
    
    closeWebcamBtn.addEventListener('click', function() {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
      webcamModal.classList.remove('active');
    });
    
    // Close modal when clicking outside
    webcamModal.addEventListener('click', function(e) {
      if (e.target === webcamModal) {
        if (stream) {
          stream.getTracks().forEach(track => track.stop());
          stream = null;
        }
        webcamModal.classList.remove('active');
      }
    });
  </script>

</body>
</html>
