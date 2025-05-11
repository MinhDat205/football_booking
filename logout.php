<?php
session_start();

// Xử lý đăng xuất trước khi gửi bất kỳ output nào
session_unset();
session_destroy();

header('Location: search.php');
exit;
?>