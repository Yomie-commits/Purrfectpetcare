<?php
// Disable HTML error output to prevent JSON corruption
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Database connection with fallback options
$host = 'localhost:3306';
$dbname = 'wqytuyeu_Purrfectpetcaredb';
$username = 'wqytuyeu_Admin';
$password = 'p3Kl1k;N:Q99Cj';

// Try to load from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env) {
        $host = $env['DB_HOST'] ?? $host;
        $dbname = $env['DB_NAME'] ?? $dbname;
        $username = $env['DB_USER'] ?? $username;
        $password = $env['DB_PASS'] ?? $password;
    }
}

// Initialize PDO variable
$pdo = null;

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // If database doesn't exist, try to create it
    if ($e->getCode() == 1049) {
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
            $pdo->exec("USE $dbname");
            
            // Create tables
            createTables($pdo);
            
        } catch(PDOException $e2) {
            // Log error but don't output HTML
            error_log("Database creation failed: " . $e2->getMessage());
            $pdo = null;
        }
    } else {
        // Log error but don't output HTML
        error_log("Database connection failed: " . $e->getMessage());
        $pdo = null;
    }
}

// Function to check if database is connected
function isDatabaseConnected() {
    global $pdo;
    return $pdo !== null;
}

// Function to get database error message
function getDatabaseError() {
    global $pdo;
    if ($pdo === null) {
        return "Database connection failed. Please check your database configuration in db_connect.php";
    }
    return null;
}

function createTables($pdo) {
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('owner', 'vet', 'admin') NOT NULL DEFAULT 'owner',
        status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        reset_token VARCHAR(255) NULL,
        reset_expiry TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Pets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS pets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(100) NOT NULL,
        breed VARCHAR(255),
        age VARCHAR(100),
        owner VARCHAR(255) NOT NULL,
        status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Appointments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        time TIME NOT NULL,
        pet VARCHAR(255) NOT NULL,
        owner VARCHAR(255) NOT NULL,
        vet VARCHAR(255) NOT NULL,
        purpose VARCHAR(255),
        status ENUM('Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Medical records table
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_name VARCHAR(255) NOT NULL,
        type VARCHAR(255) NOT NULL,
        details TEXT,
        date DATE NOT NULL,
        vet VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Vaccinations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS vaccinations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_name VARCHAR(255) NOT NULL,
        vaccine VARCHAR(255) NOT NULL,
        last_given DATE,
        next_due DATE,
        status ENUM('Up to date', 'Due soon', 'Overdue') NOT NULL DEFAULT 'Up to date',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Payments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method VARCHAR(100) NOT NULL,
        status ENUM('Pending', 'Completed', 'Failed') NOT NULL DEFAULT 'Pending',
        date DATE NOT NULL,
        transaction_id VARCHAR(255),
        reference VARCHAR(255),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Forum posts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        date DATE NOT NULL,
        status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Adoption table
    $pdo->exec("CREATE TABLE IF NOT EXISTS adoptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_name VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('Available', 'Pending', 'Adopted') NOT NULL DEFAULT 'Available',
        date_posted DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Lost & Found table
    $pdo->exec("CREATE TABLE IF NOT EXISTS lost_found (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('Lost', 'Found') NOT NULL,
        pet VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        status ENUM('Searching', 'Found', 'Reunited') NOT NULL DEFAULT 'Searching',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Telemedicine sessions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS telemedicine_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_id INT NOT NULL,
        owner_id INT NOT NULL,
        vet_id INT NOT NULL,
        date DATE NOT NULL,
        time TIME NOT NULL,
        status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
        video_url VARCHAR(255),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (vet_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Prescriptions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_id INT NOT NULL,
        vet_id INT NOT NULL,
        medication VARCHAR(255) NOT NULL,
        dosage VARCHAR(255) NOT NULL,
        duration VARCHAR(255),
        instructions TEXT,
        date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
        FOREIGN KEY (vet_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Audit logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Insert sample data
    insertSampleData($pdo);
}

function insertSampleData($pdo) {
    // Sample data insertion removed for production use
}
?>
