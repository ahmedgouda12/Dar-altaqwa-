<?php
// ============================================
// ملف: ajax_save_account.php
// حفظ حساب في قائمة الحسابات المخزنة
// ============================================

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$account_id = $data['account_id'] ?? 0;
$account_type = $data['account_type'] ?? '';
$account_name = $data['account_name'] ?? '';
$account_identifier = $data['account_identifier'] ?? '';

if (!$account_id || !$account_type) {
    echo json_encode(['success' => false, 'error' => 'بيانات غير كاملة']);
    exit;
}

if (function_exists('saveAccountForUser')) {
    $result = saveAccountForUser($pdo, $_SESSION['user_id'], $account_id, $account_type, $account_name, $account_identifier);
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'الدالة غير موجودة']);
}
?>