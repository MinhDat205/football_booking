<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

$csrf_token = generateCsrfToken();
$success_message = '';
$error_message = '';

// Kiểm tra thông báo lỗi từ session (nếu có)
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['account_type'] !== 'customer') {
    header('Location: search.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Xử lý lưu địa chỉ giao hàng vào session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    $delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : '';
    if (empty($delivery_address)) {
        $error_message = 'Vui lòng nhập địa chỉ giao hàng.';
    } else {
        $_SESSION['delivery_address'] = $delivery_address;
        $success_message = 'Địa chỉ giao hàng đã được lưu.';
    }
}

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error_message = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $owner_id = (int)$_POST['owner_id'];

        if (!isset($_SESSION['delivery_address'])) {
            $error_message = 'Vui lòng nhập địa chỉ giao hàng trước khi đặt hàng.';
        } elseif ($quantity <= 0) {
            $error_message = 'Vui lòng chọn số lượng sản phẩm hợp lệ.';
        } else {
            // Lấy thông tin sản phẩm
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $error_message = 'Sản phẩm không tồn tại.';
            } else {
                $total_price = $product['price'] * $quantity;
                $delivery_address = $_SESSION['delivery_address'];

                // Lưu đơn hàng
                $stmt = $pdo->prepare("INSERT INTO orders (customer_id, owner_id, delivery_address, total_price, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $owner_id, $delivery_address, $total_price]);
                $order_id = $pdo->lastInsertId();

                // Lưu chi tiết đơn hàng
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);

                // Gửi thông báo cho chủ sân
                $message = "Bạn có đơn đặt sản phẩm mới (ID #$order_id) từ khách hàng.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'new_order', ?)");
                $stmt->execute([$owner_id, $message, $order_id]);

                $success_message = 'Đơn hàng đã được đặt thành công! Bạn có thể theo dõi trạng thái đơn hàng trong "Đơn đã đặt".';
            }
        }
    }
}

// Xử lý chuyển hướng đến giao diện chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_chat'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error_message = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $owner_id = (int)$_POST['owner_id'];

        // Kiểm tra xem đã có cuộc trò chuyện giữa khách hàng và chủ sân chưa
        $stmt = $pdo->prepare("
            SELECT id FROM conversations 
            WHERE user_id = ? AND owner_id = ?
        ");
        $stmt->execute([$user_id, $owner_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conversation) {
            // Nếu đã có cuộc trò chuyện, lấy conversation_id
            $conversation_id = $conversation['id'];
        } else {
            // Nếu chưa có, tạo cuộc trò chuyện mới
            $stmt = $pdo->prepare("INSERT INTO conversations (user_id, owner_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $owner_id]);
            $conversation_id = $pdo->lastInsertId();

            // Gửi thông báo cho chủ sân
            $message = "Bạn có tin nhắn mới từ khách hàng (ID #$user_id).";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'new_message_conversation', ?)");
            $stmt->execute([$owner_id, $message, $conversation_id]);
        }

        // Chuyển hướng đến trang chat với conversation_id để tự động mở modal
        header("Location: chat.php?open_conversation_id=$conversation_id");
        exit;
    }
}

// Xử lý lọc sản phẩm
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$search_name = isset($_GET['search_name']) && $_GET['search_name'] !== '' ? trim($_GET['search_name']) : '';
$address = isset($_GET['address']) && $_GET['address'] !== '' ? trim($_GET['address']) : '';
$sort_price = isset($_GET['sort_price']) && in_array($_GET['sort_price'], ['asc', 'desc']) ? $_GET['sort_price'] : '';

// Chuẩn hóa dữ liệu đầu vào
if (!is_null($min_price) && $min_price < 0) {
    $min_price = 0; // Giá tối thiểu không được âm
}
if (!is_null($max_price) && $max_price < 0) {
    $max_price = 0; // Giá tối đa không được âm
}
if (!is_null($min_price) && !is_null($max_price) && $min_price > $max_price) {
    // Đảm bảo min_price không lớn hơn max_price
    $temp = $min_price;
    $min_price = $max_price;
    $max_price = $temp;
}
if ($search_name !== '' && strlen($search_name) > 255) {
    $search_name = substr($search_name, 0, 255); // Giới hạn độ dài tên
}
if ($address !== '' && strlen($address) > 255) {
    $address = substr($address, 0, 255); // Giới hạn độ dài địa chỉ
}

// Lấy danh sách sản phẩm với các điều kiện lọc
$query = "
    SELECT p.*, f.name as field_name, f.owner_id, f.address 
    FROM products p 
    JOIN fields f ON p.field_id = f.id 
    WHERE f.status = 'approved'
";
$params = [];

if (!is_null($min_price)) {
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}
if (!is_null($max_price)) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}
if ($search_name !== '') {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_name%";
    $params[] = "%$search_name%";
}
if ($address !== '') {
    $query .= " AND f.address LIKE ?";
    $params[] = "%$address%";
}

if ($sort_price === 'asc' || $sort_price === 'desc') {
    $query .= " ORDER BY p.price " . ($sort_price === 'asc' ? 'ASC' : 'DESC');
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn SQL: " . $e->getMessage();
    $products = [];
}
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .filter-form {
        background-color: #fff;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    .filter-form .form-group {
        margin-bottom: 10px;
    }
    .filter-form label {
        font-weight: 600;
        margin-bottom: 5px;
    }
    .filter-form input, .filter-form select {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        padding: 8px;
        font-size: 0.9rem;
    }
    .filter-form input:focus, .filter-form select:focus {
        border-color: #2a5298;
        outline: none;
    }
    .filter-form .btn-primary {
        padding: 8px 20px;
        font-size: 0.9rem;
    }
</style>

<div class="container">
    <h2 class="mb-3">Mua Sản Phẩm</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form nhập địa chỉ giao hàng -->
    <div class="mb-4">
        <h5>Địa chỉ giao hàng (Vui lòng nhập địa chỉ và lưu trước khi chọn sản phẩm)</h5>
        <form method="POST">
            <div class="mb-3">
                <textarea name="delivery_address" class="form-control" rows="3" placeholder="Nhập địa chỉ giao hàng" required><?php echo isset($_SESSION['delivery_address']) ? htmlspecialchars($_SESSION['delivery_address']) : ''; ?></textarea>
            </div>
            <button type="submit" name="save_address" class="btn btn-primary">Lưu địa chỉ</button>
        </form>
    </div>

    <!-- Form lọc sản phẩm -->
    <div class="filter-form">
        <h5>Lọc Sản Phẩm</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <div class="form-group">
                    <label for="search_name">Tên sản phẩm</label>
                    <input type="text" name="search_name" id="search_name" class="form-control" placeholder="Nhập tên sản phẩm" value="<?php echo htmlspecialchars($search_name); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="min_price">Giá tối thiểu (VND)</label>
                    <input type="number" name="min_price" id="min_price" class="form-control" placeholder="Giá tối thiểu" value="<?php echo !is_null($min_price) ? htmlspecialchars($min_price) : ''; ?>" min="0">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="max_price">Giá tối đa (VND)</label>
                    <input type="number" name="max_price" id="max_price" class="form-control" placeholder="Giá tối đa" value="<?php echo !is_null($max_price) ? htmlspecialchars($max_price) : ''; ?>" min="0">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="address">Địa chỉ sân</label>
                    <input type="text" name="address" id="address" class="form-control" placeholder="Nhập địa chỉ sân" value="<?php echo htmlspecialchars($address); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="sort_price">Sắp xếp giá</label>
                    <select name="sort_price" id="sort_price" class="form-control">
                        <option value="">Mặc định</option>
                        <option value="asc" <?php echo $sort_price === 'asc' ? 'selected' : ''; ?>>Thấp đến cao</option>
                        <option value="desc" <?php echo $sort_price === 'desc' ? 'selected' : ''; ?>>Cao đến thấp</option>
                    </select>
                </div>
            </div>
            <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary">Lọc</button>
            </div>
        </form>
    </div>

    <!-- Danh sách sản phẩm -->
    <div class="row">
        <?php if (empty($products)): ?>
            <p class="text-center text-muted">Không tìm thấy sản phẩm phù hợp với bộ lọc.</p>
        <?php else: ?>
            <?php foreach ($products as $index => $product): ?>
                <div class="col-md-4 mb-3">
                    <div class="card product-card">
                        <img src="assets/img/<?php echo htmlspecialchars($product['image'] ?: 'default_product.jpg'); ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             onerror="this.onerror=null; this.src='assets/img/default_product.jpg';">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                            <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                            <p class="price"><?php echo number_format($product['price'], 0, ',', '.') . ' VND'; ?></p>
                            <p class="card-text"><small class="text-muted">Chủ sân: <?php echo htmlspecialchars($product['field_name']); ?></small></p>
                            <p class="card-text"><small class="text-muted">Địa chỉ: <?php echo htmlspecialchars($product['address']); ?></small></p>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="product_<?php echo $index; ?>" onchange="toggleOrderButton(<?php echo $index; ?>)">
                                <label class="form-check-label" for="product_<?php echo $index; ?>">
                                    Chọn sản phẩm
                                </label>
                                <input type="number" name="quantity_<?php echo $index; ?>" id="quantity_<?php echo $index; ?>" min="0" value="0" class="form-control quantity-input">
                            </div>
                            <form method="POST" id="order_form_<?php echo $index; ?>" style="display:none;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="owner_id" value="<?php echo $product['owner_id']; ?>">
                                <input type="hidden" name="quantity" id="hidden_quantity_<?php echo $index; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" name="place_order" class="btn btn-primary w-100 mb-2">
                                    <i class="bi bi-cart-check"></i> Đặt hàng
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="owner_id" value="<?php echo $product['owner_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" name="start_chat" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-chat-dots-fill"></i> Chat với chủ sân
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleOrderButton(index) {
    const checkbox = document.getElementById('product_' + index);
    const form = document.getElementById('order_form_' + index);
    const quantityInput = document.getElementById('quantity_' + index);
    const hiddenQuantity = document.getElementById('hidden_quantity_' + index);

    if (checkbox.checked && quantityInput.value > 0) {
        form.style.display = 'block';
        hiddenQuantity.value = quantityInput.value;
    } else {
        form.style.display = 'none';
    }
}

// Cập nhật hidden quantity khi số lượng thay đổi
document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('input', function() {
        const index = this.id.split('_')[1];
        const checkbox = document.getElementById('product_' + index);
        toggleOrderButton(index);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>