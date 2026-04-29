<?php
require_once 'config.php';

header('Content-Type: application/json');

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

if ($teacher_id > 0) {
    // جلب معلم واحد
    $stmt = $pdo->prepare("SELECT id, name, work_days FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // جلب جميع المعلمين (للمسؤول)
    $stmt = $pdo->query("SELECT id, name, work_days FROM teachers ORDER BY name");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($teachers);
?>