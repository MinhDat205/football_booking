-- Tạo cơ sở dữ liệu mới
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
    image VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (owner_id) REFERENCES users(id)
);
ALTER TABLE fields
ADD COLUMN field_type ENUM('5', '7', '9', '11') NOT NULL DEFAULT '5';

ALTER TABLE fields
DROP COLUMN image;

CREATE TABLE field_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
);

ALTER TABLE fields
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Tạo bảng bookings
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    field_id INT,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_price DECIMAL(10, 2),
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (field_id) REFERENCES fields(id)
);
ALTER TABLE bookings
ADD COLUMN selected_products TEXT DEFAULT NULL;

-- Tạo bảng reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    field_id INT,
    rating INT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (field_id) REFERENCES fields(id)
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

-- Tạo bảng conversations (mới, để hỗ trợ chức năng chat theo cặp người dùng)
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

-- Tạo bảng messages (sử dụng conversation_id thay vì booking_id)
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

-- Tạo bảng notifications (cập nhật type để hỗ trợ new_message_conversation)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking_confirmed', 'new_message', 'new_message_conversation') NOT NULL,
    related_id INT NOT NULL, -- ID liên quan (booking_id hoặc message_id)
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
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

-- Đặt lại id bắt đầu là 2
ALTER TABLE users AUTO_INCREMENT = 2;

-- Tạo dữ liệu kiểm thử
-- 2. Thêm 10 chủ sân (owner1 -> owner10)
INSERT INTO users (full_name, email, phone, password, account_type, status) VALUES
('Owner 1', 'owner1@example.com', '0900000001', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 2', 'owner2@example.com', '0900000002', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 3', 'owner3@example.com', '0900000003', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 4', 'owner4@example.com', '0900000004', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 5', 'owner5@example.com', '0900000005', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 6', 'owner6@example.com', '0900000006', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 7', 'owner7@example.com', '0900000007', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 8', 'owner8@example.com', '0900000008', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 9', 'owner9@example.com', '0900000009', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved'),
('Owner 10', 'owner10@example.com', '0900000010', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'owner', 'approved');

-- 3. Thêm 20 khách hàng (customer1 -> customer20)
INSERT INTO users (full_name, email, phone, password, account_type, status) VALUES
('Customer 1', 'customer1@example.com', '0910000001', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 2', 'customer2@example.com', '0910000002', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 3', 'customer3@example.com', '0910000003', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 4', 'customer4@example.com', '0910000004', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 5', 'customer5@example.com', '0910000005', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 6', 'customer6@example.com', '0910000006', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 7', 'customer7@example.com', '0910000007', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 8', 'customer8@example.com', '0910000008', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 9', 'customer9@example.com', '0910000009', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 10', 'customer10@example.com', '0910000010', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 11', 'customer11@example.com', '0910000011', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 12', 'customer12@example.com', '0910000012', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 13', 'customer13@example.com', '0910000013', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 14', 'customer14@example.com', '0910000014', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 15', 'customer15@example.com', '0910000015', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 16', 'customer16@example.com', '0910000016', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 17', 'customer17@example.com', '0910000017', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 18', 'customer18@example.com', '0910000018', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 19', 'customer19@example.com', '0910000019', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved'),
('Customer 20', 'customer20@example.com', '0910000020', '$2y$10$ApJV.JRjbBPGBmzVlhpQZeqjZhG.Fzst8SsdiCrMC665ARf/UdzU.', 'customer', 'approved');

-- 4. Thêm 10 sân bóng (mỗi chủ sân sở hữu 1 sân, địa chỉ tại Hà Nội, Đà Nẵng, hoặc TP.HCM, với created_at)
INSERT INTO fields (owner_id, name, address, price_per_hour, open_time, close_time, image, status, created_at) VALUES
(2, 'Field 1', '123 Cầu Giấy, Hà Nội', 100000, '07:00:00', '23:00:00', 'field_1.jpg', 'approved', '2025-05-01 10:00:00'),
(3, 'Field 2', '456 Ba Đình, Hà Nội', 150000, '07:00:00', '23:00:00', 'field_2.jpg', 'approved', '2025-05-02 10:00:00'),
(4, 'Field 3', '789 Hải Châu, Đà Nẵng', 200000, '07:00:00', '23:00:00', 'field_3.jpg', 'approved', '2025-05-03 10:00:00'),
(5, 'Field 4', '101 Nguyễn Văn Cừ, Đà Nẵng', 250000, '07:00:00', '23:00:00', 'field_4.jpg', 'approved', '2025-05-04 10:00:00'),
(6, 'Field 5', '321 Quận 1, TP. Hồ Chí Minh', 300000, '07:00:00', '23:00:00', 'field_5.jpg', 'approved', '2025-05-05 10:00:00'),
(7, 'Field 6', '654 Quận 7, TP. Hồ Chí Minh', 350000, '07:00:00', '23:00:00', 'field_6.jpg', 'approved', '2025-05-06 10:00:00'),
(8, 'Field 7', '987 Đống Đa, Hà Nội', 400000, '07:00:00', '23:00:00', 'field_7.jpg', 'approved', '2025-05-07 10:00:00'),
(9, 'Field 8', '147 Thanh Khê, Đà Nẵng', 450000, '07:00:00', '23:00:00', 'field_8.jpg', 'approved', '2025-05-08 10:00:00'),
(10, 'Field 9', '258 Quận 3, TP. Hồ Chí Minh', 500000, '07:00:00', '23:00:00', 'field_9.jpg', 'approved', '2025-05-09 10:00:00'),
(11, 'Field 10', '369 Thủ Đức, TP. Hồ Chí Minh', 550000, '07:00:00', '23:00:00', 'field_10.jpg', 'approved', '2025-05-10 10:00:00');

-- 5. Thêm 20 sản phẩm (mỗi sân có 2 sản phẩm)
INSERT INTO products (field_id, name, description, price, image) VALUES
(1, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(1, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(2, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(2, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(3, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(3, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(4, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(4, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(5, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(5, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(6, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(6, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(7, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(7, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(8, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(8, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(9, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(9, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg'),
(10, 'Water Bottle', '500ml mineral water', 10000, 'product_1.jpg'),
(10, 'Energy Drink', '250ml energy drink', 15000, 'product_2.jpg');

-- 6. Thêm 50 yêu cầu đặt sân (mỗi khách hàng đặt 2-3 sân)
INSERT INTO bookings (user_id, field_id, booking_date, start_time, end_time, total_price, status) VALUES
(12, 1, '2025-05-12', '08:00:00', '09:00:00', 100000, 'confirmed'),
(12, 2, '2025-05-13', '09:00:00', '10:00:00', 150000, 'pending'),
(12, 3, '2025-05-14', '10:00:00', '11:00:00', 200000, 'confirmed'),
(13, 4, '2025-05-15', '11:00:00', '12:00:00', 250000, 'cancelled'),
(13, 5, '2025-05-16', '12:00:00', '13:00:00', 300000, 'confirmed'),
(13, 6, '2025-05-17', '13:00:00', '14:00:00', 350000, 'pending'),
(14, 7, '2025-05-18', '14:00:00', '15:00:00', 400000, 'confirmed'),
(14, 8, '2025-05-19', '15:00:00', '16:00:00', 450000, 'cancelled'),
(14, 9, '2025-05-20', '16:00:00', '17:00:00', 500000, 'confirmed'),
(15, 10, '2025-05-21', '17:00:00', '18:00:00', 550000, 'pending'),
(15, 1, '2025-05-22', '18:00:00', '19:00:00', 100000, 'confirmed'),
(15, 2, '2025-05-23', '19:00:00', '20:00:00', 150000, 'cancelled'),
(16, 3, '2025-05-24', '20:00:00', '21:00:00', 200000, 'confirmed'),
(16, 4, '2025-05-25', '21:00:00', '22:00:00', 250000, 'pending'),
(16, 5, '2025-05-26', '08:00:00', '09:00:00', 300000, 'confirmed'),
(17, 6, '2025-05-27', '09:00:00', '10:00:00', 350000, 'cancelled'),
(17, 7, '2025-05-28', '10:00:00', '11:00:00', 400000, 'confirmed'),
(17, 8, '2025-05-29', '11:00:00', '12:00:00', 450000, 'pending'),
(18, 9, '2025-05-30', '12:00:00', '13:00:00', 500000, 'confirmed'),
(18, 10, '2025-06-01', '13:00:00', '14:00:00', 550000, 'cancelled'),
(18, 1, '2025-06-02', '14:00:00', '15:00:00', 100000, 'confirmed'),
(19, 2, '2025-06-03', '15:00:00', '16:00:00', 150000, 'pending'),
(19, 3, '2025-06-04', '16:00:00', '17:00:00', 200000, 'confirmed'),
(19, 4, '2025-06-05', '17:00:00', '18:00:00', 250000, 'cancelled'),
(20, 5, '2025-06-06', '18:00:00', '19:00:00', 300000, 'confirmed'),
(20, 6, '2025-06-07', '19:00:00', '20:00:00', 350000, 'pending'),
(20, 7, '2025-06-08', '20:00:00', '21:00:00', 400000, 'confirmed'),
(21, 8, '2025-06-09', '21:00:00', '22:00:00', 450000, 'cancelled'),
(21, 9, '2025-06-10', '08:00:00', '09:00:00', 500000, 'confirmed'),
(21, 10, '2025-06-11', '09:00:00', '10:00:00', 550000, 'pending'),
(22, 1, '2025-06-12', '10:00:00', '11:00:00', 100000, 'confirmed'),
(22, 2, '2025-06-13', '11:00:00', '12:00:00', 150000, 'cancelled'),
(22, 3, '2025-06-14', '12:00:00', '13:00:00', 200000, 'confirmed'),
(23, 4, '2025-06-15', '13:00:00', '14:00:00', 250000, 'pending'),
(23, 5, '2025-06-16', '14:00:00', '15:00:00', 300000, 'confirmed'),
(23, 6, '2025-06-17', '15:00:00', '16:00:00', 350000, 'cancelled'),
(24, 7, '2025-06-18', '16:00:00', '17:00:00', 400000, 'confirmed'),
(24, 8, '2025-06-19', '17:00:00', '18:00:00', 450000, 'pending'),
(24, 9, '2025-06-20', '18:00:00', '19:00:00', 500000, 'confirmed'),
(25, 10, '2025-06-21', '19:00:00', '20:00:00', 550000, 'cancelled'),
(25, 1, '2025-06-22', '20:00:00', '21:00:00', 100000, 'confirmed'),
(25, 2, '2025-06-23', '21:00:00', '22:00:00', 150000, 'pending'),
(26, 3, '2025-06-24', '08:00:00', '09:00:00', 200000, 'confirmed'),
(26, 4, '2025-06-25', '09:00:00', '10:00:00', 250000, 'cancelled'),
(26, 5, '2025-06-26', '10:00:00', '11:00:00', 300000, 'confirmed'),
(27, 6, '2025-06-27', '11:00:00', '12:00:00', 350000, 'pending'),
(27, 7, '2025-06-28', '12:00:00', '13:00:00', 400000, 'confirmed'),
(27, 8, '2025-06-29', '13:00:00', '14:00:00', 450000, 'cancelled'),
(28, 9, '2025-06-30', '14:00:00', '15:00:00', 500000, 'confirmed'),
(28, 10, '2025-07-01', '15:00:00', '16:00:00', 550000, 'pending');

-- 7. Thêm 30 đánh giá (mỗi khách hàng đánh giá 1-2 sân)
INSERT INTO reviews (user_id, field_id, rating, comment) VALUES
(12, 1, 5, 'Great field, clean and well-maintained'),
(12, 2, 4, 'Good experience, but parking is limited'),
(13, 3, 3, 'Average field, needs better lighting'),
(13, 4, 5, 'Excellent service and facilities'),
(14, 5, 4, 'Nice field, friendly staff'),
(14, 6, 5, 'Top-notch field, highly recommend'),
(15, 7, 3, 'Decent field, but a bit expensive'),
(15, 8, 4, 'Good quality, will come back'),
(16, 9, 5, 'Fantastic field, great atmosphere'),
(16, 10, 4, 'Very good, but far from city center'),
(17, 1, 5, 'Perfect place for a match'),
(17, 2, 3, 'Okay, but grass needs maintenance'),
(18, 3, 4, 'Good field, clean facilities'),
(18, 4, 5, 'Loved playing here, great staff'),
(19, 5, 4, 'Nice environment, good experience'),
(19, 6, 5, 'One of the best fields I’ve played on'),
(20, 7, 3, 'Average, could improve cleanliness'),
(20, 8, 4, 'Good field, worth the price'),
(21, 9, 5, 'Amazing field, highly recommend'),
(21, 10, 4, 'Great, but booking process can be slow'),
(22, 1, 5, 'Excellent field, great location'),
(22, 2, 4, 'Good, but needs better seating'),
(23, 3, 3, 'Okay field, average experience'),
(23, 4, 5, 'Fantastic place to play'),
(24, 5, 4, 'Very good field, friendly staff'),
(24, 6, 5, 'Top quality, will return'),
(25, 7, 3, 'Decent, but a bit pricey'),
(25, 8, 4, 'Good field, clean and maintained'),
(26, 9, 5, 'Best field I’ve played on'),
(26, 10, 4, 'Great experience, but far from home');

-- 8. Thêm 10 yêu cầu hỗ trợ (từ 10 khách hàng đầu tiên)
INSERT INTO support_requests (user_id, full_name, email, content, status) VALUES
(12, 'Customer 1', 'customer1@example.com', 'Need help with booking confirmation', 'pending'),
(13, 'Customer 2', 'customer2@example.com', 'Issue with payment process', 'resolved'),
(14, 'Customer 3', 'customer3@example.com', 'Field availability question', 'pending'),
(15, 'Customer 4', 'customer4@example.com', 'Request to change booking time', 'resolved'),
(16, 'Customer 5', 'customer5@example.com', 'Problem with account login', 'pending'),
(17, 'Customer 6', 'customer6@example.com', 'Complaint about field condition', 'resolved'),
(18, 'Customer 7', 'customer7@example.com', 'Need refund for cancelled booking', 'pending'),
(19, 'Customer 8', 'customer8@example.com', 'Question about product purchase', 'resolved'),
(20, 'Customer 9', 'customer9@example.com', 'Issue with notification settings', 'pending'),
(21, 'Customer 10', 'customer10@example.com', 'General inquiry about services', 'resolved');

-- 9. Thêm 50 conversations (mỗi khách hàng có 2-3 cuộc trò chuyện với chủ sân)
INSERT INTO conversations (user_id, owner_id) VALUES
(12, 2), (12, 3), (12, 4),
(13, 5), (13, 6), (13, 7),
(14, 8), (14, 9), (14, 10),
(15, 2), (15, 3), (15, 4),
(16, 5), (16, 6), (16, 7),
(17, 8), (17, 9), (17, 10),
(18, 2), (18, 3), (18, 4),
(19, 5), (19, 6), (19, 7),
(20, 8), (20, 9), (20, 10),
(21, 2), (21, 3), (21, 4),
(22, 5), (22, 6), (22, 7),
(23, 8), (23, 9), (23, 10),
(24, 2), (24, 3), (24, 4),
(25, 5), (25, 6), (25, 7),
(26, 8), (26, 9), (26, 10),
(27, 2), (27, 3), (27, 4),
(28, 5), (28, 6);

-- 10. Thêm 100 tin nhắn (mỗi conversation có 2 tin nhắn)
INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES
(1, 12, 2, 'Hello, is Field 1 available tomorrow?'),
(1, 2, 12, 'Yes, it’s available from 08:00 to 12:00.'),
(2, 12, 3, 'Can I book Field 2 for next week?'),
(2, 3, 12, 'Please provide the date and time.'),
(3, 12, 4, 'What’s the price for Field 3?'),
(3, 4, 12, 'It’s 200,000 VND per hour.'),
(4, 13, 5, 'Is Field 4 available this weekend?'),
(4, 5, 13, 'Yes, available on Saturday morning.'),
(5, 13, 6, 'Can you confirm my booking for Field 5?'),
(5, 6, 13, 'Confirmed for 12:00 on 2025-05-16.'),
(6, 13, 7, 'Any discounts for Field 6?'),
(6, 7, 13, 'No discounts currently, sorry.'),
(7, 14, 8, 'Is Field 7 open in the evening?'),
(7, 8, 14, 'Yes, until 23:00.'),
(8, 14, 9, 'Can I book Field 8 for 2 hours?'),
(8, 9, 14, 'Yes, please specify the time.'),
(9, 14, 10, 'What’s the condition of Field 9?'),
(9, 10, 14, 'It’s in excellent condition.'),
(10, 15, 2, 'Can I cancel my booking for Field 1?'),
(10, 2, 15, 'Yes, please provide the booking ID.'),
(11, 15, 3, 'Is Field 2 available on Friday?'),
(11, 3, 15, 'Yes, from 09:00 to 15:00.'),
(12, 15, 4, 'How much is Field 3 per hour?'),
(12, 4, 15, 'It’s 200,000 VND per hour.'),
(13, 16, 5, 'Can I book Field 4 for next month?'),
(13, 5, 16, 'Please provide the exact date.'),
(14, 16, 6, 'Is Field 5 well-lit at night?'),
(14, 6, 16, 'Yes, it has excellent lighting.'),
(15, 16, 7, 'Any promotions for Field 6?'),
(15, 7, 16, 'Not at the moment.'),
(16, 17, 8, 'Can I book Field 7 for a group?'),
(16, 8, 17, 'Yes, how many people?'),
(17, 17, 9, 'Is Field 8 available tomorrow?'),
(17, 9, 17, 'Yes, from 11:00 to 17:00.'),
(18, 17, 10, 'What’s the price for Field 9?'),
(18, 10, 17, 'It’s 500,000 VND per hour.'),
(19, 18, 2, 'Can I book Field 1 for 2 hours?'),
(19, 2, 18, 'Yes, please provide the time.'),
(20, 18, 3, 'Is Field 2 clean and maintained?'),
(20, 3, 18, 'Yes, it’s in great condition.'),
(21, 18, 4, 'Any discounts for Field 3?'),
(21, 4, 18, 'No discounts currently.'),
(22, 19, 5, 'Can I book Field 4 for a match?'),
(22, 5, 19, 'Yes, please specify the date.'),
(23, 19, 6, 'Is Field 5 available on Sunday?'),
(23, 6, 19, 'Yes, from 08:00 to 14:00.'),
(24, 19, 7, 'What’s the price for Field 6?'),
(24, 7, 19, 'It’s 350,000 VND per hour.'),
(25, 20, 8, 'Can I book Field 7 for next week?'),
(25, 8, 20, 'Please provide the date and time.'),
(26, 20, 9, 'Is Field 8 well-maintained?'),
(26, 9, 20, 'Yes, it’s in top condition.'),
(27, 20, 10, 'Can I book Field 9 for 3 hours?'),
(27, 10, 20, 'Yes, please specify the time.'),
(28, 21, 2, 'Is Field 1 available tomorrow?'),
(28, 2, 21, 'Yes, from 08:00 to 12:00.'),
(29, 21, 3, 'Can I book Field 2 for a group?'),
(29, 3, 21, 'Yes, how many players?'),
(30, 21, 4, 'What’s the price for Field 3?'),
(30, 4, 21, 'It’s 200,000 VND per hour.'),
(31, 22, 5, 'Is Field 4 available next week?'),
(31, 5, 22, 'Yes, please provide the date.'),
(32, 22, 6, 'Can I book Field 5 for 2 hours?'),
(32, 6, 22, 'Yes, please specify the time.'),
(33, 22, 7, 'Is Field 6 clean?'),
(33, 7, 22, 'Yes, it’s well-maintained.'),
(34, 23, 8, 'Can I book Field 7 for a match?'),
(34, 8, 23, 'Yes, please provide the date.'),
(35, 23, 9, 'Is Field 8 available on Saturday?'),
(35, 9, 23, 'Yes, from 09:00 to 15:00.'),
(36, 23, 10, 'What’s the price for Field 9?'),
(36, 10, 23, 'It’s 500,000 VND per hour.'),
(37, 24, 2, 'Can I book Field 1 for next month?'),
(37, 2, 24, 'Please provide the exact date.'),
(38, 24, 3, 'Is Field 2 available tomorrow?'),
(38, 3, 24, 'Yes, from 10:00 to 14:00.'),
(39, 24, 4, 'Can I book Field 3 for 2 hours?'),
(39, 4, 24, 'Yes, please specify the time.'),
(40, 25, 5, 'Is Field 4 well-lit at night?'),
(40, 5, 25, 'Yes, it has great lighting.'),
(41, 25, 6, 'Can I book Field 5 for a group?'),
(41, 6, 25, 'Yes, how many players?'),
(42, 25, 7, 'What’s the price for Field 6?'),
(42, 7, 25, 'It’s 350,000 VND per hour.'),
(43, 26, 8, 'Is Field 7 available next week?'),
(43, 8, 26, 'Yes, please provide the date.'),
(44, 26, 9, 'Can I book Field 8 for 2 hours?'),
(44, 9, 26, 'Yes, please specify the time.'),
(45, 26, 10, 'Is Field 9 clean and maintained?'),
(45, 10, 26, 'Yes, it’s in excellent condition.'),
(46, 27, 2, 'Can I book Field 1 for a match?'),
(46, 2, 27, 'Yes, please provide the date.'),
(47, 27, 3, 'Is Field 2 available on Sunday?'),
(47, 3, 27, 'Yes, from 08:00 to 12:00.'),
(48, 27, 4, 'What’s the price for Field 3?'),
(48, 4, 27, 'It’s 200,000 VND per hour.'),
(49, 28, 5, 'Can I book Field 4 for next week?'),
(49, 5, 28, 'Yes, please provide the date.'),
(50, 28, 6, 'Is Field 5 available tomorrow?'),
(50, 6, 28, 'Yes, from 09:00 to 13:00.');

-- 11. Thêm 100 thông báo
-- 50 thông báo cho booking confirmed
INSERT INTO notifications (user_id, message, type, related_id) VALUES
(12, 'Booking 1 has been confirmed', 'booking_confirmed', 1),
(12, 'Booking 3 has been confirmed', 'booking_confirmed', 3),
(13, 'Booking 5 has been confirmed', 'booking_confirmed', 5),
(14, 'Booking 7 has been confirmed', 'booking_confirmed', 7),
(14, 'Booking 9 has been confirmed', 'booking_confirmed', 9),
(15, 'Booking 11 has been confirmed', 'booking_confirmed', 11),
(16, 'Booking 13 has been confirmed', 'booking_confirmed', 13),
(16, 'Booking 15 has been confirmed', 'booking_confirmed', 15),
(17, 'Booking 17 has been confirmed', 'booking_confirmed', 17),
(18, 'Booking 19 has been confirmed', 'booking_confirmed', 19),
(18, 'Booking 21 has been confirmed', 'booking_confirmed', 21),
(19, 'Booking 23 has been confirmed', 'booking_confirmed', 23),
(20, 'Booking 25 has been confirmed', 'booking_confirmed', 25),
(20, 'Booking 27 has been confirmed', 'booking_confirmed', 27),
(21, 'Booking 29 has been confirmed', 'booking_confirmed', 29),
(22, 'Booking 31 has been confirmed', 'booking_confirmed', 31),
(22, 'Booking 33 has been confirmed', 'booking_confirmed', 33),
(23, 'Booking 35 has been confirmed', 'booking_confirmed', 35),
(24, 'Booking 37 has been confirmed', 'booking_confirmed', 37),
(24, 'Booking 39 has been confirmed', 'booking_confirmed', 39),
(25, 'Booking 41 has been confirmed', 'booking_confirmed', 41),
(26, 'Booking 43 has been confirmed', 'booking_confirmed', 43),
(26, 'Booking 45 has been confirmed', 'booking_confirmed', 45),
(27, 'Booking 47 has been confirmed', 'booking_confirmed', 47),
(28, 'Booking 49 has been confirmed', 'booking_confirmed', 49),
(12, 'Booking 1 has been confirmed', 'booking_confirmed', 1),
(12, 'Booking 3 has been confirmed', 'booking_confirmed', 3),
(13, 'Booking 5 has been confirmed', 'booking_confirmed', 5),
(14, 'Booking 7 has been confirmed', 'booking_confirmed', 7),
(14, 'Booking 9 has been confirmed', 'booking_confirmed', 9),
(15, 'Booking 11 has been confirmed', 'booking_confirmed', 11),
(16, 'Booking 13 has been confirmed', 'booking_confirmed', 13),
(16, 'Booking 15 has been confirmed', 'booking_confirmed', 15),
(17, 'Booking 17 has been confirmed', 'booking_confirmed', 17),
(18, 'Booking 19 has been confirmed', 'booking_confirmed', 19),
(18, 'Booking 21 has been confirmed', 'booking_confirmed', 21),
(19, 'Booking 23 has been confirmed', 'booking_confirmed', 23),
(20, 'Booking 25 has been confirmed', 'booking_confirmed', 25),
(20, 'Booking 27 has been confirmed', 'booking_confirmed', 27),
(21, 'Booking 29 has been confirmed', 'booking_confirmed', 29),
(22, 'Booking 31 has been confirmed', 'booking_confirmed', 31),
(22, 'Booking 33 has been confirmed', 'booking_confirmed', 33),
(23, 'Booking 35 has been confirmed', 'booking_confirmed', 35),
(24, 'Booking 37 has been confirmed', 'booking_confirmed', 37),
(24, 'Booking 39 has been confirmed', 'booking_confirmed', 39),
(25, 'Booking 41 has been confirmed', 'booking_confirmed', 41),
(26, 'Booking 43 has been confirmed', 'booking_confirmed', 43),
(26, 'Booking 45 has been confirmed', 'booking_confirmed', 45),
(27, 'Booking 47 has been confirmed', 'booking_confirmed', 47),
(28, 'Booking 49 has been confirmed', 'booking_confirmed', 49);

-- 50 thông báo cho tin nhắn mới
INSERT INTO notifications (user_id, message, type, related_id) VALUES
(2, 'New message from user 12', 'new_message_conversation', 1),
(12, 'New message from user 2', 'new_message_conversation', 2),
(3, 'New message from user 12', 'new_message_conversation', 3),
(12, 'New message from user 3', 'new_message_conversation', 4),
(4, 'New message from user 12', 'new_message_conversation', 5),
(12, 'New message from user 4', 'new_message_conversation', 6),
(5, 'New message from user 13', 'new_message_conversation', 7),
(13, 'New message from user 5', 'new_message_conversation', 8),
(6, 'New message from user 13', 'new_message_conversation', 9),
(13, 'New message from user 6', 'new_message_conversation', 10),
(7, 'New message from user 13', 'new_message_conversation', 11),
(13, 'New message from user 7', 'new_message_conversation', 12),
(8, 'New message from user 14', 'new_message_conversation', 13),
(14, 'New message from user 8', 'new_message_conversation', 14),
(9, 'New message from user 14', 'new_message_conversation', 15),
(14, 'New message from user 9', 'new_message_conversation', 16),
(10, 'New message from user 14', 'new_message_conversation', 17),
(14, 'New message from user 10', 'new_message_conversation', 18),
(2, 'New message from user 15', 'new_message_conversation', 19),
(15, 'New message from user 2', 'new_message_conversation', 20),
(3, 'New message from user 15', 'new_message_conversation', 21),
(15, 'New message from user 3', 'new_message_conversation', 22),
(4, 'New message from user 15', 'new_message_conversation', 23),
(15, 'New message from user 4', 'new_message_conversation', 24),
(5, 'New message from user 16', 'new_message_conversation', 25),
(16, 'New message from user 5', 'new_message_conversation', 26),
(6, 'New message from user 16', 'new_message_conversation', 27),
(16, 'New message from user 6', 'new_message_conversation', 28),
(7, 'New message from user 16', 'new_message_conversation', 29),
(16, 'New message from user 7', 'new_message_conversation', 30),
(8, 'New message from user 17', 'new_message_conversation', 31),
(17, 'New message from user 8', 'new_message_conversation', 32),
(9, 'New message from user 17', 'new_message_conversation', 33),
(17, 'New message from user 9', 'new_message_conversation', 34),
(10, 'New message from user 17', 'new_message_conversation', 35),
(17, 'New message from user 10', 'new_message_conversation', 36),
(2, 'New message from user 18', 'new_message_conversation', 37),
(18, 'New message from user 2', 'new_message_conversation', 38),
(3, 'New message from user 18', 'new_message_conversation', 39),
(18, 'New message from user 3', 'new_message_conversation', 40),
(4, 'New message from user 18', 'new_message_conversation', 41),
(18, 'New message from user 4', 'new_message_conversation', 42),
(5, 'New message from user 19', 'new_message_conversation', 43),
(19, 'New message from user 5', 'new_message_conversation', 44),
(6, 'New message from user 19', 'new_message_conversation', 45),
(19, 'New message from user 6', 'new_message_conversation', 46),
(7, 'New message from user 19', 'new_message_conversation', 47),
(19, 'New message from user 7', 'new_message_conversation', 48),
(8, 'New message from user 20', 'new_message_conversation', 49),
(20, 'New message from user 8', 'new_message_conversation', 50);


