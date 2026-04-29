<?php
require_once 'config.php';
if (!isAdmin()) {
    die('غير مصرح');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="students_list.csv"');

// إضافة BOM ليدعم العربية في Excel
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// رأس الجدول
fputcsv($output, ['الرقم', 'اسم الطالب', 'رقم ولي الأمر', 'المعلم', 'المستوى', 'اسم المستخدم', 'كلمة المرور']);

// جلب البيانات
$students = $pdo->query("
    SELECT s.name, s.parent_phone, t.name as teacher_name, s.level, s.username
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    ORDER BY s.name
")->fetchAll();

foreach ($students as $index => $s) {
    fputcsv($output, [
        $index + 1,
        $s['name'],
        $s['parent_phone'] ?? '',
        $s['teacher_name'] ?? 'غير محدد',
        $s['level'] ?? 'مبتدئ',
        $s['username'] ?? '',
        '123456'
    ]);
}

fclose($output);