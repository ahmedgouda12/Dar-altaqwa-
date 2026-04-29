<?php
// ============================================
// ملف: stats.php
// إحصائيات الحضور - مع تقارير شهرية للمعلمين والطلاب
// آخر تحديث: 2026-04-23
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إحصائيات الحضور';
require_once 'includes/header.php';

$today = date('Y-m-d');
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = substr($current_month, 0, 4);
$month = substr($current_month, 5, 2);
$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$month_name = [
    '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل',
    '05' => 'مايو', '06' => 'يونيو', '07' => 'يوليو', '08' => 'أغسطس',
    '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'
];

// أيام الأسبوع
$week_days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

// ============================================
// دالة جلب أيام عمل المعلم في الشهر
// ============================================
function getTeacherWorkDaysInMonth($pdo, $teacher_id, $year, $month) {
    $stmt = $pdo->prepare("SELECT work_days FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $work_days_str = $stmt->fetchColumn();
    $work_days = explode(',', $work_days_str ?: '1,2,3,4,5,6,7');
    $work_days = array_map('intval', $work_days);
    
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $work_days_list = [];
    
    // جلب العطل الرسمية في الشهر
    $holidays = $pdo->prepare("
        SELECT start_date, end_date FROM holidays 
        WHERE (YEAR(start_date) = ? AND MONTH(start_date) = ?)
           OR (YEAR(end_date) = ? AND MONTH(end_date) = ?)
           OR (start_date <= ? AND end_date >= ?)
    ");
    $month_start = "$year-$month-01";
    $month_end = "$year-$month-$days_in_month";
    $holidays->execute([$year, $month, $year, $month, $month_end, $month_start]);
    $holiday_ranges = $holidays->fetchAll();
    
    $holiday_dates = [];
    foreach ($holiday_ranges as $range) {
        $start = new DateTime($range['start_date']);
        $end = new DateTime($range['end_date']);
        $current = clone $start;
        while ($current <= $end) {
            if ($current->format('Y') == $year && $current->format('m') == $month) {
                $holiday_dates[] = $current->format('Y-m-d');
            }
            $current->modify('+1 day');
        }
    }
    $holiday_dates = array_unique($holiday_dates);
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $day_of_week = date('w', strtotime($date)) + 1;
        $is_friday = ($day_of_week == 6);
        
        if (in_array($day_of_week, $work_days) && !$is_friday && !in_array($date, $holiday_dates)) {
            $work_days_list[] = $date;
        }
    }
    
    return $work_days_list;
}

// ============================================
// دالة جلب أيام حلقة الطالب في الشهر
// ============================================
function getStudentRingDaysInMonth($pdo, $student_id, $year, $month) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT rsch.day_of_week 
        FROM ring_students rs
        JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
        WHERE rs.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $ring_days = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $ring_days = array_map('intval', $ring_days);
    
    if (empty($ring_days)) {
        return [];
    }
    
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $ring_days_list = [];
    
    // جلب العطل الرسمية
    $holidays = $pdo->prepare("
        SELECT start_date, end_date FROM holidays 
        WHERE (YEAR(start_date) = ? AND MONTH(start_date) = ?)
           OR (YEAR(end_date) = ? AND MONTH(end_date) = ?)
           OR (start_date <= ? AND end_date >= ?)
    ");
    $month_start = "$year-$month-01";
    $month_end = "$year-$month-$days_in_month";
    $holidays->execute([$year, $month, $year, $month, $month_end, $month_start]);
    $holiday_ranges = $holidays->fetchAll();
    
    $holiday_dates = [];
    foreach ($holiday_ranges as $range) {
        $start = new DateTime($range['start_date']);
        $end = new DateTime($range['end_date']);
        $current = clone $start;
        while ($current <= $end) {
            if ($current->format('Y') == $year && $current->format('m') == $month) {
                $holiday_dates[] = $current->format('Y-m-d');
            }
            $current->modify('+1 day');
        }
    }
    $holiday_dates = array_unique($holiday_dates);
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $day_of_week = date('w', strtotime($date)) + 1;
        $is_friday = ($day_of_week == 6);
        
        if (in_array($day_of_week, $ring_days) && !$is_friday && !in_array($date, $holiday_dates)) {
            $ring_days_list[] = $date;
        }
    }
    
    return $ring_days_list;
}

// ============================================
// جلب المعلمين والطلاب
// ============================================
$teachers = $pdo->query("
    SELECT id, name, gender, work_days 
    FROM teachers 
    WHERE can_login = 1 
    ORDER BY name
")->fetchAll();

$students = $pdo->query("
    SELECT id, name, category 
    FROM students 
    ORDER BY name
")->fetchAll();

// ============================================
// جلب بيانات حضور المعلم المحدد
// ============================================
$teacher_attendance = [];
$teacher_work_days = [];
$teacher_info = null;

if ($selected_teacher > 0) {
    $stmt = $pdo->prepare("SELECT name, work_days FROM teachers WHERE id = ?");
    $stmt->execute([$selected_teacher]);
    $teacher_info = $stmt->fetch();
    
    if ($teacher_info) {
        $teacher_work_days = getTeacherWorkDaysInMonth($pdo, $selected_teacher, $year, $month);
        
        if (!empty($teacher_work_days)) {
            $placeholders = implode(',', array_fill(0, count($teacher_work_days), '?'));
            $stmt = $pdo->prepare("
                SELECT date, status, is_excused, excuse_reason, notes
                FROM attendance 
                WHERE person_type = 'teacher' AND person_id = ? AND date IN ($placeholders)
            ");
            $params = array_merge([$selected_teacher], $teacher_work_days);
            $stmt->execute($params);
            $attendance_records = $stmt->fetchAll();
            
            foreach ($attendance_records as $record) {
                $teacher_attendance[$record['date']] = $record;
            }
        }
    }
}

// ============================================
// جلب بيانات حضور الطالب المحدد
// ============================================
$student_attendance = [];
$student_ring_days = [];
$student_info = null;

if ($selected_student > 0) {
    $stmt = $pdo->prepare("SELECT name, category FROM students WHERE id = ?");
    $stmt->execute([$selected_student]);
    $student_info = $stmt->fetch();
    
    if ($student_info) {
        $student_ring_days = getStudentRingDaysInMonth($pdo, $selected_student, $year, $month);
        
        if (!empty($student_ring_days)) {
            $placeholders = implode(',', array_fill(0, count($student_ring_days), '?'));
            $stmt = $pdo->prepare("
                SELECT date, status, is_excused, excuse_reason, notes
                FROM attendance 
                WHERE person_type = 'student' AND person_id = ? AND date IN ($placeholders)
            ");
            $params = array_merge([$selected_student], $student_ring_days);
            $stmt->execute($params);
            $attendance_records = $stmt->fetchAll();
            
            foreach ($attendance_records as $record) {
                $student_attendance[$record['date']] = $record;
            }
        }
    }
}

// إحصائيات سريعة للمعلم المختار
$teacher_stats = [
    'total_days' => count($teacher_work_days),
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0
];

foreach ($teacher_work_days as $date) {
    $att = $teacher_attendance[$date] ?? null;
    if ($att) {
        if ($att['is_excused'] == 1) {
            $teacher_stats['excused']++;
        } elseif ($att['status'] == 'present') {
            $teacher_stats['present']++;
        } elseif ($att['status'] == 'late') {
            $teacher_stats['late']++;
        } else {
            $teacher_stats['absent']++;
        }
    } else {
        $teacher_stats['absent']++;
    }
}

// إحصائيات سريعة للطالب المختار
$student_stats = [
    'total_days' => count($student_ring_days),
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0
];

foreach ($student_ring_days as $date) {
    $att = $student_attendance[$date] ?? null;
    if ($att) {
        if ($att['is_excused'] == 1) {
            $student_stats['excused']++;
        } elseif ($att['status'] == 'present') {
            $student_stats['present']++;
        } elseif ($att['status'] == 'late') {
            $student_stats['late']++;
        } else {
            $student_stats['absent']++;
        }
    } else {
        $student_stats['absent']++;
    }
}

$teacher_percentage = $teacher_stats['total_days'] > 0 ? round(($teacher_stats['present'] / $teacher_stats['total_days']) * 100) : 0;
$student_percentage = $student_stats['total_days'] > 0 ? round(($student_stats['present'] / $student_stats['total_days']) * 100) : 0;
?>

<style>
/* ===== تصميم صفحة الإحصائيات ===== */
.stats-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.page-header h1 {
    margin: 0;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.month-selector {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 15px;
}

.month-selector input {
    padding: 8px 20px;
    border-radius: 30px;
    border: none;
    font-size: 1rem;
}

.month-selector button {
    padding: 8px 25px;
    border-radius: 30px;
    border: none;
    background: #c9a96b;
    color: #1e3c3f;
    font-weight: bold;
    cursor: pointer;
}

/* ===== أقسام المعلمين والطلاب ===== */
.section-card {
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
    padding-bottom: 10px;
    border-bottom: 2px solid #c9a96b;
    color: #1e3c3f;
}

/* ===== قوائم الاختيار ===== */
.select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.select-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
    text-align: center;
}

.select-card:hover {
    background: #e9ecef;
    transform: translateY(-3px);
}

.select-card.active {
    border-color: #c9a96b;
    background: #fff8e7;
}

.select-name {
    font-weight: bold;
    color: #1e3c3f;
}

.select-stats {
    font-size: 0.7rem;
    color: #666;
    margin-top: 5px;
}

/* ===== بطاقات الإحصائيات ===== */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-box {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
}

.stat-number.present { color: #28a745; }
.stat-number.absent { color: #dc3545; }
.stat-number.late { color: #ffc107; }
.stat-number.excused { color: #17a2b8; }
.stat-number.total { color: #1e3c3f; }

.stat-label {
    font-size: 0.8rem;
    color: #666;
    margin-top: 5px;
}

/* ===== جدول الحضور الشهري ===== */
.calendar-table {
    overflow-x: auto;
    margin-top: 20px;
}

.attendance-calendar {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.attendance-calendar th {
    background: #1e3c3f;
    color: white;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
}

.attendance-calendar td {
    padding: 10px 5px;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
}

.attendance-calendar tr:hover {
    background: #f8f9fa;
}

.day-cell {
    font-weight: 600;
    color: #1e3c3f;
}

.status-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 80px;
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

.status-excused {
    background: #d1ecf1;
    color: #0c5460;
}

.status-not-recorded {
    background: #e9ecef;
    color: #6c757d;
}

.notes-tooltip {
    cursor: help;
    border-bottom: 1px dashed #999;
    margin-left: 5px;
}

.progress-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 10px;
    transition: width 0.5s;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .stats-page { padding: 15px; }
    .stats-summary { grid-template-columns: repeat(2, 1fr); }
    .select-grid { grid-template-columns: 1fr; }
    .attendance-calendar th, 
    .attendance-calendar td { font-size: 0.8rem; padding: 6px 3px; }
    .status-cell { min-width: 60px; font-size: 0.65rem; }
}
</style>

<section class="stats-page">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> إحصائيات الحضور</h1>
        <div class="month-selector">
            <form method="get" id="monthForm">
                <input type="month" name="month" value="<?php echo $current_month; ?>" onchange="this.form.submit()">
                <input type="hidden" name="teacher_id" value="<?php echo $selected_teacher; ?>">
                <input type="hidden" name="student_id" value="<?php echo $selected_student; ?>">
                <button type="submit"><i class="fas fa-calendar-alt"></i> عرض</button>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- قسم المعلمين -->
    <!-- ============================================ -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-chalkboard-teacher"></i>
            <h2>حضور المعلمين - <?php echo $month_name[$month] . ' ' . $year; ?></h2>
        </div>

        <!-- قائمة المعلمين -->
        <div class="select-grid">
            <div class="select-card <?php echo $selected_teacher == 0 ? 'active' : ''; ?>" 
                 onclick="window.location.href='?month=<?php echo $current_month; ?>&student_id=<?php echo $selected_student; ?>'">
                <div class="select-name">-- اختر معلماً --</div>
            </div>
            <?php foreach ($teachers as $teacher): ?>
                <div class="select-card <?php echo $selected_teacher == $teacher['id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='?month=<?php echo $current_month; ?>&teacher_id=<?php echo $teacher['id']; ?>&student_id=<?php echo $selected_student; ?>'">
                    <div class="select-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                    <div class="select-stats">
                        <i class="fas fa-calendar-week"></i> أيام العمل: 
                        <?php 
                        $work_days_count = count(getTeacherWorkDaysInMonth($pdo, $teacher['id'], $year, $month));
                        echo $work_days_count;
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_teacher > 0 && $teacher_info && !empty($teacher_work_days)): ?>
            <!-- إحصائيات المعلم -->
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="stat-number total"><?php echo $teacher_stats['total_days']; ?></div>
                    <div class="stat-label">أيام العمل</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number present"><?php echo $teacher_stats['present']; ?></div>
                    <div class="stat-label">✅ حاضر</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number late"><?php echo $teacher_stats['late']; ?></div>
                    <div class="stat-label">⏰ متأخر</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number excused"><?php echo $teacher_stats['excused']; ?></div>
                    <div class="stat-label">📋 معتذر</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number absent"><?php echo $teacher_stats['absent']; ?></div>
                    <div class="stat-label">❌ غائب</div>
                </div>
            </div>

            <!-- شريط التقدم -->
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $teacher_percentage; ?>%;"></div>
            </div>
            <div style="text-align: center; margin: 10px 0 20px;">
                نسبة الحضور: <strong><?php echo $teacher_percentage; ?>%</strong>
            </div>

            <!-- جدول الحضور الشهري -->
            <div class="calendar-table">
                <table class="attendance-calendar">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>اليوم</th>
                            <th>الحالة</th>
                            <th>ملاحظات</th>
                        </thead>
                    <tbody>
                        <?php 
                        $index = 1;
                        foreach ($teacher_work_days as $date):
                            $att = $teacher_attendance[$date] ?? null;
                            $day_name = $week_days[date('w', strtotime($date))];
                            
                            if ($att && $att['is_excused'] == 1) {
                                $status_class = 'excused';
                                $status_text = 'معتذر';
                                $status_icon = 'fa-calendar-times';
                            } elseif ($att && $att['status'] == 'present') {
                                $status_class = 'present';
                                $status_text = 'حاضر';
                                $status_icon = 'fa-check-circle';
                            } elseif ($att && $att['status'] == 'late') {
                                $status_class = 'late';
                                $status_text = 'متأخر';
                                $status_icon = 'fa-clock';
                            } elseif ($att && $att['status'] == 'absent') {
                                $status_class = 'absent';
                                $status_text = 'غائب';
                                $status_icon = 'fa-times-circle';
                            } else {
                                $status_class = 'not-recorded';
                                $status_text = 'لم يسجل';
                                $status_icon = 'fa-question-circle';
                            }
                        ?>
                            <tr>
                                <td><?php echo $index++; ?></td>
                                <td><?php echo $date; ?></td>
                                <td><?php echo $day_name; ?></td>
                                <td>
                                    <span class="status-cell status-<?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                    </span>
                                </span>
                                <td>
                                    <?php if ($att && !empty($att['notes'])): ?>
                                        <span class="notes-tooltip" title="<?php echo htmlspecialchars($att['notes']); ?>">
                                            <i class="fas fa-sticky-note"></i>
                                        </span>
                                    <?php elseif ($att && $att['excuse_reason']): ?>
                                        <span class="notes-tooltip" title="<?php echo htmlspecialchars($att['excuse_reason']); ?>">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($selected_teacher > 0 && $teacher_info && empty($teacher_work_days)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-alt"></i>
                <h3>لا توجد أيام عمل</h3>
                <p>هذا المعلم ليس لديه أيام عمل في هذا الشهر</p>
            </div>
        <?php elseif ($selected_teacher > 0 && !$teacher_info): ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>المعلم غير موجود</h3>
                <p>الرجاء اختيار معلم آخر</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- قسم الطلاب -->
    <!-- ============================================ -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-user-graduate"></i>
            <h2>حضور الطلاب - <?php echo $month_name[$month] . ' ' . $year; ?></h2>
        </div>

        <!-- قائمة الطلاب -->
        <div class="select-grid">
            <div class="select-card <?php echo $selected_student == 0 ? 'active' : ''; ?>" 
                 onclick="window.location.href='?month=<?php echo $current_month; ?>&teacher_id=<?php echo $selected_teacher; ?>'">
                <div class="select-name">-- اختر طالباً --</div>
            </div>
            <?php foreach ($students as $student): 
                $ring_days_count = count(getStudentRingDaysInMonth($pdo, $student['id'], $year, $month));
                $cat_name = match($student['category']) {
                    'boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء', default => ''
                };
            ?>
                <div class="select-card <?php echo $selected_student == $student['id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='?month=<?php echo $current_month; ?>&teacher_id=<?php echo $selected_teacher; ?>&student_id=<?php echo $student['id']; ?>'">
                    <div class="select-name"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="select-stats">
                        <i class="fas fa-tag"></i> <?php echo $cat_name; ?> | 
                        <i class="fas fa-calendar-week"></i> <?php echo $ring_days_count; ?> يوم حلقة
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_student > 0 && $student_info && !empty($student_ring_days)): ?>
            <!-- إحصائيات الطالب -->
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="stat-number total"><?php echo $student_stats['total_days']; ?></div>
                    <div class="stat-label">أيام الحلقة</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number present"><?php echo $student_stats['present']; ?></div>
                    <div class="stat-label">✅ حاضر</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number late"><?php echo $student_stats['late']; ?></div>
                    <div class="stat-label">⏰ متأخر</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number excused"><?php echo $student_stats['excused']; ?></div>
                    <div class="stat-label">📋 معتذر</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number absent"><?php echo $student_stats['absent']; ?></div>
                    <div class="stat-label">❌ غائب</div>
                </div>
            </div>

            <!-- شريط التقدم -->
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $student_percentage; ?>%;"></div>
            </div>
            <div style="text-align: center; margin: 10px 0 20px;">
                نسبة الحضور: <strong><?php echo $student_percentage; ?>%</strong>
            </div>

            <!-- جدول الحضور الشهري -->
            <div class="calendar-table">
                <table class="attendance-calendar">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>اليوم</th>
                            <th>الحالة</th>
                            <th>ملاحظات</th>
                        </thead>
                    <tbody>
                        <?php 
                        $index = 1;
                        foreach ($student_ring_days as $date):
                            $att = $student_attendance[$date] ?? null;
                            $day_name = $week_days[date('w', strtotime($date))];
                            
                            if ($att && $att['is_excused'] == 1) {
                                $status_class = 'excused';
                                $status_text = 'معتذر';
                                $status_icon = 'fa-calendar-times';
                            } elseif ($att && $att['status'] == 'present') {
                                $status_class = 'present';
                                $status_text = 'حاضر';
                                $status_icon = 'fa-check-circle';
                            } elseif ($att && $att['status'] == 'late') {
                                $status_class = 'late';
                                $status_text = 'متأخر';
                                $status_icon = 'fa-clock';
                            } elseif ($att && $att['status'] == 'absent') {
                                $status_class = 'absent';
                                $status_text = 'غائب';
                                $status_icon = 'fa-times-circle';
                            } else {
                                $status_class = 'not-recorded';
                                $status_text = 'لم يسجل';
                                $status_icon = 'fa-question-circle';
                            }
                        ?>
                            <tr>
                                <td><?php echo $index++; ?></td>
                                <td><?php echo $date; ?></td>
                                <td><?php echo $day_name; ?></td>
                                <td>
                                    <span class="status-cell status-<?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                    </span>
                                </span>
                                <td>
                                    <?php if ($att && !empty($att['notes'])): ?>
                                        <span class="notes-tooltip" title="<?php echo htmlspecialchars($att['notes']); ?>">
                                            <i class="fas fa-sticky-note"></i>
                                        </span>
                                    <?php elseif ($att && $att['excuse_reason']): ?>
                                        <span class="notes-tooltip" title="<?php echo htmlspecialchars($att['excuse_reason']); ?>">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($selected_student > 0 && $student_info && empty($student_ring_days)): ?>
            <div class="empty-state">
                <i class="fas fa-ring"></i>
                <h3>لا توجد أيام حلقة</h3>
                <p>هذا الطالب ليس لديه أيام حلقة في هذا الشهر</p>
            </div>
        <?php elseif ($selected_student > 0 && !$student_info): ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h3>الطالب غير موجود</h3>
                <p>الرجاء اختيار طالب آخر</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>