<?php
// Include the configuration file to get database connection details
require_once '../includes/config.php';

// Function to create database tables
function setupDatabase()
{
  global $conn;

  try {
    // Create users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            status ENUM('active', 'banned') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

    // Insert default admin if not exists
    $checkAdmin = $conn->query("SELECT COUNT(*) FROM users WHERE email = 'admin@example.com'");
    if ($checkAdmin->fetchColumn() == 0) {
      $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) 
                                  VALUES (?, ?, ?, ?)");
      $stmt->execute([
        'Admin',
        'admin@example.com',
        password_hash('admin123', PASSWORD_DEFAULT),
        'admin'
      ]);
      echo "<p>Default admin created: admin@example.com / admin123</p>";
    } else {
      echo "<p>Admin account already exists.</p>";
    }

    // Create items table
    $conn->exec("CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('lost', 'found') NOT NULL,
            title VARCHAR(100) NOT NULL,
            description TEXT,
            category VARCHAR(50) NOT NULL,
            location VARCHAR(100) NOT NULL,
            date_lost_found DATE NOT NULL,
            keywords VARCHAR(255),
            status ENUM('active', 'resolved', 'spam', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

    // Create item_images table
    $conn->exec("CREATE TABLE IF NOT EXISTS item_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        )");
        
    // Create claims table
    $conn->exec("CREATE TABLE IF NOT EXISTS claims (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            user_id INT NOT NULL,
            claim_details TEXT NOT NULL,
            contact_info VARCHAR(255),
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

    // Create matches table
    $conn->exec("CREATE TABLE IF NOT EXISTS matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lost_item_id INT NOT NULL,
            found_item_id INT NOT NULL,
            match_percentage DECIMAL(5,2) NOT NULL,
            status ENUM('pending', 'confirmed', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lost_item_id) REFERENCES items(id) ON DELETE CASCADE,
            FOREIGN KEY (found_item_id) REFERENCES items(id) ON DELETE CASCADE
        )");

    // Create indexes for better search performance
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_category ON items (category)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_location ON items (location)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_keywords ON items (keywords)");

    echo "<p>Database setup completed successfully!</p>";
    return true;

  } catch (PDOException $e) {
    echo "<p>Database setup error: " . $e->getMessage() . "</p>";
    return false;
  }
}

// HTML interface for running the setup
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lost & Found - Database Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6 max-w-lg mx-auto">
      <h1 class="text-2xl font-bold text-center mb-6">Lost & Found Portal - Database Setup</h1>

      <?php
      // Check if form is submitted
      if (isset($_POST['setup_db'])) {
        setupDatabase();
      }
      ?>

      <form method="post" class="mt-4">
        <div class="mb-4">
          <p class="text-gray-600 mb-4">
            This script will set up the necessary database tables for the Lost & Found Portal.
            Make sure your database credentials are correctly configured in the config.php file.
          </p>
          <p class="text-yellow-600 mb-4">
            Note: This script is safe to run multiple times as it uses "IF NOT EXISTS" conditions.
          </p>
        </div>
        <div class="text-center">
          <button type="submit" name="setup_db"
            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
            Set Up Database
          </button>
        </div>
      </form>

      <div class="mt-6 border-t pt-4">
        <a href="<?php echo BASE_URL; ?>" class="text-blue-500 hover:underline">Return to Home Page</a>
      </div>
    </div>
  </div>
</body>

</html>