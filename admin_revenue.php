<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Lấy danh sách chủ sân
$stmt = $pdo->prepare("SELECT id, full_name, email, phone FROM users WHERE account_type = 'owner' AND status = 'approved' ORDER BY full_name");
$stmt->execute();
$owners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nếu admin chọn 1 chủ sân để xem chi tiết
$selected_owner_id = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$fields = [];
$revenue_by_field = [];
$total_revenue = 0;
$owner_info = null;

if ($selected_owner_id) {
    // Lấy thông tin chủ sân
    foreach ($owners as $o) {
        if ($o['id'] == $selected_owner_id) {
            $owner_info = $o;
            break;
        }
    }
    // Lấy danh sách sân bóng của chủ sân này
    $stmt = $pdo->prepare("SELECT id, name FROM fields WHERE owner_id = ? ORDER BY name");
    $stmt->execute([$selected_owner_id]);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Lấy doanh thu từng sân
    $stmt = $pdo->prepare("SELECT field_id, SUM(amount) as revenue FROM revenues WHERE owner_id = ? GROUP BY field_id");
    $stmt->execute([$selected_owner_id]);
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($revenue_data as $r) {
        $revenue_by_field[$r['field_id']] = $r['revenue'];
        $total_revenue += $r['revenue'];
    }
}
?>

<style>
.section-title {
    font-weight: 600;
    color: #1e3c72;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    text-align: center;
}
.admin-table thead th {
    font-size: 1rem;
    padding: 12px;
    background: #2a5298;
    color: #fff;
}
.admin-table td {
    padding: 12px;
    font-size: 0.95rem;
}
.admin-table .btn {
    padding: 6px 15px;
    font-size: 0.95rem;
}
</style>

<section class="admin py-3">
    <div class="container">
        <h2 class="section-title">Thống Kê Doanh Thu Chủ Sân</h2>
        <?php if (!$selected_owner_id): ?>
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Họ tên chủ sân</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Tổng doanh thu</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($owners as $owner): ?>
                            <?php
                            // Tính tổng doanh thu của chủ sân này
                            $stmt = $pdo->prepare("SELECT SUM(amount) FROM revenues WHERE owner_id = ?");
                            $stmt->execute([$owner['id']]);
                            $sum = $stmt->fetchColumn();
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($owner['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($owner['email']); ?></td>
                                <td><?php echo htmlspecialchars($owner['phone']); ?></td>
                                <td><?php echo number_format($sum, 0, ',', '.'); ?> VND</td>
                                <td><a href="admin_revenue.php?owner_id=<?php echo $owner['id']; ?>" class="btn btn-primary btn-sm">Xem chi tiết</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <a href="admin_users.php" class="btn btn-secondary mb-3">&larr; Quay lại danh sách</a>
            <h4>Chủ sân: <?php echo htmlspecialchars($owner_info['full_name']); ?> (<?php echo htmlspecialchars($owner_info['email']); ?>)</h4>
            <h5>Tổng doanh thu: <span style="color:#28a745;"><?php echo number_format($total_revenue, 0, ',', '.'); ?> VND</span></h5>
            <div class="table-responsive mt-3">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Tên sân bóng</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($field['name']); ?></td>
                                <td><?php echo number_format(isset($revenue_by_field[$field['id']]) ? $revenue_by_field[$field['id']] : 0, 0, ',', '.'); ?> VND</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?> 