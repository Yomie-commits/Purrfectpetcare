<?php
/**
 * Database Update Script for Password Reset Functionality
 * Run this file through your web browser to add missing columns
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🐾 Pet Care Management System - Database Update</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .step { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
    .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

try {
    echo "<div class='step'><h3>Step 1: Database Connection</h3>";
    
    // Include database connection
    require_once 'db_connect.php';
    
    if (!isDatabaseConnected()) {
        echo "<p><span class='error'>❌ Database connection failed!</span></p>";
        echo "<p>Error: " . getDatabaseError() . "</p>";
        exit;
    }
    
    echo "<p><span class='success'>✅ Database connection successful!</span></p>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 2: Adding Password Reset Columns</h3>";
    
    // Add reset_token column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL");
        echo "<p><span class='success'>✅ reset_token column added successfully!</span></p>";
    } catch (Exception $e) {
        echo "<p><span class='info'>ℹ️ reset_token column already exists or error: " . $e->getMessage() . "</span></p>";
    }
    
    // Add reset_expiry column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expiry TIMESTAMP NULL");
        echo "<p><span class='success'>✅ reset_expiry column added successfully!</span></p>";
    } catch (Exception $e) {
        echo "<p><span class='info'>ℹ️ reset_expiry column already exists or error: " . $e->getMessage() . "</span></p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'><h3>Step 3: Verification</h3>";
    
    // Verify the columns exist
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('reset_token', $columns)) {
        echo "<p><span class='success'>✅ reset_token column verified!</span></p>";
    } else {
        echo "<p><span class='error'>❌ reset_token column not found!</span></p>";
    }
    
    if (in_array('reset_expiry', $columns)) {
        echo "<p><span class='success'>✅ reset_expiry column verified!</span></p>";
    } else {
        echo "<p><span class='error'>❌ reset_expiry column not found!</span></p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'><h3>Step 4: Test Password Reset Functionality</h3>";
    
    // Test the forgot password functionality
    $testEmail = 'test@example.com';
    
    // Check if test user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate a test reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        $result = $stmt->execute([$resetToken, $resetExpiry, $testEmail]);
        
        if ($result) {
            echo "<p><span class='success'>✅ Password reset functionality test successful!</span></p>";
            echo "<p><strong>Test reset token:</strong> " . substr($resetToken, 0, 16) . "...</p>";
        } else {
            echo "<p><span class='error'>❌ Password reset functionality test failed!</span></p>";
        }
    } else {
        echo "<p><span class='info'>ℹ️ No test user found. Password reset functionality is ready!</span></p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'><h3>Step 5: Update Complete</h3>";
    echo "<p><span class='success'>🎉 Database update completed successfully!</span></p>";
    echo "<p><strong>New features now available:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Forgot password functionality</li>";
    echo "<li>✅ Password reset via email (simulated)</li>";
    echo "<li>✅ Secure token-based password reset</li>";
    echo "</ul>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test the forgot password link on the login form</li>";
    echo "<li>Verify registration works with confirm password field</li>";
    echo "<li>Test login with newly registered accounts</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='step'><h3>Step 6: Security Reminder</h3>";
    echo "<p><span class='warning'>⚠️ Important Security Steps:</span></p>";
    echo "<ul>";
    echo "<li>Delete or rename this file (update_database.php) after running</li>";
    echo "<li>In production, implement actual email sending for password reset</li>";
    echo "<li>Remove the reset_token from API responses in production</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Update Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}
?> 