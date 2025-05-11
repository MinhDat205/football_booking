<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['status'] !== 'approved') {
    header('Location: profile.php');
    exit;
}

// Tính tổng doanh thu (chỉ tính booking đã hoàn thành - completed)
$total_revenue = 0;
$revenue_by_field = [];
$stmt = $pdo->prepare("SELECT f.id, f.name, SUM(b.total_price) as revenue 
                       FROM bookings b 
                       JOIN fields f ON b.field_id = f.id 
                       WHERE f.owner_id = ? AND b.status = 'completed' 
                       GROUP BY f.id, f.name");
$stmt->execute([$user_id]);
$revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($revenues as $rev) {
    $total_revenue += $rev['revenue'];
    $revenue_by_field[$rev['id']] = ['name' => $rev['name'], 'revenue' => $rev['revenue']];
}
?>

<style>
    /* Tiêu đề trang */
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.2rem; /* Giảm kích thước tiêu đề */
        margin-bottom: 1rem; /* Giảm khoảng cách dưới */
    }
    /* Card doanh thu */
    .revenue-card {
        border: 1px solid #e0e4e9; /* Thêm viền nhẹ */
        border-radius: 5px; /* Giảm độ bo góc */
    }
    .revenue-card .card-body {
        padding: 10px; /* Giảm padding */
    }
    .revenue-card .card-title {
        font-size: 1rem; /* Giảm kích thước tiêu đề */
        color: #e74c3c; /* Làm nổi bật tổng doanh thu */
        margin-bottom: 0.5rem;
    }
    .revenue-card .list-group-item {
        border-radius: 5px; /* Giảm độ bo góc */
        padding: 8px 10px; /* Giảm padding */
        font-size: 0.9rem; /* Giảm kích thước chữ */
    }
    .revenue-card .badge {
        background-color: #2a5298;
        font-size: 0.85rem; /* Giảm kích thước chữ */
        padding: 5px 10px; /* Giảm padding */
        border-radius: 5px;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1rem;
        }
        .revenue-card .card-body {
            padding: 8px;
        }
        .revenue-card .card-title {
            font-size: 0.9rem;
        }
        .revenue-card .list-group-item {
            font-size: 0.85rem;
        }
        .revenue-card .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
    }
</style>

<section class="revenue py-3">
    <div class="container">
        <h2 class="section-title text-center">Theo Dõi Thu Nhập</h2>
        <div class="card mb-3 revenue-card">
            <div class="card-body">
                <h5 class="card-title">Tổng Doanh Thu: <?php echo number_format($total_revenue, 0, ',', '.') . ' VND'; ?></h5>
                <?php if (empty($revenue_by_field)): ?>
                    <p class="text-muted">Chưa có doanh thu từ các sân bóng.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($revenue_by_field as $field_id => $data): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($data['name']); ?></span>
                                <span class="badge bg-success rounded-pill"><?php echo number_format($data['revenue'], 0, ',', '.') . ' VND'; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>