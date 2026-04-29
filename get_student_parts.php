<?php
// ============================================
// ملف: get_student_parts.php
// جلب تفاصيل الأجزاء لطالب معين (AJAX)
// ============================================

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT total_parts, parts_list, completed_parts 
        FROM student_parts_stats 
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $data = $stmt->fetch();
    
    if ($data) {
        echo json_encode([
            'success' => true,
            'total_parts' => $data['total_parts'],
            'parts_list' => json_decode($data['parts_list'], true) ?: []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'total_parts' => 0,
            'parts_list' => []
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'معرف الطالب مطلوب']);
}
?>