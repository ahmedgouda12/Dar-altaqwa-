<?php
// ============================================
// ملف: check_duplicate.php - دوال التحقق من التكرار المتقدمة
// ============================================

require_once 'config.php';

/**
 * التحقق من وجود طالب بنفس الاسم بالضبط
 */
function checkExactMatch($pdo, $name, $phone) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.parent_phone, s.teacher_id, t.name as teacher_name,
               'student' as source
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.name = ? AND s.parent_phone = ?
        UNION
        SELECT r.id, r.student_name, r.parent_phone, NULL, NULL, 'request' as source
        FROM enrollment_requests r
        WHERE r.student_name = ? AND r.parent_phone = ? AND r.status != 'rejected'
        LIMIT 1
    ");
    $stmt->execute([$name, $phone, $name, $phone]);
    return $stmt->fetch();
}

/**
 * التحقق من وجود طالب بنفس رقم الهاتف
 */
function checkPhoneMatch($pdo, $phone) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.parent_phone, s.teacher_id, t.name as teacher_name,
               'student' as source
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.parent_phone = ?
        UNION
        SELECT r.id, r.student_name, r.parent_phone, NULL, NULL, 'request' as source
        FROM enrollment_requests r
        WHERE r.parent_phone = ? AND r.status != 'rejected'
    ");
    $stmt->execute([$phone, $phone]);
    return $stmt->fetchAll();
}

/**
 * التحقق الشامل من التكرار (الدالة الرئيسية)
 */
function comprehensiveDuplicateCheck($pdo, $student_name, $contact_phone, $student_age) {
    $result = [
        'is_duplicate' => false,
        'matches' => [],
        'message' => '',
        'suggestions' => []
    ];
    
    // 1. التحقق من التطابق التام
    $exactMatch = checkExactMatch($pdo, $student_name, $contact_phone);
    if ($exactMatch) {
        $result['is_duplicate'] = true;
        $source = $exactMatch['source'] == 'student' ? 'مسجل في النظام' : 'طلب سابق';
        $result['message'] = "⚠️ يوجد تطابق تام: {$exactMatch['name']} (هاتف: {$exactMatch['parent_phone']}) - {$source}";
        $result['matches'][] = $exactMatch;
        return $result;
    }
    
    // 2. التحقق من نفس رقم الهاتف
    $phoneMatches = checkPhoneMatch($pdo, $contact_phone);
    if (!empty($phoneMatches)) {
        $result['is_duplicate'] = true;
        $result['message'] = "⚠️ هذا الرقم مسجل بالفعل لطالب آخر: {$contact_phone}";
        $result['matches'] = $phoneMatches;
        return $result;
    }
    
    return $result;
}

/**
 * التحقق من وجود طالب في النظام قبل التوزيع (نسخة محسنة)
 */
function checkStudentExists($pdo, $name, $phone) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM students 
        WHERE name = ? AND parent_phone = ?
    ");
    $stmt->execute([$name, $phone]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        return true;
    }
    
    // التحقق من الطلبات الموزعة أيضاً
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM enrollment_requests 
        WHERE student_name = ? AND parent_phone = ? AND status = 'assigned'
    ");
    $stmt->execute([$name, $phone]);
    return $stmt->fetchColumn() > 0;
}

/**
 * الحصول على معلومات الطالب المكرر
 */
function getStudentDuplicateInfo($pdo, $name, $phone) {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as teacher_name
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.name = ? AND s.parent_phone = ?
    ");
    $stmt->execute([$name, $phone]);
    return $stmt->fetchAll();
}

/**
 * التحقق من أن الطالب لم يتم توزيعه مسبقاً
 */
function isStudentAlreadyAssigned($pdo, $name, $phone) {
    // التحقق في جدول students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE name = ? AND parent_phone = ?");
    $stmt->execute([$name, $phone]);
    if ($stmt->fetchColumn() > 0) {
        return true;
    }
    
    // التحقق في جدول enrollment_requests
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_requests WHERE student_name = ? AND parent_phone = ? AND status = 'assigned'");
    $stmt->execute([$name, $phone]);
    return $stmt->fetchColumn() > 0;
}
?>