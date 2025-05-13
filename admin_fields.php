<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();

// Lấy danh sách sân bóng, ưu tiên sân chưa xác nhận (pending) lên trên
$fields = $pdo->prepare("
    SELECT f.*, u.full_name AS owner_name 
    FROM fields f 
    JOIN users u ON f.owner_id = u.id 
    WHERE f.status IN ('approved', 'pending') 
    ORDER BY 
        CASE 
            WHEN f.status = 'pending' THEN 1 
            ELSE 2 
        END, 
        f.created_at DESC
");
$fields->execute();
$fields_list = $fields->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách hình ảnh cho từng sân
$field_images = [];
foreach ($fields_list as $field) {
    $stmt = $pdo->prepare("SELECT * FROM field_images WHERE field_id = ?");
    $stmt->execute([$field['id']]);
    $field_images[$field['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if (isset($_POST['approve_field']) || isset($_POST['reject_field'])) {
            $field_id = (int)$_POST['field_id'];
            $new_status = isset($_POST['approve_field']) ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE fields SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $field_id]);
            $success = "Cập nhật trạng thái sân bóng thành công!";
            header('Location: admin_fields.php');
            exit;
        }
        // Xử lý xóa sân
        if (isset($_POST['delete_field'])) {
            $field_id = (int)$_POST['field_id'];
            $stmt = $pdo->prepare("UPDATE fields SET status = 'deleted' WHERE id = ?");
            $stmt->execute([$field_id]);
            $_SESSION['success'] = "Đã ẩn sân khỏi hệ thống (xóa mềm).";
            header('Location: admin_fields.php');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .section-title {
        font-weight: 700;
        color: #2c3e50;
        font-size: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        text-transform: uppercase;
    }

    .admin-table {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
    }

    .admin-table thead {
        background: linear-gradient(to right, #1e3c72, #2a5298);
        color: #fff;
    }

    .admin-table thead th {
        padding: 14px;
        font-size: 1rem;
    }

    .admin-table tbody td {
        padding: 14px;
        font-size: 0.95rem;
        vertical-align: middle;
    }

    .admin-table tbody tr:hover {
        background-color: #f1f1f1;
    }

    .price {
        color: #e74c3c;
        font-weight: bold;
    }

    .badge.bg-warning {
        background-color: #f39c12 !important;
        color: #fff;
    }

    .badge.bg-success {
        background-color: #28a745 !important;
    }

    .badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .btn-sm {
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 0.9rem;
    }

    .modal-title {
        font-size: 1.6rem;
        font-weight: 600;
        color: #2c3e50;
    }

    .modal-body {
        background: #f9f9f9;
        border-radius: 0 0 10px 10px;
    }

    .field-images img {
        max-width: 140px;
        border-radius: 8px;
        margin: 6px;
        box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
    }

    .action-btn {
        width: 130px;
        height: 38px;
        justify-content: center;
        text-align: center;
    }

    @media (max-width: 768px) {
        .admin-table thead th,
        .admin-table tbody td {
            font-size: 0.85rem;
            padding: 10px;
        }

        .section-title {
            font-size: 1.5rem;
        }

        .modal-title {
            font-size: 1.3rem;
        }

        .field-images img {
            max-width: 100px;
        }

        .action-btn {
            width: 100%;
        }
    }
</style>

<section class="admin py-3">
    <div class="container">
        <h2 class="section-title">Quản Lý Sân Bóng</h2>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($success); ?>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($error); ?>
        <?php endif; ?>

        <?php if (empty($fields_list)): ?>
            <p class="text-center text-muted">Không có sân bóng nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Tên sân</th>
                            <th>Địa chỉ</th>
                            <th>Giá mỗi giờ</th>
                            <th>Giờ mở cửa</th>
                            <th>Giờ đóng cửa</th>
                            <th>Loại sân</th>
                            <th>Chủ sân</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields_list as $field): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($field['name']); ?></td>
                                <td><?php echo htmlspecialchars($field['address']); ?></td>
                                <td class="price"><?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND'; ?></td>
                                <td><?php echo htmlspecialchars($field['open_time']); ?></td>
                                <td><?php echo htmlspecialchars($field['close_time']); ?></td>
                                <td><?php echo htmlspecialchars($field['field_type']); ?> người</td>
                                <td><?php echo htmlspecialchars($field['owner_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $field['status'] === 'pending' ? 'bg-warning' : ($field['status'] === 'approved' ? 'bg-success' : 'bg-danger'); ?> status-badge">
                                        <?php echo htmlspecialchars($field['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm d-flex align-items-center gap-1 mt-1 action-btn" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $field['id']; ?>">
                                        <i class="bi bi-eye"></i> Xem chi tiết
                                    </button>
                                    <?php if ($field['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="approve_field" class="btn btn-primary btn-sm d-flex align-items-center gap-1 mt-1 action-btn">
                                                <i class="bi bi-check-circle"></i> Duyệt
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="reject_field" class="btn btn-danger btn-sm d-flex align-items-center gap-1 mt-1 action-btn">
                                                <i class="bi bi-x-circle"></i> Từ chối
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sân này? Hành động này không thể hoàn tác!');">
                                        <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="delete_field" class="btn btn-danger btn-sm d-flex align-items-center gap-1 mt-1 action-btn">
                                            <i class="bi bi-trash"></i> Xóa
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Modal -->
                            <div class="modal fade" id="detailModal<?php echo $field['id']; ?>" tabindex="-1" aria-labelledby="detailModalLabel<?php echo $field['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="detailModalLabel<?php echo $field['id']; ?>">Chi Tiết Sân: <?php echo htmlspecialchars($field['name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="field-info">
                                                <p><strong>Tên sân:</strong> <?php echo htmlspecialchars($field['name']); ?></p>
                                                <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($field['address']); ?></p>
                                                <p class="price"><strong>Giá mỗi giờ:</strong> <?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND'; ?></p>
                                                <p><strong>Giờ mở cửa:</strong> <?php echo htmlspecialchars($field['open_time']); ?></p>
                                                <p><strong>Giờ đóng cửa:</strong> <?php echo htmlspecialchars($field['close_time']); ?></p>
                                                <p><strong>Loại sân:</strong> <?php echo htmlspecialchars($field['field_type']); ?> người</p>
                                                <p><strong>Chủ sân:</strong> <?php echo htmlspecialchars($field['owner_name']); ?></p>
                                                <p><strong>Trạng thái:</strong> 
                                                    <span class="badge <?php echo $field['status'] === 'pending' ? 'bg-warning' : ($field['status'] === 'approved' ? 'bg-success' : 'bg-danger'); ?>">
                                                        <?php echo htmlspecialchars($field['status']); ?>
                                                    </span>
                                                </p>
                                                <p><strong>Ngày tạo:</strong> <?php echo htmlspecialchars($field['created_at']); ?></p>
                                            </div>
                                            <hr>
                                            <div class="field-images">
                                                <h6>Hình ảnh sân:</h6>
                                                <?php if (!empty($field_images[$field['id']])): ?>
                                                    <?php foreach ($field_images[$field['id']] as $image): ?>
                                                        <img src="assets/img/<?php echo htmlspecialchars($image['image']); ?>" alt="Hình ảnh sân">
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted">Không có hình ảnh.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary d-flex align-items-center gap-2 action-btn" data-bs-dismiss="modal">
                                                <i class="bi bi-x-circle"></i> Đóng
                                            </button>
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