<?php
// ============================================
// ملف: create_missing_tables.php
// إنشاء الجداول المفقودة في النظام
// ============================================

require_once 'config.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>إنشاء الجداول المفقودة - دار التقوى</title>
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h1 { color: #1e3c3f; border-bottom: 3px solid #c9a96b; padding-bottom: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .btn { display: inline-block; background: #1e3c3f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 إنشاء الجداول المفقودة</h1>
    <p>جاري إنشاء الجداول المطلوبة للنظام...</p>
    <hr>";

// ============================================
// 1. جدول student_daily_evaluations (التقييم اليومي المتقدم)
// ============================================
echo "<h3>1. إنشاء جدول student_daily_evaluations</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_daily_evaluations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT,
            evaluation_date DATE NOT NULL,
            
            -- الحضور (30%)
            attendance_status ENUM('present', 'absent', 'late') DEFAULT 'present',
            attendance_notes TEXT,
            attendance_score DECIMAL(5,2) DEFAULT 0,
            
            -- الحفظ الجديد (20%)
            new_ayah_mistakes INT DEFAULT 0,
            new_haraka_mistakes INT DEFAULT 0,
            new_tajweed_mistakes INT DEFAULT 0,
            new_hesitation_count INT DEFAULT 0,
            new_memorized_ayahs INT DEFAULT 0,
            new_range VARCHAR(255),
            new_memorization_score DECIMAL(5,2) DEFAULT 0,
            
            -- المراجعة القريبة (15%)
            recent_ayah_mistakes INT DEFAULT 0,
            recent_haraka_mistakes INT DEFAULT 0,
            recent_tajweed_mistakes INT DEFAULT 0,
            recent_hesitation_count INT DEFAULT 0,
            recent_reviewed_ayahs INT DEFAULT 0,
            recent_range VARCHAR(255),
            recent_review_score DECIMAL(5,2) DEFAULT 0,
            
            -- المراجعة البعيدة (15%)
            old_ayah_mistakes INT DEFAULT 0,
            old_haraka_mistakes INT DEFAULT 0,
            old_tajweed_mistakes INT DEFAULT 0,
            old_hesitation_count INT DEFAULT 0,
            old_reviewed_ayahs INT DEFAULT 0,
            old_range VARCHAR(255),
            old_review_score DECIMAL(5,2) DEFAULT 0,
            
            -- السلوك (20%)
            behavior_rating INT DEFAULT 3,
            behavior_notes TEXT,
            behavior_score DECIMAL(5,2) DEFAULT 0,
            
            -- النتائج النهائية
            general_notes TEXT,
            total_score DECIMAL(5,2) DEFAULT 0,
            grade VARCHAR(50),
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_student (student_id),
            INDEX idx_date (evaluation_date),
            INDEX idx_teacher (teacher_id),
            UNIQUE KEY unique_student_date (student_id, evaluation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_daily_evaluations بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 2. جدول student_daily_schedule (مواعيد الحفظ اليومي)
// ============================================
echo "<h3>2. إنشاء جدول student_daily_schedule</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_daily_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            preferred_time TIME,
            reminder_minutes INT DEFAULT 15,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_daily_schedule بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 3. جدول daily_memorization (تسجيلات الحفظ اليومي)
// ============================================
echo "<h3>3. إنشاء جدول daily_memorization</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_memorization (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT,
            surah_number INT NOT NULL,
            from_ayah INT,
            to_ayah INT,
            ayahs_count INT DEFAULT 0,
            memorized_date DATE NOT NULL,
            memorized_time TIME DEFAULT CURRENT_TIME,
            scheduled_time TIME,
            is_on_time TINYINT DEFAULT 1,
            delay_minutes INT DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_date (memorized_date),
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول daily_memorization بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 4. جدول student_timer_settings (إعدادات المؤقت)
// ============================================
echo "<h3>4. إنشاء جدول student_timer_settings</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_timer_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            teacher_id INT,
            time_per_ayah INT DEFAULT 30,
            time_per_page INT DEFAULT 60,
            time_per_surah INT DEFAULT 180,
            base_time INT DEFAULT 60,
            max_time INT DEFAULT 900,
            warning_time INT DEFAULT 60,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_timer_settings بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 5. جدول recitation_sessions (جلسات التسميع)
// ============================================
echo "<h3>5. إنشاء جدول recitation_sessions</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS recitation_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            session_date DATE NOT NULL,
            session_type ENUM('new_memorization', 'revision', 'exam') DEFAULT 'new_memorization',
            start_time DATETIME NOT NULL,
            end_time DATETIME,
            scheduled_duration INT DEFAULT 0,
            duration INT DEFAULT 0,
            surah_number INT NOT NULL,
            from_ayah INT,
            to_ayah INT,
            ayahs_count INT DEFAULT 0,
            completion_percentage INT DEFAULT 0,
            notes TEXT,
            status ENUM('in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_teacher (teacher_id),
            INDEX idx_date (session_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول recitation_sessions بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 6. جدول timer_alerts (تنبيهات المؤقت)
// ============================================
echo "<h3>6. إنشاء جدول timer_alerts</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timer_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            alert_time DATETIME NOT NULL,
            alert_type ENUM('warning', 'time_up', 'extension', 'start') DEFAULT 'warning',
            message TEXT,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول timer_alerts بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 7. جدول students_of_month (الطلاب المثاليون)
// ============================================
echo "<h3>7. إنشاء جدول students_of_month</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students_of_month (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            month_year VARCHAR(7) NOT NULL,
            category ENUM('boy', 'girl', 'child', 'woman') NOT NULL,
            rank ENUM('first', 'second', 'third') NOT NULL,
            attendance_rate DECIMAL(5,2),
            level_achievement TEXT,
            awarded_by INT,
            awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_month_rank (student_id, month_year, rank),
            INDEX idx_student (student_id),
            INDEX idx_month (month_year),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول students_of_month بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 8. جدول surah_mindmaps (الخرائط الذهنية)
// ============================================
echo "<h3>8. إنشاء جدول surah_mindmaps</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surah_mindmaps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            surah_number INT NOT NULL UNIQUE,
            title VARCHAR(255),
            main_topics TEXT,
            key_verses TEXT,
            connections TEXT,
            lessons TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_surah (surah_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول surah_mindmaps بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 9. جدول mindmap_nodes (عقد الخرائط الذهنية)
// ============================================
echo "<h3>9. إنشاء جدول mindmap_nodes</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mindmap_nodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            surah_number INT NOT NULL,
            node_type ENUM('main', 'sub') DEFAULT 'main',
            parent_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            icon VARCHAR(50) DEFAULT 'fa-circle',
            color VARCHAR(20) DEFAULT '#c9a96b',
            level INT DEFAULT 1,
            ayah_range VARCHAR(100),
            `order` INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_surah (surah_number),
            INDEX idx_parent (parent_id),
            FOREIGN KEY (parent_id) REFERENCES mindmap_nodes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول mindmap_nodes بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 10. جدول student_mindmap_progress (تقدم الطالب في الخرائط)
// ============================================
echo "<h3>10. إنشاء جدول student_mindmap_progress</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_mindmap_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            surah_number INT NOT NULL,
            viewed_at DATETIME NOT NULL,
            completed TINYINT DEFAULT 0,
            completed_at DATETIME,
            notes TEXT,
            UNIQUE KEY unique_student_surah (student_id, surah_number),
            INDEX idx_student (student_id),
            INDEX idx_surah (surah_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_mindmap_progress بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 11. جدول interactive_questions (الأسئلة التفاعلية)
// ============================================
echo "<h3>11. إنشاء جدول interactive_questions</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interactive_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            surah_number INT NOT NULL,
            ayah_number INT,
            question_type ENUM('general', 'tafsir', 'reason', 'language', 'history') DEFAULT 'general',
            difficulty_level INT DEFAULT 1,
            question_text TEXT NOT NULL,
            options JSON NOT NULL,
            correct_option INT NOT NULL,
            explanation TEXT,
            points_reward INT DEFAULT 10,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_surah (surah_number),
            INDEX idx_difficulty (difficulty_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول interactive_questions بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 12. جدول student_answers (إجابات الطلاب)
// ============================================
echo "<h3>12. إنشاء جدول student_answers</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_answers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_option INT NOT NULL,
            is_correct TINYINT NOT NULL,
            time_taken INT DEFAULT 0,
            points_earned INT DEFAULT 0,
            answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_question (question_id),
            UNIQUE KEY unique_student_question (student_id, question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_answers بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 13. جدول student_question_stats (إحصائيات الأسئلة)
// ============================================
echo "<h3>13. إنشاء جدول student_question_stats</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_question_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            total_answered INT DEFAULT 0,
            correct_answers INT DEFAULT 0,
            total_points INT DEFAULT 0,
            current_streak INT DEFAULT 0,
            best_streak INT DEFAULT 0,
            last_answer_date DATE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_question_stats بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 14. جدول group_recitation_sessions (جلسات التسميع الجماعية)
// ============================================
echo "<h3>14. إنشاء جدول group_recitation_sessions</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_recitation_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            notes TEXT,
            status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_teacher (teacher_id),
            INDEX idx_date (session_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول group_recitation_sessions بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 15. جدول session_participants (مشاركي الجلسة الجماعية)
// ============================================
echo "<h3>15. إنشاء جدول session_participants</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS session_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            slot_order INT NOT NULL,
            slot_start TIME,
            slot_end TIME,
            duration_minutes INT DEFAULT 15,
            actual_duration INT,
            started_at DATETIME,
            ended_at DATETIME,
            evaluation_score INT,
            evaluation_notes TEXT,
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            INDEX idx_session (session_id),
            INDEX idx_student (student_id),
            UNIQUE KEY unique_session_student (session_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول session_participants بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 16. جدول timer_logs (سجل المؤقتات)
// ============================================
echo "<h3>16. إنشاء جدول timer_logs</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timer_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT,
            slot_order INT,
            start_time DATETIME,
            end_time DATETIME,
            duration_seconds INT,
            status ENUM('started', 'completed', 'interrupted') DEFAULT 'started',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول timer_logs بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 17. جدول scattered_auto_timer_settings (إعدادات المؤقت التلقائي للمتفرقين)
// ============================================
echo "<h3>17. إنشاء جدول scattered_auto_timer_settings</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scattered_auto_timer_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ring_id INT NOT NULL UNIQUE,
            auto_start TINYINT DEFAULT 1,
            reminder_minutes INT DEFAULT 5,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ring (ring_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول scattered_auto_timer_settings بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 18. جدول scattered_daily_sessions (جلسات المتفرقين اليومية)
// ============================================
echo "<h3>18. إنشاء جدول scattered_daily_sessions</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scattered_daily_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ring_id INT NOT NULL,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            total_minutes INT DEFAULT 0,
            students_count INT DEFAULT 0,
            status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ring (ring_id),
            INDEX idx_date (session_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول scattered_daily_sessions بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 19. جدول scattered_session_slots (فترات الطلاب في جلسات المتفرقين)
// ============================================
echo "<h3>19. إنشاء جدول scattered_session_slots</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scattered_session_slots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            slot_order INT NOT NULL,
            slot_start TIME NOT NULL,
            slot_end TIME NOT NULL,
            duration_minutes INT DEFAULT 0,
            actual_duration INT,
            started_at DATETIME,
            ended_at DATETIME,
            evaluation_score INT,
            evaluation_notes TEXT,
            status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
            INDEX idx_session (session_id),
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول scattered_session_slots بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 20. جدول scattered_timer_logs (سجل المؤقتات للمتفرقين)
// ============================================
echo "<h3>20. إنشاء جدول scattered_timer_logs</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scattered_timer_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            slot_id INT,
            student_id INT,
            scheduled_start DATETIME,
            scheduled_end DATETIME,
            actual_start DATETIME,
            actual_end DATETIME,
            status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول scattered_timer_logs بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 21. جدول annual_reports (التقارير السنوية)
// ============================================
echo "<h3>21. إنشاء جدول annual_reports</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS annual_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hijri_year INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            summary TEXT,
            content LONGTEXT,
            total_students INT DEFAULT 0,
            total_completed_quran INT DEFAULT 0,
            total_memorized_surahs INT DEFAULT 0,
            total_certificates INT DEFAULT 0,
            created_by INT,
            status ENUM('draft', 'published') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_year (hijri_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول annual_reports بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// 22. جدول teacher_achievement_reports (تقارير إنجازات المعلمين)
// ============================================
echo "<h3>22. إنشاء جدول teacher_achievement_reports</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_achievement_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            hijri_year INT NOT NULL,
            hijri_month INT NOT NULL,
            report_title VARCHAR(255) NOT NULL,
            report_content LONGTEXT,
            total_students INT DEFAULT 0,
            new_memorized_surahs INT DEFAULT 0,
            completed_quran_count INT DEFAULT 0,
            top_students TEXT,
            notes TEXT,
            status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft',
            approved_by INT,
            approved_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_teacher (teacher_id),
            INDEX idx_year_month (hijri_year, hijri_month),
            UNIQUE KEY unique_teacher_month (teacher_id, hijri_year, hijri_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo <div class='success'>✅ تم إنشاء جدول teacher_achievement_reports بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// ============================================
// إضافة بيانات افتراضية لجدول surah_mindmaps
// ============================================
echo "<h3>إضافة بيانات افتراضية للسور</h3>";
try {
    $check = $pdo->query("SELECT COUNT(*) FROM surah_mindmaps")->fetchColumn();
    if ($check == 0) {
        for ($i = 1; $i <= 114; $i++) {
            $pdo->prepare("
                INSERT INTO surah_mindmaps (surah_number, title, main_topics, key_verses, connections, lessons)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $i,
                'سورة ' . getSurahName($i),
                json_encode(['التوحيد', 'القصص', 'العبر']),
                json_encode(['الآية الأولى', 'آية الكرسي']),
                json_encode(['سورة السابقة', 'سورة اللاحقة']),
                json_encode(['الصبر', 'التقوى'])
            ]);
        }
        echo "<div class='success'>✅ تم إضافة بيانات افتراضية لـ 114 سورة في جدول الخرائط الذهنية</div>";
    } else {
        echo "<div class='info'>ℹ️ جدول الخرائط الذهنية يحتوي بالفعل على بيانات</div>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ في إضافة البيانات: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<h2>✅ اكتملت عملية إنشاء الجداول!</h2>";
echo "<p>تم إنشاء جميع الجداول المطلوبة للنظام بنجاح.</p>";
echo "<div style='display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px;'>";
echo "<a href='advanced_evaluation.php' class='btn'>الذهاب للتقييم اليومي</a>";
echo "<a href='dashboard.php' class='btn'>الذهاب للوحة التحكم</a>";
echo "<a href='daily_memorization.php' class='btn'>الذهاب للحفظ اليومي</a>";
echo "</div>";
echo "</div></body></html>";
?>