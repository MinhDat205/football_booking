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

    // Thông tin sân cho chủ sân
    $field_name = isset($_POST['field_name']) ? trim($_POST['field_name']) : '';
    $field_address = isset($_POST['field_address']) ? trim($_POST['field_address']) : '';
    $field_price = isset($_POST['field_price']) ? (float)$_POST['field_price'] : 0;
    $field_open = isset($_POST['field_open']) ? $_POST['field_open'] : '';
    $field_close = isset($_POST['field_close']) ? $_POST['field_close'] : '';
    $field_type = isset($_POST['field_type']) ? $_POST['field_type'] : '';

    if (empty($full_name) || empty($email) || empty($password) || empty($account_type)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif (!in_array($account_type, ['customer', 'owner'])) {
        $error = 'Loại tài khoản không hợp lệ.';
    } elseif ($account_type === 'owner' && (empty($field_name) || empty($field_address) || $field_price <= 0 || empty($field_open) || empty($field_close) || empty($field_type))) {
        $error = 'Vui lòng nhập đầy đủ thông tin sân cho chủ sân.';
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
            $user_id = $pdo->lastInsertId();
            // Nếu là chủ sân, tạo luôn sân và ảnh
            if ($account_type === 'owner') {
                $stmt = $pdo->prepare("INSERT INTO fields (owner_id, name, address, price_per_hour, open_time, close_time, field_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $field_name, $field_address, $field_price, $field_open, $field_close, $field_type]);
                $field_id = $pdo->lastInsertId();
                // Xử lý upload ảnh
                if (isset($_FILES['field_images']) && !empty($_FILES['field_images']['name'][0])) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    foreach ($_FILES['field_images']['name'] as $key => $image_name) {
                        if ($_FILES['field_images']['error'][$key] === UPLOAD_ERR_OK) {
                            if (!in_array($_FILES['field_images']['type'][$key], $allowed_types)) {
                                $error = 'Chỉ hỗ trợ định dạng ảnh JPEG, PNG, GIF.';
                                break;
                            } elseif ($_FILES['field_images']['size'][$key] > $max_size) {
                                $error = 'Kích thước ảnh không được vượt quá 5MB.';
                                break;
                            } else {
                                $image_name = 'field_' . $field_id . '_' . time() . '_' . $key . '.' . pathinfo($_FILES['field_images']['name'][$key], PATHINFO_EXTENSION);
                                $upload_path = 'assets/img/' . $image_name;
                                if (move_uploaded_file($_FILES['field_images']['tmp_name'][$key], $upload_path)) {
                                    $stmt = $pdo->prepare("INSERT INTO field_images (field_id, image) VALUES (?, ?)");
                                    $stmt->execute([$field_id, $image_name]);
                                } else {
                                    $error = 'Không thể tải ảnh lên. Vui lòng thử lại.';
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            if (!$error) {
                $success = 'Đăng ký thành công! Vui lòng đăng nhập.';
                header('Location: login.php');
                exit;
            }
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
                <form method="POST" enctype="multipart/form-data">
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
                            <select name="account_type" id="account_type" class="form-select" required onchange="toggleFieldInfo()">
                                <option value="">Chọn loại tài khoản</option>
                                <option value="customer">Khách hàng</option>
                                <option value="owner">Chủ sân</option>
                            </select>
                        </div>
                    </div>
                    <!-- Thông tin sân cho chủ sân -->
                    <div id="field-info" style="display:none;">
                        <div class="mb-3">
                            <label for="field_name" class="form-label">Tên sân</label>
                            <input type="text" name="field_name" id="field_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="field_address" class="form-label">Địa chỉ sân</label>
                            <input type="text" name="field_address" id="field_address" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="field_price" class="form-label">Giá mỗi giờ (VND)</label>
                            <input type="number" name="field_price" id="field_price" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="field_open" class="form-label">Giờ mở cửa</label>
                            <input type="time" name="field_open" id="field_open" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="field_close" class="form-label">Giờ đóng cửa</label>
                            <input type="time" name="field_close" id="field_close" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="field_type" class="form-label">Loại sân</label>
                            <select name="field_type" id="field_type" class="form-select">
                                <option value="">Chọn loại sân</option>
                                <option value="5">Sân 5 người</option>
                                <option value="7">Sân 7 người</option>
                                <option value="9">Sân 9 người</option>
                                <option value="11">Sân 11 người</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="field_images" class="form-label">Ảnh sân (JPEG, PNG, GIF, tối đa 5MB/ảnh, có thể chọn nhiều ảnh)</label>
                            <input type="file" name="field_images[]" id="field_images" class="form-control" multiple accept="image/*">
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
    <script>
    function toggleFieldInfo() {
        var type = document.getElementById('account_type').value;
        document.getElementById('field-info').style.display = (type === 'owner') ? 'block' : 'none';
    }
    </script>
</body>
</html>
<?php require_once 'includes/footer.php'; ?>