<?php
require_once 'includes/config.php';

// Xử lý logic đăng ký trước khi xuất bất kỳ dữ liệu nào
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $account_type = $_POST['account_type'];

    if (empty($full_name) || empty($email) || empty($password) || empty($account_type)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif (!in_array($account_type, ['customer', 'owner'])) {
        $error = 'Loại tài khoản không hợp lệ.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $error = 'Email đã được sử dụng.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $status = $account_type === 'owner' ? 'pending' : 'approved';
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, account_type, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $hashed_password, $account_type, $status]);
            $success = 'Đăng ký thành công! Vui lòng đăng nhập.';
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - Football Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .register-container {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
        .card {
            border: 1px solid #e0e4e9;
            border-radius: 5px;
            background-color: #fff;
        }
        .card-body {
            padding: 20px;
        }
        .btn-primary {
            background-color: #2a5298;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        .btn-primary:hover {
            background-color: #1e3c72;
        }
        h2 {
            font-weight: 600;
            color: #1e3c72;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 500;
            color: #444;
            font-size: 0.9rem;
        }
        .input-group input,
        .input-group select {
            border-radius: 5px;
            border: 1px solid #e0e4e9;
        }
        .input-group input:focus,
        .input-group select:focus {
            border-color: #2a5298;
            outline: none;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e4e9;
            border-right: none;
            border-radius: 5px 0 0 5px;
        }
        .alert {
            border-radius: 5px;
        }
        .login-link {
            color: #2a5298;
            font-weight: 500;
        }
        .login-link:hover {
            color: #1e3c72;
        }
        /* Responsive */
        @media (max-width: 576px) {
            .register-container {
                padding: 10px;
            }
            .card-body {
                padding: 15px;
            }
            h2 {
                font-size: 1.2rem;
            }
            .btn-primary {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="card">
            <div class="card-body">
                <h2 class="text-center mb-4">Đăng Ký</h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Họ tên</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="full_name" id="full_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="account_type" class="form-label">Loại tài khoản</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-lines-fill"></i></span>
                            <select name="account_type" id="account_type" class="form-select" required>
                                <option value="">Chọn loại tài khoản</option>
                                <option value="customer">Khách hàng</option>
                                <option value="owner">Chủ sân</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-person-plus-fill"></i> Đăng ký
                    </button>
                </form>
                <p class="text-center mt-3">
                    Đã có tài khoản? <a href="login.php" class="login-link">Đăng nhập ngay</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
<?php require_once 'includes/footer.php'; ?>