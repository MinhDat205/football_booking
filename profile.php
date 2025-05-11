<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Lấy thông tin người dùng
$stmt = $pdo->prepare("SELECT full_name, email, phone, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $user['full_name'];
$email = $user['email'];
$phone = $user['phone'];
$status = $user['status'];

// Xử lý cập nhật thông tin trước khi gửi output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $error = '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];

        if (empty($full_name) || empty($phone)) {
            $error = 'Vui lòng điền đầy đủ họ tên và số điện thoại.';
        } elseif (strlen($full_name) > 255) {
            $error = 'Họ tên không được vượt quá 255 ký tự.';
        } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
            $error = 'Số điện thoại không hợp lệ.';
        } else {
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, password = ? WHERE id = ?");
                    $stmt->execute([$full_name, $phone, $hashed_password, $user_id]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $phone, $user_id]);
            }

            if (!$error) {
                $success = 'Cập nhật thông tin thành công!';
                header('Location: profile.php');
                exit;
            }
        }
    }
}

// Chỉ bao gồm header.php sau khi xử lý logic
require_once 'includes/header.php';
?>

<style>
    /* Tiêu đề trang */
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.5rem; /* Đồng bộ với search.php */
        margin-bottom: 1.5rem; /* Đồng bộ với search.php */
        text-align: center;
    }
    /* Form profile */
    .profile-form {
        max-width: 500px;
        margin: 0 auto;
        background-color: #fff;
        padding: 20px;
        border-radius: 5px;
        border: 1px solid #e0e4e9;
    }
    .form-control {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 1rem; /* Đồng bộ với search.php */
    }
    .form-control:focus {
        border-color: #2a5298;
    }
    .input-group-text {
        border-radius: 5px 0 0 5px;
    }
    .btn-primary {
        background-color: #28a745; /* Đồng bộ với search.php */
        border: none;
        padding: 8px 20px; /* Đồng bộ với search.php */
        font-size: 1rem; /* Đồng bộ với search.php */
        width: 100%;
    }
    .btn-primary:hover {
        background-color: #218838;
    }
    .status-badge {
        font-size: 0.95rem;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1.2rem;
        }
        .profile-form {
            padding: 15px;
        }
        .form-control {
            font-size: 0.9rem;
        }
        .btn-primary {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
    }
</style>

<section class="profile py-5">
    <div class="container">
        <h2 class="section-title">Hồ Sơ Cá Nhân</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); // Xóa biến $success sau khi hiển thị ?>
        <?php endif; ?>
        <div class="profile-form">
            <form method="POST">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Họ và tên</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu mới (để trống nếu không muốn thay đổi)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="password" id="password" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Trạng thái tài khoản</label>
                    <div>
                        <span class="badge <?php echo $status === 'pending' ? 'bg-warning' : ($status === 'approved' ? 'bg-success' : 'bg-danger'); ?> status-badge">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" name="update_profile" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-save-fill"></i> Lưu thay đổi
                </button>
            </form>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>