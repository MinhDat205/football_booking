<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $account_type = $_POST['account_type'];

    if (empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!in_array($account_type, ['customer', 'owner'])) {
        $error = 'Loại tài khoản không hợp lệ.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email đã được sử dụng.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $status = ($account_type === 'owner') ? 'pending' : 'approved';

            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, account_type, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $phone, $hashed_password, $account_type, $status]);
            $user_id = $pdo->lastInsertId();

            if ($account_type === 'owner') {
                $field_name = trim($_POST['field_name']);
                $field_address = trim($_POST['field_address']);
                $field_price = trim($_POST['field_price']);

                if (empty($field_name) || empty($field_address) || empty($field_price)) {
                    $error = 'Vui lòng điền đầy đủ thông tin sân.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO fields (owner_id, name, address, price_per_hour, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $field_name, $field_address, $field_price]);
                    $success = 'Đăng ký thành công! Tài khoản của bạn đang chờ admin xác nhận.';
                }
            } else {
                $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
            }
        }
    }
}
?>

<section class="register py-5">
    <div class="container">
        <h2 class="text-center mb-4">Đăng Ký</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Họ tên</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" id="phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Loại tài khoản</label><br>
                    <input type="radio" name="account_type" value="customer" id="customer" required>
                    <label for="customer">Khách hàng</label>
                    <input type="radio" name="account_type" value="owner" id="owner" class="ms-3">
                    <label for="owner">Chủ sân</label>
                </div>
                <div id="owner_fields" style="display: none;">
                    <div class="mb-3">
                        <label for="field_name" class="form-label">Tên sân</label>
                        <input type="text" name="field_name" id="field_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="field_address" class="form-label">Địa chỉ sân</label>
                        <input type="text" name="field_address" id="field_address" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="field_price" class="form-label">Giá cơ bản/giờ (VND)</label>
                        <input type="number" name="field_price" id="field_price" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Đăng ký</button>
            </div>
        </form>
    </div>
</section>

<script>
document.querySelectorAll('input[name="account_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('owner_fields').style.display = this.value === 'owner' ? 'block' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>