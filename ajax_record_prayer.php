<?php
// ============================================
// ملف: ajax_record_prayer.php
// معالجة طلبات AJAX لتسجيل الصلاة على النبي
// ============================================

require_once 'config.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action == 'record') {
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    try {
        // إنشاء الجداول إذا لم تكن موجودة
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS prayer_counts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_type VARCHAR(50) NOT NULL,
                prayer_date DATE NOT NULL,
                prayer_count INT DEFAULT 0,
                last_prayer_time DATETIME,
                UNIQUE KEY unique_user_date (user_id, user_type, prayer_date)
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS prayer_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_type VARCHAR(50) NOT NULL,
                prayer_time DATETIME NOT NULL,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // تسجيل الصلاة
        $stmt = $pdo->prepare("
            INSERT INTO prayer_counts (user_id, user_type, prayer_date, prayer_count, last_prayer_time)
            VALUES (?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
            prayer_count = prayer_count + 1,
            last_prayer_time = ?
        ");
        $stmt->execute([$user_id, $user_type, $today, $now, $now]);
        
        // تسجيل في سجل العمليات
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $log_stmt = $pdo->prepare("INSERT INTO prayer_logs (user_id, user_type, prayer_time, ip_address) VALUES (?, ?, ?, ?)");
        $log_stmt->execute([$user_id, $user_type, $now, $ip]);
        
        // جلب العدد الجديد
        $count_stmt = $pdo->prepare("SELECT prayer_count FROM prayer_counts WHERE user_id = ? AND user_type = ? AND prayer_date = ?");
        $count_stmt->execute([$user_id, $user_type, $today]);
        $today_count = $count_stmt->fetchColumn() ?: 0;
        
        echo json_encode([
            'success' => true,
            'today_count' => $today_count,
            'message' => 'تم تسجيل صلاتك على النبي ﷺ'
        ]);
        
    } catch (PDOException $e) {
        error_log("Prayer record error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()]);
    }
    
} elseif ($action == 'get_stats') {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT prayer_count FROM prayer_counts WHERE user_id = ? AND user_type = ? AND prayer_date = ?");
    $stmt->execute([$user_id, $user_type, $today]);
    $today_count = $stmt->fetchColumn() ?: 0;
    
    echo json_encode([
        'success' => true,
        'today_count' => $today_count
    ]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
}
?>