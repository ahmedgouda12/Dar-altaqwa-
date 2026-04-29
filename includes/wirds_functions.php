<?php
// ============================================
// ملف: includes/wirds_functions.php
// دوال نظام الأوراد اليومية والصلوات (نسخة كاملة)
// آخر تحديث: 2026-04-25
// ============================================

// منع التحميل المباشر
if (!defined('PDO')) {
    // لا تفعل شيئاً
}

// ============================================
// دوال الأوراد
// ============================================

/**
 * جلب قائمة الأوراد النشطة
 */
function getActiveWirds($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM daily_wirds 
        WHERE is_active = 1 
        ORDER BY sort_order, id
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * جلب ورد محدد حسب ID
 */
function getWirdById($pdo, $wird_id) {
    $stmt = $pdo->prepare("SELECT * FROM daily_wirds WHERE id = ?");
    $stmt->execute([$wird_id]);
    return $stmt->fetch();
}

/**
 * جلب تسجيل ورد الطالب ليوم محدد
 */
function getStudentWirdRecord($pdo, $student_id, $wird_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM student_wird_records 
        WHERE student_id = ? AND wird_id = ? AND record_date = ?
    ");
    $stmt->execute([$student_id, $wird_id, $date]);
    return $stmt->fetch();
}

/**
 * تحديث أو إنشاء تسجيل ورد للطالب
 */
function updateStudentWird($pdo, $student_id, $wird_id, $count, $notes = '') {
    $date = date('Y-m-d');
    $wird = getWirdById($pdo, $wird_id);
    
    if (!$wird) return false;
    
    $target = $wird['recommended_count'];
    $is_completed = ($count >= $target);
    $base_points = $count * $wird['points_per_unit'];
    $points = $base_points;
    
    // مكافأة إضافية إذا تجاوز الحد
    if ($wird['bonus_threshold'] > 0 && $count >= $wird['bonus_threshold']) {
        $points += $wird['bonus_points'];
    }
    
    // تحديث السلسلة اليومية
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $yesterday_record = getStudentWirdRecord($pdo, $student_id, $wird_id, $yesterday);
    
    $new_streak = 0;
    if ($is_completed) {
        $new_streak = ($yesterday_record && $yesterday_record['is_completed']) ? $yesterday_record['streak_days'] + 1 : 1;
    }
    
    // تحديث السجل
    $check = getStudentWirdRecord($pdo, $student_id, $wird_id, $date);
    
    if ($check) {
        $stmt = $pdo->prepare("
            UPDATE student_wird_records SET
                current_count = ?,
                is_completed = ?,
                points_earned = ?,
                streak_days = ?,
                notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$count, $is_completed, $points, $new_streak, $notes, $check['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO student_wird_records 
            (student_id, wird_id, record_date, current_count, target_count, is_completed, points_earned, streak_days, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $wird_id, $date, $count, $target, $is_completed, $points, $new_streak, $notes]);
    }
    
    // تحديث إجمالي نقاط الطالب
    updateTotalStudentPoints($pdo, $student_id);
    
    return true;
}

/**
 * الحصول على إحصائيات أوراد الطالب
 */
function getStudentWirdStats($pdo, $student_id, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT record_date) as total_days,
            SUM(current_count) as total_count,
            SUM(points_earned) as total_points,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_days,
            MAX(streak_days) as best_streak
        FROM student_wird_records
        WHERE student_id = ? AND record_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ");
    $stmt->execute([$student_id, $days]);
    return $stmt->fetch();
}

// ============================================
// دوال الصلوات
// ============================================

/**
 * جلب قائمة الصلوات النشطة
 */
function getActivePrayers($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM prayers 
        WHERE is_active = 1 
        ORDER BY sort_order, prayer_time
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * جلب صلاة محددة حسب ID
 */
function getPrayerById($pdo, $prayer_id) {
    $stmt = $pdo->prepare("SELECT * FROM prayers WHERE id = ?");
    $stmt->execute([$prayer_id]);
    return $stmt->fetch();
}

/**
 * جلب تسجيل صلاة الطالب ليوم محدد
 */
function getStudentPrayerRecord($pdo, $student_id, $prayer_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM student_prayer_records 
        WHERE student_id = ? AND prayer_id = ? AND prayer_date = ?
    ");
    $stmt->execute([$student_id, $prayer_id, $date]);
    return $stmt->fetch();
}

/**
 * تسجيل صلاة الطالب
 */
function recordStudentPrayer($pdo, $student_id, $prayer_id, $status, $notes = '') {
    $date = date('Y-m-d');
    $prayer = getPrayerById($pdo, $prayer_id);
    
    if (!$prayer) return false;
    
    $is_jamaa = ($status == 'jamaa');
    $points = 0;
    
    if ($status == 'jamaa') {
        $points = $prayer['points_jamaa'];
    } elseif ($status == 'on_time') {
        $points = $prayer['points_on_time'];
    } elseif ($status == 'late') {
        $points = $prayer['points_late'];
    } else {
        $points = 0;
    }
    
    $check = getStudentPrayerRecord($pdo, $student_id, $prayer_id, $date);
    
    if ($check) {
        $stmt = $pdo->prepare("
            UPDATE student_prayer_records SET
                status = ?,
                is_jamaa = ?,
                points_earned = ?,
                notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $is_jamaa, $points, $notes, $check['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO student_prayer_records 
            (student_id, prayer_id, prayer_date, status, is_jamaa, points_earned, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$student_id, $prayer_id, $date, $status, $is_jamaa, $points, $notes]);
    }
    
    // تحديث إجمالي نقاط الطالب
    updateTotalStudentPoints($pdo, $student_id);
    
    return true;
}

/**
 * الحصول على إحصائيات صلوات الطالب
 */
function getStudentPrayerStats($pdo, $student_id, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_prayers,
            SUM(CASE WHEN status = 'jamaa' THEN 1 ELSE 0 END) as jamaa_count,
            SUM(CASE WHEN status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_count,
            SUM(points_earned) as total_points,
            COUNT(DISTINCT prayer_date) as active_days
        FROM student_prayer_records
        WHERE student_id = ? AND prayer_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ");
    $stmt->execute([$student_id, $days]);
    return $stmt->fetch();
}

// ============================================
// دوال النقاط والمستويات
// ============================================

/**
 * تحديث إجمالي نقاط الطالب من جميع المصادر
 */
// ============================================
// تحديث إجمالي نقاط الطالب من جميع المصادر
// ============================================
function updateTotalStudentPoints($pdo, $student_id) {
    // 1. نقاط السور المحفوظة (كل سورة = 10 نقاط)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_surah_progress WHERE student_id = ? AND completed = 1");
    $stmt->execute([$student_id]);
    $quran_points = $stmt->fetchColumn() * 10;
    
    // 2. نقاط الأوراد
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) as total FROM student_wird_records WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $wird_points = $stmt->fetchColumn();
    
    // 3. نقاط الصلوات
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) as total FROM student_prayer_records WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $prayer_points = $stmt->fetchColumn();
    
    // 4. نقاط الحضور (كل يوم حضور = 5 نقاط)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE person_type = 'student' AND person_id = ? AND status = 'present'");
    $stmt->execute([$student_id]);
    $attendance_points = $stmt->fetchColumn() * 5;
    
    // 5. نقاط الحفظ اليومي
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) FROM points_log WHERE student_id = ? AND points_type = 'daily_memorization'");
    $stmt->execute([$student_id]);
    $daily_memorization_points = $stmt->fetchColumn();
    
    // 6. نقاط الأوراد الإضافية
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) FROM points_log WHERE student_id = ? AND points_type = 'custom_wird'");
    $stmt->execute([$student_id]);
    $custom_wird_points = $stmt->fetchColumn();
    
    $total_points = ($quran_points ?: 0) + ($wird_points ?: 0) + ($prayer_points ?: 0) + 
                    ($attendance_points ?: 0) + ($daily_memorization_points ?: 0) + 
                    ($custom_wird_points ?: 0);
    
    // تحديد المستوى
    $level = getLevelFromPoints($total_points);
    $level_name = getLevelName($level);
    $level_icon = getLevelIcon($level);
    
    // ============================================
    // التحقق من وجود الجدول والأعمدة أولاً
    // ============================================
    try {
        // التحقق من وجود جدول student_points
        $table_check = $pdo->query("SHOW TABLES LIKE 'student_points'");
        if ($table_check->rowCount() == 0) {
            // إنشاء الجدول إذا لم يكن موجوداً
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
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_student (student_id),
                    INDEX idx_points (total_points)
                )
            ");
        }
        
        // التحقق من وجود الأعمدة المطلوبة
        $columns = $pdo->query("SHOW COLUMNS FROM student_points");
        $existing_columns = $columns->fetchAll(PDO::FETCH_COLUMN);
        
        // إضافة الأعمدة المفقودة إذا لزم الأمر
        if (!in_array('total_wird_points', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN total_wird_points INT DEFAULT 0");
        }
        if (!in_array('total_prayer_points', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN total_prayer_points INT DEFAULT 0");
        }
        if (!in_array('total_quran_points', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN total_quran_points INT DEFAULT 0");
        }
        if (!in_array('total_attendance_points', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN total_attendance_points INT DEFAULT 0");
        }
        if (!in_array('daily_streak', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN daily_streak INT DEFAULT 0");
        }
        if (!in_array('best_streak', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN best_streak INT DEFAULT 0");
        }
        if (!in_array('last_activity_date', $existing_columns)) {
            $pdo->exec("ALTER TABLE student_points ADD COLUMN last_activity_date DATE");
        }
        
        // الآن نقوم بالتحديث أو الإدراج (بدون created_at)
        $check = $pdo->prepare("SELECT id FROM student_points WHERE student_id = ?");
        $check->execute([$student_id]);
        
        if ($check->fetch()) {
            // تحديث السجل الموجود
            $stmt = $pdo->prepare("
                UPDATE student_points SET
                    total_points = ?,
                    total_wird_points = ?,
                    total_prayer_points = ?,
                    total_quran_points = ?,
                    total_attendance_points = ?,
                    level = ?,
                    level_name = ?
                WHERE student_id = ?
            ");
            $stmt->execute([
                $total_points,
                $wird_points,
                $prayer_points,
                $quran_points,
                $attendance_points,
                $level,
                $level_name,
                $student_id
            ]);
        } else {
            // إدراج سجل جديد (بدون created_at)
            $stmt = $pdo->prepare("
                INSERT INTO student_points 
                (student_id, total_points, total_wird_points, total_prayer_points, 
                 total_quran_points, total_attendance_points, level, level_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id,
                $total_points,
                $wird_points,
                $prayer_points,
                $quran_points,
                $attendance_points,
                $level,
                $level_name
            ]);
        }
        
    } catch (PDOException $e) {
        // تسجيل الخطأ ولكن لا نوقفه (حتى لا يتعطل النظام)
        error_log("Error in updateTotalStudentPoints: " . $e->getMessage());
    }
    
    return [
        'total_points' => $total_points,
        'level' => $level,
        'level_name' => $level_name,
        'level_icon' => $level_icon
    ];
}

/**
 * تحديد المستوى بناءً على النقاط
 */
function getLevelFromPoints($points) {
    if ($points >= 1000) return 5;
    if ($points >= 500) return 4;
    if ($points >= 200) return 3;
    if ($points >= 50) return 2;
    return 1;
}

/**
 * الحصول على اسم المستوى
 */
function getLevelName($level) {
    $names = [
        1 => 'مبتدئ',
        2 => 'طالب',
        3 => 'متميز',
        4 => 'حافظ',
        5 => 'مجيد'
    ];
    return $names[$level] ?? 'مبتدئ';
}

/**
 * الحصول على أيقونة المستوى
 */
function getLevelIcon($level) {
    $icons = [
        1 => '🌱',
        2 => '📚',
        3 => '⭐',
        4 => '🏆',
        5 => '👑'
    ];
    return $icons[$level] ?? '🌟';
}

// ============================================
// دوال الترتيب والإحصائيات
// ============================================

/**
 * جلب أفضل الطلاب في الأوراد
 */
function getTopStudentsByWirds($pdo, $wird_id = null, $limit = 10, $teacher_id = null) {
    $sql = "
        SELECT 
            s.id,
            s.name,
            s.category,
            t.name as teacher_name,
            COALESCE(SUM(swr.points_earned), 0) as total_points,
            COALESCE(SUM(CASE WHEN swr.is_completed = 1 THEN 1 ELSE 0 END), 0) as days_completed,
            COALESCE(MAX(swr.streak_days), 0) as best_streak
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN student_wird_records swr ON s.id = swr.student_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($wird_id !== null) {
        $sql .= " AND swr.wird_id = ?";
        $params[] = $wird_id;
    }
    
    if ($teacher_id !== null && $teacher_id > 0) {
        $sql .= " AND s.teacher_id = ?";
        $params[] = $teacher_id;
    }
    
    $sql .= " GROUP BY s.id ORDER BY total_points DESC LIMIT " . (int)$limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * جلب أفضل الطلاب في الصلوات
 */
function getTopStudentsByPrayers($pdo, $limit = 10, $teacher_id = null) {
    $sql = "
        SELECT 
            s.id,
            s.name,
            s.category,
            t.name as teacher_name,
            COALESCE(SUM(spr.points_earned), 0) as total_points,
            COALESCE(SUM(CASE WHEN spr.status = 'jamaa' THEN 1 ELSE 0 END), 0) as jamaa_count,
            COUNT(DISTINCT CASE WHEN spr.status != 'missed' THEN spr.prayer_date END) as prayed_days
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN student_prayer_records spr ON s.id = spr.student_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($teacher_id !== null && $teacher_id > 0) {
        $sql .= " AND s.teacher_id = ?";
        $params[] = $teacher_id;
    }
    
    $sql .= " GROUP BY s.id ORDER BY total_points DESC LIMIT " . (int)$limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * جلب ترتيب الطالب بين أقرانه
 */
function getStudentRankInWirds($pdo, $student_id, $wird_id = null) {
    // الحصول على نقاط الطالب
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(points_earned), 0) as student_points
        FROM student_wird_records
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student_points = $stmt->fetchColumn();
    
    // حساب عدد الطلاب الذين تفوقوا عليه
    $sql = "
        SELECT COUNT(DISTINCT s.id) + 1 as rank
        FROM students s
        LEFT JOIN student_wird_records swr ON s.id = swr.student_id
        WHERE COALESCE(SUM(swr.points_earned), 0) > ?
    ";
    
    $params = [$student_points];
    
    if ($wird_id !== null) {
        $sql .= " AND swr.wird_id = ?";
        $params[] = $wird_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 1;
}

// ============================================
// دوال الأوراد الإضافية (للوالدين والطلاب)
// ============================================

/**
 * إنشاء جدول الأوراد الإضافية إذا لم يكن موجوداً
 */
function ensureCustomWirdsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS custom_wirds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            wird_name VARCHAR(255) NOT NULL,
            wird_type ENUM('quran', 'adkar', 'prayer', 'other') DEFAULT 'other',
            points_per_unit INT DEFAULT 1,
            target_units INT DEFAULT 1,
            unit_type ENUM('page', 'ayah', 'time', 'count') DEFAULT 'count',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_type (wird_type)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS custom_wird_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            wird_id INT NOT NULL,
            record_date DATE NOT NULL,
            units_completed INT DEFAULT 0,
            points_earned INT DEFAULT 0,
            notes TEXT,
            recorded_by VARCHAR(50) DEFAULT 'student',
            recorded_by_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_wird (wird_id),
            INDEX idx_date (record_date),
            UNIQUE KEY unique_student_wird_date (student_id, wird_id, record_date)
        )
    ");
}

/**
 * إضافة ورد إضافي للطالب
 */
function addCustomWird($pdo, $student_id, $wird_name, $wird_type, $points_per_unit, $target_units, $unit_type) {
    $stmt = $pdo->prepare("
        INSERT INTO custom_wirds (student_id, wird_name, wird_type, points_per_unit, target_units, unit_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$student_id, $wird_name, $wird_type, $points_per_unit, $target_units, $unit_type]);
}

/**
 * حذف ورد إضافي
 */
function deleteCustomWird($pdo, $wird_id, $student_id) {
    $pdo->prepare("DELETE FROM custom_wird_records WHERE wird_id = ?")->execute([$wird_id]);
    $stmt = $pdo->prepare("DELETE FROM custom_wirds WHERE id = ? AND student_id = ?");
    return $stmt->execute([$wird_id, $student_id]);
}

/**
 * جلب الأوراد الإضافية للطالب
 */
function getStudentCustomWirds($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT w.*,
               (SELECT SUM(units_completed) FROM custom_wird_records WHERE wird_id = w.id) as total_units,
               (SELECT SUM(points_earned) FROM custom_wird_records WHERE wird_id = w.id) as total_points,
               (SELECT units_completed FROM custom_wird_records WHERE wird_id = w.id AND record_date = CURDATE()) as today_units
        FROM custom_wirds w
        WHERE w.student_id = ?
        ORDER BY w.wird_type, w.wird_name
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

/**
 * تسجيل تقدم في ورد إضافي
 */
function recordCustomWirdProgress($pdo, $student_id, $wird_id, $units_completed, $notes = '', $recorded_by = 'student', $recorded_by_id = null) {
    $today = date('Y-m-d');
    
    // جلب معلومات الورد
    $stmt = $pdo->prepare("SELECT * FROM custom_wirds WHERE id = ? AND student_id = ?");
    $stmt->execute([$wird_id, $student_id]);
    $wird = $stmt->fetch();
    
    if (!$wird) return false;
    
    $points_earned = $units_completed * $wird['points_per_unit'];
    
    // التحقق من وجود تسجيل سابق لليوم
    $check = $pdo->prepare("
        SELECT id FROM custom_wird_records 
        WHERE student_id = ? AND wird_id = ? AND record_date = ?
    ");
    $check->execute([$student_id, $wird_id, $today]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("
            UPDATE custom_wird_records SET
                units_completed = units_completed + ?,
                points_earned = points_earned + ?,
                notes = CONCAT(IFNULL(notes, ''), '\n', ?)
            WHERE student_id = ? AND wird_id = ? AND record_date = ?
        ");
        $stmt->execute([$units_completed, $points_earned, $notes, $student_id, $wird_id, $today]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO custom_wird_records 
            (student_id, wird_id, record_date, units_completed, points_earned, notes, recorded_by, recorded_by_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $wird_id, $today, $units_completed, $points_earned, $notes, $recorded_by, $recorded_by_id]);
    }
    
    // إضافة سجل نقاط
    if (function_exists('addPointsLog')) {
        addPointsLog($pdo, $student_id, $points_earned, 'custom_wird', "تسجيل في الورد: {$wird['wird_name']} ($units_completed {$wird['unit_type']})");
    }
    
    // تحديث إجمالي نقاط الطالب
    updateTotalStudentPoints($pdo, $student_id);
    
    return true;
}

// التأكد من وجود الجداول عند تحميل الملف
ensureCustomWirdsTable($pdo);
?>