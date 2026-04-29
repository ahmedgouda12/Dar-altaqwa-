<?php
// ============================================
// ملف: switch_account_local.php
// التبديل بين الحسابات باستخدام localStorage
// ============================================

require_once 'config.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_type = isset($_GET['type']) ? $_GET['type'] : '';

if (!$user_id || !$user_type) {
    header('Location: dashboard.php');
    exit;
}

$table = match($user_type) {
    'admin' => 'admins',
    'teacher' => 'teachers',
    'student' => 'students',
    'guardian' => 'guardians',
    default => null
};

if ($table) {
    if ($user_type == 'admin') {
        $stmt = $pdo->prepare("SELECT id, username as name FROM $table WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM $table WHERE id = ?");
    }
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // تسجيل الخروج من الجلسة الحالية
        session_destroy();
        session_start();
        
        // تسجيل الدخول إلى الحساب الجديد
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user_type;
        $_SESSION['user_name'] = $user['name'];
        
        // التوجيه للوحة المناسبة
        $redirect = match($user_type) {
            'admin' => 'dashboard.php',
            'teacher' => 'teacher_dashboard.php',
            'student' => 'student_dashboard.php',
            'guardian' => 'guardian_dashboard.php',
            default => 'dashboard.php'
        };
        
        header("Location: $redirect");
        exit;
    }
}

header('Location: login.php');
exit;
?>