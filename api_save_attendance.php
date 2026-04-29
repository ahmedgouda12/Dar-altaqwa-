<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$student_ids = $data['students'] ?? [];
$date = $data['date'] ?? '';
$teacher_id = $data['teacher_id'] ?? 0;

if (empty($student_ids) || empty($date) || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
    exit;
}

$inserted = 0;
foreach ($student_ids as $student_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO attendance (person_type, person_id, date, status, notes) VALUES ('student', ?, ?, 'present', ?)");
        $stmt->execute([$student_id, $date, 'تسجيل حضور']);
        $inserted++;
    } catch (PDOException $e) {
        // قد يكون مكرراً
    }
}

echo json_encode(['success' => true, 'inserted' => $inserted]);