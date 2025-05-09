<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Sân Bóng Đá</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <header class="bg-primary text-white py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="logo">
                <h3>Football Booking</h3>
            </div>
            <form class="d-flex" action="search.php" method="GET">
                <input type="text" name="location" class="form-control me-2" placeholder="Vị trí">
                <input type="date" name="date" class="form-control me-2">
                <input type="time" name="time" class="form-control me-2">
                <button type="submit" class="btn btn-light">Tìm kiếm</button>
            </form>
            <div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="btn btn-outline-light">Hồ sơ</a>
                    <a href="logout.php" class="btn btn-outline-light">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light">Đăng nhập</a>
                    <a href="register.php" class="btn btn-outline-light">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>