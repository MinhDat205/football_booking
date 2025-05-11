<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/csrf.php';

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

$error = '';
$success = '';
$csrf_token = generateCsrfToken();

// Lấy danh sách sân của chủ sân
$stmt = $pdo->prepare("SELECT * FROM fields WHERE owner_id = ?");
$stmt->execute([$user_id]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách sản phẩm của chủ sân
$products = [];
foreach ($fields as $field) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE field_id = ?");
    $stmt->execute([$field['id']]);
    $field_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $products[$field['id']] = $field_products;
}

// Xử lý thêm sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $field_id = (int)$_POST['field_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $product_image = '';

        // Kiểm tra dữ liệu
        if (empty($name) || $price <= 0) {
            $error = 'Vui lòng điền đầy đủ thông tin sản phẩm.';
        } elseif (strlen($name) > 255) {
            $error = 'Tên sản phẩm không được vượt quá 255 ký tự.';
        } elseif (strlen($description) > 1000) {
            $error = 'Mô tả sản phẩm không được vượt quá 1000 ký tự.';
        } else {
            // Xử lý tải ảnh sản phẩm
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['product_image']['type'], $allowed_types)) {
                    $error = 'Chỉ hỗ trợ định dạng ảnh JPEG, PNG, GIF.';
                } elseif ($_FILES['product_image']['size'] > $max_size) {
                    $error = 'Kích thước ảnh không được vượt quá 5MB.';
                } else {
                    $image_name = 'product_' . $user_id . '_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                    $upload_path = 'assets/img/' . $image_name;

                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                        $product_image = $image_name;
                    } else {
                        $error = 'Không thể tải ảnh lên. Vui lòng thử lại.';
                    }
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare("INSERT INTO products (field_id, name, description, price, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$field_id, $name, $description, $price, $product_image]);
                $success = 'Thêm sản phẩm thành công!';
                header('Location: manage_products.php');
                exit;
            }
        }
    }
}

// Xử lý chỉnh sửa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $product_id = (int)$_POST['product_id'];
        $field_id = (int)$_POST['field_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];

        // Lấy thông tin sản phẩm hiện tại
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ? AND field_id IN (SELECT id FROM fields WHERE owner_id = ?)");
        $stmt->execute([$product_id, $user_id]);
        $current_product = $stmt->fetch(PDO::FETCH_ASSOC);
        $product_image = $current_product['image'];

        // Kiểm tra dữ liệu
        if (empty($name) || $price <= 0) {
            $error = 'Vui lòng điền đầy đủ thông tin sản phẩm.';
        } elseif (strlen($name) > 255) {
            $error = 'Tên sản phẩm không được vượt quá 255 ký tự.';
        } elseif (strlen($description) > 1000) {
            $error = 'Mô tả sản phẩm không được vượt quá 1000 ký tự.';
        } else {
            // Xử lý tải ảnh mới (nếu có)
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['product_image']['type'], $allowed_types)) {
                    $error = 'Chỉ hỗ trợ định dạng ảnh JPEG, PNG, GIF.';
                } elseif ($_FILES['product_image']['size'] > $max_size) {
                    $error = 'Kích thước ảnh không được vượt quá 5MB.';
                } else {
                    $image_name = 'product_' . $user_id . '_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                    $upload_path = 'assets/img/' . $image_name;

                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                        // Xóa ảnh cũ nếu có
                        if ($product_image && file_exists('assets/img/' . $product_image)) {
                            unlink('assets/img/' . $product_image);
                        }
                        $product_image = $image_name;
                    } else {
                        $error = 'Không thể tải ảnh lên. Vui lòng thử lại.';
                    }
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, image = ? WHERE id = ? AND field_id = ?");
                $stmt->execute([$name, $description, $price, $product_image, $product_id, $field_id]);
                $success = 'Cập nhật sản phẩm thành công!';
                header('Location: manage_products.php');
                exit;
            }
        }
    }
}

// Xử lý xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $product_id = (int)$_POST['product_id'];

        // Lấy thông tin sản phẩm để xóa ảnh
        $stmt = $pdo->prepare("SELECT p.image 
                               FROM products p 
                               JOIN fields f ON p.field_id = f.id 
                               WHERE p.id = ? AND f.owner_id = ?");
        $stmt->execute([$product_id, $user_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        // Xóa ảnh nếu có
        if ($product['image'] && file_exists('assets/img/' . $product['image'])) {
            unlink('assets/img/' . $product['image']);
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $success = 'Xóa sản phẩm thành công!';
        header('Location: manage_products.php');
        exit;
    }
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
    /* Sub-title */
    .sub-title {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1rem; /* Giảm kích thước tiêu đề phụ */
        margin-bottom: 0.5rem;
    }
    /* Form */
    .manage-form {
        background-color: #fff;
        padding: 10px; /* Giảm padding */
        border-radius: 5px; /* Giảm độ bo góc */
    }
    .manage-form .form-control,
    .manage-form .form-select,
    .manage-form textarea {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 0.9rem;
    }
    .manage-form .form-control:focus,
    .manage-form .form-select:focus,
    .manage-form textarea:focus {
        border-color: #2a5298;
    }
    .manage-form .input-group-text {
        border-radius: 5px 0 0 5px;
    }
    .manage-form .btn-primary {
        padding: 6px 15px; /* Giảm kích thước nút */
        font-size: 0.9rem;
    }
    /* Bảng */
    .manage-table thead th {
        font-size: 0.9rem; /* Giảm kích thước tiêu đề cột */
        padding: 10px; /* Giảm padding */
    }
    .manage-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .manage-table td {
        padding: 10px; /* Giảm padding */
    }
    .manage-table .price {
        color: #e74c3c;
        font-weight: 600;
    }
    .manage-table .btn {
        padding: 6px 12px; /* Giảm kích thước nút */
        font-size: 0.85rem;
    }
    /* Modal */
    .modal-body {
        padding: 15px; /* Giảm padding */
    }
    .modal-title {
        font-size: 1.5rem; /* Giảm kích thước tiêu đề modal */
        margin-bottom: 0.5rem;
    }
    .modal .form-label {
        font-weight: 500;
        color: #444;
        font-size: 0.9rem;
    }
    .modal .form-control,
    .modal textarea {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 0.9rem;
    }
    .modal .form-control:focus,
    .modal textarea:focus {
        border-color: #2a5298;
    }
    .modal .input-group-text {
        border-radius: 5px 0 0 5px;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .section-title {
            font-size: 1rem;
        }
        .sub-title {
            font-size: 0.9rem;
        }
        .manage-form {
            padding: 8px;
        }
        .manage-form .form-control,
        .manage-form .form-select,
        .manage-form textarea,
        .manage-form .btn-primary {
            font-size: 0.85rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .manage-table thead th,
        .manage-table td {
            padding: 8px;
            font-size: 0.8rem;
        }
        .manage-table .btn {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        .modal-body {
            padding: 10px;
        }
        .modal-title {
            font-size: 1.2rem;
        }
    }
</style>

<section class="manage-products py-3">
    <div class="container">
        <h2 class="section-title text-center">Quản Lý Sản Phẩm</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php foreach ($fields as $field): ?>
            <h5 class="sub-title"><?php echo htmlspecialchars($field['name']); ?></h5>
            <!-- Form thêm sản phẩm -->
            <form method="POST" class="row g-3 mb-3 manage-form" enctype="multipart/form-data">
                <div class="col-md-3 col-sm-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-box"></i></span>
                        <input type="text" name="name" class="form-control" placeholder="Tên sản phẩm" required>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-textarea-resize"></i></span>
                        <textarea name="description" class="form-control" placeholder="Mô tả sản phẩm" rows="1"></textarea>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                        <input type="number" name="price" class="form-control" placeholder="Giá (VND)" required>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <input type="file" name="product_image" class="form-control">
                </div>
                <div class="col-md-2 col-sm-6 d-flex align-items-end">
                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" name="add_product" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-plus-circle-fill"></i> Thêm sản phẩm
                    </button>
                </div>
            </form>

            <!-- Danh sách sản phẩm -->
            <?php if (empty($products[$field['id']])): ?>
                <p class="text-muted mb-3">Chưa có sản phẩm nào cho sân này.</p>
            <?php else: ?>
                <div class="table-responsive mb-3">
                    <table class="table manage-table">
                        <thead>
                            <tr>
                                <th>Tên sản phẩm</th>
                                <th>Mô tả</th>
                                <th>Giá</th>
                                <th>Ảnh</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products[$field['id']] as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['description']); ?></td>
                                    <td class="price"><?php echo number_format($product['price'], 0, ',', '.') . ' VND'; ?></td>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="assets/img/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="max-width: 50px; border-radius: 5px;">
                                        <?php else: ?>
                                            <span class="text-muted">Không có ảnh</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editProductModal<?php echo $product['id']; ?>">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" name="delete_product" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
                                                    <i class="bi bi-trash"></i> Xóa
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal chỉnh sửa sản phẩm -->
                                <div class="modal fade" id="editProductModal<?php echo $product['id']; ?>" tabindex="-1" aria-labelledby="editProductModalLabel<?php echo $product['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editProductModalLabel<?php echo $product['id']; ?>">Chỉnh Sửa Sản Phẩm</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="name" class="form-label">Tên sản phẩm</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="bi bi-box"></i></span>
                                                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Mô tả</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="bi bi-textarea-resize"></i></span>
                                                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($product['description']); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="price" class="form-label">Giá (VND)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                                            <input type="number" name="price" class="form-control" value="<?php echo $product['price']; ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="product_image" class="form-label">Ảnh sản phẩm (JPEG, PNG, GIF, tối đa 5MB)</label>
                                                        <input type="file" name="product_image" id="product_image" class="form-control">
                                                        <?php if ($product['image']): ?>
                                                            <p class="mt-2">Hiện tại: <img src="assets/img/<?php echo htmlspecialchars($product['image']); ?>" alt="Ảnh sản phẩm" style="max-width: 100px; border-radius: 5px;"></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary d-flex align-items-center gap-2" data-bs-dismiss="modal">
                                                        <i class="bi bi-x-circle"></i> Đóng
                                                    </button>
                                                    <button type="submit" name="edit_product" class="btn btn-primary d-flex align-items-center gap-2">
                                                        <i class="bi bi-save-fill"></i> Lưu thay đổi
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>