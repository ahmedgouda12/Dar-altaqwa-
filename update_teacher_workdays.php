<?php
require_once 'config.php';

// هذه الدالة ستستخدم في صفحة تعديل المعلم
function updateTeacherWorkDays($pdo, $teacher_id, $work_days_array) {
    // تحويل المصفوفة إلى نص مفصول بفواصل
    $work_days_str = implode(',', $work_days_array);
    
    $stmt = $pdo->prepare("UPDATE teachers SET work_days = ? WHERE id = ?");
    return $stmt->execute([$work_days_str, $teacher_id]);
}

// مثال على استخدامها في صفحة تعديل المعلم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_workdays'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $work_days = isset($_POST['work_days']) ? $_POST['work_days'] : [];
    
    if (updateTeacherWorkDays($pdo, $teacher_id, $work_days)) {
        $success = "تم تحديث أيام العمل بنجاح";
    }
}
?>