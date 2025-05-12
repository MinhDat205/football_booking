<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

// Kiểm tra nếu người dùng là chủ sân, chuyển hướng về history.php
if (isset($_SESSION['user_id']) && $_SESSION['account_type'] === 'owner') {
    header('Location: history.php');
    exit;
}

$csrf_token = generateCsrfToken();

// Lấy tất cả sân bóng đã được phê duyệt
$fields = [];
$query = "SELECT f.*, AVG(r.rating) as avg_rating 
          FROM fields f 
          LEFT JOIN reviews r ON f.id = r.field_id 
          WHERE f.status = 'approved'";
$params = [];

$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$time = isset($_GET['time']) ? $_GET['time'] : '';
$sort_price = isset($_GET['sort_price']) ? $_GET['sort_price'] : '';

if ($location) {
    if (strlen($location) > 255) {
        $location = substr($location, 0, 255);
    }
    $query .= " AND f.address LIKE ?";
    $params[] = "%$location%";
}

if ($date && $time) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time = '';
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
}

$query .= " GROUP BY f.id";

if ($sort_price === 'asc' || $sort_price === 'desc') {
    $query .= " ORDER BY f.price_per_hour " . ($sort_price === 'asc' ? 'ASC' : 'DESC');
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách sản phẩm và hình ảnh cho từng sân
$products = [];
$field_images = [];
foreach ($fields as $field) {
    // Lấy sản phẩm
    $stmt = $pdo->prepare("SELECT * FROM products WHERE field_id = ?");
    $stmt->execute([$field['id']]);
    $products[$field['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy hình ảnh
    $stmt = $pdo->prepare("SELECT * FROM field_images WHERE field_id = ?");
    $stmt->execute([$field['id']]);
    $field_images[$field['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy dữ liệu thời tiết
function getWeather($city, $date) {
    $api_key = 'your_openweathermap_api_key_here'; // Thay bằng API Key của bạn
    $url = "http://api.openweathermap.org/data/2.5/forecast?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric";

    $response = @file_get_contents($url);
    if ($response === FALSE) {
        return ['error' => 'Không thể lấy dữ liệu thời tiết.'];
    }

    $data = json_decode($response, true);
    if (!isset($data['list'])) {
        return ['error' => 'Dữ liệu thời tiết không khả dụng.'];
    }

    // Tìm dữ liệu thời tiết gần nhất với ngày đặt sân
    $target_timestamp = strtotime($date);
    $closest_weather = null;
    $min_diff = PHP_INT_MAX;

    foreach ($data['list'] as $forecast) {
        $forecast_timestamp = $forecast['dt'];
        $diff = abs($target_timestamp - $forecast_timestamp);
        if ($diff < $min_diff) {
            $min_diff = $diff;
            $closest_weather = $forecast;
        }
    }

    if ($closest_weather) {
        $weather = [
            'description' => $closest_weather['weather'][0]['description'],
            'temperature' => $closest_weather['main']['temp'],
            'humidity' => $closest_weather['main']['humidity'],
            'wind_speed' => $closest_weather['wind']['speed']
        ];

        // Gợi ý vật dụng dựa trên thời tiết
        $suggestions = [];
        if (stripos($weather['description'], 'rain') !== false) {
            $suggestions[] = "Mang giày chống trượt và áo mưa vì trời có thể mưa.";
        }
        if ($weather['temperature'] > 30) {
            $suggestions[] = "Mang nước uống và mũ vì trời khá nóng.";
        } elseif ($weather['temperature'] < 15) {
            $suggestions[] = "Mang áo ấm vì trời có thể lạnh.";
        }
        $weather['suggestions'] = $suggestions;

        return $weather;
    }

    return ['error' => 'Không tìm thấy dữ liệu thời tiết cho ngày này.'];
}

// Lấy thông báo từ session nếu có
$modal_error = isset($_SESSION['modal_error']) ? $_SESSION['modal_error'] : '';
$modal_success = isset($_SESSION['modal_success']) ? $_SESSION['modal_success'] : '';
unset($_SESSION['modal_error']);
unset($_SESSION['modal_success']);

// Xử lý đặt sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_field'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $modal_error = ''; // Khởi tạo biến $modal_error

    if (!verifyCsrfToken($token)) {
        $modal_error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    } else {
        $field_id = (int)$_POST['field_id'];
        $booking_date = $_POST['booking_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $price_per_hour = (float)$_POST['price_per_hour'];

        // Chuẩn hóa thời gian (thêm số 0 nếu cần)
        $start_time = sprintf("%02d:%02d", ...explode(':', $start_time));
        $end_time = sprintf("%02d:%02d", ...explode(':', $end_time));

        // Ghi log để kiểm tra dữ liệu đầu vào
        error_log("Booking attempt: field_id=$field_id, booking_date=$booking_date, start_time=$start_time, end_time=$end_time, price_per_hour=$price_per_hour");

        // Kiểm tra dữ liệu đặt sân
        if (empty($booking_date)) {
            $modal_error = 'Vui lòng chọn ngày đặt sân.';
        } elseif (empty($start_time) || empty($end_time)) {
            $modal_error = 'Vui lòng chọn thời gian bắt đầu và kết thúc.';
        } elseif (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
            $modal_error = 'Không thể đặt sân cho ngày trong quá khứ.';
        } elseif ($start_time === false || $end_time === false) {
            $modal_error = 'Thời gian không hợp lệ.';
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            $modal_error = 'Giờ kết thúc phải sau giờ bắt đầu.';
        } elseif ($price_per_hour <= 0) {
            $modal_error = 'Giá sân không hợp lệ.';
        } else {
            // Kiểm tra xem sân đã được đặt trong khung giờ này chưa
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                                   WHERE field_id = ? 
                                   AND booking_date = ? 
                                   AND (
                                       (start_time <= ? AND end_time >= ?) 
                                       OR (start_time <= ? AND end_time >= ?)
                                       OR (start_time >= ? AND end_time <= ?)
                                   )");
            $stmt->execute([$field_id, $booking_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
            $booking_conflict = $stmt->fetchColumn();

            if ($booking_conflict > 0) {
                $modal_error = 'Sân đã được đặt trong khung giờ này. Vui lòng chọn thời gian khác.';
            } else {
                $hours = (strtotime($end_time) - strtotime($start_time)) / 3600;
                $total_price = $price_per_hour * $hours;

                // Lấy danh sách sản phẩm đã chọn
                $selected_products = [];
                if (isset($_POST['selected_products']) && is_array($_POST['selected_products'])) {
                    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
                    foreach ($_POST['selected_products'] as $index => $product_id) {
                        $quantity = isset($quantities[$index]) ? (int)$quantities[$index] : 0;
                        if ($quantity > 0) {
                            $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ? AND field_id = ?");
                            $stmt->execute([(int)$product_id, $field_id]);
                            $product = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($product) {
                                $selected_products[] = [
                                    'product_id' => $product['id'],
                                    'name' => $product['name'],
                                    'price' => $product['price'],
                                    'quantity' => $quantity
                                ];
                                $total_price += $product['price'] * $quantity;
                            }
                        }
                    }
                }
                $selected_products_json = json_encode($selected_products ?: []); // Đảm bảo luôn có chuỗi JSON hợp lệ

                // Lưu thông tin đặt sân
                try {
                    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, field_id, booking_date, start_time, end_time, total_price, status, selected_products) 
                                           VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                    $stmt->execute([$_SESSION['user_id'], $field_id, $booking_date, $start_time, $end_time, $total_price, $selected_products_json]);
                    $booking_id = $pdo->lastInsertId();

                    // Gửi thông báo cho chủ sân
                    $stmt = $pdo->prepare("SELECT owner_id FROM fields WHERE id = ?");
                    $stmt->execute([$field_id]);
                    $field = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($field) {
                        $owner_id = $field['owner_id'];
                        $notification_message = "Bạn có yêu cầu đặt sân mới (ID #$booking_id) từ khách hàng.";
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'booking_confirmed', ?)");
                        $stmt->execute([$owner_id, $notification_message, $booking_id]);
                    }

                    $modal_success = "Yêu cầu đặt sân đã được gửi, đang chờ xác nhận!";
                    error_log("Booking successful: booking_id=$booking_id");
                } catch (PDOException $e) {
                    $modal_error = "Lỗi khi lưu thông tin đặt sân: " . $e->getMessage();
                    error_log("Booking failed: " . $e->getMessage());
                }
            }
        }
    }

    // Lưu thông báo vào session để hiển thị lại nếu modal được mở lại
    $_SESSION['modal_error'] = $modal_error;
    $_SESSION['modal_success'] = $modal_success;
    header("Location: search.php?field_id=$field_id");
    exit;
}

// Lấy field_id từ query parameter để tự động mở modal
$auto_open_field_id = isset($_GET['field_id']) ? (int)$_GET['field_id'] : null;

// Chỉ bao gồm header.php sau khi xử lý logic
require_once 'includes/header.php';
?>

<style>
    /* Tiêu đề trang */
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }
    /* Form tìm kiếm */
    .search-form {
        background-color: #fff;
        padding: 15px;
        border-radius: 5px;
    }
    .search-form .form-control,
    .search-form .form-select {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 1rem;
    }
    .search-form .form-control:focus,
    .search-form .form-select:focus {
        border-color: #2a5298;
    }
    .search-form .input-group-text {
        border-radius: 5px 0 0 5px;
        font-size: 1rem;
    }
    .search-form .btn-primary {
        background-color: #28a745;
        padding: 8px 20px;
        font-size: 1rem;
    }
    .search-form .btn-primary:hover {
        background-color: #218838;
    }
    /* Card sân bóng */
    .field-card .carousel {
        height: 200px;
        position: relative;
    }
    .field-card .carousel-item img {
        height: 200px;
        object-fit: cover;
        border-bottom: 1px solid #e0e4e9;
    }
    /* Đảm bảo nút chuyển ảnh hiển thị rõ ràng */
    .field-card .carousel-control-prev,
    .field-card .carousel-control-next {
        width: 15%;
        background: rgba(0, 0, 0, 0.3);
        opacity: 0.8;
        transition: opacity 0.3s;
    }
    .field-card .carousel-control-prev:hover,
    .field-card .carousel-control-next:hover {
        opacity: 1;
    }
    .field-card .carousel-control-prev-icon,
    .field-card .carousel-control-next-icon {
        background-color: #000;
        border-radius: 50%;
        padding: 10px;
    }
    .field-card .card-title {
        font-size: 1.2rem;
        margin-bottom: 8px;
    }
    .field-card .card-text {
        font-size: 0.95rem;
    }
    .field-card .card-text.price {
        color: #e74c3c;
        font-weight: 600;
        font-size: 1.1rem;
    }
    .field-card .card-text.rating {
        color: #ffca28;
        font-weight: 600;
    }
    .field-card .btn-primary {
        padding: 8px 20px;
        font-size: 1rem;
    }
    /* Modal đặt sân */
    .modal-body {
        padding: 20px;
    }
    .modal-title {
        font-size: 1.8rem;
        margin-bottom: 1rem;
        color: #1e3c72;
    }
    .modal .form-label {
        font-weight: 500;
        color: #444;
        font-size: 1rem;
    }
    .modal .form-control {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 1rem;
    }
    .modal .form-control:focus {
        border-color: #2a5298;
    }
    .modal .input-group-text {
        border-radius: 5px 0 0 5px;
    }
    /* Thông báo trong modal */
    .modal-message {
        margin-bottom: 15px;
    }
    /* Thời tiết */
    .weather-info h6 {
        font-weight: 600;
        color: #1e3c72;
        margin-bottom: 0.75rem;
        font-size: 1.1rem;
    }
    .weather-info p {
        font-size: 0.95rem;
    }
    /* Card sản phẩm trong modal */
    .product-card {
        border-radius: 5px;
    }
    .product-card img {
        border-top-left-radius: 5px;
        border-top-right-radius: 5px;
        max-height: 100px;
    }
    .product-card .card-body {
        padding: 12px;
    }
    .product-card .card-title {
        font-size: 1rem;
    }
    .product-card .card-text {
        font-size: 0.9rem;
    }
    .product-card .price {
        color: #e74c3c;
        font-size: 0.95rem;
    }
    /* Số lượng sản phẩm */
    .quantity-input {
        width: 70px;
        display: inline-block;
        margin-left: 10px;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1.2rem;
        }
        .search-form {
            padding: 10px;
        }
        .search-form .form-control,
        .search-form .form-select,
        .search-form .btn-primary {
            font-size: 0.9rem;
        }
        .field-card .carousel {
            height: 150px;
        }
        .field-card .carousel-item img {
            height: 150px;
        }
        .field-card .card-title {
            font-size: 1rem;
        }
        .field-card .card-text {
            font-size: 0.85rem;
        }
        .field-card .btn-primary {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
        .modal-body {
            padding: 15px;
        }
        .modal-title {
            font-size: 1.4rem;
        }
        .product-card .card-title {
            font-size: 0.9rem;
        }
        .product-card img {
            max-height: 80px;
        }
        .quantity-input {
            width: 60px;
        }
    }
</style>

<section class="search py-3">
    <div class="container">

        <!-- Form tìm kiếm -->
        <form method="GET" class="row g-3 align-items-end search-form">
            <div class="col-md-4 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                    <input type="text" name="location" class="form-control" placeholder="Nhập vị trí" value="<?php echo htmlspecialchars($location); ?>">
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                    <input type="time" name="time" class="form-control" value="<?php echo htmlspecialchars($time); ?>">
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                    <select name="sort_price" class="form-select">
                        <option value="">Sắp xếp giá</option>
                        <option value="asc" <?php echo $sort_price === 'asc' ? 'selected' : ''; ?>>Thấp đến cao</option>
                        <option value="desc" <?php echo $sort_price === 'desc' ? 'selected' : ''; ?>>Cao đến thấp</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-search"></i> Tìm
                </button>
            </div>
        </form>

        <!-- Danh sách sân -->
        <div class="row mt-3">
            <?php if (empty($fields)): ?>
                <p class="text-center text-muted">Không tìm thấy sân bóng phù hợp.</p>
            <?php else: ?>
                <?php foreach ($fields as $field): ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="card field-card">
                            <!-- Carousel hiển thị nhiều hình ảnh -->
                            <div id="carouselField<?php echo $field['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <?php if (!empty($field_images[$field['id']])): ?>
                                        <?php foreach ($field_images[$field['id']] as $index => $image): ?>
                                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                                <img src="assets/img/<?php echo htmlspecialchars($image['image']); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($field['name']); ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="carousel-item active">
                                            <img src="assets/img/default.jpg" class="d-block w-100" alt="Ảnh mặc định">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (count($field_images[$field['id']]) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselField<?php echo $field['id']; ?>" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#carouselField<?php echo $field['id']; ?>" data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Next</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($field['name']); ?></h5>
                                <p class="card-text"><i class="bi bi-geo-alt-fill"></i> Địa chỉ: <?php echo htmlspecialchars($field['address']); ?></p>
                                <p class="card-text"><i class="bi bi-people"></i> Loại sân: <?php echo htmlspecialchars($field['field_type']); ?> người</p>
                                <p class="card-text"><i class="bi bi-clock"></i> Giờ mở cửa: <?php echo htmlspecialchars($field['open_time']); ?></p>
                                <p class="card-text"><i class="bi bi-clock"></i> Giờ đóng cửa: <?php echo htmlspecialchars($field['close_time']); ?></p>
                                <p class="card-text price"><i class="bi bi-currency-dollar"></i> Giá: <?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND/giờ'; ?></p>
                                <p class="card-text rating"><i class="bi bi-star-fill text-warning"></i> Đánh giá: <?php echo $field['avg_rating'] ? round($field['avg_rating'], 1) : 'Chưa có'; ?> sao</p>
                                <a href="<?php echo isset($_SESSION['user_id']) ? 'search.php?field_id=' . $field['id'] : 'login.php?field_id=' . $field['id']; ?>" 
                                   class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2"
                                   <?php echo isset($_SESSION['user_id']) ? 'data-bs-toggle="modal" data-bs-target="#bookModal' . $field['id'] . '"' : ''; ?>>
                                    <i class="bi bi-calendar-check"></i> Đặt sân ngay
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Modal đặt sân (chỉ hiển thị nếu đã đăng nhập) -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="modal fade" id="bookModal<?php echo $field['id']; ?>" tabindex="-1" aria-labelledby="bookModalLabel<?php echo $field['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bookModalLabel<?php echo $field['id']; ?>">Đặt Sân: <?php echo htmlspecialchars($field['name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if ($modal_error && $auto_open_field_id == $field['id']): ?>
                                        <div class="alert alert-danger alert-dismissible fade show modal-message" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $modal_error; ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($modal_success && $auto_open_field_id == $field['id']): ?>
                                        <div class="alert alert-success alert-dismissible fade show modal-message" role="alert">
                                            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $modal_success; ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    <form method="POST">
                                        <div class="row">
                                            <!-- Thông tin đặt sân -->
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="booking_date_<?php echo $field['id']; ?>" class="form-label">Ngày đặt sân</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                                        <input type="date" name="booking_date" id="booking_date_<?php echo $field['id']; ?>" class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="start_time_<?php echo $field['id']; ?>" class="form-label">Giờ bắt đầu</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                                        <input type="time" name="start_time" id="start_time_<?php echo $field['id']; ?>" class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="end_time_<?php echo $field['id']; ?>" class="form-label">Giờ kết thúc</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                                        <input type="time" name="end_time" id="end_time_<?php echo $field['id']; ?>" class="form-control" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Thông tin thời tiết -->
                                            <div class="col-md-6 weather-info">
                                                <?php if ($date): ?>
                                                    <?php $weather = getWeather(explode(',', $field['address'])[0], $date); ?>
                                                    <?php if (isset($weather['error'])): ?>
                                                        <p class="text-muted"><?php echo $weather['error']; ?></p>
                                                    <?php else: ?>
                                                        <h6>Thời tiết tại <?php echo htmlspecialchars($field['address']); ?> ngày <?php echo htmlspecialchars($date); ?>:</h6>
                                                        <p><strong>Tình trạng:</strong> <?php echo htmlspecialchars($weather['description']); ?></p>
                                                        <p><strong>Nhiệt độ:</strong> <?php echo htmlspecialchars($weather['temperature']); ?>°C</p>
                                                        <p><strong>Độ ẩm:</strong> <?php echo htmlspecialchars($weather['humidity']); ?>%</p>
                                                        <p><strong>Tốc độ gió:</strong> <?php echo htmlspecialchars($weather['wind_speed']); ?> m/s</p>
                                                        <?php if (!empty($weather['suggestions'])): ?>
                                                            <p><strong>Gợi ý:</strong></p>
                                                            <ul>
                                                                <?php foreach ($weather['suggestions'] as $suggestion): ?>
                                                                    <li><?php echo htmlspecialchars($suggestion); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p class="text-muted">Vui lòng chọn ngày để xem thông tin thời tiết.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <!-- Danh sách sản phẩm -->
                                        <?php if (!empty($products[$field['id']])): ?>
                                            <hr>
                                            <h6 class="mb-3">Chọn sản phẩm đi kèm:</h6>
                                            <div class="row">
                                                <?php foreach ($products[$field['id']] as $index => $product): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="card product-card">
                                                            <img src="assets/img/<?php echo htmlspecialchars($product['image'] ?: 'default_product.jpg'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                            <div class="card-body">
                                                                <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                                <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                                                                <p class="price"><?php echo number_format($product['price'], 0, ',', '.') . ' VND'; ?></p>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="selected_products[]" value="<?php echo $product['id']; ?>" id="product_<?php echo $product['id']; ?>">
                                                                    <label class="form-check-label" for="product_<?php echo $product['id']; ?>">
                                                                        Chọn sản phẩm
                                                                    </label>
                                                                    <input type="number" name="quantities[]" min="0" value="0" class="form-control quantity-input" id="quantity_<?php echo $product['id']; ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                        <input type="hidden" name="price_per_hour" value="<?php echo $field['price_per_hour']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="book_field" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <i class="bi bi-check-circle"></i> Gửi yêu cầu đặt sân
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($auto_open_field_id): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tự động mở modal nếu có field_id trong query parameter
        const modal = document.getElementById('bookModal<?php echo $auto_open_field_id; ?>');
        if (modal) {
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        } else {
            console.error('Modal not found for field_id: <?php echo $auto_open_field_id; ?>');
        }
    });
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>