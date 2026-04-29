<?php
// ============================================
// ملف: admin_wirds_stats.php
// إحصائيات الأوراد والصلوات لجميع الطلاب (للمدير)
// نسخة مصححة بالكامل
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'includes/wirds_functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إحصائيات الأوراد والصلوات';
require_once 'includes/header.php';

$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// جلب جميع المعلمين للفلترة
$teachers = $pdo->query("
    SELECT t.id, t.name, 
           (SELECT COUNT(*) FROM students WHERE teacher_id = t.id) as students_count
    FROM teachers t
    WHERE t.can_login = 1
    ORDER BY t.name
")->fetchAll();

// بناء استعلام الطلاب - تم إصلاح ORDER BY
$sql = "
    SELECT 
        s.id,
        s.name,
        s.category,
        t.name as teacher_name,
        COALESCE(SUM(swr.points_earned), 0) as wird_points,
        COALESCE(SUM(spr.points_earned), 0) as prayer_points,
        COUNT(DISTINCT swr.record_date) as wird_days,
        COUNT(DISTINCT spr.prayer_date) as prayer_days,
        SUM(CASE WHEN swr.is_completed = 1 THEN 1 ELSE 0 END) as completed_wirds,
        SUM(CASE WHEN spr.status = 'jamaa' THEN 1 ELSE 0 END) as jamaa_count
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN student_wird_records swr ON s.id = swr.student_id
    LEFT JOIN student_prayer_records spr ON s.id = spr.student_id
    WHERE 1=1
";

$params = [];

if ($selected_teacher > 0) {
    $sql .= " AND s.teacher_id = ?";
    $params[] = $selected_teacher;
}

$sql .= " GROUP BY s.id ORDER BY (COALESCE(SUM(swr.points_earned), 0) + COALESCE(SUM(spr.points_earned), 0)) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// إحصائيات عامة
$total_students = count($students);
$total_wird_points = array_sum(array_column($students, 'wird_points'));
$total_prayer_points = array_sum(array_column($students, 'prayer_points'));
$students_with_wirds = count(array_filter($students, fn($s) => $s['wird_points'] > 0));
$students_with_prayers = count(array_filter($students, fn($s) => $s['prayer_points'] > 0));

// أفضل 10 طلاب
$top_students = array_slice($students, 0, 10);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائيات الأوراد والصلوات - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .admin-stats-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 0;
        }

        .filter-bar {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .filter-select {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            border-radius: 30px;
            font-size: 0.9rem;
            min-width: 200px;
            background: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c3f;
        }

        .stat-label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 30px;
            border-radius: 50px;
            background: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
        }

        .students-table {
            background: white;
            border-radius: 25px;
            overflow-x: auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th {
            background: #1e3c3f;
            color: white;
            padding: 15px;
            text-align: center;
        }

        td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .top-podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 25px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .podium-item {
            text-align: center;
            padding: 25px;
            border-radius: 20px;
            background: white;
            min-width: 200px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .podium-1 { transform: scale(1.05); border: 3px solid gold; background: linear-gradient(145deg, #fff, #fff9e6); }
        .podium-2 { border: 3px solid silver; }
        .podium-3 { border: 3px solid #cd7f32; }

        .podium-rank { font-size: 2rem; font-weight: bold; margin-bottom: 10px; }
        .podium-name { font-size: 1.2rem; font-weight: 700; color: #1e3c3f; }
        .podium-points { font-size: 1.3rem; color: #c9a96b; font-weight: 800; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-boy { background: #3498db; color: white; }
        .badge-girl { background: #9b59b6; color: white; }
        .badge-child { background: #f39c12; color: white; }
        .badge-woman { background: #e84342; color: white; }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .admin-stats-page { padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .top-podium { flex-direction: column; align-items: center; }
            .podium-1 { transform: scale(1); }
            .tabs { flex-direction: column; }
            .tab-btn { width: 100%; text-align: center; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<section class="admin-stats-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            إحصائيات الأوراد والصلوات
        </h1>
        <p>نظرة شاملة على أداء الطلاب في الأذكار والصلوات اليومية</p>
    </div>

    <!-- شريط الفلترة -->
    <div class="filter-bar">
        <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select name="teacher_id" class="filter-select" onchange="this.form.submit()">
                <option value="0">-- جميع المعلمين --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $selected_teacher == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['name']); ?> (<?php echo $t['students_count']; ?> طالب)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
        </form>
        <div class="stats-info">
            <i class="fas fa-info-circle"></i> آخر تحديث: <?php echo date('Y-m-d H:i'); ?>
        </div>
    </div>

    <!-- إحصائيات عامة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_students; ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_wird_points); ?></div>
            <div class="stat-label">نقاط الأوراد</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_prayer_points); ?></div>
            <div class="stat-label">نقاط الصلوات</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $students_with_wirds; ?></div>
            <div class="stat-label">طلاب سجلوا أوراداً</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $students_with_prayers; ?></div>
            <div class="stat-label">طلاب سجلوا صلوات</div>
        </div>
    </div>

    <!-- تبويبات -->
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab == 'overview' ? 'active' : ''; ?>" onclick="window.location.href='?<?php echo $selected_teacher ? 'teacher_id='.$selected_teacher.'&' : ''; ?>tab=overview'">
            <i class="fas fa-chart-simple"></i> نظرة عامة
        </button>
        <button class="tab-btn <?php echo $active_tab == 'top' ? 'active' : ''; ?>" onclick="window.location.href='?<?php echo $selected_teacher ? 'teacher_id='.$selected_teacher.'&' : ''; ?>tab=top'">
            <i class="fas fa-crown"></i> أفضل الطلاب
        </button>
        <button class="tab-btn <?php echo $active_tab == 'details' ? 'active' : ''; ?>" onclick="window.location.href='?<?php echo $selected_teacher ? 'teacher_id='.$selected_teacher.'&' : ''; ?>tab=details'">
            <i class="fas fa-table-list"></i> التفاصيل الكاملة
        </button>
    </div>

    <?php if ($active_tab == 'overview'): ?>
        <!-- نظرة عامة: إحصائيات حسب المعلمين -->
        <div class="students-table">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-chart-bar"></i> إحصائيات حسب المعلمين</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المعلم</th>
                        <th>عدد الطلاب</th>
                        <th>نقاط الأوراد</th>
                        <th>نقاط الصلوات</th>
                        <th>المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $teacher_stats = [];
                    foreach ($students as $s) {
                        $teacher = $s['teacher_name'] ?: 'غير محدد';
                        if (!isset($teacher_stats[$teacher])) {
                            $teacher_stats[$teacher] = [
                                'students_count' => 0,
                                'wird_points' => 0,
                                'prayer_points' => 0
                            ];
                        }
                        $teacher_stats[$teacher]['students_count']++;
                        $teacher_stats[$teacher]['wird_points'] += $s['wird_points'];
                        $teacher_stats[$teacher]['prayer_points'] += $s['prayer_points'];
                    }
                    $rank = 1;
                    foreach ($teacher_stats as $name => $stats): 
                        $total = $stats['wird_points'] + $stats['prayer_points'];
                    ?>
                        <tr>
                            <td><strong><?php echo $rank++; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($name); ?></strong></td>
                            <td><?php echo $stats['students_count']; ?></td>
                            <td><?php echo number_format($stats['wird_points']); ?></td>
                            <td><?php echo number_format($stats['prayer_points']); ?></td>
                            <td><span style="color: gold; font-weight: bold;"><?php echo number_format($total); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($active_tab == 'top'): ?>
        <!-- أفضل الطلاب مع منصة التتويج -->
        <div class="top-podium">
            <?php 
            $top3 = array_slice($top_students, 0, 3);
            foreach ($top3 as $index => $student):
                $position = $index + 1;
                $total = $student['wird_points'] + $student['prayer_points'];
                $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
            ?>
                <div class="podium-item podium-<?php echo $position; ?>">
                    <div class="podium-rank">
                        <?php if ($position == 1): ?>🥇
                        <?php elseif ($position == 2): ?>🥈
                        <?php else: ?>🥉
                        <?php endif; ?>
                    </div>
                    <div class="podium-name"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="podium-points"><?php echo number_format($total); ?> نقطة</div>
                    <div style="font-size: 0.8rem; color: #666;">
                        <span class="badge badge-<?php echo $student['category']; ?>">
                            <?php echo $cat_names[$student['category']] ?? $student['category']; ?>
                        </span>
                        <br><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="students-table">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-list"></i> قائمة أفضل <?php echo count($top_students); ?> طالب</h3>
            <table>
                <thead>
                    <tr>
                        <th>الترتيب</th>
                        <th>الطالب</th>
                        <th>المعلم</th>
                        <th>الفئة</th>
                        <th>نقاط الأوراد</th>
                        <th>نقاط الصلوات</th>
                        <th>المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_students as $index => $student): 
                        $total = $student['wird_points'] + $student['prayer_points'];
                        $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
                    ?>
                        <tr>
                            <td><strong>#<?php echo $index + 1; ?></strong></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></td>
                            <td><span class="badge badge-<?php echo $student['category']; ?>"><?php echo $cat_names[$student['category']] ?? $student['category']; ?></span></td>
                            <td><?php echo number_format($student['wird_points']); ?></td>
                            <td><?php echo number_format($student['prayer_points']); ?></td>
                            <td><span style="color: gold; font-weight: bold;"><?php echo number_format($total); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- التفاصيل الكاملة لجميع الطلاب -->
        <div class="students-table">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-table"></i> تفاصيل جميع الطلاب</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الطالب</th>
                        <th>المعلم</th>
                        <th>الفئة</th>
                        <th>نقاط الأوراد</th>
                        <th>أيام الأوراد</th>
                        <th>نقاط الصلوات</th>
                        <th>صلاة جماعة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء']; ?>
                    <?php foreach ($students as $index => $student): 
                        $total = $student['wird_points'] + $student['prayer_points'];
                    ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></td>
                            <td><span class="badge badge-<?php echo $student['category']; ?>"><?php echo $cat_names[$student['category']] ?? $student['category']; ?></span></td>
                            <td><?php echo number_format($student['wird_points']); ?></td>
                            <td><?php echo $student['wird_days']; ?> يوم</td>
                            <td><?php echo number_format($student['prayer_points']); ?></td>
                            <td><span style="color: #28a745;"><?php echo $student['jamaa_count']; ?></span></td>
                            <td><span style="color: gold; font-weight: bold;"><?php echo number_format($total); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">لا توجد بيانات للعرض</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>