<?php
// SET TIMEZONE TO MALAYSIA (UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

class Database {
    private $host = 'localhost';
    private $db_name = 'salon'; // Matches the folder name in your screenshot
    private $username = 'root';
    private $password = ''; // Default XAMPP password is empty
    public $conn;

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            // REMOVED $this->port to prevent errors
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Optional: Uncomment the line below to test if it works (delete after testing)
            // echo "Connected successfully to database: " . $this->db_name;

        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            // If connection fails, stop the script and show the error
            die("Connection failed: " . $exception->getMessage()); 
        }

        return $this->conn;
    }
}
?>