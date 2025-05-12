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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $users = $pdo->prepare("SELECT * FROM users WHERE account_type IN ('customer', 'owner') AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY created_at DESC");
    $like = "%$search%";
    $users->execute([$like, $like, $like]);
} else {
    $users = $pdo->prepare("SELECT * FROM users WHERE account_type IN ('customer', 'owner') ORDER BY created_at DESC");
    $users->execute();
}
$users_list = $users->fetchAll(PDO::FETCH_ASSOC);

// Xử lý hành động của admin: duyệt hoặc từ chối tài khoản người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        // Duyệt hoặc từ chối tài khoản
        if (isset($_POST['approve_user']) || isset($_POST['reject_user'])) {
            $user_id_to_update = (int)$_POST['user_id'];
            $new_status = isset($_POST['approve_user']) ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id_to_update]);
            $success = "Cập nhật trạng thái tài khoản thành công!";
            header('Location: admin_users.php');
            exit;
        }
        // Thêm người dùng mới
        if (isset($_POST['add_user'])) {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $account_type = $_POST['account_type'];
            $status = $_POST['status'];
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, account_type, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $phone, $password, $account_type, $status]);
            $success = "Thêm người dùng thành công!";
            header('Location: admin_users.php');
            exit;
        }
        // Sửa thông tin người dùng
        if (isset($_POST['edit_user'])) {
            $edit_id = (int)$_POST['edit_id'];
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $account_type = $_POST['account_type'];
            $status = $_POST['status'];
            $update_query = "UPDATE users SET full_name=?, email=?, phone=?, account_type=?, status=?";
            $params = [$full_name, $email, $phone, $account_type, $status];
            if (!empty($_POST['password'])) {
                $update_query .= ", password=?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $update_query .= " WHERE id=?";
            $params[] = $edit_id;
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($params);
            $success = "Cập nhật thông tin người dùng thành công!";
            header('Location: admin_users.php');
            exit;
        }
        // Xóa người dùng
        if (isset($_POST['delete_user'])) {
            $delete_id = (int)$_POST['delete_id'];
            // Xóa các conversation liên quan trước
            $stmt = $pdo->prepare("DELETE FROM conversations WHERE owner_id = ?");
            $stmt->execute([$delete_id]);
            // Sau đó mới xóa user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "Xóa người dùng thành công!";
            header('Location: admin_users.php');
            exit;
        }
        // Khóa/Mở khóa tài khoản
        if (isset($_POST['toggle_status'])) {
            $toggle_id = (int)$_POST['toggle_id'];
            $current_status = $_POST['current_status'];
            $new_status = $current_status === 'approved' ? 'rejected' : 'approved';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $toggle_id]);
            $success = "Đã cập nhật trạng thái tài khoản!";
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

        <!-- Form tìm kiếm -->
        <form method="GET" class="mb-3 d-flex" style="max-width:400px;margin:auto;">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm theo tên, email, SĐT..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </form>

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
                                    <button type="button" class="btn btn-warning btn-sm me-1" onclick='showEditModal(<?php echo json_encode($user); ?>)'>
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa người dùng này?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Xóa</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="toggle_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-secondary btn-sm">
                                            <i class="bi bi-lock"></i> <?php echo $user['status'] === 'approved' ? 'Khóa' : 'Mở khóa'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Nút Thêm người dùng -->
        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-circle"></i> Thêm người dùng</button>
        <!-- Modal Thêm người dùng -->
        <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <form method="POST" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Thêm người dùng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                  <label class="form-label">Họ tên</label>
                  <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Số điện thoại</label>
                  <input type="text" name="phone" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Mật khẩu</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Loại tài khoản</label>
                  <select name="account_type" class="form-select" required>
                    <option value="customer">Khách hàng</option>
                    <option value="owner">Chủ sân</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select" required>
                    <option value="approved">Đã duyệt</option>
                    <option value="pending">Chờ duyệt</option>
                    <option value="rejected">Bị khóa</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" name="add_user" class="btn btn-success">Thêm</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Sửa người dùng (dùng JS để fill dữ liệu) -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <form method="POST" class="modal-content" id="editUserForm">
              <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Sửa thông tin người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="mb-3">
                  <label class="form-label">Họ tên</label>
                  <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Số điện thoại</label>
                  <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Mật khẩu mới (bỏ qua nếu không đổi)</label>
                  <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Loại tài khoản</label>
                  <select name="account_type" id="edit_account_type" class="form-select" required>
                    <option value="customer">Khách hàng</option>
                    <option value="owner">Chủ sân</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" id="edit_status" class="form-select" required>
                    <option value="approved">Đã duyệt</option>
                    <option value="pending">Chờ duyệt</option>
                    <option value="rejected">Bị khóa</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" name="edit_user" class="btn btn-primary">Lưu thay đổi</button>
              </div>
            </form>
          </div>
        </div>
    </div>
</section>

<script>
// Hiển thị modal sửa và fill dữ liệu
function showEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_account_type').value = user.account_type;
    document.getElementById('edit_status').value = user.status;
    var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>