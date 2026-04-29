<?php
// ============================================
// ملف: add_multiple_surahs.php - إضافة عدة سور دفعة واحدة
// ============================================

require_once 'config.php';

if (!isTeacher() && !isAdmin()) {
    $_SESSION['error'] = "غير مصرح لك";
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['surahs'])) {
    $_SESSION['error'] = "لم يتم تحديد أي سور";
    header('Location: ' . (isTeacher() ? 'teacher_memorization_dashboard.php' : 'admin_memorization_report.php'));
    exit;
}

$student_id = (int)$_POST['student_id'];
$selected_surahs = array_map('intval', (array)$_POST['surahs']);
$selected_surahs = array_filter($selected_surahs, fn($n) => $n >= 1 && $n <= 114);

if (empty($selected_surahs)) {
    $_SESSION['error'] = "لم يتم تحديد أي سور صالحة";
    header('Location: teacher_memorization_dashboard.php');
    exit;
}

try {
    if (isTeacher()) {
        $teacher_id = $_SESSION['user_id'];
        $check = $pdo->prepare("SELECT id, name FROM students WHERE id = ? AND teacher_id = ?");
        $check->execute([$student_id, $teacher_id]);
        $student = $check->fetch();
        if (!$student) {
            $_SESSION['error'] = "❌ هذا الطالب ليس من طلابك";
            header('Location: teacher_memorization_dashboard.php');
            exit;
        }
        $student_name = $student['name'];
    } else {
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        if (!$teacher_id) {
            $stmt = $pdo->prepare("SELECT teacher_id, name FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $data = $stmt->fetch();
            $teacher_id = $data['teacher_id'];
            $student_name = $data['name'];
        } else {
            $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student_name = $stmt->fetchColumn();
        }
    }
    
    $pdo->beginTransaction();
    $added = 0;
    $skipped = 0;
    $added_surahs = [];
    
    $check_stmt = $pdo->prepare("SELECT id FROM student_surah_progress WHERE student_id = ? AND surah_number = ?");
    $insert_stmt = $pdo->prepare("INSERT INTO student_surah_progress (student_id, teacher_id, surah_number, completed, completed_at) VALUES (?, ?, ?, 1, NOW())");
    
    foreach ($selected_surahs as $surah) {
        $check_stmt->execute([$student_id, $surah]);
        if (!$check_stmt->fetch()) {
            $insert_stmt->execute([$student_id, $teacher_id, $surah]);
            $added++;
            $added_surahs[] = $surah;
        } else {
            $skipped++;
        }
    }
    $pdo->commit();
    
    // تحديث إحصائيات الأجزاء
    updateStudentPartsStats($pdo, $student_id);
    
    $total_pages = getStudentUniquePages($pdo, $student_id);
    $total_parts = calculatePartsFromUniquePages($total_pages);
    
    $msg = "✅ تمت إضافة {$added} سورة للطالب {$student_name} بنجاح" . ($skipped ? " (تخطي {$skipped} مضافة مسبقاً)" : "");
    $msg .= "<br>📊 إجمالي الصفحات الفريدة: {$total_pages} صفحة";
    $msg .= "<br>📖 إجمالي الأجزاء: {$total_parts} جزء";
    
    if (!empty($added_surahs)) {
        $names = array_slice($added_surahs, 0, 5);
        $names_text = implode('، ', array_map('getSurahName', $names));
        if (count($added_surahs) > 5) $names_text .= " و" . (count($added_surahs)-5) . " أخرى";
        $msg .= "<br><small>السور المضافة: {$names_text}</small>";
    }
    $_SESSION['success'] = $msg;
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
}
if (function_exists('updateStudentPoints')) {
    updateStudentPoints($pdo, $student_id);
    addPointsLog($pdo, $student_id, 10, 'surah', 'حفظ سورة جديدة');
}
header('Location: ' . (isTeacher() ? 'teacher_memorization_dashboard.php' : 'admin_memorization_report.php'));
exit;
?>