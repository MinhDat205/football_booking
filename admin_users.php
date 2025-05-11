<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Lấy danh sách người dùng (chưa duyệt hoặc đã duyệt)
$users = $pdo->prepare("SELECT * FROM users WHERE account_type IN ('customer', 'owner') ORDER BY created_at DESC");
$users->execute();
$users_list = $users->fetchAll(PDO::FETCH_ASSOC);

// Xử lý hành động của admin: duyệt hoặc từ chối tài khoản người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if (isset($_POST['approve_user']) || isset($_POST['reject_user'])) {
            $user_id_to_update = (int)$_POST['user_id'];
            $new_status = isset($_POST['approve_user']) ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id_to_update]);
            $success = "Cập nhật trạng thái tài khoản thành công!";
            header('Location: admin_users.php');
            exit;
        }
    }
}

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
    .admin-table thead th {
        font-size: 1rem;
        padding: 12px;
        background: #2a5298;
        color: #fff;
    }
    .admin-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .admin-table td {
        padding: 12px;
        font-size: 0.95rem;
    }
    .admin-table .status-badge {
        font-size: 0.95rem;
    }
    .admin-table .btn {
        padding: 8px 20px;
        font-size: 1rem;
    }
    .admin-table .btn-primary {
        background-color: #28a745;
    }
    .admin-table .btn-primary:hover {
        background-color: #218838;
    }
    .admin-table .btn-danger {
        background-color: #e74c3c;
    }
    .admin-table .btn-danger:hover {
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
        .admin-table thead th,
        .admin-table td {
            padding: 10px;
            font-size: 0.9rem;
        }
        .admin-table .btn {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
    }
</style>

<section class="admin py-3">
    <div class="container">
        <h2 class="section-title">Quản Lý Người Dùng</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); // Xóa biến $success sau khi hiển thị ?>
        <?php endif; ?>

        <?php if (empty($users_list)): ?>
            <p class="text-center text-muted">Không có người dùng nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Loại tài khoản</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo htmlspecialchars($user['account_type']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] === 'pending' ? 'bg-warning' : ($user['status'] === 'approved' ? 'bg-success' : 'bg-danger'); ?> status-badge">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="approve_user" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-check-circle"></i> Duyệt
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="reject_user" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-x-circle"></i> Từ chối
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