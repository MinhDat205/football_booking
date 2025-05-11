<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$field_id = isset($_GET['field_id']) ? (int)$_GET['field_id'] : 0;
$error = '';
$success = '';
$csrf_token = generateCsrfToken();

if ($field_id === 0) {
    header('Location: search.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM fields WHERE id = ? AND status = 'approved'");
$stmt->execute([$field_id]);
$field = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$field) {
    header('Location: search.php');
    exit;
}

$stmt = $pdo->prepare("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.field_id = ?");
$stmt->execute([$field_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif ($_SESSION['account_type'] !== 'customer') {
        $error = 'Chỉ khách hàng mới có thể gửi đánh giá.';
    } else {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);

        if ($rating < 1 || $rating > 5) {
            $error = 'Điểm đánh giá phải từ 1 đến 5 sao.';
        } elseif (strlen($comment) > 200) {
            $error = 'Bình luận không được vượt quá 200 ký tự.';
        } elseif (empty($comment)) {
            $error = 'Vui lòng nhập bình luận.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, field_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $field_id, $rating, $comment]);
            $success = 'Đánh giá của bạn đã được gửi!';
            header('Location: review.php?field_id=' . $field_id);
            exit;
        }
    }
}
?>

<section class="reviews py-5">
    <div class="container">
        <h2 class="text-center mb-4">Đánh Giá Sân: <?php echo htmlspecialchars($field['name']); ?></h2>

        <!-- Danh sách đánh giá -->
        <div class="mb-5">
            <h4>Danh Sách Đánh Giá</h4>
            <?php if (empty($reviews)): ?>
                <p>Chưa có đánh giá nào cho sân này.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($review['full_name']); ?> - <?php echo $review['rating']; ?> sao</h6>
                            <p class="card-text"><?php echo htmlspecialchars($review['comment']); ?></p>
                            <p class="card-text"><small class="text-muted"><?php echo $review['created_at']; ?></small></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Form gửi đánh giá -->
        <h4>Gửi Đánh Giá Của Bạn</h4>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="rating" class="form-label">Điểm đánh giá (1-5 sao)</label>
                    <select name="rating" id="rating" class="form-select" required>
                        <option value="1">1 sao</option>
                        <option value="2">2 sao</option>
                        <option value="3">3 sao</option>
                        <option value="4">4 sao</option>
                        <option value="5">5 sao</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="comment" class="form-label">Bình luận (tối đa 200 ký tự)</label>
                    <textarea name="comment" id="comment" class="form-control" rows="3" maxlength="200" required></textarea>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="btn btn-primary w-100">Gửi đánh giá</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>