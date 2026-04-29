<?php
// ============================================
// ملف: teacher_attendance_by_ring.php
// تسجيل حضور الطلاب حسب الحلقة - يعرض فقط الطلاب الذين يمكنهم الحضور اليوم
// آخر تحديث: 2026-04-26
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تسجيل حضور الطلاب حسب الحلقة';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$today_day = date('w', strtotime($today)) + 1;
$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;
$message = '';
$message_type = '';

// التحقق من العطل الرسمية
$holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
$holiday_check->execute([$today, $today]);
$is_holiday = $holiday_check->fetch();
$is_friday = ($today_day == 6);

// جلب حلقات المعلم
$rings = $pdo->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
    FROM rings r
    WHERE r.teacher_id = ?
    ORDER BY r.name
");
$rings->execute([$teacher_id]);
$rings_list = $rings->fetchAll();

// جلب طلاب الحلقة المحددة
$ring_students = [];
$ring_info = null;

if ($ring_id > 0) {
    $ring_stmt = $pdo->prepare("SELECT * FROM rings WHERE id = ? AND teacher_id = ?");
    $ring_stmt->execute([$ring_id, $teacher_id]);
    $ring_info = $ring_stmt->fetch();
    
    if ($ring_info) {
        // جلب أيام حلقة هذا الطالب (من ring_schedules)
        $ring_days_stmt = $pdo->prepare("
            SELECT day_of_week FROM ring_schedules WHERE ring_id = ?
        ");
        $ring_days_stmt->execute([$ring_id]);
        $ring_days = $ring_days_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // جلب جميع طلاب الحلقة
        $all_ring_students = $pdo->prepare("
            SELECT s.id, s.name, s.level, s.parent_phone, s.category,
                   (SELECT COUNT(*) FROM student_surah_progress 
                    WHERE student_id = s.id AND completed = 1) as memorized_count
            FROM ring_students rs
            JOIN students s ON rs.student_id = s.id
            WHERE rs.ring_id = ?
            ORDER BY s.name
        ");
        $all_ring_students->execute([$ring_id]);
        $temp_students = $all_ring_students->fetchAll();
        
        // تصفية: فقط الطلاب الذين لهم حلقة في هذا اليوم
        foreach ($temp_students as $student) {
            // التحقق: هل هذا الطالب لديه حلقة اليوم؟
            // نتحقق من أيام حلقته الشخصية (وليس أيام الحلقة الحالية فقط)
            $student_ring_days = getStudentRingDays($pdo, $student['id']);
            $can_attend_today = in_array($today_day, $student_ring_days);
            
            $student['can_attend_today'] = $can_attend_today;
            $student['ring_days_text'] = getStudentRingDaysText($pdo, $student['id']);
            
            // جلب حالة الحضور الحالية إن وجدت
            $att_stmt = $pdo->prepare("
                SELECT status, notes, is_excused FROM attendance 
                WHERE person_type = 'student' AND person_id = ? AND date = ?
            ");
            $att_stmt->execute([$student['id'], $today]);
            $attendance = $att_stmt->fetch();
            $student['attendance_status'] = $attendance['status'] ?? null;
            $student['attendance_notes'] = $attendance['notes'] ?? '';
            $student['is_excused'] = $attendance['is_excused'] ?? 0;
            
            $ring_students[] = $student;
        }
    }
}

// ============================================
// جلب تسجيلات الحضور الحالية
// ============================================
$attendance_map = [];
if (!empty($ring_students)) {
    $ids = array_column($ring_students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT person_id, status, notes, is_excused 
        FROM attendance 
        WHERE person_type = 'student' AND date = ? AND person_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$today], $ids));
    foreach ($stmt->fetchAll() as $att) {
        $attendance_map[$att['person_id']] = $att;
    }
}

// تحديث بيانات الطلاب بالحضور المسجل
foreach ($ring_students as &$student) {
    if (isset($attendance_map[$student['id']])) {
        $student['attendance_status'] = $attendance_map[$student['id']]['status'];
        $student['attendance_notes'] = $attendance_map[$student['id']]['notes'];
        $student['is_excused'] = $attendance_map[$student['id']]['is_excused'];
    }
}

// ============================================
// معالجة تسجيل الحضور
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $attendance_date = $_POST['attendance_date'] ?? $today;
    $attendance_notes = trim($_POST['attendance_notes'] ?? '');
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($ring_students as $student) {
        // فقط الطلاب الذين يمكنهم الحضور اليوم (لديهم حلقة)
        if (!$student['can_attend_today']) {
            continue;
        }
        
        $status = isset($_POST['status_' . $student['id']]) ? $_POST['status_' . $student['id']] : '';
        $notes = isset($_POST['notes_' . $student['id']]) ? trim($_POST['notes_' . $student['id']]) : '';
        
        if (empty($status)) continue;
        
        $is_excused = ($status == 'excused') ? 1 : 0;
        $att_status = ($status == 'present') ? 'present' : (($status == 'late') ? 'late' : 'absent');
        
        $check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
        $check->execute([$student['id'], $attendance_date]);
        
        if ($check->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ?, notes = ?, is_excused = ?, excuse_reason = ? 
                WHERE person_type='student' AND person_id=? AND date=?
            ");
            $stmt->execute([$att_status, $notes, $is_excused, ($is_excused ? $notes : null), $student['id'], $attendance_date]);
            $updated++;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance (person_type, person_id, date, status, notes, is_excused, excuse_reason) 
                VALUES ('student', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student['id'], $attendance_date, $att_status, $notes, $is_excused, ($is_excused ? $notes : null)]);
            $inserted++;
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
    $ids = array_column($ring_students, 'id');
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT person_id, status, notes, is_excused 
            FROM attendance 
            WHERE person_type = 'student' AND date = ? AND person_id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$today], $ids));
        $attendance_map = [];
        foreach ($stmt->fetchAll() as $att) {
            $attendance_map[$att['person_id']] = $att;
        }
        foreach ($ring_students as &$student) {
            if (isset($attendance_map[$student['id']])) {
                $student['attendance_status'] = $attendance_map[$student['id']]['status'];
                $student['attendance_notes'] = $attendance_map[$student['id']]['notes'];
                $student['is_excused'] = $attendance_map[$student['id']]['is_excused'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>تسجيل حضور الطلاب حسب الحلقة - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); min-height: 100vh; }
        .attendance-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .page-header { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 25px; border-radius: 20px; margin-bottom: 25px; text-align: center; }
        .page-header h1 { margin: 0; display: flex; align-items: center; justify-content: center; gap: 15px; font-size: 1.5rem; }
        .date-badge { background: rgba(255,255,255,0.15); padding: 5px 15px; border-radius: 30px; display: inline-block; margin-top: 10px; font-size: 0.85rem; }
        
        .rings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .ring-card { background: white; border-radius: 20px; padding: 20px; cursor: pointer; transition: 0.3s; border: 2px solid transparent; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center; }
        .ring-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .ring-card.active { border-color: #c9a96b; background: linear-gradient(135deg, #fff8e7, #fff3d6); }
        .ring-name { font-size: 1.2rem; font-weight: bold; color: #1e3c3f; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .ring-students { background: #c9a96b; color: #1e3c3f; padding: 5px 15px; border-radius: 30px; display: inline-block; font-size: 0.8rem; font-weight: 600; }
        
        .attendance-form { background: white; border-radius: 25px; padding: 25px; margin-top: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .form-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #c9a96b; }
        .form-header h2 { color: #1e3c3f; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
        .info-note { background: #e7f3ff; padding: 12px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #0c5460; font-size: 0.85rem; }
        .students-list { display: flex; flex-direction: column; gap: 15px; margin: 20px 0; max-height: 500px; overflow-y: auto; }
        .student-row { display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 15px; transition: 0.3s; border: 1px solid #e9ecef; flex-wrap: wrap; }
        .student-row.cannot-attend { background: #e9ecef; opacity: 0.7; }
        .student-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: bold; flex-shrink: 0; }
        .student-info { flex: 2; min-width: 150px; }
        .student-name { font-weight: bold; color: #1e3c3f; font-size: 1rem; margin-bottom: 3px; }
        .student-details { font-size: 0.75rem; color: #666; display: flex; gap: 10px; flex-wrap: wrap; }
        .student-details i { color: #c9a96b; }
        .ring-days-text { background: #c9a96b20; padding: 2px 8px; border-radius: 20px; }
        .attendance-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .attendance-option { display: flex; align-items: center; gap: 5px; padding: 8px 15px; border-radius: 30px; cursor: pointer; transition: 0.3s; background: white; border: 2px solid transparent; font-size: 0.8rem; }
        .attendance-option.present { border-color: #28a745; color: #155724; }
        .attendance-option.present.selected { background: #28a745; color: white; }
        .attendance-option.late { border-color: #ffc107; color: #856404; }
        .attendance-option.late.selected { background: #ffc107; color: #212529; }
        .attendance-option.excused { border-color: #17a2b8; color: #0c5460; }
        .attendance-option.excused.selected { background: #17a2b8; color: white; }
        .attendance-option input { display: none; }
        .cannot-attend-message { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 12px; text-align: center; display: flex; align-items: center; gap: 8px; justify-content: center; font-size: 0.85rem; width: 100%; }
        .notes-input { width: 200px; padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 30px; font-size: 0.8rem; display: none; }
        .notes-input.show { display: block; }
        .btn-save { width: 100%; padding: 15px; background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; border-radius: 50px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(40,167,69,0.3); }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid #28a745; }
        .alert-warning { background: #fff3cd; color: #856404; border-right: 5px solid #ffc107; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; }
        @media (max-width: 768px) {
            .attendance-page { padding: 15px; }
            .rings-grid { grid-template-columns: 1fr; }
            .student-row { flex-direction: column; align-items: flex-start; }
            .attendance-options { width: 100%; justify-content: space-between; }
            .notes-input { width: 100%; margin-top: 10px; }
        }
    </style>
</head>
<body>

<section class="attendance-page">
    <div class="page-header">
        <h1><i class="fas fa-ring"></i> تسجيل حضور الطلاب حسب الحلقة</h1>
        <div class="date-badge"><i class="fas fa-calendar-alt"></i> <?php echo $today; ?> - <?php echo $is_friday ? 'الجمعة (إجازة)' : ''; ?></div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($is_friday): ?>
        <div class="alert alert-warning"><i class="fas fa-mosque"></i> يوم الجمعة إجازة رسمية - لا يمكن تسجيل الحضور اليوم.</div>
    <?php elseif ($is_holiday): ?>
        <div class="alert alert-warning"><i class="fas fa-calendar-times"></i> اليوم عطلة رسمية - لا يمكن تسجيل الحضور اليوم.</div>
    <?php endif; ?>

    <!-- قائمة الحلقات -->
    <?php if (!empty($rings_list)): ?>
    <div class="rings-grid">
        <?php foreach ($rings_list as $ring): ?>
            <div class="ring-card <?php echo $ring_id == $ring['id'] ? 'active' : ''; ?>" 
                 onclick="window.location.href='?ring_id=<?php echo $ring['id']; ?>'">
                <div class="ring-name"><i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?></div>
                <div class="ring-students"><i class="fas fa-users"></i> <?php echo $ring['students_count']; ?> طالب</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($ring_id > 0 && $ring_info && !$is_friday && !$is_holiday): ?>
        
        <div class="attendance-form">
            <div class="form-header">
                <h2><i class="fas fa-ring"></i> حلقة: <?php echo htmlspecialchars($ring_info['name']); ?></h2>
            </div>
            
            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                <strong>ملاحظة:</strong> يتم عرض الطلاب الذين يمكنهم الحضور اليوم فقط (لديهم حلقة في هذا اليوم). الطلاب الذين ليس لديهم حلقة اليوم لا يظهرون للتسجيل.
            </div>

            <form method="post" id="attendanceForm">
                <input type="hidden" name="attendance_date" value="<?php echo $today; ?>">
                
                <div class="students-list">
                    <?php 
                    $has_can_attend = false;
                    foreach ($ring_students as $student): 
                        if ($student['can_attend_today']) $has_can_attend = true;
                        $current_status = $student['attendance_status'] ?? '';
                        $is_excused = $student['is_excused'] ?? 0;
                        
                        $present_selected = ($current_status == 'present' && !$is_excused);
                        $late_selected = ($current_status == 'late');
                        $excused_selected = ($is_excused == 1);
                        
                        $avatar_color = $student['category'] == 'boy' ? '#3498db' : ($student['category'] == 'girl' ? '#9b59b6' : ($student['category'] == 'child' ? '#f39c12' : '#e84342'));
                    ?>
                        <div class="student-row <?php echo !$student['can_attend_today'] ? 'cannot-attend' : ''; ?>">
                            <div class="student-avatar" style="background: <?php echo $avatar_color; ?>;">
                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="student-info">
                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                <div class="student-details">
                                    <span><i class="fas fa-tag"></i> <?php echo $student['category'] == 'boy' ? 'أولاد' : ($student['category'] == 'girl' ? 'بنات' : ($student['category'] == 'child' ? 'أطفال' : 'نساء')); ?></span>
                                    <span><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                                    <span class="ring-days-text"><i class="fas fa-ring"></i> أيام حلقته: <?php echo $student['ring_days_text']; ?></span>
                                </div>
                            </div>
                            
                            <?php if ($student['can_attend_today']): ?>
                                <div class="attendance-options">
                                    <label class="attendance-option present <?php echo $present_selected ? 'selected' : ''; ?>">
                                        <i class="fas fa-check-circle"></i> حاضر
                                        <input type="radio" name="status_<?php echo $student['id']; ?>" value="present" <?php echo $present_selected ? 'checked' : ''; ?>>
                                    </label>
                                    <label class="attendance-option late <?php echo $late_selected ? 'selected' : ''; ?>">
                                        <i class="fas fa-clock"></i> متأخر
                                        <input type="radio" name="status_<?php echo $student['id']; ?>" value="late" <?php echo $late_selected ? 'checked' : ''; ?>>
                                    </label>
                                    <label class="attendance-option excused <?php echo $excused_selected ? 'selected' : ''; ?>">
                                        <i class="fas fa-calendar-times"></i> معتذر
                                        <input type="radio" name="status_<?php echo $student['id']; ?>" value="excused" <?php echo $excused_selected ? 'checked' : ''; ?>>
                                    </label>
                                </div>
                                <input type="text" class="notes-input" id="notes_<?php echo $student['id']; ?>" 
                                       placeholder="ملاحظات" value="<?php echo htmlspecialchars($student['attendance_notes'] ?? ''); ?>"
                                       onchange="document.getElementById('notes_input_<?php echo $student['id']; ?>').value = this.value">
                            <?php else: ?>
                                <div class="cannot-attend-message">
                                    <i class="fas fa-calendar-day"></i>
                                    لا يمكنه الحضور اليوم - اليوم ليس من أيام حلقته
                                </div>
                            <?php endif; ?>
                            
                            <input type="hidden" name="notes_<?php echo $student['id']; ?>" id="notes_input_<?php echo $student['id']; ?>" value="<?php echo htmlspecialchars($student['attendance_notes'] ?? ''); ?>">
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($ring_students)): ?>
                        <div class="empty-state">لا يوجد طلاب في هذه الحلقة</div>
                    <?php elseif (!$has_can_attend): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-day"></i>
                            <h3>لا يوجد طلاب يمكنهم الحضور اليوم</h3>
                            <p>جميع طلاب هذه الحلقة ليس لديهم حلقة في هذا اليوم</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($has_can_attend): ?>
                <div class="form-group" style="margin: 15px 0;">
                    <label><i class="fas fa-sticky-note"></i> ملاحظات عامة (اختياري)</label>
                    <textarea name="attendance_notes" class="form-control" rows="2" style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:12px;"></textarea>
                </div>
                <button type="submit" name="save_attendance" class="btn-save">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
                <?php endif; ?>
            </form>
        </div>
        
    <?php elseif ($ring_id > 0 && $ring_info && ($is_friday || $is_holiday)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times" style="color: #ffc107;"></i>
            <h3>لا يمكن تسجيل الحضور اليوم</h3>
            <p><?php echo $is_friday ? 'يوم الجمعة إجازة رسمية' : 'اليوم عطلة رسمية'; ?></p>
            <a href="teacher_attendance_by_ring.php" class="btn" style="background: #1e3c3f; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: none; margin-top: 15px;">العودة</a>
        </div>
    <?php elseif ($ring_id > 0 && !$ring_info): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>الحلقة غير موجودة</h3>
            <p>الحلقة غير موجودة أو لا تخصك</p>
        </div>
    <?php elseif (empty($rings_list)): ?>
        <div class="empty-state">
            <i class="fas fa-ring"></i>
            <h3>لا توجد حلقات</h3>
            <p>قم بإضافة حلقة أولاً</p>
            <a href="add_ring.php" class="btn" style="background: #1e3c3f; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: none;">إضافة حلقة</a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-hand-point-left"></i>
            <h3>اختر حلقة من القائمة</h3>
            <p>يرجى اختيار حلقة من القائمة أعلاه لتسجيل حضور طلابها</p>
        </div>
    <?php endif; ?>
</section>

<script>
// تفعيل خيارات الحضور
document.querySelectorAll('.attendance-option').forEach(option => {
    option.addEventListener('click', function() {
        const parentRow = this.closest('.student-row');
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            parentRow.querySelectorAll('.attendance-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // إظهار حقل الملاحظات
            const notesInput = parentRow.querySelector('.notes-input');
            if (notesInput) notesInput.classList.add('show');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
        