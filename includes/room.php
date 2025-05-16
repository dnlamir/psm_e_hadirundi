<?php
$host = "localhost"; // Change if necessary
$user = "root"; // Change if necessary
$password = ""; // Change if necessary
$database = "user"; // Database name is 'user'

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set PHP timezone to Malaysia time (UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Set MySQL session timezone to match PHP
$conn->query("SET time_zone = '+08:00'");
?>
