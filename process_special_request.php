<?php
// ============================================
// ملف: process_special_request.php
// معالجة طلبات تحويل الطلاب إلى خاص
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $notes = trim($_POST['notes'] ?? '');
    $action = isset($_POST['make_special']) ? 'make' : (isset($_POST['remove_special']) ? 'remove' : '');
    
    // التحقق من وجود الطالب
    $student = $pdo->prepare("SELECT s.*, t.name as teacher_name FROM students s LEFT JOIN teachers t ON s.teacher_id = t.id WHERE s.id = ?");
    $student->execute([$student_id]);
    $student_data = $student->fetch();
    
    if (!$student_data) {
        $_SESSION['error'] = "❌ الطالب غير موجود";
        header("Location: " . (isAdmin() ? "students.php" : "teacher_dashboard.php"));
        exit;
    }
    
    // التحقق من الصلاحية
    if (isTeacher() && $student_data['teacher_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "❌ لا يمكنك تعديل طالب ليس من طلابك";
        header("Location: teacher_dashboard.php");
        exit;
    }
    
    if ($action == 'make') {
        // تحويل لطالب خاص
        $update = $pdo->prepare("
            UPDATE students SET 
                is_special = 1, 
                special_approved_by = ?, 
                special_approved_at = NOW(),
                special_notes = ?
            WHERE id = ?
        ");
        $update->execute([$_SESSION['user_id'], $notes, $student_id]);
        
        // إنشاء جدول السجل إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS special_conversion_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                teacher_id INT NOT NULL,
                action ENUM('make', 'remove') DEFAULT 'make',
                notes TEXT,
                performed_by INT NOT NULL,
                performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student (student_id),
                INDEX idx_teacher (teacher_id)
            )
        ");
        
        // تسجيل في السجل
        $log = $pdo->prepare("
            INSERT INTO special_conversion_logs (student_id, teacher_id, action, notes, performed_by)
            VALUES (?, ?, 'make', ?, ?)
        ");
        $log->execute([$student_id, $student_data['teacher_id'], $notes, $_SESSION['user_id']]);
        
        // إضافة إشعار للطالب
        if (file_exists('notifications_functions.php')) {
            require_once 'notifications_functions.php';
            addNotification($pdo, $student_id, 'student', 
                '👑 ترقية إلى طالب خاص', 
                "تم ترقيتك إلى طالب خاص في دار التقوى. استمتع بالمميزات الحصرية!",
                'success',
                'student_dashboard.php'
            );
        }
        
        $_SESSION['success'] = "✅ تم تحويل الطالب {$student_data['name']} إلى طالب خاص";
        
    } elseif ($action == 'remove') {
        // إلغاء الخاصية
        $update = $pdo->prepare("
            UPDATE students SET 
                is_special = 0, 
                special_approved_by = NULL, 
                special_approved_at = NULL,
                special_notes = NULL
            WHERE id = ?
        ");
        $update->execute([$student_id]);
        
        $_SESSION['success'] = "✅ تم إلغاء خاصية الطالب الخاص عن {$student_data['name']}";
    }
    
    header("Location: " . (isAdmin() ? "students.php" : "teacher_dashboard.php"));
    exit;
}

header("Location: " . (isAdmin() ? "students.php" : "teacher_dashboard.php"));
exit;
?>