<?php
// ============================================
// ملف: delete_ring.php - حذف حلقة (نسخة محسنة)
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$ring_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ring_id <= 0) {
    header('Location: rings.php');
    exit;
}

// جلب معلومات الحلقة
$stmt = $pdo->prepare("SELECT * FROM rings WHERE id = ?");
$stmt->execute([$ring_id]);
$ring = $stmt->fetch();

if (!$ring) {
    header('Location: rings.php');
    exit;
}

// التحقق من الصلاحية
$canDelete = isAdmin() || (isTeacher() && $ring['teacher_id'] == $_SESSION['user_id']);

if (!$canDelete) {
    header('Location: rings.php');
    exit;
}

// حفظ معرف المعلم قبل الحذف
$teacher_id = $ring['teacher_id'];

// حذف جميع طلاب الحلقة أولاً
$pdo->prepare("DELETE FROM ring_students WHERE ring_id = ?")->execute([$ring_id]);

// حذف مواعيد الحلقة
$pdo->prepare("DELETE FROM ring_schedules WHERE ring_id = ?")->execute([$ring_id]);

// حذف الحلقة نفسها
$stmt = $pdo->prepare("DELETE FROM rings WHERE id = ?");
$stmt->execute([$ring_id]);

// تحديث عدد الطلاب في جميع حلقات المعلم
if (function_exists('updateAllRingsCountForTeacher')) {
    updateAllRingsCountForTeacher($pdo, $teacher_id);
}

// تحديث عدد طلاب المعلم
if (function_exists('updateTeacherStudentsCount')) {
    updateTeacherStudentsCount($pdo, $teacher_id);
}

// توجيه مع رسالة نجاح
header('Location: rings.php?msg=deleted');
exit;
?>