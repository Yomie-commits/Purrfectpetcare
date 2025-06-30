<?php
/**
 * Database Connection Test Script
 * Use this to test your database connection before deployment
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Database Connection Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .step { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
    .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

// Database configuration
$host = 'localhost:3306';
$dbname = 'wqytuyeu_Purrfectpetcaredb';
$username = 'wqytuyeu_Admin';
$password = 'p3Kl1k;N:Q99Cj';

echo "<div class='step'><h3>Step 1: Configuration Check</h3>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Database:</strong> $dbname</p>";
echo "<p><strong>Username:</strong> $username</p>";
echo "<p><strong>Password:</strong> ✅ Set</p>";
echo "</div>";

echo "<div class='step'><h3>Step 2: Connection Test</h3>";

try {
    // Test connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<p><span class='success'>✅ Database connection successful!</span></p>";
    echo "<p><span class='success'>✅ PDO connection established</span></p>";
    
    // Test basic query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "<p><span class='success'>✅ MySQL Version: " . $version['version'] . "</span></p>";
    
} catch (PDOException $e) {
    echo "<p><span class='error'>❌ Connection failed!</span></p>";
    echo "<p><span class='error'>Error: " . $e->getMessage() . "</span></p>";
    
    // Provide specific guidance based on error
    if ($e->getCode() == 1049) {
        echo "<p><span class='info'>💡 Database '$dbname' does not exist. Create it in cPanel first.</span></p>";
    } elseif ($e->getCode() == 1045) {
        echo "<p><span class='info'>💡 Access denied. Check username and password.</span></p>";
    } elseif ($e->getCode() == 2002) {
        echo "<p><span class='info'>💡 Cannot connect to MySQL server. Check if MySQL is running.</span></p>";
    }
    
    echo "<p><span class='info'>📋 Troubleshooting steps:</span></p>";
    echo "<ol>";
    echo "<li>Verify database exists in cPanel</li>";
    echo "<li>Check username and password</li>";
    echo "<li>Ensure user has proper privileges</li>";
    echo "<li>Verify MySQL is enabled in cPanel</li>";
    echo "</ol>";
    exit;
}
echo "</div>";

echo "<div class='step'><h3>Step 3: Table Check</h3>";

try {
    // Check if tables exist
    $tables = ['users', 'pets', 'appointments', 'medical_records', 'vaccinations', 'payments', 'forum_posts', 'adoptions', 'lost_found'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p><span class='success'>✅ Table '$table' exists</span></p>";
        } else {
            echo "<p><span class='warning'>⚠️ Table '$table' does not exist</span></p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p><span class='error'>❌ Error checking tables: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

echo "<div class='step'><h3>Step 4: Data Check</h3>";

try {
    // Check if sample data exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "<p><span class='info'>📊 Users in database: $userCount</span></p>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pets");
    $petCount = $stmt->fetch()['count'];
    echo "<p><span class='info'>📊 Pets in database: $petCount</span></p>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $appointmentCount = $stmt->fetch()['count'];
    echo "<p><span class='info'>📊 Appointments in database: $appointmentCount</span></p>";
    
    if ($userCount == 0) {
        echo "<p><span class='warning'>⚠️ No users found. Run init_database.php to create sample data.</span></p>";
    }
    
} catch (PDOException $e) {
    echo "<p><span class='error'>❌ Error checking data: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

echo "<div class='step'><h3>Step 5: Next Steps</h3>";

echo "<p><span class='success'>✅ Database connection is working perfectly!</span></p>";
echo "<p><strong>You can now:</strong></p>";
echo "<ul>";
echo "<li>✅ Access your system at <a href='index.html'>index.html</a></li>";
echo "<li>✅ Login with test accounts</li>";
echo "<li>✅ Test all dashboard features</li>";
echo "<li>✅ Use all CRUD operations</li>";
echo "</ul>";

echo "</div>";
?> 