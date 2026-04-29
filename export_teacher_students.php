<?php
require_once 'config.php';
if (!isAdmin()) {
    die('غير مصرح');
}

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
if (!$teacher_id) {
    die('معلم غير محدد');
}

// جلب اسم المعلم
$teacher_name = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$teacher_name->execute([$teacher_id]);
$teacher_name = $teacher_name->fetchColumn();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="students_' . $teacher_name . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($output, ['#', 'اسم الطالب', 'ولي الأمر', 'المستوى', 'اسم المستخدم', 'كلمة المرور']);

$students = $pdo->prepare("
    SELECT name, parent_phone, level, username
    FROM students
    WHERE teacher_id = ?
    ORDER BY name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

foreach ($students as $index => $s) {
    fputcsv($output, [
        $index + 1,
        $s['name'],
        $s['parent_phone'] ?? '',
        $s['level'] ?? 'مبتدئ',
        $s['username'] ?? '',
        '123456'
    ]);
}

fclose($output);