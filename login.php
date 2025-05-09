<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ email và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['account_type'] = $user['account_type'];
            header('Location: profile.php');
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng.';
            // Debug để kiểm tra chi tiết
            if ($user) {
                echo "Email tìm thấy: " . $email . "<br>";
                echo "Mật khẩu nhập vào: " . $password . "<br>";
                echo "Mật khẩu lưu trong cơ sở dữ liệu: " . $user['password'] . "<br>";
                echo "Kết quả kiểm tra mật khẩu: " . (password_verify($password, $user['password']) ? 'Đúng' : 'Sai') . "<br>";
                echo "Loại tài khoản: " . $user['account_type'] . "<br>";
            } else {
                echo "Email không tồn tại: " . $email . "<br>";
            }
        }
    }
}
?>

<section class="login py-5">
    <div class="container">
        <h2 class="text-center mb-4">Đăng Nhập</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <a href="#" class="text-primary">Quên mật khẩu?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>