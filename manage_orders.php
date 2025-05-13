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

if ($_SESSION['account_type'] !== 'owner') {
    header('Location: search.php');
    exit;
}

$owner_id = $_SESSION['user_id'];

// Xử lý xác nhận/từ chối/hoàn thành đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['confirm_order']) || isset($_POST['reject_order']) || isset($_POST['complete_order']))) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error_message = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND owner_id = ?");
        $stmt->execute([$order_id, $owner_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error_message = 'Đơn hàng không tồn tại hoặc không thuộc quyền quản lý của bạn.';
        } else {
            if (isset($_POST['confirm_order'])) {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ? AND owner_id = ?");
                $stmt->execute([$order_id, $owner_id]);
                $success_message = 'Đơn hàng đã được xác nhận.';

                // Gửi thông báo cho khách hàng
                $message = "Đơn hàng của bạn (ID #$order_id) đã được xác nhận.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'order_confirmed', ?)");
                $stmt->execute([$order['customer_id'], $message, $order_id]);
            } elseif (isset($_POST['reject_order'])) {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ? AND owner_id = ?");
                $stmt->execute([$order_id, $owner_id]);
                $success_message = 'Đơn hàng đã bị từ chối.';

                // Gửi thông báo cho khách hàng
                $message = "Đơn hàng của bạn (ID #$order_id) đã bị từ chối.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'order_rejected', ?)");
                $stmt->execute([$order['customer_id'], $message, $order_id]);
            } elseif (isset($_POST['complete_order'])) {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND owner_id = ?");
                $stmt->execute([$order_id, $owner_id]);
                $success_message = 'Đơn hàng đã được đánh dấu là hoàn thành.';

                // Gửi thông báo cho khách hàng
                $message = "Đơn hàng của bạn (ID #$order_id) đã hoàn thành. Vui lòng xác nhận nhận hàng.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'order_completed', ?)");
                $stmt->execute([$order['customer_id'], $message, $order_id]);
            }
        }
    }
}

// Xử lý khởi tạo cuộc trò chuyện
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_chat'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error_message = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $customer_id = (int)$_POST['customer_id'];

        // Kiểm tra xem đã có cuộc trò chuyện giữa chủ sân và khách hàng chưa
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

            // Gửi thông báo cho khách hàng
            $message = "Bạn có tin nhắn mới từ chủ sân (ID #$owner_id).";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'new_message_conversation', ?)");
            $stmt->execute([$customer_id, $message, $conversation_id]);
        }

        // Chuyển hướng đến trang chat với conversation_id để tự động mở modal
        header("Location: chat.php?open_conversation_id=$conversation_id");
        exit;
    }
}

// Lấy danh sách đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name as customer_name 
    FROM orders o 
    JOIN users u ON o.customer_id = u.id 
    WHERE o.owner_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$owner_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once 'includes/header.php'; ?>

<div class="container">
    <h2 class="mb-3">Quản Lý Đơn Đặt Sản Phẩm</h2>

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
        <p class="text-center text-muted">Không có đơn hàng nào để hiển thị.</p>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách hàng</th>
                    <th>Địa chỉ giao hàng</th>
                    <th>Tổng giá (VND)</th>
                    <th>Trạng thái</th>
                    <th>Ngày đặt</th>
                    <th>Chi tiết</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
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
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#orderDetailsModal<?php echo $order['id']; ?>">
                                Xem chi tiết
                            </button>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if ($order['status'] == 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="confirm_order" class="btn btn-sm btn-success">Xác nhận</button>
                                        <button type="submit" name="reject_order" class="btn btn-sm btn-danger">Từ chối</button>
                                    </form>
                                <?php elseif ($order['status'] == 'confirmed'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="complete_order" class="btn btn-sm btn-primary">Hoàn thành</button>
                                    </form>
                                <?php endif; ?>
                                <!-- Nút Chat -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="customer_id" value="<?php echo $order['customer_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" name="start_chat" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                        <i class="bi bi-chat-dots-fill"></i> Chat
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal chi tiết đơn hàng -->
                    <div class="modal fade" id="orderDetailsModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="orderDetailsModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="orderDetailsModalLabel<?php echo $order['id']; ?>">Chi tiết đơn hàng #<?php echo $order['id']; ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT oi.*, p.name as product_name 
                                        FROM order_items oi 
                                        JOIN products p ON oi.product_id = p.id 
                                        WHERE oi.order_id = ?
                                    ");
                                    $stmt->execute([$order['id']]);
                                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <p><strong>Khách hàng:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                    <p><strong>Địa chỉ giao hàng:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                                    <p><strong>Tổng giá:</strong> <?php echo number_format($order['total_price'], 0, ',', '.') . ' VND'; ?></p>
                                    <p><strong>Trạng thái:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
                                    <hr>
                                    <h6>Sản phẩm:</h6>
                                    <ul>
                                        <?php foreach ($items as $item): ?>
                                            <li>
                                                <?php echo htmlspecialchars($item['product_name']); ?> - 
                                                Số lượng: <?php echo htmlspecialchars($item['quantity']); ?> - 
                                                Giá: <?php echo number_format($item['price'], 0, ',', '.') . ' VND'; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>