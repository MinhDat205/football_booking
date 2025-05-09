<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

session_start();

// Xử lý tìm kiếm
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$time = isset($_GET['time']) ? $_GET['time'] : '';
$sort_price = isset($_GET['sort_price']) ? $_GET['sort_price'] : '';
$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;

$fields = [];
$query = "SELECT f.*, AVG(r.rating) as avg_rating 
          FROM fields f 
          LEFT JOIN reviews r ON f.id = r.field_id 
          WHERE f.status = 'approved'";
$params = [];

if ($location) {
    $query .= " AND f.address LIKE ?";
    $params[] = "%$location%";
}

if ($date && $time) {
    $query .= " AND f.id NOT IN (
        SELECT field_id FROM bookings 
        WHERE booking_date = ? 
        AND start_time <= ? AND end_time >= ?
    )";
    $params[] = $date;
    $params[] = $time;
    $params[] = $time;
}

$query .= " GROUP BY f.id";
if ($min_rating > 0) {
    $query .= " HAVING avg_rating >= ?";
    $params[] = $min_rating;
}

if ($sort_price === 'asc') {
    $query .= " ORDER BY f.price_per_hour ASC";
} elseif ($sort_price === 'desc') {
    $query .= " ORDER BY f.price_per_hour DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý đặt sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_field'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $field_id = $_POST['field_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $price_per_hour = $_POST['price_per_hour'];
    $hours = (strtotime($end_time) - strtotime($start_time)) / 3600;
    $total_price = $price_per_hour * $hours;

    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, field_id, booking_date, start_time, end_time, total_price, status) 
                           VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'], $field_id, $booking_date, $start_time, $end_time, $total_price]);
    $success = "Yêu cầu đặt sân đã được gửi, đang chờ xác nhận!";
}
?>

<section class="search py-5">
    <div class="container">
        <h2 class="text-center mb-4">Tìm Kiếm Sân Bóng</h2>

        <!-- Form tìm kiếm -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" name="location" class="form-control" placeholder="Nhập vị trí" value="<?php echo htmlspecialchars($location); ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <div class="col-md-2">
                <input type="time" name="time" class="form-control" value="<?php echo htmlspecialchars($time); ?>">
            </div>
            <div class="col-md-2">
                <select name="sort_price" class="form-select">
                    <option value="">Sắp xếp giá</option>
                    <option value="asc" <?php echo $sort_price === 'asc' ? 'selected' : ''; ?>>Thấp đến cao</option>
                    <option value="desc" <?php echo $sort_price === 'desc' ? 'selected' : ''; ?>>Cao đến thấp</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="min_rating" class="form-select">
                    <option value="0">Đánh giá tối thiểu</option>
                    <option value="1" <?php echo $min_rating == 1 ? 'selected' : ''; ?>>1 sao</option>
                    <option value="2" <?php echo $min_rating == 2 ? 'selected' : ''; ?>>2 sao</option>
                    <option value="3" <?php echo $min_rating == 3 ? 'selected' : ''; ?>>3 sao</option>
                    <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4 sao</option>
                    <option value="5" <?php echo $min_rating == 5 ? 'selected' : ''; ?>>5 sao</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Tìm</button>
            </div>
        </form>

        <!-- Thông báo thành công -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Danh sách sân -->
        <div class="row">
            <?php if (empty($fields)): ?>
                <p class="text-center">Không tìm thấy sân bóng phù hợp.</p>
            <?php else: ?>
                <?php foreach ($fields as $field): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <img src="assets/img/<?php echo $field['image'] ?: 'default.jpg'; ?>" class="card-img-top" alt="<?php echo $field['name']; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $field['name']; ?></h5>
                                <p class="card-text"><?php echo $field['address']; ?></p>
                                <p class="card-text"><?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND/giờ'; ?></p>
                                <p class="card-text">Đánh giá: <?php echo $field['avg_rating'] ? round($field['avg_rating'], 1) : 'Chưa có'; ?> sao</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal<?php echo $field['id']; ?>">Đặt sân</button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal đặt sân -->
                    <div class="modal fade" id="bookModal<?php echo $field['id']; ?>" tabindex="-1" aria-labelledby="bookModalLabel<?php echo $field['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bookModalLabel<?php echo $field['id']; ?>">Đặt Sân: <?php echo $field['name']; ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="booking_date" class="form-label">Ngày đặt</label>
                                            <input type="date" name="booking_date" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="start_time" class="form-label">Giờ bắt đầu</label>
                                            <input type="time" name="start_time" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="end_time" class="form-label">Giờ kết thúc</label>
                                            <input type="time" name="end_time" class="form-control" required>
                                        </div>
                                        <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                        <input type="hidden" name="price_per_hour" value="<?php echo $field['price_per_hour']; ?>">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                        <button type="submit" name="book_field" class="btn btn-primary">Gửi yêu cầu</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>