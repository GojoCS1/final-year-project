<?php
$servername = "127.0.0.1"; // Just the IP, no port here
$username = "root";
$password = "";
$dbname = "dhrs_db"; // Your database name
$port = 3307; // Your specific port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Automatically check if a backup is needed today
include 'auto_backup.php'; 
?>