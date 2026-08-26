<?php
/**
 * WEZO CAMPUS HUB Database Class
 * Powered by AYGLOBE INC
 */

namespace Core;

use PDO;
use PDOException;
use Exception;

class Database {
    private static $instance = null;
    private $connection;
    private $config;
    private $isConnected = false;
    
    private function __construct() {
        // Use Config class for configuration
        $this->config = Config::getDatabaseConfig();
        $this->connect();
    }
    
    /**
     * Get database instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        try {
            // Build DSN with port
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];
            
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            
            // Test connection
            $this->connection->query("SELECT 1");
            $this->isConnected = true;
            
            // Set timezone if needed
            $this->connection->exec("SET time_zone = '+03:00'"); // Nairobi timezone
            
        } catch (PDOException $e) {
            $this->logError('Database Connection Error', $e->getMessage(), [
                'config' => [
                    'host' => $this->config['host'],
                    'database' => $this->config['database'],
                    'username' => $this->config['username'],
                    'port' => $this->config['port'] ?? '3306'
                ],
                'error_code' => $e->getCode(),
                'error_info' => $e->errorInfo ?? []
            ]);
            
            // User-friendly error messages
            $errorMsg = $this->getUserFriendlyError($e);
            throw new Exception($errorMsg);
        }
    }
    
    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyError(PDOException $e) {
        switch ($e->getCode()) {
            case 1045: // Access denied
                return "Database access denied for user '{$this->config['username']}'. Please check your username and password.";
            
            case 1049: // Unknown database
                $dbName = $this->config['database'];
                return "Database '{$dbName}' does not exist. Please create the database first.";
            
            case 2002: // Connection refused
                return "Cannot connect to MySQL server on '{$this->config['host']}:{$this->config['port']}'. Make sure MySQL service is running.";
            
            case 0: // General error
                if (strpos($e->getMessage(), 'SQLSTATE[HY000]') !== false) {
                    return "Database connection failed. Please check your database configuration.";
                }
                // fall through
            
            default:
                return "Database connection failed: " . $e->getMessage();
        }
    }
    
    /**
     * Check if connected
     */
    public function isConnected() {
        return $this->isConnected;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        if (!$this->isConnected) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Execute a query
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query Error', $e->getMessage(), [
                'sql' => $sql,
                'params' => $params,
                'error_info' => $e->errorInfo ?? []
            ]);
            
            if (Config::APP_ENV === 'development') {
                throw new Exception("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            }
            
            throw new Exception('Database operation failed. Please try again.');
        }
    }
    
    /**
     * Fetch a single row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Fetch column
     */
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }
    
    /**
     * Get row count
     */
    public function rowCount($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId($name = null) {
        return $this->connection->lastInsertId($name);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Insert data into table
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->lastInsertId();
    }
    
    /**
     * Update data in table
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "`{$key}` = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete data from table
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Check if record exists
     */
    public function exists($table, $where, $params = []) {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
        return $this->fetchColumn($sql, $params) > 0;
    }
    
    /**
     * Paginate results
     */
    public function paginate($sql, $params = [], $page = 1, $perPage = 10) {
        // Remove ORDER BY for count query
        $countSql = preg_replace('/ORDER BY .*/i', '', $sql);
        $countSql = preg_replace('/SELECT .*? FROM/i', 'SELECT COUNT(*) as total FROM', $countSql, 1);
        
        $total = $this->fetchColumn($countSql, $params, 0);
        $totalPages = ceil($total / $perPage);
        $offset = max(0, ($page - 1) * $perPage);
        
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        $results = $this->fetchAll($sql, $params);
        
        return [
            'data' => $results,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }
    
    /**
     * Sanitize input
     */
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Escape string for safe SQL use
     */
    public function quote($string) {
        return $this->connection->quote($string);
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $this->connection->query("SELECT 1");
            return ['success' => true, 'message' => 'Database connection successful'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get database information
     */
    public function getDatabaseInfo() {
        try {
            $info = [];
            
            // Database name
            $stmt = $this->query("SELECT DATABASE() as db");
            $info['database'] = $stmt->fetchColumn();
            
            // Version
            $stmt = $this->query("SELECT VERSION() as version");
            $info['version'] = $stmt->fetchColumn();
            
            // Character set
            $stmt = $this->query("SHOW VARIABLES LIKE 'character_set_database'");
            $info['charset'] = $stmt->fetchColumn(1);
            
            // Table count
            $stmt = $this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?", [$info['database']]);
            $info['table_count'] = $stmt->fetchColumn();
            
            return $info;
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Log database errors
     */
    private function logError($type, $message, $context = []) {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logMessage = date('[Y-m-d H:i:s]') . " {$type}: {$message}" . PHP_EOL;
        
        if (!empty($context)) {
            $logMessage .= "Context:" . PHP_EOL;
            foreach ($context as $key => $value) {
                $logMessage .= "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . PHP_EOL;
            }
        }
        
        $logMessage .= "Backtrace:" . PHP_EOL;
        $logMessage .= $this->getDebugBacktrace() . PHP_EOL;
        $logMessage .= str_repeat('-', 80) . PHP_EOL;
        
        file_put_contents($logDir . 'database_errors.log', $logMessage, FILE_APPEND);
        
        // Also log to PHP error log for development
        if (Config::APP_ENV === 'development') {
            error_log("Database Error: {$type} - {$message}");
        }
    }
    
    /**
     * Get debug backtrace
     */
    private function getDebugBacktrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $output = '';
        
        foreach ($backtrace as $index => $trace) {
            if ($index === 0 || $index === 1) continue; // Skip current method and logError
            
            $file = $trace['file'] ?? '[internal function]';
            $line = $trace['line'] ?? '';
            $function = $trace['function'] ?? '';
            $class = $trace['class'] ?? '';
            
            $output .= "#{$index} ";
            if ($file !== '[internal function]') {
                $output .= "{$file}({$line}): ";
            }
            if ($class) {
                $output .= "{$class}->";
            }
            $output .= "{$function}()" . PHP_EOL;
        }
        
        return $output;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {}
    
    /**
     * Destructor - close connection
     */
    public function __destruct() {
        $this->connection = null;
        $this->isConnected = false;
    }
}