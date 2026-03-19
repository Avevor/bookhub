<?php
$host = 'localhost';         // MySQL host
$db_user = 'root';           // Your MySQL username
$db_pass = '';               // Your MySQL password
$db_name = 'bookhubdb';     // Your database name
$db_port = 3307;            // Custom MySQL port (matches XAMPP configuration)

// Create connection
$conn = new mysqli($host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    // User-friendly error message
    $error_title = "Database Connection Error";
    $error_message = "We're sorry, but we're having trouble connecting to our system. Please try again later.";
    
    // Check if this is a login page or includes a header
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page === 'login.php') {
        // Show inline error on login page
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Connection Error</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; margin: 0; }
                .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
                .error-icon { color: #e74c3c; font-size: 48px; margin-bottom: 15px; }
                .error-title { color: #333; margin-bottom: 10px; }
                .error-message { color: #666; margin-bottom: 20px; }
                .retry-btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                .retry-btn:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h2 class="error-title">Unable to Connect</h2>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="login.php" class="retry-btn">Try Again</a>
            </div>
        </body>
        </html>
        <?php
    } else {
        // For other pages, show a simple error and log the details
        error_log("Database Connection Error: " . $conn->connect_error);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>System Error</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; margin: 0; }
                .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
                .error-icon { color: #e74c3c; font-size: 48px; margin-bottom: 15px; }
                .error-title { color: #333; margin-bottom: 10px; }
                .error-message { color: #666; margin-bottom: 20px; }
                .home-btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                .home-btn:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h2 class="error-title"><?php echo htmlspecialchars($error_title); ?></h2>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="index.php" class="home-btn">Go to Homepage</a>
            </div>
        </body>
        </html>
        <?php
    }
    exit();
}

// Set character set
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $conn->error);
}
?>
