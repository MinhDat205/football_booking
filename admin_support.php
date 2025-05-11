<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Lấy danh sách yêu cầu hỗ trợ
$support_requests = $pdo->prepare("SELECT sr.*, u.full_name AS user_name FROM support_requests sr LEFT JOIN users u ON sr.user_id = u.id ORDER BY sr.created_at DESC");
$support_requests->execute();
$support_requests_list = $support_requests->fetchAll(PDO::FETCH_ASSOC);

// Xử lý hành động của admin: đánh dấu yêu cầu hỗ trợ là đã giải quyết
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if (isset($_POST['resolve_support_request'])) {
            $request_id = (int)$_POST['request_id'];
            $stmt = $pdo->prepare("UPDATE support_requests SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$request_id]);
            $success = "Đã đánh dấu yêu cầu hỗ trợ là đã giải quyết!";
            header('Location: admin_support.php');
            exit;
        }
    }
}

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
    .admin-table thead th {
        font-size: 1rem;
        padding: 12px;
        background: #2a5298;
        color: #fff;
    }
    .admin-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .admin-table td {
        padding: 12px;
        font-size: 0.95rem;
    }
    .admin-table .status-badge {
        font-size: 0.95rem;
    }
    .admin-table .btn {
        padding: 8px 20px;
        font-size: 1rem;
    }
    .admin-table .btn-primary {
        background-color: #28a745;
    }
    .admin-table .btn-primary:hover {
        background-color: #218838;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1.2rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .admin-table thead th,
        .admin-table td {
            padding: 10px;
            font-size: 0.9rem;
        }
        .admin-table .btn {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
    }
</style>

<section class="admin py-3">
    <div class="container">
        <h2 class="section-title">Quản Lý Yêu Cầu Hỗ Trợ</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); // Xóa biến $success sau khi hiển thị ?>
        <?php endif; ?>

        <?php if (empty($support_requests_list)): ?>
            <p class="text-center text-muted">Không có yêu cầu hỗ trợ nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Nội dung</th>
                            <th>Người gửi</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($support_requests_list as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td><?php echo htmlspecialchars($request['content']); ?></td>
                                <td><?php echo htmlspecialchars($request['user_name'] ?? 'Khách'); ?></td>
                                <td>
                                    <span class="badge <?php echo $request['status'] === 'pending' ? 'bg-warning' : 'bg-success'; ?> status-badge">
                                        <?php echo htmlspecialchars($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="resolve_support_request" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-check-circle"></i> Giải quyết
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>