<?php
// ============================================
// ملف: attendance_summary.php
// ملخص الحضور والغياب - نسخة مصححة بالكامل
// آخر تحديث: 2026-04-13
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'ملخص الحضور والغياب';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$today = date('Y-m-d');

// ============================================
// معالجة الفلترة
// ============================================
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_date = isset($_GET['date']) ? $_GET['date'] : $today;
$filter_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// جلب المعلمين للفلترة
if (isAdmin()) {
    $teachers_list = $pdo->query("SELECT id, name FROM teachers WHERE can_login = 1 ORDER BY name")->fetchAll();
}

// ============================================
// إحصائيات عامة (للتاريخ المحدد)
// ============================================

$selected_date = $filter_date;
$selected_day = date('w', strtotime($selected_date)) + 1;
$is_friday = ($selected_day == 6);
$is_holiday = false;

// التحقق من العطل الرسمية
$holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
$holiday_check->execute([$selected_date, $selected_date]);
$is_holiday = $holiday_check->fetch();

// إجمالي المعلمين
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE can_login = 1")->fetchColumn();

// إجمالي الطلاب
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// المعلمين الذين يمكنهم الحضور في هذا اليوم
$teachers_can_attend = $pdo->prepare("
    SELECT COUNT(*) FROM teachers 
    WHERE can_login = 1 
    AND on_leave = 0
    AND FIND_IN_SET(?, work_days)
");
$teachers_can_attend->execute([$selected_day]);
$teachers_can_attend = $teachers_can_attend->fetchColumn();

// حضور المعلمين في التاريخ المحدد
$teachers_present = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'teacher' AND date = ? AND status = 'present'
");
$teachers_present->execute([$selected_date]);
$teachers_present = $teachers_present->fetchColumn();

$teachers_absent = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'teacher' AND date = ? AND status = 'absent'
");
$teachers_absent->execute([$selected_date]);
$teachers_absent = $teachers_absent->fetchColumn();

$teachers_late = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'teacher' AND date = ? AND status = 'late'
");
$teachers_late->execute([$selected_date]);
$teachers_late = $teachers_late->fetchColumn();

// الطلاب الذين يمكنهم الحضور في هذا اليوم
$students_can_attend = $pdo->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN ring_students rs ON s.id = rs.student_id
    JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
    WHERE rsch.day_of_week = ?
");
$students_can_attend->execute([$selected_day]);
$students_can_attend = $students_can_attend->fetchColumn();

// حضور الطلاب في التاريخ المحدد
$students_present = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'student' AND date = ? AND status = 'present'
");
$students_present->execute([$selected_date]);
$students_present = $students_present->fetchColumn();

$students_absent = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'student' AND date = ? AND status = 'absent'
");
$students_absent->execute([$selected_date]);
$students_absent = $students_absent->fetchColumn();

$students_late = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'student' AND date = ? AND status = 'late'
");
$students_late->execute([$selected_date]);
$students_late = $students_late->fetchColumn();

// نسبة الحضور
$teachers_percentage = $teachers_can_attend > 0 ? round(($teachers_present / $teachers_can_attend) * 100) : 0;
$students_percentage = $students_can_attend > 0 ? round(($students_present / $students_can_attend) * 100) : 0;

// ============================================
// جلب سجلات الحضور مع التفاصيل
// ============================================

$query = "
    SELECT 
        a.*,
        CASE 
            WHEN a.person_type = 'teacher' THEN (SELECT name FROM teachers WHERE id = a.person_id)
            ELSE (SELECT name FROM students WHERE id = a.person_id)
        END as person_name,
        CASE 
            WHEN a.person_type = 'teacher' THEN (SELECT gender FROM teachers WHERE id = a.person_id)
            ELSE (SELECT category FROM students WHERE id = a.person_id)
        END as person_category,
        CASE 
            WHEN a.person_type = 'teacher' THEN (SELECT phone FROM teachers WHERE id = a.person_id)
            ELSE (SELECT parent_phone FROM students WHERE id = a.person_id)
        END as person_phone,
        CASE 
            WHEN a.person_type = 'teacher' THEN (SELECT work_days FROM teachers WHERE id = a.person_id)
            ELSE NULL
        END as work_days
    FROM attendance a
    WHERE 1=1
";

$params = [];

if ($filter_type != 'all') {
    $query .= " AND a.person_type = ?";
    $params[] = $filter_type;
}

$query .= " AND a.date = ?";
$params[] = $filter_date;

if ($filter_teacher > 0 && $filter_type == 'student') {
    $query .= " AND a.person_id IN (SELECT id FROM students WHERE teacher_id = ?)";
    $params[] = $filter_teacher;
}

if ($filter_status != 'all') {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY a.person_type, a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// إحصائيات السجلات المعروضة
$total_records = count($attendance_records);
$total_present = count(array_filter($attendance_records, fn($r) => ($r['status'] ?? '') == 'present'));
$total_absent = count(array_filter($attendance_records, fn($r) => ($r['status'] ?? '') == 'absent'));
$total_late = count(array_filter($attendance_records, fn($r) => ($r['status'] ?? '') == 'late'));
$auto_generated_count = count(array_filter($attendance_records, fn($r) => !empty($r['auto_generated']) && $r['auto_generated'] == 1));

// ============================================
// إحصائيات شهرية (لآخر 30 يوم)
// ============================================
$current_month = date('Y-m');
$monthly_teachers_present = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'teacher' AND DATE_FORMAT(date, '%Y-%m') = ? AND status = 'present'
");
$monthly_teachers_present->execute([$current_month]);
$monthly_teachers_present = $monthly_teachers_present->fetchColumn();

$monthly_students_present = $pdo->prepare("
    SELECT COUNT(*) FROM attendance 
    WHERE person_type = 'student' AND DATE_FORMAT(date, '%Y-%m') = ? AND status = 'present'
");
$monthly_students_present->execute([$current_month]);
$monthly_students_present = $monthly_students_present->fetchColumn();

// أفضل المعلمين حضوراً هذا الشهر
$top_teachers = $pdo->query("
    SELECT 
        t.name,
        COUNT(a.id) as attendance_count
    FROM attendance a
    JOIN teachers t ON a.person_id = t.id
    WHERE a.person_type = 'teacher' 
    AND DATE_FORMAT(a.date, '%Y-%m') = '{$current_month}'
    AND a.status = 'present'
    GROUP BY t.id
    ORDER BY attendance_count DESC
    LIMIT 5
")->fetchAll();

// أفضل الطلاب حضوراً هذا الشهر
$top_students_monthly = $pdo->query("
    SELECT 
        s.name,
        s.category,
        COUNT(a.id) as attendance_count
    FROM attendance a
    JOIN students s ON a.person_id = s.id
    WHERE a.person_type = 'student' 
    AND DATE_FORMAT(a.date, '%Y-%m') = '{$current_month}'
    AND a.status = 'present'
    GROUP BY s.id
    ORDER BY attendance_count DESC
    LIMIT 5
")->fetchAll();

// ============================================
// إحصائيات الغياب التلقائي
// ============================================
$auto_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_auto,
        COUNT(CASE WHEN person_type = 'teacher' THEN 1 END) as teachers_auto,
        COUNT(CASE WHEN person_type = 'student' THEN 1 END) as students_auto,
        MAX(date) as last_auto_date
    FROM attendance 
    WHERE auto_generated = 1
")->fetch();

// آخر 5 تسجيلات غياب تلقائي
$recent_auto = $pdo->query("
    SELECT a.*,
           CASE WHEN a.person_type = 'teacher' THEN (SELECT name FROM teachers WHERE id = a.person_id)
                ELSE (SELECT name FROM students WHERE id = a.person_id)
           END as person_name
    FROM attendance a
    WHERE a.auto_generated = 1
    ORDER BY a.date DESC, a.created_at DESC
    LIMIT 5
")->fetchAll();

// تنسيق التاريخ للعرض
$formatted_date = date('Y-m-d', strtotime($selected_date));
$day_names = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
$day_name = $day_names[date('w', strtotime($selected_date))];

// دالة مساعدة آمنة لـ htmlspecialchars
function safeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --gray: #6c757d;
            --gray-light: #e9ecef;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        .summary-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* ===== رأس الصفحة ===== */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-size: 1.8rem;
        }

        .page-header h1 i {
            color: var(--secondary);
        }

        .page-header p {
            margin-top: 10px;
            opacity: 0.9;
        }

        .selected-date {
            margin-top: 15px;
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: 30px;
            display: inline-block;
            font-size: 0.9rem;
        }

        /* ===== شريط الفلترة ===== */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 20px;
            border: 2px solid var(--gray-light);
            border-radius: 30px;
            font-size: 0.9rem;
            background: white;
            min-width: 130px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .filter-btn {
            padding: 10px 25px;
            border-radius: 30px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            background: var(--primary);
            color: white;
        }

        .filter-btn:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .reset-btn {
            background: #6c757d;
        }

        .reset-btn:hover {
            background: #5a6268;
        }

        /* ===== بطاقات الإحصائيات ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--primary));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .progress-bar-small {
            height: 6px;
            background: var(--gray-light);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill-small {
            height: 100%;
            background: var(--success);
            border-radius: 10px;
            transition: width 0.5s;
        }

        /* ===== رسائل التنبيه ===== */
        .notice-box {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-right: 5px solid var(--warning);
        }

        /* ===== جدول السجلات ===== */
        .records-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
            color: var(--primary);
        }

        .section-title i {
            font-size: 1.3rem;
        }

        .records-count {
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        .table-container {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: var(--primary);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }

        .attendance-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid var(--gray-light);
            vertical-align: middle;
        }

        .record-row:hover {
            background: #f8f9fa;
        }

        .type-icon {
            font-size: 1.2rem;
            margin-left: 5px;
        }

        .record-phone, .record-category {
            font-size: 0.7rem;
            color: #666;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-present {
            background: #d4edda;
            color: #155724;
        }

        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .status-late {
            background: #fff3cd;
            color: #856404;
        }

        .auto-badge {
            background: #e9ecef;
            color: #6c757d;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-left: 5px;
        }

        .records-summary {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* ===== إحصائيات شهرية ===== */
        .monthly-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .monthly-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .monthly-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-list {
            list-style: none;
            padding: 0;
        }

        .top-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .top-list li:last-child {
            border-bottom: none;
        }

        .top-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .rank-1 { background: gold; color: #212529; }
        .rank-2 { background: silver; color: #212529; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-other { background: #e9ecef; color: #6c757d; }

        /* ===== الغياب التلقائي ===== */
        .auto-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-right: 5px solid var(--warning);
        }

        .auto-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 15px 0;
        }

        .auto-stat {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 15px;
            text-align: center;
        }

        .auto-stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--warning);
        }

        .auto-stat-label {
            font-size: 0.7rem;
            color: #666;
        }

        .auto-list {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .auto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--gray-light);
            font-size: 0.85rem;
        }

        /* ===== أزرار الإجراءات ===== */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-light);
            margin-bottom: 15px;
        }

        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .monthly-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .summary-page { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-select { flex: 1; }
            .filter-btn { width: 100%; }
            .attendance-table th,
            .attendance-table td {
                padding: 8px;
                font-size: 0.8rem;
            }
            .record-phone, .record-category {
                display: none;
            }
            .records-summary {
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }
            .auto-stats { flex-direction: column; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .page-header h1 { font-size: 1.4rem; }
            .stat-number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<section class="summary-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            ملخص الحضور والغياب
        </h1>
        <p>نظرة شاملة على حضور المعلمين والطلاب</p>
        <div class="selected-date">
            <i class="fas fa-calendar-alt"></i>
            <?php echo safeHtml($formatted_date) . ' - ' . safeHtml($day_name); ?>
        </div>
    </div>

    <!-- شريط الفلترة -->
    <div class="filter-bar">
        <div class="filter-group">
            <select id="typeFilter" class="filter-select" onchange="applyFilters()">
                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>الكل</option>
                <option value="teacher" <?php echo $filter_type == 'teacher' ? 'selected' : ''; ?>>معلمين</option>
                <option value="student" <?php echo $filter_type == 'student' ? 'selected' : ''; ?>>طلاب</option>
            </select>
            
            <input type="date" id="dateFilter" class="filter-select" value="<?php echo safeHtml($filter_date); ?>" onchange="applyFilters()">
            
            <select id="statusFilter" class="filter-select" onchange="applyFilters()">
                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                <option value="present" <?php echo $filter_status == 'present' ? 'selected' : ''; ?>>حاضر</option>
                <option value="absent" <?php echo $filter_status == 'absent' ? 'selected' : ''; ?>>غائب</option>
                <option value="late" <?php echo $filter_status == 'late' ? 'selected' : ''; ?>>متأخر</option>
            </select>
            
            <?php if (isAdmin() && $filter_type == 'student'): ?>
            <select id="teacherFilter" class="filter-select" onchange="applyFilters()">
                <option value="0">-- جميع المعلمين --</option>
                <?php foreach ($teachers_list as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $filter_teacher == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo safeHtml($t['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <button class="filter-btn" onclick="applyFilters()">
                <i class="fas fa-search"></i> بحث
            </button>
            <button class="filter-btn reset-btn" onclick="resetFilters()">
                <i class="fas fa-undo"></i> إعادة تعيين
            </button>
        </div>
    </div>

    <!-- تنبيه إذا كان اليوم عطلة -->
    <?php if ($is_friday): ?>
    <div class="notice-box">
        <i class="fas fa-mosque fa-2x"></i>
        <div>
            <strong>📅 يوم الجمعة</strong><br>
            يوم الجمعة إجازة رسمية، لا توجد حصص دراسية في هذا اليوم.
        </div>
    </div>
    <?php elseif ($is_holiday): ?>
    <div class="notice-box">
        <i class="fas fa-calendar-times fa-2x"></i>
        <div>
            <strong>📅 عطلة رسمية</strong><br>
            هذا اليوم عطلة رسمية، لا توجد حصص دراسية.
        </div>
    </div>
    <?php endif; ?>

    <!-- إحصائيات التاريخ المحدد -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👨‍🏫</div>
            <div class="stat-number"><?php echo $teachers_present; ?>/<?php echo $teachers_can_attend; ?></div>
            <div class="stat-label">معلمين حاضرين</div>
            <?php if (!$is_friday && !$is_holiday && $teachers_can_attend > 0): ?>
            <div class="progress-bar-small">
                <div class="progress-fill-small" style="width: <?php echo $teachers_percentage; ?>%;"></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-number"><?php echo $students_present; ?>/<?php echo $students_can_attend; ?></div>
            <div class="stat-label">طلاب حاضرين</div>
            <?php if (!$is_friday && !$is_holiday && $students_can_attend > 0): ?>
            <div class="progress-bar-small">
                <div class="progress-fill-small" style="width: <?php echo $students_percentage; ?>%;"></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏰</div>
            <div class="stat-number"><?php echo $teachers_late + $students_late; ?></div>
            <div class="stat-label">متأخرين</div>
            <div class="stat-label" style="font-size:0.7rem;">(معلمين: <?php echo $teachers_late; ?> | طلاب: <?php echo $students_late; ?>)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">❌</div>
            <div class="stat-number"><?php echo $teachers_absent + $students_absent; ?></div>
            <div class="stat-label">غائبين</div>
            <div class="stat-label" style="font-size:0.7rem;">(معلمين: <?php echo $teachers_absent; ?> | طلاب: <?php echo $students_absent; ?>)</div>
        </div>
    </div>

    <!-- إحصائيات شهرية -->
    <div class="monthly-stats">
        <div class="monthly-card">
            <h3><i class="fas fa-chalkboard-teacher"></i> أفضل المعلمين حضوراً (<?php echo safeHtml($current_month); ?>)</h3>
            <?php if (empty($top_teachers)): ?>
                <div class="empty-state" style="padding: 20px;"><i class="fas fa-chart-line"></i><p>لا توجد بيانات</p></div>
            <?php else: ?>
                <ul class="top-list">
                    <?php foreach ($top_teachers as $index => $t): ?>
                    <li>
                        <div>
                            <span class="top-rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></span>
                            <strong><?php echo safeHtml($t['name']); ?></strong>
                        </div>
                        <span class="status-badge status-present"><?php echo $t['attendance_count']; ?> يوم</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="monthly-card">
            <h3><i class="fas fa-user-graduate"></i> أفضل الطلاب حضوراً (<?php echo safeHtml($current_month); ?>)</h3>
            <?php if (empty($top_students_monthly)): ?>
                <div class="empty-state" style="padding: 20px;"><i class="fas fa-chart-line"></i><p>لا توجد بيانات</p></div>
            <?php else: ?>
                <ul class="top-list">
                    <?php foreach ($top_students_monthly as $index => $s): ?>
                    <li>
                        <div>
                            <span class="top-rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></span>
                            <strong><?php echo safeHtml($s['name']); ?></strong>
                            <span class="student-category" style="font-size:0.7rem; margin-right:5px;">(<?php 
                                $cat_name = match($s['category']) {
                                    'boy' => 'أولاد',
                                    'girl' => 'بنات',
                                    'child' => 'أطفال',
                                    'woman' => 'نساء',
                                    default => $s['category']
                                };
                                echo safeHtml($cat_name); 
                            ?>)</span>
                        </div>
                        <span class="status-badge status-present"><?php echo $s['attendance_count']; ?> يوم</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- الغياب التلقائي -->
    <div class="auto-card">
        <div class="section-title" style="margin-bottom: 10px; border-bottom-color: var(--warning);">
            <i class="fas fa-robot" style="color: var(--warning);"></i>
            <h3>الغياب التلقائي</h3>
        </div>
        <div class="auto-stats">
            <div class="auto-stat">
                <div class="auto-stat-number"><?php echo $auto_stats['total_auto']; ?></div>
                <div class="auto-stat-label">إجمالي الغياب التلقائي</div>
            </div>
            <div class="auto-stat">
                <div class="auto-stat-number" style="color: #dc3545;"><?php echo $auto_stats['teachers_auto']; ?></div>
                <div class="auto-stat-label">معلمين</div>
            </div>
            <div class="auto-stat">
                <div class="auto-stat-number" style="color: #dc3545;"><?php echo $auto_stats['students_auto']; ?></div>
                <div class="auto-stat-label">طلاب</div>
            </div>
            <div class="auto-stat">
                <div class="auto-stat-number"><?php echo safeHtml($auto_stats['last_auto_date'] ?? '-'); ?></div>
                <div class="auto-stat-label">آخر تاريخ</div>
            </div>
        </div>
        
        <?php if (!empty($recent_auto)): ?>
        <div class="auto-list">
            <strong><i class="fas fa-history"></i> آخر 5 تسجيلات:</strong>
            <?php foreach ($recent_auto as $auto): ?>
            <div class="auto-item">
                <span><?php echo safeHtml($auto['date']); ?></span>
                <span><?php echo $auto['person_type'] == 'teacher' ? '👨‍🏫' : '👨‍🎓'; ?> <?php echo safeHtml($auto['person_name']); ?></span>
                <span class="status-badge status-absent">غياب تلقائي</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- جدول سجلات الحضور -->
    <div class="records-card">
        <div class="section-title">
            <i class="fas fa-list-alt"></i>
            <h3>سجلات الحضور</h3>
            <span class="records-count"><?php echo $total_records; ?> سجل</span>
        </div>
        
        <?php if (empty($attendance_records)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>لا توجد سجلات حضور مطابقة للبحث</p>
                <?php if (!$is_friday && !$is_holiday): ?>
                <p style="margin-top: 10px;">يمكنك تسجيل حضور جديد من الأزرار أدناه</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>الحالة</th>
                            <th>الملاحظات</th>
                            <th>وقت التسجيل</th>
                        </thead>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr class="record-row">
                            <td class="record-type">
                                <?php if (($record['person_type'] ?? '') == 'teacher'): ?>
                                    <span class="type-icon">👨‍🏫</span> معلم
                                <?php else: ?>
                                    <span class="type-icon">👨‍🎓</span> طالب
                                <?php endif; ?>
                            </td>
                            <td class="record-name">
                                <strong><?php echo safeHtml($record['person_name']); ?></strong>
                                <?php if (!empty($record['person_phone'])): ?>
                                    <br><small class="record-phone">📞 <?php echo safeHtml($record['person_phone']); ?></small>
                                <?php endif; ?>
                                <?php if (($record['person_type'] ?? '') == 'student' && !empty($record['person_category'])): ?>
                                    <br><small class="record-category">📌 <?php 
                                        $cat_name = match($record['person_category']) {
                                            'boy' => 'أولاد',
                                            'girl' => 'بنات',
                                            'child' => 'أطفال',
                                            'woman' => 'نساء',
                                            default => $record['person_category']
                                        };
                                        echo safeHtml($cat_name); 
                                    ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="record-status">
                                <span class="status-badge status-<?php echo safeHtml($record['status']); ?>">
                                    <?php if (($record['status'] ?? '') == 'present'): ?>
                                        ✅ حاضر
                                    <?php elseif (($record['status'] ?? '') == 'absent'): ?>
                                        ❌ غائب
                                    <?php else: ?>
                                        ⏰ متأخر
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($record['auto_generated']) && $record['auto_generated'] == 1): ?>
                                    <span class="auto-badge">تلقائي</span>
                                <?php endif; ?>
                            </td>
                            <td class="record-notes"><?php echo safeHtml($record['notes'] ?? '-'); ?></td>
                            <td class="record-time"><?php echo date('H:i:s', strtotime($record['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- إحصائيات السجلات المعروضة -->
            <div class="records-summary">
                <div class="summary-item present">
                    <span class="status-badge status-present">✅ حاضر: <?php echo $total_present; ?></span>
                </div>
                <div class="summary-item absent">
                    <span class="status-badge status-absent">❌ غائب: <?php echo $total_absent; ?></span>
                </div>
                <div class="summary-item late">
                    <span class="status-badge status-late">⏰ متأخر: <?php echo $total_late; ?></span>
                </div>
                <div class="summary-item auto">
                    <span class="auto-badge">🤖 تلقائي: <?php echo $auto_generated_count; ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- أزرار الإجراءات -->
        <div class="action-buttons">
            <a href="attendance_teachers.php" class="btn btn-primary">
                <i class="fas fa-chalkboard-teacher"></i> تسجيل حضور المعلمين
            </a>
            <a href="attendance_students_teacher.php" class="btn btn-primary">
                <i class="fas fa-user-graduate"></i> تسجيل حضور الطلاب
            </a>
            <a href="cron_setup.php" class="btn btn-secondary">
                <i class="fas fa-cog"></i> إعدادات الغياب التلقائي
            </a>
        </div>
    </div>
</section>

<script>
function applyFilters() {
    const type = document.getElementById('typeFilter')?.value || 'all';
    const date = document.getElementById('dateFilter')?.value || '';
    const status = document.getElementById('statusFilter')?.value || 'all';
    const teacher = document.getElementById('teacherFilter')?.value || '0';
    
    let url = `?type=${type}&date=${date}&status=${status}`;
    if (teacher && teacher !== '0') {
        url += `&teacher_id=${teacher}`;
    }
    
    window.location.href = url;
}

function resetFilters() {
    window.location.href = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
}

console.log('✅ ملخص الحضور والغياب جاهز');
console.log('📅 التاريخ المحدد: <?php echo addslashes($selected_date); ?>');
console.log('👨‍🏫 المعلمين: <?php echo $teachers_present; ?>/<?php echo $teachers_can_attend; ?>');
console.log('👨‍🎓 الطلاب: <?php echo $students_present; ?>/<?php echo $students_can_attend; ?>');
</script>

<?php require_once 'includes/footer.php'; ?>