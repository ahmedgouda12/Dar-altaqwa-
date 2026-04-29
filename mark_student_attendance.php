<?php
// ============================================
// ملف: mark_student_attendance.php
// معالجة تسجيل حضور الطالب وإعادة تعيين الغياب المتتالي
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'check_student_absences.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $student_id = (int)$_POST['student_id'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    // تسجيل الحضور
    $result = markStudentAttendanceAndResetStreak($pdo, $student_id, $status, $notes);
    
    $_SESSION['success'] = "✅ تم تسجيل حضور الطالب بنجاح وإعادة تعيين الغياب المتتالي";
    
    // العودة إلى الصفحة السابقة
    $referer = $_SERVER['HTTP_REFERER'] ?? 'repeated_absences.php';
    header("Location: $referer");
    exit;
}

header("Location: repeated_absences.php");
exit;
?>