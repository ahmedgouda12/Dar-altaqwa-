<?php
// ============================================
// ملف: attendance_teachers.php - تسجيل حضور المعلمين (نسخة محسنة)
// آخر تحديث: 2026-04-23
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تسجيل حضور المعلمين';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : 0;
$is_admin = isAdmin();

// ============================================
// اختيار التاريخ
// ============================================
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$today = $selected_date;
$today_day = date('w', strtotime($today)) + 1;
$is_today = ($selected_date == date('Y-m-d'));

$message = '';
$message_type = '';

// ============================================
// جلب المعلمين حسب الصلاحية
// ============================================
if ($is_admin) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM students WHERE teacher_id = t.id) as students_count,
               (SELECT COUNT(*) FROM rings WHERE teacher_id = t.id) as rings_count
        FROM teachers t
        WHERE t.can_login = 1
        ORDER BY t.name
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM students WHERE teacher_id = t.id) as students_count,
               (SELECT COUNT(*) FROM rings WHERE teacher_id = t.id) as rings_count
        FROM teachers t
        WHERE t.id = ?
    ");
    $stmt->execute([$teacher_id]);
    $teachers = $stmt->fetchAll();
}

// ============================================
// جلب تسجيلات الحضور للتاريخ المحدد
// ============================================
$attendance_today = [];
$stmt = $pdo->prepare("
    SELECT person_id, status, notes, is_excused, excuse_reason
    FROM attendance 
    WHERE person_type = 'teacher' AND date = ?
");
$stmt->execute([$selected_date]);
$attendance_data = $stmt->fetchAll();

foreach ($attendance_data as $att) {
    $attendance_today[$att['person_id']] = $att;
}

// ============================================
// جلب العطل الرسمية
// ============================================
$holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
$holiday_check->execute([$selected_date, $selected_date]);
$is_holiday = $holiday_check->fetch();

$is_friday = ($today_day == 6);

// ============================================
// جلب المعلمين على إجازة
// ============================================
$teachers_on_leave = [];
if ($is_admin) {
    $stmt = $pdo->prepare("
        SELECT t.* 
        FROM teachers t
        WHERE t.can_login = 1 AND t.on_leave = 1
        AND t.leave_start_date <= ? AND t.leave_end_date >= ?
        ORDER BY t.name
    ");
    $stmt->execute([$selected_date, $selected_date]);
    $teachers_on_leave = $stmt->fetchAll();
}

// ============================================
// معالجة حفظ التعديلات
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $attendance_date = $_POST['attendance_date'] ?? $selected_date;
    $attendance_notes = trim($_POST['attendance_notes'] ?? '');
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($teachers as $teacher) {
        $teacher_id_val = $teacher['id'];
        
        // جلب الحالة المختارة للمعلم الحالي
        $selected_status = isset($_POST['status_' . $teacher_id_val]) ? $_POST['status_' . $teacher_id_val] : 'none';
        
        $is_present = ($selected_status === 'present');
        $is_excused = ($selected_status === 'excused');
        
        if ($selected_status === 'none') {
            continue; // لم يتم اختيار أي حالة لهذا المعلم، نتخطاه
        }
        
        $status = $is_present ? 'present' : 'absent';
        $notes = $attendance_notes;
        
        // التحقق من وجود سجل
        $check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='teacher' AND person_id=? AND date=?");
        $check->execute([$teacher_id_val, $attendance_date]);
        
        if ($check->fetch()) {
            // تحديث الموجود
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ?, 
                    notes = ?, 
                    is_excused = ?, 
                    excuse_reason = ?
                WHERE person_type='teacher' AND person_id=? AND date=?
            ");
            $stmt->execute([
                $status, 
                $notes, 
                $is_excused ? 1 : 0, 
                $is_excused ? ($attendance_notes ?: 'اعتذار') : null, 
                $teacher_id_val, 
                $attendance_date
            ]);
            $updated++;
        } else {
            // إضافة جديد
            $stmt = $pdo->prepare("
                INSERT INTO attendance (person_type, person_id, date, status, notes, is_excused, excuse_reason)
                VALUES ('teacher', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $teacher_id_val, 
                $attendance_date, 
                $status, 
                $notes, 
                $is_excused ? 1 : 0, 
                $is_excused ? ($attendance_notes ?: 'اعتذار') : null
            ]);
            $inserted++;
        }
    }
    
    if ($inserted > 0 || $updated > 0) {
        $message = "✅ تم تسجيل حضور $inserted معلم جديد، وتحديث $updated معلم";
        $message_type = 'success';
    } else {
        $message = "⚠️ لم يتم تسجيل أي معلم";
        $message_type = 'warning';
    }
    
    // إعادة تحميل البيانات
    $stmt = $pdo->prepare("SELECT person_id, status, notes, is_excused FROM attendance WHERE person_type='teacher' AND date=?");
    $stmt->execute([$selected_date]);
    $attendance_data = $stmt->fetchAll();
    $attendance_today = [];
    foreach ($attendance_data as $att) {
        $attendance_today[$att['person_id']] = $att;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل حضور المعلمين - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .attendance-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .page-header { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 25px; border-radius: 20px; margin-bottom: 25px; text-align: center; }
        .page-header h1 { margin: 0; display: flex; align-items: center; justify-content: center; gap: 15px; font-size: 1.8rem; }
        .page-header p { margin-top: 10px; opacity: 0.9; }
        .date-selector { background: white; border-radius: 20px; padding: 15px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .date-input-group { display: flex; align-items: center; gap: 10px; background: #f8f9fa; padding: 5px 15px; border-radius: 50px; }
        .date-input-group label { font-weight: 600; color: #1e3c3f; }
        .date-input-group input { padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 30px; font-size: 0.9rem; background: white; }
        .date-nav { display: flex; gap: 10px; }
        .date-nav-btn { background: #f8f9fa; border: none; padding: 8px 20px; border-radius: 30px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: #1e3c3f; }
        .date-nav-btn:hover { background: #c9a96b; color: white; transform: translateY(-2px); }
        .date-nav-btn.today { background: #28a745; color: white; }
        .notice-box { background: #fff3cd; color: #856404; padding: 15px; border-radius: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-right: 5px solid #ffc107; }
        .teachers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .teacher-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid #eee; }
        .teacher-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .teacher-card.recorded { background: #e8f5e9; border-right: 5px solid #28a745; }
        .teacher-card.excused { background: #d1ecf1; border-right: 5px solid #17a2b8; }
        .teacher-card.cannot-attend { opacity: 0.7; background: #f8f9fa; border-right: 5px solid #ffc107; }
        .teacher-card.on-leave { opacity: 0.6; background: #e9ecef; border-right: 5px solid #17a2b8; }
        .teacher-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .teacher-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .teacher-name { font-size: 1.2rem; font-weight: 700; color: #1e3c3f; margin-bottom: 3px; }
        .teacher-details { font-size: 0.8rem; color: #666; display: flex; gap: 10px; flex-wrap: wrap; }
        .teacher-details i { color: #c9a96b; }
        .work-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-top: 8px; }
        .work-badge.can-work { background: #d4edda; color: #155724; }
        .work-badge.cannot-work { background: #f8d7da; color: #721c24; }
        .attendance-options { display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap; }
        .attendance-option { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border-radius: 30px; cursor: pointer; transition: 0.3s; border: 2px solid transparent; font-weight: 600; font-size: 0.9rem; background: #f8f9fa; }
        .attendance-option.present { border-color: #28a745; color: #155724; }
        .attendance-option.present.selected { background: #28a745; color: white; }
        .attendance-option.excused { border-color: #17a2b8; color: #0c5460; }
        .attendance-option.excused.selected { background: #17a2b8; color: white; }
        .attendance-option input { display: none; }
        .recorded-badge { background: #28a745; color: white; padding: 12px; border-radius: 12px; text-align: center; margin-top: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; }
        .excused-badge { background: #17a2b8; color: white; padding: 12px; border-radius: 12px; text-align: center; margin-top: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; }
        .leave-badge { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 12px; text-align: center; margin-top: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; }
        .notes-section { margin: 20px 0; }
        .notes-section label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e3c3f; }
        .notes-section textarea { width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; resize: vertical; }
        .btn-save { width: 100%; padding: 15px; background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; border-radius: 50px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(40,167,69,0.3); }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid #28a745; }
        .alert-warning { background: #fff3cd; color: #856404; border-right: 5px solid #ffc107; }
        @media (max-width: 768px) {
            .attendance-page { padding: 15px; }
            .teachers-grid { grid-template-columns: 1fr; }
            .date-selector { flex-direction: column; }
            .date-input-group { width: 100%; justify-content: center; }
            .date-nav { width: 100%; justify-content: center; }
            .attendance-options { flex-direction: column; }
        }
    </style>
</head>
<body>
<section class="attendance-page">
    <div class="page-header">
        <h1><i class="fas fa-chalkboard-teacher"></i> تسجيل حضور المعلمين</h1>
        <p>سجل حضور المعلمين اليومي (يمكنك اختيار أي تاريخ)</p>
    </div>

    <!-- محدد التاريخ -->
    <div class="date-selector">
        <div class="date-input-group">
            <label><i class="fas fa-calendar-alt"></i> التاريخ:</label>
            <input type="date" id="datePicker" value="<?php echo $selected_date; ?>">
        </div>
        <div class="date-nav">
            <button class="date-nav-btn" onclick="changeDate(-1)"><i class="fas fa-chevron-right"></i> اليوم السابق</button>
            <button class="date-nav-btn" onclick="changeDate(1)">اليوم التالي <i class="fas fa-chevron-left"></i></button>
            <button class="date-nav-btn today" onclick="goToToday()"><i class="fas fa-calendar-day"></i> اليوم</button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($is_friday): ?>
        <div class="notice-box"><i class="fas fa-mosque fa-2x"></i><div><strong>📅 يوم الجمعة</strong><br>اليوم هو يوم الجمعة (إجازة رسمية)، لا يمكن تسجيل الحضور في هذا اليوم.</div></div>
    <?php elseif ($is_holiday): ?>
        <div class="notice-box"><i class="fas fa-calendar-times fa-2x"></i><div><strong>📅 إجازة رسمية</strong><br>اليوم عطلة رسمية، لا يمكن تسجيل الحضور في هذا اليوم.</div></div>
    <?php endif; ?>

    <!-- عرض المعلمين على إجازة -->
    <?php if (!empty($teachers_on_leave)): ?>
        <div class="notice-box" style="background: #d1ecf1; color: #0c5460; border-right-color: #17a2b8;">
            <i class="fas fa-clock fa-2x"></i>
            <div><strong>📋 معلمون على إجازة في هذا التاريخ:</strong><br>
            <?php foreach ($teachers_on_leave as $t): ?>
                <?php echo htmlspecialchars($t['name']); ?> (من <?php echo $t['leave_start_date']; ?> إلى <?php echo $t['leave_end_date']; ?>)
                <?php if ($t['leave_reason']): ?> - <?php echo htmlspecialchars($t['leave_reason']); ?><?php endif; ?><br>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($teachers)): ?>
        <div class="empty-state"><i class="fas fa-chalkboard-teacher" style="font-size: 4rem; color: #dee2e6;"></i><h3 style="margin-top: 15px;">لا يوجد معلمين متاحين للحضور في هذا التاريخ</h3><p>جميع المعلمين على إجازة أو لا يوجد معلمين مسجلين</p></div>
    <?php else: ?>
        <form method="post" id="attendanceForm">
            <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
            <div class="teachers-grid">
                <?php foreach ($teachers as $teacher):
                    $work_days = explode(',', $teacher['work_days'] ?? '1,2,3,4,5,6,7');
                    $can_work_today = in_array($today_day, $work_days);
                    $is_on_leave = false;
                    foreach ($teachers_on_leave as $leave_teacher) {
                        if ($leave_teacher['id'] == $teacher['id']) {
                            $is_on_leave = true;
                            break;
                        }
                    }
                    
                    $current_att = $attendance_today[$teacher['id']] ?? null;
                    $current_status = $current_att['status'] ?? '';
                    $current_is_excused = $current_att['is_excused'] ?? 0;
                    
                    $teacher_initial = mb_substr($teacher['name'], 0, 1, 'UTF-8');
                    
                    $present_selected = ($current_status == 'present' && !$current_is_excused);
                    $excused_selected = ($current_is_excused == 1);
                ?>
                    <div class="teacher-card 
                        <?php echo $is_on_leave ? 'on-leave' : ''; ?> 
                        <?php echo (!$can_work_today && !$is_on_leave && !$is_friday && !$is_holiday) ? 'cannot-attend' : ''; ?>
                        <?php echo ($present_selected) ? 'recorded' : ''; ?>
                        <?php echo ($excused_selected) ? 'excused' : ''; ?>">
                        
                        <div class="teacher-header">
                            <div class="teacher-avatar"><?php echo $teacher_initial; ?></div>
                            <div>
                                <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                                <div class="teacher-details">
                                    <span><i class="fas fa-phone"></i> <?php echo $teacher['phone'] ?: 'لا يوجد'; ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo $teacher['students_count']; ?> طالب</span>
                                    <span><i class="fas fa-ring"></i> <?php echo $teacher['rings_count']; ?> حلقة</span>
                                </div>
                                <?php if (!$is_on_leave && !$is_friday && !$is_holiday): ?>
                                    <div class="work-badge <?php echo $can_work_today ? 'can-work' : 'cannot-work'; ?>">
                                        <i class="fas <?php echo $can_work_today ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <?php echo $can_work_today ? 'يوم عمل' : 'ليس يوم عمل'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($is_on_leave): ?>
                            <div class="leave-badge"><i class="fas fa-clock"></i> على إجازة</div>
                        <?php elseif (!$can_work_today && !$is_friday && !$is_holiday): ?>
                            <div class="excused-badge" style="background: #6c757d;"><i class="fas fa-calendar-day"></i> ليس يوم عمل</div>
                        <?php elseif ($is_friday || $is_holiday): ?>
                            <div class="excused-badge" style="background: #ffc107; color: #212529;"><i class="fas fa-calendar-times"></i> إجازة رسمية</div>
                        <?php else: ?>
<!-- خيارات الحضور - راديوبوتن واضحة -->
                            <div class="attendance-options">
                                <label class="attendance-option present <?php echo $present_selected ? 'selected' : ''; ?>">
                                    <i class="fas fa-check-circle"></i> ✅ حاضر
                                    <input type="radio" name="status_<?php echo $teacher['id']; ?>" value="present" <?php echo $present_selected ? 'checked' : ''; ?>>
                                </label>
                                <label class="attendance-option excused <?php echo $excused_selected ? 'selected' : ''; ?>">
                                    <i class="fas fa-calendar-times"></i> ⏰ معتذر
                                    <input type="radio" name="status_<?php echo $teacher['id']; ?>" value="excused" <?php echo $excused_selected ? 'checked' : ''; ?>>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="notes-section">
                <label><i class="fas fa-sticky-note"></i> ملاحظات عامة (اختياري)</label>
                <textarea name="attendance_notes" rows="2" placeholder="أي ملاحظات عن حضور المعلمين في هذا التاريخ..."></textarea>
            </div>

            <?php if ((!$is_friday && !$is_holiday)): ?>
                <button type="submit" name="save_attendance" class="btn-save"><i class="fas fa-save"></i> حفظ التغييرات</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<script>
function changeDate(delta) {
    const currentDate = new Date('<?php echo $selected_date; ?>');
    currentDate.setDate(currentDate.getDate() + delta);
    const newDate = currentDate.toISOString().split('T')[0];
    window.location.href = '?date=' + newDate;
}

function goToToday() {
    const today = new Date().toISOString().split('T')[0];
    window.location.href = '?date=' + today;
}

// تفعيل خيارات الحضور وإلغاء التحديدات الأخرى تلقائياً
document.querySelectorAll('.attendance-option').forEach(option => {
    option.addEventListener('click', function() {
        const parentCard = this.closest('.teacher-card');
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            
            // إزالة التحديد من جميع خيارات هذا المعلم
            parentCard.querySelectorAll('.attendance-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // إضافة التحديد للخيار المختار
            this.classList.add('selected');
            
            // تحديث لون البطاقة حسب الحالة
            if (this.classList.contains('present')) {
                parentCard.classList.add('recorded');
                parentCard.classList.remove('excused');
            } else if (this.classList.contains('excused')) {
                parentCard.classList.add('excused');
                parentCard.classList.remove('recorded');
            }
        }
    });
});

// تحديث محدد التاريخ
document.getElementById('datePicker')?.addEventListener('change', function() {
    window.location.href = '?date=' + this.value;
});

console.log('✅ صفحة تسجيل حضور المعلمين - النسخة المحسنة جاهزة');
</script>

<?php require_once 'includes/footer.php'; ?>