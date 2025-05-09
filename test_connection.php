<?php
require_once 'includes/config.php';

try {
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "Kết nối đến cơ sở dữ liệu thành công!";
    }
} catch (PDOException $e) {
    echo "Lỗi kết nối: " . $e->getMessage();
}
?>