<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "ahv2_db"; // <-- your database name in phpMyAdmin
$port = 3307; // <-- IMPORTANT: use your new MySQL port

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
