<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

// Kiểm tra nếu người dùng không phải chủ sân hoặc không được phê duyệt, chuyển hướng trước khi xuất dữ liệu
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

$csrf_token = generateCsrfToken();

// Xử lý thêm sân, chỉnh sửa sân, xóa sân trước khi xuất bất kỳ dữ liệu nào
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $error = '';
    $success = '';

    if (!verifyCsrfToken($token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        // Xử lý thêm sân
        if (isset($_POST['add_field'])) {
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);
            $price_per_hour = (float)$_POST['price_per_hour'];
            $open_time = $_POST['open_time'];
            $close_time = $_POST['close_time'];
            $field_type = $_POST['field_type'];

            // Kiểm tra dữ liệu
            if (empty($name) || empty($address) || $price_per_hour <= 0 || empty($open_time) || empty($close_time) || empty($field_type)) {
                $error = 'Vui lòng điền đầy đủ thông tin sân.';
            } elseif (strlen($name) > 255) {
                $error = 'Tên sân không được vượt quá 255 ký tự.';
            } elseif (strlen($address) > 255) {
                $error = 'Địa chỉ sân không được vượt quá 255 ký tự.';
            } elseif (!preg_match('/^\d{2}:\d{2}$/', $open_time) || !preg_match('/^\d{2}:\d{2}$/', $close_time)) {
                $error = 'Giờ mở/đóng cửa không hợp lệ.';
            } elseif (strtotime($close_time) <= strtotime($open_time)) {
                $error = 'Giờ đóng cửa phải sau giờ mở cửa.';
            } elseif (!in_array($field_type, ['5', '7', '9', '11'])) {
                $error = 'Loại sân không hợp lệ.';
            } else {
                // Lưu thông tin sân vào bảng fields
                $stmt = $pdo->prepare("INSERT INTO fields (owner_id, name, address, price_per_hour, open_time, close_time, field_type, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $name, $address, $price_per_hour, $open_time, $close_time, $field_type]);
                $field_id = $pdo->lastInsertId();

                // Xử lý tải lên nhiều hình ảnh
                if (isset($_FILES['field_images']) && !empty($_FILES['field_images']['name'][0])) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    foreach ($_FILES['field_images']['name'] as $key => $image_name) {
                        if ($_FILES['field_images']['error'][$key] === UPLOAD_ERR_OK) {
                            if (!in_array($_FILES['field_images']['type'][$key], $allowed_types)) {
                                $error = 'Chỉ hỗ trợ định dạng ảnh JPEG, PNG, GIF.';
                                break;
                            } elseif ($_FILES['field_images']['size'][$key] > $max_size) {
                                $error = 'Kích thước ảnh không được vượt quá 5MB.';
                                break;
                            } else {
                                $image_name = 'field_' . $field_id . '_' . time() . '_' . $key . '.' . pathinfo($_FILES['field_images']['name'][$key], PATHINFO_EXTENSION);
                                $upload_path = 'assets/img/' . $image_name;

                                if (move_uploaded_file($_FILES['field_images']['tmp_name'][$key], $upload_path)) {
                                    $stmt = $pdo->prepare("INSERT INTO field_images (field_id, image) VALUES (?, ?)");
                                    $stmt->execute([$field_id, $image_name]);
                                } else {
                                    $error = 'Không thể tải ảnh lên. Vui lòng thử lại.';
                                    break;
                                }
                            }
                        }
                    }
                }

                if (!$error) {
                    $success = 'Thêm sân thành công! Đang chờ admin phê duyệt.';
                }
            }
        }

        // Xử lý chỉnh sửa sân
        if (isset($_POST['edit_field'])) {
            $field_id = (int)$_POST['field_id'];
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);
            $price_per_hour = (float)$_POST['price_per_hour'];
            $open_time = $_POST['open_time'];
            $close_time = $_POST['close_time'];
            $field_type = $_POST['field_type'];

            // Kiểm tra dữ liệu
            if (empty($name) || empty($address) || $price_per_hour <= 0 || empty($open_time) || empty($close_time) || empty($field_type)) {
                $error = 'Vui lòng điền đầy đủ thông tin sân.';
            } elseif (strlen($name) > 255) {
                $error = 'Tên sân không được vượt quá 255 ký tự.';
            } elseif (strlen($address) > 255) {
                $error = 'Địa chỉ sân không được vượt quá 255 ký tự.';
            } elseif (!preg_match('/^\d{2}:\d{2}$/', $open_time) || !preg_match('/^\d{2}:\d{2}$/', $close_time)) {
                $error = 'Giờ mở/đóng cửa không hợp lệ.';
            } elseif (strtotime($close_time) <= strtotime($open_time)) {
                $error = 'Giờ đóng cửa phải sau giờ mở cửa.';
            } elseif (!in_array($field_type, ['5', '7', '9', '11'])) {
                $error = 'Loại sân không hợp lệ.';
            } else {
                // Cập nhật thông tin sân
                $stmt = $pdo->prepare("UPDATE fields SET name = ?, address = ?, price_per_hour = ?, open_time = ?, close_time = ?, field_type = ? WHERE id = ? AND owner_id = ?");
                $stmt->execute([$name, $address, $price_per_hour, $open_time, $close_time, $field_type, $field_id, $user_id]);

                // Xử lý tải lên hình ảnh mới (nếu có)
                if (isset($_FILES['field_images']) && !empty($_FILES['field_images']['name'][0])) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    foreach ($_FILES['field_images']['name'] as $key => $image_name) {
                        if ($_FILES['field_images']['error'][$key] === UPLOAD_ERR_OK) {
                            if (!in_array($_FILES['field_images']['type'][$key], $allowed_types)) {
                                $error = 'Chỉ hỗ trợ định dạng ảnh JPEG, PNG, GIF.';
                                break;
                            } elseif ($_FILES['field_images']['size'][$key] > $max_size) {
                                $error = 'Kích thước ảnh không được vượt quá 5MB.';
                                break;
                            } else {
                                $image_name = 'field_' . $field_id . '_' . time() . '_' . $key . '.' . pathinfo($_FILES['field_images']['name'][$key], PATHINFO_EXTENSION);
                                $upload_path = 'assets/img/' . $image_name;

                                if (move_uploaded_file($_FILES['field_images']['tmp_name'][$key], $upload_path)) {
                                    $stmt = $pdo->prepare("INSERT INTO field_images (field_id, image) VALUES (?, ?)");
                                    $stmt->execute([$field_id, $image_name]);
                                } else {
                                    $error = 'Không thể tải ảnh lên. Vui lòng thử lại.';
                                    break;
                                }
                            }
                        }
                    }
                }

                if (!$error) {
                    $success = 'Cập nhật sân thành công!';
                }
            }
        }

        // Xử lý xóa hình ảnh
        if (isset($_POST['delete_image'])) {
            $image_id = (int)$_POST['image_id'];
            $field_id = (int)$_POST['field_id'];

            // Kiểm tra xem hình ảnh có thuộc về sân của chủ sân không
            $stmt = $pdo->prepare("SELECT fi.image FROM field_images fi 
                                   JOIN fields f ON fi.field_id = f.id 
                                   WHERE fi.id = ? AND fi.field_id = ? AND f.owner_id = ?");
            $stmt->execute([$image_id, $field_id, $user_id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                // Xóa file hình ảnh
                if (file_exists('assets/img/' . $image['image'])) {
                    unlink('assets/img/' . $image['image']);
                }
                // Xóa bản ghi trong bảng field_images
                $stmt = $pdo->prepare("DELETE FROM field_images WHERE id = ?");
                $stmt->execute([$image_id]);
                $success = 'Xóa hình ảnh thành công!';
            } else {
                $error = 'Hình ảnh không hợp lệ.';
            }
        }

        // Xử lý xóa sân
        if (isset($_POST['delete_field'])) {
            $field_id = (int)$_POST['field_id'];

            // Xóa sân (hình ảnh sẽ tự động bị xóa nhờ ON DELETE CASCADE)
            $stmt = $pdo->prepare("DELETE FROM fields WHERE id = ? AND owner_id = ?");
            $stmt->execute([$field_id, $user_id]);
            $success = 'Xóa sân thành công!';
        }
    }

    // Lưu thông báo vào session và chuyển hướng
    if ($error) {
        $_SESSION['error'] = $error;
    } elseif ($success) {
        $_SESSION['success'] = $success;
    }
    header('Location: manage_field.php');
    exit;
}

// Lấy danh sách sân của chủ sân
$stmt = $pdo->prepare("SELECT * FROM fields WHERE owner_id = ?");
$stmt->execute([$user_id]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách hình ảnh cho từng sân
$field_images = [];
foreach ($fields as $field) {
    $stmt = $pdo->prepare("SELECT * FROM field_images WHERE field_id = ?");
    $stmt->execute([$field['id']]);
    $field_images[$field['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
    .manage-form .form-select {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 0.9rem;
    }
    .manage-form .form-control:focus,
    .manage-form .form-select:focus {
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
    .modal .form-control {
        border-radius: 5px;
        border: 1px solid #e0e4e9;
        font-size: 0.9rem;
    }
    .modal .form-control:focus {
        border-color: #2a5298;
    }
    .modal .input-group-text {
        border-radius: 5px 0 0 5px;
    }
    /* Hình ảnh trong modal và preview */
    .image-preview img {
        max-width: 100px;
        margin: 5px;
        border-radius: 5px;
    }
    .preview-container img {
        max-width: 100px;
        margin: 5px;
        border-radius: 5px;
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
        .image-preview img,
        .preview-container img {
            max-width: 80px;
        }
    }
</style>

<section class="manage-field py-3">
    <div class="container">
        <h2 class="section-title text-center">Quản Lý Sân</h2>
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
        
        <!-- Form thêm sân mới -->
        <h5 class="sub-title">Thêm Sân Mới</h5>
        <form method="POST" class="row g-3 mb-3 manage-form" enctype="multipart/form-data">
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                    <input type="text" name="name" class="form-control" placeholder="Tên sân" required>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                    <input type="text" name="address" class="form-control" placeholder="Địa chỉ" required>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                    <input type="number" name="price_per_hour" class="form-control" placeholder="Giá/giờ (VND)" required>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                    <input type="time" name="open_time" class="form-control" required>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                    <input type="time" name="close_time" class="form-control" required>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-people"></i></span>
                    <select name="field_type" class="form-select" required>
                        <option value="" disabled selected>Loại sân</option>
                        <option value="5">Sân 5 người</option>
                        <option value="7">Sân 7 người</option>
                        <option value="9">Sân 9 người</option>
                        <option value="11">Sân 11 người</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <label for="field_images" class="form-label">Hình ảnh sân (JPEG, PNG, GIF, tối đa 5MB - Có thể chọn nhiều ảnh)</label>
                <input type="file" name="field_images[]" id="field_images_add" class="form-control" multiple>
                <div id="preview_add" class="preview-container mt-2"></div>
            </div>
            <div class="col-md-2 col-sm-6 d-flex align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" name="add_field" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-plus-circle-fill"></i> Thêm sân
                </button>
            </div>
        </form>

        <!-- Danh sách sân -->
        <h5 class="sub-title">Danh Sách Sân</h5>
        <?php if (empty($fields)): ?>
            <p class="text-muted mb-3">Chưa có sân bóng nào.</p>
        <?php else: ?>
            <div class="table-responsive mb-3">
                <table class="table manage-table">
                    <thead>
                        <tr>
                            <th>Tên sân</th>
                            <th>Địa chỉ</th>
                            <th>Giá/giờ</th>
                            <th>Giờ mở cửa</th>
                            <th>Giờ đóng cửa</th>
                            <th>Loại sân</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($field['name']); ?></td>
                                <td><?php echo htmlspecialchars($field['address']); ?></td>
                                <td class="price"><?php echo number_format($field['price_per_hour'], 0, ',', '.') . ' VND'; ?></td>
                                <td><?php echo htmlspecialchars($field['open_time']); ?></td>
                                <td><?php echo htmlspecialchars($field['close_time']); ?></td>
                                <td><?php echo htmlspecialchars($field['field_type']); ?> người</td>
                                <td>
                                    <span class="badge <?php echo $field['status'] === 'approved' ? 'bg-success' : ($field['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo htmlspecialchars($field['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $field['id']; ?>">
                                            <i class="bi bi-pencil"></i> Sửa
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sân này?');">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" name="delete_field" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-trash"></i> Xóa
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal chỉnh sửa sân -->
                            <div class="modal fade" id="editModal<?php echo $field['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $field['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $field['id']; ?>">Chỉnh Sửa Sân</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Tên sân</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-building"></i></span>
                                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($field['name']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="address" class="form-label">Địa chỉ</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                                                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($field['address']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="price_per_hour" class="form-label">Giá/giờ (VND)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                                        <input type="number" name="price_per_hour" class="form-control" value="<?php echo $field['price_per_hour']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="open_time" class="form-label">Giờ mở cửa</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                                        <input type="time" name="open_time" class="form-control" value="<?php echo htmlspecialchars($field['open_time']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="close_time" class="form-label">Giờ đóng cửa</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                                        <input type="time" name="close_time" class="form-control" value="<?php echo htmlspecialchars($field['close_time']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="field_type" class="form-label">Loại sân</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-people"></i></span>
                                                        <select name="field_type" class="form-select" required>
                                                            <option value="5" <?php echo $field['field_type'] === '5' ? 'selected' : ''; ?>>Sân 5 người</option>
                                                            <option value="7" <?php echo $field['field_type'] === '7' ? 'selected' : ''; ?>>Sân 7 người</option>
                                                            <option value="9" <?php echo $field['field_type'] === '9' ? 'selected' : ''; ?>>Sân 9 người</option>
                                                            <option value="11" <?php echo $field['field_type'] === '11' ? 'selected' : ''; ?>>Sân 11 người</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Hình ảnh hiện tại</label>
                                                    <div class="image-preview">
                                                        <?php if (!empty($field_images[$field['id']])): ?>
                                                            <?php foreach ($field_images[$field['id']] as $image): ?>
                                                                <div class="d-inline-block position-relative">
                                                                    <img src="assets/img/<?php echo htmlspecialchars($image['image']); ?>" alt="Hình ảnh sân">
                                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa hình ảnh này?');">
                                                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                                        <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm position-absolute top-0 end-0">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <p class="text-muted">Chưa có hình ảnh nào.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="field_images_edit_<?php echo $field['id']; ?>" class="form-label">Thêm hình ảnh mới (JPEG, PNG, GIF, tối đa 5MB - Có thể chọn nhiều ảnh)</label>
                                                    <input type="file" name="field_images[]" id="field_images_edit_<?php echo $field['id']; ?>" class="form-control" multiple>
                                                    <div id="preview_edit_<?php echo $field['id']; ?>" class="preview-container mt-2"></div>
                                                </div>
                                                <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary d-flex align-items-center gap-2" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-circle"></i> Đóng
                                                </button>
                                                <button type="submit" name="edit_field" class="btn btn-primary d-flex align-items-center gap-2">
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
    </div>
</section>

<script>
// JavaScript để hiển thị xem trước hình ảnh khi thêm sân
document.getElementById('field_images_add').addEventListener('change', function(event) {
    const preview = document.getElementById('preview_add');
    preview.innerHTML = ''; // Xóa các hình ảnh xem trước cũ
    const files = event.target.files;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    }
});

// JavaScript để hiển thị xem trước hình ảnh khi chỉnh sửa sân
<?php foreach ($fields as $field): ?>
    document.getElementById('field_images_edit_<?php echo $field['id']; ?>').addEventListener('change', function(event) {
        const preview = document.getElementById('preview_edit_<?php echo $field['id']; ?>');
        preview.innerHTML = ''; // Xóa các hình ảnh xem trước cũ
        const files = event.target.files;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        }
    });
<?php endforeach; ?>
</script>

<?php require_once 'includes/footer.php'; ?>