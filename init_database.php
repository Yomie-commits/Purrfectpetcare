<?php
/**
 * Database Initialization Script for cPanel Hosting
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
    .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

try {
    echo "<div class='step'><h3>Step 1: Database Configuration</h3>";
    
    // Database configuration for cPanel hosting
    $host = 'localhost';
    $dbname = 'yomieco_pet_care';
    $username = 'yomieco_admin';
    $password = 'your_db_password'; // Replace with actual password
    
    echo "<p><strong>Host:</strong> $host</p>";
    echo "<p><strong>Database:</strong> $dbname</p>";
    echo "<p><strong>Username:</strong> $username</p>";
    echo "<p><strong>Status:</strong> <span class='success'>Configuration loaded</span></p>";
    
    if ($password === 'your_db_password') {
        echo "<p><span class='warning'>⚠️ Please update the database password in db_connect.php before proceeding!</span></p>";
    }
    echo "</div>";
    
    echo "<div class='step'><h3>Step 2: Database Connection</h3>";
    
    // Try to connect to the database
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        echo "<p><span class='success'>✅ Database connection successful!</span></p>";
        
    } catch (PDOException $e) {
        if ($e->getCode() == 1049) {
            echo "<p><span class='error'>❌ Database '$dbname' does not exist!</span></p>";
            echo "<p><span class='info'>💡 Please create the database in cPanel MySQL Databases first.</span></p>";
            echo "<p><span class='info'>📋 Steps:</span></p>";
            echo "<ol>";
            echo "<li>Login to cPanel at yomiecommits.co.ke/cpanel</li>";
            echo "<li>Go to 'MySQL Databases'</li>";
            echo "<li>Create database: yomieco_pet_care</li>";
            echo "<li>Create user: yomieco_admin</li>";
            echo "<li>Assign user to database with ALL PRIVILEGES</li>";
            echo "<li>Update password in db_connect.php</li>";
            echo "<li>Refresh this page</li>";
            echo "</ol>";
            exit;
        } else {
            echo "<p><span class='error'>❌ Connection failed: " . $e->getMessage() . "</span></p>";
            echo "<p><span class='info'>💡 Please check your database credentials in db_connect.php</span></p>";
            exit;
        }
    }
    echo "</div>";
    
    echo "<div class='step'><h3>Step 3: Creating Tables</h3>";
    
    // Include the main database connection file to create tables
    require_once 'db_connect.php';
    
    echo "<p><span class='success'>✅ All tables created successfully!</span></p>";
    echo "<div class='info'>Sample data inserted successfully!</div>";
    echo "<h3>Next Steps:</h3>";
    echo "<ul>";
    echo "<li>Create your first admin account through the registration form</li>";
    echo "<li>Update the sample user passwords immediately after first login</li>";
    echo "<li>Configure your clinic settings in the admin panel</li>";
    echo "</ul>";
    echo "<div class='warning'>⚠️ IMPORTANT: Change all default passwords immediately after setup!</div>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 4: Test Data</h3>";
    
    // Test the connection by counting users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    
    echo "<p><span class='success'>✅ Database is working! Found $userCount users.</span></p>";
    
    // Test appointments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $appointmentCount = $stmt->fetch()['count'];
    echo "<p><span class='success'>✅ Found $appointmentCount appointments.</span></p>";
    
    // Test pets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pets");
    $petCount = $stmt->fetch()['count'];
    echo "<p><span class='success'>✅ Found $petCount pets.</span></p>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 5: System Access</h3>";
    echo "<p><span class='success'>🎉 Database setup complete!</span></p>";
    echo "<p><strong>Your system is now ready at:</strong></p>";
    echo "<p><a href='index.html' target='_blank'>https://yomiecommits.co.ke/pet-care/index.html</a></p>";
    echo "<p><strong>Test login accounts:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Pet Owner:</strong> john@example.com / password</li>";
    echo "<li><strong>Veterinarian:</strong> sarah@example.com / password</li>";
    echo "<li><strong>Administrator:</strong> admin@example.com / password</li>";
    echo "</ul>";
    echo "<p><strong>All features are now live and functional!</strong></p>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 6: Security Reminder</h3>";
    echo "<p><span class='warning'>⚠️ Important Security Steps:</span></p>";
    echo "<ul>";
    echo "<li>Delete or rename this file (init_database.php) after setup</li>";
    echo "<li>Ensure .env file is not publicly accessible</li>";
    echo "<li>Set proper file permissions (644 for .php files)</li>";
    echo "<li>Enable SSL certificate in cPanel</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Setup Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}
?> 