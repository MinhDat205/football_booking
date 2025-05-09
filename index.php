<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

// Lấy danh sách sân nổi bật
$stmt = $pdo->query("SELECT * FROM fields WHERE status = 'approved' LIMIT 5");
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="banner bg-primary text-white text-center py-5">
    <div class="container">
        <h1>Đặt sân nhanh, chơi bóng vui!</h1>
        <p>Tìm và đặt sân bóng dễ dàng theo vị trí và thời gian của bạn.</p>
    </div>
</section>

<section class="search py-4">
    <div class="container">
        <form action="search.php" method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="location" class="form-control" placeholder="Nhập vị trí">
            </div>
            <div class="col-md-3">
                <input type="date" name="date" class="form-control">
            </div>
            <div class="col-md-3">
                <input type="time" name="time" class="form-control">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
            </div>
        </form>
    </div>
</section>

<section class="featured-fields py-5">
    <div class="container">
        <h2 class="text-center mb-4">Sân bóng nổi bật</h2>
        <div class="row">
            <?php foreach ($fields as $field): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <img src="assets/img/<?php echo $field['image'] ?: 'default.jpg'; ?>" class="card-img-top" alt="<?php echo $field['name']; ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $field['name']; ?></h5>
                            <p class="card-text"><?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND/giờ'; ?></p>
                            <a href="search.php?field_id=<?php echo $field['id']; ?>" class="btn btn-primary">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>