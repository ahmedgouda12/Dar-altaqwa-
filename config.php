<?php
// ============================================
// ملف: config.php - إعدادات النظام الأساسية
// آخر تحديث: 2026-04-26
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Africa/Cairo');

// ============================================
// إعدادات قاعدة البيانات
// ============================================

$host = 'sql200.infinityfree.com';
$dbname = 'if0_41209760_dar_alraqwa_dp';
$username = 'if0_41209760';
$password = 'AL5elWdR3zbAQv';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// ============================================
// إعدادات الجلسة
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ============================================
// تضمين دوال النظام (جميع الدوال موجودة هنا)
// ============================================

require_once 'functions.php';

// ============================================
// تشغيل الغياب التلقائي عند كل زيارة
// ============================================

// ملف لتخزين آخر تاريخ تمت معالجته
$last_processed_file = __DIR__ . '/last_attendance_date.txt';
$last_processed = file_exists($last_processed_file) ? file_get_contents($last_processed_file) : '';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// إذا كان آخر تاريخ معالج أقل من الأمس، نقوم بمعالجة الأمس فقط
if (empty($last_processed) || $last_processed < $yesterday) {
    // التأكد من وجود الدالة قبل استدعائها
    if (function_exists('processDayAttendance')) {
        processDayAttendance($pdo, $yesterday);
        file_put_contents($last_processed_file, $yesterday);
    }
}

// ============================================
// تحديث آخر نشاط للمستخدم (إذا كان مسجلاً)
// ============================================

if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $table = match ($_SESSION['user_type']) {
        'admin' => 'admins',
        'teacher' => 'teachers',
        'student' => 'students',
        'guardian' => 'guardians',
        default => null
    };
    
    if ($table) {
        try {
            // التأكد من وجود عمود last_activity
            $check = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'last_activity'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN last_activity DATETIME NULL");
                $pdo->exec("ALTER TABLE {$table} ADD INDEX idx_last_activity (last_activity)");
            }
            $pdo->prepare("UPDATE {$table} SET last_activity = NOW() WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // تجاهل الأخطاء
        }
    }
    $_SESSION['last_activity'] = time();
}

// ============================================
// إنشاء الجداول المفقودة تلقائياً
// ============================================

try {
    // جدول إحصائيات الأجزاء
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_parts_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            total_parts INT DEFAULT 0,
            total_pages INT DEFAULT 0,
            full_parts INT DEFAULT 0,
            remaining_pages INT DEFAULT 0,
            current_part_progress INT DEFAULT 0,
            current_part_number INT DEFAULT 0,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_parts (total_parts)
        )
    ");
    
    // جدول سجل التحويلات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transfer_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            old_teacher_id INT DEFAULT 0,
            new_teacher_id INT NOT NULL,
            old_ring_id INT NULL,
            new_ring_id INT NULL,
            transfer_reason TEXT,
            transferred_by INT NOT NULL,
            transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id)
        )
    ");
    
    // جدول الإشعارات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type ENUM('student', 'teacher', 'guardian', 'admin') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('success', 'info', 'warning', 'danger') DEFAULT 'info',
            link VARCHAR(500) DEFAULT NULL,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id, user_type),
            INDEX idx_read (is_read)
        )
    ");
    
    // جدول العطل الرسمية
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            description TEXT,
            apply_to ENUM('both', 'teachers', 'students') DEFAULT 'both',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (start_date, end_date)
        )
    ");
    
    // جدول نقاط الطلاب
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_points (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            total_points INT DEFAULT 0,
            total_wird_points INT DEFAULT 0,
            total_prayer_points INT DEFAULT 0,
            total_quran_points INT DEFAULT 0,
            total_attendance_points INT DEFAULT 0,
            level INT DEFAULT 1,
            level_name VARCHAR(50) DEFAULT 'مبتدئ',
            daily_streak INT DEFAULT 0,
            best_streak INT DEFAULT 0,
            last_activity_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_points (total_points)
        )
    ");
    
    // إضافة أعمدة missing إلى attendance
    $check_attendance = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'is_excused'");
    if ($check_attendance->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN is_excused TINYINT DEFAULT 0");
        $pdo->exec("ALTER TABLE attendance ADD COLUMN excuse_reason TEXT NULL");
    }
    
    $check_auto = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'auto_generated'");
    if ($check_auto->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN auto_generated TINYINT DEFAULT 0");
    }
    
    // إضافة أعمدة الإجازات للطلاب
    $check_students_leave = $pdo->query("SHOW COLUMNS FROM students LIKE 'on_leave'");
    if ($check_students_leave->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN on_leave TINYINT DEFAULT 0");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_start_date DATE NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_end_date DATE NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_reason TEXT NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_is_excused TINYINT DEFAULT 0");
    }
    
    // إضافة أعمدة الإجازات للمعلمين
    $check_teachers_leave = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'on_leave'");
    if ($check_teachers_leave->rowCount() == 0) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN on_leave TINYINT DEFAULT 0");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_start_date DATE NULL");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_end_date DATE NULL");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_reason TEXT NULL");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_is_excused TINYINT DEFAULT 0");
    }
    
    // إضافة عطل رسمية افتراضية إذا كان الجدول فارغاً
    $check_holidays = $pdo->query("SELECT COUNT(*) FROM holidays")->fetchColumn();
    if ($check_holidays == 0) {
        $pdo->exec("
            INSERT INTO holidays (name, start_date, end_date, description, apply_to) VALUES
            ('عيد الفطر المبارك', '2026-04-20', '2026-04-22', 'إجازة عيد الفطر المبارك', 'both'),
            ('عيد الأضحى المبارك', '2026-06-27', '2026-06-30', 'إجازة عيد الأضحى المبارك', 'both'),
            ('رأس السنة الهجرية', '2026-07-19', '2026-07-19', 'إجازة رأس السنة الهجرية', 'both'),
            ('المولد النبوي الشريف', '2026-09-28', '2026-09-28', 'إجازة المولد النبوي الشريف', 'both')
        ");
    }
    
    // إضافة عمود work_days للمعلمين إذا لم يكن موجوداً
    $check_work_days = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'work_days'");
    if ($check_work_days->rowCount() == 0) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN work_days VARCHAR(50) DEFAULT '1,2,3,4,5,6,7'");
    }
    
} catch (PDOException $e) {
    // تجاهل الأخطاء (قد تكون الجداول موجودة بالفعل)
}

// لا يوجد echo هنا - هذا مهم لمنع أخطاء headers
?>