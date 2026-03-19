<?php
// Define constant to allow includes
if (!defined('INCLUDED_FROM_APPLICATION')) {
    define('INCLUDED_FROM_APPLICATION', true);
}

// Include session config first
include('../includes/session_config.php');

// Check for session expired parameter
$session_expired = isset($_GET['session_expired']) && $_GET['session_expired'] == 1;

// Automatic redirect to dashboard based on role (only if session is valid)
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && isset($_SESSION['last_activity'])) {
    // Check if session has expired before redirect
    $session_timeout = $_SESSION['session_timeout'] ?? 2700;
    $elapsed = time() - $_SESSION['last_activity'];
    
    if ($elapsed < $session_timeout) {
        if ($_SESSION['role_id'] == 1) {
            header('Location: ../admin/admin_dashboard.php');
            exit();
        } elseif ($_SESSION['role_id'] == 2) {
            header('Location: ../sales-user/sales_dashboard.php');
            exit();
        }
    }
}

include('../config/db.php');

// Get book logo from settings
$logo_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'book_logo' LIMIT 1";
$logo_result = $conn->query($logo_query);
$book_logo = ($logo_result->num_rows > 0) ? $logo_result->fetch_assoc()['setting_value'] : '../images/bookhub.jpg';
$logo_result->free();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $email = $_POST['email'] ?? $_POST['username'] ?? $_POST['user'] ?? '';
    $password = $_POST['password'] ?? $_POST['pass'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit();
    }

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT user_id, password, role_id, username FROM users WHERE email = ? AND status = 'Active'");
    $stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $result->free();
    $stmt->close();

if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['last_activity'] = time();
        $_SESSION['session_start'] = time();
        echo json_encode(['success' => true, 'role' => $user['role_id']]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit();
    }
} else {
    $result->free();
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit();
}
    exit(); // Ensure no further code is executed after POST handling
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BookHub - Login</title>
    <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link href="fontawesome/css/fontawesome.css" rel="stylesheet" />
    <link href="fontawesome/css/all.css" rel="stylesheet" />
    <link href="css/fonts.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div class="login-wrapper">
        <div class="login-form-container">
            <?php if ($session_expired): ?>
            <div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #ffc107;">
                ⚠️ Your session has expired due to inactivity. Please login again.
            </div>
            <?php endif; ?>
            <h2>Login</h2>
            <form id="loginform" method="POST" action="login.php" class="form-vertical">
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" placeholder="Enter email address" required />
                </div>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" placeholder="Enter password" required />
                    <span class="toggle-password"><i class="fas fa-eye"></i></span>
                </div>
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot password?</a>
                </div>
                <button type="submit" class="btn-submit">Submit</button>
            </form>
        </div>
        <div class="login-illustration">
            <div class="logo" style="background-image: url('<?php echo htmlspecialchars($book_logo); ?>');"></div>

            <div class="illustration-text">
                <h3>BOOKHUB</h3>
                <p>Developed by Avid Solutions</p>
            </div>
        </div>
    </div>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/login-general.js"></script>
</body>
</html>
