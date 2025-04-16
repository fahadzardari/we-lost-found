<?php
// Database connection details
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Replace with your MySQL username
define('DB_PASS', 'your_new_password');      // Replace with your MySQL password
define('DB_NAME', 'lost-found');

// Establish database connection
try {
  $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Connection failed: " . $e->getMessage());
}

// Base URL of the application
define('BASE_URL', '/lostfound');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . BASE_URL . '/assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}
?>