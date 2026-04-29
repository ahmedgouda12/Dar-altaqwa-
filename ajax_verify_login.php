<?php
// ============================================
// ملف: ajax_verify_login.php
// التحقق من صحة بيانات الدخول وحفظ الحساب
// ============================================

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$type || !$username || !$password) {
    echo json_encode(['success' => false, 'error' => 'بيانات غير كاملة']);
    exit;
}

try {
    $user = null;
    
    if ($type === 'admin') {
        $stmt = $pdo->prepare("SELECT id, username as name FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && !password_verify($password, $user['password'] ?? '')) {
            $user = null;
        }
    } 
    elseif ($type === 'teacher') {
        $stmt = $pdo->prepare("SELECT id, name, password FROM teachers WHERE email = ? AND can_login = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && !password_verify($password, $user['password'])) {
            $user = null;
        }
    } 
    elseif ($type === 'student') {
        $stmt = $pdo->prepare("SELECT id, name, password FROM students WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && !password_verify($password, $user['password'])) {
            $user = null;
        }
    } 
    elseif ($type === 'guardian') {
        $clean_phone = preg_replace('/[^0-9]/', '', $username);
        $stmt = $pdo->prepare("SELECT id, name, password FROM guardians WHERE phone = ? OR username = ?");
        $stmt->execute([$clean_phone, $username]);
        $user = $stmt->fetch();
        if ($user && !password_verify($password, $user['password'])) {
            $user = null;
        }
    }
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'user_name' => $user['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'بيانات الدخول غير صحيحة']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>