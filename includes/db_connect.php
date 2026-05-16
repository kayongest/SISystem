<?php
class Database
{
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "ability_db";
    private $conn;

    public function __construct()
    {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    public function lastInsertId()
    {
        return $this->conn->insert_id;
    }

    public function close()
    {
        $this->conn->close();
    }
}

// Add global getConnection() function for backward compatibility - OUTSIDE the class
if (!function_exists('getConnection')) {
    function getConnection()
    {
        static $db = null;

        if ($db === null) {
            $db = new Database();
        }

        return $db->getConnection();
    }
}

// Function to get Database instance
if (!function_exists('getDatabase')) {
    function getDatabase()
    {
        static $db = null;

        if ($db === null) {
            $db = new Database();
        }

        return $db;
    }
}

// Create items table if not exists
function createTables()
{
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS items (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        item_name VARCHAR(255) NOT NULL,
        serial_number VARCHAR(100) NOT NULL UNIQUE,
        category VARCHAR(50),
        department VARCHAR(50),
        description TEXT,
        brand_model VARCHAR(100),
        `condition` VARCHAR(20) DEFAULT 'good',
        stock_location VARCHAR(255),
        notes TEXT,
        quantity INT(11) DEFAULT 1,
        status VARCHAR(20) DEFAULT 'available',
        image VARCHAR(500),
        qr_code VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_status (status),
        INDEX idx_location (stock_location)
    )";

    $conn->query($sql);
    $db->close();
}

// Initialize database
createTables();
