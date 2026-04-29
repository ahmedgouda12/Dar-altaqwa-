<?php
// ============================================
// ملف: create_memorization_group.php
// إنشاء مجموعة طلاب متشابهين
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$group_name = trim($data['group_name'] ?? '');
$category = $data['category'] ?? '';
$student_ids = $data['student_ids'] ?? [];
$description = trim($data['description'] ?? '');

if (empty($group_name) || empty($category) || empty($student_ids)) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
    exit;
}

$result = createMemorizationGroup($pdo, $group_name, $category, $student_ids, $description);

echo json_encode($result);
?>