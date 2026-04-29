<?php
ob_start();
require_once 'hijri_date.php'; // إضافة هذا السطر
require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    header('Location: login.php');
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$teacher_id = $_SESSION['user_id'];

$current_hijri = getHijriDate();
$hijri_year = $current_hijri['year'];
$hijri_month = $current_hijri['month'];

// جلب الهدف
$goal = $pdo->prepare("
    SELECT g.*, s.name as student_name, s.teacher_id
    FROM student_monthly_goals g
    JOIN students s ON g.student_id = s.id
    WHERE g.student_id = ? AND g.hijri_year = ? AND g.hijri_month_id = ? AND g.completed = 1
");
$goal->execute([$student_id, $hijri_year, $hijri_month]);
$goal_data = $goal->fetch();

if (!$goal_data) {
    $_SESSION['error'] = "❌ الهدف غير مكتمل أو غير موجود";
    header("Location: teacher_monthly_goals.php");
    exit;
}

if (isTeacher() && $goal_data['teacher_id'] != $teacher_id) {
    $_SESSION['error'] = "❌ هذا الطالب ليس من طلابك";
    header("Location: teacher_monthly_goals.php");
    exit;
}

$check = $pdo->prepare("SELECT id FROM student_achievements WHERE student_id = ? AND goal_id = ?");
$check->execute([$student_id, $goal_data['id']]);

if ($check->fetch()) {
    $_SESSION['error'] = "❌ هذا الطالب حصل على الشهادة مسبقاً";
    header("Location: teacher_monthly_goals.php");
    exit;
}

// إنشاء رقم شهادة فريد - هذه الدالة ستكون متاحة الآن
$certificate_number = generateCertificateNumber($student_id, $hijri_year, $hijri_month);

$stmt = $pdo->prepare("
    INSERT INTO student_achievements 
    (student_id, goal_id, teacher_id, achieved_at, certificate_number, approved_by, notes)
    VALUES (?, ?, ?, CURDATE(), ?, ?, ?)
");
$stmt->execute([
    $student_id, $goal_data['id'], $goal_data['teacher_id'],
    $certificate_number, $teacher_id, 'إكمال الهدف الشهري'
]);

$achievement_id = $pdo->lastInsertId();

$_SESSION['success'] = "✅ تم منح الشهادة للطالب";
header("Location: certificate_view.php?id=$achievement_id");
exit;
?>