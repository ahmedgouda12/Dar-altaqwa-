<?php
// ============================================
// ملف: search_students_by_surahs.php
// البحث عن الطلاب الذين يحفظون سور محددة
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح']);
    exit;
}

$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$surahs_param = isset($_GET['surahs']) ? $_GET['surahs'] : '';

if (empty($surahs_param)) {
    echo json_encode([]);
    exit;
}

$surahs_array = explode(',', $surahs_param);
$surahs_count = count($surahs_array);
$placeholders = implode(',', array_fill(0, $surahs_count, '?'));

$sql = "
    SELECT 
        s.id,
        s.name,
        s.category,
        t.name as teacher_name,
        (SELECT COUNT(*) FROM student_surah_progress 
         WHERE student_id = s.id AND completed = 1 
         AND surah_number IN ($placeholders)) as matching_surahs,
        (SELECT COUNT(*) FROM student_surah_progress 
         WHERE student_id = s.id AND completed = 1) as total_surahs
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE 1=1
";

$params = $surahs_array;

if ($category != 'all') {
    $sql .= " AND s.category = ?";
    $params[] = $category;
}

$sql .= " HAVING matching_surahs = ?";
$params[] = $surahs_count;

$sql .= " ORDER BY s.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($students);
?>