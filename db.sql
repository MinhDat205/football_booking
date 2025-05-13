CREATE DATABASE football_booking;
USE football_booking;

-- Tạo bảng users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    account_type ENUM('customer', 'owner', 'admin') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Thêm dữ liệu mẫu cho bảng users
INSERT INTO users (full_name, email, phone, password, account_type, status)
VALUES (
    'New Admin',
    'newadmin@example.com',
    '0123456789',
    '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 
    'admin',
    'approved'
);

-- Tạo bảng fields
CREATE TABLE fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255) NOT NULL,
    price_per_hour DECIMAL(10, 2) NOT NULL,
    open_time TIME,
    close_time TIME,
    field_type ENUM('5', '7', '9', '11') NOT NULL DEFAULT '5',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Tạo bảng field_images
CREATE TABLE field_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
);

-- Tạo bảng products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES fields(id)
);

-- Tạo bảng bookings
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    field_id INT,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_price DECIMAL(10, 2),
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    selected_products JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (field_id) REFERENCES fields(id),
    CONSTRAINT check_time CHECK (end_time > start_time),
    CONSTRAINT unique_booking UNIQUE (field_id, booking_date, start_time, end_time)
);

-- Tạo bảng reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    field_id INT,
    rating INT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (field_id) REFERENCES fields(id),
    CONSTRAINT check_rating CHECK (rating >= 1 AND rating <= 5)
);

-- Tạo bảng support_requests
CREATE TABLE support_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tạo bảng password_resets
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(email)
);

-- Tạo bảng conversations
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (user_id, owner_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Tạo bảng messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

-- Tạo bảng notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking_confirmed', 'new_message', 'new_message_conversation') NOT NULL,
    related_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tạo bảng revenues
CREATE TABLE revenues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    booking_id INT NOT NULL,
    field_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    owner_id INT NOT NULL,
    delivery_address VARCHAR(255) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'rejected', 'completed', 'received') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Thêm các chỉ mục để tối ưu hiệu suất
CREATE INDEX idx_user_id ON bookings(user_id);
CREATE INDEX idx_field_id ON bookings(field_id);
CREATE INDEX idx_booking_date ON bookings(booking_date);
CREATE INDEX idx_user_id_reviews ON reviews(user_id);
CREATE INDEX idx_field_id_reviews ON reviews(field_id);
CREATE INDEX idx_conversation_id ON messages(conversation_id);

-- Đặt lại id bắt đầu là 2
ALTER TABLE users AUTO_INCREMENT = 2;

-- Chủ sân (ID 2-11)
INSERT INTO users (id, full_name, email, phone, password, account_type, status, created_at)
VALUES 
(2, 'Chủ sân 1', 'owner1@example.com', '0912000001', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:00:00'),
(3, 'Chủ sân 2', 'owner2@example.com', '0912000002', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:05:00'),
(4, 'Chủ sân 3', 'owner3@example.com', '0912000003', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:10:00'),
(5, 'Chủ sân 4', 'owner4@example.com', '0912000004', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:15:00'),
(6, 'Chủ sân 5', 'owner5@example.com', '0912000005', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:20:00'),
(7, 'Chủ sân 6', 'owner6@example.com', '0912000006', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:25:00'),
(8, 'Chủ sân 7', 'owner7@example.com', '0912000007', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:30:00'),
(9, 'Chủ sân 8', 'owner8@example.com', '0912000008', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:35:00'),
(10, 'Chủ sân 9', 'owner9@example.com', '0912000009', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:40:00'),
(11, 'Chủ sân 10', 'owner10@example.com', '0912000010', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'owner', 'approved', '2025-05-01 10:45:00');

-- Khách hàng (ID 12-31)
INSERT INTO users (id, full_name, email, phone, password, account_type, status, created_at)
VALUES 
(12, 'Khách hàng 1', 'customer1@example.com', '0922000001', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:00:00'),
(13, 'Khách hàng 2', 'customer2@example.com', '0922000002', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:05:00'),
(14, 'Khách hàng 3', 'customer3@example.com', '0922000003', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:10:00'),
(15, 'Khách hàng 4', 'customer4@example.com', '0922000004', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:15:00'),
(16, 'Khách hàng 5', 'customer5@example.com', '0922000005', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:20:00'),
(17, 'Khách hàng 6', 'customer6@example.com', '0922000006', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:25:00'),
(18, 'Khách hàng 7', 'customer7@example.com', '0922000007', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:30:00'),
(19, 'Khách hàng 8', 'customer8@example.com', '0922000008', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:35:00'),
(20, 'Khách hàng 9', 'customer9@example.com', '0922000009', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:40:00'),
(21, 'Khách hàng 10', 'customer10@example.com', '0922000010', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:45:00'),
(22, 'Khách hàng 11', 'customer11@example.com', '0922000011', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:50:00'),
(23, 'Khách hàng 12', 'customer12@example.com', '0922000012', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 09:55:00'),
(24, 'Khách hàng 13', 'customer13@example.com', '0922000013', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:00:00'),
(25, 'Khách hàng 14', 'customer14@example.com', '0922000014', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:05:00'),
(26, 'Khách hàng 15', 'customer15@example.com', '0922000015', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:10:00'),
(27, 'Khách hàng 16', 'customer16@example.com', '0922000016', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:15:00'),
(28, 'Khách hàng 17', 'customer17@example.com', '0922000017', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:20:00'),
(29, 'Khách hàng 18', 'customer18@example.com', '0922000018', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:25:00'),
(30, 'Khách hàng 19', 'customer19@example.com', '0922000019', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:30:00'),
(31, 'Khách hàng 20', 'customer20@example.com', '0922000020', '$2y$10$Zdek8oAz829KJNPf4tojLeZnoAKtgNekLzKJeWnz.WgY6KAQBIBwK', 'customer', 'approved', '2025-05-02 10:35:00');

INSERT INTO fields (id, owner_id, name, address, price_per_hour, open_time, close_time, field_type, status, created_at)
VALUES 
(1, 2, 'Sân bóng A1', '123 Nguyễn Trãi, Hà Nội', 300000, '08:00:00', '22:00:00', '5', 'approved', '2025-05-03 08:00:00'),
(2, 3, 'Sân bóng A2', '456 Lê Lợi, Hà Nội', 350000, '09:00:00', '23:00:00', '7', 'approved', '2025-05-03 08:05:00'),
(3, 4, 'Sân bóng B1', '789 Trần Phú, Hà Nội', 400000, '07:00:00', '21:00:00', '9', 'approved', '2025-05-03 08:10:00'),
(4, 5, 'Sân bóng C1', '101 Nguyễn Huệ, Thành phố Hồ Chí Minh', 320000, '08:00:00', '22:00:00', '5', 'approved', '2025-05-03 08:15:00'),
(5, 6, 'Sân bóng C2', '202 Lê Lai, Thành phố Hồ Chí Minh', 370000, '09:00:00', '23:00:00', '7', 'approved', '2025-05-03 08:20:00'),
(6, 7, 'Sân bóng D1', '303 Phạm Văn Đồng, Thành phố Hồ Chí Minh', 410000, '07:00:00', '21:00:00', '11', 'approved', '2025-05-03 08:25:00'),
(7, 8, 'Sân bóng E1', '404 Nguyễn Thị Minh Khai, Đà Nẵng', 310000, '08:00:00', '22:00:00', '5', 'approved', '2025-05-03 08:30:00'),
(8, 9, 'Sân bóng E2', '505 Hàn Thuyên, Đà Nẵng', 360000, '09:00:00', '23:00:00', '7', 'approved', '2025-05-03 08:35:00'),
(9, 10, 'Sân bóng F1', '606 Nguyễn Văn Cừ, Đà Nẵng', 390000, '07:00:00', '21:00:00', '9', 'approved', '2025-05-03 08:40:00'),
(10, 11, 'Sân bóng F2', '707 Lê Duẩn, Đà Nẵng', 420000, '08:00:00', '22:00:00', '11', 'approved', '2025-05-03 08:45:00');

INSERT INTO field_images (field_id, image, created_at)
VALUES 
(1, 'field1.jpg', '2025-05-03 09:00:00'),
(2, 'field2.jpg', '2025-05-03 09:05:00'),
(3, 'field3.jpg', '2025-05-03 09:10:00'),
(4, 'field4.jpg', '2025-05-03 09:15:00'),
(5, 'field5.jpg', '2025-05-03 09:20:00'),
(6, 'field6.jpg', '2025-05-03 09:25:00'),
(7, 'field7.jpg', '2025-05-03 09:30:00'),
(8, 'field8.jpg', '2025-05-03 09:35:00'),
(9, 'field9.jpg', '2025-05-03 09:40:00'),
(10, 'field10.jpg', '2025-05-03 09:45:00');

INSERT INTO products (field_id, name, description, price, image, created_at)
VALUES 
(1, 'Nước suối', 'Nước suối 500ml', 10000, 'water.jpg', '2025-05-04 10:00:00'),
(1, 'Bóng đá', 'Bóng đá loại 5', 50000, 'ball.jpg', '2025-05-04 10:05:00'),
(2, 'Nước suối', 'Nước suối 500ml', 10000, 'water.jpg', '2025-05-04 10:10:00'),
(2, 'Áo thi đấu', 'Áo thi đấu đội tuyển', 150000, 'jersey.jpg', '2025-05-04 10:15:00'),
(3, 'Nước ngọt', 'Nước ngọt 330ml', 15000, 'soda.jpg', '2025-05-04 10:20:00'),
(3, 'Bóng đá', 'Bóng đá loại 7', 60000, 'ball.jpg', '2025-05-04 10:25:00'),
(4, 'Nước suối', 'Nước suối 500ml', 10000, 'water.jpg', '2025-05-04 10:30:00'),
(4, 'Khăn lạnh', 'Khăn lạnh dùng 1 lần', 5000, 'towel.jpg', '2025-05-04 10:35:00'),
(5, 'Nước ngọt', 'Nước ngọt 330ml', 15000, 'soda.jpg', '2025-05-04 10:40:00'),
(5, 'Bóng đá', 'Bóng đá loại 5', 50000, 'ball.jpg', '2025-05-04 10:45:00'),
(6, 'Nước suối', 'Nước suối 500ml', 10000, 'water.jpg', '2025-05-04 10:50:00'),
(6, 'Áo thi đấu', 'Áo thi đấu đội tuyển', 150000, 'jersey.jpg', '2025-05-04 10:55:00'),
(7, 'Nước ngọt', 'Nước ngọt 330ml', 15000, 'soda.jpg', '2025-05-04 11:00:00'),
(7, 'Khăn lạnh', 'Khăn lạnh dùng 1 lần', 5000, 'towel.jpg', '2025-05-04 11:05:00'),
(8, 'Nước suối', 'Nước suối 500ml', 10000, 'water.jpg', '2025-05-04 11:10:00'),
(8, 'Bóng đá', 'Bóng đá loại 7', 60000, 'ball.jpg', '2025-05-04 11:15:00'),
(9, 'Nước ngọt', 'Nước ngọt 330ml', 15000, 'soda.jpg', '2025-05-04 11:20:00'),
(9, 'Áo thi đấu', 'Áo thi đấu đội tuyển', 150000, 'jersey.jpg', '2025-05-04 11:25:00'),
(10, 'Nước suối', 'Nước suối 500ml', 10000, 'water.jpg', '2025-05-04 11:30:00'),
(10, 'Khăn lạnh', 'Khăn lạnh dùng 1 lần', 5000, 'towel.jpg', '2025-05-04 11:35:00');

INSERT INTO bookings (user_id, field_id, booking_date, start_time, end_time, total_price, status, selected_products, created_at)
VALUES 
-- Đặt sân bởi customer1 (ID 12)
(12, 1, '2025-05-15', '08:00:00', '09:00:00', 300000, 'pending', '[{"product_id": 1, "name": "Nước suối", "price": 10000, "quantity": 2}]', '2025-05-05 08:00:00'),
(12, 2, '2025-05-15', '09:00:00', '10:00:00', 350000, 'confirmed', '[{"product_id": 3, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 08:05:00'),
(12, 3, '2025-05-15', '10:00:00', '11:00:00', 400000, 'completed', '[{"product_id": 5, "name": "Nước ngọt", "price": 15000, "quantity": 3}]', '2025-05-05 08:10:00'),
-- Đặt sân bởi customer2 (ID 13)
(13, 4, '2025-05-15', '11:00:00', '12:00:00', 320000, 'pending', NULL, '2025-05-05 08:15:00'),
(13, 5, '2025-05-15', '12:00:00', '13:00:00', 370000, 'cancelled', '[{"product_id": 9, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 08:20:00'),
(13, 6, '2025-05-15', '13:00:00', '14:00:00', 410000, 'confirmed', '[{"product_id": 11, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 08:25:00'),
-- Đặt sân bởi customer3 (ID 14)
(14, 7, '2025-05-15', '14:00:00', '15:00:00', 310000, 'completed', '[{"product_id": 13, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 08:30:00'),
(14, 8, '2025-05-15', '15:00:00', '16:00:00', 360000, 'pending', NULL, '2025-05-05 08:35:00'),
(14, 9, '2025-05-15', '16:00:00', '17:00:00', 390000, 'confirmed', '[{"product_id": 17, "name": "Nước ngọt", "price": 15000, "quantity": 1}]', '2025-05-05 08:40:00'),
-- Đặt sân bởi customer4 (ID 15)
(15, 10, '2025-05-15', '17:00:00', '18:00:00', 420000, 'cancelled', '[{"product_id": 19, "name": "Nước suối", "price": 10000, "quantity": 3}]', '2025-05-05 08:45:00'),
(15, 1, '2025-05-16', '08:00:00', '09:00:00', 300000, 'pending', NULL, '2025-05-05 08:50:00'),
(15, 2, '2025-05-16', '09:00:00', '10:00:00', 350000, 'completed', '[{"product_id": 3, "name": "Nước suối", "price": 10000, "quantity": 2}]', '2025-05-05 08:55:00'),
-- Đặt sân bởi customer5 (ID 16)
(16, 3, '2025-05-16', '10:00:00', '11:00:00', 400000, 'confirmed', '[{"product_id": 5, "name": "Nước ngọt", "price": 15000, "quantity": 1}]', '2025-05-05 09:00:00'),
(16, 4, '2025-05-16', '11:00:00', '12:00:00', 320000, 'pending', NULL, '2025-05-05 09:05:00'),
(16, 5, '2025-05-16', '12:00:00', '13:00:00', 370000, 'cancelled', '[{"product_id": 9, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 09:10:00'),
-- Đặt sân bởi customer6 (ID 17)
(17, 6, '2025-05-16', '13:00:00', '14:00:00', 410000, 'completed', '[{"product_id": 11, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 09:15:00'),
(17, 7, '2025-05-16', '14:00:00', '15:00:00', 310000, 'pending', NULL, '2025-05-05 09:20:00'),
(17, 8, '2025-05-16', '15:00:00', '16:00:00', 360000, 'confirmed', '[{"product_id": 15, "name": "Bóng đá", "price": 60000, "quantity": 1}]', '2025-05-05 09:25:00'),
-- Đặt sân bởi customer7 (ID 18)
(18, 9, '2025-05-16', '16:00:00', '17:00:00', 390000, 'cancelled', '[{"product_id": 17, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 09:30:00'),
(18, 10, '2025-05-16', '17:00:00', '18:00:00', 420000, 'pending', NULL, '2025-05-05 09:35:00'),
(18, 1, '2025-05-17', '08:00:00', '09:00:00', 300000, 'completed', '[{"product_id": 1, "name": "Nước suối", "price": 10000, "quantity": 3}]', '2025-05-05 09:40:00'),
-- Đặt sân bởi customer8 (ID 19)
(19, 2, '2025-05-17', '09:00:00', '10:00:00', 350000, 'confirmed', '[{"product_id": 3, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 09:45:00'),
(19, 3, '2025-05-17', '10:00:00', '11:00:00', 400000, 'pending', NULL, '2025-05-05 09:50:00'),
(19, 4, '2025-05-17', '11:00:00', '12:00:00', 320000, 'cancelled', '[{"product_id": 7, "name": "Nước suối", "price": 10000, "quantity": 2}]', '2025-05-05 09:55:00'),
-- Đặt sân bởi customer9 (ID 20)
(20, 5, '2025-05-17', '12:00:00', '13:00:00', 370000, 'completed', '[{"product_id": 9, "name": "Nước ngọt", "price": 15000, "quantity": 1}]', '2025-05-05 10:00:00'),
(20, 6, '2025-05-17', '13:00:00', '14:00:00', 410000, 'pending', NULL, '2025-05-05 10:05:00'),
(20, 7, '2025-05-17', '14:00:00', '15:00:00', 310000, 'confirmed', '[{"product_id": 13, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 10:10:00'),
-- Đặt sân bởi customer10 (ID 21)
(21, 8, '2025-05-17', '15:00:00', '16:00:00', 360000, 'cancelled', '[{"product_id": 15, "name": "Bóng đá", "price": 60000, "quantity": 1}]', '2025-05-05 10:15:00'),
(21, 9, '2025-05-17', '16:00:00', '17:00:00', 390000, 'pending', NULL, '2025-05-05 10:20:00'),
(21, 10, '2025-05-17', '17:00:00', '18:00:00', 420000, 'completed', '[{"product_id": 19, "name": "Nước suối", "price": 10000, "quantity": 3}]', '2025-05-05 10:25:00'),
-- Đặt sân bởi customer11 (ID 22)
(22, 1, '2025-05-18', '08:00:00', '09:00:00', 300000, 'confirmed', '[{"product_id": 1, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 10:30:00'),
(22, 2, '2025-05-18', '09:00:00', '10:00:00', 350000, 'pending', NULL, '2025-05-05 10:35:00'),
(22, 3, '2025-05-18', '10:00:00', '11:00:00', 400000, 'cancelled', '[{"product_id": 5, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 10:40:00'),
-- Đặt sân bởi customer12 (ID 23)
(23, 4, '2025-05-18', '11:00:00', '12:00:00', 320000, 'completed', '[{"product_id": 7, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 10:45:00'),
(23, 5, '2025-05-18', '12:00:00', '13:00:00', 370000, 'pending', NULL, '2025-05-05 10:50:00'),
(23, 6, '2025-05-18', '13:00:00', '14:00:00', 410000, 'confirmed', '[{"product_id": 11, "name": "Nước suối", "price": 10000, "quantity": 2}]', '2025-05-05 10:55:00'),
-- Đặt sân bởi customer13 (ID 24)
(24, 7, '2025-05-18', '14:00:00', '15:00:00', 310000, 'cancelled', '[{"product_id": 13, "name": "Nước ngọt", "price": 15000, "quantity": 1}]', '2025-05-05 11:00:00'),
(24, 8, '2025-05-18', '15:00:00', '16:00:00', 360000, 'pending', NULL, '2025-05-05 11:05:00'),
(24, 9, '2025-05-18', '16:00:00', '17:00:00', 390000, 'completed', '[{"product_id": 17, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 11:10:00'),
-- Đặt sân bởi customer14 (ID 25)
(25, 10, '2025-05-18', '17:00:00', '18:00:00', 420000, 'confirmed', '[{"product_id": 19, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 11:15:00'),
(25, 1, '2025-05-19', '08:00:00', '09:00:00', 300000, 'pending', NULL, '2025-05-05 11:20:00'),
(25, 2, '2025-05-19', '09:00:00', '10:00:00', 350000, 'cancelled', '[{"product_id": 3, "name": "Nước suối", "price": 10000, "quantity": 2}]', '2025-05-05 11:25:00'),
-- Đặt sân bởi customer15 (ID 26)
(26, 3, '2025-05-19', '10:00:00', '11:00:00', 400000, 'completed', '[{"product_id": 5, "name": "Nước ngọt", "price": 15000, "quantity": 1}]', '2025-05-05 11:30:00'),
(26, 4, '2025-05-19', '11:00:00', '12:00:00', 320000, 'pending', NULL, '2025-05-05 11:35:00'),
(26, 5, '2025-05-19', '12:00:00', '13:00:00', 370000, 'confirmed', '[{"product_id": 9, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 11:40:00'),
-- Đặt sân bởi customer16 (ID 27)
(27, 6, '2025-05-19', '13:00:00', '14:00:00', 410000, 'cancelled', '[{"product_id": 11, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 11:45:00'),
(27, 7, '2025-05-19', '14:00:00', '15:00:00', 310000, 'pending', NULL, '2025-05-05 11:50:00'),
(27, 8, '2025-05-19', '15:00:00', '16:00:00', 360000, 'completed', '[{"product_id": 15, "name": "Bóng đá", "price": 60000, "quantity": 1}]', '2025-05-05 11:55:00'),
-- Đặt sân bởi customer17 (ID 28)
(28, 9, '2025-05-19', '16:00:00', '17:00:00', 390000, 'confirmed', '[{"product_id": 17, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 12:00:00'),
(28, 10, '2025-05-19', '17:00:00', '18:00:00', 420000, 'pending', NULL, '2025-05-05 12:05:00'),
(28, 1, '2025-05-20', '08:00:00', '09:00:00', 300000, 'cancelled', '[{"product_id": 1, "name": "Nước suối", "price": 10000, "quantity": 3}]', '2025-05-05 12:10:00'),
-- Đặt sân bởi customer18 (ID 29)
(29, 2, '2025-05-20', '09:00:00', '10:00:00', 350000, 'completed', '[{"product_id": 3, "name": "Nước suối", "price": 10000, "quantity": 1}]', '2025-05-05 12:15:00'),
(29, 3, '2025-05-20', '10:00:00', '11:00:00', 400000, 'pending', NULL, '2025-05-05 12:20:00'),
(29, 4, '2025-05-20', '11:00:00', '12:00:00', 320000, 'confirmed', '[{"product_id": 7, "name": "Nước suối", "price": 10000, "quantity": 2}]', '2025-05-05 12:25:00'),
-- Đặt sân bởi customer19 (ID 30)
(30, 5, '2025-05-20', '12:00:00', '13:00:00', 370000, 'cancelled', '[{"product_id": 9, "name": "Nước ngọt", "price": 15000, "quantity": 1}]', '2025-05-05 12:30:00'),
(30, 6, '2025-05-20', '13:00:00', '14:00:00', 410000, 'pending', NULL, '2025-05-05 12:35:00'),
(30, 7, '2025-05-20', '14:00:00', '15:00:00', 310000, 'completed', '[{"product_id": 13, "name": "Nước ngọt", "price": 15000, "quantity": 2}]', '2025-05-05 12:40:00'),
-- Đặt sân bởi customer20 (ID 31)
(31, 8, '2025-05-20', '15:00:00', '16:00:00', 360000, 'confirmed', '[{"product_id": 15, "name": "Bóng đá", "price": 60000, "quantity": 1}]', '2025-05-05 12:45:00'),
(31, 9, '2025-05-20', '16:00:00', '17:00:00', 390000, 'pending', NULL, '2025-05-05 12:50:00'),
(31, 10, '2025-05-20', '17:00:00', '18:00:00', 420000, 'cancelled', '[{"product_id": 19, "name": "Nước suối", "price": 10000, "quantity": 3}]', '2025-05-05 12:55:00');

INSERT INTO reviews (user_id, field_id, rating, comment, created_at)
VALUES 
(12, 1, 4, 'Sân đẹp, sạch sẽ', '2025-05-06 08:00:00'),
(12, 2, 3, 'Sân ổn nhưng hơi nhỏ', '2025-05-06 08:05:00'),
(12, 3, 5, 'Rất hài lòng', '2025-05-06 08:10:00'),
(13, 4, 2, 'Sân xuống cấp', '2025-05-06 08:15:00'),
(13, 5, 4, 'Dịch vụ tốt', '2025-05-06 08:20:00'),
(13, 6, 3, 'Giá hơi cao', '2025-05-06 08:25:00'),
(14, 7, 5, 'Tuyệt vời', '2025-05-06 08:30:00'),
(14, 8, 4, 'Sân đẹp, phục vụ tốt', '2025-05-06 08:35:00'),
(14, 9, 3, 'Cần cải thiện ánh sáng', '2025-05-06 08:40:00'),
(15, 10, 2, 'Sân không sạch', '2025-05-06 08:45:00'),
(15, 1, 4, 'Sân tốt, giá hợp lý', '2025-05-06 08:50:00'),
(15, 2, 5, 'Rất thích sân này', '2025-05-06 08:55:00'),
(16, 3, 3, 'Sân ổn, nhưng đông quá', '2025-05-06 09:00:00'),
(16, 4, 4, 'Dịch vụ tốt, sân sạch', '2025-05-06 09:05:00'),
(16, 5, 5, 'Rất hài lòng', '2025-05-06 09:10:00'),
(17, 6, 2, 'Sân cần bảo trì', '2025-05-06 09:15:00'),
(17, 7, 4, 'Sân đẹp, giá tốt', '2025-05-06 09:20:00'),
(17, 8, 3, 'Chất lượng trung bình', '2025-05-06 09:25:00'),
(18, 9, 5, 'Sân tuyệt vời', '2025-05-06 09:30:00'),
(18, 10, 4, 'Sân sạch, phục vụ tốt', '2025-05-06 09:35:00'),
(18, 1, 3, 'Cần cải thiện dịch vụ', '2025-05-06 09:40:00'),
(19, 2, 2, 'Sân không đạt yêu cầu', '2025-05-06 09:45:00'),
(19, 3, 4, 'Sân đẹp, giá hợp lý', '2025-05-06 09:50:00'),
(19, 4, 5, 'Rất hài lòng', '2025-05-06 09:55:00'),
(20, 5, 3, 'Sân ổn, nhưng đông', '2025-05-06 10:00:00'),
(20, 6, 4, 'Sân sạch, dịch vụ tốt', '2025-05-06 10:05:00'),
(20, 7, 5, 'Tuyệt vời', '2025-05-06 10:10:00'),
(21, 8, 2, 'Sân cần bảo trì', '2025-05-06 10:15:00'),
(21, 9, 4, 'Sân đẹp, giá hợp lý', '2025-05-06 10:20:00'),
(21, 10, 3, 'Chất lượng trung bình', '2025-05-06 10:25:00');

INSERT INTO support_requests (user_id, full_name, email, content, status, created_at)
VALUES 
(12, 'Khách hàng 1', 'customer1@example.com', 'Tôi không thể đặt sân vào ngày 15/05', 'pending', '2025-05-07 08:00:00'),
(13, 'Khách hàng 2', 'customer2@example.com', 'Sân bóng A1 không sạch', 'resolved', '2025-05-07 08:05:00'),
(14, 'Khách hàng 3', 'customer3@example.com', 'Yêu cầu hoàn tiền đặt sân B1', 'pending', '2025-05-07 08:10:00'),
(15, 'Khách hàng 4', 'customer4@example.com', 'Không nhận được xác nhận đặt sân', 'resolved', '2025-05-07 08:15:00'),
(16, 'Khách hàng 5', 'customer5@example.com', 'Sân C2 đóng cửa sớm', 'pending', '2025-05-07 08:20:00'),
(17, 'Khách hàng 6', 'customer6@example.com', 'Cần hỗ trợ đặt sân D1', 'resolved', '2025-05-07 08:25:00'),
(18, 'Khách hàng 7', 'customer7@example.com', 'Không thể đăng nhập', 'pending', '2025-05-07 08:30:00'),
(19, 'Khách hàng 8', 'customer8@example.com', 'Sân E2 không đúng mô tả', 'resolved', '2025-05-07 08:35:00'),
(20, 'Khách hàng 9', 'customer9@example.com', 'Yêu cầu hủy đặt sân F1', 'pending', '2025-05-07 08:40:00'),
(21, 'Khách hàng 10', 'customer10@example.com', 'Sân F2 không có nước uống', 'resolved', '2025-05-07 08:45:00');

INSERT INTO conversations (user_id, owner_id, created_at)
VALUES 
(12, 2, '2025-05-08 09:00:00'),  -- customer1 với owner1
(13, 3, '2025-05-08 09:05:00'),  -- customer2 với owner2
(14, 4, '2025-05-08 09:10:00'),  -- customer3 với owner3
(15, 5, '2025-05-08 09:15:00'),  -- customer4 với owner4
(16, 6, '2025-05-08 09:20:00'),  -- customer5 với owner5
(17, 7, '2025-05-08 09:25:00'),  -- customer6 với owner6
(18, 8, '2025-05-08 09:30:00'),  -- customer7 với owner7
(19, 9, '2025-05-08 09:35:00'),  -- customer8 với owner8
(20, 10, '2025-05-08 09:40:00'), -- customer9 với owner9
(21, 11, '2025-05-08 09:45:00'), -- customer10 với owner10
(22, 2, '2025-05-08 09:50:00'),  -- customer11 với owner1
(23, 3, '2025-05-08 09:55:00'),  -- customer12 với owner2
(24, 4, '2025-05-08 10:00:00'),  -- customer13 với owner3
(25, 5, '2025-05-08 10:05:00'),  -- customer14 với owner4
(26, 6, '2025-05-08 10:10:00'),  -- customer15 với owner5
(27, 7, '2025-05-08 10:15:00'),  -- customer16 với owner6
(28, 8, '2025-05-08 10:20:00'),  -- customer17 với owner7
(29, 9, '2025-05-08 10:25:00'),  -- customer18 với owner8
(30, 10, '2025-05-08 10:30:00'), -- customer19 với owner9
(31, 11, '2025-05-08 10:35:00'); -- customer20 với owner10

INSERT INTO messages (conversation_id, sender_id, receiver_id, message, created_at)
VALUES 
-- Cuộc trò chuyện 1 (customer1 và owner1)
(1, 12, 2, 'Chào anh, sân A1 còn trống vào ngày 15/05 không?', '2025-05-08 09:00:00'),
(1, 2, 12, 'Chào bạn, còn trống từ 08:00 đến 09:00 nhé!', '2025-05-08 09:02:00'),
(1, 12, 2, 'Cảm ơn anh, tôi sẽ đặt ngay!', '2025-05-08 09:04:00'),
-- Cuộc trò chuyện 2 (customer2 và owner2)
(2, 13, 3, 'Sân A2 có sẵn ngày 15/05 không?', '2025-05-08 09:05:00'),
(2, 3, 13, 'Có sẵn từ 09:00 đến 10:00, bạn đặt nhé!', '2025-05-08 09:07:00'),
-- Cuộc trò chuyện 3 (customer3 và owner3)
(3, 14, 4, 'Sân B1 có thể đặt vào 10:00 ngày 15/05 không?', '2025-05-08 09:10:00'),
(3, 4, 14, 'Được bạn ơi, bạn đặt đi nhé!', '2025-05-08 09:12:00'),
(3, 14, 4, 'Cảm ơn, tôi đặt ngay đây!', '2025-05-08 09:14:00'),
-- Cuộc trò chuyện 4 (customer4 và owner4)
(4, 15, 5, 'Sân C1 còn trống không anh?', '2025-05-08 09:15:00'),
(4, 5, 15, 'Còn trống từ 11:00 đến 12:00 ngày 15/05, bạn đặt nhé!', '2025-05-08 09:17:00'),
-- Cuộc trò chuyện 5 (customer5 và owner5)
(5, 16, 6, 'Sân C2 có thể đặt ngày 15/05 không?', '2025-05-08 09:20:00'),
(5, 6, 16, 'Có sẵn từ 12:00 đến 13:00, bạn đặt ngay nhé!', '2025-05-08 09:22:00'),
(5, 16, 6, 'Ok anh, tôi đặt ngay!', '2025-05-08 09:24:00'),
-- Cuộc trò chuyện 6 (customer6 và owner6)
(6, 17, 7, 'Sân D1 có trống ngày 15/05 không?', '2025-05-08 09:25:00'),
(6, 7, 17, 'Có từ 13:00 đến 14:00, bạn đặt đi nhé!', '2025-05-08 09:27:00'),
-- Cuộc trò chuyện 7 (customer7 và owner7)
(7, 18, 8, 'Sân E1 còn trống không anh?', '2025-05-08 09:30:00'),
(7, 8, 18, 'Còn từ 14:00 đến 15:00 ngày 15/05, bạn đặt nhé!', '2025-05-08 09:32:00'),
(7, 18, 8, 'Cảm ơn anh, tôi đặt ngay!', '2025-05-08 09:34:00'),
-- Cuộc trò chuyện 8 (customer8 và owner8)
(8, 19, 9, 'Sân E2 có sẵn ngày 15/05 không?', '2025-05-08 09:35:00'),
(8, 9, 19, 'Có từ 15:00 đến 16:00, bạn đặt đi nhé!', '2025-05-08 09:37:00'),
-- Cuộc trò chuyện 9 (customer9 và owner9)
(9, 20, 10, 'Sân F1 có thể đặt ngày 15/05 không?', '2025-05-08 09:40:00'),
(9, 10, 20, 'Có từ 16:00 đến 17:00, bạn đặt ngay nhé!', '2025-05-08 09:42:00'),
(9, 20, 10, 'Ok anh, tôi đặt ngay đây!', '2025-05-08 09:44:00'),
-- Cuộc trò chuyện 10 (customer10 và owner10)
(10, 21, 11, 'Sân F2 còn trống ngày 15/05 không?', '2025-05-08 09:45:00'),
(10, 11, 21, 'Còn từ 17:00 đến 18:00, bạn đặt nhé!', '2025-05-08 09:47:00'),
-- Cuộc trò chuyện 11 (customer11 và owner1)
(11, 22, 2, 'Sân A1 có sẵn ngày 16/05 không?', '2025-05-08 09:50:00'),
(11, 2, 22, 'Có từ 08:00 đến 09:00, bạn đặt đi nhé!', '2025-05-08 09:52:00'),
(11, 22, 2, 'Cảm ơn anh, tôi đặt ngay!', '2025-05-08 09:54:00'),
-- Cuộc trò chuyện 12 (customer12 và owner2)
(12, 23, 3, 'Sân A2 còn trống ngày 16/05 không?', '2025-05-08 09:55:00'),
(12, 3, 23, 'Có từ 09:00 đến 10:00, bạn đặt nhé!', '2025-05-08 09:57:00'),
-- Cuộc trò chuyện 13 (customer13 và owner3)
(13, 24, 4, 'Sân B1 có sẵn ngày 16/05 không?', '2025-05-08 10:00:00'),
(13, 4, 24, 'Có từ 10:00 đến 11:00, bạn đặt đi nhé!', '2025-05-08 10:02:00'),
(13, 24, 4, 'Ok, tôi đặt ngay đây!', '2025-05-08 10:04:00'),
-- Cuộc trò chuyện 14 (customer14 và owner4)
(14, 25, 5, 'Sân C1 có sẵn ngày 16/05 không?', '2025-05-08 10:05:00'),
(14, 5, 25, 'Có từ 11:00 đến 12:00, bạn đặt nhé!', '2025-05-08 10:07:00'),
-- Cuộc trò chuyện 15 (customer15 và owner5)
(15, 26, 6, 'Sân C2 có sẵn ngày 16/05 không?', '2025-05-08 10:10:00'),
(15, 6, 26, 'Có từ 12:00 đến 13:00, bạn đặt đi nhé!', '2025-05-08 10:12:00'),
(15, 26, 6, 'Cảm ơn anh, tôi đặt ngay!', '2025-05-08 10:14:00'),
-- Cuộc trò chuyện 16 (customer16 và owner6)
(16, 27, 7, 'Sân D1 có sẵn ngày 16/05 không?', '2025-05-08 10:15:00'),
(16, 7, 27, 'Có từ 13:00 đến 14:00, bạn đặt nhé!', '2025-05-08 10:17:00'),
-- Cuộc trò chuyện 17 (customer17 và owner7)
(17, 28, 8, 'Sân E1 có sẵn ngày 16/05 không?', '2025-05-08 10:20:00'),
(17, 8, 28, 'Có từ 14:00 đến 15:00, bạn đặt đi nhé!', '2025-05-08 10:22:00'),
(17, 28, 8, 'Ok, tôi đặt ngay đây!', '2025-05-08 10:24:00'),
-- Cuộc trò chuyện 18 (customer18 và owner8)
(18, 29, 9, 'Sân E2 có sẵn ngày 16/05 không?', '2025-05-08 10:25:00'),
(18, 9, 29, 'Có từ 15:00 đến 16:00, bạn đặt nhé!', '2025-05-08 10:27:00'),
-- Cuộc trò chuyện 19 (customer19 và owner9)
(19, 30, 10, 'Sân F1 có sẵn ngày 16/05 không?', '2025-05-08 10:30:00'),
(19, 10, 30, 'Có từ 16:00 đến 17:00, bạn đặt đi nhé!', '2025-05-08 10:32:00'),
(19, 30, 10, 'Cảm ơn anh, tôi đặt ngay!', '2025-05-08 10:34:00'),
-- Cuộc trò chuyện 20 (customer20 và owner10)
(20, 31, 11, 'Sân F2 có sẵn ngày 16/05 không?', '2025-05-08 10:35:00'),
(20, 11, 31, 'Có từ 17:00 đến 18:00, bạn đặt nhé!', '2025-05-08 10:37:00');

INSERT INTO notifications (user_id, message, type, related_id, is_read, created_at)
VALUES 
-- Thông báo cho customer1 (ID 12)
(12, 'Yêu cầu đặt sân của bạn (ID #1) đã được xác nhận.', 'booking_confirmed', 1, 0, '2025-05-06 07:00:00'),
(12, 'Yêu cầu đặt sân của bạn (ID #2) đã được xác nhận.', 'booking_confirmed', 2, 0, '2025-05-06 07:05:00'),
(12, 'Yêu cầu đặt sân của bạn (ID #3) đã hoàn thành.', 'booking_confirmed', 3, 0, '2025-05-06 07:10:00'),
-- Thông báo cho customer2 (ID 13)
(13, 'Yêu cầu đặt sân của bạn (ID #4) đã được xác nhận.', 'booking_confirmed', 4, 0, '2025-05-06 07:15:00'),
(13, 'Yêu cầu đặt sân của bạn (ID #5) đã bị hủy.', 'booking_confirmed', 5, 0, '2025-05-06 07:20:00'),
(13, 'Yêu cầu đặt sân của bạn (ID #6) đã được xác nhận.', 'booking_confirmed', 6, 0, '2025-05-06 07:25:00'),
-- Thông báo cho customer3 (ID 14)
(14, 'Yêu cầu đặt sân của bạn (ID #7) đã hoàn thành.', 'booking_confirmed', 7, 0, '2025-05-06 07:30:00'),
(14, 'Yêu cầu đặt sân của bạn (ID #8) đã được xác nhận.', 'booking_confirmed', 8, 0, '2025-05-06 07:35:00'),
(14, 'Yêu cầu đặt sân của bạn (ID #9) đã được xác nhận.', 'booking_confirmed', 9, 0, '2025-05-06 07:40:00'),
-- Thông báo cho customer4 (ID 15)
(15, 'Yêu cầu đặt sân của bạn (ID #10) đã bị hủy.', 'booking_confirmed', 10, 0, '2025-05-06 07:45:00'),
(15, 'Yêu cầu đặt sân của bạn (ID #11) đã được xác nhận.', 'booking_confirmed', 11, 0, '2025-05-06 07:50:00'),
(15, 'Yêu cầu đặt sân của bạn (ID #12) đã hoàn thành.', 'booking_confirmed', 12, 0, '2025-05-06 07:55:00'),
-- Thông báo cho customer5 (ID 16)
(16, 'Yêu cầu đặt sân của bạn (ID #13) đã được xác nhận.', 'booking_confirmed', 13, 0, '2025-05-06 08:00:00'),
(16, 'Yêu cầu đặt sân của bạn (ID #14) đã được xác nhận.', 'booking_confirmed', 14, 0, '2025-05-06 08:05:00'),
(16, 'Yêu cầu đặt sân của bạn (ID #15) đã bị hủy.', 'booking_confirmed', 15, 0, '2025-05-06 08:10:00'),
-- Thông báo cho customer6 (ID 17)
(17, 'Yêu cầu đặt sân của bạn (ID #16) đã hoàn thành.', 'booking_confirmed', 16, 0, '2025-05-06 08:15:00'),
(17, 'Yêu cầu đặt sân của bạn (ID #17) đã được xác nhận.', 'booking_confirmed', 17, 0, '2025-05-06 08:20:00'),
(17, 'Yêu cầu đặt sân của bạn (ID #18) đã được xác nhận.', 'booking_confirmed', 18, 0, '2025-05-06 08:25:00'),
-- Thông báo cho customer7 (ID 18)
(18, 'Yêu cầu đặt sân của bạn (ID #19) đã bị hủy.', 'booking_confirmed', 19, 0, '2025-05-06 08:30:00'),
(18, 'Yêu cầu đặt sân của bạn (ID #20) đã được xác nhận.', 'booking_confirmed', 20, 0, '2025-05-06 08:35:00'),
(18, 'Yêu cầu đặt sân của bạn (ID #21) đã hoàn thành.', 'booking_confirmed', 21, 0, '2025-05-06 08:40:00'),
-- Thông báo cho customer8 (ID 19)
(19, 'Yêu cầu đặt sân của bạn (ID #22) đã được xác nhận.', 'booking_confirmed', 22, 0, '2025-05-06 08:45:00'),
(19, 'Yêu cầu đặt sân của bạn (ID #23) đã được xác nhận.', 'booking_confirmed', 23, 0, '2025-05-06 08:50:00'),
(19, 'Yêu cầu đặt sân của bạn (ID #24) đã bị hủy.', 'booking_confirmed', 24, 0, '2025-05-06 08:55:00'),
-- Thông báo cho customer9 (ID 20)
(20, 'Yêu cầu đặt sân của bạn (ID #25) đã hoàn thành.', 'booking_confirmed', 25, 0, '2025-05-06 09:00:00'),
(20, 'Yêu cầu đặt sân của bạn (ID #26) đã được xác nhận.', 'booking_confirmed', 26, 0, '2025-05-06 09:05:00'),
(20, 'Yêu cầu đặt sân của bạn (ID #27) đã được xác nhận.', 'booking_confirmed', 27, 0, '2025-05-06 09:10:00'),
-- Thông báo cho customer10 (ID 21)
(21, 'Yêu cầu đặt sân của bạn (ID #28) đã bị hủy.', 'booking_confirmed', 28, 0, '2025-05-06 09:15:00'),
(21, 'Yêu cầu đặt sân của bạn (ID #29) đã được xác nhận.', 'booking_confirmed', 29, 0, '2025-05-06 09:20:00'),
(21, 'Yêu cầu đặt sân của bạn (ID #30) đã hoàn thành.', 'booking_confirmed', 30, 0, '2025-05-06 09:25:00'),
-- Thông báo cho customer11 (ID 22)
(22, 'Yêu cầu đặt sân của bạn (ID #31) đã được xác nhận.', 'booking_confirmed', 31, 0, '2025-05-06 09:30:00'),
(22, 'Yêu cầu đặt sân của bạn (ID #32) đã được xác nhận.', 'booking_confirmed', 32, 0, '2025-05-06 09:35:00'),
(22, 'Yêu cầu đặt sân của bạn (ID #33) đã bị hủy.', 'booking_confirmed', 33, 0, '2025-05-06 09:40:00'),
-- Thông báo cho customer12 (ID 23)
(23, 'Yêu cầu đặt sân của bạn (ID #34) đã hoàn thành.', 'booking_confirmed', 34, 0, '2025-05-06 09:45:00'),
(23, 'Yêu cầu đặt sân của bạn (ID #35) đã được xác nhận.', 'booking_confirmed', 35, 0, '2025-05-06 09:50:00'),
(23, 'Yêu cầu đặt sân của bạn (ID #36) đã được xác nhận.', 'booking_confirmed', 36, 0, '2025-05-06 09:55:00'),
-- Thông báo cho customer13 (ID 24)
(24, 'Yêu cầu đặt sân của bạn (ID #37) đã bị hủy.', 'booking_confirmed', 37, 0, '2025-05-06 10:00:00'),
(24, 'Yêu cầu đặt sân của bạn (ID #38) đã được xác nhận.', 'booking_confirmed', 38, 0, '2025-05-06 10:05:00'),
(24, 'Yêu cầu đặt sân của bạn (ID #39) đã hoàn thành.', 'booking_confirmed', 39, 0, '2025-05-06 10:10:00'),
-- Thông báo cho customer14 (ID 25)
(25, 'Yêu cầu đặt sân của bạn (ID #40) đã được xác nhận.', 'booking_confirmed', 40, 0, '2025-05-06 10:15:00'),
(25, 'Yêu cầu đặt sân của bạn (ID #41) đã được xác nhận.', 'booking_confirmed', 41, 0, '2025-05-06 10:20:00'),
(25, 'Yêu cầu đặt sân của bạn (ID #42) đã bị hủy.', 'booking_confirmed', 42, 0, '2025-05-06 10:25:00'),
-- Thông báo cho customer15 (ID 26)
(26, 'Yêu cầu đặt sân của bạn (ID #43) đã hoàn thành.', 'booking_confirmed', 43, 0, '2025-05-06 10:30:00'),
(26, 'Yêu cầu đặt sân của bạn (ID #44) đã được xác nhận.', 'booking_confirmed', 44, 0, '2025-05-06 10:35:00'),
(26, 'Yêu cầu đặt sân của bạn (ID #45) đã được xác nhận.', 'booking_confirmed', 45, 0, '2025-05-06 10:40:00'),
-- Thông báo cho customer16 (ID 27)
(27, 'Yêu cầu đặt sân của bạn (ID #46) đã bị hủy.', 'booking_confirmed', 46, 0, '2025-05-06 10:45:00'),
(27, 'Yêu cầu đặt sân của bạn (ID #47) đã được xác nhận.', 'booking_confirmed', 47, 0, '2025-05-06 10:50:00'),
(27, 'Yêu cầu đặt sân của bạn (ID #48) đã hoàn thành.', 'booking_confirmed', 48, 0, '2025-05-06 10:55:00'),
-- Thông báo cho customer17 (ID 28)
(28, 'Yêu cầu đặt sân của bạn (ID #49) đã được xác nhận.', 'booking_confirmed', 49, 0, '2025-05-06 11:00:00'),
(28, 'Yêu cầu đặt sân của bạn (ID #50) đã bị hủy.', 'booking_confirmed', 50, 0, '2025-05-06 11:05:00');

INSERT INTO notifications (user_id, message, type, related_id, is_read, created_at)
VALUES 
-- Thông báo tin nhắn cho customer1 (ID 12)
(12, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng A1', 'new_message_conversation', 1, 0, '2025-05-08 09:02:00'),
(2, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng A1', 'new_message_conversation', 2, 0, '2025-05-08 09:04:00'),
(12, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng A1', 'new_message_conversation', 3, 0, '2025-05-08 09:07:00'),
-- Thông báo tin nhắn cho customer2 (ID 13)
(13, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng A2', 'new_message_conversation', 4, 0, '2025-05-08 09:07:00'),
(3, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng A2', 'new_message_conversation', 5, 0, '2025-05-08 09:12:00'),
-- Thông báo tin nhắn cho customer3 (ID 14)
(14, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng B1', 'new_message_conversation', 6, 0, '2025-05-08 09:12:00'),
(4, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng B1', 'new_message_conversation', 7, 0, '2025-05-08 09:14:00'),
(14, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng B1', 'new_message_conversation', 8, 0, '2025-05-08 09:17:00'),
-- Thông báo tin nhắn cho customer4 (ID 15)
(15, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng C1', 'new_message_conversation', 9, 0, '2025-05-08 09:17:00'),
(5, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng C1', 'new_message_conversation', 10, 0, '2025-05-08 09:22:00'),
-- Thông báo tin nhắn cho customer5 (ID 16)
(16, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng C2', 'new_message_conversation', 11, 0, '2025-05-08 09:22:00'),
(6, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng C2', 'new_message_conversation', 12, 0, '2025-05-08 09:24:00'),
(16, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng C2', 'new_message_conversation', 13, 0, '2025-05-08 09:27:00'),
-- Thông báo tin nhắn cho customer6 (ID 17)
(17, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng D1', 'new_message_conversation', 14, 0, '2025-05-08 09:27:00'),
(7, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng D1', 'new_message_conversation', 15, 0, '2025-05-08 09:32:00'),
-- Thông báo tin nhắn cho customer7 (ID 18)
(18, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng E1', 'new_message_conversation', 16, 0, '2025-05-08 09:32:00'),
(8, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng E1', 'new_message_conversation', 17, 0, '2025-05-08 09:34:00'),
(18, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng E1', 'new_message_conversation', 18, 0, '2025-05-08 09:37:00'),
-- Thông báo tin nhắn cho customer8 (ID 19)
(19, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng E2', 'new_message_conversation', 19, 0, '2025-05-08 09:37:00'),
(9, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng E2', 'new_message_conversation', 20, 0, '2025-05-08 09:42:00'),
-- Thông báo tin nhắn cho customer9 (ID 20)
(20, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng F1', 'new_message_conversation', 21, 0, '2025-05-08 09:42:00'),
(10, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng F1', 'new_message_conversation', 22, 0, '2025-05-08 09:44:00'),
(20, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng F1', 'new_message_conversation', 23, 0, '2025-05-08 09:47:00'),
-- Thông báo tin nhắn cho customer10 (ID 21)
(21, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng F2', 'new_message_conversation', 24, 0, '2025-05-08 09:47:00'),
(11, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng F2', 'new_message_conversation', 25, 0, '2025-05-08 09:52:00'),
-- Thông báo tin nhắn cho customer11 (ID 22)
(22, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng A1', 'new_message_conversation', 26, 0, '2025-05-08 09:52:00'),
(2, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng A1', 'new_message_conversation', 27, 0, '2025-05-08 09:54:00'),
(22, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng A1', 'new_message_conversation', 28, 0, '2025-05-08 09:57:00'),
-- Thông báo tin nhắn cho customer12 (ID 23)
(23, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng A2', 'new_message_conversation', 29, 0, '2025-05-08 09:57:00'),
(3, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng A2', 'new_message_conversation', 30, 0, '2025-05-08 10:02:00'),
-- Thông báo tin nhắn cho customer13 (ID 24)
(24, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng B1', 'new_message_conversation', 31, 0, '2025-05-08 10:02:00'),
(4, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng B1', 'new_message_conversation', 32, 0, '2025-05-08 10:04:00'),
(24, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng B1', 'new_message_conversation', 33, 0, '2025-05-08 10:07:00'),
-- Thông báo tin nhắn cho customer14 (ID 25)
(25, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng C1', 'new_message_conversation', 34, 0, '2025-05-08 10:07:00'),
(5, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng C1', 'new_message_conversation', 35, 0, '2025-05-08 10:12:00'),
-- Thông báo tin nhắn cho customer15 (ID 26)
(26, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng C2', 'new_message_conversation', 36, 0, '2025-05-08 10:12:00'),
(6, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng C2', 'new_message_conversation', 37, 0, '2025-05-08 10:14:00'),
(26, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng C2', 'new_message_conversation', 38, 0, '2025-05-08 10:17:00'),
-- Thông báo tin nhắn cho customer16 (ID 27)
(27, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng D1', 'new_message_conversation', 39, 0, '2025-05-08 10:17:00'),
(7, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng D1', 'new_message_conversation', 40, 0, '2025-05-08 10:22:00'),
-- Thông báo tin nhắn cho customer17 (ID 28)
(28, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng E1', 'new_message_conversation', 41, 0, '2025-05-08 10:22:00'),
(8, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng E1', 'new_message_conversation', 42, 0, '2025-05-08 10:24:00'),
(28, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng E1', 'new_message_conversation', 43, 0, '2025-05-08 10:27:00'),
-- Thông báo tin nhắn cho customer18 (ID 29)
(29, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng E2', 'new_message_conversation', 44, 0, '2025-05-08 10:27:00'),
(9, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng E2', 'new_message_conversation', 45, 0, '2025-05-08 10:32:00'),
-- Thông báo tin nhắn cho customer19 (ID 30)
(30, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng F1', 'new_message_conversation', 46, 0, '2025-05-08 10:32:00'),
(10, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng F1', 'new_message_conversation', 47, 0, '2025-05-08 10:34:00'),
(30, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng F1', 'new_message_conversation', 48, 0, '2025-05-08 10:37:00'),
-- Thông báo tin nhắn cho customer20 (ID 31)
(31, 'Bạn có tin nhắn mới từ chủ sân liên quan đến sân bóng F2', 'new_message_conversation', 49, 0, '2025-05-08 10:37:00'),
(11, 'Bạn có tin nhắn mới từ khách hàng liên quan đến sân bóng F2', 'new_message_conversation', 50, 0, '2025-05-08 10:42:00');

INSERT INTO revenues (owner_id, booking_id, field_id, amount, created_at)
VALUES 
-- Booking 3 (field 1, owner 2)
(2, 3, 1, 300000 + (10000 * 3), '2025-05-06 07:10:00'),  -- total_price + nước suối (10,000 x 3)
-- Booking 7 (field 7, owner 8)
(8, 7, 7, 310000 + (15000 * 2), '2025-05-06 07:30:00'),  -- total_price + nước ngọt (15,000 x 2)
-- Booking 12 (field 2, owner 3)
(3, 12, 2, 350000 + (10000 * 2), '2025-05-06 07:55:00'), -- total_price + nước suối (10,000 x 2)
-- Booking 16 (field 6, owner 7)
(7, 16, 6, 410000 + (10000 * 1), '2025-05-06 08:15:00'), -- total_price + nước suối (10,000 x 1)
-- Booking 21 (field 1, owner 2)
(2, 21, 1, 300000 + (10000 * 3), '2025-05-06 08:40:00'), -- total_price + nước suối (10,000 x 3)
-- Booking 25 (field 5, owner 6)
(6, 25, 5, 370000 + (15000 * 1), '2025-05-06 09:00:00'), -- total_price + nước ngọt (15,000 x 1)
-- Booking 30 (field 10, owner 11)
(11, 30, 10, 420000 + (10000 * 3), '2025-05-06 09:25:00'), -- total_price + nước suối (10,000 x 3)
-- Booking 34 (field 4, owner 5)
(5, 34, 4, 320000 + (10000 * 1), '2025-05-06 09:45:00'), -- total_price + nước suối (10,000 x 1)
-- Booking 39 (field 9, owner 10)
(10, 39, 9, 390000 + (15000 * 2), '2025-05-06 10:10:00'), -- total_price + nước ngọt (15,000 x 2)
-- Booking 43 (field 3, owner 4)
(4, 43, 3, 400000 + (15000 * 1), '2025-05-06 10:30:00'), -- total_price + nước ngọt (15,000 x 1)
-- Booking 48 (field 8, owner 9)
(9, 48, 8, 360000 + (60000 * 1), '2025-05-06 10:55:00'); -- total_price + bóng đá (60,000 x 1)

INSERT INTO orders (customer_id, owner_id, delivery_address, total_price, status, created_at)
VALUES 
(12, 2, '123 Đường Láng, Hà Nội', 60000, 'pending', '2025-05-13 11:00:00'), -- Khách hàng 1 đặt hàng của chủ sân 2
(12, 3, '123 Đường Láng, Hà Nội', 160000, 'confirmed', '2025-05-13 11:05:00'), -- Khách hàng 1 đặt hàng của chủ sân 3
(13, 2, '456 Nguyễn Huệ, Hà Nội', 20000, 'rejected', '2025-05-13 11:10:00'), -- Khách hàng 2 đặt hàng của chủ sân 2
(14, 4, '789 Lê Lợi, TP.HCM', 55000, 'completed', '2025-05-13 11:15:00'), -- Khách hàng 3 đặt hàng của chủ sân 4
(15, 5, '101 Trần Phú, TP.HCM', 30000, 'received', '2025-05-13 11:20:00'); -- Khách hàng 4 đặt hàng của chủ sân 5

INSERT INTO order_items (order_id, product_id, quantity, price)
VALUES 
-- Đơn hàng 1 (pending): Khách hàng 1 đặt 1 bóng đá (ID 2) từ chủ sân 2
(1, 2, 1, 50000),
(1, 1, 1, 10000),
-- Đơn hàng 2 (confirmed): Khách hàng 1 đặt 1 áo thi đấu (ID 4) và 1 nước suối (ID 3) từ chủ sân 3
(2, 4, 1, 150000),
(2, 3, 1, 10000),
-- Đơn hàng 3 (rejected): Khách hàng 2 đặt 2 nước suối (ID 1) từ chủ sân 2
(3, 1, 2, 10000),
-- Đơn hàng 4 (completed): Khách hàng 3 đặt 1 bóng đá (ID 7) và 1 nước suối (ID 6) từ chủ sân 4
(4, 7, 1, 50000),
(4, 6, 1, 5000),
-- Đơn hàng 5 (received): Khách hàng 4 đặt 2 nước ngọt (ID 9) từ chủ sân 5
(5, 9, 2, 15000);

INSERT INTO notifications (user_id, message, type, related_id, created_at)
VALUES 
-- Thông báo cho chủ sân 2 về đơn hàng 1 (pending)
(2, 'Bạn có đơn đặt sản phẩm mới (ID #1) từ khách hàng.', 'new_order', 1, '2025-05-13 11:00:00'),
-- Thông báo cho chủ sân 3 về đơn hàng 2 (confirmed)
(3, 'Bạn có đơn đặt sản phẩm mới (ID #2) từ khách hàng.', 'new_order', 2, '2025-05-13 11:05:00'),
(12, 'Đơn hàng của bạn (ID #2) đã được xác nhận.', 'order_confirmed', 2, '2025-05-13 11:06:00'),
-- Thông báo cho chủ sân 2 về đơn hàng 3 (rejected)
(2, 'Bạn có đơn đặt sản phẩm mới (ID #3) từ khách hàng.', 'new_order', 3, '2025-05-13 11:10:00'),
(13, 'Đơn hàng của bạn (ID #3) đã bị từ chối.', 'order_rejected', 3, '2025-05-13 11:11:00'),
-- Thông báo cho chủ sân 4 về đơn hàng 4 (completed)
(4, 'Bạn có đơn đặt sản phẩm mới (ID #4) từ khách hàng.', 'new_order', 4, '2025-05-13 11:15:00'),
(14, 'Đơn hàng của bạn (ID #4) đã được xác nhận.', 'order_confirmed', 4, '2025-05-13 11:16:00'),
(14, 'Đơn hàng của bạn (ID #4) đã hoàn thành. Vui lòng xác nhận nhận hàng.', 'order_completed', 4, '2025-05-13 11:17:00'),
-- Thông báo cho chủ sân 5 về đơn hàng 5 (received)
(5, 'Bạn có đơn đặt sản phẩm mới (ID #5) từ khách hàng.', 'new_order', 5, '2025-05-13 11:20:00'),
(15, 'Đơn hàng của bạn (ID #5) đã được xác nhận.', 'order_confirmed', 5, '2025-05-13 11:21:00'),
(15, 'Đơn hàng của bạn (ID #5) đã hoàn thành. Vui lòng xác nhận nhận hàng.', 'order_completed', 5, '2025-05-13 11:22:00');