<?php
// ============================================
// ملف: delete_saved_account.php
// حذف حساب من قائمة الحسابات المخزنة
// ============================================

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$account_id = (int)$_POST['account_id'];
$account_type = $_POST['account_type'];

if (function_exists('deleteSavedAccount')) {
    $result = deleteSavedAccount($pdo, $_SESSION['user_id'], $account_id, $account_type);
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'الدالة غير موجودة']);
}
?>