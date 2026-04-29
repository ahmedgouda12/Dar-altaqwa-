<?php
require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    die('غير مصرح بالوصول');
}

$today = date('Y-m-d');
$today_day = date('w') + 1;
$today_name = getDayNameArabic($today_day - 1);

echo "<h2>📊 تشخيص مشكلة الحلقات</h2>";
echo "<p>اليوم: $today ($today_name)</p>";
echo "<p>رقم اليوم في النظام: $today_day</p>";

// جلب جميع الحلقات
$rings = $pdo->query("
    SELECT r.*, t.name as teacher_name
    FROM rings r
    LEFT JOIN teachers t ON r.teacher_id = t.id
    ORDER BY r.name
")->fetchAll();

echo "<h3>📋 الحلقات المسجلة:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>اسم الحلقة</th><th>المعلم</th><th>أيام الحلقة</th></tr>";

foreach ($rings as $ring) {
    $schedules = $pdo->prepare("SELECT * FROM ring_schedules WHERE ring_id = ?");
    $schedules->execute([$ring['id']]);
    $schedules = $schedules->fetchAll();
    
    $days_text = [];
    foreach ($schedules as $sch) {
        $days_text[] = getDayNameArabic($sch['day_of_week'] - 1) . ' (' . $sch['start_time'] . ')';
    }
    
    echo "<tr>";
    echo "<td>{$ring['id']}</td>";
    echo "<td>{$ring['name']}</td>";
    echo "<td>{$ring['teacher_name']}</td>";
    echo "<td>" . (empty($days_text) ? '⚠️ لا يوجد أيام' : implode('<br>', $days_text)) . "</td>";
    echo "</tr>";
}
echo "</table>";

// جلب الطلاب مع حلقاتهم
$students = $pdo->query("
    SELECT s.id, s.name, s.teacher_id
    FROM students s
    ORDER BY s.name
")->fetchAll();

echo "<h3>👨‍🎓 الطلاب المسجلون في حلقات:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>الطالب</th><th>الحلقات</th><th>يمكنه الحضور اليوم؟</th></tr>";

foreach ($students as $student) {
    $rings_of_student = $pdo->prepare("
        SELECT r.name, rs.day_of_week
        FROM ring_students rstu
        JOIN rings r ON rstu.ring_id = r.id
        JOIN ring_schedules rs ON r.id = rs.ring_id
        WHERE rstu.student_id = ?
    ");
    $rings_of_student->execute([$student['id']]);
    $rings = $rings_of_student->fetchAll();
    
    $can_attend = isRingDayForStudent($pdo, $student['id'], $today);
    $status = $can_attend ? '✅ نعم' : '❌ لا';
    
    echo "<tr>";
    echo "<td>{$student['name']}</td>";
    echo "<td>";
    foreach ($rings as $r) {
        echo "- {$r['name']} (" . getDayNameArabic($r['day_of_week'] - 1) . ")<br>";
    }
    echo "</td>";
    echo "<td style='font-weight: bold; color: " . ($can_attend ? 'green' : 'red') . ";'>$status</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h4>🔧 إصلاح سريع:</h4>";
echo "<p>إذا كانت الأيام غير صحيحة، استخدم هذا الرابط لتحديثها:</p>";
echo "<a href='fix_ring_days.php' class='btn' onclick='return confirm(\"هل أنت متأكد؟ سيتم حذف جميع أيام الحلقات وإعادة إضافتها\")'>إصلاح أيام الحلقات</a>";