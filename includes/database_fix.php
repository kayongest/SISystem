<?php
// database_fix.php - Database connection helper

class DatabaseFix {
    private $conn;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $host = 'localhost';
        $dbname = 'ability_db';
        $username = 'root';
        $password = '';
        
        try {
            $this->conn = new mysqli($host, $username, $password, $dbname);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    // Helper method for PDO if needed
    public static function getPDO() {
        static $pdo = null;
        
        if ($pdo === null) {
            try {
                $host = 'localhost';
                $dbname = 'ability_db';
                $username = 'root';
                $password = '';
                
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("PDO Connection failed: " . $e->getMessage());
            }
        }
        
        return $pdo;
    }
}
?>