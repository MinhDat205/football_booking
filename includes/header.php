<?php
require_once 'includes/csrf.php';
$csrf_token = generateCsrfToken();

// Lấy số lượng thông báo chưa đọc và danh sách thông báo nếu người dùng đã đăng nhập
$unread_notifications_count = 0;
$notifications = [];
$error = '';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Lấy số lượng thông báo chưa đọc
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications_count = $stmt->fetchColumn();

    // Lấy danh sách thông báo
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Xử lý đánh dấu thông báo đã đọc
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_read'])) {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!verifyCsrfToken($token)) {
            $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
        } else {
            $notification_id = (int)$_POST['notification_id'];
            $stmt = $pdo->prepare("SELECT is_read FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($notification && !$notification['is_read']) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }
}

// Lấy tên file hiện tại từ URL để làm nổi bật mục active
$current_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Sân Bóng Đá</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        /* Header */
        header {
            background: #1e3c72;
            padding: 10px 0;
        }
        header .logo h3 {
            font-weight: 600;
            color: #fff;
            margin: 0;
            font-size: 1.5rem;
        }
        header .btn-outline-light {
            border-radius: 5px;
            padding: 6px 15px;
            font-size: 0.9rem;
            border: 1px solid #fff;
            color: #fff;
        }
        header .btn-outline-light:hover {
            background-color: #fff;
            color: #1e3c72;
        }
        header .btn-light {
            border-radius: 5px;
            padding: 6px 15px;
            font-size: 0.9rem;
            background-color: #fff;
            color: #1e3c72;
        }
        header .btn-light:hover {
            background-color: #e6f0ff;
        }
        /* Sidebar */
        .sidebar {
            width: 200px;
            background: #2a5298;
            color: #fff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 50px;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 10px 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #1e3c72;
        }
        .sidebar .nav-link i {
            font-size: 1.1rem;
        }
        /* Main content */
        .main-content {
            margin-left: <?php echo isset($_SESSION['user_id']) ? '200px' : '0'; ?>;
            padding: 15px;
        }
        /* Container */
        .container {
            padding: 20px 10px;
        }
        /* Card sân bóng */
        .card {
            border: 1px solid #e0e4e9;
            border-radius: 5px;
            background-color: #fff;
        }
        .card-img-top {
            height: 150px;
            object-fit: cover;
        }
        .card-body {
            padding: 10px;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 5px;
        }
        .card-text {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        .btn-primary {
            background-color: #2a5298;
            border: none;
            border-radius: 5px;
            padding: 6px 15px;
            font-size: 0.9rem;
        }
        .btn-primary:hover {
            background-color: #1e3c72;
        }
        /* Modal */
        .modal-content {
            border-radius: 5px;
        }
        .modal-header {
            background-color: #f5f7fa;
            border-bottom: none;
        }
        .modal-title {
            font-weight: 600;
            color: #1e3c72;
        }
        .modal-body {
            padding: 15px;
        }
        /* Product card trong modal */
        .product-card {
            border: 1px solid #e0e4e9;
            border-radius: 5px;
            background-color: #fff;
        }
        .product-card img {
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            max-height: 80px;
            object-fit: cover;
        }
        .product-card .card-body {
            padding: 10px;
        }
        .product-card .card-title {
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 5px;
        }
        .product-card .card-text {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
        }
        .product-card .price {
            font-weight: 600;
            color: #e74c3c;
            font-size: 0.85rem;
        }
        .product-card .form-check {
            margin-top: 5px;
        }
        /* Bảng quản lý */
        .table {
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #e0e4e9;
        }
        .table thead {
            background: #2a5298;
            color: #fff;
        }
        .table thead th {
            font-weight: 600;
            padding: 10px;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .table td {
            vertical-align: middle;
            padding: 10px;
        }
        /* Form tìm kiếm */
        .search form {
            background-color: #fff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .search form input, .search form select, .search form button {
            border-radius: 5px;
            padding: 6px 10px;
            font-size: 0.9rem;
            border: 1px solid #e0e4e9;
        }
        .search form input:focus, .search form select:focus {
            border-color: #2a5298;
            outline: none;
        }
        /* Dropdown thông báo */
        .dropdown-menu {
            border-radius: 5px;
            max-height: 250px;
            overflow-y: auto;
        }
        .dropdown-item {
            padding: 8px 10px;
            font-size: 0.9rem;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .dropdown-item.fw-bold {
            background-color: #e6f0ff;
        }
        .badge.bg-danger {
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 0.75rem;
            background-color: #e74c3c;
        }
        /* Hamburger menu for mobile */
        .hamburger {
            display: none;
            font-size: 1.3rem;
            color: #fff;
            cursor: pointer;
        }
        /* Responsive design */
        @media (max-width: 768px) {
            .sidebar {
                left: -200px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .main-content.active {
                margin-left: 200px;
            }
            .hamburger {
                display: block;
            }
            header form {
                flex-direction: column;
                gap: 5px;
            }
            header form input, header form select, header form button {
                width: 100%;
            }
            .card-img-top {
                height: 120px;
            }
            .card-body {
                padding: 8px;
            }
            .modal-dialog {
                margin: 5px;
            }
            .modal-body {
                padding: 10px;
            }
            .table {
                font-size: 0.8rem;
            }
            .table thead th, .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="hamburger" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </div>
                <?php endif; ?>
                <div class="logo">
                    <h3>Football Booking</h3>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-0 py-1 px-2" role="alert" style="font-size: 0.8rem;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($error); // Xóa biến $error sau khi hiển thị ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Icon thông báo -->
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-1" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill"></i> Thông Báo 
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_notifications_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <?php if (empty($notifications)): ?>
                                <li><a class="dropdown-item text-muted" href="#">Không có thông báo</a></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <?php
                                        // Xác định liên kết dựa trên loại thông báo
                                        $notification_link = '#';
                                        if ($notification['type'] === 'new_message_conversation') {
                                            // Lấy conversation_id từ related_id (message_id)
                                            $stmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = ?");
                                            $stmt->execute([$notification['related_id']]);
                                            $message = $stmt->fetch(PDO::FETCH_ASSOC);
                                            if ($message) {
                                                $notification_link = '/football_booking/chat.php?conversation_id=' . $message['conversation_id'];
                                            }
                                        } elseif ($notification['type'] === 'booking_confirmed') {
                                            $notification_link = '/football_booking/history.php?booking_id=' . $notification['related_id'];
                                        }
                                        ?>
                                        <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>" href="<?php echo $notification_link; ?>">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($notification['created_at']); ?></small>
                                        </a>
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" name="mark_notification_read" class="btn btn-link btn-sm text-decoration-none d-flex align-items-center gap-1">
                                                    <i class="bi bi-check-all"></i> Đánh dấu đã đọc
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <a href="/football_booking/profile.php" class="btn btn-outline-light">Hồ sơ</a>
                    <?php if (isset(
                        $_SESSION['account_type']) && in_array($_SESSION['account_type'], ['customer', 'owner'])): ?>
                        <a href="/football_booking/support.php" class="btn btn-outline-light">Support</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/football_booking/login.php" class="btn btn-outline-light">Đăng nhập</a>
                    <a href="/football_booking/register.php" class="btn btn-outline-light">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'customer'): ?>
                <!-- Khách hàng -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'search.php' ? 'active' : ''; ?>" href="/football_booking/search.php">
                        <i class="bi bi-house-door-fill"></i> Trang chủ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="/football_booking/history.php">
                        <i class="bi bi-clock-history"></i> Lịch sử đặt sân
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>" href="/football_booking/chat.php">
                        <i class="bi bi-chat-dots-fill"></i> Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/football_booking/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                    </a>
                </li>
            <?php elseif (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'owner'): ?>
                <!-- Chủ sân -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="/football_booking/history.php">
                        <i class="bi bi-clock-history"></i> Lịch sử đặt sân
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>" href="/football_booking/chat.php">
                        <i class="bi bi-chat-dots-fill"></i> Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'manage_field.php' ? 'active' : ''; ?>" href="/football_booking/manage_field.php">
                        <i class="bi bi-gear-fill"></i> Quản lý sân
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'manage_products.php' ? 'active' : ''; ?>" href="/football_booking/manage_products.php">
                        <i class="bi bi-box"></i> Quản lý sản phẩm
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'manage_bookings.php' ? 'active' : ''; ?>" href="/football_booking/manage_bookings.php">
                        <i class="bi bi-calendar-check"></i> Quản lý đặt sân
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'revenue.php' ? 'active' : ''; ?>" href="/football_booking/revenue.php">
                        <i class="bi bi-currency-dollar"></i> Theo dõi thu nhập
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/football_booking/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                    </a>
                </li>
            <?php elseif (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin'): ?>
                <!-- Admin -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_users.php' ? 'active' : ''; ?>" href="/football_booking/admin_users.php">
                        <i class="bi bi-person-fill"></i> Quản lý người dùng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_fields.php' ? 'active' : ''; ?>" href="/football_booking/admin_fields.php">
                        <i class="bi bi-soccer-ball-fill"></i> Quản lý sân bóng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_support.php' ? 'active' : ''; ?>" href="/football_booking/admin_support.php">
                        <i class="bi bi-headset"></i> Yêu cầu hỗ trợ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/football_booking/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Main content -->
    <div class="main-content" id="main-content">
        <script>
            function toggleSidebar() {
                document.getElementById('sidebar')?.classList.toggle('active');
                document.getElementById('main-content')?.classList.toggle('active');
            }
        </script>