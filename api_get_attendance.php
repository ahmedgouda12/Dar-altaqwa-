<?php
require_once 'config.php';

header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (empty($date) || empty($type)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT person_id, status FROM attendance WHERE person_type = ? AND date = ?");
$stmt->execute([$type, $date]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($attendance);
?>