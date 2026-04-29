<?php
// ============================================
// ملف: attendance_students_teacher.php
// تسجيل حضور الطلاب - مع إمكانية اختيار التاريخ
// آخر تحديث: 2026-04-23
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'تسجيل حضور الطلاب';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];

// ============================================
// اختيار التاريخ (جديد)
// ============================================
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$today = $selected_date; // استخدام التاريخ المختار بدلاً من اليوم الحالي
$today_day = date('w', strtotime($today)) + 1;

$message = '';
$message_type = '';

// جلب طلاب المعلم
$students = $pdo->prepare("
    SELECT s.id, s.name, s.category, s.parent_phone, s.on_leave,
           (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_count
    FROM students s 
    WHERE s.teacher_id = ?
    ORDER BY s.category, s.name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// جلب أيام حلقات الطلاب
$ring_days_data = [];
foreach ($students as $student) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT rsch.day_of_week
        FROM ring_students rs
        JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
        WHERE rs.student_id = ?
        ORDER BY rsch.day_of_week
    ");
    $stmt->execute([$student['id']]);
    $days = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $days_text = getStudentRingDaysText($pdo, $student['id']);
    
    $ring_days_data[$student['id']] = [
        'has_ring' => !empty($days),
        'days' => $days,
        'days_text' => $days_text
    ];
}

// جلب جميع تسجيلات الحضور للتاريخ المحدد
$all_attendance = [];
if (!empty($students)) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT person_id, date, status, notes, is_excused FROM attendance WHERE person_type = 'student' AND date = ? AND person_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$selected_date], $ids);
    $stmt->execute($params);
    $all_attendance = $stmt->fetchAll();
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : [];
    $attendance_date = $_POST['attendance_date'] ?? $selected_date;
    $attendance_notes = trim($_POST['attendance_notes'] ?? '');
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($students as $student) {
        $student_id = $student['id'];
        
        // التحقق من أن الطالب يمكنه الحضور في هذا اليوم
        $can_attend_today = canAttend($student_id, $attendance_date, $ring_days_data);
        
        // إذا كان لا يمكنه الحضور، نتخطى
        if (!$can_attend_today) {
            continue;
        }
        
        $status = isset($attendance_data[$student_id]['status']) ? $attendance_data[$student_id]['status'] : '';
        $notes = isset($attendance_data[$student_id]['notes']) ? trim($attendance_data[$student_id]['notes']) : '';
        
        if (empty($status)) continue;
        
        // التحقق من أن الطالب ليس على إجازة
        if ($student['on_leave'] == 1 && $status != 'excused') {
            continue;
        }
        
        $check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
        $check->execute([$student_id, $attendance_date]);
        
        if ($status == 'present') {
            // حاضر
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE attendance SET status='present', notes=?, is_excused=0 WHERE person_type='student' AND person_id=? AND date=?");
                $stmt->execute([$notes, $student_id, $attendance_date]);
                $updated++;
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (person_type, person_id, date, status, notes, is_excused) VALUES ('student', ?, ?, 'present', ?, 0)");
                $stmt->execute([$student_id, $attendance_date, $notes]);
                $inserted++;
            }
        } elseif ($status == 'excused') {
            // معتذر
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE attendance SET status='absent', notes=?, is_excused=1 WHERE person_type='student' AND person_id=? AND date=?");
                $stmt->execute([$notes, $student_id, $attendance_date]);
                $updated++;
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (person_type, person_id, date, status, notes, is_excused) VALUES ('student', ?, ?, 'absent', ?, 1)");
                $stmt->execute([$student_id, $attendance_date, $notes]);
                $inserted++;
            }
        }
    }
    
    if ($inserted > 0 || $updated > 0) {
        $message = "✅ تم تسجيل حضور $inserted طالب جديد، وتحديث $updated طالب";
        $message_type = 'success';
    } else {
        $message = "⚠️ لم يتم تسجيل أي تغيير";
        $message_type = 'warning';
    }
    
    // إعادة تحميل البيانات
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT person_id, date, status, notes, is_excused FROM attendance WHERE person_type = 'student' AND date = ? AND person_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$attendance_date], $ids);
    $stmt->execute($params);
    $all_attendance = $stmt->fetchAll();
}

// دالة مساعدة للتحقق من إمكانية الحضور
function canAttend($student_id, $date, $ring_days_data) {
    $day_num = date('w', strtotime($date)) + 1;
    $student_days = isset($ring_days_data[$student_id]['days']) ? $ring_days_data[$student_id]['days'] : [];
    return in_array($day_num, $student_days);
}

// تمرير البيانات إلى JavaScript
$students_json = !empty($students) ? json_encode($students) : '[]';
$ring_days_json = !empty($ring_days_data) ? json_encode($ring_days_data) : '{}';
$attendance_json = !empty($all_attendance) ? json_encode($all_attendance) : '[]';
?>

<style>
/* ===== تصميم عصري لصفحة حضور الطلاب ===== */
.attendance-page {
    max-width: 1200px;
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
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

/* ===== محدد التاريخ (جديد) ===== */
.date-selector {
    background: white;
    border-radius: 20px;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.date-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8f9fa;
    padding: 5px 15px;
    border-radius: 50px;
}

.date-input-group label {
    font-weight: 600;
    color: #1e3c3f;
}

.date-input-group input {
    padding: 8px 12px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.9rem;
    background: white;
}

.date-nav {
    display: flex;
    gap: 10px;
}

.date-nav-btn {
    background: #f8f9fa;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    cursor: pointer;
    transition: 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
    color: #1e3c3f;
}

.date-nav-btn:hover {
    background: #c9a96b;
    color: white;
    transform: translateY(-2px);
}

.date-nav-btn.today {
    background: #28a745;
    color: white;
}

.date-nav-btn.today:hover {
    background: #218838;
}

/* ===== إحصائيات ===== */
.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e3c3f;
}

.stat-label {
    color: #666;
    font-size: 0.8rem;
}

/* ===== أزرار الإجراءات ===== */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 10px 20px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    background: #f8f9fa;
    color: #1e3c3f;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.action-btn:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

/* ===== شبكة الطلاب ===== */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.student-card {
    background: white;
    border-radius: 20px;
    padding: 0;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    border: 1px solid #eee;
    position: relative;
    overflow: hidden;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.student-card.present {
    background: #e8f5e9;
    border-right: 5px solid #28a745;
}

.student-card.excused {
    background: #d1ecf1;
    border-right: 5px solid #17a2b8;
}

.student-card.on-leave {
    background: #fff3cd;
    border-right: 5px solid #ffc107;
}

.student-card.cannot-attend {
    background: #f8f9fa;
    border-right: 5px solid #6c757d;
    opacity: 0.8;
}

.ring-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.ring-badge.can-attend {
    background: #28a745;
    color: white;
}

.ring-badge.cannot-attend {
    background: #6c757d;
    color: white;
}

.card-content {
    padding: 20px;
}

.student-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    margin-top: 10px;
}

.student-avatar {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e3c3f;
}

.student-days {
    font-size: 0.75rem;
    color: #666;
    margin-top: 3px;
}

/* ===== خيارات الحضور ===== */
.attendance-options {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.attendance-option {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border-radius: 30px;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
    font-weight: 600;
    font-size: 0.9rem;
}

.attendance-option.present {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

.attendance-option.present.selected {
    background: #28a745;
    color: white;
}

.attendance-option.excused {
    background: #d1ecf1;
    color: #0c5460;
    border-color: #17a2b8;
}

.attendance-option.excused.selected {
    background: #17a2b8;
    color: white;
}

.cannot-attend-message {
    background: #e9ecef;
    border-radius: 30px;
    padding: 12px;
    text-align: center;
    margin: 15px 0;
    color: #6c757d;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.notes-input {
    width: 100%;
    padding: 8px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 0.8rem;
    margin-top: 10px;
    display: none;
}

.notes-input.show {
    display: block;
}

.present-badge, .excused-badge, .leave-badge {
    background: #28a745;
    color: white;
    padding: 10px;
    border-radius: 12px;
    text-align: center;
    margin-top: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.excused-badge {
    background: #17a2b8;
}

.leave-badge {
    background: #ffc107;
    color: #212529;
}

.save-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    margin-top: 25px;
    transition: 0.3s;
}

.save-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(40,167,69,0.3);
}

.alert {
    padding: 15px;
    border-radius: 12px;
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

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

@media (max-width: 768px) {
    .attendance-page { padding: 15px; }
    .stats-container { grid-template-columns: repeat(2, 1fr); }
    .students-grid { grid-template-columns: 1fr; }
    .attendance-options { flex-direction: column; }
    .attendance-option { width: 100%; }
    .action-buttons { flex-direction: column; }
    .action-btn { width: 100%; justify-content: center; }
    .date-selector { flex-direction: column; }
    .date-input-group { width: 100%; justify-content: center; }
    .date-nav { width: 100%; justify-content: center; }
}
</style>

<section class="attendance-page">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> تسجيل حضور الطلاب</h1>
        <div class="date-display" id="dateDisplay"></div>
    </div>

    <!-- ============================================ -->
    <!-- محدد التاريخ (جديد) -->
    <!-- ============================================ -->
    <div class="date-selector">
        <div class="date-input-group">
            <label><i class="fas fa-calendar-alt"></i> التاريخ:</label>
            <input type="date" id="datePicker" value="<?php echo $selected_date; ?>">
        </div>
        <div class="date-nav">
            <button class="date-nav-btn" onclick="changeDate(-1)">
                <i class="fas fa-chevron-right"></i> اليوم السابق
            </button>
            <button class="date-nav-btn" onclick="changeDate(1)">
                اليوم التالي <i class="fas fa-chevron-left"></i>
            </button>
            <button class="date-nav-btn today" onclick="goToToday()">
                <i class="fas fa-calendar-day"></i> اليوم
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3>لا يوجد طلاب تابعون لك</h3>
            <p>لم تقم بإضافة أي طلاب إلى قائمتك بعد.</p>
            <a href="add_student.php" class="action-btn" style="background: #1e3c3f; color: white; display: inline-block; margin-top: 15px;">
                <i class="fas fa-user-plus"></i> إضافة طالب جديد
            </a>
        </div>
    <?php else: ?>
        
        <div class="stats-container">
            <div class="stat-card"><div class="stat-number" id="totalCount"><?php echo count($students); ?></div><div class="stat-label">إجمالي الطلاب</div></div>
            <div class="stat-card"><div class="stat-number" id="presentCount">0</div><div class="stat-label">✅ حاضر</div></div>
            <div class="stat-card"><div class="stat-number" id="excusedCount">0</div><div class="stat-label">⏰ معتذر (لا يحسب غياب)</div></div>
        </div>

        <div class="action-buttons" id="actionButtons">
            <button class="action-btn" onclick="selectAll('present')"><i class="fas fa-check-double"></i> تحديد الكل (حاضر)</button>
            <button class="action-btn" onclick="selectAll('excused')"><i class="fas fa-calendar-times"></i> تحديد الكل (معتذر)</button>
            <button class="action-btn" onclick="deselectAll()"><i class="fas fa-undo"></i> إلغاء التحديد</button>
        </div>

        <form method="post" id="attendanceForm">
            <input type="hidden" name="attendance_date" id="attendanceDate" value="<?php echo $selected_date; ?>">
            <div class="students-grid" id="studentsGrid"></div>
            <button type="submit" name="save_attendance" class="save-btn">
                <i class="fas fa-save"></i> حفظ التغييرات
            </button>
        </form>

    <?php endif; ?>
</section>

<script>
const studentsData = <?php echo $students_json; ?>;
const ringDaysData = <?php echo $ring_days_json; ?>;
const attendanceData = <?php echo $attendance_json; ?>;
const currentSelectedDate = '<?php echo $selected_date; ?>';

function getLocalDate() {
    return currentSelectedDate;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
}

function getDayNumber(dateStr) {
    return new Date(dateStr).getDay() + 1;
}

function canAttend(studentId, date) {
    const dayNum = getDayNumber(date);
    const studentDays = ringDaysData[studentId]?.days || [];
    return studentDays.includes(dayNum);
}

// تغيير التاريخ
function changeDate(delta) {
    const currentDate = new Date(currentSelectedDate);
    currentDate.setDate(currentDate.getDate() + delta);
    const newDate = currentDate.toISOString().split('T')[0];
    window.location.href = '?date=' + newDate;
}

function goToToday() {
    const today = new Date().toISOString().split('T')[0];
    window.location.href = '?date=' + today;
}

// تخزين حالة كل طالب
let studentStatus = {};
let studentNotes = {};

function renderPage() {
    const currentDate = getLocalDate();
    const formattedDate = formatDate(currentDate);
    
    document.getElementById('dateDisplay').innerHTML = `<i class="fas fa-calendar-alt"></i> ${formattedDate}`;
    document.getElementById('attendanceDate').value = currentDate;
    
    // تجهيز بيانات الحضور لهذا اليوم
    const todayAttendance = attendanceData.filter(a => a.date === currentDate);
    const presentIds = todayAttendance.filter(a => a.status === 'present' && a.is_excused != 1).map(a => a.person_id);
    const excusedIds = todayAttendance.filter(a => a.is_excused == 1).map(a => a.person_id);
    
    document.getElementById('presentCount').textContent = presentIds.length;
    document.getElementById('excusedCount').textContent = excusedIds.length;
    
    let html = '';
    let hasAnyCanAttend = false;
    
    studentsData.forEach(student => {
        const existingRecord = todayAttendance.find(a => a.person_id == student.id);
        const isPresent = existingRecord?.status === 'present' && existingRecord?.is_excused != 1;
        const isExcused = existingRecord?.is_excused == 1;
        const currentNotes = existingRecord?.notes || '';
        const canAttendToday = canAttend(student.id, currentDate);
        const daysText = ringDaysData[student.id]?.days_text || 'لا يوجد حلقات';
        const isOnLeave = student.on_leave == 1;
        
// حفظ الحالة الافتراضية
        if (!studentStatus[student.id]) {
            if (isPresent) studentStatus[student.id] = 'present';
            else if (isExcused) studentStatus[student.id] = 'excused';
            else studentStatus[student.id] = '';
            studentNotes[student.id] = currentNotes;
        }
        
        let avatarColor = '#1e3c3f';
        switch(student.category) {
            case 'boy': avatarColor = '#3498db'; break;
            case 'girl': avatarColor = '#9b59b6'; break;
            case 'child': avatarColor = '#f39c12'; break;
            case 'woman': avatarColor = '#e84342'; break;
        }
        
        const badgeClass = canAttendToday ? 'can-attend' : 'cannot-attend';
        const badgeText = canAttendToday ? '✅ يمكنه الحضور' : '❌ لا يمكنه الحضور اليوم';
        
        let cardClass = '';
        if (isOnLeave) cardClass = 'on-leave';
        else if (!canAttendToday) cardClass = 'cannot-attend';
        else if (studentStatus[student.id] === 'present') cardClass = 'present';
        else if (studentStatus[student.id] === 'excused') cardClass = 'excused';
        
        if (canAttendToday) hasAnyCanAttend = true;
        
        html += `
            <div class="student-card ${cardClass}" data-student-id="${student.id}">
                <div class="ring-badge ${badgeClass}">${badgeText}</div>
                <div class="card-content">
                    <div class="student-header">
                        <div class="student-avatar" style="background: ${avatarColor};">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="student-info">
                            <div class="student-name">${escapeHtml(student.name)}</div>
                            <div class="student-days">📅 أيام الحلقات: ${daysText}</div>
                        </div>
                    </div>
        `;
        
        if (canAttendToday) {
            // يمكنه الحضور - يظهر الخيارات
            html += `
                    <div class="attendance-options">
                        <div class="attendance-option present ${studentStatus[student.id] === 'present' ? 'selected' : ''}" 
                             onclick="setStatus(${student.id}, 'present')">
                            <i class="fas fa-check-circle"></i> ✅ حاضر
                        </div>
                        <div class="attendance-option excused ${studentStatus[student.id] === 'excused' ? 'selected' : ''}" 
                             onclick="setStatus(${student.id}, 'excused')">
                            <i class="fas fa-calendar-times"></i> ⏰ معتذر (لا يحسب غياب)
                        </div>
                    </div>
                    
                    <input type="text" class="notes-input" id="notes_${student.id}" 
                           placeholder="📝 ملاحظات (اختياري)" value="${escapeHtml(studentNotes[student.id])}"
                           onchange="updateNotes(${student.id}, this.value)">
            `;
        } else {
            // لا يمكنه الحضور - يظهر رسالة فقط
            html += `
                    <div class="cannot-attend-message">
                        <i class="fas fa-clock"></i>
                        لا يمكن تسجيل حضور - اليوم ليس من أيام حلقته
                    </div>
            `;
        }
        
        html += `
                    <input type="hidden" name="attendance[${student.id}][status]" id="status_${student.id}" value="${studentStatus[student.id]}">
                    <input type="hidden" name="attendance[${student.id}][notes]" id="notes_input_${student.id}" value="${escapeHtml(studentNotes[student.id])}">
                </div>
            </div>
        `;
    });
    
    document.getElementById('studentsGrid').innerHTML = html;
    
    // إظهار/إخفاء أزرار الإجراءات إذا كان هناك طلاب يمكنهم الحضور
    const actionButtons = document.getElementById('actionButtons');
    if (actionButtons) {
        actionButtons.style.display = hasAnyCanAttend ? 'flex' : 'none';
    }
    
    // إظهار/إخفاء حقول الملاحظات حسب الحالة
    document.querySelectorAll('.student-card').forEach(card => {
        const studentId = parseInt(card.dataset.studentId);
        const notesInput = card.querySelector(`#notes_${studentId}`);
        if (notesInput && studentStatus[studentId] && studentStatus[studentId] !== '') {
            notesInput.classList.add('show');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function setStatus(studentId, status) {
    studentStatus[studentId] = status;
    
    // تحديث واجهة المستخدم
    const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
    if (card) {
        card.querySelectorAll('.attendance-option').forEach(opt => opt.classList.remove('selected'));
        card.querySelector(`.attendance-option.${status}`).classList.add('selected');
        
        // تحديث لون البطاقة
        card.classList.remove('present', 'excused');
        if (status === 'present') card.classList.add('present');
        if (status === 'excused') card.classList.add('excused');
        
        const notesInput = card.querySelector(`#notes_${studentId}`);
        if (notesInput && status && status !== '') {
            notesInput.classList.add('show');
        } else if (notesInput) {
            notesInput.classList.remove('show');
        }
    }
    
    document.getElementById(`status_${studentId}`).value = status;
    
    // تحديث الإحصائيات
    updateStats();
}

function updateNotes(studentId, notes) {
    studentNotes[studentId] = notes;
    document.getElementById(`notes_input_${studentId}`).value = notes;
}

function updateStats() {
    let present = 0;
    let excused = 0;
    
    for (const studentId in studentStatus) {
        if (studentStatus[studentId] === 'present') {
            present++;
        } else if (studentStatus[studentId] === 'excused') {
            excused++;
        }
    }
    
    document.getElementById('presentCount').textContent = present;
    document.getElementById('excusedCount').textContent = excused;
}

function selectAll(status) {
    studentsData.forEach(student => {
        // فقط الطلاب الذين يمكنهم الحضور اليوم
        if (canAttend(student.id, getLocalDate()) && student.on_leave != 1) {
            setStatus(student.id, status);
        }
    });
}

function deselectAll() {
    studentsData.forEach(student => {
        if (canAttend(student.id, getLocalDate()) && student.on_leave != 1) {
            setStatus(student.id, '');
        }
    });
}

// تحديث محدد التاريخ
document.getElementById('datePicker')?.addEventListener('change', function() {
    window.location.href = '?date=' + this.value;
});

document.addEventListener('DOMContentLoaded', renderPage);
</script>

<?php require_once 'includes/footer.php'; ?>