<?php
// ============================================
// ملف: admin_attendance.php
// تسجيل حضور الطلاب للإدارة - يعرض فقط الطلاب الذين يمكنهم الحضور اليوم
// آخر تحديث: 2026-04-26
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تسجيل حضور الطلاب - الإدارة';
require_once 'includes/header.php';

$today = date('Y-m-d');
$today_day = date('w', strtotime($today)) + 1;
$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$selected_ring = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;
$message = '';
$message_type = '';

// التحقق من العطل الرسمية
$holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
$holiday_check->execute([$today, $today]);
$is_holiday = $holiday_check->fetch();
$is_friday = ($today_day == 6);

// ============================================
// جلب جميع المعلمين
// ============================================
$teachers = $pdo->query("
    SELECT t.id, t.name, t.gender, t.specialization,
           COUNT(DISTINCT s.id) as students_count,
           COUNT(DISTINCT r.id) as rings_count
    FROM teachers t
    LEFT JOIN students s ON t.id = s.teacher_id
    LEFT JOIN rings r ON t.id = r.teacher_id
    WHERE t.can_login = 1
    GROUP BY t.id
    ORDER BY t.name
")->fetchAll();

// ============================================
// إذا تم اختيار معلم، جلب حلقاته وطلابه الذين يمكنهم الحضور اليوم
// ============================================
$teacher_info = null;
$rings_list = [];
$students_list = [];
$current_ring = null;

if ($selected_teacher > 0) {
    $teacher_stmt = $pdo->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM students WHERE teacher_id = t.id) as total_students,
               (SELECT COUNT(*) FROM rings WHERE teacher_id = t.id) as total_rings
        FROM teachers t
        WHERE t.id = ?
    ");
    $teacher_stmt->execute([$selected_teacher]);
    $teacher_info = $teacher_stmt->fetch();
    
    if ($teacher_info) {
        // جلب حلقات المعلم
        $rings_stmt = $pdo->prepare("
            SELECT r.*, 
                   (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
            FROM rings r
            WHERE r.teacher_id = ?
            ORDER BY r.name
        ");
        $rings_stmt->execute([$selected_teacher]);
        $rings_list = $rings_stmt->fetchAll();
        
        // دالة للحصول على طلاب الحلقة مع فلترة (يمكنهم الحضور اليوم فقط)
        function getStudentsForRing($pdo, $ring_id, $today, $today_day) {
            $students = [];
            
            // جلب جميع طلاب الحلقة
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, s.category, s.level, s.parent_phone,
                       (SELECT COUNT(*) FROM student_surah_progress 
                        WHERE student_id = s.id AND completed = 1) as memorized_count
                FROM ring_students rs
                JOIN students s ON rs.student_id = s.id
                WHERE rs.ring_id = ?
                ORDER BY s.name
            ");
            $stmt->execute([$ring_id]);
            $all_students = $stmt->fetchAll();
            
            // تصفية: فقط الطلاب الذين لهم حلقة في هذا اليوم
            $filtered = [];
            foreach ($all_students as $student) {
                $student_ring_days = getStudentRingDays($pdo, $student['id']);
                if (in_array($today_day, $student_ring_days)) {
                    $student['can_attend_today'] = true;
                    $student['ring_days_text'] = getStudentRingDaysText($pdo, $student['id']);
                    $filtered[] = $student;
                } else {
                    $student['can_attend_today'] = false;
                    $student['ring_days_text'] = getStudentRingDaysText($pdo, $student['id']);
                    $filtered[] = $student;
                }
            }
            
            return $filtered;
        }
        
        if ($selected_ring > 0) {
            $ring_stmt = $pdo->prepare("SELECT * FROM rings WHERE id = ? AND teacher_id = ?");
            $ring_stmt->execute([$selected_ring, $selected_teacher]);
            $current_ring = $ring_stmt->fetch();
            
            if ($current_ring) {
                $students_list = getStudentsForRing($pdo, $selected_ring, $today, $today_day);
            }
        } elseif (count($rings_list) == 1) {
            $selected_ring = $rings_list[0]['id'];
            $current_ring = $rings_list[0];
            $students_list = getStudentsForRing($pdo, $selected_ring, $today, $today_day);
        }
    }
}

// ============================================
// جلب تسجيلات الحضور الحالية لهذا اليوم
// ============================================
$attendance_map = [];
if (!empty($students_list)) {
    $ids = array_column($students_list, 'id');
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
foreach ($students_list as &$student) {
    if (isset($attendance_map[$student['id']])) {
        $student['attendance_status'] = $attendance_map[$student['id']]['status'];
        $student['attendance_notes'] = $attendance_map[$student['id']]['notes'];
        $student['is_excused'] = $attendance_map[$student['id']]['is_excused'];
    } else {
        $student['attendance_status'] = null;
        $student['attendance_notes'] = '';
        $student['is_excused'] = 0;
    }
}

// ============================================
// معالجة تسجيل الحضور
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance']) && $selected_ring > 0) {
    $attendance_notes = trim($_POST['attendance_notes'] ?? '');
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($students_list as $student) {
        $status = isset($_POST['status_' . $student['id']]) ? $_POST['status_' . $student['id']] : '';
        $notes = isset($_POST['notes_' . $student['id']]) ? trim($_POST['notes_' . $student['id']]) : '';
        
        if (empty($status)) continue;
        
        $is_excused = ($status == 'excused') ? 1 : 0;
        $att_status = ($status == 'present') ? 'present' : (($status == 'late') ? 'late' : 'absent');
        
        $check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
        $check->execute([$student['id'], $today]);
        
        if ($check->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ?, notes = ?, is_excused = ?, excuse_reason = ? 
                WHERE person_type='student' AND person_id=? AND date=?
            ");
            $stmt->execute([$att_status, $notes, $is_excused, ($is_excused ? $notes : null), $student['id'], $today]);
            $updated++;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance (person_type, person_id, date, status, notes, is_excused, excuse_reason) 
                VALUES ('student', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student['id'], $today, $att_status, $notes, $is_excused, ($is_excused ? $notes : null)]);
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
    if (!empty($students_list)) {
        $ids = array_column($students_list, 'id');
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
        foreach ($students_list as &$student) {
            if (isset($attendance_map[$student['id']])) {
                $student['attendance_status'] = $attendance_map[$student['id']]['status'];
                $student['attendance_notes'] = $attendance_map[$student['id']]['notes'];
                $student['is_excused'] = $attendance_map[$student['id']]['is_excused'];
            }
        }
    }
}

// ============================================
// عرض النموذج
// ============================================
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>تسجيل حضور الطلاب - الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); min-height: 100vh; }
        .admin-attendance { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .page-header { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 25px; border-radius: 20px; margin-bottom: 25px; text-align: center; }
        .page-header h1 { margin: 0; display: flex; align-items: center; justify-content: center; gap: 15px; font-size: 1.5rem; }
        .date-badge { background: rgba(255,255,255,0.15); padding: 5px 15px; border-radius: 30px; display: inline-block; margin-top: 10px; font-size: 0.85rem; }
        
        .teachers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .teacher-card { background: white; border-radius: 20px; padding: 20px; cursor: pointer; transition: 0.3s; border: 2px solid transparent; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center; }
        .teacher-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .teacher-card.active { border-color: #c9a96b; background: linear-gradient(135deg, #fff8e7, #fff3d6); }
        .teacher-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 15px; }
        .teacher-name { font-size: 1.1rem; font-weight: bold; color: #1e3c3f; margin-bottom: 5px; }
        .teacher-stats { display: flex; justify-content: center; gap: 15px; font-size: 0.8rem; color: #666; }
        
        .rings-section { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .section-title { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #c9a96b; }
        .rings-list { display: flex; gap: 10px; flex-wrap: wrap; }
        .ring-tag { background: #f8f9fa; border: 2px solid transparent; border-radius: 30px; padding: 8px 20px; text-decoration: none; color: #1e3c3f; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .ring-tag:hover { background: #e9ecef; transform: translateY(-2px); }
        .ring-tag.active { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; border-color: #c9a96b; }
        
        .attendance-form { background: white; border-radius: 25px; padding: 25px; margin-top: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .form-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #c9a96b; }
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
        .btn-save { width: 100%; padding: 15px; background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; border-radius: 50px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(40,167,69,0.3); }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid #28a745; }
        .alert-warning { background: #fff3cd; color: #856404; border-right: 5px solid #ffc107; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; background: #6c757d; color: white; padding: 8px 20px; border-radius: 30px; text-decoration: none; margin-bottom: 20px; transition: 0.3s; }
        .back-link:hover { transform: translateX(-5px); background: #5a6268; }
        
        @media (max-width: 768px) {
            .admin-attendance { padding: 15px; }
            .teachers-grid { grid-template-columns: 1fr; }
            .student-row { flex-direction: column; align-items: flex-start; }
            .attendance-options { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>

<section class="admin-attendance">
    <div class="page-header">
        <h1><i class="fas fa-user-check"></i> تسجيل حضور الطلاب - الإدارة</h1>
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

    <!-- ============================================ -->
    <!-- المستوى 1: قائمة المعلمين -->
    <!-- ============================================ -->
    <?php if (!$selected_teacher): ?>
        <div class="teachers-grid">
            <?php foreach ($teachers as $teacher): ?>
                <div class="teacher-card" onclick="window.location.href='?teacher_id=<?php echo $teacher['id']; ?>'">
                    <div class="teacher-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                    <div class="teacher-stats">
                        <span><i class="fas fa-users"></i> <?php echo $teacher['students_count']; ?> طالب</span>
                        <span><i class="fas fa-ring"></i> <?php echo $teacher['rings_count']; ?> حلقة</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($teachers)): ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>لا يوجد معلمين</h3>
                <p>قم بإضافة معلمين أولاً</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- المستوى 2: حلقات المعلم -->
    <!-- ============================================ -->
    <?php if ($selected_teacher > 0 && $teacher_info && !$selected_ring && count($rings_list) > 1): ?>
        
        <a href="admin_attendance.php" class="back-link"><i class="fas fa-arrow-right"></i> العودة للمعلمين</a>
        
        <div class="rings-section">
            <div class="section-title"><i class="fas fa-ring"></i> <h3>حلقات <?php echo htmlspecialchars($teacher_info['name']); ?></h3></div>
            <div class="rings-list">
                <a href="?teacher_id=<?php echo $selected_teacher; ?>" class="ring-tag <?php echo !$selected_ring ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> جميع الطلاب (<?php echo $teacher_info['total_students']; ?>)
                </a>
                <?php foreach ($rings_list as $ring): ?>
                    <a href="?teacher_id=<?php echo $selected_teacher; ?>&ring_id=<?php echo $ring['id']; ?>" class="ring-tag <?php echo $selected_ring == $ring['id'] ? 'active' : ''; ?>">
                        <i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?>
                        <span style="background: #e9ecef; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;"><?php echo $ring['students_count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
    <?php elseif ($selected_teacher > 0 && $teacher_info && $selected_ring && $current_ring): ?>
        
        <a href="admin_attendance.php" class="back-link"><i class="fas fa-arrow-right"></i> المعلمين</a>
        <a href="?teacher_id=<?php echo $selected_teacher; ?>" class="back-link"><i class="fas fa-arrow-right"></i> حلقات <?php echo htmlspecialchars($teacher_info['name']); ?></a>
        
    <?php elseif ($selected_teacher > 0 && $teacher_info && !$selected_ring && count($rings_list) <= 1): ?>
        
        <a href="admin_attendance.php" class="back-link"><i class="fas fa-arrow-right"></i> العودة للمعلمين</a>
        
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- المستوى 3: نموذج تسجيل الحضور -->
    <!-- ============================================ -->
    <?php if ($selected_ring > 0 && $current_ring && !$is_friday && !$is_holiday): ?>
        
        <div class="attendance-form">
            <div class="form-header">
                <h2><i class="fas fa-ring"></i> حلقة: <?php echo htmlspecialchars($current_ring['name']); ?></h2>
                <div><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($teacher_info['name']); ?></div>
            </div>
            
            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                <strong>ملاحظة:</strong> يتم عرض الطلاب الذين يمكنهم الحضور اليوم فقط. الطلاب الذين ليس لديهم حلقة اليوم لا يظهرون.
            </div>

            <form method="post" id="attendanceForm">
                <div class="students-list">
                    <?php 
                    $has_can_attend = false;
                    foreach ($students_list as $student): 
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
                            <?php else: ?>
                                <div class="cannot-attend-message">
                                    <i class="fas fa-calendar-day"></i>
                                    لا يمكنه الحضور اليوم - اليوم ليس من أيام حلقته
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($students_list)): ?>
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
                <button type="submit" name="save_attendance" class="btn-save">
                    <i class="fas fa-save"></i> تسجيل الحضور
                </button>
                <?php endif; ?>
            </form>
        </div>
        
    <?php elseif ($selected_ring > 0 && $current_ring && ($is_friday || $is_holiday)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times" style="color: #ffc107;"></i>
            <h3>لا يمكن تسجيل الحضور اليوم</h3>
            <p><?php echo $is_friday ? 'يوم الجمعة إجازة رسمية' : 'اليوم عطلة رسمية'; ?></p>
        </div>
    <?php elseif ($selected_teacher > 0 && $teacher_info && empty($students_list)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>لا يوجد طلاب</h3>
            <p>لا يوجد طلاب لهذا المعلم</p>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>