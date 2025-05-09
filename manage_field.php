<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['status'] !== 'approved') {
    header('Location: profile.php');
    exit;
}

$error = '';
$success = '';

// Lấy danh sách sân của chủ sân
$stmt = $pdo->prepare("SELECT * FROM fields WHERE owner_id = ?");
$stmt->execute([$user_id]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách yêu cầu đặt sân
$stmt = $pdo->prepare("SELECT b.*, f.name AS field_name, u.full_name AS customer_name 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       JOIN users u ON b.user_id = u.id 
                       WHERE f.owner_id = ? AND b.status = 'pending'");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy lịch đặt sân đã xác nhận
$stmt = $pdo->prepare("SELECT b.*, f.name AS field_name, u.full_name AS customer_name 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       JOIN users u ON b.user_id = u.id 
                       WHERE f.owner_id = ? AND b.status = 'confirmed'");
$stmt->execute([$user_id]);
$confirmed_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tính doanh thu
$total_revenue = 0;
$revenue_by_field = [];
$stmt = $pdo->prepare("SELECT f.id, f.name, SUM(b.total_price) as revenue 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       WHERE f.owner_id = ? AND b.status = 'confirmed' 
                       GROUP BY f.id, f.name");
$stmt->execute([$user_id]);
$revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($revenues as $rev) {
    $total_revenue += $rev['revenue'];
    $revenue_by_field[$rev['id']] = ['name' => $rev['name'], 'revenue' => $rev['revenue']];
}

// Xử lý thêm sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_field'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $price_per_hour = (float)$_POST['price_per_hour'];
    $open_time = $_POST['open_time'];
    $close_time = $_POST['close_time'];

    if (empty($name) || empty($address) || $price_per_hour <= 0 || empty($open_time) || empty($close_time)) {
        $error = 'Vui lòng điền đầy đủ thông tin sân.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO fields (owner_id, name, address, price_per_hour, open_time, close_time, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $name, $address, $price_per_hour, $open_time, $close_time]);
        $success = 'Thêm sân thành công! Đang chờ admin phê duyệt.';
        header('Location: manage_field.php');
        exit;
    }
}

// Xử lý chỉnh sửa sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_field'])) {
    $field_id = (int)$_POST['field_id'];
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $price_per_hour = (float)$_POST['price_per_hour'];
    $open_time = $_POST['open_time'];
    $close_time = $_POST['close_time'];

    if (empty($name) || empty($address) || $price_per_hour <= 0 || empty($open_time) || empty($close_time)) {
        $error = 'Vui lòng điền đầy đủ thông tin sân.';
    } else {
        $stmt = $pdo->prepare("UPDATE fields SET name = ?, address = ?, price_per_hour = ?, open_time = ?, close_time = ? WHERE id = ? AND owner_id = ?");
        $stmt->execute([$name, $address, $price_per_hour, $open_time, $close_time, $field_id, $user_id]);
        $success = 'Cập nhật sân thành công!';
        header('Location: manage_field.php');
        exit;
    }
}

// Xử lý xóa sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_field'])) {
    $field_id = (int)$_POST['field_id'];
    $stmt = $pdo->prepare("DELETE FROM fields WHERE id = ? AND owner_id = ?");
    $stmt->execute([$field_id, $user_id]);
    $success = 'Xóa sân thành công!';
    header('Location: manage_field.php');
    exit;
}

// Xử lý xác nhận/hủy đặt sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
    $stmt->execute([$booking_id]);
    $success = 'Xác nhận đặt sân thành công!';
    header('Location: manage_field.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);
    $success = 'Hủy đặt sân thành công!';
    header('Location: manage_field.php');
    exit;
}
?>

<section class="manage-field py-5">
    <div class="container">
        <h2 class="text-center mb-4">Quản Lý Sân Bóng</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Thêm sân -->
        <h4 class="mb-3">Thêm Sân Mới</h4>
        <form method="POST" class="row g-3 mb-5">
            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="Tên sân" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="address" class="form-control" placeholder="Địa chỉ" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="price_per_hour" class="form-control" placeholder="Giá/giờ (VND)" required>
            </div>
            <div class="col-md-2">
                <input type="time" name="open_time" class="form-control" required>
            </div>
            <div class="col-md-2">
                <input type="time" name="close_time" class="form-control" required>
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_field" class="btn btn-primary w-100">Thêm sân</button>
            </div>
        </form>

        <!-- Danh sách sân -->
        <h4 class="mb-3">Danh Sách Sân</h4>
        <?php if (empty($fields)): ?>
            <p>Chưa có sân bóng nào.</p>
        <?php else: ?>
            <div class="row">
                <div class="col-md-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Tên sân</th>
                                <th>Địa chỉ</th>
                                <th>Giá/giờ</th>
                                <th>Giờ mở cửa</th>
                                <th>Giờ đóng cửa</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><?php echo $field['name']; ?></td>
                                    <td><?php echo $field['address']; ?></td>
                                    <td><?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND'; ?></td>
                                    <td><?php echo $field['open_time']; ?></td>
                                    <td><?php echo $field['close_time']; ?></td>
                                    <td><?php echo $field['status']; ?></td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $field['id']; ?>">Sửa</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sân này?');">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <button type="submit" name="delete_field" class="btn btn-danger btn-sm">Xóa</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Modal chỉnh sửa sân -->
                                <div class="modal fade" id="editModal<?php echo $field['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $field['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel<?php echo $field['id']; ?>">Chỉnh Sửa Sân</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="name" class="form-label">Tên sân</label>
                                                        <input type="text" name="name" class="form-control" value="<?php echo $field['name']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="address" class="form-label">Địa chỉ</label>
                                                        <input type="text" name="address" class="form-control" value="<?php echo $field['address']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="price_per_hour" class="form-label">Giá/giờ (VND)</label>
                                                        <input type="number" name="price_per_hour" class="form-control" value="<?php echo $field['price_per_hour']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="open_time" class="form-label">Giờ mở cửa</label>
                                                        <input type="time" name="open_time" class="form-control" value="<?php echo $field['open_time']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="close_time" class="form-label">Giờ đóng cửa</label>
                                                        <input type="time" name="close_time" class="form-control" value="<?php echo $field['close_time']; ?>" required>
                                                    </div>
                                                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                    <button type="submit" name="edit_field" class="btn btn-primary">Lưu thay đổi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Yêu cầu đặt sân -->
        <h4 class="mb-3 mt-5">Yêu Cầu Đặt Sân</h4>
        <?php if (empty($bookings)): ?>
            <p>Chưa có yêu cầu đặt sân nào.</p>
        <?php else: ?>
            <div class="row">
                <div class="col-md-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Khách hàng</th>
                                <th>Sân</th>
                                <th>Ngày đặt</th>
                                <th>Giờ bắt đầu</th>
                                <th>Giờ kết thúc</th>
                                <th>Tổng giá</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['customer_name']; ?></td>
                                    <td><?php echo $booking['field_name']; ?></td>
                                    <td><?php echo $booking['booking_date']; ?></td>
                                    <td><?php echo $booking['start_time']; ?></td>
                                    <td><?php echo $booking['end_time']; ?></td>
                                    <td><?php echo number_format($booking['total_price'], 0, ',', '.') . ' VND'; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" name="confirm_booking" class="btn btn-success btn-sm">Xác nhận</button>
                                            <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">Hủy</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Lịch đặt sân đã xác nhận -->
        <h4 class="mb-3 mt-5">Lịch Đặt Sân Đã Xác Nhận</h4>
        <?php if (empty($confirmed_bookings)): ?>
            <p>Chưa có đặt sân nào được xác nhận.</p>
        <?php else: ?>
            <div class="row">
                <div class="col-md-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Khách hàng</th>
                                <th>Sân</th>
                                <th>Ngày đặt</th>
                                <th>Giờ bắt đầu</th>
                                <th>Giờ kết thúc</th>
                                <th>Tổng giá</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($confirmed_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['customer_name']; ?></td>
                                    <td><?php echo $booking['field_name']; ?></td>
                                    <td><?php echo $booking['booking_date']; ?></td>
                                    <td><?php echo $booking['start_time']; ?></td>
                                    <td><?php echo $booking['end_time']; ?></td>
                                    <td><?php echo number_format($booking['total_price'], 0, ',', '.') . ' VND'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Doanh thu -->
        <h4 class="mb-3 mt-5">Doanh Thu</h4>
        <div class="row">
            <div class="col-md-12">
                <p><strong>Tổng doanh thu:</strong> <?php echo number_format($total_revenue, 0, ',', '.') . ' VND'; ?></p>
                <h5>Doanh thu theo sân:</h5>
                <?php if (empty($revenue_by_field)): ?>
                    <p>Chưa có doanh thu nào.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($revenue_by_field as $rev): ?>
                            <li><?php echo $rev['name'] . ': ' . number_format($rev['revenue'], 0, ',', '.') . ' VND'; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>