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
    $access_sql = "SELECT sales_enabled FROM page_access_settings WHERE page_name = 'manage_suppliers'";
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
$message = '';
$message_type = '';

// Handle add supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_supplier'])) {
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $contact_person = $conn->real_escape_string($_POST['contact_person'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    
    if ($name) {
        $sql = "INSERT INTO suppliers (name, contact_person, email, phone, address) 
                VALUES ('$name', '$contact_person', '$email', '$phone', '$address')";
        if ($conn->query($sql)) {
            $message = "Supplier added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding supplier: " . $conn->error;
            $message_type = "error";
        }
    } else {
        $message = "Supplier name is required.";
        $message_type = "error";
    }
}

// Handle edit supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_supplier'])) {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $contact_person = $conn->real_escape_string($_POST['contact_person'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    
    if ($supplier_id && $name) {
        $sql = "UPDATE suppliers SET name = '$name', contact_person = '$contact_person', 
                email = '$email', phone = '$phone', address = '$address' 
                WHERE supplier_id = $supplier_id";
        if ($conn->query($sql)) {
            $message = "Supplier updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating supplier: " . $conn->error;
            $message_type = "error";
        }
    } else {
        $message = "Supplier ID and name are required.";
        $message_type = "error";
    }
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // First delete from book_suppliers
    $delete_link = $conn->prepare("DELETE FROM book_suppliers WHERE supplier_id = ?");
    $delete_link->bind_param("i", $delete_id);
    $delete_link->execute();
    $delete_link->close();
    
    // Then delete from suppliers
    $delete_supplier = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $delete_supplier->bind_param("i", $delete_id);
    if ($delete_supplier->execute()) {
        $message = "Supplier deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting supplier: " . $conn->error;
        $message_type = "error";
    }
    $delete_supplier->close();
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query with search
$query = "SELECT * FROM suppliers WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (name LIKE '%$search%' OR contact_person LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

$query .= " ORDER BY supplier_id DESC";

$result = $conn->query($query);

// Count total suppliers
$count_query = "SELECT COUNT(*) as total FROM suppliers";
$count_result = $conn->query($count_query);
$total_suppliers = $count_result->fetch_assoc()['total'];

// Get supplier for editing if ID is provided
$edit_supplier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM suppliers WHERE supplier_id = $edit_id");
    $edit_supplier = $edit_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Suppliers - Book Hub</title>
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
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 25px;
    }
    @media (max-width: 1024px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
    }
    .form-section {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      height: fit-content;
    }
    .form-section h2 {
      margin: 0 0 20px 0;
      color: #003366;
      font-size: 1.4em;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .form-group {
      margin-bottom: 18px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      font-size: 0.95em;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
      box-sizing: border-box;
      transition: border-color 0.3s ease;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #003366;
    }
    .form-group textarea {
      resize: vertical;
      min-height: 80px;
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
      width: 100%;
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
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
    .filter-group input {
      width: 100%;
      padding: 10px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
      box-sizing: border-box;
    }
    .filter-group input:focus {
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
    }
    .suppliers-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95em;
    }
    .suppliers-table thead {
      background: #f8f9fa;
    }
    .suppliers-table th {
      padding: 15px;
      text-align: left;
      font-weight: 600;
      color: #333;
      border-bottom: 2px solid #e0e0e0;
      white-space: nowrap;
    }
    .suppliers-table td {
      padding: 15px;
      border-bottom: 1px solid #e0e0e0;
      vertical-align: middle;
    }
    .suppliers-table tbody tr:hover {
      background: #f8f9fa;
    }
    .suppliers-table tbody tr:last-child td {
      border-bottom: none;
    }
    .supplier-name {
      font-weight: 600;
      color: #003366;
    }
    .contact-person {
      color: #666;
    }
    .email-link {
      color: #003366;
      text-decoration: none;
    }
    .email-link:hover {
      text-decoration: underline;
    }
    .phone {
      font-weight: 500;
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
      transform: translateY(-22px);
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.4);
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
      margin: 0;
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
    .address-text {
      font-size: 0.9em;
      color: #666;
      max-width: 200px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
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
          <img src="../images/school.png" alt="School Logo" class="dashboard-title-logo">
          🏢 Manage Suppliers
        </h1>
        <p class="page-subtitle">Manage your book suppliers and their contact information</p>
      </div>

      <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">🏢</div>
          <div class="stat-content">
            <h3><?php echo $total_suppliers; ?></h3>
            <p>Total Suppliers</p>
          </div>
        </div>
      </div>

      <div class="content-grid">
        <!-- Add/Edit Form -->
        <div class="form-section">
          <h2>
            <?php if ($edit_supplier): ?>
              ✏️ Edit Supplier
            <?php else: ?>
              ➕ Add Supplier
            <?php endif; ?>
          </h2>
          
          <form method="POST" action="">
            <?php if ($edit_supplier): ?>
              <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['supplier_id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
              <label for="name">Supplier Name *</label>
              <input type="text" id="name" name="name" placeholder="Enter supplier name" 
                     value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['name']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
              <label for="contact_person">Contact Person</label>
              <input type="text" id="contact_person" name="contact_person" placeholder="Enter contact person name" 
                     value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['contact_person']) : ''; ?>">
            </div>
            
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" placeholder="Enter email address" 
                     value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
              <label for="phone">Phone Number</label>
              <input type="text" id="phone" name="phone" placeholder="Enter phone number" 
                     value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['phone']) : ''; ?>">
            </div>
            
            <div class="form-group">
              <label for="address">Address</label>
              <textarea id="address" name="address" placeholder="Enter supplier address"><?php echo $edit_supplier ? htmlspecialchars($edit_supplier['address']) : ''; ?></textarea>
            </div>
            
            <button type="submit" name="<?php echo $edit_supplier ? 'edit_supplier' : 'add_supplier'; ?>" class="btn-submit">
              <?php if ($edit_supplier): ?>
                💾 Update Supplier
              <?php else: ?>
                ➕ Add Supplier
              <?php endif; ?>
            </button>
            
            <?php if ($edit_supplier): ?>
              <a href="manage_suppliers.php" class="btn-reset" style="display: block; text-align: center; margin-top: 10px; text-decoration: none;">
                ↺ Cancel Edit
              </a>
            <?php endif; ?>
          </form>
        </div>

        <!-- Suppliers List -->
        <div class="table-section">
          <div class="table-header">
            <h2>📋 Suppliers List</h2>
          </div>
          
          <div class="filter-section">
            <form method="GET" action="" class="filter-form">
              <div class="filter-group">
                <label for="search">🔍 Search</label>
                <input type="text" id="search" name="search" placeholder="Search by name, contact, email or phone" 
                       value="<?php echo htmlspecialchars($search); ?>">
              </div>
              <button type="submit" class="btn-filter">🔎 Search</button>
              <a href="manage_suppliers.php" class="btn-reset">↺ Reset</a>
            </form>
          </div>
          
          <div style="padding: 0 20px 10px; color: #666;">
            <?php echo $result->num_rows; ?> supplier(s) found
          </div>
          
          <?php if ($result->num_rows > 0): ?>
            <div class="table-container">
              <table class="suppliers-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $counter = 1; while ($supplier = $result->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo $counter++; ?></td>
                      <td>
                        <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                      </td>
                      <td class="contact-person"><?php echo htmlspecialchars($supplier['contact_person'] ?: '-'); ?></td>
                      <td>
                        <?php if ($supplier['email']): ?>
                          <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>" class="email-link">
                            <?php echo htmlspecialchars($supplier['email']); ?>
                          </a>
                        <?php else: ?>
                          -
                        <?php endif; ?>
                      </td>
                      <td class="phone"><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></td>
                      <td>
                        <span class="address-text" title="<?php echo htmlspecialchars($supplier['address'] ?? ''); ?>">
                          <?php echo htmlspecialchars($supplier['address'] ?: '-'); ?>
                        </span>
                      </td>
                      <td>
                        <div class="action-btns">
                          <a href="?edit=<?php echo $supplier['supplier_id']; ?>" class="btn-action btn-edit">✏️ Edit</a>
                          <a href="?delete=<?php echo $supplier['supplier_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this supplier?')">🗑️ Delete</a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">🏢</div>
              <h3>No Suppliers Found</h3>
              <p>No suppliers match your search criteria. Try adjusting your search or add a new supplier.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <?php include('../includes/footer.php'); ?>

</body>
</html>
