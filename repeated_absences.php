<?php
// ============================================
// ملف: repeated_absences.php
// الطلاب المنقطعين - حساب دقيق بناءً على الحصص التي مرت فعلياً
// آخر تحديث: 2026-04-13
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الطلاب المنقطعين - الغياب المتتالي';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$message = '';
$message_type = '';

// ============================================
// دالة جلب تفاصيل حلقة الطالب
// ============================================
function getStudentRingDetails($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT rsch.day_of_week, rsch.start_time, r.id as ring_id, r.name as ring_name
        FROM ring_students rs
        JOIN rings r ON rs.ring_id = r.id
        JOIN ring_schedules rsch ON r.id = rsch.ring_id
        WHERE rs.student_id = ?
        ORDER BY rsch.day_of_week
    ");
    $stmt->execute([$student_id]);
    $days = $stmt->fetchAll();
    
    $day_names = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    
    return [
        'has_ring' => !empty($days),
        'days' => $days,
        'day_numbers' => array_column($days, 'day_of_week'),
        'days_count' => count($days),
        'days_names' => array_map(function($d) use ($day_names) {
            return $day_names[$d['day_of_week'] - 1];
        }, $days),
        'sorted_days' => $days // مصفوفة الأيام مرتبة
    ];
}

// ============================================
// دالة حساب تفاصيل الغياب بدقة (تعتمد على الحصص التي مرت فقط)
// ============================================
function getStudentAbsenceDetailsAccurate($pdo, $student_id, $weeks_back = 1) {
    // جلب تفاصيل حلقة الطالب
    $ring_details = getStudentRingDetails($pdo, $student_id);
    
    if (!$ring_details['has_ring']) {
        return [
            'has_ring' => false,
            'consecutive_absences' => 0,
            'total_absences' => 0,
            'total_passed_sessions' => 0,
            'last_attendance_date' => null,
            'last_absence_date' => null,
            'attendance_rate' => 0,
            'ring_days_count' => 0,
            'ring_days_names' => [],
            'session_details' => []
        ];
    }
    
    $ring_day_numbers = $ring_details['day_numbers'];
    $ring_days_count = $ring_details['days_count'];
    $today = date('Y-m-d');
    $today_day = (int)date('w') + 1;
    
    // حساب تاريخ البداية (منذ X أسابيع)
    $start_date = date('Y-m-d', strtotime("-$weeks_back weeks"));
    
    // جلب سجلات الحضور للطالب
    $stmt = $pdo->prepare("
        SELECT date, status 
        FROM attendance 
        WHERE person_type = 'student' AND person_id = ? 
        AND date >= ?
        ORDER BY date ASC
    ");
    $stmt->execute([$student_id, $start_date]);
    $records = $stmt->fetchAll();
    
    // تحويل السجلات إلى مصفوفة للبحث السريع
    $attendance_by_date = [];
    foreach ($records as $record) {
        $attendance_by_date[$record['date']] = $record['status'];
    }
    
    // ============================================
    // حساب جميع الجلسات التي مرت (أيام الحلقة الفعلية)
    // ============================================
    $passed_sessions = []; // الجلسات التي مرت (تاريخها <= اليوم)
    $session_details = [];
    
    $current = new DateTime($start_date);
    $end = new DateTime($today);
    $end->modify('+1 day');
    
    while ($current < $end) {
        $date = $current->format('Y-m-d');
        $day_of_week = (int)$current->format('w') + 1;
        
        // هل هذا اليوم من أيام حلقة الطالب؟
        if (in_array($day_of_week, $ring_day_numbers)) {
            // التحقق من العطل الرسمية
            $holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
            $holiday_check->execute([$date, $date]);
            $is_holiday = $holiday_check->fetch();
            $is_friday = ($day_of_week == 6);
            
            // فقط الأيام التي ليست عطلة
            if (!$is_holiday && !$is_friday) {
                $passed_sessions[] = $date;
                $status = $attendance_by_date[$date] ?? 'absent';
                $session_details[$date] = [
                    'status' => $status,
                    'day_name' => getDayName($day_of_week),
                    'date' => $date
                ];
            }
        }
        $current->modify('+1 day');
    }
    
    // عدد الجلسات التي مرت فعلياً
    $total_passed_sessions = count($passed_sessions);
    
    // ============================================
    // حساب الغياب المتتالي من نهاية القائمة (أحدث الجلسات)
    // ============================================
    $consecutive_absences = 0;
    $last_absence_date = null;
    $total_absences = 0;
    $found_attendance = false;
    
    // نعكس الترتيب لنبدأ من الأحدث
    $passed_sessions_reversed = array_reverse($passed_sessions);
    
    foreach ($passed_sessions_reversed as $date) {
        $status = $attendance_by_date[$date] ?? 'absent';
        
        if ($status == 'present' || $status == 'late') {
            $found_attendance = true;
            break;
        } else {
            // غياب
            $total_absences++;
            if (!$found_attendance) {
                $consecutive_absences++;
                if (!$last_absence_date) {
                    $last_absence_date = $date;
                }
            }
        }
    }
    
    // ============================================
    // حساب نسبة الحضور
    // ============================================
    $attendance_rate = $total_passed_sessions > 0 
        ? round((($total_passed_sessions - $total_absences) / $total_passed_sessions) * 100) 
        : 0;
    
    // آخر حضور (في أي وقت)
    $last_attendance_stmt = $pdo->prepare("
        SELECT date FROM attendance 
        WHERE person_type = 'student' AND person_id = ? 
        AND (status = 'present' OR status = 'late')
        ORDER BY date DESC LIMIT 1
    ");
    $last_attendance_stmt->execute([$student_id]);
    $last_attendance_date = $last_attendance_stmt->fetchColumn();
    
    // التحقق من حضور اليوم
    $has_attendance_today = isset($attendance_by_date[$today]) && 
        ($attendance_by_date[$today] == 'present' || $attendance_by_date[$today] == 'late');
    
    return [
        'has_ring' => true,
        'ring_days_count' => $ring_days_count,
        'ring_days_names' => $ring_details['days_names'],
        'ring_sorted_days' => $ring_details['sorted_days'],
        'consecutive_absences' => $consecutive_absences,
        'total_absences' => $total_absences,
        'total_passed_sessions' => $total_passed_sessions,
        'last_attendance_date' => $last_attendance_date,
        'last_absence_date' => $last_absence_date,
        'has_attendance_today' => $has_attendance_today,
        'attendance_rate' => $attendance_rate,
        'period_start' => $start_date,
        'period_end' => $today,
        'passed_sessions' => $passed_sessions,
        'session_details' => $session_details
    ];
}

// دالة مساعدة لاسم اليوم
function getDayName($day_number) {
    $names = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    return $names[$day_number - 1];
}

// ============================================
// معالجة تسجيل حضور لطالب
// ============================================
if (isset($_GET['mark_present']) && isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
    $weeks_back = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 1;
    
    $can_mark = false;
    if (isAdmin()) {
        $can_mark = true;
    } elseif (isTeacher()) {
        $stmt = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $can_mark = ($stmt->fetchColumn() == $teacher_id);
    }
    
    if ($can_mark) {
        $today = date('Y-m-d');
        $day_of_week = (int)date('w') + 1;
        
        $ring_details = getStudentRingDetails($pdo, $student_id);
        $is_ring_day = in_array($day_of_week, $ring_details['day_numbers']);
        
        $holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
        $holiday_check->execute([$today, $today]);
        $is_holiday = $holiday_check->fetch();
        $is_friday = ($day_of_week == 6);
        
        if ($is_friday || $is_holiday || !$is_ring_day) {
            $message = "⚠️ لا يمكن تسجيل حضور اليوم (ليس يوم حلقة أو يوم عطلة)";
            $message_type = 'warning';
        } else {
            $check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
            $check->execute([$student_id, $today]);
            
            if (!$check->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (person_type, student_id, date, status, notes)
                    VALUES ('student', ?, ?, 'present', 'تسجيل حضور يدوي - إنهاء سلسلة الغياب')
                ");
                $stmt->execute([$student_id, $today]);
                $message = "✅ تم تسجيل حضور الطالب لهذا اليوم، وتم إنهاء سلسلة الغياب.";
                $message_type = 'success';
            } else {
                $message = "⚠️ الطالب مسجل حضوره اليوم بالفعل.";
                $message_type = 'warning';
            }
        }
    } else {
        $message = "❌ لا تملك صلاحية تعديل هذا الطالب.";
        $message_type = 'error';
    }
}

// ============================================
// جلب الطلاب وتحليل غيابهم
// ============================================
$weeks_back = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 1;
$weeks_back = max(1, min(4, $weeks_back)); // أسبوع إلى 4 أسابيع

$sql = "
    SELECT 
        s.id,
        s.name,
        s.parent_phone,
        s.level,
        s.category,
        t.name as teacher_name,
        t.id as teacher_id
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE 1=1
";

$params = [];

if ($teacher_id) {
    $sql .= " AND s.teacher_id = :teacher_id";
    $params[':teacher_id'] = $teacher_id;
}

$sql .= " ORDER BY s.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_students = $stmt->fetchAll();

// تحليل كل طالب
$disconnected_students = [];
foreach ($all_students as $student) {
    $absence_data = getStudentAbsenceDetailsAccurate($pdo, $student['id'], $weeks_back);
    
    // شرط الانقطاع: غياب متتالي حصتين أو أكثر خلال الفترة
    // ويجب أن يكون عدد الجلسات التي مرت على الأقل 2
    if ($absence_data['has_ring'] && 
        $absence_data['consecutive_absences'] >= 2 && 
        $absence_data['total_passed_sessions'] >= 2) {
        
        $student['consecutive_absences'] = $absence_data['consecutive_absences'];
        $student['total_absences'] = $absence_data['total_absences'];
        $student['total_passed_sessions'] = $absence_data['total_passed_sessions'];
        $student['last_attendance_date'] = $absence_data['last_attendance_date'];
        $student['last_absence_date'] = $absence_data['last_absence_date'];
        $student['has_attendance_today'] = $absence_data['has_attendance_today'];
        $student['attendance_rate'] = $absence_data['attendance_rate'];
        $student['ring_days_count'] = $absence_data['ring_days_count'];
        $student['ring_days_names'] = $absence_data['ring_days_names'];
        $student['period_start'] = $absence_data['period_start'];
        $student['period_end'] = $absence_data['period_end'];
        $student['session_details'] = $absence_data['session_details'];
        
        // تحديد مستوى الخطورة
        if ($student['consecutive_absences'] >= $student['ring_days_count'] * 2) {
            $student['status_class'] = 'critical';
            $student['status_text'] = '🔴 خطير جداً';
            $student['status_icon'] = 'fa-skull-crossbones';
        } elseif ($student['consecutive_absences'] >= $student['ring_days_count']) {
            $student['status_class'] = 'warning';
            $student['status_text'] = '🟠 تحذير';
            $student['status_icon'] = 'fa-exclamation-triangle';
        } else {
            $student['status_class'] = 'notice';
            $student['status_text'] = '🟡 تنبيه';
            $student['status_icon'] = 'fa-bell';
        }
        
        $disconnected_students[] = $student;
    }
}

// ترتيب حسب الغياب المتتالي تنازلياً
usort($disconnected_students, function($a, $b) {
    return $b['consecutive_absences'] - $a['consecutive_absences'];
});

// إحصائيات
$stats = [
    'total' => count($disconnected_students),
    'total_absences' => array_sum(array_column($disconnected_students, 'total_absences')),
    'total_passed' => array_sum(array_column($disconnected_students, 'total_passed_sessions')),
    'critical' => count(array_filter($disconnected_students, fn($s) => $s['status_class'] == 'critical')),
    'warning' => count(array_filter($disconnected_students, fn($s) => $s['status_class'] == 'warning'))
];

$total_students = count($all_students);
$percentage = $total_students > 0 ? round(($stats['total'] / $total_students) * 100) : 0;

$category_names = [
    'boy' => 'أولاد',
    'girl' => 'بنات',
    'child' => 'أطفال',
    'woman' => 'نساء'
];

// عرض رسالة النجاح/الخطأ
if ($message) {
    echo '<div class="alert alert-' . $message_type . '"><i class="fas ' . ($message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle') . '"></i> ' . $message . '</div>';
}
?>

<style>
/* ===== نفس الأنماط السابقة مع إضافات ===== */
.absences-page {
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.8rem;
}

.page-header h1 i {
    color: #c9a96b;
}

.stats-badge {
    background: rgba(255,255,255,0.15);
    padding: 10px 25px;
    border-radius: 50px;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.weeks-selector {
    display: flex;
    gap: 10px;
    align-items: center;
    background: white;
    padding: 10px 20px;
    border-radius: 30px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.weeks-selector label {
    font-weight: 600;
    color: #1e3c3f;
}

.weeks-selector select {
    padding: 8px 15px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.9rem;
    background: white;
}

.weeks-selector .btn-go {
    background: #1e3c3f;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    cursor: pointer;
}

.period-badge {
    background: #e7f3ff;
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 0.8rem;
    color: #0c5460;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.stats-grid {
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
    background: linear-gradient(90deg, #c9a96b, #1e3c3f);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
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

.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.filter-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 20px;
    border-radius: 30px;
    background: #f8f9fa;
    color: #666;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
}

.filter-btn.active {
    background: #1e3c3f;
    color: white;
    border-color: #c9a96b;
}

.filter-btn:hover:not(.active) {
    background: #e9ecef;
}

.search-box input {
    padding: 10px 20px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    width: 250px;
    font-size: 0.9rem;
}

.search-box input:focus {
    outline: none;
    border-color: #c9a96b;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 25px;
}

.student-card {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s;
    border: 1px solid rgba(0,0,0,0.05);
}

.student-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.card-status-bar {
    height: 8px;
}

.card-status-bar.critical { background: linear-gradient(90deg, #dc3545, #ff6b6b); }
.card-status-bar.warning { background: linear-gradient(90deg, #ffc107, #ffdb58); }
.card-status-bar.notice { background: linear-gradient(90deg, #17a2b8, #20c997); }

.card-content {
    padding: 20px;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.student-avatar {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: bold;
    color: white;
    flex-shrink: 0;
}

.student-title {
    flex: 1;
}

.student-name {
    font-size: 1.2rem;
    font-weight: 800;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.student-category {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
    margin-bottom: 5px;
}

.student-teacher {
    font-size: 0.8rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}

.student-teacher i {
    color: #c9a96b;
}

.ring-info {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 10px 15px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.ring-badge {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.absence-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.absence-stat {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 15px;
}

.absence-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
}

.absence-stat-label {
    font-size: 0.7rem;
    color: #666;
}

.dates-info {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 12px;
    margin-bottom: 15px;
}

.date-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px dashed #e9ecef;
}

.date-row:last-child {
    border-bottom: none;
}

.date-label {
    font-size: 0.8rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}

.date-label i {
    width: 20px;
    color: #c9a96b;
}

.date-value {
    font-weight: 600;
    color: #1e3c3f;
    font-size: 0.85rem;
}

.sessions-timeline {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 12px;
    margin-bottom: 15px;
}

.sessions-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e3c3f;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sessions-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.session-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.session-present {
    background: #d4edda;
    color: #155724;
}

.session-absent {
    background: #f8d7da;
    color: #721c24;
}

.session-late {
    background: #fff3cd;
    color: #856404;
}

.progress-section {
    margin: 15px 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 5px;
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s;
}

.progress-fill.critical { background: #dc3545; }
.progress-fill.warning { background: #ffc107; }
.progress-fill.notice { background: #17a2b8; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-critical { background: #f8d7da; color: #721c24; }
.status-warning { background: #fff3cd; color: #856404; }
.status-notice { background: #d1ecf1; color: #0c5460; }

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    padding: 10px 15px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.85rem;
}

.btn-primary {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-whatsapp {
    background: #25d366;
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
    background: white;
    border-radius: 25px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

.empty-state h3 {
    color: #1e3c3f;
    margin-bottom: 10px;
}

.alert {
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-right: 5px solid #ffc107;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.info-note {
    margin-top: 30px;
    background: #e7f3ff;
    border-radius: 20px;
    padding: 20px;
}

@media (max-width: 768px) {
    .absences-page { padding: 15px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .students-grid { grid-template-columns: 1fr; }
    .card-header { flex-direction: column; text-align: center; }
    .action-buttons { flex-direction: column; }
    .btn { width: 100%; }
    .absence-stats { grid-template-columns: 1fr; }
    .filter-bar { flex-direction: column; }
    .search-box input { width: 100%; }
    .page-header { flex-direction: column; text-align: center; }
    .weeks-selector { flex-direction: column; }
    .ring-info { flex-direction: column; text-align: center; }
    .sessions-list { justify-content: center; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .date-row { flex-direction: column; align-items: flex-start; gap: 5px; }
}
</style>

<section class="absences-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-exclamation-triangle"></i>
            الطلاب المنقطعين
        </h1>
        <div class="stats-badge">
            <i class="fas fa-calendar-alt"></i>
            آخر <?php echo $weeks_back; ?> أسبوع
        </div>
    </div>

    <!-- اختيار المدة الزمنية (بالأسابيع) -->
    <div class="weeks-selector">
        <label><i class="fas fa-clock"></i> عرض المنقطعين خلال:</label>
        <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($teacher_id): ?>
                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
            <?php endif; ?>
            <select name="weeks">
                <option value="1" <?php echo $weeks_back == 1 ? 'selected' : ''; ?>>الأسبوع الماضي (آخر 7 أيام)</option>
                <option value="2" <?php echo $weeks_back == 2 ? 'selected' : ''; ?>>آخر أسبوعين</option>
                <option value="3" <?php echo $weeks_back == 3 ? 'selected' : ''; ?>>آخر 3 أسابيع</option>
                <option value="4" <?php echo $weeks_back == 4 ? 'selected' : ''; ?>>آخر 4 أسابيع</option>
            </select>
            <button type="submit" class="btn-go">عرض</button>
        </form>
        <div class="period-badge">
            <i class="fas fa-chart-line"></i>
            يتم احتساب الغياب بناءً على الحصص التي مرت فعلياً خلال آخر <?php echo $weeks_back; ?> أسبوع
        </div>
    </div>

    <!-- إحصائيات -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">طلاب منقطعون</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_absences']; ?></div>
            <div class="stat-label">إجمالي حصص الغياب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_passed']; ?></div>
            <div class="stat-label">حصص مرت فعلياً</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ffc107;"><?php echo $percentage; ?>%</div>
            <div class="stat-label">نسبة المنقطعين</div>
        </div>
    </div>

    <!-- شريط الفلترة والبحث -->
    <div class="filter-bar">
        <div class="filter-tabs">
            <span class="filter-btn active" data-filter="all" onclick="filterStudents('all')">الكل</span>
            <span class="filter-btn" data-filter="critical" onclick="filterStudents('critical')">🔴 خطير</span>
            <span class="filter-btn" data-filter="warning" onclick="filterStudents('warning')">🟠 تحذير</span>
            <span class="filter-btn" data-filter="notice" onclick="filterStudents('notice')">🟡 تنبيه</span>
        </div>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 بحث عن طالب...">
        </div>
    </div>

    <?php if (empty($disconnected_students)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color: #28a745;"></i>
            <h3>لا توجد طلاب منقطعين</h3>
            <p>جميع الطلاب منتظمون في حضور حصص الحلقات خلال آخر <?php echo $weeks_back; ?> أسبوع</p>
        </div>
    <?php else: ?>
        <div class="students-grid" id="studentsGrid">
            <?php foreach ($disconnected_students as $student): 
                $avatar_color = match($student['category']) {
                    'boy' => '#3498db',
                    'girl' => '#9b59b6',
                    'child' => '#f39c12',
                    'woman' => '#e84342',
                    default => '#1e3c3f'
                };
                
                $attendance_percent = $student['attendance_rate'];
                $progress_class = $student['status_class'];
                $ring_days_text = implode(' - ', $student['ring_days_names']);
                
                // بناء شريط الجلسات التي مرت
                $sessions_html = '';
                $session_details = $student['session_details'];
                // ترتيب الجلسات من الأقدم إلى الأحدث
                $sorted_sessions = array_reverse($student['session_details']);
                foreach ($sorted_sessions as $date => $session) {
                    $status_class = $session['status'] == 'present' ? 'present' : ($session['status'] == 'late' ? 'late' : 'absent');
                    $status_text = $session['status'] == 'present' ? 'حاضر' : ($session['status'] == 'late' ? 'متأخر' : 'غائب');
                    $icon = $session['status'] == 'present' ? '✅' : ($session['status'] == 'late' ? '⏰' : '❌');
                    $sessions_html .= "<span class='session-badge session-$status_class'>$icon {$session['day_name']} ($date) - $status_text</span>";
                }
            ?>
                <div class="student-card" data-status="<?php echo $student['status_class']; ?>" data-name="<?php echo strtolower($student['name']); ?>">
                    <div class="card-status-bar <?php echo $student['status_class']; ?>"></div>
                    <div class="card-content">
                        <div class="card-header">
                            <div class="student-avatar" style="background: <?php echo $avatar_color; ?>;">
                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="student-title">
                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                <span class="student-category" style="background: <?php echo $avatar_color; ?>;">
                                    <?php echo $category_names[$student['category']]; ?>
                                </span>
                                <div class="student-teacher">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- معلومات أيام الحلقة -->
                        <div class="ring-info">
                            <div>
                                <i class="fas fa-ring"></i>
                                <strong>أيام الحلقة:</strong> <?php echo $ring_days_text; ?>
                            </div>
                            <div class="ring-badge">
                                <i class="fas fa-calendar-week"></i>
                                <?php echo $student['ring_days_count']; ?> حصة/أسبوع
                            </div>
                        </div>

                        <!-- شريط الجلسات التي مرت -->
                        <div class="sessions-timeline">
                            <div class="sessions-title">
                                <i class="fas fa-history"></i>
                                الجلسات التي مرت (<?php echo count($student['session_details']); ?> جلسة):
                            </div>
                            <div class="sessions-list">
                                <?php echo $sessions_html; ?>
                            </div>
                        </div>

                        <!-- إحصائيات الغياب -->
                        <div class="absence-stats">
                            <div class="absence-stat">
                                <div class="absence-stat-value" style="color: #dc3545;"><?php echo $student['consecutive_absences']; ?></div>
                                <div class="absence-stat-label">غياب متتالي</div>
                            </div>
                            <div class="absence-stat">
                                <div class="absence-stat-value" style="color: #ffc107;"><?php echo $student['total_absences']; ?>/<?php echo $student['total_passed_sessions']; ?></div>
                                <div class="absence-stat-label">غياب / حصص مرت</div>
                            </div>
                            <div class="absence-stat">
                                <div class="absence-stat-value" style="color: #17a2b8;"><?php echo $student['attendance_rate']; ?>%</div>
                                <div class="absence-stat-label">نسبة الحضور</div>
                            </div>
                        </div>

                        <!-- معلومات التواريخ -->
                        <div class="dates-info">
                            <div class="date-row">
                                <span class="date-label"><i class="fas fa-calendar-check"></i> آخر حضور:</span>
                                <span class="date-value"><?php echo $student['last_attendance_date'] ?? 'لم يحضر بعد'; ?></span>
                            </div>
                            <div class="date-row">
                                <span class="date-label"><i class="fas fa-calendar-times"></i> آخر غياب:</span>
                                <span class="date-value"><?php echo $student['last_absence_date'] ?? '-'; ?></span>
                            </div>
                        </div>

                        <!-- شريط التقدم -->
                        <div class="progress-section">
                            <div class="progress-label">
                                <span>نسبة الحضور</span>
                                <span><?php echo $attendance_percent; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $progress_class; ?>" style="width: <?php echo $attendance_percent; ?>%;"></div>
                            </div>
                        </div>

                        <!-- حالة الطالب -->
                        <div class="status-badge status-<?php echo $student['status_class']; ?>">
                            <i class="fas <?php echo $student['status_icon']; ?>"></i>
                            <?php echo $student['status_text']; ?> - غاب <?php echo $student['consecutive_absences']; ?> حصة متتالية
                        </div>

                        <!-- أزرار الإجراءات -->
                        <div class="action-buttons">
                            <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                            <a href="?mark_present=1&student_id=<?php echo $student['id']; ?><?php echo $teacher_id ? '&teacher_id='.$teacher_id : ''; ?>&weeks=<?php echo $weeks_back; ?>" class="btn btn-success" onclick="return confirm('✅ تسجيل حضور لهذا الطالب اليوم؟\n\nسيتم إنهاء سلسلة الغياب ولن يظهر في قائمة المنقطعين.')">
                                <i class="fas fa-check-circle"></i> تسجيل حضور
                            </a>
                            <?php 
                            $phone = formatWhatsAppNumber($student['parent_phone']);
                            if ($phone):
                                $msg = "السلام عليكم ورحمة الله وبركاته\n";
                                $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                                $msg .= "📋 *تنبيه هام: غياب متكرر*\n";
                                $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                                $msg .= "👤 *الطالب/ة:* {$student['name']}\n";
                                $msg .= "📊 *الغياب المتتالي:* {$student['consecutive_absences']} حصة\n";
                                $msg .= "📅 *آخر حضور:* " . ($student['last_attendance_date'] ?? 'لم يحضر') . "\n";
                                $msg .= "📖 *أيام الحلقة:* {$ring_days_text}\n";
                                $msg .= "📈 *نسبة الحضور:* {$student['attendance_rate']}%\n\n";
                                $msg .= "نأمل منكم متابعة حضور الطالب.\n\n";
                                $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                                $msg .= "دار التقوى لتحفيظ القرآن الكريم";
                                $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($msg);
                            ?>
                                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn btn-whatsapp">
                                    <i class="fab fa-whatsapp"></i> واتساب
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- معلومات عن طريقة الاحتساب -->
    <div class="info-note">
        <h4 style="color: #0c5460; display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <i class="fas fa-info-circle"></i> طريقة احتساب الغياب المتتالي (الدقيقة)
        </h4>
        <ul style="margin-right: 25px; color: #0c5460; line-height: 1.8;">
            <li><strong>📊 يتم احتساب الغياب فقط في أيام حلقة الطالب</strong> (وليست كل أيام الأسبوع)</li>
            <li><strong>🔄 يتم حساب الجلسات التي مرت فعلياً</strong> (وليست كل الأيام)</li>
            <li><strong>📅 يتم الحساب خلال آخر <?php echo $weeks_back; ?> أسبوع فقط</strong> (يمكنك تغيير المدة)</li>
            <li><strong>✅ عند تسجيل حضور الطالب</strong>، يتم إنهاء سلسلة الغياب ويختفي من القائمة فوراً</li>
            <li><strong>📈 نسبة الحضور تحسب = (عدد الحضور / عدد الجلسات التي مرت) × 100</strong></li>
            <li><strong>⚠️ الطالب يظهر في القائمة فقط إذا غاب حصتين متتاليتين أو أكثر</strong></li>
        </ul>
    </div>
</section>

<script>
function filterStudents(status) {
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.filter-btn[data-filter="${status}"]`).classList.add('active');
    
    const cards = document.querySelectorAll('.student-card');
    cards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const cards = document.querySelectorAll('.student-card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        if (name.includes(searchText) || searchText === '') {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.student-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>