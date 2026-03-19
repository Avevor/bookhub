<?php
// Define constant to allow includes
if (!defined('INCLUDED_FROM_APPLICATION')) {
    define('INCLUDED_FROM_APPLICATION', true);
}

// Include session config
include(__DIR__ . '/session_config.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: ../pages/login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'Guest';

// Check if this is NOT the login page
$isLoginPage = basename($_SERVER['PHP_SELF']) == 'login.php';
?>

<!-- ✅ Top Navbar -->
<div class="navbar">
  <div class="navbar-left">
    <img src="../images/bookhub.jpg" alt="book Logo" class="navbar-logo">
    <h1>Book Hub</h1>
  </div>
  <div class="navbar-right">
    <span>👋 Welcome, <?php echo htmlspecialchars($username); ?></span>
    <a href="../pages/logout.php" class="logout">Logout</a>
  </div>
</div>

<?php if (!$isLoginPage && isset($_SESSION['user_id'])): ?>
<script src="../js/session_timeout.js"></script>
<?php endif; ?>

