<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT b.*, f.name AS field_name 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       WHERE b.user_id = ? 
                       ORDER BY b.created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý hủy đặt sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $stmt = $pdo->prepare("SELECT booking_date, start_time FROM bookings WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking) {
        $booking_time = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
        $current_time = strtotime('now');
        $hours_diff = ($booking_time - $current_time) / 3600;

        if ($hours_diff < 24) {
            $error = 'Không thể hủy đặt sân vì còn dưới 24 giờ trước giờ bắt đầu.';
        } else {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$booking_id]);
            $success = 'Đã hủy đặt sân thành công!';
            header('Location: history.php');
            exit;
        }
    } else {
        $error = 'Không tìm thấy đặt sân hoặc đặt sân không thể hủy.';
    }
}
?>

<section class="history py-5">
    <div class="container">
        <h2 class="text-center mb-4">Lịch Sử Đặt Sân</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <p class="text-center">Bạn chưa có đặt sân nào.</p>
        <?php else: ?>
            <div class="row">
                <div class="col-md-10 offset-md-1">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Ngày đặt</th>
                                <th>Giờ bắt đầu</th>
                                <th>Giờ kết thúc</th>
                                <th>Tên sân</th>
                                <th>Tổng giá</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['booking_date']; ?></td>
                                    <td><?php echo $booking['start_time']; ?></td>
                                    <td><?php echo $booking['end_time']; ?></td>
                                    <td><?php echo $booking['field_name']; ?></td>
                                    <td><?php echo number_format($booking['total_price'], 0, ',', '.') . ' VND'; ?></td>
                                    <td><?php echo $booking['status']; ?></td>
                                    <td>
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">Hủy</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>