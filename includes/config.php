<?php
// Bắt đầu session
session_start();

// Kết nối cơ sở dữ liệu
$host = 'localhost';
$port = '3307'; // Cổng đúng theo XAMPP
$dbname = 'football_booking';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Kết nối thành công!";
} catch (PDOException $e) {
    die("Kết nối thất bại: " . $e->getMessage());
}
?>