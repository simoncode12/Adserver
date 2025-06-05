<?php
/**
 * Database Configuration (Fixed)
 * AdServer Platform Database Connection
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'user_ad');
define('DB_PASS', 'Puputchen12$');
define('DB_NAME', 'user_ad');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Update method (FIXED) - ensures consistent parameter binding
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "{$field} = :{$field}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
        
        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }
        
        // Handle WHERE parameters - convert positional to named if needed
        if (!empty($whereParams)) {
            if (is_array($whereParams) && isset($whereParams[0])) {
                // Positional parameters - convert WHERE clause to use named parameters
                $whereParamCount = count($whereParams);
                $namedWhere = $where;
                for ($i = 0; $i < $whereParamCount; $i++) {
                    $namedWhere = preg_replace('/\?/', ':where_param_' . $i, $namedWhere, 1);
                    $params[':where_param_' . $i] = $whereParams[$i];
                }
                $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$namedWhere}";
            } else {
                // Named parameters
                $params = array_merge($params, $whereParams);
            }
        }
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
?>
