<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/email.php';
require_once 'includes/csrf.php';

$error = '';
$success = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
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
                // Tạo token đặt lại mật khẩu
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
                $stmt->execute([$email, $token]);

                // Gửi email với liên kết đặt lại mật khẩu
                $reset_link = "http://localhost/football_booking/reset_password.php?token=" . $token;
                $subject = "Đặt Lại Mật Khẩu";
                $body = "<h2>Xin chào " . htmlspecialchars($user['full_name']) . ",</h2>
                         <p>Bạn đã yêu cầu đặt lại mật khẩu. Nhấn vào liên kết sau để đặt lại:</p>
                         <p><a href='$reset_link'>Đặt lại mật khẩu</a></p>
                         <p>Liên kết này sẽ hết hạn sau 1 giờ.</p>";
                if (sendEmail($email, $subject, $body)) {
                    $success = 'Một email với liên kết đặt lại mật khẩu đã được gửi đến ' . htmlspecialchars($email) . '.';
                } else {
                    $error = 'Không thể gửi email. Vui lòng thử lại sau.';
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
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="btn btn-primary w-100">Gửi Yêu Cầu</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>