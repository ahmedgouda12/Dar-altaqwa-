<?php
require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    die('غير مصرح بالوصول');
}

$teacher_id = isTeacher() ? $_SESSION['user_id'] : (isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0);

// إذا كان المسؤول، نعرض قائمة المعلمين للاختيار
if (isAdmin()) {
    $teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
    echo "<form method='get'>";
    echo "<label>اختر المعلم: </label>";
    echo "<select name='teacher_id' onchange='this.form.submit()'>";
    echo "<option value='0'>-- اختر --</option>";
    foreach ($teachers as $t) {
        $selected = $t['id'] == $teacher_id ? 'selected' : '';
        echo "<option value='{$t['id']}' $selected>{$t['name']}</option>";
    }
    echo "</select>";
    echo "</form>";
}

if (!$teacher_id) {
    exit;
}

// جلب طلاب المعلم
$students = $pdo->prepare("
    SELECT id, name FROM students WHERE teacher_id = ? ORDER BY name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

$today = date('Y-m-d');
$today_day = date('w') + 1;
$today_name = getDayNameArabic($today_day - 1);

echo "<h2>📅 اليوم: $today ($today_name) - رقم اليوم: $today_day</h2>";

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>
        <th>الطالب</th>
        <th>أيام الحلقات (نص)</th>
        <th>أيام الحلقات (أرقام)</th>
        <th>يمكنه الحضور اليوم؟</th>
        <th>سبب المنع</th>
      </tr>";

foreach ($students as $student) {
    $ring_days = getStudentRingDays($pdo, $student['id']);
    $ring_days_text = getStudentRingDaysText($pdo, $student['id']);
    
    $can_attend = in_array($today_day, $ring_days);
    $reason = $can_attend ? '✅ يمكنه الحضور' : '❌ اليوم ليس من أيام حلقاته';
    $color = $can_attend ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>{$student['name']}</td>";
    echo "<td>$ring_days_text</td>";
    echo "<td>" . ($ring_days ? implode(', ', $ring_days) : 'لا يوجد') . "</td>";
    echo "<td style='color: $color; font-weight: bold;'>" . ($can_attend ? 'نعم' : 'لا') . "</td>";
    echo "<td>$reason</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<a href='attendance_students_teacher.php'>العودة لتسجيل الحضور</a>";
?>