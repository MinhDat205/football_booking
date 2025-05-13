<?php
// Đặt header Content-Type để đảm bảo trả về JSON
header('Content-Type: application/json; charset=UTF-8');

// Bật hiển thị lỗi để debug (tạm thời, sau khi sửa lỗi thì tắt đi)
ini_set('display_errors', 0); // Tắt hiển thị lỗi trực tiếp để tránh trả về HTML
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Xử lý lỗi PHP để tránh trả về HTML
try {
    // Kiểm tra và bao gồm file config.php
    if (!file_exists('includes/config.php')) {
        throw new Exception('File config.php không tồn tại.');
    }
    require_once 'includes/config.php';

    // Ánh xạ tên thành phố từ tiếng Việt sang tiếng Anh
    $city_mapping = [
        'Hà Nội' => 'Hanoi',
        'Thành phố Hồ Chí Minh' => 'Ho Chi Minh City',
        'Đà Nẵng' => 'Da Nang',
        // Thêm các ánh xạ khác nếu cần
    ];

    // Hàm chuẩn hóa tên thành phố
    function normalizeCityName($city) {
        global $city_mapping;
        // Loại bỏ dấu ngoặc kép và các ký tự không mong muốn
        $city = trim($city, " \"\t\n\r\0\x0B");
        // Ánh xạ tên thành phố nếu có trong $city_mapping
        return isset($city_mapping[$city]) ? $city_mapping[$city] : $city;
    }

    // Hàm lấy dữ liệu thời tiết
    function getWeather($city, $date) {
        $api_key = '57e1eab72d8a64ece2b2a55424d83598'; // API Key hợp lệ
        $city = normalizeCityName($city); // Chuẩn hóa tên thành phố
        $url = "https://api.openweathermap.org/data/2.5/forecast?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric";

        // Ghi log thông tin yêu cầu
        error_log("Weather API request: city=$city, date=$date, url=$url");

        // Kiểm tra xem ngày có vượt quá phạm vi 5 ngày không
        $current_date = strtotime(date('Y-m-d'));
        $target_date = strtotime(date('Y-m-d', strtotime($date)));
        $days_diff = ($target_date - $current_date) / (60 * 60 * 24);
        if ($days_diff > 5) {
            $error_msg = "API OpenWeatherMap chỉ hỗ trợ dự báo 5 ngày. Ngày $date vượt quá phạm vi.";
            error_log($error_msg);
            return [
                'error' => $error_msg
            ];
        }

        // Sử dụng curl để xử lý yêu cầu API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout sau 10 giây
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === FALSE || $http_code !== 200) {
            $error_msg = 'Không thể lấy dữ liệu thời tiết. Mã lỗi HTTP: ' . $http_code . '. Lỗi: ' . $error;
            error_log($error_msg);
            return [
                'error' => $error_msg
            ];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            $error_msg = 'Lỗi phân tích JSON từ API OpenWeatherMap.';
            error_log($error_msg . ' Response: ' . $response);
            return [
                'error' => $error_msg
            ];
        }

        if (!isset($data['list'])) {
            $error_msg = 'Dữ liệu thời tiết không khả dụng. Mã lỗi từ API: ' . (isset($data['cod']) ? $data['cod'] : 'Không xác định') . '. Thông điệp: ' . (isset($data['message']) ? $data['message'] : 'Không có thông điệp');
            error_log($error_msg);
            return [
                'error' => $error_msg
            ];
        }

        // Tìm dữ liệu thời tiết gần nhất với ngày đặt sân
        $target_timestamp = strtotime($date);
        if ($target_timestamp === false) {
            $error_msg = 'Ngày không hợp lệ: ' . $date;
            error_log($error_msg);
            return [
                'error' => $error_msg
            ];
        }

        $closest_weather = null;
        $min_diff = PHP_INT_MAX;

        foreach ($data['list'] as $forecast) {
            $forecast_timestamp = $forecast['dt'];
            $diff = abs($target_timestamp - $forecast_timestamp);
            if ($diff < $min_diff) {
                $min_diff = $diff;
                $closest_weather = $forecast;
            }
        }

        if ($closest_weather) {
            $weather = [
                'description' => $closest_weather['weather'][0]['description'],
                'temperature' => $closest_weather['main']['temp'],
                'humidity' => $closest_weather['main']['humidity'],
                'wind_speed' => $closest_weather['wind']['speed'],
                'rain_probability' => isset($closest_weather['pop']) ? $closest_weather['pop'] : 0, // Xác suất mưa (0-1)
                'cloudiness' => $closest_weather['clouds']['all'], // Độ che phủ mây (%)
            ];

            // Gợi ý vật dụng dựa trên thời tiết
            $suggestions = [];
            // Gợi ý dựa trên tình trạng mưa
            if (stripos($weather['description'], 'rain') !== false) {
                $suggestions[] = "Mang giày chống trượt và áo mưa vì trời có thể mưa.";
            }
            // Gợi ý dựa trên xác suất mưa
            if ($weather['rain_probability'] >= 0.7) {
                $suggestions[] = "Xác suất mưa cao (" . round($weather['rain_probability'] * 100) . "%), nên mang ô hoặc áo mưa.";
            } else if ($weather['rain_probability'] >= 0.3) {
                $suggestions[] = "Có khả năng mưa nhẹ (" . round($weather['rain_probability'] * 100) . "%), bạn có thể mang theo ô phòng trường hợp cần.";
            }
            // Gợi ý dựa trên nhiệt độ
            if ($weather['temperature'] > 30) {
                $suggestions[] = "Mang nước uống và mũ vì trời khá nóng.";
            } elseif ($weather['temperature'] < 15) {
                $suggestions[] = "Mang áo ấm vì trời có thể lạnh.";
            }
            // Gợi ý dựa trên tốc độ gió
            if ($weather['wind_speed'] > 10) {
                $suggestions[] = "Gió mạnh (" . $weather['wind_speed'] . " m/s), tránh các vật dễ rơi và cân nhắc mặc áo gió.";
            } else if ($weather['wind_speed'] > 5) {
                $suggestions[] = "Gió vừa (" . $weather['wind_speed'] . " m/s), bạn nên mặc áo dài tay để tránh lạnh.";
            }
            // Gợi ý dựa trên độ che phủ mây
            if ($weather['cloudiness'] > 80) {
                $suggestions[] = "Trời rất nhiều mây (" . $weather['cloudiness'] . "%), có thể âm u, bạn nên mang theo đèn pin nếu cần.";
            } else if ($weather['cloudiness'] > 50) {
                $suggestions[] = "Trời nhiều mây (" . $weather['cloudiness'] . "%), ánh sáng có thể yếu, chú ý khi di chuyển.";
            }
            // Gợi ý dựa trên độ ẩm
            if ($weather['humidity'] > 80) {
                $suggestions[] = "Độ ẩm cao (" . $weather['humidity'] . "%), có thể cảm thấy oi bức, nên mặc quần áo thoáng mát.";
            } else if ($weather['humidity'] < 30) {
                $suggestions[] = "Độ ẩm thấp (" . $weather['humidity'] . "%), không khí có thể khô, nên mang theo kem dưỡng ẩm.";
            }

            $weather['suggestions'] = $suggestions;

            error_log("Weather data retrieved successfully for city=$city, date=$date");
            return $weather;
        }

        $error_msg = 'Không tìm thấy dữ liệu thời tiết cho ngày này.';
        error_log($error_msg);
        return [
            'error' => $error_msg
        ];
    }

    // Xử lý yêu cầu AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';

        if (empty($city) || empty($date)) {
            $error_msg = 'Vui lòng cung cấp thành phố và ngày.';
            error_log($error_msg);
            echo json_encode(['error' => $error_msg]);
            exit;
        }

        $weather = getWeather($city, $date);
        echo json_encode($weather);
        exit;
    }

    echo json_encode(['error' => 'Yêu cầu không hợp lệ.']);
    exit;
} catch (Exception $e) {
    // Nếu có lỗi PHP, trả về JSON thay vì HTML
    error_log("PHP error in get_weather.php: " . $e->getMessage());
    echo json_encode(['error' => 'Lỗi máy chủ: ' . $e->getMessage()]);
    exit;
}
?>