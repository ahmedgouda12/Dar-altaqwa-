<?php
// ============================================
// ملف: export_annual_pdf.php
// تصدير الإنجازات السنوية كـ PDF
// آخر تحديث: 2026-04-02
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// جلب جميع البيانات للتقرير
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$completed_quran = $pdo->query("
    SELECT COUNT(DISTINCT student_id) 
    FROM student_surah_progress 
    WHERE completed = 1 
    GROUP BY student_id 
    HAVING COUNT(*) >= 114
")->rowCount();
$total_memorized = $pdo->query("SELECT COUNT(*) FROM student_surah_progress WHERE completed = 1")->fetchColumn();

$category_stats = $pdo->query("
    SELECT 
        SUM(CASE WHEN category = 'boy' THEN 1 ELSE 0 END) as boys,
        SUM(CASE WHEN category = 'girl' THEN 1 ELSE 0 END) as girls,
        SUM(CASE WHEN category = 'child' THEN 1 ELSE 0 END) as children,
        SUM(CASE WHEN category = 'woman' THEN 1 ELSE 0 END) as women
    FROM students
")->fetch();

$top_students = $pdo->query("
    SELECT s.name, s.category, COUNT(sp.id) as memorized_count
    FROM students s
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    GROUP BY s.id
    ORDER BY memorized_count DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الإنجازات السنوية - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: white;
            padding: 40px;
        }
        .report {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border: 2px solid #c9a96b;
            border-radius: 20px;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #c9a96b;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 2rem;
            color: #1e3c3f;
        }
        .header p {
            color: #666;
            margin-top: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border-right: 4px solid #c9a96b;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c3f;
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .category-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .top-student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.9rem;
        }
        @media print {
            body { padding: 0; }
            .report { border: none; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="report">
        <div class="header">
            <h1>دار التقوى لتحفيظ القرآن الكريم</h1>
            <p>تقرير الإنجازات السنوية - عام <?php echo $selected_year; ?></p>
            <p>منيا القمح - الشرقية</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo number_format($total_students); ?></div><div>إجمالي الطلاب</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo number_format($total_teachers); ?></div><div>المعلمون</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo number_format($completed_quran); ?></div><div>خاتمين للقرآن</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo number_format($total_memorized); ?></div><div>سور محفوظة</div></div>
        </div>

        <h3 style="margin: 20px 0 10px;">توزيع الطلاب حسب الفئة</h3>
        <div class="category-grid">
            <div class="category-card"><strong><?php echo number_format($category_stats['boys']); ?></strong><br>أولاد</div>
            <div class="category-card"><strong><?php echo number_format($category_stats['girls']); ?></strong><br>بنات</div>
            <div class="category-card"><strong><?php echo number_format($category_stats['children']); ?></strong><br>أطفال</div>
            <div class="category-card"><strong><?php echo number_format($category_stats['women']); ?></strong><br>نساء</div>
        </div>

        <h3 style="margin: 20px 0 10px;">أفضل 10 طلاب حفظاً</h3>
        <?php foreach ($top_students as $index => $student): ?>
            <div class="top-student-item">
                <span><strong>#<?php echo $index + 1; ?></strong> <?php echo htmlspecialchars($student['name']); ?></span>
                <span style="color: #c9a96b;"><?php echo $student['memorized_count']; ?> سورة</span>
            </div>
        <?php endforeach; ?>

        <div class="footer">
            <p>دار التقوى لتحفيظ القرآن الكريم - منيا القمح - الشرقية</p>
            <p>تم إنشاء هذا التقرير بتاريخ <?php echo date('Y-m-d'); ?></p>
        </div>
    </div>
</body>
</html>