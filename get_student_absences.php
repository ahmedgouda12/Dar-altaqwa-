<?php
// ============================================
// ملف: get_student_absences.php
// جلب تفاصيل الغياب لطالب معين (AJAX)
// ============================================

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف الطالب مطلوب']);
    exit;
}

// التحقق من الصلاحية
$can_view = false;
if (isAdmin()) {
    $can_view = true;
} elseif (isTeacher()) {
    $stmt = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $teacher_id = $stmt->fetchColumn();
    $can_view = ($teacher_id == $_SESSION['user_id']);
} elseif (isStudent()) {
    $can_view = ($_SESSION['user_id'] == $student_id);
} elseif (isGuardian()) {
    $stmt = $pdo->prepare("SELECT guardian_id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $guardian_id = $stmt->fetchColumn();
    $can_view = ($guardian_id == $_SESSION['user_id']);
}

if (!$can_view) {
    echo json_encode(['success' => false, 'message' => 'لا تملك صلاحية عرض هذا الطالب']);
    exit;
}

// جلب تفاصيل الغياب
$stmt = $pdo->prepare("
    SELECT date, status, notes, is_excused, excuse_type, auto_generated
    FROM attendance 
    WHERE person_type = 'student' 
    AND person_id = ? 
    AND status IN ('absent', 'late')
    ORDER BY date DESC
    LIMIT 30
");
$stmt->execute([$student_id]);
$absences = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'absences' => $absences,
    'count' => count($absences)
]);
?>