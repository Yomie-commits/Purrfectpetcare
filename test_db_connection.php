<?php
// Database Connection Test for Pet Care System
// This file helps verify database connectivity

// Disable HTML error output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

echo "<h2>🐾 Pet Care System - Database Connection Test</h2>";

// Database configuration
$host = 'localhost:3306';
$dbname = 'wqytuyeu_Purrfectpetcaredb';
$username = 'wqytuyeu_Admin';
$password = 'p3Kl1k;N:Q99Cj';

echo "<h3>Step 1: Configuration Check</h3>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Database:</strong> $dbname</p>";
echo "<p><strong>Username:</strong> $username</p>";
echo "<p><strong>Password:</strong> ✅ Set</p>";

echo "<h3>Step 2: Connection Test</h3>";

try {
    // Test connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ <strong>Connection successful!</strong><br>";
    echo "🎉 Database is ready for the Pet Care System!<br><br>";
    
    // Test if tables exist
    echo "<h3>Step 3: Database Tables Check</h3>";
    
    $tables = ['users', 'pets', 'appointments', 'medical_records', 'vaccinations', 'payments', 'forum_posts', 'adoptions', 'lost_found'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existingTables[] = $table;
                echo "✅ Table '$table' exists<br>";
            } else {
                echo "❌ Table '$table' missing<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "<br>";
        }
    }
    
    if (count($existingTables) === count($tables)) {
        echo "<br>🎉 <strong>All tables exist! Database is fully configured.</strong><br>";
    } else {
        echo "<br>⚠️ <strong>Some tables are missing. You may need to run the database setup.</strong><br>";
        echo "<p>Missing tables: " . implode(', ', array_diff($tables, $existingTables)) . "</p>";
    }
    
    // Test sample data
    echo "<h3>Step 4: Sample Data Check</h3>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $userCount = $stmt->fetch()['count'];
        echo "👥 Users in database: $userCount<br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM pets");
        $petCount = $stmt->fetch()['count'];
        echo "🐾 Pets in database: $petCount<br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
        $appointmentCount = $stmt->fetch()['count'];
        echo "📅 Appointments in database: $appointmentCount<br>";
        
    } catch (Exception $e) {
        echo "⚠️ Error checking sample data: " . $e->getMessage() . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ <strong>Connection failed!</strong><br>";
    echo "Error: " . $e->getMessage() . "<br><br>";
    
    echo "<h3>💡 Troubleshooting steps:</h3>";
    echo "<ul>";
    echo "<li>Verify database exists in cPanel</li>";
    echo "<li>Check username and password</li>";
    echo "<li>Ensure user has proper privileges</li>";
    echo "<li>Verify MySQL is enabled in cPanel</li>";
    echo "<li>Check if the database server is running</li>";
    echo "</ul>";
    
    echo "<h3>🔧 Quick Fix:</h3>";
    echo "<p>1. Go to your cPanel → MySQL Databases</p>";
    echo "<p>2. Check if database 'wqytuyeu_Purrfectpetcaredb' exists</p>";
    echo "<p>3. Check if user 'wqytuyeu_Admin' exists</p>";
    echo "<p>4. Verify the password is correct</p>";
    echo "<p>5. Refresh this page to test again</p>";
}

echo "<br><hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>✅ Database credentials configured</li>";
echo "<li>✅ Upload all files to public_html</li>";
echo "<li>✅ Test the main application</li>";
echo "<li>✅ Configure any additional settings</li>";
echo "</ul>";

echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Pet Care System</a></p>";
?> 