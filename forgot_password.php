<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/email.php';
require_once 'includes/csrf.php';

$error = '';
$success = '';
$show_reset_form = false;
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $email = trim($_POST['email']);
        if (isset($_POST['verification_code'])) {
            // Bước 2: Xác nhận mã và đổi mật khẩu
            $verification_code = trim($_POST['verification_code']);
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);
            if (empty($verification_code) || empty($password) || empty($confirm_password)) {
                $error = 'Vui lòng điền đầy đủ thông tin.';
                $show_reset_form = true;
            } elseif ($password !== $confirm_password) {
                $error = 'Mật khẩu xác nhận không khớp.';
                $show_reset_form = true;
            } elseif (strlen($password) < 6) {
                $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
                $show_reset_form = true;
            } else {
                $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ?");
                $stmt->execute([$email, $verification_code]);
                $reset = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$reset) {
                    $error = 'Mã xác nhận không đúng hoặc đã hết hạn.';
                    $show_reset_form = false;
                } else {
                    $created_at = strtotime($reset['created_at']);
                    $current_time = strtotime('now');
                    if (($current_time - $created_at) > 3600) {
                        $error = 'Mã xác nhận đã hết hạn.';
                        $show_reset_form = false;
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $stmt->execute([$hashed_password, $email]);
                        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                        $stmt->execute([$email]);
                        $success = 'Mật khẩu đã được đặt lại thành công! Bạn có thể <a href=\"login.php\">đăng nhập</a> ngay bây giờ.';
                    }
                }
            }
        } else {
            // Bước 1: Gửi mã xác nhận
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
                    $verification_code = sprintf("%06d", mt_rand(0, 999999));
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
                    $stmt->execute([$email, $verification_code]);
                    $subject = "Mã Xác Nhận Đặt Lại Mật Khẩu";
                    $body = "<h2>Xin chào " . htmlspecialchars($user['full_name']) . ",</h2>
                             <p>Bạn đã yêu cầu đặt lại mật khẩu. Mã xác nhận của bạn là: <strong>" . $verification_code . "</strong></p>
                             <p>Mã này sẽ hết hạn sau 1 giờ.</p>";
                    if (sendEmail($email, $subject, $body)) {
                        $success = 'Một email với mã xác nhận đã được gửi đến ' . htmlspecialchars($email) . '.';
                        $show_reset_form = true;
                    } else {
                        $error = 'Không thể gửi email. Vui lòng thử lại sau.';
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
        <?php if ($success && !$show_reset_form): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!$show_reset_form): ?>
        <!-- Bước 1: Nhập email -->
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="btn btn-primary w-100">Gửi mã xác nhận</button>
            </div>
        </form>
        <?php else: ?>
        <!-- Bước 2: Nhập mã xác nhận và mật khẩu mới -->
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
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
                <button type="submit" class="btn btn-primary w-100">Đặt lại mật khẩu</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>