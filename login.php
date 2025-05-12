<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    // Chuyển hướng dựa trên loại tài khoản
    if ($_SESSION['account_type'] === 'owner') {
        header('Location: history.php');
    } elseif ($_SESSION['account_type'] === 'admin') {
        header('Location: admin_users.php');
    } else {
        header('Location: search.php');
    }
    exit;
}

$email = '';
$password = '';
$error = '';
$csrf_token = generateCsrfToken();

// Lấy field_id từ query parameter nếu có
$field_id = isset($_GET['field_id']) ? (int)$_GET['field_id'] : null;

// Xử lý đăng nhập trước khi gửi bất kỳ output nào
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = 'Vui lòng điền đầy đủ email và mật khẩu.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'rejected') {
                    $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên để biết thêm chi tiết.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['account_type'] = $user['account_type'];

                    // Chuyển hướng dựa trên loại tài khoản
                    $redirect_url = '';
                    if ($user['account_type'] === 'owner') {
                        $redirect_url = 'history.php';
                    } elseif ($user['account_type'] === 'admin') {
                        $redirect_url = 'admin_users.php';
                    } else {
                        $redirect_url = 'search.php';
                        if ($field_id) {
                            $redirect_url .= '?field_id=' . $field_id;
                        }
                    }
                    header('Location: ' . $redirect_url);
                    exit;
                }
            } else {
                $error = 'Email hoặc mật khẩu không đúng.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .login-section {
        max-width: 400px;
        margin: 0 auto;
        padding: 20px;
        background-color: #fff;
        border-radius: 5px;
        border: 1px solid #e0e4e9;
    }
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    .form-control {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 1rem;
    }
    .form-control:focus {
        border-color: #2a5298;
    }
    .input-group-text {
        border-radius: 5px 0 0 5px;
    }
    .btn-primary {
        background-color: #28a745;
        border: none;
        padding: 8px 20px;
        font-size: 1rem;
        width: 100%;
    }
    .btn-primary:hover {
        background-color: #218838;
    }
    .register-link {
        text-align: center;
        margin-top: 1rem;
    }
    .register-link a {
        color: #2a5298;
        text-decoration: none;
    }
    .register-link a:hover {
        text-decoration: underline;
    }
    @media (max-width: 768px) {
        .login-section {
            padding: 15px;
        }
        .section-title {
            font-size: 1.2rem;
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

<section class="login py-5">
    <div class="container">
        <div class="login-section">
            <h2 class="section-title">Đăng Nhập</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                </button>
            </form>
            <div class="register-link">
                <p><a href="forgot_password.php">Quên mật khẩu?</a></p> 
                <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
            </div>
        </div>
    </div>
</section>

