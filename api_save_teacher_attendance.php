<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// استقبال البيانات
$input = json_decode(file_get_contents('php://input'), true);
$teacher_ids = $input['teachers'] ?? [];
$date = $input['date'] ?? '';
$user_id = $input['user_id'] ?? 0;
$user_type = $input['user_type'] ?? '';

// التحقق من البيانات
if (empty($teacher_ids) || empty($date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
    exit;
}

// التحقق من صحة التاريخ
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'صيغة تاريخ غير صحيحة']);
    exit;
}

// إذا كان المستخدم معلماً، يسمح فقط بتسجيل نفسه
if ($user_type === 'teacher') {
    if (count($teacher_ids) !== 1 || $teacher_ids[0] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'لا يمكنك تسجيل حضور معلم آخر']);
        exit;
    }
}

// حفظ الحضور
$inserted = 0;
$errors = [];

foreach ($teacher_ids as $teacher_id) {
    // التحقق من وجود المعلم
    $check = $pdo->prepare("SELECT id FROM teachers WHERE id = ?");
    $check->execute([$teacher_id]);
    if (!$check->fetch()) {
        $errors[] = "المعلم ID $teacher_id غير موجود";
        continue;
    }
    
    // التحقق من عدم التكرار
    $exists = $pdo->prepare("SELECT id FROM attendance WHERE person_type='teacher' AND person_id=? AND date=?");
    $exists->execute([$teacher_id, $date]);
    if ($exists->fetch()) {
        $errors[] = "المعلم ID $teacher_id مسجل مسبقاً";
        continue;
    }
    
    // إدراج الحضور
    try {
        $stmt = $pdo->prepare("INSERT INTO attendance (person_type, person_id, date, status, notes) VALUES ('teacher', ?, ?, 'present', ?)");
        $stmt->execute([$teacher_id, $date, 'تسجيل حضور']);
        $inserted++;
    } catch (PDOException $e) {
        $errors[] = "خطأ في إدراج المعلم ID $teacher_id: " . $e->getMessage();
    }
}

// إرجاع النتيجة
if ($inserted > 0) {
    echo json_encode([
        'success' => true,
        'inserted' => $inserted,
        'errors' => $errors,
        'message' => "تم تسجيل $inserted معلم بنجاح"
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم تسجيل أي معلم',
        'errors' => $errors
    ]);
}
?>