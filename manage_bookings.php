<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

// Kiểm tra nếu người dùng không phải chủ sân, chuyển hướng về trang phù hợp
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'owner') {
    if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'customer') {
        header('Location: search.php');
        exit;
    } elseif (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin') {
        header('Location: admin_users.php');
        exit;
    } else {
        header('Location: login.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Xử lý xác nhận, hủy, hoặc hoàn thành đặt sân trước khi xuất bất kỳ dữ liệu nào
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $error = '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if (isset($_POST['send_message'])) {
            // Xử lý gửi tin nhắn
            $receiver_id = (int)$_POST['receiver_id'];
            $message = trim($_POST['message']);
            $booking_id = (int)$_POST['booking_id'];

            if (empty($message)) {
                $error = 'Vui lòng nhập nội dung tin nhắn.';
            } elseif (strlen($message) > 1000) {
                $error = 'Tin nhắn không được vượt quá 1000 ký tự.';
            } else {
                // Kiểm tra xem cuộc trò chuyện đã tồn tại chưa
                $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user_id = ? AND owner_id = ?");
                $stmt->execute([$receiver_id, $user_id]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$conversation) {
                    // Tạo cuộc trò chuyện mới nếu chưa tồn tại
                    $stmt = $pdo->prepare("INSERT INTO conversations (user_id, owner_id) VALUES (?, ?)");
                    $stmt->execute([$receiver_id, $user_id]);
                    $conversation_id = $pdo->lastInsertId();
                } else {
                    $conversation_id = $conversation['id'];
                }

                // Lưu tin nhắn
                $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$conversation_id, $user_id, $receiver_id, $message]);
                $message_id = $pdo->lastInsertId();

                // Gửi thông báo
                $stmt = $pdo->prepare("SELECT f.name FROM fields f JOIN bookings b ON b.field_id = f.id WHERE b.id = ?");
                $stmt->execute([$booking_id]);
                $field_name = $stmt->fetchColumn();
                $notification_message = "Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng " . $field_name;
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id, is_read) VALUES (?, ?, 'new_message_conversation', ?, 0)");
                $stmt->execute([$receiver_id, $notification_message, $message_id]);

                $success = 'Tin nhắn đã được gửi!';
            }
        } else {
            // Xử lý xác nhận hoặc hủy đặt sân
            $booking_id = (int)$_POST['booking_id'];
            $action = $_POST['action'];

            // Kiểm tra xem đặt sân có thuộc về sân bóng của chủ sân không
            $stmt = $pdo->prepare("SELECT b.*, f.name as field_name FROM bookings b 
                                   JOIN fields f ON b.field_id = f.id 
                                   WHERE b.id = ? AND f.owner_id = ?");
            $stmt->execute([$booking_id, $user_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $error = 'Đặt sân không hợp lệ hoặc không thuộc quyền quản lý của bạn.';
            } else {
                try {
                    if ($action === 'confirm') {
                        $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                        $stmt->execute([$booking_id]);

                        // Gửi thông báo cho khách hàng
                        $notification_message = "Yêu cầu đặt sân của bạn (ID #$booking_id) đã được xác nhận.";
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id, is_read) VALUES (?, ?, 'booking_confirmed', ?, 0)");
                        $stmt->execute([$booking['user_id'], $notification_message, $booking_id]);

                        $success = "Đã xác nhận đặt sân.";
                    } elseif ($action === 'cancel') {
                        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                        $stmt->execute([$booking_id]);

                        // Gửi thông báo cho khách hàng
                        $notification_message = "Yêu cầu đặt sân của bạn (ID #$booking_id) đã bị hủy.";
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id, is_read) VALUES (?, ?, 'booking_confirmed', ?, 0)");
                        $stmt->execute([$booking['user_id'], $notification_message, $booking_id]);

                        $success = "Đã hủy đặt sân.";
                    } elseif ($action === 'complete') {
                        // Kiểm tra xem đặt sân có đang ở trạng thái confirmed không
                        if ($booking['status'] !== 'confirmed') {
                            $error = 'Chỉ có thể hoàn thành các đặt sân đã được xác nhận.';
                        } else {
                            // Tính tổng thu nhập bao gồm cả sản phẩm đi kèm (nhân với số lượng)
                            $total_revenue = $booking['total_price'];
                            $products = json_decode($booking['selected_products'], true);
                            if (!empty($products)) {
                                foreach ($products as $product) {
                                    $total_revenue += $product['price'] * $product['quantity'];
                                }
                            }

                            // Cập nhật trạng thái thành completed
                            $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                            $stmt->execute([$booking_id]);

                            // Ghi nhận thu nhập vào bảng revenues, bao gồm cả giá sản phẩm
                            $stmt = $pdo->prepare("INSERT INTO revenues (owner_id, booking_id, field_id, amount) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$user_id, $booking_id, $booking['field_id'], $total_revenue]);

                            // Gửi thông báo cho khách hàng
                            $notification_message = "Đặt sân của bạn (ID #$booking_id) đã hoàn thành.";
                            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id, is_read) VALUES (?, ?, 'booking_completed', ?, 0)");
                            $stmt->execute([$booking['user_id'], $notification_message, $booking_id]);

                            $success = "Đã xác nhận hoàn thành đặt sân.";
                        }
                    } else {
                        $error = 'Hành động không hợp lệ.';
                    }
                } catch (PDOException $e) {
                    // Ghi log lỗi và hiển thị thông báo
                    error_log("Error in manage_bookings.php: " . $e->getMessage());
                    $error = "Có lỗi xảy ra khi xử lý đặt sân: " . $e->getMessage();
                }
            }
        }
    }

    // Lưu thông báo vào session và chuyển hướng
    if ($error) {
        $_SESSION['error'] = $error;
    } elseif ($success) {
        $_SESSION['success'] = $success;
    }
    header('Location: manage_bookings.php');
    exit;
}

// Lấy danh sách đặt sân của sân bóng thuộc chủ sân, chỉ lấy trạng thái pending và confirmed
$stmt = $pdo->prepare("SELECT b.*, f.name as field_name, u.full_name as customer_name, u.id as customer_id 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       JOIN users u ON b.user_id = u.id 
                       WHERE f.owner_id = ? AND b.status IN ('pending', 'confirmed') 
                       ORDER BY b.created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy thông báo từ session nếu có
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error']);
unset($_SESSION['success']);

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
        text-align: center;
    }
    /* Bảng quản lý */
    .booking-table thead th {
        font-size: 1rem;
        padding: 12px;
        background: #2a5298;
        color: #fff;
    }
    .booking-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .booking-table td {
        padding: 12px;
        font-size: 0.95rem;
    }
    .booking-table .price {
        color: #e74c3c;
        font-weight: 600;
        font-size: 1.1rem;
    }
    .booking-table .status-badge {
        font-size: 0.95rem;
    }
    .booking-table .btn {
        padding: 8px 20px;
        font-size: 1rem;
    }
    .booking-table .btn-primary {
        background-color: #28a745;
    }
    .booking-table .btn-primary:hover {
        background-color: #218838;
    }
    .booking-table .btn-info {
        background-color: #17a2b8;
    }
    .booking-table .btn-info:hover {
        background-color: #138496;
    }
    .booking-table .btn-danger {
        background-color: #e74c3c;
    }
    .booking-table .btn-danger:hover {
        background-color: #c0392b;
    }
    /* Modal chat */
    .modal-body {
        padding: 20px;
    }
    .modal-title {
        font-size: 1.8rem;
        margin-bottom: 1rem;
        color: #1e3c72;
    }
    .chat-box {
        border-radius: 5px;
        max-height: 400px;
        overflow-y: auto;
    }
    .chat-box .message {
        margin-bottom: 12px;
    }
    .chat-box .message p {
        border-radius: 5px;
        padding: 10px 14px;
        font-size: 1rem;
    }
    .chat-box .message.text-end p {
        background-color: #2a5298;
        color: #fff;
    }
    .chat-box .message:not(.text-end) p {
        background-color: #e6f0ff;
        color: #333;
    }
    .chat-box .message small {
        font-size: 0.85rem;
    }
    .chat-form textarea {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 1rem;
    }
    .chat-form textarea:focus {
        border-color: #2a5298;
    }
    .chat-form .btn-primary {
        padding: 8px 20px;
        font-size: 1rem;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1.2rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .booking-table thead th,
        .booking-table td {
            padding: 10px;
            font-size: 0.9rem;
        }
        .booking-table .btn {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
        .modal-body {
            padding: 15px;
        }
        .modal-title {
            font-size: 1.4rem;
        }
        .chat-box {
            max-height: 300px;
        }
        .chat-box .message p {
            font-size: 0.9rem;
        }
        .chat-box .message small {
            font-size: 0.75rem;
        }
        .chat-form textarea {
            font-size: 0.9rem;
        }
        .chat-form .btn-primary {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
    }
</style>

<section class="manage-bookings py-3">
    <div class="container">
        <h2 class="section-title">Quản Lý Đặt Sân</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); // Xóa biến $success sau khi hiển thị ?>
        <?php endif; ?>
        <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($error); // Xóa biến $error sau khi hiển thị ?>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <p class="text-center text-muted">Không có đặt sân nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table booking-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sân bóng</th>
                            <th>Khách hàng</th>
                            <th>Ngày đặt</th>
                            <th>Khung giờ</th>
                            <th>Tổng giá</th>
                            <th>Sản phẩm đi kèm</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['start_time'] . ' - ' . $booking['end_time']); ?></td>
                                <td class="price">
                                    <?php
                                    $total_price = $booking['total_price'];
                                    $products = json_decode($booking['selected_products'], true);
                                    if (!empty($products)) {
                                        foreach ($products as $product) {
                                            $total_price += $product['price'] * $product['quantity'];
                                        }
                                    }
                                    echo number_format($total_price, 0, ',', '.') . ' VND';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($products)) {
                                        foreach ($products as $product) {
                                            echo htmlspecialchars($product['name']) . ' (' . number_format($product['price'], 0, ',', '.') . ' VND x ' . $product['quantity'] . ')<br>';
                                        }
                                    } else {
                                        echo 'Không có sản phẩm đi kèm.';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $booking['status'] === 'pending' ? 'bg-warning' : 'bg-success'; ?> status-badge">
                                        <?php 
                                        // Thay đổi văn bản hiển thị của trạng thái
                                        switch ($booking['status']) {
                                            case 'pending':
                                                echo 'Chờ xác nhận';
                                                break;
                                            case 'confirmed':
                                                echo 'Đã xác nhận';
                                                break;
                                            default:
                                                echo htmlspecialchars($booking['status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                                    <i class="bi bi-check-circle"></i> Xác nhận
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
                                                    <i class="bi bi-x-circle"></i> Hủy
                                                </button>
                                            </form>
                                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" class="btn btn-success btn-sm d-flex align-items-center gap-1">
                                                    <i class="bi bi-check-circle"></i> Hoàn thành
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn btn-info btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#chatModal<?php echo $booking['id']; ?>">
                                            <i class="bi bi-chat-dots-fill"></i> Chat
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal chat -->
                            <div class="modal fade" id="chatModal<?php echo $booking['id']; ?>" tabindex="-1" aria-labelledby="chatModalLabel<?php echo $booking['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="chatModalLabel<?php echo $booking['id']; ?>">
                                                Chat với <?php echo htmlspecialchars($booking['customer_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Hiển thị tin nhắn -->
                                            <?php
                                            // Kiểm tra xem cuộc trò chuyện đã tồn tại chưa
                                            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user_id = ? AND owner_id = ?");
                                            $stmt->execute([$booking['customer_id'], $user_id]);
                                            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                                            $conversation_id = $conversation ? $conversation['id'] : null;

                                            $messages = [];
                                            if ($conversation_id) {
                                                $stmt = $pdo->prepare("SELECT m.*, u.full_name AS sender_name 
                                                                       FROM messages m 
                                                                       JOIN users u ON m.sender_id = u.id 
                                                                       WHERE m.conversation_id = ? 
                                                                       ORDER BY m.created_at ASC");
                                                $stmt->execute([$conversation_id]);
                                                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            }
                                            ?>
                                            <div class="chat-box">
                                                <?php if (empty($messages)): ?>
                                                    <p class="text-muted">Chưa có tin nhắn nào.</p>
                                                <?php else: ?>
                                                    <?php foreach ($messages as $message): ?>
                                                        <div class="message mb-3 <?php echo $message['sender_id'] == $user_id ? 'text-end' : ''; ?>">
                                                            <strong><?php echo htmlspecialchars($message['sender_name']); ?>:</strong>
                                                            <p class="mb-1"><?php echo htmlspecialchars($message['message']); ?></p>
                                                            <small class="text-muted d-block"><?php echo htmlspecialchars($message['created_at']); ?></small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Form gửi tin nhắn -->
                                            <form method="POST" class="mt-3 chat-form">
                                                <div class="mb-3">
                                                    <textarea name="message" class="form-control" rows="2" placeholder="Nhập tin nhắn..." maxlength="1000" required></textarea>
                                                </div>
                                                <input type="hidden" name="receiver_id" value="<?php echo $booking['customer_id']; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" name="send_message" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bi bi-send-fill"></i> Gửi
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>