<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/email.php';
require_once 'includes/csrf.php';

$error = '';
$success = '';
$csrf_token = generateCsrfToken();
$step = isset($_POST['step']) ? $_POST['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if ($step == 1) {
            // Bước 1: Nhập email và gửi mã xác nhận
            $email = trim($_POST['email']);

            if (empty($email)) {
                $error = 'Vui lòng nhập email.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email không hợp lệ.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = 'Email không tồn tại.';
                } else {
                    // Tạo mã xác nhận 6 chữ số
                    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    
                    // Xóa các mã cũ của email này
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    // Lưu mã mới
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
                    $stmt->execute([$email, $verification_code]);

                    // Gửi email với mã xác nhận
                    $subject = "Đặt Lại Mật Khẩu";
                    $body = "<h2>Xin chào " . htmlspecialchars($user['full_name']) . ",</h2>
                             <p>Bạn đã yêu cầu đặt lại mật khẩu. Mã xác nhận của bạn là:</p>
                             <h1 style='font-size: 24px; color: #007bff; text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px;'>" . $verification_code . "</h1>
                             <p>Mã này sẽ hết hạn sau 1 giờ.</p>
                             <p>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>";
                             
                    if (sendEmail($email, $subject, $body)) {
                        $success = 'Một email với mã xác nhận đã được gửi đến ' . htmlspecialchars($email) . '.';
                        $step = 2; // Chuyển sang bước 2
                    } else {
                        $error = 'Không thể gửi email. Vui lòng thử lại sau.';
                    }
                }
            }
        } else if ($step == 2) {
            // Bước 2: Nhập mã xác nhận và mật khẩu mới
            $email = trim($_POST['email']);
            $verification_code = trim($_POST['verification_code']);
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);

            if (empty($email) || empty($verification_code) || empty($password) || empty($confirm_password)) {
                $error = 'Vui lòng điền đầy đủ thông tin.';
            } elseif ($password !== $confirm_password) {
                $error = 'Mật khẩu xác nhận không khớp.';
            } elseif (strlen($password) < 6) {
                $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
            } else {
                // Kiểm tra mã xác nhận và email
                $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ?");
                $stmt->execute([$email, $verification_code]);
                $reset = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$reset) {
                    $error = 'Mã xác nhận không đúng hoặc đã hết hạn.';
                } else {
                    // Kiểm tra thời gian token (hết hạn sau 1 giờ)
                    $created_at = strtotime($reset['created_at']);
                    $current_time = strtotime('now');
                    if (($current_time - $created_at) > 3600) {
                        $error = 'Mã xác nhận đã hết hạn.';
                    } else {
                        // Cập nhật mật khẩu mới
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $stmt->execute([$hashed_password, $email]);

                        // Xóa token đã sử dụng
                        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND token = ?");
                        $stmt->execute([$email, $verification_code]);

                        $success = 'Mật khẩu đã được đặt lại thành công! Bạn có thể <a href="login.php">đăng nhập</a> ngay bây giờ.';
                        $step = 3; // Chuyển sang bước 3 (hoàn thành)
                    }
                }
            }
        }
    }
}
?>

<section class="forgot-password py-5">
    <div class="container">
        <h2 class="text-center mb-4">Quên Mật Khẩu</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- Bước 1: Nhập email -->
            <form method="POST" class="row justify-content-center">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="step" value="1">
                    <button type="submit" class="btn btn-primary w-100">Gửi Mã Xác Nhận</button>
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">Quay lại đăng nhập</a>
                    </div>
                </div>
            </form>
        <?php elseif ($step == 2): ?>
            <!-- Bước 2: Nhập mã xác nhận và mật khẩu mới -->
            <form method="POST" class="row justify-content-center">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="verification_code" class="form-label">Mã xác nhận</label>
                        <input type="text" name="verification_code" id="verification_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu mới</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="step" value="2">
                    <button type="submit" class="btn btn-primary w-100">Đặt lại mật khẩu</button>
                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-decoration-none">Quay lại bước 1</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>