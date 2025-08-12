<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "ahv2_db"; // <-- your database name in phpMyAdmin

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
