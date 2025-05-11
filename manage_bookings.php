<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

// Kiểm tra nếu người dùng không phải chủ sân, chuyển hướng về trang phù hợp
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'owner') {
    if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'customer') {
        header('Location: search.php');
        exit;
    } elseif (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin') {
        header('Location: admin_users.php');
        exit;
    } else {
        header('Location: login.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Xử lý xác nhận hoặc hủy đặt sân trước khi xuất bất kỳ dữ liệu nào
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $error = '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $booking_id = (int)$_POST['booking_id'];
        $action = $_POST['action'];

        // Kiểm tra xem đặt sân có thuộc về sân bóng của chủ sân không
        $stmt = $pdo->prepare("SELECT b.* FROM bookings b 
                               JOIN fields f ON b.field_id = f.id 
                               WHERE b.id = ? AND f.owner_id = ?");
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $error = 'Đặt sân không hợp lệ hoặc không thuộc quyền quản lý của bạn.';
        } else {
            if ($action === 'confirm') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                $stmt->execute([$booking_id]);

                // Gửi thông báo cho khách hàng
                $notification_message = "Yêu cầu đặt sân của bạn (ID #$booking_id) đã được xác nhận.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'booking_confirmed', ?)");
                $stmt->execute([$booking['user_id'], $notification_message, $booking_id]);

                $success = "Đã xác nhận đặt sân.";
            } elseif ($action === 'cancel') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$booking_id]);

                // Gửi thông báo cho khách hàng
                $notification_message = "Yêu cầu đặt sân của bạn (ID #$booking_id) đã bị hủy.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'booking_confirmed', ?)");
                $stmt->execute([$booking['user_id'], $notification_message, $booking_id]);

                $success = "Đã hủy đặt sân.";
            } else {
                $error = 'Hành động không hợp lệ.';
            }
        }
    }

    // Lưu thông báo vào session và chuyển hướng
    if ($error) {
        $_SESSION['error'] = $error;
    } elseif ($success) {
        $_SESSION['success'] = $success;
    }
    header('Location: manage_bookings.php');
    exit;
}

// Lấy danh sách đặt sân của sân bóng thuộc chủ sân
$stmt = $pdo->prepare("SELECT b.*, f.name as field_name, u.full_name as customer_name 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       JOIN users u ON b.user_id = u.id 
                       WHERE f.owner_id = ? 
                       ORDER BY b.created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy thông báo từ session nếu có
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error']);
unset($_SESSION['success']);

// Chỉ bao gồm header.php sau khi xử lý logic
require_once 'includes/header.php';
?>

<style>
    /* Tiêu đề trang */
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    /* Bảng quản lý */
    .booking-table thead th {
        font-size: 1rem;
        padding: 12px;
        background: #2a5298;
        color: #fff;
    }
    .booking-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .booking-table td {
        padding: 12px;
        font-size: 0.95rem;
    }
    .booking-table .price {
        color: #e74c3c;
        font-weight: 600;
        font-size: 1.1rem;
    }
    .booking-table .status-badge {
        font-size: 0.95rem;
    }
    .booking-table .btn {
        padding: 8px 20px;
        font-size: 1rem;
    }
    .booking-table .btn-primary {
        background-color: #28a745;
    }
    .booking-table .btn-primary:hover {
        background-color: #218838;
    }
    .booking-table .btn-danger {
        background-color: #e74c3c;
    }
    .booking-table .btn-danger:hover {
        background-color: #c0392b;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1.2rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .booking-table thead th,
        .booking-table td {
            padding: 10px;
            font-size: 0.9rem;
        }
        .booking-table .btn {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
    }
</style>

<section class="manage-bookings py-3">
    <div class="container">
        <h2 class="section-title">Quản Lý Đặt Sân</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); // Xóa biến $success sau khi hiển thị ?>
        <?php endif; ?>
        <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($error); // Xóa biến $error sau khi hiển thị ?>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <p class="text-center text-muted">Không có đặt sân nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table booking-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sân bóng</th>
                            <th>Khách hàng</th>
                            <th>Ngày đặt</th>
                            <th>Khung giờ</th>
                            <th>Tổng giá</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['start_time'] . ' - ' . $booking['end_time']); ?></td>
                                <td class="price"><?php echo number_format($booking['total_price'], 0, ',', '.') . ' VND'; ?></td>
                                <td>
                                    <span class="badge <?php echo $booking['status'] === 'pending' ? 'bg-warning' : ($booking['status'] === 'confirmed' ? 'bg-success' : 'bg-danger'); ?> status-badge">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-check-circle"></i> Xác nhận
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-x-circle"></i> Hủy
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>