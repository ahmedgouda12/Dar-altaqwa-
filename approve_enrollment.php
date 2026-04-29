<?php
// ============================================
// ملف: approve_enrollment.php - موافقة المعلم على الطالب
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'check_duplicate.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $request_id = (int)$_POST['request_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        // جلب معلومات الطلب مع قفل
        $stmt = $pdo->prepare("SELECT * FROM enrollment_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            throw new Exception("الطلب غير موجود");
        }
        
        // التحقق من عدم توزيع الطالب مسبقاً
        if ($request['status'] == 'assigned') {
            throw new Exception("❌ هذا الطالب تم توزيعه مسبقاً!");
        }
        
        // التحقق من عدم وجود الطالب في النظام
        if (isStudentAlreadyAssigned($pdo, $request['student_name'], $request['parent_phone'])) {
            throw new Exception("❌ الطالب {$request['student_name']} مسجل بالفعل في النظام!");
        }
        
        // تحديث حالة الطلب
        $update = $pdo->prepare("
            UPDATE enrollment_requests 
            SET status = 'approved_by_teacher', 
                approved_by_teacher_id = ?,
                approved_at = NOW()
            WHERE id = ?
        ");
        $update->execute([$_SESSION['user_id'], $request_id]);
        
        // تسجيل في السجل
        $log = $pdo->prepare("
            INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, ?, 'approved_by_teacher', ?, ?, ?)
        ");
        $log->execute([
            $request_id,
            $request['status'],
            $_SESSION['user_id'],
            isTeacher() ? 'teacher' : 'admin',
            $notes
        ]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "✅ تمت الموافقة على الطلب بنجاح";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

header("Location: " . (isTeacher() ? "teacher_enrollment.php" : "enrollment_requests.php"));
exit;
?>