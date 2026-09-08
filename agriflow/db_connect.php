<?php
// Database Configuration
$host     = 'localhost';     // Usually 'localhost'
$db_name  = 'agriflow_db';   // Change this to your actual database name
$username = 'root';          // Your database username
$password = '';              // Your database password (empty for XAMPP default)
$charset  = 'utf8mb4';

// Data Source Name
$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

// Options for safety and error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throws errors so you can find bugs
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Returns data as easy-to-use arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Extra security against SQL injection
];

try {
    // Create the connection
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // If connection fails, stop and show the error
    die("Database connection failed: " . $e->getMessage());
}
?>