<?php
// Database connection settings
$host = 'localhost';
$dbname = 'task_tracker';
$username = 'root';
$password = ''; // Change this to your MySQL root password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
