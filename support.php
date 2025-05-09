<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

session_start();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $content = trim($_POST['content']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

    if (empty($full_name) || empty($email) || empty($content)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO support_requests (user_id, full_name, email, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $full_name, $email, $content]);
        $success = 'Yêu cầu hỗ trợ đã được gửi thành công!';
    }
}

$faqs = [
    ['question' => 'Làm thế nào để đặt sân?', 'answer' => 'Bạn có thể tìm sân trên trang Tìm kiếm, chọn sân phù hợp, và gửi yêu cầu đặt sân.'],
    ['question' => 'Tôi có thể hủy đặt sân không?', 'answer' => 'Có, bạn có thể hủy đặt sân nếu còn trên 24 giờ trước giờ bắt đầu.'],
    ['question' => 'Làm sao để trở thành chủ sân?', 'answer' => 'Đăng ký tài khoản với loại "Chủ sân" và chờ admin phê duyệt.'],
    ['question' => 'Tôi quên mật khẩu thì phải làm sao?', 'answer' => 'Nhấn vào "Quên mật khẩu" trên trang đăng nhập để nhận hướng dẫn đặt lại.'],
    ['question' => 'Làm thế nào để gửi đánh giá sân?', 'answer' => 'Sau khi sử dụng sân, bạn có thể gửi đánh giá trên trang Đánh giá sân.'],
];
?>

<section class="support py-5">
    <div class="container">
        <h2 class="text-center mb-4">Hỗ Trợ Khách Hàng</h2>

        <!-- FAQ -->
        <h4 class="mb-3">Câu Hỏi Thường Gặp (FAQ)</h4>
        <div class="accordion mb-5" id="faqAccordion">
            <?php foreach ($faqs as $index => $faq): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                        <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                            <?php echo $faq['question']; ?>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <?php echo $faq['answer']; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Form gửi yêu cầu hỗ trợ -->
        <h4 class="mb-3">Gửi Yêu Cầu Hỗ Trợ</h4>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Họ tên</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo isset($_SESSION['user_id']) ? ($pdo->query("SELECT full_name FROM users WHERE id = " . $_SESSION['user_id'])->fetchColumn()) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($_SESSION['user_id']) ? ($pdo->query("SELECT email FROM users WHERE id = " . $_SESSION['user_id'])->fetchColumn()) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="content" class="form-label">Nội dung yêu cầu</label>
                    <textarea name="content" id="content" class="form-control" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">Gửi yêu cầu</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>