<?php
// ============================================
// ملف: advanced_evaluation.php
// نظام التقييم اليومي المتقدم + المؤقت للطلاب المتفرقين
// نسخة كاملة ومتكاملة - آخر تحديث: 2026-04-04
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'التقييم اليومي المتقدم';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$today = date('Y-m-d');
$current_time = time();
$current_time_str = date('H:i:s', $current_time);
$message = '';
$message_type = '';

// الأوزان
$weights = [
    'attendance' => 30,
    'new' => 20,
    'recent' => 15,
    'old' => 15,
    'behavior' => 20
];

// ============================================
// دوال مساعدة
// ============================================

function getTextRating($score) {
    if ($score >= 95) return ['text' => 'ممتاز', 'icon' => 'fa-crown', 'color' => '#ffd700'];
    if ($score >= 85) return ['text' => 'جيد جداً', 'icon' => 'fa-star', 'color' => '#28a745'];
    if ($score >= 75) return ['text' => 'جيد', 'icon' => 'fa-thumbs-up', 'color' => '#ffc107'];
    if ($score >= 60) return ['text' => 'مقبول', 'icon' => 'fa-hourglass-half', 'color' => '#fd7e14'];
    return ['text' => 'ضعيف', 'icon' => 'fa-exclamation-triangle', 'color' => '#dc3545'];
}

function getFinalGrade($score) {
    if ($score >= 95) return 'ممتاز 🌟🌟🌟';
    if ($score >= 85) return 'جيد جداً 🌟🌟';
    if ($score >= 75) return 'جيد 🌟';
    if ($score >= 60) return 'مقبول';
    return 'ضعيف ⚠️';
}

function calculateSectionScore($ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count, $total_items) {
    $base_score = 100;
    $deduction = ($ayah_mistakes * 3) + ($haraka_mistakes * 1) + ($tajweed_mistakes * 1) + ($hesitation_count * 1);
    $score = max(0, $base_score - $deduction);
    if ($total_items > 0) {
        $bonus = min(5, floor($total_items / 10));
        $score = min(100, $score + $bonus);
    }
    return round($score, 2);
}

function calculateAttendanceScore($attendance_status, $arrival_time = null) {
    switch ($attendance_status) {
        case 'present': return 100;
        case 'late':
            if ($arrival_time) {
                $deduction = min(50, floor($arrival_time / 5) * 5);
                return max(50, 100 - $deduction);
            }
            return 70;
        case 'absent': return 0;
        default: return 0;
    }
}

function getStudentAttendance($pdo, $student_id, $date) {
    $stmt = $pdo->prepare("SELECT status, notes FROM attendance WHERE person_type = 'student' AND person_id = ? AND date = ?");
    $stmt->execute([$student_id, $date]);
    return $stmt->fetch();
}

// ============================================
// دوال الطلاب المتفرقين
// ============================================

function getStudentScatteredInfo($pdo, $student_id) {
    // أولاً: التحقق من أن الطالب في حلقة متفرقين
    $check_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM rings r
        JOIN ring_students rs ON r.id = rs.ring_id
        WHERE rs.student_id = ? AND r.is_scattered = 1
    ");
    $check_stmt->execute([$student_id]);
    $is_scattered = $check_stmt->fetchColumn() > 0;
    
    if (!$is_scattered) {
        return ['has_timer' => false, 'is_scattered' => false];
    }
    
    // جلب معلومات الحلقة المتفرقة
    $ring_stmt = $pdo->prepare("
        SELECT r.scattered_start_time, r.scattered_end_time
        FROM rings r
        JOIN ring_students rs ON r.id = rs.ring_id
        WHERE rs.student_id = ? AND r.is_scattered = 1
        LIMIT 1
    ");
    $ring_stmt->execute([$student_id]);
    $ring = $ring_stmt->fetch();
    
    // جلب الجلسة النشطة للطالب (بدون شرط session_date)
    $session_stmt = $pdo->prepare("
        SELECT ss.id, ss.status, ss.started_at, ss.duration_minutes
        FROM scattered_session_slots ss
        WHERE ss.student_id = ? AND ss.status = 'active'
        LIMIT 1
    ");
    $session_stmt->execute([$student_id]);
    $session = $session_stmt->fetch();
    
    $time_remaining = 0;
    $progress_percent = 0;
    $is_active = false;
    
    if ($session && $session['started_at'] && $session['duration_minutes']) {
        $start = strtotime($session['started_at']);
        $now = time();
        $elapsed = $now - $start;
        $total = $session['duration_minutes'] * 60;
        $time_remaining = max(0, $total - $elapsed);
        $progress_percent = min(100, ($elapsed / $total) * 100);
        $is_active = ($session['status'] == 'active' && $time_remaining > 0);
    }
    
    return [
        'has_timer' => true,
        'is_scattered' => true,
        'start_time' => $ring['scattered_start_time'] ?? null,
        'end_time' => $ring['scattered_end_time'] ?? null,
        'duration' => $session['duration_minutes'] ?? 15,
        'session_id' => $session['id'] ?? null,
        'session_status' => $session['status'] ?? null,
        'started_at' => $session['started_at'] ?? null,
        'time_remaining' => $time_remaining,
        'progress_percent' => $progress_percent,
        'is_active' => $is_active
    ];
}

function getScatteredSessionSlots($pdo, $ring_id) {
    $stmt = $pdo->prepare("
        SELECT ss.*, s.name, s.level, s.id as student_id
        FROM scattered_session_slots ss
        JOIN students s ON ss.student_id = s.id
        WHERE ss.ring_id = ?
        ORDER BY ss.slot_order
    ");
    $stmt->execute([$ring_id]);
    return $stmt->fetchAll();
}

// ============================================
// جلب الطلاب (نسخة مبسطة ومباشرة)
// ============================================

$students = [];
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (isTeacher()) {
    $stmt = $pdo->prepare("
        SELECT id, name, category, level 
        FROM students 
        WHERE teacher_id = ?
        ORDER BY name
    ");
    $stmt->execute([$teacher_id]);
    $students_data = $stmt->fetchAll();
    
    foreach ($students_data as $student) {
        $eval_stmt = $pdo->prepare("SELECT COUNT(*) FROM student_daily_evaluations WHERE student_id = ? AND evaluation_date = ?");
        $eval_stmt->execute([$student['id'], $today]);
        $evaluated_today = $eval_stmt->fetchColumn();
        
        $att_stmt = $pdo->prepare("SELECT status FROM attendance WHERE person_type = 'student' AND person_id = ? AND date = ?");
        $att_stmt->execute([$student['id'], $today]);
        $attendance_status = $att_stmt->fetchColumn();
        
        $scattered_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM rings r
            JOIN ring_students rs ON r.id = rs.ring_id
            WHERE rs.student_id = ? AND r.is_scattered = 1
        ");
        $scattered_stmt->execute([$student['id']]);
        $is_scattered = $scattered_stmt->fetchColumn() > 0 ? 1 : 0;
        
        $students[] = [
            'id' => $student['id'],
            'name' => $student['name'],
            'category' => $student['category'],
            'level' => $student['level'],
            'evaluated_today' => $evaluated_today,
            'attendance_status' => $attendance_status,
            'is_scattered' => $is_scattered
        ];
    }
} elseif (isAdmin()) {
    $all_students = $pdo->query("SELECT id, name, category, level FROM students ORDER BY name")->fetchAll();
    foreach ($all_students as $student) {
        $eval_stmt = $pdo->prepare("SELECT COUNT(*) FROM student_daily_evaluations WHERE student_id = ? AND evaluation_date = ?");
        $eval_stmt->execute([$student['id'], $today]);
        $evaluated_today = $eval_stmt->fetchColumn();
        
        $att_stmt = $pdo->prepare("SELECT status FROM attendance WHERE person_type = 'student' AND person_id = ? AND date = ?");
        $att_stmt->execute([$student['id'], $today]);
        $attendance_status = $att_stmt->fetchColumn();
        
        $students[] = [
            'id' => $student['id'],
            'name' => $student['name'],
            'category' => $student['category'],
            'level' => $student['level'],
            'evaluated_today' => $evaluated_today,
            'attendance_status' => $attendance_status,
            'is_scattered' => 0
        ];
    }
}

// إحصائيات سريعة
$total_students = count($students);
$attendance_today = count(array_filter($students, fn($s) => in_array($s['attendance_status'], ['present', 'late'])));
$evaluated_today = count(array_filter($students, fn($s) => $s['evaluated_today'] > 0));

// ============================================
// جلب بيانات الطالب المختار
// ============================================

$student_data = null;
$student_attendance = null;
$scattered_info = null;
$session_slots = [];
$last_evaluation = null;

if ($selected_student > 0) {
    foreach ($students as $s) {
        if ($s['id'] == $selected_student) {
            $student_data = $s;
            break;
        }
    }
    
    if ($student_data) {
        $student_attendance = getStudentAttendance($pdo, $selected_student, $today);
        $scattered_info = getStudentScatteredInfo($pdo, $selected_student);
        
        $last_eval_stmt = $pdo->prepare("SELECT * FROM student_daily_evaluations WHERE student_id = ? AND evaluation_date = ?");
        $last_eval_stmt->execute([$selected_student, $today]);
        $last_evaluation = $last_eval_stmt->fetch();
        
        if ($scattered_info['is_scattered'] && $scattered_info['session_id']) {
            $slots_stmt = $pdo->prepare("
                SELECT ss.*, s.name, s.level
                FROM scattered_session_slots ss
                JOIN students s ON ss.student_id = s.id
                WHERE ss.session_id = ? AND ss.session_date = CURDATE()
                ORDER BY ss.slot_order
            ");
            $slots_stmt->execute([$scattered_info['session_id']]);
            $session_slots = $slots_stmt->fetchAll();
        }
    }
}

// ============================================
// معالجة حفظ التقييم
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation']) && $selected_student > 0) {
    $attendance_status = $_POST['attendance_status'];
    $attendance_delay = (int)($_POST['attendance_delay'] ?? 0);
    $attendance_notes = trim($_POST['attendance_notes'] ?? '');
    
    $new_ayah_mistakes = (int)$_POST['new_ayah_mistakes'];
    $new_haraka_mistakes = (int)$_POST['new_haraka_mistakes'];
    $new_tajweed_mistakes = (int)$_POST['new_tajweed_mistakes'];
    $new_hesitation_count = (int)$_POST['new_hesitation_count'];
    $new_memorized_ayahs = (int)$_POST['new_memorized_ayahs'];
    $new_range = trim($_POST['new_range'] ?? '');
    
    $recent_ayah_mistakes = (int)$_POST['recent_ayah_mistakes'];
    $recent_haraka_mistakes = (int)$_POST['recent_haraka_mistakes'];
    $recent_tajweed_mistakes = (int)$_POST['recent_tajweed_mistakes'];
    $recent_hesitation_count = (int)$_POST['recent_hesitation_count'];
    $recent_reviewed_ayahs = (int)$_POST['recent_reviewed_ayahs'];
    $recent_range = trim($_POST['recent_range'] ?? '');
    
    $old_ayah_mistakes = (int)$_POST['old_ayah_mistakes'];
    $old_haraka_mistakes = (int)$_POST['old_haraka_mistakes'];
    $old_tajweed_mistakes = (int)$_POST['old_tajweed_mistakes'];
    $old_hesitation_count = (int)$_POST['old_hesitation_count'];
    $old_reviewed_ayahs = (int)$_POST['old_reviewed_ayahs'];
    $old_range = trim($_POST['old_range'] ?? '');
    
    $behavior_rating = (int)$_POST['behavior_rating'];
    $behavior_notes = trim($_POST['behavior_notes'] ?? '');
    $general_notes = trim($_POST['general_notes'] ?? '');
    
    $new_score = calculateSectionScore($new_ayah_mistakes, $new_haraka_mistakes, $new_tajweed_mistakes, $new_hesitation_count, $new_memorized_ayahs);
    $recent_score = calculateSectionScore($recent_ayah_mistakes, $recent_haraka_mistakes, $recent_tajweed_mistakes, $recent_hesitation_count, $recent_reviewed_ayahs);
    $old_score = calculateSectionScore($old_ayah_mistakes, $old_haraka_mistakes, $old_tajweed_mistakes, $old_hesitation_count, $old_reviewed_ayahs);
    
    $behavior_score = ($behavior_rating / 5) * 100;
    $attendance_score = calculateAttendanceScore($attendance_status, $attendance_delay);
    
    $total_score = round(($attendance_score * 0.30) + ($new_score * 0.20) + ($recent_score * 0.15) + ($old_score * 0.15) + ($behavior_score * 0.20), 2);
    $grade = getFinalGrade($total_score);
    
    try {
        $pdo->beginTransaction();
        
        // تحديث الحضور
        $att_check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
        $att_check->execute([$selected_student, $today]);
        if ($att_check->fetch()) {
            $pdo->prepare("UPDATE attendance SET status=?, notes=? WHERE person_type='student' AND person_id=? AND date=?")
                ->execute([$attendance_status, $attendance_notes, $selected_student, $today]);
        } else {
            $pdo->prepare("INSERT INTO attendance (person_type, person_id, date, status, notes) VALUES ('student', ?, ?, ?, ?)")
                ->execute([$selected_student, $today, $attendance_status, $attendance_notes]);
        }
        
        // إنهاء جلسة المتفرقين إذا كانت نشطة
        if ($scattered_info['is_scattered'] && $scattered_info['session_id']) {
            $pdo->prepare("
                UPDATE scattered_session_slots 
                SET status = 'completed', ended_at = NOW(),
                    evaluation_score = ?, evaluation_notes = ?
                WHERE session_id = ? AND student_id = ? AND status = 'active'
            ")->execute([$behavior_rating, $general_notes, $scattered_info['session_id'], $selected_student]);
        }
        
        // حفظ التقييم
        $check = $pdo->prepare("SELECT id FROM student_daily_evaluations WHERE student_id = ? AND evaluation_date = ?");
        $check->execute([$selected_student, $today]);
        
        if ($check->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE student_daily_evaluations SET
                    attendance_status = ?, attendance_notes = ?, attendance_score = ?,
                    new_ayah_mistakes = ?, new_haraka_mistakes = ?, new_tajweed_mistakes = ?,
                    new_hesitation_count = ?, new_memorized_ayahs = ?, new_range = ?,
                    recent_ayah_mistakes = ?, recent_haraka_mistakes = ?, recent_tajweed_mistakes = ?,
                    recent_hesitation_count = ?, recent_reviewed_ayahs = ?, recent_range = ?,
                    old_ayah_mistakes = ?, old_haraka_mistakes = ?, old_tajweed_mistakes = ?,
                    old_hesitation_count = ?, old_reviewed_ayahs = ?, old_range = ?,
                    behavior_rating = ?, behavior_notes = ?, general_notes = ?,
                    new_memorization_score = ?, recent_review_score = ?, old_review_score = ?,
                    behavior_score = ?, total_score = ?, grade = ?
                WHERE student_id = ? AND evaluation_date = ?
            ");
            $stmt->execute([
                $attendance_status, $attendance_notes, $attendance_score,
                $new_ayah_mistakes, $new_haraka_mistakes, $new_tajweed_mistakes,
                $new_hesitation_count, $new_memorized_ayahs, $new_range,
                $recent_ayah_mistakes, $recent_haraka_mistakes, $recent_tajweed_mistakes,
                $recent_hesitation_count, $recent_reviewed_ayahs, $recent_range,
                $old_ayah_mistakes, $old_haraka_mistakes, $old_tajweed_mistakes,
                $old_hesitation_count, $old_reviewed_ayahs, $old_range,
                $behavior_rating, $behavior_notes, $general_notes,
                $new_score, $recent_score, $old_score, $behavior_score, $total_score, $grade,
                $selected_student, $today
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO student_daily_evaluations (
                    student_id, teacher_id, evaluation_date,
                    attendance_status, attendance_notes, attendance_score,
                    new_ayah_mistakes, new_haraka_mistakes, new_tajweed_mistakes,
                    new_hesitation_count, new_memorized_ayahs, new_range,
                    recent_ayah_mistakes, recent_haraka_mistakes, recent_tajweed_mistakes,
                    recent_hesitation_count, recent_reviewed_ayahs, recent_range,
                    old_ayah_mistakes, old_haraka_mistakes, old_tajweed_mistakes,
                    old_hesitation_count, old_reviewed_ayahs, old_range,
                    behavior_rating, behavior_notes, general_notes,
                    new_memorization_score, recent_review_score, old_review_score,
                    behavior_score, total_score, grade
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $selected_student, $teacher_id, $today,
                $attendance_status, $attendance_notes, $attendance_score,
                $new_ayah_mistakes, $new_haraka_mistakes, $new_tajweed_mistakes,
                $new_hesitation_count, $new_memorized_ayahs, $new_range,
                $recent_ayah_mistakes, $recent_haraka_mistakes, $recent_tajweed_mistakes,
                $recent_hesitation_count, $recent_reviewed_ayahs, $recent_range,
                $old_ayah_mistakes, $old_haraka_mistakes, $old_tajweed_mistakes,
                $old_hesitation_count, $old_reviewed_ayahs, $old_range,
                $behavior_rating, $behavior_notes, $general_notes,
                $new_score, $recent_score, $old_score, $behavior_score, $total_score, $grade
            ]);
        }
        
        $pdo->commit();
        $message = "✅ تم تسجيل تقييم الطالب بنجاح";
        $message_type = 'success';
        
        // تحديث الصفحة لإعادة التوجيه للقائمة
        echo "<meta http-equiv='refresh' content='2;url=advanced_evaluation.php'>";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "❌ خطأ: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقييم اليومي المتقدم - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .evaluation-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ===== رأس الصفحة ===== */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
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
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.6rem;
        }
        
        .date-badge {
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 1rem;
        }
        /* ===== إحصائيات ===== */
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
            color: var(--primary);
        }
        
        /* ===== قائمة الطلاب ===== */
        .students-list {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .student-item:hover {
            background: #e9ecef;
            transform: translateX(-5px);
        }
        
        .student-item.active {
            border-color: var(--secondary);
            background: linear-gradient(135deg, #fff8e7, #fff3d6);
        }
        
        .student-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
        }
        
        .student-badge {
            font-size: 0.7rem;
            padding: 4px 12px;
            border-radius: 30px;
            font-weight: 600;
        }
        
        .badge-present { background: #d4edda; color: #155724; }
        .badge-absent { background: #f8d7da; color: #721c24; }
        .badge-late { background: #fff3cd; color: #856404; }
        .badge-scattered { background: #d1ecf1; color: #0c5460; }
        
        .student-time-info {
            font-size: 0.7rem;
            color: var(--info);
            margin-top: 3px;
        }
        
        /* ===== المؤقت ===== */
        .timer-card {
            background: linear-gradient(145deg, #1a1a2e, #16213e);
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .timer-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,215,0,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .timer-display {
            font-size: 4rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 8px;
            color: var(--secondary);
            margin: 20px 0;
            position: relative;
            z-index: 2;
        }
        
        .progress-container {
            width: 80%;
            margin: 15px auto;
            position: relative;
            z-index: 2;
        }
        
        .progress-bar-bg {
            height: 10px;
            background: rgba(255,255,255,0.2);
            border-radius: 30px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success), #20c997);
            border-radius: 30px;
            transition: width 0.5s linear;
        }
        
        /* ===== قائمة فترات الطلاب ===== */
        .slots-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .slots-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }
        
        .slot-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
            border-right: 4px solid;
        }
        
        .slot-item.active {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-right-color: var(--success);
        }
        
        .slot-order {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .slot-item.active .slot-order {
            background: var(--success);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* ===== نموذج التقييم ===== */
        .form-card {
            background: white;
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        
        .section-title i.new { background: linear-gradient(135deg, #28a745, #20c997); }
        .section-title i.recent { background: linear-gradient(135deg, #17a2b8, #138496); }
        .section-title i.old { background: linear-gradient(135deg, #fd7e14, #ffc107); }
        .section-title i.behavior { background: linear-gradient(135deg, #6f42c1, #9b59b6); }
        
        .weight-badge {
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .mistakes-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .mistake-card {
            background: white;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .counter-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .counter-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .counter-btn.minus { background: var(--danger); color: white; }
        .counter-btn.plus { background: var(--success); color: white; }
        .counter-value { font-size: 1.3rem; font-weight: 700; min-width: 50px; text-align: center; }
        
        .attendance-options {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .attendance-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: white;
            border-radius: 50px;
            cursor: pointer;
            border: 2px solid transparent;
            flex: 1;
            justify-content: center;
        }
        
        .attendance-option.selected {
            border-color: var(--secondary);
            background: var(--primary);
            color: white;
        }
        
        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .rating-star {
            font-size: 2.5rem;
            cursor: pointer;
            color: #ddd;
            transition: 0.2s;
        }
        
        .rating-star:hover,
        .rating-star.active {
            color: #ffc107;
            transform: scale(1.1);
        }
        
        .final-score-preview {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            border-radius: 30px;
            padding: 30px;
            text-align: center;
            margin: 30px 0 20px;
        }
        
        .final-score-value {
            font-size: 3rem;
            font-weight: 800;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            resize: vertical;
        }
        
        .btn-save {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40,167,69,0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid var(--success); }
        .alert-error { background: #f8d7da; color: #721c24; border-right: 5px solid var(--danger); }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 25px;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border-radius: 30px;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .stats-summary { grid-template-columns: repeat(2, 1fr); }
            .students-grid { grid-template-columns: 1fr; }
            .mistakes-grid { grid-template-columns: repeat(2, 1fr); }
            .timer-display { font-size: 2.5rem; letter-spacing: 5px; }
        }
        
        @media (max-width: 480px) {
            .mistakes-grid { grid-template-columns: 1fr; }
            .attendance-options { flex-direction: column; }
        }
    </style> 
    </head>
<body>
<div class="evaluation-page">
    <div class="page-header">
        <h1><i class="fas fa-star"></i> التقييم اليومي المتقدم</h1>
        <div class="date-badge"><i class="fas fa-calendar-alt"></i> <?php echo $today; ?></div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات -->
    <div class="stats-summary">
        <div class="stat-card"><div class="stat-number"><?php echo $total_students; ?></div><div>إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#28a745;"><?php echo $attendance_today; ?></div><div>حاضر اليوم</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#ffc107;"><?php echo $evaluated_today; ?></div><div>مقيم اليوم</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#dc3545;"><?php echo $total_students - $evaluated_today; ?></div><div>لم يقيم بعد</div></div>
    </div>

    <!-- قائمة الطلاب -->
    <div class="students-list">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-users"></i> اختر الطالب</h3>
        
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash" style="font-size: 3rem; color: #ccc;"></i>
                <h3>لا يوجد طلاب</h3>
                <p>لا يوجد طلاب مرتبطون بحسابك</p>
                <?php if (isTeacher()): ?>
                    <a href="students.php" class="btn btn-primary">إدارة الطلاب</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="students-grid">
                <?php foreach ($students as $s): 
                    $status_class = '';
                    $status_text = '';
                    if ($s['attendance_status'] == 'present') {
                        $status_class = 'present';
                        $status_text = 'حاضر';
                    } elseif ($s['attendance_status'] == 'late') {
                        $status_class = 'late';
                        $status_text = 'متأخر';
                    } elseif ($s['attendance_status'] == 'absent') {
                        $status_class = 'absent';
                        $status_text = 'غائب';
                    }
                    
                    $scattered_time = '';
                    if ($s['is_scattered']) {
                        $scattered_info_item = getStudentScatteredInfo($pdo, $s['id']);
                        if ($scattered_info_item['has_timer']) {
                            $scattered_time = '⏰ ' . date('h:i A', strtotime($scattered_info_item['start_time'])) . ' - ' . date('h:i A', strtotime($scattered_info_item['end_time']));
                        }
                    }
                ?>
                    <div class="student-item <?php echo $selected_student == $s['id'] ? 'active' : ''; ?>" 
                         onclick="window.location.href='?student_id=<?php echo $s['id']; ?>'">
                        <div>
                            <div class="student-name"><?php echo htmlspecialchars($s['name']); ?></div>
                            <small><?php echo htmlspecialchars($s['level'] ?? 'مبتدئ'); ?></small>
                            <?php if ($scattered_time): ?>
                                <div class="student-time-info"><i class="fas fa-hourglass-half"></i> <?php echo $scattered_time; ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($status_class): ?>
                                <span class="student-badge badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            <?php elseif ($s['evaluated_today'] > 0): ?>
                                <span class="student-badge badge-present"><i class="fas fa-check-circle"></i> مقيم</span>
                            <?php else: ?>
                                <span class="student-badge" style="background:#e9ecef; color:#6c757d;">لم يقيم</span>
                            <?php endif; ?>
                            <?php if ($s['is_scattered']): ?>
                                <span class="student-badge badge-scattered" style="margin-left: 5px;"><i class="fas fa-hourglass-half"></i> متفرق</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selected_student > 0 && $student_data): ?>
    
        <!-- زر العودة -->
        <a href="advanced_evaluation.php" class="back-btn"><i class="fas fa-arrow-right"></i> العودة للقائمة</a>
        
        <!-- معلومات الطالب -->
        <div style="background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px;">
            <h3 style="color: var(--primary);"><i class="fas fa-user-graduate"></i> تقييم: <?php echo htmlspecialchars($student_data['name']); ?></h3>
            <p>المستوى: <?php echo htmlspecialchars($student_data['level'] ?? 'مبتدئ'); ?> | الفئة: <?php echo $student_data['category']; ?></p>
        </div>
        
        <!-- ===== للطلاب المتفرقين: المؤقت ===== -->
        <?php if ($scattered_info && $scattered_info['is_scattered'] && $scattered_info['is_active']): ?>
            <div class="timer-card">
                <div class="timer-display" id="timerDisplay">
                    <?php echo sprintf("%02d:%02d", floor($scattered_info['time_remaining'] / 60), $scattered_info['time_remaining'] % 60); ?>
                </div>
                <div class="progress-container">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="progressFill" style="width: <?php echo $scattered_info['progress_percent']; ?>%;"></div>
                    </div>
                </div>
                <div style="color: rgba(255,255,255,0.7);">الوقت المتبقي: <?php echo floor($scattered_info['time_remaining'] / 60); ?> دقيقة</div>
            </div>
        <?php elseif ($scattered_info && $scattered_info['is_scattered']): ?>
            <div class="timer-card">
                <div class="timer-display">00:00</div>
                <div style="color: rgba(255,255,255,0.7);">في انتظار وقت الجلسة: <?php echo date('h:i A', strtotime($scattered_info['start_time'])); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- ===== للطلاب المتفرقين: فترات الجلسة ===== -->
        <?php if (!empty($session_slots)): ?>
            <div class="slots-card">
                <div class="section-header" style="margin-bottom: 0; border-bottom: none;">
                    <div class="section-title">
                        <i class="fas fa-list-ol"></i>
                        <h3>ترتيب الطلاب في الجلسة</h3>
                    </div>
                </div>
                <div class="slots-list">
                    <?php foreach ($session_slots as $slot):
                        $is_current = ($slot['student_id'] == $selected_student);
                        $status_class = '';
                        if ($slot['status'] == 'active') $status_class = 'active';
                    ?>
                        <div class="slot-item <?php echo $status_class; ?>">
                            <div class="slot-order"><?php echo $slot['slot_order']; ?></div>
                            <div class="slot-name" style="flex:1;"><?php echo htmlspecialchars($slot['name']); ?></div>
                            <div class="slot-time">
                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($slot['slot_start'])); ?> - <?php echo date('h:i A', strtotime($slot['slot_end'])); ?>
                            </div>
                            <div class="slot-duration" style="background: var(--secondary); padding: 3px 10px; border-radius: 20px;"><?php echo $slot['duration_minutes']; ?> دقيقة</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ===== نموذج التقييم المتقدم ===== -->
        <form method="post" class="form-card" id="evaluationForm">
            <input type="hidden" name="save_evaluation" value="1">
            
            <!-- قسم الحضور -->
            <div class="section-header">
                <div class="section-title"><i class="fas fa-calendar-check"></i><h3>الحضور</h3></div>
                <span class="weight-badge">30%</span>
            </div>
            <div class="attendance-options" id="attendanceOptions">
                <label class="attendance-option <?php echo ($student_attendance && $student_attendance['status'] == 'present') ? 'selected' : ''; ?>" data-value="present">
                    <i class="fas fa-check-circle"></i> حاضر (100%)
                    <input type="radio" name="attendance_status" value="present" <?php echo ($student_attendance && $student_attendance['status'] == 'present') ? 'checked' : ''; ?>>
                </label>
                <label class="attendance-option <?php echo ($student_attendance && $student_attendance['status'] == 'late') ? 'selected' : ''; ?>" data-value="late">
                    <i class="fas fa-clock"></i> متأخر (70-95%)
                    <input type="radio" name="attendance_status" value="late" <?php echo ($student_attendance && $student_attendance['status'] == 'late') ? 'checked' : ''; ?>>
                </label>
                <label class="attendance-option <?php echo ($student_attendance && $student_attendance['status'] == 'absent') ? 'selected' : ''; ?>" data-value="absent">
                    <i class="fas fa-times-circle"></i> غائب (0%)
                    <input type="radio" name="attendance_status" value="absent" <?php echo ($student_attendance && $student_attendance['status'] == 'absent') ? 'checked' : ''; ?>>
                </label>
            </div>
            <div id="delayInput" class="delay-input" style="display: none; margin-top: 15px;">
                <label>مقدار التأخير (بالدقائق)</label>
                <input type="number" name="attendance_delay" id="attendanceDelay" class="form-control" value="0" min="0" max="120">
            </div>
            <textarea name="attendance_notes" class="form-control" rows="2" placeholder="📝 ملاحظات عن الحضور..."></textarea>
            
            <!-- الحفظ الجديد -->
            <div class="section-header" style="margin-top: 25px;">
                <div class="section-title"><i class="fas fa-book-open new"></i><h3>الحفظ الجديد</h3></div>
                <span class="weight-badge">20%</span>
            </div>
            <div class="mistakes-grid">
                <div class="mistake-card">
                    <div class="mistake-title">خطأ في الآية</div>
                    <div class="counter-container">
                        <button type="button" class="counter-btn minus" onclick="updateCounter('new_ayah', -1)">−</button>
                        <span class="counter-value" id="new_ayah_val">0</span>
                        <button type="button" class="counter-btn plus" onclick="updateCounter('new_ayah', 1)">+</button>
                    </div>
                    <input type="hidden" name="new_ayah_mistakes" id="new_ayah" value="0">
                </div>
                <div class="mistake-card">
                    <div class="mistake-title">خطأ في التشكيل</div>
                    <div class="counter-container">
                        <button type="button" class="counter-btn minus" onclick="updateCounter('new_haraka', -1)">−</button>
                        <span class="counter-value" id="new_haraka_val">0</span>
                        <button type="button" class="counter-btn plus" onclick="updateCounter('new_haraka', 1)">+</button>
                    </div>
                    <input type="hidden" name="new_haraka_mistakes" id="new_haraka" value="0">
                </div>
                <div class="mistake-card">
                    <div class="mistake-title">خطأ في التجويد</div>
                    <div class="counter-container">
                        <button type="button" class="counter-btn minus" onclick="updateCounter('new_tajweed', -1)">−</button>
                        <span class="counter-value" id="new_tajweed_val">0</span>
                        <button type="button" class="counter-btn plus" onclick="updateCounter('new_tajweed', 1)">+</button>
                    </div>
                    <input type="hidden" name="new_tajweed_mistakes" id="new_tajweed" value="0">
                </div>
                <div class="mistake-card">
                    <div class="mistake-title">تردد / شك</div>
                    <div class="counter-container">
                        <button type="button" class="counter-btn minus" onclick="updateCounter('new_hesitation', -1)">−</button>
                        <span class="counter-value" id="new_hesitation_val">0</span>
                        <button type="button" class="counter-btn plus" onclick="updateCounter('new_hesitation', 1)">+</button>
                    </div>
                    <input type="hidden" name="new_hesitation_count" id="new_hesitation" value="0">
                </div>
            </div>
            <div class="form-group">
                <label>عدد الآيات المحفوظة</label>
                <input type="number" name="new_memorized_ayahs" id="new_ayahs" class="form-control" value="0" min="0">
            </div>
            <input type="text" name="new_range" class="form-control" placeholder="نطاق الحفظ (مثال: سورة الملك آية 1-10)">
            
<!-- ============================================ -->
<!-- المراجعة القريبة -->
<!-- ============================================ -->
<div class="section-header" style="margin-top: 25px;">
    <div class="section-title"><i class="fas fa-history recent"></i><h3>المراجعة القريبة</h3></div>
    <span class="weight-badge">15%</span>
</div>

<div class="mistakes-grid">
    <div class="mistake-card">
        <div class="mistake-title">خطأ في الآية</div>
        <div class="mistake-deduction">-3 نقاط</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('recent_ayah', -1)">−</button>
            <span class="counter-value" id="recent_ayah_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('recent_ayah', 1)">+</button>
        </div>
        <input type="hidden" name="recent_ayah_mistakes" id="recent_ayah" value="0">
    </div>
    <div class="mistake-card">
        <div class="mistake-title">خطأ في التشكيل</div>
        <div class="mistake-deduction">-1 نقطة</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('recent_haraka', -1)">−</button>
            <span class="counter-value" id="recent_haraka_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('recent_haraka', 1)">+</button>
        </div>
        <input type="hidden" name="recent_haraka_mistakes" id="recent_haraka" value="0">
    </div>
    <div class="mistake-card">
        <div class="mistake-title">خطأ في التجويد</div>
        <div class="mistake-deduction">-1 نقطة</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('recent_tajweed', -1)">−</button>
            <span class="counter-value" id="recent_tajweed_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('recent_tajweed', 1)">+</button>
        </div>
        <input type="hidden" name="recent_tajweed_mistakes" id="recent_tajweed" value="0">
    </div>
    <div class="mistake-card">
        <div class="mistake-title">تردد / شك</div>
        <div class="mistake-deduction">-1 نقطة</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('recent_hesitation', -1)">−</button>
            <span class="counter-value" id="recent_hesitation_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('recent_hesitation', 1)">+</button>
        </div>
        <input type="hidden" name="recent_hesitation_count" id="recent_hesitation" value="0">
    </div>
</div>

<div class="form-group">
    <label>عدد آيات المراجعة القريبة</label>
    <input type="number" name="recent_reviewed_ayahs" id="recent_ayahs" class="form-control" value="0" min="0" onchange="updateScores()">
    <small>كل 20 آية مراجعة = +1 نقطة مكافأة (بحد أقصى 5 نقاط)</small>
</div>

<input type="text" name="recent_range" class="form-control" placeholder="📖 نطاق المراجعة القريبة (مثال: سورة الكهف آية 1-20)">

<!-- ============================================ -->
<!-- المراجعة البعيدة -->
<!-- ============================================ -->
<div class="section-header" style="margin-top: 25px;">
    <div class="section-title"><i class="fas fa-archive old"></i><h3>المراجعة البعيدة</h3></div>
    <span class="weight-badge">15%</span>
</div>

<div class="mistakes-grid">
    <div class="mistake-card">
        <div class="mistake-title">خطأ في الآية</div>
        <div class="mistake-deduction">-3 نقاط</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('old_ayah', -1)">−</button>
            <span class="counter-value" id="old_ayah_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('old_ayah', 1)">+</button>
        </div>
        <input type="hidden" name="old_ayah_mistakes" id="old_ayah" value="0">
    </div>
    <div class="mistake-card">
        <div class="mistake-title">خطأ في التشكيل</div>
        <div class="mistake-deduction">-1 نقطة</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('old_haraka', -1)">−</button>
            <span class="counter-value" id="old_haraka_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('old_haraka', 1)">+</button>
        </div>
        <input type="hidden" name="old_haraka_mistakes" id="old_haraka" value="0">
    </div>
    <div class="mistake-card">
        <div class="mistake-title">خطأ في التجويد</div>
        <div class="mistake-deduction">-1 نقطة</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('old_tajweed', -1)">−</button>
            <span class="counter-value" id="old_tajweed_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('old_tajweed', 1)">+</button>
        </div>
        <input type="hidden" name="old_tajweed_mistakes" id="old_tajweed" value="0">
    </div>
    <div class="mistake-card">
        <div class="mistake-title">تردد / شك</div>
        <div class="mistake-deduction">-1 نقطة</div>
        <div class="counter-container">
            <button type="button" class="counter-btn minus" onclick="updateCounter('old_hesitation', -1)">−</button>
            <span class="counter-value" id="old_hesitation_val">0</span>
            <button type="button" class="counter-btn plus" onclick="updateCounter('old_hesitation', 1)">+</button>
        </div>
        <input type="hidden" name="old_hesitation_count" id="old_hesitation" value="0">
    </div>
</div>

<div class="form-group">
    <label>عدد آيات المراجعة البعيدة</label>
    <input type="number" name="old_reviewed_ayahs" id="old_ayahs" class="form-control" value="0" min="0" onchange="updateScores()">
    <small>كل 20 آية مراجعة = +1 نقطة مكافأة (بحد أقصى 5 نقاط)</small>
</div>

<input type="text" name="old_range" class="form-control" placeholder="📖 نطاق المراجعة البعيدة (مثال: الأجزاء 1-5)">
            
            <!-- السلوك -->
            <div class="section-header" style="margin-top: 25px;">
                <div class="section-title"><i class="fas fa-smile behavior"></i><h3>السلوك</h3></div>
                <span class="weight-badge">20%</span>
            </div>
            <div class="rating-stars" id="ratingStars">
                <span class="rating-star" data-value="1">★</span>
                <span class="rating-star" data-value="2">★</span>
                <span class="rating-star" data-value="3">★</span>
                <span class="rating-star" data-value="4">★</span>
                <span class="rating-star" data-value="5">★</span>
            </div>
            <input type="hidden" name="behavior_rating" id="behaviorValue" value="3">
            <textarea name="behavior_notes" class="form-control" rows="2" placeholder="ملاحظات عن السلوك..."></textarea>
            
            <!-- ملاحظات عامة -->
            <textarea name="general_notes" class="form-control" rows="2" placeholder="ملاحظات عامة..." style="margin-top: 15px;"></textarea>
            
            <!-- النتيجة النهائية -->
            <div class="final-score-preview">
                <div class="final-score-value" id="previewScore">0</div>
                <div id="previewGrade"></div>
            </div>
            
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ التقييم</button>
        </form>
        
    <?php endif; ?>
</div>

<script>
// ============================================
// تعريف متغيرات الأخطاء لجميع الأقسام
// ============================================
let mistakes = {
    // الحفظ الجديد
    ayah: 0,
    haraka: 0,
    tajweed: 0,
    hesitation: 0,
    // المراجعة القريبة
    recent_ayah: 0,
    recent_haraka: 0,
    recent_tajweed: 0,
    recent_hesitation: 0,
    // المراجعة البعيدة
    old_ayah: 0,
    old_haraka: 0,
    old_tajweed: 0,
    old_hesitation: 0
};

// ============================================
// دالة تحديث العداد
// ============================================
function updateCounter(type, change) {
    let newVal = (mistakes[type] || 0) + change;
    if (newVal < 0) return;
    mistakes[type] = newVal;
    
    // تحديث العرض
    let valSpan = document.getElementById(type + '_val');
    let valInput = document.getElementById(type);
    if (valSpan) valSpan.innerText = newVal;
    if (valInput) valInput.value = newVal;
    
    updateScores();
}

// ============================================
// دالة الحصول على التقييم النصي حسب الدرجة
// ============================================
function getRating(score) {
    if (score >= 95) return { text: 'ممتاز', icon: 'fa-crown', color: '#ffd700' };
    if (score >= 90) return { text: 'ممتاز', icon: 'fa-star', color: '#28a745' };
    if (score >= 85) return { text: 'جيد جداً', icon: 'fa-star-half-alt', color: '#20c997' };
    if (score >= 80) return { text: 'جيد جداً', icon: 'fa-smile', color: '#17a2b8' };
    if (score >= 75) return { text: 'جيد', icon: 'fa-thumbs-up', color: '#ffc107' };
    if (score >= 70) return { text: 'جيد', icon: 'fa-check-circle', color: '#fd7e14' };
    if (score >= 60) return { text: 'مقبول', icon: 'fa-hourglass-half', color: '#6c757d' };
    return { text: 'ضعيف', icon: 'fa-exclamation-triangle', color: '#dc3545' };
}

// ============================================
// دالة تحديث عرض التقييم النصي
// ============================================
function updateRatingDisplay(elementId, score) {
    let element = document.getElementById(elementId);
    if (!element) return;
    
    let rating = getRating(score);
    element.innerHTML = `<i class="fas ${rating.icon}" style="color: ${rating.color};"></i> ${rating.text}`;
    element.style.background = `linear-gradient(135deg, ${rating.color}20, ${rating.color}10)`;
    element.style.color = rating.color;
}

// ============================================
// دالة تحديث بطاقة النتيجة
// ============================================
function updateResultCard(cardId, score) {
    let card = document.getElementById(cardId);
    if (!card) return;
    
    let resultValue = card.querySelector('.result-value');
    let resultRating = card.querySelector('.result-rating');
    
    if (resultValue) {
        resultValue.innerText = Math.round(score) + '%';
    }
    if (resultRating) {
        let rating = getRating(score);
        resultRating.innerHTML = `<i class="fas ${rating.icon}" style="color: ${rating.color};"></i> ${rating.text}`;
        resultRating.style.background = `linear-gradient(135deg, ${rating.color}20, ${rating.color}10)`;
        resultRating.style.color = rating.color;
    }
}

// ============================================
// دالة تحديث جميع الدرجات (كاملة)
// ============================================
function updateScores() {
    // ============================================
    // 1. حساب درجة الحضور (الوزن 30%)
    // ============================================
    let attendanceScore = 100;
    let selectedAttendance = document.querySelector('input[name="attendance_status"]:checked');
    if (selectedAttendance) {
        if (selectedAttendance.value === 'present') {
            attendanceScore = 100;
        } else if (selectedAttendance.value === 'late') {
            let delay = parseInt(document.getElementById('attendanceDelay')?.value) || 0;
            let deduction = Math.min(50, Math.floor(delay / 5) * 5);
            attendanceScore = Math.max(50, 100 - deduction);
        } else if (selectedAttendance.value === 'absent') {
            attendanceScore = 0;
        }
    }
    if (document.getElementById('previewAttendance')) {
        document.getElementById('previewAttendance').innerText = attendanceScore;
    }
    
    // ============================================
    // 2. حساب درجة الحفظ الجديد (الوزن 20%)
    // ============================================
    let newAyahMistakes = mistakes.ayah || 0;
    let newHarakaMistakes = mistakes.haraka || 0;
    let newTajweedMistakes = mistakes.tajweed || 0;
    let newHesitationCount = mistakes.hesitation || 0;
    let newMemorizedAyahs = parseInt(document.getElementById('new_ayahs')?.value) || 0;
    
    let newScore = 100 - ((newAyahMistakes * 3) + (newHarakaMistakes * 1) + (newTajweedMistakes * 1) + (newHesitationCount * 1));
    newScore = Math.max(0, newScore);
    let newBonus = Math.min(5, Math.floor(newMemorizedAyahs / 10));
    newScore = Math.min(100, newScore + newBonus);
    
    if (document.getElementById('newScoreDisplay')) {
        document.getElementById('newScoreDisplay').innerText = Math.round(newScore) + '%';
    }
    updateRatingDisplay('newRatingDisplay', newScore);
    if (document.getElementById('previewNew')) {
        document.getElementById('previewNew').innerText = Math.round(newScore);
    }
    
    // ============================================
    // 3. حساب درجة المراجعة القريبة (الوزن 15%)
    // ============================================
    let recentAyahMistakes = mistakes.recent_ayah || 0;
    let recentHarakaMistakes = mistakes.recent_haraka || 0;
    let recentTajweedMistakes = mistakes.recent_tajweed || 0;
    let recentHesitationCount = mistakes.recent_hesitation || 0;
    let recentReviewedAyahs = parseInt(document.getElementById('recent_ayahs')?.value) || 0;
    
    let recentScore = 100 - ((recentAyahMistakes * 3) + (recentHarakaMistakes * 1) + (recentTajweedMistakes * 1) + (recentHesitationCount * 1));
    recentScore = Math.max(0, recentScore);
    let recentBonus = Math.min(5, Math.floor(recentReviewedAyahs / 20));
    recentScore = Math.min(100, recentScore + recentBonus);
    
    if (document.getElementById('recentScoreDisplay')) {
        document.getElementById('recentScoreDisplay').innerText = Math.round(recentScore) + '%';
    }
    updateRatingDisplay('recentRatingDisplay', recentScore);
    if (document.getElementById('previewRecent')) {
        document.getElementById('previewRecent').innerText = Math.round(recentScore);
    }
    
    // ============================================
    // 4. حساب درجة المراجعة البعيدة (الوزن 15%)
    // ============================================
    let oldAyahMistakes = mistakes.old_ayah || 0;
    let oldHarakaMistakes = mistakes.old_haraka || 0;
    let oldTajweedMistakes = mistakes.old_tajweed || 0;
    let oldHesitationCount = mistakes.old_hesitation || 0;
    let oldReviewedAyahs = parseInt(document.getElementById('old_ayahs')?.value) || 0;
    
    let oldScore = 100 - ((oldAyahMistakes * 3) + (oldHarakaMistakes * 1) + (oldTajweedMistakes * 1) + (oldHesitationCount * 1));
    oldScore = Math.max(0, oldScore);
    let oldBonus = Math.min(5, Math.floor(oldReviewedAyahs / 20));
    oldScore = Math.min(100, oldScore + oldBonus);
    
    if (document.getElementById('oldScoreDisplay')) {
        document.getElementById('oldScoreDisplay').innerText = Math.round(oldScore) + '%';
    }
    updateRatingDisplay('oldRatingDisplay', oldScore);
    if (document.getElementById('previewOld')) {
        document.getElementById('previewOld').innerText = Math.round(oldScore);
    }
    
    // ============================================
    // 5. حساب درجة السلوك (الوزن 20%)
    // ============================================
    let behaviorRating = parseInt(document.getElementById('behaviorValue')?.value) || 3;
    let behaviorScore = (behaviorRating / 5) * 100;
    if (document.getElementById('behaviorScoreDisplay')) {
        document.getElementById('behaviorScoreDisplay').innerText = Math.round(behaviorScore) + '%';
    }
    if (document.getElementById('previewBehavior')) {
        document.getElementById('previewBehavior').innerText = Math.round(behaviorScore);
    }
    
    // ============================================
    // 6. حساب الدرجة النهائية (باستخدام الأوزان)
    // ============================================
    let totalScore = (attendanceScore * 0.30) + 
                     (newScore * 0.20) + 
                     (recentScore * 0.15) + 
                     (oldScore * 0.15) + 
                     (behaviorScore * 0.20);
    totalScore = Math.round(totalScore);
    
    // ============================================
    // 7. تحديد التقدير النهائي
    // ============================================
    let grade = '';
    if (totalScore >= 95) {
        grade = 'ممتاز 🌟🌟🌟';
    } else if (totalScore >= 85) {
        grade = 'جيد جداً 🌟🌟';
    } else if (totalScore >= 75) {
        grade = 'جيد 🌟';
    } else if (totalScore >= 60) {
        grade = 'مقبول';
    } else {
        grade = 'ضعيف ⚠️';
    }
    
    if (document.getElementById('previewScore')) {
        document.getElementById('previewScore').innerText = totalScore;
    }
    if (document.getElementById('previewGrade')) {
        document.getElementById('previewGrade').innerHTML = grade;
    }
    
    // تحديث بطاقات النتائج
    updateResultCard('newResultCard', newScore);
    updateResultCard('recentResultCard', recentScore);
    updateResultCard('oldResultCard', oldScore);
    
    // طباعة في الكونسول للتشخيص
    console.log('📊 تحديث الدرجات:', {
        الحضور: attendanceScore,
        'حفظ جديد': Math.round(newScore),
        'مراجعة قريبة': Math.round(recentScore),
        'مراجعة بعيدة': Math.round(oldScore),
        السلوك: Math.round(behaviorScore),
        'الدرجة النهائية': totalScore,
        التقدير: grade
    });
}

// ============================================
// تقييم السلوك (النجوم)
// ============================================
document.querySelectorAll('.rating-star').forEach(star => {
    star.addEventListener('click', function() {
        let value = parseInt(this.dataset.value);
        document.getElementById('behaviorValue').value = value;
        document.querySelectorAll('.rating-star').forEach(s => {
            if (parseInt(s.dataset.value) <= value) {
                s.classList.add('active');
            } else {
                s.classList.remove('active');
            }
        });
        updateScores();
    });
});

// تعيين التقييم الافتراضي
if (document.querySelector('.rating-star[data-value="3"]')) {
    document.querySelector('.rating-star[data-value="3"]').classList.add('active');
}

// ============================================
// اختيارات الحضور
// ============================================
document.querySelectorAll('.attendance-option').forEach(option => {
    option.addEventListener('click', function() {
        let radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            document.querySelectorAll('.attendance-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            let delayDiv = document.getElementById('delayInput');
            if (delayDiv) {
                if (radio.value === 'late') {
                    delayDiv.style.display = 'block';
                } else {
                    delayDiv.style.display = 'none';
                }
            }
            updateScores();
        }
    });
});

// ============================================
// حقل التأخير
// ============================================
let attendanceDelay = document.getElementById('attendanceDelay');
if (attendanceDelay) {
    attendanceDelay.addEventListener('input', function() {
        updateScores();
    });
}

// ============================================
// حقول الآيات
// ============================================
let newAyahsInput = document.getElementById('new_ayahs');
if (newAyahsInput) {
    newAyahsInput.addEventListener('input', updateScores);
}

let recentAyahsInput = document.getElementById('recent_ayahs');
if (recentAyahsInput) {
    recentAyahsInput.addEventListener('input', updateScores);
}

let oldAyahsInput = document.getElementById('old_ayahs');
if (oldAyahsInput) {
    oldAyahsInput.addEventListener('input', updateScores);
}

// ============================================
// المؤقت للطلاب المتفرقين
// ============================================
<?php if ($scattered_info && $scattered_info['is_scattered'] && $scattered_info['is_active']): ?>
let timerInterval;
let timeRemaining = <?php echo $scattered_info['time_remaining']; ?>;
let totalDuration = <?php echo $scattered_info['duration'] * 60; ?>;

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(function() {
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            let timerDisplay = document.getElementById('timerDisplay');
            if (timerDisplay) timerDisplay.innerHTML = "00:00";
            return;
        }
        timeRemaining--;
        let minutes = Math.floor(timeRemaining / 60);
        let seconds = timeRemaining % 60;
        let timerDisplay = document.getElementById('timerDisplay');
        if (timerDisplay) {
            timerDisplay.innerHTML = String(minutes).padStart(2,'0') + ":" + String(seconds).padStart(2,'0');
        }
        let progress = ((totalDuration - timeRemaining) / totalDuration) * 100;
        let progressFill = document.getElementById('progressFill');
        if (progressFill) {
            progressFill.style.width = progress + "%";
        }
    }, 1000);
}
startTimer();
<?php endif; ?>

// ============================================
// تهيئة الصفحة - تحديث الدرجات عند التحميل
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    updateScores();
    console.log('✅ نظام التقييم المتقدم جاهز');
});
</script>

<?php require_once 'includes/footer.php'; ?>