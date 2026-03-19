<?php
session_start();
include('../config/db.php');

// Get school logo from settings
$logo_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'school_logo' LIMIT 1";
$logo_result = $conn->query($logo_query);
$school_logo = ($logo_result->num_rows > 0) ? $logo_result->fetch_assoc()['setting_value'] : '../images/bookhub.jpg';

$message = '';
$message_type = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'email';
$email = '';
$token = '';

// Generate a unique token
function generateResetToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Process email verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_email') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Email is required';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format';
        $message_type = 'danger';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND status = 'Active' AND role_id IN (1,2)");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Generate token
            $token = generateResetToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Delete existing tokens
            $delete_stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Insert new token
            $insert_stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $email, $token, $expires_at);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Redirect to password reset page with token
            header('Location: forgot_password.php?step=reset&email=' . urlencode($email) . '&token=' . urlencode($token));
            exit();
        } else {
            $message = 'No account found with this email address';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $email = trim($_POST['email'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($token) || empty($password) || empty($confirm_password)) {
        $message = 'All fields are required';
        $message_type = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters';
        $message_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'danger';
    } else {
        // Verify token
        $verify_stmt = $conn->prepare("SELECT token_id FROM password_reset_tokens WHERE email = ? AND token = ? AND used = 0 AND expires_at > CURRENT_TIMESTAMP");
        $verify_stmt->bind_param("ss", $email, $token);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            $message = 'Invalid or expired token. Please start over.';
            $message_type = 'danger';
            $step = 'email';
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $hashed_password, $email);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows > 0) {
                // Mark token as used
                $used_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE email = ? AND token = ?");
                $used_stmt->bind_param("ss", $email, $token);
                $used_stmt->execute();
                $used_stmt->close();
                
                $message = 'Password reset successfully! You can now login with your new password.';
                $message_type = 'success';
                $step = 'success';
            } else {
                $message = 'Failed to reset password. Please try again.';
                $message_type = 'danger';
            }
            $update_stmt->close();
        }
        $verify_stmt->close();
    }
}

// Get email and token from URL for reset step
if ($step === 'reset') {
    $email = $_GET['email'] ?? '';
    $token = $_GET['token'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Book Inventory System - Forgot Password</title>
    <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link href="../fontawesome/css/fontawesome.css" rel="stylesheet" />
    <link href="../fontawesome/css/all.css" rel="stylesheet" />
    <link href="../css/fonts.css" rel="stylesheet" type="text/css" />
    <style>
        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            width: 100%;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-form-container">
            <h2>Forgot Password</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 'email'): ?>
                <p class="text-center mb-3">Enter your email address to reset your password.</p>
                
                <form method="POST" action="forgot_password.php" class="form-vertical">
                    <input type="hidden" name="action" value="verify_email" />
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" placeholder="Enter your email address" required />
                    </div>
                    <button type="submit" class="btn-submit">Verify Email</button>
                </form>
                
            <?php elseif ($step === 'reset'): ?>
                <p class="text-center mb-3" style="color: #28a745;">
                    <i class="fas fa-check-circle"></i> Email verified! Create your new password.
                </p>
                
                <form method="POST" action="forgot_password.php?step=reset&email=<?php echo urlencode($email); ?>&token=<?php echo urlencode($token); ?>" class="form-vertical">
                    <input type="hidden" name="action" value="reset_password" />
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>" />
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" placeholder="New password" required />
                        <span class="toggle-password" onclick="togglePassword(this)"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required />
                        <span class="toggle-password" onclick="togglePassword(this)"><i class="fas fa-eye"></i></span>
                    </div>
                    <button type="submit" class="btn-submit">Reset Password</button>
                </form>
                
            <?php elseif ($step === 'success'): ?>
                <div class="text-center">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 20px;"></i>
                    <p>Your password has been reset successfully!</p>
                    <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <?php if ($step !== 'success'): ?>
                <div class="text-center mt-3">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="login-illustration">
            <div class="logo" style="background-image: url('<?php echo htmlspecialchars($school_logo); ?>');"></div>
            <div class="illustration-text">
                <h3>BOOK INVENTORY SYSTEM</h3>
                <p>Developed by Avid Solutions</p>
            </div>
        </div>
    </div>
    <script>
        function togglePassword(btn) {
            var input = btn.previousElementSibling;
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
