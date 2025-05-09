<?php
$password = 'newadmin2025';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Chuỗi mã hóa: " . $hashed_password;
?>