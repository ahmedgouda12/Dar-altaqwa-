<?php
// ============================================
// ملف: add_surah.php - إضافة سورة محفوظة
// ============================================

require_once 'config.php';

if (!isTeacher() && !isAdmin()) {
    $_SESSION['error'] = "غير مصرح لك";
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['surah_number'])) {
    $_SESSION['error'] = "طلب غير صالح";
    header('Location: ' . (isTeacher() ? 'teacher_memorization_dashboard.php' : 'admin_memorization_report.php'));
    exit;
}

$student_id = (int)$_POST['student_id'];
$surah_number = (int)$_POST['surah_number'];
$notes = trim($_POST['notes'] ?? '');

if ($surah_number < 1 || $surah_number > 114) {
    $_SESSION['error'] = "رقم سورة غير صحيح";
    header('Location: teacher_memorization_dashboard.php');
    exit;
}

try {
    if (isTeacher()) {
        $teacher_id = $_SESSION['user_id'];
        $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
        $check->execute([$student_id, $teacher_id]);
        if (!$check->fetch()) {
            $_SESSION['error'] = "❌ هذا الطالب ليس من طلابك";
            header('Location: teacher_memorization_dashboard.php');
            exit;
        }
    } else {
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        if (!$teacher_id) {
            $stmt = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $teacher_id = $stmt->fetchColumn();
        }
    }
    
    // التحقق من عدم تكرار السورة
    $exists = $pdo->prepare("SELECT id FROM student_surah_progress WHERE student_id = ? AND surah_number = ?");
    $exists->execute([$student_id, $surah_number]);
    
    if ($exists->fetch()) {
        $_SESSION['error'] = "❌ هذه السورة مسجلة مسبقاً";
        header('Location: ' . (isTeacher() ? 'teacher_memorization_dashboard.php' : 'admin_memorization_report.php'));
        exit;
    }
    
    // إضافة السورة
    $stmt = $pdo->prepare("
        INSERT INTO student_surah_progress (student_id, teacher_id, surah_number, completed, completed_at, notes) 
        VALUES (?, ?, ?, 1, NOW(), ?)
    ");
    $stmt->execute([$student_id, $teacher_id, $surah_number, $notes]);
    
    // تحديث إحصائيات الأجزاء
    updateStudentPartsStats($pdo, $student_id);
    
    $total_pages = getStudentUniquePages($pdo, $student_id);
    $total_parts = calculatePartsFromUniquePages($total_pages);
    $surah_name = getSurahName($surah_number);
    
    $_SESSION['success'] = "✅ تمت إضافة سورة {$surah_name}<br>
                            📊 إجمالي الصفحات الفريدة: {$total_pages} صفحة<br>
                            📖 إجمالي الأجزاء: {$total_parts} جزء";
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
}
if (function_exists('updateStudentPoints')) {
    updateStudentPoints($pdo, $student_id);
    addPointsLog($pdo, $student_id, 10, 'surah', 'حفظ سورة جديدة');
}
header('Location: ' . (isTeacher() ? 'teacher_memorization_dashboard.php' : 'admin_memorization_report.php'));
exit;
?>