<?php
// ============================================
// ملف: manage_holidays.php - إدارة الإجازات (نسخة كاملة محدثة)
// آخر تحديث: 2026-04-18
// ============================================

ob_start();

require_once 'config.php';
require_once 'functions.php';

// السماح لكل من المسؤول والمعلم
if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'إدارة الإجازات';
$is_admin = isAdmin();
$teacher_id = isTeacher() ? $_SESSION['user_id'] : 0;

$message = '';
$message_type = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'my_requests';

// ============================================
// إنشاء الجداول إذا لم تكن موجودة
// ============================================
try {
    // التحقق من وجود الأعمدة في جدول teachers
    $check_columns = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'on_leave'");
    if ($check_columns->rowCount() == 0) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN on_leave TINYINT DEFAULT 0");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_start_date DATE NULL");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_end_date DATE NULL");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_reason TEXT NULL");
        $pdo->exec("ALTER TABLE teachers ADD COLUMN leave_is_excused TINYINT DEFAULT 0");
    }
    
    $check_columns = $pdo->query("SHOW COLUMNS FROM students LIKE 'on_leave'");
    if ($check_columns->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN on_leave TINYINT DEFAULT 0");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_start_date DATE NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_end_date DATE NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_reason TEXT NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN leave_is_excused TINYINT DEFAULT 0");
    }
    
    // إنشاء الجداول إذا لم تكن موجودة
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
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_leaves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason TEXT,
            leave_category ENUM('medical', 'maternity', 'excused', 'unexcused') DEFAULT 'unexcused',
            is_excused TINYINT DEFAULT 0,
            deduction_amount DECIMAL(10,2) DEFAULT 0,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            approved_by INT,
            approved_at DATETIME,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_teacher (teacher_id),
            INDEX idx_status (status),
            INDEX idx_date (start_date, end_date)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_leaves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason TEXT,
            leave_category ENUM('medical', 'excused', 'unexcused') DEFAULT 'unexcused',
            is_excused TINYINT DEFAULT 0,
            deduction_amount DECIMAL(10,2) DEFAULT 0,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            approved_by INT,
            approved_at DATETIME,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_status (status),
            INDEX idx_date (start_date, end_date)
        )
    ");
    
} catch (PDOException $e) {
    // تجاهل الأخطاء
}

// ============================================
// إعدادات الخصم (للمسؤول فقط)
// ============================================
$settings_file = 'leave_settings.json';
$default_settings = [
    'teacher_daily_deduction' => 100,
    'student_daily_deduction' => 50,
    'max_teacher_excused_per_month' => 1,
    'max_student_excused_per_month' => 2,
    'auto_deduct' => true
];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
} else {
    $settings = $default_settings;
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
}

// ============================================
// معالجة الإجراءات
// ============================================

// حفظ الإعدادات (للمسؤول فقط)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settings = [
        'teacher_daily_deduction' => (float)$_POST['teacher_daily_deduction'],
        'student_daily_deduction' => (float)$_POST['student_daily_deduction'],
        'max_teacher_excused_per_month' => (int)$_POST['max_teacher_excused_per_month'],
        'max_student_excused_per_month' => (int)$_POST['max_student_excused_per_month'],
        'auto_deduct' => isset($_POST['auto_deduct']) ? true : false,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    $_SESSION['success_message'] = "✅ تم حفظ الإعدادات بنجاح";
    header("Location: manage_holidays.php?tab=settings");
    exit;
}

// ============================================
// للمعلم: إضافة إجازة لنفسه (مباشرة)
// ============================================
if (isTeacher() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_my_leave_direct'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $leave_category = $_POST['leave_category'];
    
    if ($start_date > $end_date) {
        $_SESSION['error_message'] = "❌ تاريخ البداية يجب أن يكون قبل تاريخ النهاية";
    } else {
        try {
            $pdo->beginTransaction();
            
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $days = $start->diff($end)->days + 1;
            
            $is_excused = true;
            $deduction_amount = 0;
            
            // إدراج سجل الإجازة
            $stmt = $pdo->prepare("
                INSERT INTO teacher_leaves 
                (teacher_id, start_date, end_date, reason, leave_category, is_excused, deduction_amount, status, approved_by, approved_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?)
            ");
            $stmt->execute([$teacher_id, $start_date, $end_date, $reason, $leave_category, $is_excused, $deduction_amount, $teacher_id, $teacher_id]);
            
            // تحديث حالة المعلم إلى على إجازة
            $pdo->prepare("
                UPDATE teachers 
                SET on_leave = 1, 
                    leave_start_date = ?, 
                    leave_end_date = ?,
                    leave_reason = ?,
                    leave_is_excused = ?
                WHERE id = ?
            ")->execute([$start_date, $end_date, $reason, 1, $teacher_id]);
            
            $pdo->commit();
            $_SESSION['success_message'] = "✅ تم تسجيل إجازتك بنجاح";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
        }
    }
    
    header("Location: manage_holidays.php?tab=my_requests");
    exit;
}

// ============================================
// للمعلم: إضافة إجازة لطالب (مباشرة - بدون انتظار)
// ============================================
if (isTeacher() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_leave_direct'])) {
    $student_id = (int)$_POST['student_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $leave_category = $_POST['leave_category'];
    
    // جلب اسم الطالب
    $student_name_stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $student_name_stmt->execute([$student_id]);
    $student_name = $student_name_stmt->fetchColumn();
    
    // التحقق من أن الطالب يتبع هذا المعلم
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
    $check->execute([$student_id, $teacher_id]);
    
    if (!$check->fetch()) {
        $_SESSION['error_message'] = "❌ هذا الطالب ليس من طلابك";
    } elseif ($start_date > $end_date) {
        $_SESSION['error_message'] = "❌ تاريخ البداية يجب أن يكون قبل تاريخ النهاية";
    } else {
        try {
            $pdo->beginTransaction();
            
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $days = $start->diff($end)->days + 1;
            
            $is_excused = true;
            $deduction_amount = 0;
            
            // إدراج سجل الإجازة
            $stmt = $pdo->prepare("
                INSERT INTO student_leaves 
                (student_id, start_date, end_date, reason, leave_category, is_excused, deduction_amount, status, approved_by, approved_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?)
            ");
            $stmt->execute([$student_id, $start_date, $end_date, $reason, $leave_category, $is_excused, $deduction_amount, $teacher_id, $teacher_id]);
            
            // تحديث حالة الطالب إلى على إجازة
            $pdo->prepare("
                UPDATE students 
                SET on_leave = 1, 
                    leave_start_date = ?, 
                    leave_end_date = ?,
                    leave_reason = ?,
                    leave_is_excused = ?
                WHERE id = ?
            ")->execute([$start_date, $end_date, $reason, 1, $student_id]);
            
            // حذف أي سجلات غياب تلقائي خلال فترة الإجازة
            $current = new DateTime($start_date);
            $end_date_obj = new DateTime($end_date);
            $end_date_obj->modify('+1 day');
            
            while ($current < $end_date_obj) {
                $date = $current->format('Y-m-d');
                $pdo->prepare("
                    DELETE FROM attendance 
                    WHERE person_type = 'student' AND person_id = ? AND date = ? AND auto_generated = 1
                ")->execute([$student_id, $date]);
                $current->modify('+1 day');
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "✅ تم تسجيل إجازة للطالب {$student_name} بنجاح";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
        }
    }
    
    header("Location: manage_holidays.php?tab=student_requests");
    exit;
}

// ============================================
// للمعلم: إنهاء إجازة لنفسه
// ============================================
if (isTeacher() && isset($_GET['end_my_leave'])) {
    try {
        $pdo->prepare("
            UPDATE teachers 
            SET on_leave = 0, 
                leave_start_date = NULL, 
                leave_end_date = NULL,
                leave_reason = NULL,
                leave_is_excused = NULL
            WHERE id = ?
        ")->execute([$teacher_id]);
        
        // تحديث حالة طلبات الإجازة
        $pdo->prepare("
            UPDATE teacher_leaves 
            SET status = 'completed' 
            WHERE teacher_id = ? AND status = 'approved'
        ")->execute([$teacher_id]);
        
        $_SESSION['success_message'] = "✅ تم إنهاء إجازتك بنجاح، يمكنك العودة للعمل";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: manage_holidays.php?tab=my_requests");
    exit;
}

// ============================================
// للمعلم: إنهاء إجازة طالب
// ============================================
if (isTeacher() && isset($_GET['end_student_leave']) && isset($_GET['id'])) {
    $student_id = (int)$_GET['id'];
    
    // التحقق من أن الطالب يتبع هذا المعلم
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
    $check->execute([$student_id, $teacher_id]);
    
    if ($check->fetch()) {
        try {
            $pdo->prepare("
                UPDATE students 
                SET on_leave = 0, 
                    leave_start_date = NULL, 
                    leave_end_date = NULL,
                    leave_reason = NULL,
                    leave_is_excused = NULL
                WHERE id = ?
            ")->execute([$student_id]);
            
            // تحديث حالة طلب الإجازة
            $pdo->prepare("
                UPDATE student_leaves 
                SET status = 'completed' 
                WHERE student_id = ? AND status = 'approved'
            ")->execute([$student_id]);
            
            $_SESSION['success_message'] = "✅ تم إنهاء إجازة الطالب بنجاح";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "❌ هذا الطالب ليس من طلابك";
    }
    
    header("Location: manage_holidays.php?tab=student_requests");
    exit;
}

// ============================================
// للمعلم: إلغاء طلب إجازة خاص بي
// ============================================
if (isTeacher() && isset($_GET['cancel_my_leave']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['cancel_my_leave'];
    
    $stmt = $pdo->prepare("DELETE FROM teacher_leaves WHERE id = ? AND teacher_id = ? AND status = 'pending'");
    $stmt->execute([$leave_id, $teacher_id]);
    
    $_SESSION['success_message'] = "✅ تم إلغاء طلب الإجازة";
    header("Location: manage_holidays.php?tab=my_requests");
    exit;
}

// ============================================
// للإدارة: الموافقة على إجازة معلم
// ============================================
if ($is_admin && isset($_GET['approve_teacher']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['approve_teacher'];
    
    try {
        $pdo->beginTransaction();
        
        $leave = $pdo->prepare("SELECT * FROM teacher_leaves WHERE id = ? AND status = 'pending'");
        $leave->execute([$leave_id]);
        $leave_data = $leave->fetch();
        
        if ($leave_data) {
            $stmt = $pdo->prepare("UPDATE teacher_leaves SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $leave_id]);
            
            $pdo->prepare("
                UPDATE teachers 
                SET on_leave = 1, 
                    leave_start_date = ?, 
                    leave_end_date = ?,
                    leave_reason = ?,
                    leave_is_excused = ?
                WHERE id = ?
            ")->execute([
                $leave_data['start_date'], 
                $leave_data['end_date'], 
                $leave_data['reason'],
                $leave_data['is_excused'],
                $leave_data['teacher_id']
            ]);
            
            $pdo->commit();
            $_SESSION['success_message'] = "✅ تم الموافقة على إجازة المعلم";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: manage_holidays.php?tab=pending_teachers");
    exit;
}

// ============================================
// للإدارة: رفض إجازة معلم
// ============================================
if ($is_admin && isset($_GET['reject_teacher']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['reject_teacher'];
    
    $stmt = $pdo->prepare("UPDATE teacher_leaves SET status = 'rejected' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$leave_id]);
    
    $_SESSION['success_message'] = "✅ تم رفض إجازة المعلم";
    header("Location: manage_holidays.php?tab=pending_teachers");
    exit;
}

// ============================================
// للإدارة: الموافقة على إجازة طالب
// ============================================
if ($is_admin && isset($_GET['approve_student_admin']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['approve_student_admin'];
    
    try {
        $pdo->beginTransaction();
        
        $leave = $pdo->prepare("SELECT * FROM student_leaves WHERE id = ? AND status = 'pending'");
        $leave->execute([$leave_id]);
        $leave_data = $leave->fetch();
        
        if ($leave_data) {
            $stmt = $pdo->prepare("UPDATE student_leaves SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $leave_id]);
            
            $pdo->prepare("
                UPDATE students 
                SET on_leave = 1, 
                    leave_start_date = ?, 
                    leave_end_date = ?,
                    leave_reason = ?,
                    leave_is_excused = ?
                WHERE id = ?
            ")->execute([
                $leave_data['start_date'], 
                $leave_data['end_date'], 
                $leave_data['reason'],
                $leave_data['is_excused'],
                $leave_data['student_id']
            ]);
            
            $pdo->commit();
            $_SESSION['success_message'] = "✅ تم الموافقة على إجازة الطالب";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: manage_holidays.php?tab=pending_students");
    exit;
}

// ============================================
// للإدارة: رفض إجازة طالب
// ============================================
if ($is_admin && isset($_GET['reject_student_admin']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['reject_student_admin'];
    
    $stmt = $pdo->prepare("UPDATE student_leaves SET status = 'rejected' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$leave_id]);
    
    $_SESSION['success_message'] = "✅ تم رفض إجازة الطالب";
    header("Location: manage_holidays.php?tab=pending_students");
    exit;
}

// ============================================
// إنهاء الإجازة (للمسؤول)
// ============================================
if ($is_admin && isset($_GET['end_leave']) && isset($_GET['type']) && isset($_GET['id'])) {
    $person_id = (int)$_GET['id'];
    $type = $_GET['type'];
    $table = ($type == 'teacher') ? 'teachers' : 'students';
    $leaves_table = ($type == 'teacher') ? 'teacher_leaves' : 'student_leaves';
    
    try {
        $pdo->prepare("
            UPDATE $table 
            SET on_leave = 0, 
                leave_start_date = NULL, 
                leave_end_date = NULL,
                leave_reason = NULL,
                leave_is_excused = NULL
            WHERE id = ?
        ")->execute([$person_id]);
        
        $pdo->prepare("
            UPDATE $leaves_table 
            SET status = 'completed' 
            WHERE ${type}_id = ? AND status = 'approved'
        ")->execute([$person_id]);
        
        $_SESSION['success_message'] = "✅ تم إنهاء الإجازة بنجاح";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: manage_holidays.php?tab=on_leave");
    exit;
}

// ============================================
// إضافة إجازة عامة (للمسؤول فقط)
// ============================================
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_general_holiday'])) {
    $name = trim($_POST['name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description = trim($_POST['description']);
    $apply_to = isset($_POST['apply_to']) ? $_POST['apply_to'] : 'both';
    
    if ($start_date > $end_date) {
        $_SESSION['error_message'] = "❌ تاريخ البداية يجب أن يكون قبل تاريخ النهاية";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO holidays (name, start_date, end_date, description, apply_to, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $start_date, $end_date, $description, $apply_to, $_SESSION['user_id']]);
            $_SESSION['success_message'] = "✅ تم إضافة الإجازة العامة بنجاح";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
        }
    }
    
    header("Location: manage_holidays.php?tab=general");
    exit;
}

// ============================================
// حذف إجازة عامة (للمسؤول فقط)
// ============================================
if ($is_admin && isset($_GET['delete_holiday'])) {
    $holiday_id = (int)$_GET['delete_holiday'];
    try {
        $pdo->prepare("DELETE FROM holidays WHERE id = ?")->execute([$holiday_id]);
        $_SESSION['success_message'] = "✅ تم حذف الإجازة العامة";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: manage_holidays.php?tab=general");
    exit;
}

// ============================================
// جلب البيانات
// ============================================

// الإجازات العامة (للمسؤول فقط)
$general_holidays = [];
if ($is_admin) {
    $general_holidays = $pdo->query("
        SELECT h.*, a.username as created_by_name,
               DATEDIFF(h.end_date, h.start_date) + 1 as days_count
        FROM holidays h
        LEFT JOIN admins a ON h.created_by = a.id
        ORDER BY h.start_date DESC
    ")->fetchAll();
}

// المعلمين (للمسؤول فقط)
$all_teachers = [];
if ($is_admin) {
    $all_teachers = $pdo->query("
        SELECT id, name, gender, phone, on_leave, leave_start_date, leave_end_date, leave_reason
        FROM teachers WHERE can_login = 1 ORDER BY name
    ")->fetchAll();
}

// طلاب المعلم (للمعلم)
$my_students = [];
if (isTeacher()) {
    $my_students = $pdo->prepare("
        SELECT id, name, category, parent_phone, on_leave, leave_start_date, leave_end_date, leave_reason
        FROM students WHERE teacher_id = ? ORDER BY name
    ");
    $my_students->execute([$teacher_id]);
    $my_students = $my_students->fetchAll();
}

// جميع الطلاب (للمسؤول)
$all_students = [];
if ($is_admin) {
    $all_students = $pdo->query("
        SELECT id, name, category, parent_phone, on_leave, leave_start_date, leave_end_date, leave_reason
        FROM students ORDER BY name
    ")->fetchAll();
}

// طلبات إجازتي (للمعلم)
$my_leave_requests = [];
if (isTeacher()) {
    $my_leave_requests = $pdo->prepare("
        SELECT l.*, DATEDIFF(l.end_date, l.start_date) + 1 as days_count
        FROM teacher_leaves l
        WHERE l.teacher_id = ?
        ORDER BY 
            CASE l.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            l.created_at DESC
    ");
    $my_leave_requests->execute([$teacher_id]);
    $my_leave_requests = $my_leave_requests->fetchAll();
}

// إجازات طلابي (للمعلم - المعتمدة فقط)
$my_student_leaves = [];
if (isTeacher()) {
    $my_student_leaves = $pdo->prepare("
        SELECT l.*, s.name as student_name, s.parent_phone,
               DATEDIFF(l.end_date, l.start_date) + 1 as days_count
        FROM student_leaves l
        JOIN students s ON l.student_id = s.id
        WHERE s.teacher_id = ? AND l.status = 'approved'
        ORDER BY l.start_date DESC
    ");
    $my_student_leaves->execute([$teacher_id]);
    $my_student_leaves = $my_student_leaves->fetchAll();
}

// طلبات إجازات المعلمين (للمسؤول)
$teacher_leave_requests = [];
if ($is_admin) {
    $teacher_leave_requests = $pdo->query("
        SELECT l.*, t.name as teacher_name, t.phone as teacher_phone,
               DATEDIFF(l.end_date, l.start_date) + 1 as days_count
        FROM teacher_leaves l
        JOIN teachers t ON l.teacher_id = t.id
        WHERE l.status = 'pending'
        ORDER BY l.created_at DESC
    ")->fetchAll();
}

// طلبات إجازات الطلاب (للمسؤول)
$student_leave_requests = [];
if ($is_admin) {
    $student_leave_requests = $pdo->query("
        SELECT l.*, s.name as student_name, s.parent_phone,
               DATEDIFF(l.end_date, l.start_date) + 1 as days_count
        FROM student_leaves l
        JOIN students s ON l.student_id = s.id
        WHERE l.status = 'pending'
        ORDER BY l.created_at DESC
    ")->fetchAll();
}

// إحصائيات (للمعلم)
$my_stats = [];
if (isTeacher()) {
    $my_stats = [
        'my_pending' => count(array_filter($my_leave_requests, fn($l) => $l['status'] == 'pending')),
        'my_approved' => count(array_filter($my_leave_requests, fn($l) => $l['status'] == 'approved')),
        'my_on_leave' => $teacher_info['on_leave'] ?? 0,
        'students_on_leave' => count(array_filter($my_students, fn($s) => $s['on_leave'] == 1)),
        'students_leave_count' => count($my_student_leaves)
    ];
}

// إحصائيات (للمسؤول)
$admin_stats = [];
if ($is_admin) {
    $admin_stats = [
        'teacher_pending' => count($teacher_leave_requests),
        'student_pending' => count($student_leave_requests),
        'teachers_on_leave' => count(array_filter($all_teachers, fn($t) => $t['on_leave'] == 1)),
        'students_on_leave' => count(array_filter($all_students, fn($s) => $s['on_leave'] == 1))
    ];
}

// جلب معلومات المعلم (للمعلم)
$teacher_info = [];
if (isTeacher()) {
    $stmt = $pdo->prepare("SELECT name, on_leave, leave_start_date, leave_end_date FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher_info = $stmt->fetch();
}

// عرض رسائل الجلسة
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
$warning_message = $_SESSION['warning_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['warning_message']);

ob_end_clean();
require_once 'includes/header.php';
?>

<style>
.leaves-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px 30px;
    border-radius: 25px;
    margin-bottom: 25px;
    text-align: center;
}

.page-header h1 {
    margin: 0;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.page-header h1 i {
    color: #c9a96b;
}

/* ===== التبويبات ===== */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
    justify-content: center;
}

.tab-btn {
    padding: 12px 30px;
    border-radius: 50px;
    background: white;
    color: #1e3c3f;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.tab-btn.active {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    border-color: #c9a96b;
}

.tab-btn:hover:not(.active) {
    background: #e9ecef;
    transform: translateY(-2px);
}

/* ===== إحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e3c3f;
}

.stat-number.pending { color: #ffc107; }
.stat-number.approved { color: #28a745; }
.stat-number.on-leave { color: #17a2b8; }

.stat-label {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
}

/* ===== البطاقات ===== */
.form-card, .leaves-card, .settings-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #c9a96b;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
}

.radio-group {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #f8f9fa;
    border-radius: 30px;
    cursor: pointer;
    transition: 0.3s;
}

.radio-option.selected {
    background: #1e3c3f;
    color: white;
}

.radio-option.selected i {
    color: #c9a96b;
}

.radio-option input {
    display: none;
}

/* ===== الجداول ===== */
.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #1e3c3f;
    color: white;
    padding: 12px;
    text-align: center;
}

td {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
}

tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-completed { background: #cfe2ff; color: #084298; }

/* ===== أزرار ===== */
.btn {
    padding: 10px 20px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    width: 100%;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 0.8rem;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== بطاقات الأشخاص ===== */
.person-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-right: 4px solid #17a2b8;
}

.person-name {
    font-weight: 700;
    color: #1e3c3f;
}

.person-date {
    font-size: 0.8rem;
    color: #666;
}

/* ===== حالة فارغة ===== */
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

/* ===== رسائل ===== */
.alert {
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-right: 5px solid #ffc107;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .leaves-page { padding: 15px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .form-row { grid-template-columns: 1fr; }
    .tabs { flex-direction: column; }
    .tab-btn { text-align: center; }
    .radio-group { flex-direction: column; }
    .person-card { flex-direction: column; text-align: center; }
}
</style>

<section class="leaves-page">
    <div class="page-header">
        <h1><i class="fas fa-calendar-alt"></i> إدارة الإجازات</h1>
        <p><?php echo $is_admin ? 'إدارة إجازات المعلمين والطلاب' : 'تقديم طلبات إجازة لنفسك ولطلابك'; ?></p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if ($warning_message): ?>
        <div class="alert alert-warning"><?php echo $warning_message; ?></div>
    <?php endif; ?>
    
    <!-- ============================================ -->
    <!-- تبويبات المعلم -->
    <!-- ============================================ -->
    <?php if (isTeacher()): ?>
        <div class="tabs">
            <a href="?tab=my_requests" class="tab-btn <?php echo $active_tab == 'my_requests' ? 'active' : ''; ?>">
                <i class="fas fa-user-clock"></i> إجازتي
            </a>
            <a href="?tab=add_my_leave" class="tab-btn <?php echo $active_tab == 'add_my_leave' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> إضافة إجازة لي
            </a>
            <a href="?tab=student_requests" class="tab-btn <?php echo $active_tab == 'student_requests' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> إجازات طلابي (<?php echo $my_stats['students_leave_count']; ?>)
            </a>
            <a href="?tab=add_student_leave" class="tab-btn <?php echo $active_tab == 'add_student_leave' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> إضافة إجازة لطالب
            </a>
            <a href="?tab=on_leave" class="tab-btn <?php echo $active_tab == 'on_leave' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> على إجازة (<?php echo $my_stats['students_on_leave']; ?>)
            </a>
        </div>

        <!-- تبويب: إجازتي -->
        <div id="tab-my_requests" class="tab-content" style="display: <?php echo $active_tab == 'my_requests' ? 'block' : 'none'; ?>">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number <?php echo $teacher_info['on_leave'] ? 'on-leave' : ''; ?>"><?php echo $teacher_info['on_leave'] ? 'على إجازة' : 'نشط'; ?></div><div class="stat-label">حالتي</div></div>
                <?php if ($teacher_info['on_leave'] && $teacher_info['leave_end_date']): ?>
                    <div class="stat-card"><div class="stat-number"><?php echo $teacher_info['leave_end_date']; ?></div><div class="stat-label">نهاية الإجازة</div></div>
                <?php endif; ?>
            </div>
            
            <?php if ($teacher_info['on_leave']): ?>
                <div class="leaves-card">
                    <div class="section-title"><i class="fas fa-info-circle"></i><h3>أنت على إجازة حالياً</h3></div>
                    <div class="person-card">
                        <div><div class="person-name">إجازة من <?php echo $teacher_info['leave_start_date']; ?> إلى <?php echo $teacher_info['leave_end_date']; ?></div></div>
                        <a href="?end_my_leave=1&tab=my_requests" class="btn btn-warning btn-sm" onclick="return confirm('إنهاء إجازتك والعودة للعمل؟')"><i class="fas fa-undo"></i> إنهاء الإجازة</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- تبويب: إضافة إجازة لي -->
        <div id="tab-add_my_leave" class="tab-content" style="display: <?php echo $active_tab == 'add_my_leave' ? 'block' : 'none'; ?>">
            <div class="form-card">
                <div class="section-title"><i class="fas fa-plus-circle"></i><h3>تقديم طلب إجازة</h3></div>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group"><label><i class="fas fa-calendar-start"></i> تاريخ البداية</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="form-group"><label><i class="fas fa-calendar-end"></i> تاريخ النهاية</label><input type="date" name="end_date" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> نوع الإجازة</label>
                        <div class="radio-group">
                            <label class="radio-option"><i class="fas fa-ambulance"></i> إجازة مرضية<input type="radio" name="leave_category" value="medical" required></label>
                            <label class="radio-option"><i class="fas fa-baby"></i> إجازة ولادة<input type="radio" name="leave_category" value="maternity"></label>
                            <label class="radio-option"><i class="fas fa-check-circle"></i> إجازة معذورة<input type="radio" name="leave_category" value="excused"></label>
                        </div>
                    </div>
                    <div class="form-group"><label><i class="fas fa-sticky-note"></i> سبب الإجازة</label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
                    <button type="submit" name="add_my_leave_direct" class="btn btn-primary" onclick="return confirm('هل أنت متأكد من تسجيل إجازتك؟')">تسجيل الإجازة</button>
                </form>
            </div>
        </div>

        <!-- تبويب: إجازات طلابي -->
        <div id="tab-student_requests" class="tab-content" style="display: <?php echo $active_tab == 'student_requests' ? 'block' : 'none'; ?>">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number approved"><?php echo $my_stats['students_leave_count']; ?></div><div class="stat-label">إجازات مسجلة</div></div>
                <div class="stat-card"><div class="stat-number on-leave"><?php echo $my_stats['students_on_leave']; ?></div><div class="stat-label">طلاب على إجازة حالياً</div></div>
            </div>
            
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-list"></i><h3>إجازات طلابي المسجلة</h3></div>
                <?php if (empty($my_student_leaves)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>لا توجد إجازات مسجلة لطلابك</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>الطالب</th><th>الفترة</th><th>الأيام</th><th>النوع</th><th>السبب</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($my_student_leaves as $leave): 
                                    $type_text = match($leave['leave_category']) {
                                        'medical' => '🩺 مرضية', 'excused' => '✅ معذورة', default => '❌ غير معذورة'
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($leave['student_name']); ?></strong></td>
                                        <td><?php echo $leave['start_date']; ?> - <?php echo $leave['end_date']; ?></td>
                                        <td><?php echo $leave['days_count']; ?> يوم</span></td>
                                        <td><?php echo $type_text; ?></td>
                                        <td><?php echo htmlspecialchars($leave['reason'] ?: '-'); ?></td>
                                        <td><span class="status-badge status-<?php echo $leave['status']; ?>"><?php echo $leave['status'] == 'approved' ? '✅ معتمدة' : ($leave['status'] == 'completed' ? '📋 منتهية' : '⏳ قيد المراجعة'); ?></span></td>
                                        <td>
                                            <?php if ($leave['status'] == 'approved'): ?>
                                                <a href="?end_student_leave=1&id=<?php echo $leave['student_id']; ?>&tab=student_requests" class="btn btn-warning btn-sm" onclick="return confirm('إنهاء إجازة هذا الطالب مبكراً؟')"><i class="fas fa-undo"></i> إنهاء</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- تبويب: إضافة إجازة لطالب -->
        <div id="tab-add_student_leave" class="tab-content" style="display: <?php echo $active_tab == 'add_student_leave' ? 'block' : 'none'; ?>">
            <div class="form-card">
                <div class="section-title"><i class="fas fa-plus-circle"></i><h3>إضافة إجازة لطالب (مباشرة)</h3></div>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user-graduate"></i> الطالب</label>
                            <select name="student_id" class="form-control" required>
                                <option value="">-- اختر الطالب --</option>
                                <?php foreach ($my_students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['name']); ?>
                                        <?php if ($student['on_leave'] == 1): ?> [على إجازة حالياً] <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label><i class="fas fa-calendar-start"></i> تاريخ البداية</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="form-group"><label><i class="fas fa-calendar-end"></i> تاريخ النهاية</label><input type="date" name="end_date" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> نوع الإجازة</label>
                        <div class="radio-group">
                            <label class="radio-option"><i class="fas fa-ambulance"></i> إجازة مرضية<input type="radio" name="leave_category" value="medical" required></label>
                            <label class="radio-option"><i class="fas fa-check-circle"></i> إجازة معذورة<input type="radio" name="leave_category" value="excused"></label>
                        </div>
                    </div>
                    <div class="form-group"><label><i class="fas fa-sticky-note"></i> سبب الإجازة</label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
                    <button type="submit" name="add_student_leave_direct" class="btn btn-primary" onclick="return confirm('هل أنت متأكد من تسجيل إجازة لهذا الطالب؟')">تسجيل الإجازة</button>
                </form>
            </div>
        </div>

        <!-- تبويب: على إجازة حالياً -->
        <div id="tab-on_leave" class="tab-content" style="display: <?php echo $active_tab == 'on_leave' ? 'block' : 'none'; ?>">
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-clock"></i><h3>طلابي على إجازة حالياً</h3></div>
                <?php $my_students_on_leave = array_filter($my_students, fn($s) => $s['on_leave'] == 1); ?>
                <?php if (empty($my_students_on_leave)): ?>
                    <div class="empty-state"><i class="fas fa-check-circle" style="color: var(--success);"></i><p>لا يوجد طلاب على إجازة حالياً</p></div>
                <?php else: ?>
                    <?php foreach ($my_students_on_leave as $student): ?>
                        <div class="person-card">
                            <div><div class="person-name"><?php echo htmlspecialchars($student['name']); ?></div><div class="person-date">من <?php echo $student['leave_start_date']; ?> إلى <?php echo $student['leave_end_date']; ?></div></div>
                            <a href="?end_student_leave=1&id=<?php echo $student['id']; ?>&tab=on_leave" class="btn btn-warning btn-sm" onclick="return confirm('إنهاء إجازة هذا الطالب مبكراً؟')"><i class="fas fa-undo"></i> إنهاء الإجازة</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <!-- ============================================ -->
    <!-- تبويبات المسؤول -->
    <!-- ============================================ -->
    <?php elseif ($is_admin): ?>
        <div class="tabs">
            <a href="?tab=general" class="tab-btn <?php echo $active_tab == 'general' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> إجازات عامة</a>
            <a href="?tab=pending_teachers" class="tab-btn <?php echo $active_tab == 'pending_teachers' ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> طلبات معلمين (<?php echo $admin_stats['teacher_pending']; ?>)</a>
            <a href="?tab=pending_students" class="tab-btn <?php echo $active_tab == 'pending_students' ? 'active' : ''; ?>"><i class="fas fa-user-graduate"></i> طلبات طلاب (<?php echo $admin_stats['student_pending']; ?>)</a>
            <a href="?tab=on_leave" class="tab-btn <?php echo $active_tab == 'on_leave' ? 'active' : ''; ?>"><i class="fas fa-clock"></i> على إجازة (<?php echo $admin_stats['teachers_on_leave'] + $admin_stats['students_on_leave']; ?>)</a>
            <a href="?tab=settings" class="tab-btn <?php echo $active_tab == 'settings' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> الإعدادات</a>
        </div>
        <!-- تبويب الإجازات العامة -->
        <div id="tab-general" class="tab-content" style="display: <?php echo $active_tab == 'general' ? 'block' : 'none'; ?>">
            <div class="form-card">
                <div class="section-title"><i class="fas fa-plus-circle"></i><h3>إضافة إجازة عامة</h3></div>
                <form method="post">
                    <div class="form-row"><div class="form-group"><label>اسم الإجازة</label><input type="text" name="name" class="form-control" required></div></div>
                    <div class="form-row">
                        <div class="form-group"><label>تاريخ البداية</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="form-group"><label>تاريخ النهاية</label><input type="date" name="end_date" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label>تشمل</label>
                        <div class="radio-group">
                            <label class="radio-option"><i class="fas fa-users"></i> الجميع<input type="radio" name="apply_to" value="both" checked></label>
                            <label class="radio-option"><i class="fas fa-chalkboard-teacher"></i> المعلمين فقط<input type="radio" name="apply_to" value="teachers"></label>
                            <label class="radio-option"><i class="fas fa-user-graduate"></i> الطلاب فقط<input type="radio" name="apply_to" value="students"></label>
                        </div>
                    </div>
                    <div class="form-group"><label>وصف الإجازة</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <button type="submit" name="add_general_holiday" class="btn btn-primary">إضافة الإجازة العامة</button>
                </form>
            </div>
            
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-list"></i><h3>الإجازات العامة</h3></div>
                <?php if (empty($general_holidays)): ?>
                    <div class="empty-state"><i class="fas fa-calendar-alt"></i><p>لا توجد إجازات عامة مسجلة</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>الاسم</th><th>الفترة</th><th>الأيام</th><th>تشمل</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($general_holidays as $holiday): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($holiday['name']); ?></strong><br><small><?php echo htmlspecialchars($holiday['description']); ?></small></td>
                                        <td><?php echo $holiday['start_date']; ?> - <?php echo $holiday['end_date']; ?></td>
                                        <td><?php echo $holiday['days_count']; ?> يوم</span></td>
                                        <td><?php echo $holiday['apply_to'] == 'both' ? 'الجميع' : ($holiday['apply_to'] == 'teachers' ? 'المعلمين فقط' : 'الطلاب فقط'); ?></td>
                                        <td><a href="?delete_holiday=<?php echo $holiday['id']; ?>&tab=general" class="btn btn-danger btn-sm" onclick="return confirm('حذف هذه الإجازة؟')"><i class="fas fa-trash"></i> حذف</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- تبويب طلبات المعلمين -->
        <div id="tab-pending_teachers" class="tab-content" style="display: <?php echo $active_tab == 'pending_teachers' ? 'block' : 'none'; ?>">
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-list"></i><h3>طلبات إجازات المعلمين</h3></div>
                <?php if (empty($teacher_leave_requests)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>لا توجد طلبات إجازات</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>المعلم</th><th>الفترة</th><th>الأيام</th><th>النوع</th><th>السبب</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($teacher_leave_requests as $leave): 
                                    $type_text = match($leave['leave_category']) {
                                        'medical' => '🩺 مرضية', 'maternity' => '👶 ولادة', 'excused' => '✅ معذورة', default => '❌ غير معذورة'
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($leave['teacher_name']); ?></strong><br><small><?php echo $leave['teacher_phone']; ?></small></td>
                                        <td><?php echo $leave['start_date']; ?> - <?php echo $leave['end_date']; ?></td>
                                        <td><?php echo $leave['days_count']; ?> يوم</span></td>
                                        <td><?php echo $type_text; ?></td>
                                        <td><?php echo htmlspecialchars($leave['reason'] ?: '-'); ?></td>
                                        <td><span class="status-badge status-pending">⏳ قيد المراجعة</span></td>
                                        <td>
                                            <a href="?approve_teacher=1&id=<?php echo $leave['id']; ?>&tab=pending_teachers" class="btn btn-success btn-sm" onclick="return confirm('الموافقة على هذه الإجازة؟')"><i class="fas fa-check"></i> موافقة</a>
                                            <a href="?reject_teacher=1&id=<?php echo $leave['id']; ?>&tab=pending_teachers" class="btn btn-danger btn-sm" onclick="return confirm('رفض هذه الإجازة؟')"><i class="fas fa-times"></i> رفض</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- تبويب طلبات الطلاب -->
        <div id="tab-pending_students" class="tab-content" style="display: <?php echo $active_tab == 'pending_students' ? 'block' : 'none'; ?>">
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-list"></i><h3>طلبات إجازات الطلاب</h3></div>
                <?php if (empty($student_leave_requests)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>لا توجد طلبات إجازات</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>الطالب</th><th>الفترة</th><th>الأيام</th><th>النوع</th><th>السبب</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($student_leave_requests as $leave): 
                                    $type_text = match($leave['leave_category']) {
                                        'medical' => '🩺 مرضية', 'excused' => '✅ معذورة', default => '❌ غير معذورة'
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($leave['student_name']); ?></strong><br><small><?php echo $leave['parent_phone']; ?></small></td>
                                        <td><?php echo $leave['start_date']; ?> - <?php echo $leave['end_date']; ?></td>
                                        <td><?php echo $leave['days_count']; ?> يوم</span></td>
                                        <td><?php echo $type_text; ?></td>
                                        <td><?php echo htmlspecialchars($leave['reason'] ?: '-'); ?></td>
                                        <td><span class="status-badge status-pending">⏳ قيد المراجعة</span></td>
                                        <td>
                                            <a href="?approve_student_admin=1&id=<?php echo $leave['id']; ?>&tab=pending_students" class="btn btn-success btn-sm" onclick="return confirm('الموافقة على إجازة هذا الطالب؟')"><i class="fas fa-check"></i> موافقة</a>
                                            <a href="?reject_student_admin=1&id=<?php echo $leave['id']; ?>&tab=pending_students" class="btn btn-danger btn-sm" onclick="return confirm('رفض إجازة هذا الطالب؟')"><i class="fas fa-times"></i> رفض</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- تبويب الأشخاص على إجازة -->
        <div id="tab-on_leave" class="tab-content" style="display: <?php echo $active_tab == 'on_leave' ? 'block' : 'none'; ?>">
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-clock"></i><h3>المعلمون على إجازة</h3></div>
                <?php $teachers_on_leave = array_filter($all_teachers, fn($t) => $t['on_leave'] == 1); ?>
                <?php if (empty($teachers_on_leave)): ?>
                    <div class="empty-state"><i class="fas fa-check-circle" style="color: var(--success);"></i><p>لا يوجد معلمون على إجازة حالياً</p></div>
                <?php else: ?>
                    <?php foreach ($teachers_on_leave as $teacher): ?>
                        <div class="person-card">
                            <div><div class="person-name"><?php echo htmlspecialchars($teacher['name']); ?></div><div class="person-date">من <?php echo $teacher['leave_start_date']; ?> إلى <?php echo $teacher['leave_end_date']; ?></div></div>
                            <a href="?end_leave=1&type=teacher&id=<?php echo $teacher['id']; ?>&tab=on_leave" class="btn btn-warning btn-sm" onclick="return confirm('إنهاء إجازة هذا المعلم مبكراً؟')"><i class="fas fa-undo"></i> إنهاء الإجازة</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="leaves-card">
                <div class="section-title"><i class="fas fa-clock"></i><h3>الطلاب على إجازة</h3></div>
                <?php $students_on_leave = array_filter($all_students, fn($s) => $s['on_leave'] == 1); ?>
                <?php if (empty($students_on_leave)): ?>
                    <div class="empty-state"><i class="fas fa-check-circle" style="color: var(--success);"></i><p>لا يوجد طلاب على إجازة حالياً</p></div>
                <?php else: ?>
                    <?php foreach ($students_on_leave as $student): ?>
                        <div class="person-card">
                            <div><div class="person-name"><?php echo htmlspecialchars($student['name']); ?></div><div class="person-date">من <?php echo $student['leave_start_date']; ?> إلى <?php echo $student['leave_end_date']; ?></div></div>
                            <a href="?end_leave=1&type=student&id=<?php echo $student['id']; ?>&tab=on_leave" class="btn btn-warning btn-sm" onclick="return confirm('إنهاء إجازة هذا الطالب مبكراً؟')"><i class="fas fa-undo"></i> إنهاء الإجازة</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- تبويب الإعدادات -->
        <div id="tab-settings" class="tab-content" style="display: <?php echo $active_tab == 'settings' ? 'block' : 'none'; ?>">
            <div class="settings-card">
                <div class="section-title"><i class="fas fa-cog"></i><h3>إعدادات الخصم والإجازات</h3></div>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group"><label><i class="fas fa-chalkboard-teacher"></i> خصم اليوم للمعلم (ج.م)</label><input type="number" name="teacher_daily_deduction" class="form-control" value="<?php echo $settings['teacher_daily_deduction']; ?>"></div>
                        <div class="form-group"><label><i class="fas fa-user-graduate"></i> خصم اليوم للطالب (ج.م)</label><input type="number" name="student_daily_deduction" class="form-control" value="<?php echo $settings['student_daily_deduction']; ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label><i class="fas fa-calendar-alt"></i> الحد الأقصى للإجازات المعذورة للمعلم</label><input type="number" name="max_teacher_excused_per_month" class="form-control" value="<?php echo $settings['max_teacher_excused_per_month']; ?>"></div>
                        <div class="form-group"><label><i class="fas fa-calendar-alt"></i> الحد الأقصى للإجازات المعذورة للطالب</label><input type="number" name="max_student_excused_per_month" class="form-control" value="<?php echo $settings['max_student_excused_per_month']; ?>"></div>
                    </div>
                    <div class="form-group"><label class="checkbox-label"><input type="checkbox" name="auto_deduct" value="1" <?php echo $settings['auto_deduct'] ? 'checked' : ''; ?>> <i class="fas fa-check-circle"></i> خصم تلقائي للإجازات غير المعذورة</label></div>
                    <button type="submit" name="save_settings" class="btn btn-primary">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>