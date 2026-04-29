<?php
require_once 'config.php';
require_once 'functions.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>تشخيص مشكلة الطلاب</title>
    <style>
        body { font-family: 'Cairo', sans-serif; padding: 20px; background: #f5f7fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 25px; }
        h2 { color: #1e3c3f; border-bottom: 2px solid #c9a96b; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #1e3c3f; color: white; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 10px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h2>🔍 تشخيص مشكلة عرض الطلاب</h2>";

// 1. عرض معلومات الجلسة
echo "<h3>📋 معلومات الجلسة:</h3>";
echo "<pre>";
print_r([
    'user_id' => $_SESSION['user_id'] ?? 'غير موجود',
    'user_type' => $_SESSION['user_type'] ?? 'غير موجود',
    'user_name' => $_SESSION['user_name'] ?? 'غير موجود'
]);
echo "</pre>";

// 2. التحقق من وجود طلاب في قاعدة البيانات
echo "<h3>📊 إحصائيات الطلاب:</h3>";
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
echo "<p>إجمالي الطلاب في قاعدة البيانات: <strong>$total_students</strong></p>";

// 3. إذا كان المستخدم معلم، جلب طلابه
if (isTeacher()) {
    $teacher_id = $_SESSION['user_id'];
    echo "<h3>👨‍🏫 معلومات المعلم:</h3>";
    $teacher = $pdo->prepare("SELECT id, name, gender FROM teachers WHERE id = ?");
    $teacher->execute([$teacher_id]);
    $teacher_data = $teacher->fetch();
    echo "<pre>";
    print_r($teacher_data);
    echo "</pre>";
    
    // جلب طلاب المعلم مباشرة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $teacher_students = $stmt->fetchColumn();
    echo "<p>عدد طلاب هذا المعلم: <strong>$teacher_students</strong></p>";
    
    // جلب تفاصيل طلاب المعلم
    $stmt = $pdo->prepare("SELECT id, name, category FROM students WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $students_list = $stmt->fetchAll();
    
    if (count($students_list) > 0) {
        echo "<h3>📋 قائمة طلاب المعلم:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>الاسم</th><th>الفئة</th></tr>";
        foreach ($students_list as $s) {
            echo "<tr><td>{$s['id']}</td><td>{$s['name']}</td><td>{$s['category']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ لا يوجد طلاب مرتبطون بهذا المعلم!</p>";
        echo "<p>هل قمت بإضافة طلاب لهذا المعلم؟ إذا كان الطلاب موجودين ولكن teacher_id = NULL، فهذه هي المشكلة.</p>";
    }
}

// 4. عرض جميع الطلاب (للمسؤول فقط)
if (isAdmin()) {
    echo "<h3>📋 جميع الطلاب في النظام:</h3>";
    $all_students = $pdo->query("SELECT id, name, teacher_id, category FROM students LIMIT 20")->fetchAll();
    
    if (count($all_students) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>الاسم</th><th>teacher_id</th><th>الفئة</th></tr>";
        foreach ($all_students as $s) {
            $teacher_name = '';
            if ($s['teacher_id']) {
                $t = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
                $t->execute([$s['teacher_id']]);
                $teacher_name = $t->fetchColumn();
            }
            echo "<tr>
                    <td>{$s['id']}</td>
                    <td>{$s['name']}</td>
                    <td>" . ($s['teacher_id'] ?: 'NULL') . " ($teacher_name)</td>
                    <td>{$s['category']}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ لا يوجد طلاب في قاعدة البيانات!</p>";
    }
}

// 5. التحقق من الاستعلام المستخدم في advanced_evaluation.php
echo "<h3>🔧 الاستعلام المستخدم في advanced_evaluation.php:</h3>";
$today = date('Y-m-d');
if (isTeacher()) {
    $sql = "
        SELECT s.id, s.name, s.category, s.level,
               (SELECT COUNT(*) FROM student_daily_evaluations 
                WHERE student_id = s.id AND evaluation_date = '$today') as evaluated_today,
               (SELECT status FROM attendance 
                WHERE person_type = 'student' AND person_id = s.id AND date = '$today') as attendance_status,
               (SELECT is_scattered FROM rings r
                JOIN ring_students rs ON r.id = rs.ring_id
                WHERE rs.student_id = s.id AND r.is_scattered = 1 LIMIT 1) as is_scattered
        FROM students s
        WHERE s.teacher_id = ?
        ORDER BY s.name
    ";
    echo "<pre class='info'>$sql</pre>";
    
    // تنفيذ الاستعلام وعرض النتيجة
    $test_stmt = $pdo->prepare($sql);
    $test_stmt->execute([$teacher_id]);
    $test_results = $test_stmt->fetchAll();
    echo "<p>نتائج الاستعلام: <strong>" . count($test_results) . "</strong> طالب</p>";
    
    if (count($test_results) == 0) {
        echo "<p class='error'>❌ الاستعلام لا يعيد أي نتائج!</p>";
        echo "<p>الأسباب المحتملة:</p>";
        echo "<ul>";
        echo "<li>لا يوجد طلاب مرتبطون بهذا المعلم (teacher_id = NULL أو لا يساوي {$teacher_id})</li>";
        echo "<li>الجدول students لا يحتوي على عمود teacher_id</li>";
        echo "<li>المعلم ليس لديه صلاحية رؤية الطلاب</li>";
        echo "</ul>";
    } else {
        echo "<h3>نتائج الاستعلام:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>الاسم</th><th>الفئة</th><th>مقيم اليوم</th></tr>";
        foreach ($test_results as $r) {
            echo "<tr>
                    <td>{$r['id']}</td>
                    <td>{$r['name']}</td>
                    <td>{$r['category']}</td>
                    <td>" . ($r['evaluated_today'] ? 'نعم' : 'لا') . "</td>
                  </tr>";
        }
        echo "</table>";
    }
}

// 6. حل المشكلة: ربط الطلاب غير المرتبطين
if (isAdmin() && $total_students > 0) {
    $unassigned = $pdo->query("SELECT COUNT(*) FROM students WHERE teacher_id IS NULL OR teacher_id = 0")->fetchColumn();
    if ($unassigned > 0) {
        echo "<h3>⚠️ طلاب بدون معلم:</h3>";
        echo "<p>عدد الطلاب غير المرتبطين: <strong class='error'>$unassigned</strong></p>";
        
        // جلب أول معلم
        $first_teacher = $pdo->query("SELECT id, name FROM teachers LIMIT 1")->fetch();
        if ($first_teacher) {
            echo "<p>يمكنك ربطهم بالمعلم: <strong>{$first_teacher['name']}</strong></p>";
            echo "<a href='?fix_teachers=1' class='btn' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ربط الطلاب بهذا المعلم</a>";
            
            if (isset($_GET['fix_teachers'])) {
                $update = $pdo->prepare("UPDATE students SET teacher_id = ? WHERE teacher_id IS NULL OR teacher_id = 0");
                $update->execute([$first_teacher['id']]);
                $updated = $update->rowCount();
                echo "<p class='success'>✅ تم ربط $updated طالب بالمعلم {$first_teacher['name']}</p>";
                echo "<meta http-equiv='refresh' content='2'>";
            }
        }
    }
}

echo "</div></body></html>";
?>