<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $user_id]);
    header('Location: profile.php');
    exit;
}

$bookings = [];
if ($user['account_type'] === 'customer') {
    $stmt = $pdo->prepare("SELECT b.*, f.name AS field_name FROM bookings b JOIN fields f ON b.field_id = f.id WHERE b.user_id = ?");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<section class="profile py-5">
    <div class="container">
        <h2 class="text-center mb-4">Hồ Sơ Cá Nhân</h2>
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Thông Tin Cá Nhân</h5>
                        <p><strong>Họ tên:</strong> <?php echo $user['full_name']; ?></p>
                        <p><strong>Email:</strong> <?php echo $user['email']; ?></p>
                        <p><strong>Số điện thoại:</strong> <?php echo $user['phone']; ?></p>
                        <?php if ($user['account_type'] === 'owner'): ?>
                            <p><strong>Trạng thái tài khoản:</strong> <?php echo $user['status'] === 'pending' ? 'Đang chờ xác nhận' : 'Đã xác nhận'; ?></p>
                            <?php if ($user['status'] === 'approved'): ?>
                                <a href="manage_field.php" class="btn btn-primary">Quản lý sân</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal">Chỉnh sửa</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user['account_type'] === 'customer' && !empty($bookings)): ?>
            <h3 class="text-center my-4">Lịch Sử Đặt Sân</h3>
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Giờ bắt đầu</th>
                                <th>Giờ kết thúc</th>
                                <th>Tên sân</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['booking_date']; ?></td>
                                    <td><?php echo $booking['start_time']; ?></td>
                                    <td><?php echo $booking['end_time']; ?></td>
                                    <td><?php echo $booking['field_name']; ?></td>
                                    <td><?php echo $booking['status']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal chỉnh sửa thông tin -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Chỉnh Sửa Thông Tin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Họ tên</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo $user['full_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?php echo $user['phone']; ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>