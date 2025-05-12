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

// Lấy danh sách sân bóng của chủ sân
$stmt = $pdo->prepare("SELECT id, name FROM fields WHERE owner_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý bộ lọc thời gian và sân bóng
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // Mặc định là 'all'
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // Mặc định là ngày hiện tại
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // Mặc định là tháng hiện tại
$year_filter = isset($_GET['year']) ? $_GET['year'] : date('Y'); // Mặc định là năm hiện tại
$field_id = isset($_GET['field_id']) ? $_GET['field_id'] : 'all'; // Mặc định là 'all' (tất cả sân bóng)

// Tính tổng doanh thu và doanh thu theo sân bóng
$total_revenue = 0;
$revenue_by_field = [];
$chart_labels = [];
$chart_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['apply_filter'])) {
    // Điều kiện WHERE cho sân bóng
    $field_condition = $field_id !== 'all' ? ' AND r.field_id = ?' : '';
    $field_params = $field_id !== 'all' ? [$field_id] : [];

    if ($filter === 'day') {
        // Theo ngày
        $start_of_day = $date_filter . ' 00:00:00';
        $end_of_day = $date_filter . ' 23:59:59';

        $stmt = $pdo->prepare("SELECT f.id, f.name, SUM(r.amount) as revenue 
                               FROM revenues r 
                               JOIN fields f ON r.field_id = f.id 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY f.id, f.name");
        $stmt->execute(array_merge([$user_id, $start_of_day, $end_of_day], $field_params));
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dữ liệu biểu đồ: Theo giờ trong ngày
        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = 0;
        }
        $stmt = $pdo->prepare("SELECT HOUR(r.created_at) as hour, SUM(r.amount) as revenue 
                               FROM revenues r 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY HOUR(r.created_at)");
        $stmt->execute(array_merge([$user_id, $start_of_day, $end_of_day], $field_params));
        $hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($hourly_data as $data) {
            $hours[$data['hour']] = $data['revenue'];
        }
        $chart_labels = array_keys($hours);
        $chart_data = array_values($hours);

    } elseif ($filter === 'week') {
        // Theo tuần
        $start_of_week = date('Y-m-d 00:00:00', strtotime($date_filter . ' -' . date('w', strtotime($date_filter)) . ' days'));
        $end_of_week = date('Y-m-d 23:59:59', strtotime($start_of_week . ' +6 days'));

        $stmt = $pdo->prepare("SELECT f.id, f.name, SUM(r.amount) as revenue 
                               FROM revenues r 
                               JOIN fields f ON r.field_id = f.id 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY f.id, f.name");
        $stmt->execute(array_merge([$user_id, $start_of_week, $end_of_week], $field_params));
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dữ liệu biểu đồ: Theo ngày trong tuần
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = date('Y-m-d', strtotime($start_of_week . " +$i days"));
            $days[$day] = 0;
        }
        $stmt = $pdo->prepare("SELECT DATE(r.created_at) as day, SUM(r.amount) as revenue 
                               FROM revenues r 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY DATE(r.created_at)");
        $stmt->execute(array_merge([$user_id, $start_of_week, $end_of_week], $field_params));
        $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($daily_data as $data) {
            $days[$data['day']] = $data['revenue'];
        }
        $chart_labels = array_map(function($date) {
            return date('d/m', strtotime($date));
        }, array_keys($days));
        $chart_data = array_values($days);

    } elseif ($filter === 'month') {
        // Theo tháng
        $start_of_month = $month_filter . '-01 00:00:00';
        $end_of_month = date('Y-m-t 23:59:59', strtotime($start_of_month));

        $stmt = $pdo->prepare("SELECT f.id, f.name, SUM(r.amount) as revenue 
                               FROM revenues r 
                               JOIN fields f ON r.field_id = f.id 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY f.id, f.name");
        $stmt->execute(array_merge([$user_id, $start_of_month, $end_of_month], $field_params));
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dữ liệu biểu đồ: Theo ngày trong tháng
        $days_in_month = date('t', strtotime($start_of_month));
        $days = [];
        for ($i = 1; $i <= $days_in_month; $i++) {
            $day = $month_filter . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $days[$day] = 0;
        }
        $stmt = $pdo->prepare("SELECT DATE(r.created_at) as day, SUM(r.amount) as revenue 
                               FROM revenues r 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY DATE(r.created_at)");
        $stmt->execute(array_merge([$user_id, $start_of_month, $end_of_month], $field_params));
        $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($daily_data as $data) {
            $days[$data['day']] = $data['revenue'];
        }
        $chart_labels = array_map(function($date) {
            return date('d', strtotime($date));
        }, array_keys($days));
        $chart_data = array_values($days);

    } elseif ($filter === 'year') {
        // Theo năm
        $start_of_year = $year_filter . '-01-01 00:00:00';
        $end_of_year = $year_filter . '-12-31 23:59:59';

        $stmt = $pdo->prepare("SELECT f.id, f.name, SUM(r.amount) as revenue 
                               FROM revenues r 
                               JOIN fields f ON r.field_id = f.id 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY f.id, f.name");
        $stmt->execute(array_merge([$user_id, $start_of_year, $end_of_year], $field_params));
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dữ liệu biểu đồ: Theo tháng trong năm
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $month = str_pad($i, 2, '0', STR_PAD_LEFT);
            $months[$month] = 0;
        }
        $stmt = $pdo->prepare("SELECT MONTH(r.created_at) as month, SUM(r.amount) as revenue 
                               FROM revenues r 
                               WHERE r.owner_id = ? AND r.created_at BETWEEN ? AND ? $field_condition
                               GROUP BY MONTH(r.created_at)");
        $stmt->execute(array_merge([$user_id, $start_of_year, $end_of_year], $field_params));
        $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($monthly_data as $data) {
            $month_key = str_pad($data['month'], 2, '0', STR_PAD_LEFT);
            $months[$month_key] = $data['revenue'];
        }
        $chart_labels = array_keys($months);
        $chart_data = array_values($months);

    } else {
        // Toàn bộ thời gian
        $stmt = $pdo->prepare("SELECT f.id, f.name, SUM(r.amount) as revenue 
                               FROM revenues r 
                               JOIN fields f ON r.field_id = f.id 
                               WHERE r.owner_id = ? $field_condition
                               GROUP BY f.id, f.name");
        $stmt->execute(array_merge([$user_id], $field_params));
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dữ liệu biểu đồ: Theo năm
        $stmt = $pdo->prepare("SELECT YEAR(r.created_at) as year, SUM(r.amount) as revenue 
                               FROM revenues r 
                               WHERE r.owner_id = ? $field_condition
                               GROUP BY YEAR(r.created_at) 
                               ORDER BY year");
        $stmt->execute(array_merge([$user_id], $field_params));
        $yearly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $chart_labels = array_column($yearly_data, 'year');
        $chart_data = array_column($yearly_data, 'revenue');
    }

    $total_revenue = 0;
    $revenue_by_field = [];
    if (!empty($revenues)) {
        foreach ($revenues as $rev) {
            $total_revenue += $rev['revenue'];
            $revenue_by_field[$rev['id']] = ['name' => $rev['name'], 'revenue' => $rev['revenue']];
        }
    }
}
?>

<style>
    /* Tiêu đề trang */
    .section-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        text-align: center;
    }
    /* Form lọc */
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .filter-form label {
        display: block;
        font-weight: 500;
        margin-bottom: 4px;
    }
    .filter-form div {
        display: flex;
        flex-direction: column;
        margin-bottom: 1rem;
        min-width: 150px;
    }
    .filter-form select,
    .filter-form input {
        padding: 8px;
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 0.95rem;
    }
    .filter-form button {
        padding: 8px 15px;
        background-color: #2a5298;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 0.95rem;
        align-self: flex-end;
    }
    .filter-form button:hover {
        background-color: #1e3c72;
    }
    /* Card doanh thu */
    .revenue-card {
        border: 1px solid #e0e4e9;
        border-radius: 5px;
        margin-bottom: 1.5rem;
    }
    .revenue-card .card-body {
        padding: 10px;
    }
    .revenue-card .card-title {
        font-size: 1rem;
        color: #e74c3c;
        margin-bottom: 0.5rem;
    }
    .revenue-card .list-group-item {
        border-radius: 5px;
        padding: 8px 10px;
        font-size: 0.9rem;
    }
    .revenue-card .badge {
        background-color: #2a5298;
        font-size: 0.85rem;
        padding: 5px 10px;
        border-radius: 5px;
    }
    /* Biểu đồ */
    .chart-container {
        max-width: 800px;
        margin: 0 auto;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1rem;
        }
        .filter-form {
            gap: 0.5rem;
        }
        .filter-form select,
        .filter-form input,
        .filter-form button {
            width: 100%;
            font-size: 0.9rem;
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
        .chart-container {
            max-width: 100%;
        }
    }
</style>

<section class="revenue py-3">
    <div class="container">
        <h2 class="section-title text-center">Theo Dõi Thu Nhập</h2>

        <!-- Form lọc -->
        <form class="filter-form flex-wrap" method="GET">
            <div>
                <label for="filter">Thống kê theo:</label>
                <select name="filter" id="filter">
                    <option value="all" <?= $filter === 'all' ? 'selected' : ''; ?>>Toàn bộ</option>
                    <option value="day" <?= $filter === 'day' ? 'selected' : ''; ?>>Theo ngày</option>
                    <option value="week" <?= $filter === 'week' ? 'selected' : ''; ?>>Theo tuần</option>
                    <option value="month" <?= $filter === 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                    <option value="year" <?= $filter === 'year' ? 'selected' : ''; ?>>Theo năm</option>
                </select>
            </div>

            <div>
                <label for="field_id">Chọn sân bóng:</label>
                <select name="field_id" id="field_id">
                    <option value="all" <?= $field_id === 'all' ? 'selected' : ''; ?>>Tất cả sân bóng</option>
                    <?php foreach ($fields as $field): ?>
                        <option value="<?= $field['id']; ?>" <?= $field_id == $field['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($field['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($filter === 'day' || $filter === 'week'): ?>
                <div>
                    <label for="date">Chọn ngày:</label>
                    <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter); ?>">
                </div>
            <?php elseif ($filter === 'month'): ?>
                <div>
                    <label for="month">Chọn tháng:</label>
                    <input type="month" id="month" name="month" value="<?= htmlspecialchars($month_filter); ?>">
                </div>
            <?php elseif ($filter === 'year'): ?>
                <div>
                    <label for="year">Chọn năm:</label>
                    <input type="number" id="year" name="year" min="2000" max="<?= date('Y'); ?>" value="<?= htmlspecialchars($year_filter); ?>">
                </div>
            <?php endif; ?>
            <button type="submit" name="apply_filter">Lọc</button>
        </form>

        <!-- Tổng doanh thu và doanh thu theo sân -->
        <div class="card mb-3 revenue-card">
            <div class="card-body">
                <h5 class="card-title">Tổng Doanh Thu: <?php echo number_format($total_revenue, 0, ',', '.') . ' VND'; ?></h5>
                <?php if (empty($revenue_by_field)): ?>
                    <p class="text-muted">Chưa có doanh thu từ các sân bóng. Vui lòng hoàn thành một số đặt sân trong manage_bookings.php để ghi nhận doanh thu.</p>
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

        <!-- Biểu đồ -->
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</section>

<!-- Tích hợp Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Thu nhập (VND)',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.2)', // Xanh lá nhạt
                borderColor: 'rgba(40, 167, 69, 1)', // Xanh lá đậm
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Thu nhập (VND)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '<?php echo $filter === "day" ? "Giờ" : ($filter === "week" ? "Ngày" : ($filter === "month" ? "Ngày" : ($filter === "year" ? "Tháng" : "Năm"))); ?>'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Thu nhập: ' + context.formattedValue.replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' VND';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>