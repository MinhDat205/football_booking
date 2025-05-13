<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Xử lý tìm kiếm
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$query = "SELECT * FROM users WHERE account_type IN ('customer', 'owner')";
$params = [];
if ($search !== '') {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_type !== '') {
    $query .= " AND account_type = ?";
    $params[] = $filter_type;
}
if ($filter_status !== '') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}
$query .= " ORDER BY created_at DESC";
$users = $pdo->prepare($query);
$users->execute($params);
$users_list = $users->fetchAll(PDO::FETCH_ASSOC);

// Tách users_list thành 2 mảng: customers và owners
$customers = array_filter($users_list, function($u) { return $u['account_type'] === 'customer'; });
$owners = array_filter($users_list, function($u) { return $u['account_type'] === 'owner'; });

// Lấy thông báo lỗi/thành công từ session (nếu có)
$error = '';
$success = '';
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Đảm bảo xử lý POST thêm/sửa user nằm trước khi render HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
        header('Location: admin_users.php');
        exit;
    }
    // Thêm user
    if (isset($_POST['add_user'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $account_type = $_POST['account_type'];
        $status = $_POST['status'];
        if (empty($full_name) || empty($email) || empty($password) || empty($account_type) || empty($status)) {
            $_SESSION['error_message'] = 'Vui lòng điền đầy đủ thông tin.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = 'Email không hợp lệ.';
        } elseif (strlen($password) < 6) {
            $_SESSION['error_message'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        } elseif (!in_array($account_type, ['customer', 'owner'])) {
            $_SESSION['error_message'] = 'Loại tài khoản không hợp lệ.';
        } elseif (!in_array($status, ['pending', 'approved', 'rejected'])) {
            $_SESSION['error_message'] = 'Trạng thái không hợp lệ.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['error_message'] = 'Email đã được sử dụng.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, account_type, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $email, $phone, $hashed_password, $account_type, $status]);
                $_SESSION['success_message'] = 'Thêm người dùng thành công!';
            }
        }
        header('Location: admin_users.php');
        exit;
    }
    // Sửa user
    if (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $account_type = $_POST['account_type'];
        $status = $_POST['status'];
        if (empty($full_name) || empty($email) || empty($account_type) || empty($status)) {
            $_SESSION['error_message'] = 'Vui lòng điền đầy đủ thông tin.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = 'Email không hợp lệ.';
        } elseif (!in_array($account_type, ['customer', 'owner'])) {
            $_SESSION['error_message'] = 'Loại tài khoản không hợp lệ.';
        } elseif (!in_array($status, ['pending', 'approved', 'rejected'])) {
            $_SESSION['error_message'] = 'Trạng thái không hợp lệ.';
        } else {
            // Kiểm tra email trùng với user khác
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['error_message'] = 'Email đã được sử dụng.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, account_type = ?, status = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $account_type, $status, $user_id]);
                $_SESSION['success_message'] = 'Cập nhật thông tin người dùng thành công!';
            }
        }
        header('Location: admin_users.php');
        exit;
    }
    // Xử lý hành động của admin: duyệt hoặc từ chối tài khoản người dùng
    if (isset($_POST['approve_user']) || isset($_POST['reject_user'])) {
        $user_id_to_update = (int)$_POST['user_id'];
        $new_status = isset($_POST['approve_user']) ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id_to_update]);
        $success = "Cập nhật trạng thái tài khoản thành công!";
        header('Location: admin_users.php');
        exit;
    }
    // Xóa user
    if (isset($_POST['delete_user'])) {
        $user_id_to_delete = (int)$_POST['user_id'];
        // Xóa mềm: cập nhật status = 'deleted'
        $stmt = $pdo->prepare("UPDATE users SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$user_id_to_delete]);
        $_SESSION['success_message'] = "Đã xóa (ẩn) tài khoản khỏi hệ thống. Tài khoản này sẽ không thể đăng nhập nữa.";
        header('Location: admin_users.php');
        exit;
    }
    // Khóa user
    if (isset($_POST['lock_user'])) {
        $user_id_to_lock = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$user_id_to_lock]);
        $success = "Khóa tài khoản thành công!";
        header('Location: admin_users.php');
        exit;
    }
    // Mở khóa user
    if (isset($_POST['unlock_user'])) {
        $user_id_to_unlock = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$user_id_to_unlock]);
        $success = "Mở khóa tài khoản thành công!";
        header('Location: admin_users.php');
        exit;
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
        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="search" class="form-control" placeholder="Tên hoặc email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Loại tài khoản</label>
                <select name="filter_type" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="customer" <?php if($filter_type==='customer') echo 'selected'; ?>>Khách hàng</option>
                    <option value="owner" <?php if($filter_type==='owner') echo 'selected'; ?>>Chủ sân</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Trạng thái</label>
                <select name="filter_status" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="pending" <?php if($filter_status==='pending') echo 'selected'; ?>>Chờ duyệt</option>
                    <option value="approved" <?php if($filter_status==='approved') echo 'selected'; ?>>Đã duyệt</option>
                    <option value="rejected" <?php if($filter_status==='rejected') echo 'selected'; ?>>Đã khóa</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Tìm kiếm</button>
            </div>
        </form>
        <!-- Nút thêm user (mở modal) -->
        <div class="mb-2 text-end">
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-circle"></i> Thêm người dùng</button>
        </div>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (empty($customers) && empty($owners)): ?>
            <p class="text-center text-muted">Không có người dùng nào.</p>
        <?php else: ?>
            <!-- Bảng khách hàng -->
            <h4 class="mt-4 mb-2">Danh sách khách hàng</h4>
            <div class="table-responsive mb-4">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] === 'pending' ? 'bg-warning' : ($user['status'] === 'approved' ? 'bg-success' : 'bg-danger'); ?> status-badge">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                <td class="d-flex flex-wrap gap-1">
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="approve_user" class="btn btn-primary btn-sm" title="Duyệt"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="reject_user" class="btn btn-danger btn-sm" title="Từ chối"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Nút sửa -->
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-edit-user" title="Sửa" data-user='<?php echo json_encode(["id"=>$user["id"],"full_name"=>$user["full_name"],"email"=>$user["email"],"phone"=>$user["phone"],"account_type"=>$user["account_type"],"status"=>$user["status"]]); ?>'><i class="bi bi-pencil-square"></i></button>
                                    <!-- Nút khóa/mở khóa -->
                                    <?php if ($user['status'] === 'approved'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Khóa tài khoản này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="lock_user" class="btn btn-outline-warning btn-sm" title="Khóa tài khoản"><i class="bi bi-lock"></i></button>
                                        </form>
                                    <?php elseif ($user['status'] === 'rejected'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mở khóa tài khoản này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="unlock_user" class="btn btn-outline-success btn-sm" title="Mở khóa tài khoản"><i class="bi bi-unlock"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Bảng chủ sân -->
            <h4 class="mt-4 mb-2">Danh sách chủ sân</h4>
            <div class="table-responsive mb-4">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($owners as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] === 'pending' ? 'bg-warning' : ($user['status'] === 'approved' ? 'bg-success' : 'bg-danger'); ?> status-badge">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                <td class="d-flex flex-wrap gap-1">
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="approve_user" class="btn btn-primary btn-sm" title="Duyệt"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="reject_user" class="btn btn-danger btn-sm" title="Từ chối"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Nút xem doanh thu -->
                                    <?php if ($user['status'] === 'approved'): ?>
                                        <a href="admin_revenue.php?owner_id=<?php echo $user['id']; ?>" class="btn btn-outline-success btn-sm" title="Xem doanh thu"><i class="bi bi-bar-chart"></i></a>
                                    <?php endif; ?>
                                    <!-- Nút sửa -->
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-edit-user" title="Sửa" data-user='<?php echo json_encode(["id"=>$user["id"],"full_name"=>$user["full_name"],"email"=>$user["email"],"phone"=>$user["phone"],"account_type"=>$user["account_type"],"status"=>$user["status"]]); ?>'><i class="bi bi-pencil-square"></i></button>
                                    <!-- Nút khóa/mở khóa -->
                                    <?php if ($user['status'] === 'approved'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Khóa tài khoản này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="lock_user" class="btn btn-outline-warning btn-sm" title="Khóa tài khoản"><i class="bi bi-lock"></i></button>
                                        </form>
                                    <?php elseif ($user['status'] === 'rejected'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mở khóa tài khoản này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="unlock_user" class="btn btn-outline-success btn-sm" title="Mở khóa tài khoản"><i class="bi bi-unlock"></i></button>
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

<!-- Modal thêm user -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="addUserModalLabel">Thêm người dùng mới</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
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
            <input type="password" name="password" class="form-control" required minlength="6">
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
              <option value="pending">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="rejected">Đã khóa</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
          <button type="submit" name="add_user" class="btn btn-primary">Thêm</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modal sửa user (dùng JS để fill dữ liệu) -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editUserModalLabel">Sửa thông tin người dùng</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
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
            <label class="form-label">Loại tài khoản</label>
            <select name="account_type" id="edit_account_type" class="form-select" required>
              <option value="customer">Khách hàng</option>
              <option value="owner">Chủ sân</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Trạng thái</label>
            <select name="status" id="edit_status" class="form-select" required>
              <option value="pending">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="rejected">Đã khóa</option>
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
<script>
// Xử lý nút sửa user
const editButtons = document.querySelectorAll('.btn-edit-user');
editButtons.forEach(btn => {
  btn.addEventListener('click', function() {
    const user = JSON.parse(this.dataset.user);
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_account_type').value = user.account_type;
    document.getElementById('edit_status').value = user.status;
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>