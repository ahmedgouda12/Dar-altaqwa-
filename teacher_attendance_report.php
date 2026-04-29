<?php
// ============================================
// ملف: teacher_attendance_report.php
// تقرير الحضور الشهري للطلاب - للمعلم
// آخر تحديث: 2026-04-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تقرير الحضور الشهري';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$year = substr($current_month, 0, 4);
$month = substr($current_month, 5, 2);

// أسماء الأشهر
$month_names = [
    '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل',
    '05' => 'مايو', '06' => 'يونيو', '07' => 'يوليو', '08' => 'أغسطس',
    '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'
];

// أيام الأسبوع
$week_days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

// جلب جميع طلاب المعلم
$students = $pdo->prepare("
    SELECT s.id, s.name, s.category, s.level,
           (SELECT COUNT(*) FROM attendance 
            WHERE person_type='student' AND person_id=s.id 
            AND YEAR(date)=? AND MONTH(date)=? AND status='present') as present_count,
           (SELECT COUNT(*) FROM attendance 
            WHERE person_type='student' AND person_id=s.id 
            AND YEAR(date)=? AND MONTH(date)=? AND status='absent' AND is_excused=0) as absent_count,
           (SELECT COUNT(*) FROM attendance 
            WHERE person_type='student' AND person_id=s.id 
            AND YEAR(date)=? AND MONTH(date)=? AND is_excused=1) as excused_count,
           (SELECT COUNT(*) FROM attendance 
            WHERE person_type='student' AND person_id=s.id 
            AND YEAR(date)=? AND MONTH(date)=? AND status='late') as late_count
    FROM students s
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY s.name
");
$students->execute([$year, $month, $year, $month, $year, $month, $year, $month, $teacher_id]);
$students = $students->fetchAll();

// تهيئة المتغيرات
$attendance_details = [];
$student_name = '';
$student_level = '';
$student_category = '';
$ring_days = []; // تهيئة كمصفوفة فارغة

if ($selected_student > 0) {
    // جلب معلومات الطالب
    $stmt = $pdo->prepare("SELECT name, level, category FROM students WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$selected_student, $teacher_id]);
    $student = $stmt->fetch();
    if ($student) {
        $student_name = $student['name'];
        $student_level = $student['level'];
        $student_category = $student['category'];
    }
    
    // جلب أيام حلقة الطالب
    $ring_days = getStudentRingDays($pdo, $selected_student);
    
    // التأكد من أن $ring_days هو مصفوفة
    if (!is_array($ring_days)) {
        $ring_days = [];
    }
    
    // جلب سجلات الحضور للشهر
    $stmt = $pdo->prepare("
        SELECT date, status, is_excused, notes
        FROM attendance
        WHERE person_type = 'student' 
        AND person_id = ?
        AND YEAR(date) = ? AND MONTH(date) = ?
        ORDER BY date ASC
    ");
    $stmt->execute([$selected_student, $year, $month]);
    $records = $stmt->fetchAll();
    
    // تحويل السجلات إلى مصفوفة للبحث السريع
    $attendance_by_date = [];
    foreach ($records as $record) {
        $attendance_by_date[$record['date']] = $record;
    }
    
    // حساب أيام الشهر
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    // بناء جدول الحضور
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $day_of_week = date('w', strtotime($date));
        $is_ring_day = in_array($day_of_week + 1, $ring_days);
        
        $attendance = isset($attendance_by_date[$date]) ? $attendance_by_date[$date] : null;
        
        $status = 'not_recorded';
        $status_text = 'لم يسجل';
        $status_class = 'not-recorded';
        $is_excused = false;
        
        if ($attendance) {
            if ($attendance['is_excused'] == 1) {
                $status = 'excused';
                $status_text = 'معتذر';
                $status_class = 'excused';
                $is_excused = true;
            } elseif ($attendance['status'] == 'present') {
                $status = 'present';
                $status_text = 'حاضر';
                $status_class = 'present';
            } elseif ($attendance['status'] == 'late') {
                $status = 'late';
                $status_text = 'متأخر';
                $status_class = 'late';
            } elseif ($attendance['status'] == 'absent') {
                $status = 'absent';
                $status_text = 'غائب';
                $status_class = 'absent';
            }
        } elseif (!$is_ring_day) {
            $status = 'no_ring';
            $status_text = 'لا يوجد حلقة';
            $status_class = 'no-ring';
        }
        
        $attendance_details[] = [
            'date' => $date,
            'day' => $day,
            'day_name' => $week_days[$day_of_week],
            'is_ring_day' => $is_ring_day,
            'status' => $status,
            'status_text' => $status_text,
            'status_class' => $status_class,
            'notes' => $attendance ? $attendance['notes'] : '',
            'is_excused' => $is_excused
        ];
    }
}

// إحصائيات سريعة للشهر
$total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$total_ring_days = 0;

// حساب عدد أيام الحلقات في الشهر (إذا كان هناك طالب محدد)
if ($selected_student > 0 && !empty($ring_days)) {
    for ($day = 1; $day <= $total_days; $day++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $day_of_week = date('w', strtotime($date));
        if (in_array($day_of_week + 1, $ring_days)) {
            $total_ring_days++;
        }
    }
}
?>

<style>
/* ===== تصميم تقرير الحضور الشهري ===== */
.attendance-report {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* رأس الصفحة */
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px 30px;
    border-radius: 25px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 1.6rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: #c9a96b;
}

.month-selector {
    display: flex;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    padding: 5px;
    border-radius: 50px;
}

.month-selector input {
    padding: 8px 20px;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    font-family: 'Cairo', sans-serif;
}

.month-selector button {
    padding: 8px 25px;
    border: none;
    border-radius: 50px;
    background: #c9a96b;
    color: #1e3c3f;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.month-selector button:hover {
    background: white;
    transform: translateY(-2px);
}

/* إحصائيات سريعة */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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

.stat-number.present { color: #28a745; }
.stat-number.absent { color: #dc3545; }
.stat-number.excused { color: #17a2b8; }

.stat-label {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
}

/* قائمة الطلاب */
.students-section {
    background: white;
    border-radius: 25px;
    padding: 20px;
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
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.student-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
    display: flex;
    align-items: center;
    gap: 15px;
}

.student-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    background: white;
}

.student-card.active {
    border-color: #c9a96b;
    background: linear-gradient(135deg, #fff8e7, #fff3d6);
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: bold;
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 700;
    color: #1e3c3f;
    margin-bottom: 3px;
}

.student-stats {
    font-size: 0.7rem;
    color: #666;
    display: flex;
    gap: 8px;
}

.stats-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.stats-badge.present { color: #28a745; }
.stats-badge.absent { color: #dc3545; }
.stats-badge.excused { color: #17a2b8; }

/* جدول الحضور الشهري */
.calendar-section {
    background: white;
    border-radius: 25px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.student-header-card {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 20px;
    border-radius: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.student-header-card h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-badge {
    background: rgba(255,255,255,0.15);
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.85rem;
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
    font-size: 0.85rem;
}

.attendance-calendar td {
    padding: 10px 5px;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.85rem;
}

.attendance-calendar tr:hover {
    background: #f8f9fa;
}

/* حالة الحضور */
.status-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 70px;
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

.status-no-ring {
    background: #f8f9fa;
    color: #adb5bd;
    font-style: italic;
}

/* أيام الجمعة */
.friday-cell {
    background: #f0f2f5;
    color: #999;
}

.ring-day-badge {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #c9a96b;
    margin-left: 5px;
}

.notes-tooltip {
    cursor: help;
    border-bottom: 1px dashed #999;
    margin-left: 5px;
}

/* أزرار */
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #6c757d;
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    transition: 0.3s;
}

.btn-back:hover {
    background: #5a6268;
    transform: translateX(-5px);
}

/* حالة عدم وجود بيانات */
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 25px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

/* تحسينات للهاتف */
@media (max-width: 768px) {
    .attendance-report { padding: 15px; }
    .stats-summary { grid-template-columns: repeat(2, 1fr); }
    .students-grid { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; text-align: center; }
    .month-selector { width: 100%; justify-content: center; }
    .student-header-card { flex-direction: column; text-align: center; }
    .status-cell { min-width: auto; padding: 4px 8px; font-size: 0.7rem; }
}

@media (max-width: 480px) {
    .stats-summary { grid-template-columns: 1fr; }
}

/* تحسينات للطباعة */
@media print {
    .no-print, .month-selector, .students-section, .btn-back, .page-header .month-selector {
        display: none !important;
    }
    
    .attendance-report { padding: 0; }
    .calendar-section { box-shadow: none; padding: 0; }
    .student-header-card { background: #1e3c3f; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .attendance-calendar th { background: #1e3c3f; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .status-present { background: #d4edda; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .status-absent { background: #f8d7da; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<section class="attendance-report">
    <div class="page-header">
        <h1>
            <i class="fas fa-calendar-alt"></i>
            تقرير الحضور الشهري
        </h1>
        <div class="month-selector no-print">
            <form method="get">
                <input type="month" name="month" value="<?php echo $current_month; ?>" required>
                <?php if ($selected_student): ?>
                    <input type="hidden" name="student_id" value="<?php echo $selected_student; ?>">
                <?php endif; ?>
                <button type="submit"><i class="fas fa-search"></i> عرض</button>
            </form>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>لا يوجد طلاب</h3>
            <p>لم تقم بإضافة أي طلاب بعد</p>
            <a href="add_student.php" class="btn-back" style="background: #1e3c3f;">إضافة طالب</a>
        </div>
    <?php else: ?>
        
        <!-- إحصائيات سريعة -->
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($students); ?></div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>
            <div class="stat-card">
                <div class="stat-number present"><?php echo array_sum(array_column($students, 'present_count')); ?></div>
                <div class="stat-label">✅ إجمالي حضور</div>
            </div>
            <div class="stat-card">
                <div class="stat-number absent"><?php echo array_sum(array_column($students, 'absent_count')); ?></div>
                <div class="stat-label">❌ إجمالي غياب</div>
            </div>
            <div class="stat-card">
                <div class="stat-number excused"><?php echo array_sum(array_column($students, 'excused_count')); ?></div>
                <div class="stat-label">⏰ إجمالي اعتذار</div>
            </div>
        </div>

        <!-- قائمة الطلاب -->
        <div class="students-section no-print">
            <div class="section-title">
                <i class="fas fa-users"></i>
                <h3>طلابي</h3>
            </div>
            <div class="students-grid">
                <?php foreach ($students as $student): 
                    $total = $student['present_count'] + $student['absent_count'] + $student['excused_count'];
                    $percentage = $total > 0 ? round(($student['present_count'] / $total) * 100) : 0;
                    $avatar_letter = mb_substr($student['name'], 0, 1, 'UTF-8');
                ?>
                    <div class="student-card <?php echo $selected_student == $student['id'] ? 'active' : ''; ?>" 
                         onclick="window.location.href='?month=<?php echo $current_month; ?>&student_id=<?php echo $student['id']; ?>'">
                        <div class="student-avatar"><?php echo $avatar_letter; ?></div>
                        <div class="student-info">
                            <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="student-stats">
                                <span class="stats-badge present"><i class="fas fa-check-circle"></i> <?php echo $student['present_count']; ?></span>
                                <span class="stats-badge absent"><i class="fas fa-times-circle"></i> <?php echo $student['absent_count']; ?></span>
                                <span class="stats-badge excused"><i class="fas fa-calendar-times"></i> <?php echo $student['excused_count']; ?></span>
                                <span class="stats-badge"><i class="fas fa-chart-line"></i> <?php echo $percentage; ?>%</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- جدول الحضور التفصيلي -->
        <?php if ($selected_student > 0 && !empty($attendance_details)): ?>
            <div class="calendar-section">
                <div class="student-header-card">
                    <h2>
                        <i class="fas fa-user-graduate"></i>
                        <?php echo htmlspecialchars($student_name); ?>
                    </h2>
                    <div>
                        <span class="student-badge"><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student_level ?: 'مبتدئ'); ?></span>
                        <span class="student-badge"><i class="fas fa-tag"></i> 
                            <?php 
                            $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
                            echo $cat_names[$student_category] ?? $student_category;
                            ?>
                        </span>
                        <span class="student-badge"><i class="fas fa-calendar"></i> <?php echo $month_names[$month] . ' ' . $year; ?></span>
                    </div>
                </div>

                <div style="overflow-x: auto;">
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
                            $present_count = 0;
                            $absent_count = 0;
                            $excused_count = 0;
                            $late_count = 0;
                            $no_ring_count = 0;
                            
                            foreach ($attendance_details as $detail):
                                $is_friday = ($detail['day_name'] == 'الجمعة');
                                
                                if ($detail['status'] == 'present') $present_count++;
                                elseif ($detail['status'] == 'absent') $absent_count++;
                                elseif ($detail['status'] == 'excused') $excused_count++;
                                elseif ($detail['status'] == 'late') $late_count++;
                                elseif ($detail['status'] == 'no_ring') $no_ring_count++;
                            ?>
                                <tr class="<?php echo $is_friday ? 'friday-cell' : ''; ?>">
                                    <td><?php echo $detail['day']; ?></td>
                                    <td><?php echo $detail['date']; ?></td>
                                    <td>
                                        <?php echo $detail['day_name']; ?>
                                        <?php if ($detail['is_ring_day'] && !$is_friday): ?>
                                            <span class="ring-day-badge" title="يوم حلقة"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-cell status-<?php echo $detail['status_class']; ?>">
                                            <?php if ($detail['status'] == 'present'): ?>
                                                <i class="fas fa-check-circle"></i> <?php echo $detail['status_text']; ?>
                                            <?php elseif ($detail['status'] == 'absent'): ?>
                                                <i class="fas fa-times-circle"></i> <?php echo $detail['status_text']; ?>
                                            <?php elseif ($detail['status'] == 'late'): ?>
                                                <i class="fas fa-clock"></i> <?php echo $detail['status_text']; ?>
                                            <?php elseif ($detail['status'] == 'excused'): ?>
                                                <i class="fas fa-calendar-times"></i> <?php echo $detail['status_text']; ?>
                                            <?php elseif ($detail['status'] == 'no_ring'): ?>
                                                <i class="fas fa-ring"></i> <?php echo $detail['status_text']; ?>
                                            <?php else: ?>
                                                <i class="fas fa-question-circle"></i> <?php echo $detail['status_text']; ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($detail['notes'])): ?>
                                            <span class="notes-tooltip" title="<?php echo htmlspecialchars($detail['notes']); ?>">
                                                <i class="fas fa-sticky-note"></i>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ملخص الشهر -->
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div><i class="fas fa-check-circle" style="color: #28a745;"></i> <strong>حاضر:</strong> <?php echo $present_count; ?></div>
                    <div><i class="fas fa-clock" style="color: #ffc107;"></i> <strong>متأخر:</strong> <?php echo $late_count; ?></div>
                    <div><i class="fas fa-times-circle" style="color: #dc3545;"></i> <strong>غائب:</strong> <?php echo $absent_count; ?></div>
                    <div><i class="fas fa-calendar-times" style="color: #17a2b8;"></i> <strong>معتذر:</strong> <?php echo $excused_count; ?></div>
                    <div><i class="fas fa-ring" style="color: #6c757d;"></i> <strong>لا يوجد حلقة:</strong> <?php echo $no_ring_count; ?></div>
                    <div><i class="fas fa-percent" style="color: #1e3c3f;"></i> <strong>نسبة الحضور:</strong> 
                        <?php 
                        $total_eligible = $present_count + $absent_count + $late_count + $excused_count;
                        $attendance_percent = $total_eligible > 0 ? round(($present_count / $total_eligible) * 100) : 0;
                        echo $attendance_percent . '%';
                        ?>
                    </div>
                </div>
            </div>
            
            <a href="?month=<?php echo $current_month; ?>" class="btn-back no-print">
                <i class="fas fa-arrow-right"></i> العودة لقائمة الطلاب
            </a>
            
        <?php elseif ($selected_student > 0): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-alt"></i>
                <h3>لا توجد بيانات حضور</h3>
                <p>لا توجد سجلات حضور لهذا الطالب في الشهر المحدد</p>
                <a href="?month=<?php echo $current_month; ?>" class="btn-back" style="background: #1e3c3f;">العودة للقائمة</a>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>