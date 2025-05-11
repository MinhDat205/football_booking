<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/csrf.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$csrf_token = generateCsrfToken();

if (empty($token)) {
    $error = 'Liên kết không hợp lệ.';
} else {
    // Kiểm tra token
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $error = 'Token không hợp lệ hoặc đã hết hạn.';
    } else {
        // Kiểm tra thời gian token (hết hạn sau 1 giờ)
        $created_at = strtotime($reset['created_at']);
        $current_time = strtotime('now');
        if (($current_time - $created_at) > 3600) {
            $error = 'Token đã hết hạn.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf_token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

            if (!verifyCsrfToken($csrf_token_post)) {
                $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
            } else {
                $password = trim($_POST['password']);
                $confirm_password = trim($_POST['confirm_password']);

                if (empty($password) || empty($confirm_password)) {
                    $error = 'Vui lòng điền đầy đủ mật khẩu.';
                } elseif ($password !== $confirm_password) {
                    $error = 'Mật khẩu xác nhận không khớp.';
                } elseif (strlen($password) < 6) {
                    $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
                } else {
                    // Cập nhật mật khẩu mới
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->execute([$hashed_password, $reset['email']]);

                    // Xóa token đã sử dụng
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->execute([$token]);

                    $success = 'Mật khẩu đã được đặt lại thành công! Bạn có thể <a href="login.php">đăng nhập</a> ngay bây giờ.';
                }
            }
        }
    }
}
?>

<section class="reset-password py-5">
    <div class="container">
        <h2 class="text-center mb-4">Đặt Lại Mật Khẩu</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST" class="row justify-content-center">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu mới</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="btn btn-primary w-100">Đặt lại mật khẩu</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>