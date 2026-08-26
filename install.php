<?php
/**
 * WEZO CAMPUS HUB Installation Script
 * Run this script to setup your database
 * Powered by AYGLOBE INC | Founder: Ayman Muhammad
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration manually for installation
require_once __DIR__ . '/core/Config.php';

use Core\Config;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WEZO CAMPUS HUB - Installation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1A56DB; border-bottom: 2px solid #10B981; padding-bottom: 10px; }
        .success { color: #10B981; background: #f0fdf4; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc2626; background: #fef2f2; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f59e0b; background: #fffbeb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3b82f6; background: #eff6ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .step { background: #f8fafc; padding: 15px; margin: 15px 0; border-left: 4px solid #1A56DB; }
        .btn { background: #1A56DB; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1e40af; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f8fafc; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 WEZO CAMPUS HUB Installation</h1>
        <p><strong>Powered by AYGLOBE INC | Founder: Ayman Muhammad</strong></p>
        
        <?php
        // Get database configuration
        $config = Config::getDatabaseConfig();
        $dbHost = $config['host'];
        $dbPort = $config['port'] ?? 3306;
        $dbUser = $config['username'];
        $dbPass = $config['password'];
        $dbName = $config['database'];
        
        echo "<div class='info'>";
        echo "<strong>Database Configuration:</strong><br>";
        echo "Host: {$dbHost}:{$dbPort}<br>";
        echo "Database: {$dbName}<br>";
        echo "Username: {$dbUser}<br>";
        echo "Password: " . (empty($dbPass) ? '(empty)' : '••••••••') . "<br>";
        echo "</div>";
        
        // Step 1: Test connection
        echo "<div class='step'>";
        echo "<h3>Step 1: Testing Database Connection</h3>";
        
        try {
            // Try to connect without database first
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            echo "<div class='success'>✅ Connected to MySQL server successfully!</div>";
            
            // Check if database exists
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbName}'");
            $dbExists = $stmt->rowCount() > 0;
            
            if ($dbExists) {
                echo "<div class='info'>✅ Database '{$dbName}' exists</div>";
            } else {
                echo "<div class='warning'>⚠️ Database '{$dbName}' doesn't exist. It will be created.</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Connection failed: " . $e->getMessage() . "</div>";
            echo "<p>Please check your database configuration in core/Config.php</p>";
            echo "<p>For XAMPP, make sure:</p>";
            echo "<ul>";
            echo "<li>MySQL service is running</li>";
            echo "<li>Username is 'root'</li>";
            echo "<li>Password is empty (default)</li>";
            echo "</ul>";
            exit;
        }
        echo "</div>";
        
        // Step 2: Create database if needed
        echo "<div class='step'>";
        echo "<h3>Step 2: Creating/Verifying Database</h3>";
        
        try {
            if (!$dbExists) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "<div class='success'>✅ Database '{$dbName}' created successfully!</div>";
            }
            
            // Select the database
            $pdo->exec("USE `{$dbName}`");
            echo "<div class='success'>✅ Using database '{$dbName}'</div>";
            
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
            exit;
        }
        echo "</div>";
        
        // Step 3: Import schema
        echo "<div class='step'>";
        echo "<h3>Step 3: Creating Tables</h3>";
        
        $schemaFile = __DIR__ . '/schema.sql';
        
        if (!file_exists($schemaFile)) {
            echo "<div class='error'>❌ Schema file not found: schema.sql</div>";
            echo "<p>Please make sure the schema.sql file exists in your root directory.</p>";
        } else {
            $schema = file_get_contents($schemaFile);
            
            // Split into individual queries
            $queries = explode(';', $schema);
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    try {
                        // Skip comments and empty lines
                        if (strpos($query, '--') === 0) continue;
                        
                        $pdo->exec($query);
                        $successCount++;
                    } catch (PDOException $e) {
                        $errorCount++;
                        $errors[] = [
                            'query' => substr($query, 0, 100) . '...',
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }
            
            echo "<div class='success'>✅ {$successCount} queries executed successfully</div>";
            
            if ($errorCount > 0) {
                echo "<div class='warning'>⚠️ {$errorCount} queries had errors</div>";
                echo "<details>";
                echo "<summary>Show Errors</summary>";
                echo "<table>";
                echo "<tr><th>Query (first 100 chars)</th><th>Error</th></tr>";
                foreach ($errors as $error) {
                    echo "<tr><td>{$error['query']}</td><td>{$error['error']}</td></tr>";
                }
                echo "</table>";
                echo "</details>";
            }
            
            // Verify tables were created
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<div class='info'>📊 Database now contains " . count($tables) . " tables</div>";
            
            if (count($tables) > 0) {
                echo "<details>";
                echo "<summary>Show Tables</summary>";
                echo "<table>";
                echo "<tr><th>Table Name</th></tr>";
                foreach ($tables as $table) {
                    echo "<tr><td>{$table}</td></tr>";
                }
                echo "</table>";
                echo "</details>";
            }
        }
        echo "</div>";
        
        // Step 4: Verify installation
        echo "<div class='step'>";
        echo "<h3>Step 4: Verification</h3>";
        
        try {
            // Check essential tables
            $essentialTables = ['users', 'notes', 'marketplace_items', 'hostels'];
            $missingTables = [];
            
            foreach ($essentialTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() === 0) {
                    $missingTables[] = $table;
                }
            }
            
            if (empty($missingTables)) {
                echo "<div class='success'>✅ All essential tables created successfully!</div>";
            } else {
                echo "<div class='error'>❌ Missing tables: " . implode(', ', $missingTables) . "</div>";
            }
            
            // Check if admin user exists
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount > 0) {
                echo "<div class='success'>✅ Admin user created</div>";
            } else {
                echo "<div class='warning'>⚠️ Admin user not found in database</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Verification error: " . $e->getMessage() . "</div>";
        }
        echo "</div>";
        
        // Step 5: Final steps
        echo "<div class='step'>";
        echo "<h3>Step 5: Final Steps</h3>";
        
        echo "<div class='info'>";
        echo "<strong>Installation Complete! 🎉</strong><br>";
        echo "Your WEZO CAMPUS HUB database has been setup.<br><br>";
        
        echo "<strong>Next Steps:</strong><br>";
        echo "1. <a href='index.php' class='btn'>Go to Homepage</a><br>";
        echo "2. Login with: <br>";
        echo "   - Username: <strong>admin</strong><br>";
        echo "   - Password: <strong>admin123</strong><br><br>";
        
        echo "<strong>Security Note:</strong><br>";
        echo "• Change the default admin password immediately<br>";
        echo "• Delete this install.php file for security<br>";
        echo "• Update your Config.php for production use<br>";
        echo "</div>";
        echo "</div>";
        
        ?>
        
        <hr>
        <p style="text-align: center; color: #6b7280;">
            &copy; <?php echo date('Y'); ?> WEZO CAMPUS HUB | Powered by AYGLOBE INC | Founder: Ayman Muhammad
        </p>
    </div>
</body>
</html>