<?php
// ============================================
// ملف: switch_account.php
// التبديل بين الحسابات
// ============================================

require_once 'config.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_type = isset($_GET['type']) ? $_GET['type'] : '';

if (!$user_id || !$user_type) {
    header('Location: dashboard.php');
    exit;
}

if ($user_type == 'admin') {
    $stmt = $pdo->prepare("SELECT id, username as name FROM admins WHERE id = ?");
} elseif ($user_type == 'teacher') {
    $stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE id = ?");
} elseif ($user_type == 'student') {
    $stmt = $pdo->prepare("SELECT id, name FROM students WHERE id = ?");
} elseif ($user_type == 'guardian') {
    $stmt = $pdo->prepare("SELECT id, name FROM guardians WHERE id = ?");
} else {
    header('Location: login.php');
    exit;
}

$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    session_destroy();
    session_start();
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_type'] = $user_type;
    $_SESSION['user_name'] = $user['name'];
    
    if ($user_type == 'admin') {
        header('Location: dashboard.php');
    } elseif ($user_type == 'teacher') {
        header('Location: teacher_dashboard.php');
    } elseif ($user_type == 'student') {
        header('Location: student_dashboard.php');
    } else {
        header('Location: guardian_dashboard.php');
    }
    exit;
}

header('Location: login.php');
exit;
?>