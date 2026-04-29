<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isTeacher()) {
    echo json_encode([]);
    exit;
}

$challenge_id = isset($_GET['challenge_id']) ? (int)$_GET['challenge_id'] : 0;

$stmt = $pdo->prepare("
    SELECT s.id, s.name
    FROM challenge_participants cp
    JOIN students s ON cp.student_id = s.id
    WHERE cp.challenge_id = ?
    ORDER BY s.name
");
$stmt->execute([$challenge_id]);
$students = $stmt->fetchAll();

echo json_encode($students);