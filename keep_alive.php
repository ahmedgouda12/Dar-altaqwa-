<?php
// ============================================
// ملف: keep_alive.php - الحفاظ على الجلسة
// آخر تحديث: 2026-04-02
// ============================================

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    // تحديث وقت آخر نشاط
    $_SESSION['last_activity'] = time();
    
    // تحديث آخر نشاط في قاعدة البيانات
    if (isset($pdo)) {
        updateUserActivity($pdo, $_SESSION['user_id'], $_SESSION['user_type']);
    }
    
    // تجديد الجلسة إذا مر وقت طويل (كل ساعة)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
        session_regenerate_id(true);
        $_SESSION['login_time'] = time();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث الجلسة بنجاح',
        'user_id' => $_SESSION['user_id'],
        'user_type' => $_SESSION['user_type'],
        'last_activity' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'لا توجد جلسة نشطة',
        'redirect' => 'login.php'
    ]);
}
?>