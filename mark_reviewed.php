<?php
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$mistake_id = $input['id'] ?? 0;

if (!$mistake_id) {
    echo json_encode(['success' => false, 'error' => 'معرف الخطأ مطلوب']);
    exit;
}

$stmt = $pdo->prepare("UPDATE student_mistakes SET reviewed = 1, reviewed_date = CURDATE() WHERE id = ?");
$success = $stmt->execute([$mistake_id]);

echo json_encode(['success' => $success]);