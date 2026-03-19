<?php
session_start();
include('../config/db.php');

// Get school logo from settings
$logo_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'school_logo' LIMIT 1";
$logo_result = $conn->query($logo_query);
$school_logo = ($logo_result->num_rows > 0) ? $logo_result->fetch_assoc()['setting_value'] : '../images/school.png';

$message = '';
$message_type = '';

$token = $_GET['token'] ?? '';
$valid_token = false;
$email = '';

if (!empty($token)) {
    // Verify token
    $stmt = $conn->prepare("SELECT email FROM password_reset_tokens WHERE token = ? AND used = 0 AND expires_at > CURRENT_TIMESTAMP");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $email = $row['email'];
        $valid_token = true;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    header('Content-Type: application/json');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        exit();
    }

    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }

    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Update password in users table
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $update_stmt->bind_param("ss", $hashed_password, $email);
    $update_stmt->execute();

    if ($update_stmt->affected_rows > 0) {
        // Mark token as used
        $used_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
        $used_stmt->bind_param("s", $token);
        $used_stmt->execute();
        $used_stmt->close();

        echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now login with your new password.']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
        exit();
    }

    $update_stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Book Inventory System - Reset Password</title>
    <link rel="icon" href="../images/bookhub.jpg" type="image/jpg" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link href="../fontawesome/css/fontawesome.css" rel="stylesheet" />
    <link href="../fontawesome/css/all.css" rel="stylesheet" />
    <link href="../css/fonts.css" rel="stylesheet" type="text/css" />
</head>
<body>
    <div class="login-wrapper">
        <div class="login-form-container">
            <h2>Reset Password</h2>

            <?php if (!$valid_token): ?>
                <div class="alert alert-danger">
                    Invalid or expired reset link. Please request a new password reset.
                </div>
                <div class="text-center mt-3">
                    <a href="forgot_password.php">Request New Reset Link</a> | <a href="login.php">Back to Login</a>
                </div>
            <?php else: ?>
                <p class="text-center mb-3">Enter your new password below.</p>

                <div id="message" class="alert" style="display: none;"></div>

                <form id="resetPasswordForm" method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" class="form-vertical">
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" placeholder="New password" required />
                        <span class="toggle-password"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required />
                        <span class="toggle-password"><i class="fas fa-eye"></i></span>
                    </div>
                    <button type="submit" class="btn-submit">Reset Password</button>
                </form>

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
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <style>
        .alert {
            padding: 10px;
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
    </style>
    <script>
        $(document).ready(function() {
            $('#resetPasswordForm').on('submit', function(e) {
                e.preventDefault();

                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.text();
                submitBtn.text('Resetting...').prop('disabled', true);

                $.ajax({
                    url: 'reset_password.php?token=<?php echo htmlspecialchars($token); ?>',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        const messageDiv = $('#message');
                        if (response.success) {
                            messageDiv.removeClass('alert-danger').addClass('alert-success').text(response.message).show();
                            $('#resetPasswordForm')[0].reset();
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 3000);
                        } else {
                            messageDiv.removeClass('alert-success').addClass('alert-danger').text(response.message).show();
                        }
                    },
                    error: function() {
                        $('#message').removeClass('alert-success').addClass('alert-danger').text('An error occurred. Please try again.').show();
                    },
                    complete: function() {
                        submitBtn.text(originalText).prop('disabled', false);
                    }
                });
            });

            // Toggle password visibility
            $('.toggle-password').on('click', function() {
                const input = $(this).siblings('input');
                const icon = $(this).find('i');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });
    </script>
</body>
</html>
