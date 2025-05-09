<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

try {
    // Lấy danh sách người dùng
    $users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách sân bóng
    $fields = $pdo->query("SELECT f.*, u.full_name AS owner_name FROM fields f JOIN users u ON f.owner_id = u.id")->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách yêu cầu hỗ trợ
    $support_requests = $pdo->query("SELECT sr.*, u.full_name AS user_name FROM support_requests sr LEFT JOIN users u ON sr.user_id = u.id")->fetchAll(PDO::FETCH_ASSOC);

    // Thống kê
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_owners_pending = $pdo->query("SELECT COUNT(*) FROM users WHERE account_type = 'owner' AND status = 'pending'")->fetchColumn();
    $total_fields = $pdo->query("SELECT COUNT(*) FROM fields WHERE status = 'approved'")->fetchColumn();
    $total_fields_pending = $pdo->query("SELECT COUNT(*) FROM fields WHERE status = 'pending'")->fetchColumn();
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();

    // Xử lý phê duyệt/từ chối tài khoản chủ sân
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_owner'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND account_type = 'owner'");
        $stmt->execute([$user_id]);
        $success = 'Phê duyệt tài khoản chủ sân thành công!';
        header('Location: admin.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_owner'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND account_type = 'owner'");
        $stmt->execute([$user_id]);
        $success = 'Từ chối tài khoản chủ sân thành công!';
        header('Location: admin.php');
        exit;
    }

    // Xử lý khóa/mở khóa tài khoản
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_status'])) {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['current_status'] === 'approved' ? 'rejected' : 'approved';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        $success = $new_status === 'approved' ? 'Mở khóa tài khoản thành công!' : 'Khóa tài khoản thành công!';
        header('Location: admin.php');
        exit;
    }

    // Xử lý phê duyệt/từ chối sân bóng
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_field'])) {
        $field_id = (int)$_POST['field_id'];
        $stmt = $pdo->prepare("UPDATE fields SET status = 'approved' WHERE id = ?");
        $stmt->execute([$field_id]);
        $success = 'Phê duyệt sân bóng thành công!';
        header('Location: admin.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_field'])) {
        $field_id = (int)$_POST['field_id'];
        $stmt = $pdo->prepare("UPDATE fields SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$field_id]);
        $success = 'Từ chối sân bóng thành công!';
        header('Location: admin.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_field'])) {
        $field_id = (int)$_POST['field_id'];
        $stmt = $pdo->prepare("DELETE FROM fields WHERE id = ?");
        $stmt->execute([$field_id]);
        $success = 'Xóa sân bóng thành công!';
        header('Location: admin.php');
        exit;
    }

    // Xử lý phản hồi yêu cầu hỗ trợ
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_request'])) {
        $request_id = (int)$_POST['request_id'];
        $stmt = $pdo->prepare("UPDATE support_requests SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$request_id]);
        $success = 'Đã đánh dấu yêu cầu hỗ trợ là đã xử lý!';
        header('Location: admin.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
}
?>

<section class="admin py-5">
    <div class="container">
        <h2 class="text-center mb-4">Quản Lý Admin</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Thống kê -->
        <h4 class="mb-3">Thống Kê Cơ Bản</h4>
        <div class="row mb-5">
            <div class="col-md-12">
                <p><strong>Tổng số tài khoản:</strong> <?php echo $total_users; ?></p>
                <p><strong>Chủ sân chờ xác nhận:</strong> <?php echo $total_owners_pending; ?></p>
                <p><strong>Tổng số sân bóng (đã phê duyệt):</strong> <?php echo $total_fields; ?></p>
                <p><strong>Sân bóng chờ phê duyệt:</strong> <?php echo $total_fields_pending; ?></p>
                <p><strong>Tổng số lượt đặt sân (tháng này):</strong> <?php echo $total_bookings; ?></p>
            </div>
        </div>

        <!-- Quản lý tài khoản -->
        <h4 class="mb-3">Quản Lý Tài Khoản</h4>
        <div class="row mb-5">
            <div class="col-md-12">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Loại tài khoản</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['account_type']; ?></td>
                                <td><?php echo $user['status']; ?></td>
                                <td>
                                    <?php if ($user['account_type'] === 'owner' && $user['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="approve_owner" class="btn btn-success btn-sm">Phê duyệt</button>
                                            <button type="submit" name="reject_owner" class="btn btn-danger btn-sm">Từ chối</button>
                                        </form>
                                    <?php elseif ($user['account_type'] !== 'admin'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                            <button type="submit" name="toggle_user_status" class="btn btn-<?php echo $user['status'] === 'approved' ? 'danger' : 'success'; ?> btn-sm">
                                                <?php echo $user['status'] === 'approved' ? 'Khóa' : 'Mở khóa'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quản lý sân bóng -->
        <h4 class="mb-3">Quản Lý Sân Bóng</h4>
        <div class="row mb-5">
            <div class="col-md-12">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tên sân</th>
                            <th>Địa chỉ</th>
                            <th>Chủ sân</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($field['name']); ?></td>
                                <td><?php echo htmlspecialchars($field['address']); ?></td>
                                <td><?php echo htmlspecialchars($field['owner_name']); ?></td>
                                <td><?php echo $field['status']; ?></td>
                                <td>
                                    <?php if ($field['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <button type="submit" name="approve_field" class="btn btn-success btn-sm">Phê duyệt</button>
                                            <button type="submit" name="reject_field" class="btn btn-danger btn-sm">Từ chối</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sân này?');">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <button type="submit" name="delete_field" class="btn btn-danger btn-sm">Xóa</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quản lý yêu cầu hỗ trợ -->
        <h4 class="mb-3">Quản Lý Yêu Cầu Hỗ Trợ</h4>
        <div class="row">
            <div class="col-md-12">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Người gửi</th>
                            <th>Email</th>
                            <th>Nội dung</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($support_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['full_name'] ?: $request['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td><?php echo htmlspecialchars($request['content']); ?></td>
                                <td><?php echo $request['status']; ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="resolve_request" class="btn btn-success btn-sm">Đã xử lý</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>