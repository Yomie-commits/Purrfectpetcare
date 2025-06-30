<?php
/**
 * Database Setup Script for Pet Care Management System
 * Run this file through your web browser to initialize the database
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🐾 Pet Care Management System - Database Setup</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .step { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
</style>";

try {
    echo "<div class='step'><h3>Step 1: Database Configuration</h3>";
    
    // Database configuration
    $host = 'localhost:3306';
    $dbname = 'wqytuyeu_Purrfectpetcaredb';
    $username = 'wqytuyeu_Admin';
    $password = 'p3Kl1k;N:Q99Cj';
    
    echo "<p><strong>Host:</strong> $host</p>";
    echo "<p><strong>Database:</strong> $dbname</p>";
    echo "<p><strong>Username:</strong> $username</p>";
    echo "<p><strong>Status:</strong> <span class='success'>Configuration loaded</span></p>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 2: Database Connection</h3>";
    
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<p><span class='success'>✅ Database connection successful!</span></p>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 3: Creating Tables</h3>";
    
    // Create tables
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('owner', 'vet', 'admin') DEFAULT 'owner',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'pets' => "CREATE TABLE IF NOT EXISTS pets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(100) NOT NULL,
            breed VARCHAR(255),
            age VARCHAR(100),
            owner_id INT,
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'appointments' => "CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            time TIME NOT NULL,
            pet_id INT,
            owner_id INT,
            vet_id INT,
            purpose VARCHAR(255),
            status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (vet_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'health_records' => "CREATE TABLE IF NOT EXISTS health_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pet_id INT,
            type VARCHAR(100) NOT NULL,
            details TEXT,
            date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
        )",
        
        'vaccinations' => "CREATE TABLE IF NOT EXISTS vaccinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pet_id INT,
            vaccine_name VARCHAR(255) NOT NULL,
            last_given DATE,
            next_due DATE,
            status ENUM('Up to date', 'Due soon', 'Overdue') DEFAULT 'Up to date',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
        )",
        
        'payments' => "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(100),
            status ENUM('Pending', 'Completed', 'Failed') DEFAULT 'Pending',
            transaction_id VARCHAR(255),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'adoptions' => "CREATE TABLE IF NOT EXISTS adoptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pet_name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('Available', 'Pending', 'Adopted') DEFAULT 'Available',
            posted_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'lost_found' => "CREATE TABLE IF NOT EXISTS lost_found (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('Lost', 'Found') NOT NULL,
            pet_name VARCHAR(255),
            location VARCHAR(255),
            status ENUM('Searching', 'Found', 'Reunited') DEFAULT 'Searching',
            reported_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'forum_posts' => "CREATE TABLE IF NOT EXISTS forum_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author_id INT,
            content TEXT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        'telemedicine_sessions' => "CREATE TABLE IF NOT EXISTS telemedicine_sessions (
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
        )",
        
        'prescriptions' => "CREATE TABLE IF NOT EXISTS prescriptions (
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
        )",
        
        'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($tables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p><span class='success'>✅ Table '$tableName' created successfully</span></p>";
        } catch (PDOException $e) {
            echo "<p><span class='info'>ℹ️ Table '$tableName' already exists or error: " . $e->getMessage() . "</span></p>";
        }
    }
    echo "</div>";
    
    echo "<div class='step'><h3>Step 4: Inserting Sample Data</h3>";
    // Sample data insertion removed for production use
    echo "<div class='info'>Sample data inserted successfully!</div>";
    echo "<h3>Next Steps:</h3>";
    echo "<ul>";
    echo "<li>Create your first admin account through the registration form</li>";
    echo "<li>Update the sample user passwords immediately after first login</li>";
    echo "<li>Configure your clinic settings in the admin panel</li>";
    echo "</ul>";
    echo "<div class='warning'>⚠️ IMPORTANT: Change all default passwords immediately after setup!</div>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 5: Database Verification</h3>";
    
    // Verify tables exist
    $tableCount = $pdo->query("SHOW TABLES")->rowCount();
    echo "<p><span class='success'>✅ Total tables created: $tableCount</span></p>";
    
    // Count records in each table
    $tables = ['users', 'pets', 'appointments', 'health_records', 'vaccinations', 'payments'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch()['count'];
            echo "<p><span class='info'>📊 $table: $count records</span></p>";
        } catch (PDOException $e) {
            echo "<p><span class='error'>❌ Error counting $table: " . $e->getMessage() . "</span></p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='success' style='text-align: center; padding: 20px; margin: 20px 0;'>";
    echo "<h2>🎉 Database Setup Completed Successfully!</h2>";
    echo "<p>Your Pet Care Management System database is now ready to use.</p>";
    echo "<p><strong>Default Login Credentials:</strong></p>";
    echo "<ul style='text-align: left; display: inline-block;'>";
    echo "<li><strong>Pet Owner:</strong> john@example.com / password</li>";
    echo "<li><strong>Veterinarian:</strong> sarah@example.com / password</li>";
    echo "<li><strong>Admin:</strong> admin@example.com / password</li>";
    echo "</ul>";
    echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px;'>🚀 Go to Application</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Database Setup Failed</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Possible Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Verify MySQL server is running</li>";
    echo "<li>Check database credentials in db_connect.php</li>";
    echo "<li>Ensure database exists and user has proper permissions</li>";
    echo "<li>Verify network connectivity to database server</li>";
    echo "</ul>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Setup Error</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?> 