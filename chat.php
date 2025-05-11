<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$account_type = $_SESSION['account_type'];
$csrf_token = generateCsrfToken();

// Lấy danh sách các cuộc trò chuyện (dựa trên cặp người dùng)
$conversations = [];
if ($account_type === 'customer') {
    // Khách hàng: Lấy các cuộc trò chuyện với chủ sân
    $stmt = $pdo->prepare("
        SELECT c.id AS conversation_id, c.owner_id AS receiver_id, u.full_name AS receiver_name,
               (SELECT f.name 
                FROM fields f 
                JOIN bookings b ON b.field_id = f.id 
                WHERE b.user_id = c.user_id AND f.owner_id = c.owner_id 
                LIMIT 1) AS field_name,
               (SELECT COUNT(*) FROM messages m2 
                WHERE m2.conversation_id = c.id 
                AND m2.receiver_id = ? 
                AND m2.created_at > IFNULL((
                    SELECT MAX(m3.created_at) 
                    FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_id = ?
                ), '1970-01-01')) AS unread_count,
               (SELECT MAX(created_at) 
                FROM messages m 
                WHERE m.conversation_id = c.id) AS last_message_time
        FROM conversations c
        JOIN users u ON c.owner_id = u.id
        WHERE c.user_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($account_type === 'owner') {
    // Chủ sân: Lấy các cuộc trò chuyện với khách hàng
    $stmt = $pdo->prepare("
        SELECT c.id AS conversation_id, c.user_id AS receiver_id, u.full_name AS receiver_name,
               (SELECT f.name 
                FROM fields f 
                JOIN bookings b ON b.field_id = f.id 
                WHERE b.user_id = c.user_id AND f.owner_id = c.owner_id 
                LIMIT 1) AS field_name,
               (SELECT COUNT(*) FROM messages m2 
                WHERE m2.conversation_id = c.id 
                AND m2.receiver_id = ? 
                AND m2.created_at > IFNULL((
                    SELECT MAX(m3.created_at) 
                    FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_id = ?
                ), '1970-01-01')) AS unread_count,
               (SELECT MAX(created_at) 
                FROM messages m 
                WHERE m.conversation_id = c.id) AS last_message_time
        FROM conversations c
        JOIN users u ON c.user_id = u.id
        WHERE c.owner_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Xử lý gửi tin nhắn trước khi gửi bất kỳ output nào
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $error = '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $receiver_id = (int)$_POST['receiver_id'];
        $message = trim($_POST['message']);

        if (empty($message)) {
            $error = 'Vui lòng nhập nội dung tin nhắn.';
        } elseif (strlen($message) > 1000) {
            $error = 'Tin nhắn không được vượt quá 1000 ký tự.';
        } else {
            // Kiểm tra xem cuộc trò chuyện đã tồn tại chưa
            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE (user_id = ? AND owner_id = ?) OR (user_id = ? AND owner_id = ?)");
            $stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conversation) {
                // Tạo cuộc trò chuyện mới nếu chưa tồn tại
                $stmt = $pdo->prepare("INSERT INTO conversations (user_id, owner_id) VALUES (?, ?)");
                if ($account_type === 'customer') {
                    $stmt->execute([$user_id, $receiver_id]);
                } else {
                    $stmt->execute([$receiver_id, $user_id]);
                }
                $conversation_id = $pdo->lastInsertId();
            } else {
                $conversation_id = $conversation['id'];
            }

            // Lưu tin nhắn
            $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conversation_id, $user_id, $receiver_id, $message]);
            $message_id = $pdo->lastInsertId();

            // Gửi thông báo
            $field_name = $conversations[array_search($conversation_id, array_column($conversations, 'conversation_id'))]['field_name'] ?? 'Không xác định';
            $notification_message = "Bạn có tin nhắn mới từ " . ($account_type === 'owner' ? "chủ sân" : "khách hàng") . " liên quan đến sân bóng " . $field_name;
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'new_message_conversation', ?)");
            $stmt->execute([$receiver_id, $notification_message, $message_id]);

            $success = 'Tin nhắn đã được gửi!';
            header('Location: chat.php');
            exit;
        }
    }
}

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
    /* Bảng danh sách cuộc trò chuyện */
    .chat-table thead th {
        font-size: 1rem;
        padding: 12px;
    }
    .chat-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .chat-table td {
        padding: 12px;
        font-size: 0.95rem;
    }
    .chat-table .btn {
        padding: 8px 20px;
        font-size: 1rem;
    }
    .chat-table .btn:hover {
        background-color: #1e3c72;
    }
    .chat-table .unread-count {
        font-weight: 600;
        color: #e74c3c;
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
        .chat-table thead th,
        .chat-table td {
            padding: 10px;
            font-size: 0.9rem;
        }
        .chat-table .btn {
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

<section class="chat py-3">
    <div class="container">
        <h2 class="section-title text-center">Chat</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); // Xóa biến $success sau khi hiển thị ?>
        <?php endif; ?>
        <?php if (empty($conversations)): ?>
            <p class="text-center text-muted">Bạn chưa có cuộc trò chuyện nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table chat-table">
                    <thead>
                        <tr>
                            <th>Sân bóng</th>
                            <th>Người nhận</th>
                            <th>Tin nhắn chưa đọc</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conversation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($conversation['field_name'] ?? 'Không xác định'); ?></td>
                                <td><?php echo htmlspecialchars($conversation['receiver_name']); ?></td>
                                <td class="unread-count"><?php echo $conversation['unread_count']; ?> tin nhắn</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#chatModal<?php echo $conversation['conversation_id']; ?>">
                                            <i class="bi bi-chat-dots-fill"></i> Chat
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal chat -->
                            <div class="modal fade" id="chatModal<?php echo $conversation['conversation_id']; ?>" tabindex="-1" aria-labelledby="chatModalLabel<?php echo $conversation['conversation_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="chatModalLabel<?php echo $conversation['conversation_id']; ?>">
                                                Chat với <?php echo htmlspecialchars($conversation['receiver_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Hiển thị tin nhắn -->
                                            <?php
                                            $stmt = $pdo->prepare("SELECT m.*, u.full_name AS sender_name 
                                                                   FROM messages m 
                                                                   JOIN users u ON m.sender_id = u.id 
                                                                   WHERE m.conversation_id = ? 
                                                                   ORDER BY m.created_at ASC");
                                            $stmt->execute([$conversation['conversation_id']]);
                                            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                                <input type="hidden" name="receiver_id" value="<?php echo $conversation['receiver_id']; ?>">
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