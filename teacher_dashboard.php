<?php
// ============================================
// ملف: teacher_dashboard.php - لوحة تحكم المعلم
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';

date_default_timezone_set('Africa/Cairo');

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'لوحة تحكم المعلم - دار التقوى';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$today_day = date('w', strtotime($today)) + 1;

// جلب معلومات المعلم
$teacher = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM rings WHERE teacher_id = t.id) as rings_count,
           (SELECT COUNT(*) FROM students WHERE teacher_id = t.id) as students_count
    FROM teachers t WHERE t.id = ?
");
$teacher->execute([$teacher_id]);
$teacher_info = $teacher->fetch();

$total_students = $teacher_info['students_count'] ?? 0;

// الطلاب الخاصين
$special_students = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ? AND is_special = 1");
$special_students->execute([$teacher_id]);
$special_count = $special_students->fetchColumn();

// الطلاب الذين يمكنهم الحضور اليوم
$students_can_attend = 0;
$is_friday = ($today_day == 6);
$holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
$holiday_check->execute([$today, $today]);
$is_holiday = $holiday_check->fetch();

if (!$is_holiday && !$is_friday) {
    $students_can_attend = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) 
        FROM students s
        JOIN ring_students rs ON s.id = rs.student_id
        JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
        WHERE s.teacher_id = ? AND rsch.day_of_week = ?
    ");
    $students_can_attend->execute([$teacher_id, $today_day]);
    $students_can_attend = $students_can_attend->fetchColumn();
}

// الطلاب الحاضرين
$present_students = $pdo->prepare("
    SELECT COUNT(*) FROM attendance a
    JOIN students s ON a.person_id = s.id
    WHERE a.person_type = 'student' AND a.date = ? AND a.status = 'present' AND s.teacher_id = ?
");
$present_students->execute([$today, $teacher_id]);
$present_count = $present_students->fetchColumn();

$late_students = $pdo->prepare("
    SELECT COUNT(*) FROM attendance a
    JOIN students s ON a.person_id = s.id
    WHERE a.person_type = 'student' AND a.date = ? AND a.status = 'late' AND s.teacher_id = ?
");
$late_students->execute([$today, $teacher_id]);
$late_count = $late_students->fetchColumn();

$students_percentage = $students_can_attend > 0 ? round(($present_count / $students_can_attend) * 100) : 0;

// إحصائيات الحفظ
$total_memorized = $pdo->prepare("
    SELECT COUNT(*) FROM student_surah_progress sp
    JOIN students s ON sp.student_id = s.id
    WHERE s.teacher_id = ? AND sp.completed = 1
");
$total_memorized->execute([$teacher_id]);
$total_memorized = $total_memorized->fetchColumn();

$completed_quran = $pdo->prepare("
    SELECT COUNT(DISTINCT sp.student_id) 
    FROM student_surah_progress sp
    JOIN students s ON sp.student_id = s.id
    WHERE s.teacher_id = ? AND sp.completed = 1
    GROUP BY sp.student_id HAVING COUNT(*) >= 114
");
$completed_quran->execute([$teacher_id]);
$completed_count = $completed_quran->rowCount();

// أفضل الطلاب
$top_students = $pdo->prepare("
    SELECT s.id, s.name, COUNT(sp.id) as memorized_count
    FROM students s
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    WHERE s.teacher_id = ?
    GROUP BY s.id ORDER BY memorized_count DESC LIMIT 5
");
$top_students->execute([$teacher_id]);
$top_students = $top_students->fetchAll();

// حضور المعلم
$my_attendance = $pdo->prepare("SELECT status, notes, is_excused, excuse_reason FROM attendance WHERE person_type='teacher' AND person_id=? AND date=?");
$my_attendance->execute([$teacher_id, $today]);
$my_attendance_data = $my_attendance->fetch();
$is_present = $my_attendance_data ? true : false;
$is_excused = $my_attendance_data['is_excused'] ?? false;

// أيام العمل
$work_days = explode(',', $teacher_info['work_days'] ?? '1,2,3,4,5,6,7');
$work_days_names = [];
$days_names = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
foreach ($work_days as $day) {
    if (isset($days_names[$day - 1])) $work_days_names[] = $days_names[$day - 1];
}
$can_work_today = in_array($today_day, $work_days);

// الحلقات
$rings_list = [];
$rings_query = $pdo->prepare("
    SELECT r.id, r.name, r.description, r.location,
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
    FROM rings r WHERE r.teacher_id = ? ORDER BY r.name
");
$rings_query->execute([$teacher_id]);
$rings_list = $rings_query->fetchAll();
$total_rings = count($rings_list);

// ============================================
// الطلاب المنقطعون للمعلم (حساب دقيق)
// ============================================
$absent_stmt = $pdo->prepare("
    SELECT s.id, s.name, s.parent_phone,
           COUNT(a.id) as total_absences, MAX(a.date) as last_absence
    FROM students s
    LEFT JOIN attendance a ON s.id = a.person_id 
        AND a.person_type = 'student' 
        AND a.status = 'absent' 
        AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE s.teacher_id = ?
    GROUP BY s.id 
    HAVING total_absences >= 2 
    ORDER BY total_absences DESC
");
$absent_stmt->execute([$teacher_id]);
$all_absent = $absent_stmt->fetchAll();
$absent_count = count($all_absent);
$absent_list = array_slice($all_absent, 0, 5);

// التقييمات الأخيرة
$recent_evaluations = $pdo->prepare("
    SELECT e.*, s.name as student_name
    FROM student_daily_evaluations e
    JOIN students s ON e.student_id = s.id
    WHERE s.teacher_id = ?
    ORDER BY e.evaluation_date DESC, e.created_at DESC LIMIT 5
");
$recent_evaluations->execute([$teacher_id]);
$recent_evaluations = $recent_evaluations->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المعلم - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); min-height: 100vh; }
        .teacher-dashboard { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .welcome-card {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .welcome-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid #c9a96b;
        }
        .attendance-status-badge { background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 50px; display: flex; align-items: center; gap: 8px; }
        .status-excused { color: #ffc107; }
        .status-present { color: #28a745; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
        }
        .stat-icon.students { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); }
        .stat-icon.rings { background: linear-gradient(135deg, #c9a96b, #dbb87c); color: #1e3c3f; }
        .stat-icon.memorized { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.special { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-icon.prayer { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-number { font-size: 2rem; font-weight: 800; color: #2c3e50; }
        .stat-label { color: #6c757d; font-size: 0.85rem; }
        
        .attendance-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .attendance-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .attendance-header { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #c9a96b; }
        .attendance-numbers { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .attendance-item { text-align: center; }
        .attendance-value { font-size: 2rem; font-weight: 800; }
        .attendance-value.can { color: #17a2b8; }
        .attendance-value.present { color: #28a745; }
        .attendance-value.late { color: #ffc107; }
        .progress-bar { height: 10px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin-top: 15px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 10px; }
        
        .quick-actions {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
        .action-btn {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 20px;
            text-decoration: none;
            text-align: center;
            color: #2c3e50;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            border: 1px solid #e9ecef;
            cursor: pointer;
        }
        .action-btn i { font-size: 1.5rem; color: #c9a96b; }
        .action-btn:hover { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; transform: translateY(-3px); }
        .action-btn.excuse { background: linear-gradient(135deg, #fff3cd, #ffe69c); border-color: #ffc107; }
        .badge { position: relative; top: -5px; right: -5px; background: #dc3545; color: white; padding: 3px 10px; border-radius: 30px; font-size: 0.7rem; }
        
        .content-row { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        .content-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .card-header { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #c9a96b; }
        .item-list { max-height: 350px; overflow-y: auto; }
        .item-row { display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid #e9ecef; }
        .item-rank { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .rank-1 { background: gold; color: #212529; }
        .rank-2 { background: silver; color: #212529; }
        .rank-3 { background: #cd7f32; color: white; }
        .item-value { background: #c9a96b; color: #1e3c3f; padding: 5px 15px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; }
        
        .alert-item {
            background: #f8d7da;
            border-radius: 20px;
            padding: 15px;
            margin-bottom: 12px;
            border-right: 4px solid #dc3545;
        }
        .alert-title { font-weight: 700; color: #721c24; display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .alert-btn { background: #dc3545; color: white; padding: 5px 15px; border-radius: 30px; text-decoration: none; font-size: 0.75rem; display: inline-block; }
        
        .evaluation-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #e9ecef; }
        .evaluation-score { background: #c9a96b; color: #1e3c3f; padding: 3px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; }
        
        .links-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .link-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            text-decoration: none;
            text-align: center;
            transition: 0.3s;
            border: 1px solid #e9ecef;
            display: block;
        }
        .link-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .link-card i { font-size: 2.5rem; margin-bottom: 15px; }
        .link-card h4 { color: #1e3c3f; margin-bottom: 8px; }
        
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .attendance-stats { grid-template-columns: 1fr; }
            .content-row { grid-template-columns: 1fr; }
            .links-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .teacher-dashboard { padding: 15px; }
            .welcome-card { flex-direction: column; text-align: center; }
            .stats-grid { grid-template-columns: 1fr; }
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
            .links-row { grid-template-columns: 1fr; }
        }
        /* أيقونة مميزة للإجازات */
.action-btn.holiday {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.action-btn.holiday i {
    color: white;
}

.action-btn.holiday:hover {
    background: linear-gradient(135deg, #e67e22, #d35400);
}

/* إذا أردت استخدام الصنف holiday */
    </style>
</head>
<body>
<div class="teacher-dashboard">
    <div class="welcome-card">
        <div class="welcome-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="welcome-content">
            <h2>مرحباً، <?php echo htmlspecialchars($teacher_info['name'] ?? ''); ?></h2>
            <p><i class="fas fa-graduation-cap" style="color:#c9a96b;"></i> <?php echo htmlspecialchars($teacher_info['specialization'] ?? 'معلم قرآن كريم'); ?> | <i class="fas fa-phone" style="color:#c9a96b;"></i> <?php echo htmlspecialchars($teacher_info['phone'] ?? 'رقم غير محدد'); ?></p>
        </div>
        <div class="attendance-status-badge">
            <?php if ($is_excused): ?>
                <i class="fas fa-calendar-times status-excused"></i> <span>معتذر اليوم</span>
            <?php elseif ($is_present): ?>
                <i class="fas fa-check-circle status-present"></i> <span>تم تسجيل حضورك اليوم</span>
            <?php elseif ($can_work_today && !$is_holiday && !$is_friday): ?>
                <i class="fas fa-clock status-late"></i> <a href="attendance_teachers.php" style="color:white; text-decoration:underline;">سجل حضورك الآن</a>
            <?php elseif ($is_friday): ?>
                <i class="fas fa-mosque"></i> <span>يوم الجمعة - إجازة</span>
            <?php else: ?>
                <i class="fas fa-calendar-alt"></i> <span>اليوم ليس من أيام عملك</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon students"><i class="fas fa-users"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($total_students); ?></div><div class="stat-label">إجمالي الطلاب</div><div style="font-size:0.7rem; color:#999;"><?php echo $special_count; ?> طالب خاص</div></div></div>
        <div class="stat-card"><div class="stat-icon rings"><i class="fas fa-ring"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($total_rings); ?></div><div class="stat-label">الحلقات</div></div></div>
        <div class="stat-card"><div class="stat-icon memorized"><i class="fas fa-quran"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($total_memorized); ?></div><div class="stat-label">سور محفوظة</div><div style="font-size:0.7rem; color:#999;"><?php echo $completed_count; ?> طالب أتموا القرآن</div></div></div>
        <div class="stat-card"><div class="stat-icon special"><i class="fas fa-chart-line"></i></div><div class="stat-content"><div class="stat-number"><?php echo round(($total_memorized / max(1, $total_students)) / 114 * 100, 1); ?>%</div><div class="stat-label">نسبة الإنجاز</div></div></div>
        <div class="stat-card"><div class="stat-icon prayer"><i class="fas fa-star-and-crescent"></i></div><div class="stat-content"><div class="stat-number" id="teacherPrayerCount">0</div><div class="stat-label">صلاة اليوم على النبي ﷺ</div></div></div>
    </div>

    <div class="attendance-stats">
        <div class="attendance-card">
            <div class="attendance-header"><h3><i class="fas fa-user-graduate"></i> حضور الطلاب</h3><div><?php echo $today; ?></div></div>
            <div class="attendance-numbers">
                <div class="attendance-item"><div class="attendance-value can"><?php echo $students_can_attend; ?></div><div class="attendance-label">يمكنهم الحضور</div></div>
                <div class="attendance-item"><div class="attendance-value present"><?php echo $present_count; ?></div><div class="attendance-label">✅ حاضر</div></div>
                <div class="attendance-item"><div class="attendance-value late"><?php echo $late_count; ?></div><div class="attendance-label">⏰ متأخر</div></div>
            </div>
            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $students_percentage; ?>%;"></div></div>
            <div style="text-align:center; margin-top:8px;">نسبة الحضور: <?php echo $students_percentage; ?>%</div>
        </div>
        <div class="attendance-card">
            <div class="attendance-header"><h3><i class="fas fa-chalkboard-teacher"></i> أيام عملي</h3></div>
            <div class="attendance-numbers">
                <div class="attendance-item" style="grid-column:span 3;"><div class="attendance-value can" style="font-size:1.2rem;"><?php echo implode(' - ', $work_days_names); ?></div><div class="attendance-label">أيام العمل في الأسبوع</div></div>
            </div>
            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $can_work_today ? '100' : '0'; ?>%;"></div></div>
            <div style="text-align:center; margin-top:8px;"><?php echo $can_work_today ? '✅ يوم عمل' : '❌ ليس يوم عمل'; ?></div>
        </div>
    </div>

    <!-- ===== الإجراءات السريعة ===== -->
<div class="quick-actions">
    <h3><i class="fas fa-bolt"></i> الإجراءات السريعة</h3>
    <div class="actions-grid">
        <!-- حضور وغياب -->
        <a href="attendance_teachers.php" class="action-btn">
            <i class="fas fa-calendar-check"></i>
            <span>تسجيل حضوري</span>
        </a>
        <a href="teacher_quick_absence.php" class="action-btn excuse">
            <i class="fas fa-calendar-times"></i>
            <span>اعتذار سريع</span>
        </a>
        <a href="attendance_students_teacher.php" class="action-btn">
            <i class="fas fa-users"></i>
            <span>حضور طلابي</span>
        </a>
        
        <!-- إجازات -->
        <a href="manage_holidays.php" class="action-btn">
            <i class="fas fa-calendar-alt"></i>
            <span>إدارة الإجازات</span>
        </a>
        
        <!-- تقييم ومتابعة -->
        <a href="advanced_evaluation.php" class="action-btn">
            <i class="fas fa-star"></i>
            <span>تقييم يومي</span>
        </a>
        <a href="teacher_memorization_dashboard.php" class="action-btn">
            <i class="fas fa-quran"></i>
            <span>تقدم الحفظ</span>
        </a>
        
        <!-- الطلاب -->
        <a href="students.php" class="action-btn">
            <i class="fas fa-user-graduate"></i>
            <span>طلابي</span>
        </a>
        <a href="teacher_monthly_goals.php" class="action-btn">
            <i class="fas fa-bullseye"></i>
            <span>أهداف شهرية</span>
        </a>
        
        <!-- تنبيهات وطلبات -->
        <a href="repeated_absences.php" class="action-btn">
            <i class="fas fa-exclamation-triangle"></i>
            <span>المنقطعون</span>
            <?php if(count($absent_list) > 0): ?>
                <span class="badge"><?php echo count($absent_list); ?></span>
            <?php endif; ?>
        </a>
        <a href="special_teacher_requests.php" class="action-btn">
            <i class="fas fa-crown"></i>
            <span>طلبات خاص</span>
        </a>
    </div>
</div>

    <div class="content-row">
        <div class="content-card">
            <div class="card-header"><h4><i class="fas fa-crown" style="color:gold;"></i> أفضل الطلاب حفظاً</h4><a href="teacher_memorization_dashboard.php" style="color:#1e3c3f;">عرض الكل</a></div>
            <?php if(count($top_students)>0):?>
                <?php foreach($top_students as $index=>$student):?>
                <div class="item-row"><div class="item-rank rank-<?php echo $index+1; ?>"><?php echo $index+1; ?></div><div style="flex:1;"><?php echo htmlspecialchars($student['name']); ?></div><div class="item-value"><?php echo $student['memorized_count']; ?> سورة</div></div>
                <?php endforeach;?>
            <?php else:?>
                <div class="empty-state"><i class="fas fa-quran"></i><p>لا توجد بيانات بعد</p></div>
            <?php endif;?>
        </div>
        <div class="content-card">
            <div class="card-header"><h4><i class="fas fa-bell"></i> تنبيهات الغياب</h4></div>
            <?php if(count($absent_list)>0):?>
                <?php foreach($absent_list as $student):?>
                <div class="alert-item"><div class="alert-title"><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?></div><div style="margin-bottom:10px;">غاب <?php echo $student['total_absences']; ?> حصة | آخر غياب: <?php echo $student['last_absence']; ?></div><a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="alert-btn">متابعة</a></div>
                <?php endforeach;?>
            <?php else:?>
                <div class="empty-state"><i class="fas fa-check-circle" style="color:#28a745;"></i><p>لا توجد تنبيهات - جميع الطلاب منتظمون</p></div>
            <?php endif;?>
        </div>
    </div>

    <div class="content-row">
        <div class="content-card">
            <div class="card-header"><h4><i class="fas fa-star"></i> آخر التقييمات</h4><a href="advanced_evaluation.php" style="color:#1e3c3f;">تقييم جديد</a></div>
            <?php if(count($recent_evaluations)>0):?>
                <?php foreach($recent_evaluations as $eval):?>
                <div class="evaluation-item"><div><div class="evaluation-student" style="font-weight:600;"><?php echo htmlspecialchars($eval['student_name']); ?></div><div style="font-size:0.7rem; color:#999;"><?php echo $eval['evaluation_date']; ?></div></div><div class="evaluation-score"><?php echo round($eval['total_score']??0); ?>%</div></div>
                <?php endforeach;?>
            <?php else:?>
                <div class="empty-state"><i class="fas fa-star"></i><p>لا توجد تقييمات بعد</p></div>
            <?php endif;?>
        </div>
        <div class="content-card">
            <div class="card-header"><h4><i class="fas fa-ring"></i> حلقاتي</h4><a href="rings.php" style="color:#1e3c3f;">إدارة الحلقات</a></div>
            <?php if(count($rings_list)>0):?>
                <?php foreach($rings_list as $ring):?>
                <div class="item-row"><div style="flex:1;"><i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?></div><div class="item-value"><?php echo $ring['students_count']; ?> طالب</div></div>
                <?php endforeach;?>
            <?php else:?>
                <div class="empty-state"><i class="fas fa-ring"></i><p>لا توجد حلقات بعد</p></div>
            <?php endif;?>
        </div>
    </div>

    <div class="links-row">
        <a href="teacher_achievement.php" class="link-card"><i class="fas fa-chart-line" style="color:#1e3c3f;"></i><h4>إنجازاتي</h4><p>تقارير الإنجازات الشهرية</p></a>
        <a href="teacher_ring_achievements.php" class="link-card"><i class="fas fa-trophy" style="color:gold;"></i><h4>إنجازات الحلقات</h4><p>تسجيل إنجازات الحلقات</p></a>
        <a href="final_exam_dashboard.php" class="link-card"><i class="fas fa-graduation-cap" style="color:#17a2b8;"></i><h4>الاختبارات</h4><p>إدارة اختبارات الطلاب</p></a>
    </div>
</div>

<script>
fetch("ajax_record_prayer.php?action=get_stats")
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("teacherPrayerCount").innerHTML = data.today_count;
        }
    });
</script>

<?php
ob_end_flush();
require_once 'includes/footer.php';
?>
       