<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "speechcare_final_db";

$conn = mysqli_connect($host, $username, $password, $database);  

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

date_default_timezone_set("Asia/Colombo");

?>