<?php
// ============================================
// ملف: dashboard.php - لوحة تحكم الإدارة
// ============================================

ob_start();
require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'لوحة تحكم الإدارة - دار التقوى';
require_once 'includes/header.php';

$today = date('Y-m-d');
$today_day = date('w', strtotime($today)) + 1;

// تحديث إحصائيات الأجزاء
$all_students_ids = $pdo->query("SELECT id FROM students")->fetchAll(PDO::FETCH_COLUMN);
foreach ($all_students_ids as $student_id) {
    updateStudentPartsStats($pdo, $student_id);
}

// إحصائيات الطلاب
$students_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN category = 'boy' THEN 1 ELSE 0 END) as boys,
        SUM(CASE WHEN category = 'girl' THEN 1 ELSE 0 END) as girls,
        SUM(CASE WHEN category = 'child' THEN 1 ELSE 0 END) as children,
        SUM(CASE WHEN category = 'woman' THEN 1 ELSE 0 END) as women
    FROM students
")->fetch();

$total_students = $students_stats['total'];

// إحصائيات السور والصفحات
$total_memorized = $pdo->query("SELECT COUNT(*) FROM student_surah_progress WHERE completed = 1")->fetchColumn();
$total_pages = $pdo->query("SELECT COALESCE(SUM(total_pages), 0) FROM student_parts_stats")->fetchColumn();
$total_parts_precise = $pdo->query("SELECT COALESCE(SUM(total_parts), 0) FROM student_parts_stats")->fetchColumn();
$avg_parts = $total_students > 0 ? round($total_parts_precise / $total_students, 2) : 0;

// الطلاب الخاتمين
$completed_quran = $pdo->query("SELECT COUNT(*) FROM student_parts_stats WHERE total_parts >= 30")->fetchColumn();

// إحصائيات المعلمين والحلقات
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE can_login = 1")->fetchColumn();
$total_rings = $pdo->query("SELECT COUNT(*) FROM rings")->fetchColumn();
$special_students = $pdo->query("SELECT COUNT(*) FROM students WHERE is_special = 1")->fetchColumn();

// المعلمين الذين يمكنهم الحضور اليوم
$teachers_can_attend = [];
$teachers_can_attend_count = 0;
$all_teachers = $pdo->query("SELECT id, name, gender, work_days, on_leave FROM teachers WHERE can_login = 1")->fetchAll();

foreach ($all_teachers as $teacher) {
    if ($teacher['on_leave']) continue;
    $work_days = explode(',', $teacher['work_days'] ?? '1,2,3,4,5,6,7');
    if (in_array($today_day, $work_days)) {
        $teachers_can_attend[] = $teacher;
        $teachers_can_attend_count++;
    }
}

$present_teachers_stmt = $pdo->prepare("SELECT person_id FROM attendance WHERE person_type = 'teacher' AND date = ?");
$present_teachers_stmt->execute([$today]);
$present_teachers_ids = $present_teachers_stmt->fetchAll(PDO::FETCH_COLUMN);

// المعلمين المعتذرين
$excused_teachers_stmt = $pdo->prepare("
    SELECT a.*, t.name as teacher_name, t.gender
    FROM attendance a
    JOIN teachers t ON a.person_id = t.id
    WHERE a.person_type = 'teacher' AND a.date = ? AND a.is_excused = 1
");
$excused_teachers_stmt->execute([$today]);
$excused_teachers = $excused_teachers_stmt->fetchAll();

// حضور الطلاب اليوم
$students_can_attend_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.id) 
    FROM students s
    JOIN ring_students rs ON s.id = rs.student_id
    JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
    WHERE rsch.day_of_week = ?
");
$students_can_attend_stmt->execute([$today_day]);
$students_can_attend = $students_can_attend_stmt->fetchColumn();

$present_students_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendance WHERE person_type = 'student' AND date = ? AND status = 'present'
");
$present_students_stmt->execute([$today]);
$present_students = $present_students_stmt->fetchColumn();

$late_students_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendance WHERE person_type = 'student' AND date = ? AND status = 'late'
");
$late_students_stmt->execute([$today]);
$late_students = $late_students_stmt->fetchColumn();

$students_percentage = $students_can_attend > 0 ? round(($present_students / $students_can_attend) * 100) : 0;

// الطلاب المنقطعين
// ============================================
// الطلاب المنقطعين - حساب العدد الدقيق (بدون LIMIT)
// ============================================

// أولاً: جلب قائمة الطلاب المنقطعين (بدون LIMIT)
$disconnected_query = "
    SELECT 
        s.id, s.name, s.parent_phone, t.name as teacher_name,
        COUNT(CASE WHEN a.status = 'absent' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as total_absences,
        MAX(CASE WHEN a.status IN ('present', 'late') THEN a.date END) as last_present
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN attendance a ON s.id = a.person_id AND a.person_type = 'student'
    GROUP BY s.id
    HAVING total_absences >= 3 OR (last_present IS NOT NULL AND last_present < DATE_SUB(CURDATE(), INTERVAL 7 DAY))
    ORDER BY total_absences DESC
";

// جلب العدد الإجمالي للمنقطعين
$disconnected_count_stmt = $pdo->prepare($disconnected_query);
$disconnected_count_stmt->execute();
$all_disconnected = $disconnected_count_stmt->fetchAll();
$disconnected_count = count($all_disconnected);

// جلب أول 5 لعرضهم في القائمة
$disconnected_students = array_slice($all_disconnected, 0, 5);

// الطلبات المعلقة
$pending_requests = $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending'")->fetchColumn();
$special_requests = $pdo->query("SELECT COUNT(*) FROM special_enrollment_requests WHERE status = 'pending'")->fetchColumn();

// توزيع الأجزاء
$students_with_no_memorization = $pdo->query("
    SELECT COUNT(*) FROM students s
    LEFT JOIN student_parts_stats sps ON s.id = sps.student_id
    WHERE sps.total_parts IS NULL OR sps.total_parts = 0
")->fetchColumn();

$precise_distribution = $pdo->query("
    SELECT FLOOR(total_parts) as part_number, COUNT(*) as count, AVG((total_parts - FLOOR(total_parts)) * 100) as avg_progress
    FROM student_parts_stats WHERE total_parts > 0
    GROUP BY FLOOR(total_parts) ORDER BY part_number
")->fetchAll();

$parts_distribution = array_fill(0, 31, 0);
$parts_avg_progress = array_fill(0, 31, 0);
foreach ($precise_distribution as $d) {
    $parts_distribution[$d['part_number']] = $d['count'];
    $parts_avg_progress[$d['part_number']] = round($d['avg_progress'], 1);
}
$parts_distribution[0] = $students_with_no_memorization;
$students_with_memorization = $total_students - $students_with_no_memorization;

// أفضل الطلاب
$top_students = $pdo->query("
    SELECT s.id, s.name, s.category, sps.total_parts, sps.total_pages, sps.description,
           COUNT(sp.id) as surahs_count
    FROM students s
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    LEFT JOIN student_parts_stats sps ON s.id = sps.student_id
    GROUP BY s.id
    ORDER BY sps.total_parts DESC, sps.total_pages DESC
    LIMIT 10
")->fetchAll();

// آخر النشاطات
$recent_activities_raw = $pdo->query("
    SELECT sp.completed_at, sp.surah_number, s.name as student_name, t.name as teacher_name
    FROM student_surah_progress sp
    JOIN students s ON sp.student_id = s.id
    LEFT JOIN teachers t ON sp.teacher_id = t.id
    WHERE sp.completed = 1
    ORDER BY sp.completed_at DESC LIMIT 5
")->fetchAll();

$recent_activities = [];
foreach ($recent_activities_raw as $activity) {
    $activity['surah_name'] = getSurahName($activity['surah_number']);
    $recent_activities[] = $activity;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الإدارة - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); min-height: 100vh; }
        .dashboard { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* بطاقة الترحيب */
        .welcome-card {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 25px 30px;
            border-radius: 30px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .welcome-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            border: 2px solid #c9a96b;
        }
        .welcome-content h2 { font-size: 1.5rem; margin-bottom: 5px; }
        .welcome-date { background: rgba(255,255,255,0.15); padding: 8px 20px; border-radius: 50px; font-size: 0.85rem; }
        
        /* إحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        .stat-icon.students { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); }
        .stat-icon.teachers { background: linear-gradient(135deg, #17a2b8, #138496); }
        .stat-icon.rings { background: linear-gradient(135deg, #c9a96b, #dbb87c); color: #1e3c3f; }
        .stat-icon.special { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-icon.disconnected { background: linear-gradient(135deg, #dc3545, #c82333); }
        .stat-icon.prayer { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-number { font-size: 1.6rem; font-weight: 800; color: #1e3c3f; }
        .stat-label { color: #6c757d; font-size: 0.7rem; }
        
        /* حضور */
        .attendance-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px; }
        .attendance-card {
            background: white;
            border-radius: 25px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .attendance-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        .attendance-numbers { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
        .attendance-item { text-align: center; }
        .attendance-value { font-size: 1.3rem; font-weight: 800; }
        .attendance-value.present { color: #28a745; }
        .attendance-value.late { color: #ffc107; }
        .attendance-value.can { color: #17a2b8; }
        .attendance-label { font-size: 0.65rem; color: #6c757d; }
        .progress-bar { height: 6px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 10px; }
        
        /* المعلمين */
        .teachers-card {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .teachers-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        .teachers-list { display: flex; flex-wrap: wrap; gap: 10px; max-height: 150px; overflow-y: auto; }
        .teacher-badge {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
        }
        .teacher-badge.present { background: #d4edda; color: #155724; border-right: 3px solid #28a745; }
        .teacher-badge.excused { background: #fff3cd; color: #856404; border-right: 3px solid #ffc107; }
        
        /* الأجزاء */
        .parts-stats {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .parts-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        .parts-summary {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        .summary-item { text-align: center; flex: 1; }
        .summary-number { font-size: 1.5rem; font-weight: 800; }
        .summary-number.completed { color: #28a745; }
        .summary-number.memorized { color: #17a2b8; }
        .summary-number.none { color: #dc3545; }
        .summary-label { font-size: 0.65rem; color: #6c757d; }
        .parts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            margin-top: 15px;
            max-height: 350px;
            overflow-y: auto;
        }
        .part-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 10px;
            text-align: center;
            border-right: 3px solid #e9ecef;
        }
        .part-item.completed { background: #d4edda; border-right-color: #28a745; }
        .part-number { font-size: 1rem; font-weight: 800; }
        .part-count { font-size: 0.6rem; color: #6c757d; }
        .part-bar { height: 3px; background: #e9ecef; border-radius: 2px; margin-top: 6px; overflow: hidden; }
        .part-bar-fill { height: 100%; background: linear-gradient(90deg, #c9a96b, #1e3c3f); border-radius: 2px; }
        
        /* أفضل الطلاب */
        .top-students-card {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .top-student-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .top-student-rank {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .rank-1 { background: gold; color: #212529; }
        .rank-2 { background: silver; color: #212529; }
        .rank-3 { background: #cd7f32; color: white; }
        .top-student-value {
            background: #c9a96b;
            color: #1e3c3f;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* إجراءات سريعة */
        .quick-actions {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        .action-btn {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 15px;
            text-decoration: none;
            text-align: center;
            color: #2c3e50;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            border: 1px solid #e9ecef;
            font-size: 0.7rem;
        }
        .action-btn i { font-size: 1.1rem; color: #c9a96b; }
        .action-btn:hover { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; transform: translateY(-3px); }
        .badge-count { background: #dc3545; color: white; border-radius: 30px; padding: 2px 8px; font-size: 0.6rem; }
        
        /* نشاطات */
        .activity-card {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* تنبيهات */
        .alert-item {
            background: #f8d7da;
            border-radius: 15px;
            padding: 12px;
            margin-bottom: 10px;
            border-right: 3px solid #dc3545;
        }
        .alert-title { font-weight: 700; color: #721c24; display: flex; align-items: center; gap: 5px; margin-bottom: 5px; }
        .alert-btn { background: #dc3545; color: white; padding: 3px 10px; border-radius: 30px; text-decoration: none; font-size: 0.65rem; display: inline-block; }
        
        /* روابط */
        .links-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .link-card {
            background: white;
            border-radius: 25px;
            padding: 20px;
            text-decoration: none;
            text-align: center;
            transition: 0.3s;
            border: 1px solid #e9ecef;
        }
        .link-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .link-card i { font-size: 2rem; margin-bottom: 10px; }
        .link-card h4 { color: #1e3c3f; font-size: 0.9rem; }
        
        .empty-state { text-align: center; padding: 30px; color: #6c757d; }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .parts-grid { grid-template-columns: repeat(5, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .attendance-stats { grid-template-columns: 1fr; }
            .parts-grid { grid-template-columns: repeat(3, 1fr); }
            .links-row { grid-template-columns: 1fr; }
            .welcome-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- بطاقة الترحيب -->
    <div class="welcome-card">
        <div class="welcome-avatar"><i class="fas fa-user-cog"></i></div>
        <div class="welcome-content">
            <h2>مرحباً، <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'مدير النظام'); ?></h2>
            <p>لوحة تحكم الإدارة - نظرة عامة على المنصة</p>
        </div>
        <div class="welcome-date"><i class="fas fa-calendar-alt"></i> <?php echo $today; ?></div>
    </div>

<!-- الإحصائيات الرئيسية -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon students"><i class="fas fa-users"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($total_students); ?></div><div class="stat-label">إجمالي الطلاب</div></div></div>
        <div class="stat-card"><div class="stat-icon teachers"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($total_teachers); ?></div><div class="stat-label">المعلمون</div></div></div>
        <div class="stat-card"><div class="stat-icon rings"><i class="fas fa-ring"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($total_rings); ?></div><div class="stat-label">الحلقات</div></div></div>
        <div class="stat-card"><div class="stat-icon special"><i class="fas fa-crown"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($special_students); ?></div><div class="stat-label">طلاب خاصين</div></div></div>
        <div class="stat-card"><div class="stat-icon disconnected"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-content"><div class="stat-number" style="color:#dc3545;"><?php echo $disconnected_count; ?></div><div class="stat-label">طلاب منقطعون</div></div></div>
        <div class="stat-card"><div class="stat-icon prayer"><i class="fas fa-star-and-crescent"></i></div><div class="stat-content"><div class="stat-number" id="adminPrayerCount">0</div><div class="stat-label">صلاة اليوم على النبي ﷺ</div></div></div>
    </div>

    <!-- حضور المعلمين والطلاب -->
    <div class="attendance-stats">
        <div class="attendance-card">
            <div class="attendance-header"><h3><i class="fas fa-chalkboard-teacher"></i> حضور المعلمين</h3><span><?php echo $today; ?></span></div>
            <div class="attendance-numbers">
                <div class="attendance-item"><div class="attendance-value present"><?php echo count($present_teachers_ids); ?></div><div class="attendance-label">✅ حاضر</div></div>
                <div class="attendance-item"><div class="attendance-value can"><?php echo $teachers_can_attend_count; ?></div><div class="attendance-label">يمكنهم الحضور</div></div>
                <div class="attendance-item"><div class="attendance-value can"><?php echo $total_teachers; ?></div><div class="attendance-label">إجمالي المعلمين</div></div>
            </div>
        </div>
        <div class="attendance-card">
            <div class="attendance-header"><h3><i class="fas fa-user-graduate"></i> حضور الطلاب</h3><span><?php echo $today; ?></span></div>
            <div class="attendance-numbers">
                <div class="attendance-item"><div class="attendance-value can"><?php echo $students_can_attend; ?></div><div class="attendance-label">يمكنهم الحضور</div></div>
                <div class="attendance-item"><div class="attendance-value present"><?php echo $present_students; ?></div><div class="attendance-label">✅ حاضر</div></div>
                <div class="attendance-item"><div class="attendance-value late"><?php echo $late_students; ?></div><div class="attendance-label">⏰ متأخر</div></div>
            </div>
            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $students_percentage; ?>%;"></div></div>
            <div style="text-align:center; font-size:0.65rem; margin-top:8px;">نسبة الحضور: <?php echo $students_percentage; ?>%</div>
        </div>
    </div>

    <!-- حالة المعلمين -->
    <div class="teachers-card">
        <div class="teachers-header"><h3><i class="fas fa-chalkboard-teacher"></i> حالة المعلمين اليوم</h3><span class="parts-badge"><?php echo $teachers_can_attend_count; ?> يمكنهم الحضور | <?php echo count($excused_teachers); ?> معتذر</span></div>
        <div class="teachers-list">
            <?php if (empty($teachers_can_attend) && empty($excused_teachers)): ?>
                <div class="empty-state"><i class="fas fa-info-circle"></i><p>لا يوجد معلمين يمكنهم الحضور اليوم</p></div>
            <?php else: ?>
                <?php foreach ($excused_teachers as $teacher): ?>
                    <div class="teacher-badge excused"><i class="fas <?php echo ($teacher['gender'] == 'male') ? 'fa-male' : 'fa-female'; ?>"></i> <?php echo htmlspecialchars($teacher['teacher_name']); ?> <span style="color:#ffc107;">⚠️ معتذر</span></div>
                <?php endforeach; ?>
                <?php foreach ($teachers_can_attend as $teacher): 
                    $is_present = in_array($teacher['id'], $present_teachers_ids);
                    $is_excused = false;
                    foreach ($excused_teachers as $et) { if ($et['person_id'] == $teacher['id']) $is_excused = true; }
                    if ($is_excused) continue;
                ?>
                    <div class="teacher-badge <?php echo $is_present ? 'present' : ''; ?>"><i class="fas <?php echo ($teacher['gender'] == 'male') ? 'fa-male' : 'fa-female'; ?>"></i> <?php echo htmlspecialchars($teacher['name']); ?> <?php if ($is_present): ?>✓<?php endif; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- توزيع الأجزاء -->
    <div class="parts-stats">
        <div class="parts-header"><h3><i class="fas fa-chart-bar"></i> توزيع الطلاب حسب الأجزاء المحفوظة</h3><span class="parts-badge">📊 <?php echo $students_with_memorization; ?> طالب لديهم حفظ</span></div>
        <div class="parts-summary">
            <div class="summary-item"><div class="summary-number completed"><?php echo $completed_quran; ?></div><div class="summary-label">🎓 خاتمين للقرآن</div></div>
            <div class="summary-item"><div class="summary-number memorized"><?php echo $students_with_memorization; ?></div><div class="summary-label">📖 لديهم حفظ</div></div>
            <div class="summary-item"><div class="summary-number none"><?php echo $total_students - $students_with_memorization; ?></div><div class="summary-label">🌱 مبتدئون</div></div>
            <div class="summary-item"><div class="summary-number memorized"><?php echo number_format($total_pages); ?></div><div class="summary-label">📄 صفحات فريدة</div></div>
            <div class="summary-item"><div class="summary-number memorized"><?php echo number_format($avg_parts, 2); ?></div><div class="summary-label">📊 متوسط الأجزاء</div></div>
        </div>
        <div class="parts-grid">
            <?php for ($parts = 1; $parts <= 30; $parts++): 
                $count = $parts_distribution[$parts] ?? 0;
                $percentage = $total_students > 0 ? round(($count / $total_students) * 100) : 0;
            ?>
                <div class="part-item <?php echo ($parts == 30 && $count > 0) ? 'completed' : ''; ?>">
                    <div class="part-number"><?php echo $parts; ?></div>
                    <div class="part-count"><?php echo $count; ?> طالب</div>
                    <div class="part-bar"><div class="part-bar-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
                </div>
            <?php endfor; ?>
        </div>
        <div style="margin-top:12px; padding:8px; background:#e7f3ff; border-radius:12px; font-size:0.65rem; color:#0c5460;"><i class="fas fa-info-circle"></i> يتم حساب الأجزاء بدقة: كل 20 صفحة = جزء واحد. القرآن الكريم 604 صفحات = 30 جزءاً كاملاً.</div>
    </div>

    <!-- أفضل الطلاب -->
    <div class="top-students-card">
        <div class="teachers-header"><h3><i class="fas fa-crown" style="color:gold;"></i> أفضل الطلاب حفظاً</h3><a href="admin_memorization_report.php" class="parts-badge" style="text-decoration:none;">عرض الكل</a></div>
        <?php if (empty($top_students)): ?>
            <div class="empty-state"><i class="fas fa-quran"></i><p>لا توجد بيانات بعد</p></div>
        <?php else: ?>
            <?php foreach ($top_students as $index => $student): 
                $rank = $index + 1;
                $parts = $student['total_parts'] ?? 0;
                $value_display = ($parts >= 30) ? '🎓 ختم القرآن' : number_format($parts, 2) . ' جزء';
            ?>
                <div class="top-student-item">
                    <div class="top-student-rank rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>"><?php if($rank==1):?>🥇<?php elseif($rank==2):?>🥈<?php elseif($rank==3):?>🥉<?php else: echo $rank; endif; ?></div>
                    <div style="flex:1;"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="top-student-value"><?php echo $value_display; ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- إجراءات سريعة -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> الإجراءات السريعة</h3>
        <div class="actions-grid">
            <a href="add_student.php" class="action-btn"><i class="fas fa-user-plus"></i><span>إضافة طالب</span></a>
            <a href="add_teacher.php" class="action-btn"><i class="fas fa-chalkboard-teacher"></i><span>إضافة معلم</span></a>
            <a href="add_ring.php" class="action-btn"><i class="fas fa-ring"></i><span>إضافة حلقة</span></a>
            <a href="attendance_teachers.php" class="action-btn"><i class="fas fa-calendar-check"></i><span>تسجيل حضور</span></a>
            <a href="students.php" class="action-btn"><i class="fas fa-users"></i><span>الطلاب</span></a>
            <a href="teachers.php" class="action-btn"><i class="fas fa-chalkboard-teacher"></i><span>المعلمون</span></a>
            <a href="enrollment_requests.php" class="action-btn"><i class="fas fa-user-check"></i><span>طلبات التحاق</span><?php if($pending_requests>0):?><span class="badge-count"><?php echo $pending_requests; ?></span><?php endif;?></a>
            <a href="special_requests.php" class="action-btn"><i class="fas fa-crown"></i><span>طلبات خاص</span><?php if($special_requests>0):?><span class="badge-count"><?php echo $special_requests; ?></span><?php endif;?></a>
            <a href="repeated_absences.php" class="action-btn"><i class="fas fa-exclamation-triangle"></i><span>المنقطعون</span><?php if($disconnected_count>0):?><span class="badge-count"><?php echo $disconnected_count; ?></span><?php endif;?></a>
            <a href="admin_transfer_students.php" class="action-btn"><i class="fas fa-exchange-alt"></i><span>نقل طلاب</span></a>
            <a href="admin_payments_report.php" class="action-btn"><i class="fas fa-money-bill-wave"></i><span>الاشتراكات</span></a>
        </div>
    </div>

    <!-- آخر النشاطات -->
    <div class="activity-card">
        <div class="teachers-header"><h3><i class="fas fa-history"></i> آخر النشاطات</h3></div>
        <?php if (empty($recent_activities)): ?>
            <div class="empty-state"><i class="fas fa-clock"></i><p>لا توجد نشاطات حديثة</p></div>
        <?php else: ?>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon"><i class="fas fa-quran"></i></div>
                    <div class="activity-details">
                        <div class="activity-text"><strong><?php echo htmlspecialchars($activity['student_name']); ?></strong> حفظ سورة <?php echo htmlspecialchars($activity['surah_name']); ?><?php if($activity['teacher_name']):?> مع <strong><?php echo htmlspecialchars($activity['teacher_name']); ?></strong><?php endif; ?></div>
                        <div class="activity-time"><i class="fas fa-clock"></i> <?php echo date('Y-m-d H:i', strtotime($activity['completed_at'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- تنبيهات الغياب -->
    <div class="top-students-card">
        <div class="teachers-header"><h3><i class="fas fa-bell"></i> تنبيهات الغياب المتكرر</h3><a href="repeated_absences.php" class="parts-badge" style="text-decoration:none;">عرض الكل</a></div>
        <?php if (empty($disconnected_students)): ?>
            <div class="empty-state"><i class="fas fa-check-circle" style="color:#28a745;"></i><p>لا توجد تنبيهات - جميع الطلاب منتظمون</p></div>
        <?php else: ?>
            <?php foreach ($disconnected_students as $student): ?>
                <div class="alert-item">
                    <div class="alert-title"><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="alert-details">غاب <?php echo $student['total_absences']; ?> حصة | آخر حضور: <?php echo $student['last_present'] ?? 'لم يحضر'; ?><?php if($student['teacher_name']):?> | معلم: <?php echo htmlspecialchars($student['teacher_name']); ?><?php endif; ?></div>
                    <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="alert-btn">متابعة</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- روابط سريعة -->
    <div class="links-row">
        <a href="parts_statistics_advanced.php" class="link-card"><i class="fas fa-chart-line" style="color:#1e3c3f;"></i><h4>إحصائيات الأجزاء</h4><p>توزيع دقيق حسب الأجزاء</p></a>
        <a href="final_exam_results.php" class="link-card"><i class="fas fa-trophy" style="color:gold;"></i><h4>نتائج الاختبارات</h4><p><?php echo $completed_quran; ?> طالب أتموا القرآن</p></a>
        <a href="admin_achievements.php" class="link-card"><i class="fas fa-chart-line" style="color:#c9a96b;"></i><h4>إنجازات الدار</h4><p>التقارير السنوية</p></a>
    </div>
</div>

<script>
fetch("ajax_record_prayer.php?action=get_stats")
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("adminPrayerCount").innerHTML = data.today_count;
        }
    });
</script>

<?php
ob_end_flush();
require_once 'includes/footer.php';
?>