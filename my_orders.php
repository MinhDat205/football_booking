<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

$csrf_token = generateCsrfToken();
$success_message = '';
$error_message = '';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['account_type'] !== 'customer') {
    header('Location: search.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Xử lý xác nhận nhận hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_received'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error_message = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
        $stmt->execute([$order_id, $customer_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error_message = 'Đơn hàng không tồn tại hoặc không thuộc quyền quản lý của bạn.';
        } elseif ($order['status'] !== 'completed') {
            $error_message = 'Đơn hàng chưa được hoàn thành bởi chủ sân.';
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'received' WHERE id = ? AND customer_id = ?");
            $stmt->execute([$order_id, $customer_id]);
            $success_message = 'Đơn hàng đã được xác nhận nhận thành công.';
        }
    }
}

// Xử lý khởi tạo cuộc trò chuyện
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
        $stmt->execute([$customer_id, $owner_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conversation) {
            // Nếu đã có cuộc trò chuyện, lấy conversation_id
            $conversation_id = $conversation['id'];
        } else {
            // Nếu chưa có, tạo cuộc trò chuyện mới
            $stmt = $pdo->prepare("INSERT INTO conversations (user_id, owner_id) VALUES (?, ?)");
            $stmt->execute([$customer_id, $owner_id]);
            $conversation_id = $pdo->lastInsertId();

            // Gửi thông báo cho chủ sân
            $message = "Bạn có tin nhắn mới từ khách hàng (ID #$customer_id).";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'new_message_conversation', ?)");
            $stmt->execute([$owner_id, $message, $conversation_id]);
        }

        // Chuyển hướng đến trang chat với conversation_id để tự động mở modal
        header("Location: chat.php?open_conversation_id=$conversation_id");
        exit;
    }
}

// Lấy danh sách đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name as owner_name,
           (SELECT f.name 
            FROM fields f 
            JOIN products p ON f.id = p.field_id 
            JOIN order_items oi ON p.id = oi.product_id 
            WHERE oi.order_id = o.id 
            LIMIT 1) as field_name
    FROM orders o 
    JOIN users u ON o.owner_id = u.id 
    WHERE o.customer_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách sản phẩm cho từng đơn hàng
$order_products = [];
foreach ($orders as $order) {
    $stmt = $pdo->prepare("
        SELECT oi.quantity, p.name as product_name 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $order_products[$order['id']] = $items;
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="container">
    <h2 class="mb-3">Đơn Hàng Đã Đặt</h2>

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

    <?php if (empty($orders)): ?>
        <p class="text-center text-muted">Bạn chưa đặt đơn hàng nào.</p>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Chủ sân</th>
                    <th>Sân</th>
                    <th>Sản phẩm</th>
                    <th>Địa chỉ giao hàng</th>
                    <th>Tổng giá (VND)</th>
                    <th>Trạng thái</th>
                    <th>Ngày đặt</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['field_name'] ?? 'Không xác định'); ?></td>
                        <td>
                            <?php
                            $products = $order_products[$order['id']];
                            $product_list = [];
                            foreach ($products as $item) {
                                $product_list[] = htmlspecialchars($item['product_name']) . ' (' . htmlspecialchars($item['quantity']) . ')';
                            }
                            echo implode(', ', $product_list);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($order['delivery_address']); ?></td>
                        <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?></td>
                        <td>
                            <?php
                            $status = $order['status'];
                            $status_class = '';
                            switch ($status) {
                                case 'pending':
                                    $status_class = 'badge bg-warning';
                                    break;
                                case 'confirmed':
                                    $status_class = 'badge bg-success';
                                    break;
                                case 'rejected':
                                    $status_class = 'badge bg-danger';
                                    break;
                                case 'completed':
                                    $status_class = 'badge bg-primary';
                                    break;
                                case 'received':
                                    $status_class = 'badge bg-info';
                                    break;
                            }
                            ?>
                            <span class="<?php echo $status_class; ?>">
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if ($order['status'] == 'pending'): ?>
                                    <span class="text-muted">Đang chờ xử lý</span>
                                <?php elseif ($order['status'] == 'confirmed'): ?>
                                    <span class="text-muted">Đã xác nhận, đang xử lý</span>
                                <?php elseif ($order['status'] == 'rejected'): ?>
                                    <span class="text-muted">Đơn hàng bị từ chối</span>
                                <?php elseif ($order['status'] == 'completed'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="confirm_received" class="btn btn-sm btn-success">Xác nhận nhận hàng</button>
                                    </form>
                                <?php elseif ($order['status'] == 'received'): ?>
                                    <span class="text-muted">Đã nhận hàng</span>
                                <?php endif; ?>
                                <!-- Nút Chat -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="owner_id" value="<?php echo $order['owner_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" name="start_chat" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                        <i class="bi bi-chat-dots-fill"></i> Chat
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>