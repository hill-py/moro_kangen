<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'moro_kangen');
define('DB_CHARSET', 'utf8mb4');
 
function getDB() {
    static $conn = null;
 
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
 
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode([
                'error' => 'Koneksi database gagal: ' . $conn->connect_error
            ]));
        }
 
        $conn->set_charset(DB_CHARSET);
    }
 
    return $conn;
}