<?php
// ============================================
// ملف: logout.php - تسجيل الخروج
// ============================================

require_once 'config.php';

// حذف جميع متغيرات الجلسة
$_SESSION = array();

// حذف كوكي الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// إنهاء الجلسة
session_destroy();

// التوجيه إلى صفحة تسجيل الدخول
header("Location: login.php");
exit;
?>