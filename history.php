<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$account_type = $_SESSION['account_type'];

$bookings = [];
if ($account_type === 'customer') {
    $stmt = $pdo->prepare("SELECT b.*, f.name AS field_name, u.full_name AS owner_name, f.owner_id AS owner_id 
                           FROM bookings b 
                           JOIN fields f ON b.field_id = f.id 
                           JOIN users u ON f.owner_id = u.id 
                           WHERE b.user_id = ? 
                           ORDER BY b.booking_date DESC, b.start_time DESC");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($account_type === 'owner') {
    $stmt = $pdo->prepare("SELECT b.*, f.name AS field_name, u.full_name AS customer_name, u.id AS customer_id 
                           FROM bookings b 
                           JOIN fields f ON b.field_id = f.id 
                           JOIN users u ON b.user_id = u.id 
                           WHERE f.owner_id = ? 
                           ORDER BY b.booking_date DESC, b.start_time DESC");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kiểm tra xem khách hàng đã đánh giá sân cho booking cụ thể chưa
$existing_reviews = [];
if ($account_type === 'customer') {
    foreach ($bookings as $booking) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ? AND booking_id = ?");
        $stmt->execute([$user_id, $booking['id']]);
        $existing_reviews[$booking['id']] = $stmt->fetchColumn() > 0;
    }
}
?>

<style>
    /* Tiêu đề trang */
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }
    /* Bảng lịch sử đặt sân */
    .history-table thead th {
        font-size: 1rem;
        padding: 12px;
    }
    .history-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .history-table td {
        padding: 12px;
        font-size: 0.95rem;
    }
    .history-table .total-price {
        color: #e74c3c;
        font-weight: 600;
        font-size: 1.1rem;
    }
    .history-table .btn {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    .history-table .btn-primary {
        background-color: #2a5298;
    }
    .history-table .btn-primary:hover {
        background-color: #1e3c72;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1.2rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .history-table thead th,
        .history-table td {
            padding: 10px;
            font-size: 0.9rem;
        }
        .history-table .btn {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
    }
</style>

<section class="history py-3">
    <div class="container">
        <h2 class="section-title text-center">Lịch Sử Đặt Sân</h2>
        <?php if (empty($bookings)): ?>
            <p class="text-center text-muted">Bạn chưa có lịch sử đặt sân.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table history-table">
                    <thead>
                        <tr>
                            <?php if ($account_type === 'owner'): ?>
                                <th>Khách hàng</th>
                            <?php else: ?>
                                <th>Chủ sân</th>
                            <?php endif; ?>
                            <th>Sân</th>
                            <th>Ngày đặt</th>
                            <th>Giờ bắt đầu</th>
                            <th>Giờ kết thúc</th>
                            <th>Tổng giá</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <?php if ($account_type === 'owner'): ?>
                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($booking['owner_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($booking['end_time']); ?></td>
                                <td class="total-price"><?php echo number_format($booking['total_price'], 0, ',', '.') . ' VND'; ?></td>
                                <td>
                                    <span class="badge <?php echo $booking['status'] === 'pending' ? 'bg-warning' : ($booking['status'] === 'confirmed' ? 'bg-success' : ($booking['status'] === 'completed' ? 'bg-info' : 'bg-danger')); ?>">
                                        <?php 
                                        switch ($booking['status']) {
                                            case 'pending':
                                                echo 'Chờ xác nhận';
                                                break;
                                            case 'confirmed':
                                                echo 'Đã xác nhận';
                                                break;
                                            case 'completed':
                                                echo 'Đã hoàn thành';
                                                break;
                                            case 'cancelled':
                                                echo 'Đã hủy';
                                                break;
                                            default:
                                                echo htmlspecialchars($booking['status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($account_type === 'customer' && $booking['status'] === 'completed' && !$existing_reviews[$booking['id']]): ?>
                                        <a href="/football_booking/review.php?field_id=<?php echo $booking['field_id']; ?>&booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                            <i class="bi bi-star-fill"></i> Đánh giá
                                        </a>
                                    <?php elseif ($account_type === 'customer' && $booking['status'] === 'completed' && $existing_reviews[$booking['id']]): ?>
                                        <span class="text-muted">Đã đánh giá</span>
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