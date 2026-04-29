<?php
// ============================================
// ملف: scattered_auto_timer.php
// نظام المؤقت التلقائي مع التقييم المتقدم للطلاب المتفرقين
// آخر تحديث: 2026-04-04
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'المؤقت الذكي - الطلاب المتفرقين';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;
$current_time = time();
$current_time_str = date('H:i:s', $current_time);
$current_date = date('Y-m-d');

// ============================================
// 1. جلب حلقات المتفرقين للمعلم
// ============================================
$rings = $pdo->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count,
           (SELECT COUNT(*) FROM scattered_daily_sessions 
            WHERE ring_id = r.id AND session_date = CURDATE() AND status IN ('pending', 'active')) as has_active_session
    FROM rings r
    WHERE r.teacher_id = ? AND r.is_scattered = 1
    ORDER BY r.scattered_start_time, r.name
");
$rings->execute([$teacher_id]);
$rings_list = $rings->fetchAll();

// ============================================
// 2. جلب طلاب الحلقة مع أوقاتهم (للمتفرقين فقط)
// ============================================
$students_with_times = [];
if ($ring_id > 0) {
    // جلب إعدادات الأوقات المحفوظة
    $saved_times = $_SESSION['scattered_times_' . $ring_id] ?? [];
    $saved_order = $_SESSION['scattered_order_' . $ring_id] ?? [];
    
    $students_stmt = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.parent_phone,
               (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_surahs
        FROM ring_students rs
        JOIN students s ON rs.student_id = s.id
        WHERE rs.ring_id = ?
        ORDER BY s.name
    ");
    $students_stmt->execute([$ring_id]);
    $students_list = $students_stmt->fetchAll();
    
    // إضافة الوقت المخصص لكل طالب
    foreach ($students_list as $student) {
        $students_with_times[] = [
            'id' => $student['id'],
            'name' => $student['name'],
            'level' => $student['level'],
            'parent_phone' => $student['parent_phone'],
            'memorized_surahs' => $student['memorized_surahs'],
            'duration' => $saved_times[$student['id']] ?? 15, // الوقت الافتراضي 15 دقيقة
            'order' => $saved_order[$student['id']] ?? 999
        ];
    }
    
    // ترتيب الطلاب حسب الترتيب المحفوظ
    usort($students_with_times, function($a, $b) {
        return $a['order'] - $b['order'];
    });
}

// ============================================
// 3. التحقق من بدء الجلسات تلقائياً
// ============================================

function createScatteredSessionSlots($pdo, $session_id, $ring_id, $students_with_times, $saved_times) {
    if (empty($students_with_times)) return false;
    
    // جلب وقت بداية ونهاية الحلقة
    $session = $pdo->prepare("SELECT start_time, end_time FROM scattered_daily_sessions WHERE id = ?");
    $session->execute([$session_id]);
    $session_data = $session->fetch();
    
    $start = strtotime($session_data['start_time']);
    $end = strtotime($session_data['end_time']);
    $total_minutes = ($end - $start) / 60;
    $students_count = count($students_with_times);
    
    if ($students_count == 0) return false;
    
    $has_custom_times = !empty($saved_times);
    $per_student = floor($total_minutes / $students_count);
    $remaining = $total_minutes - ($per_student * $students_count);
    
    $current_time = $start;
    $order = 1;
    
    foreach ($students_with_times as $index => $student) {
        if ($has_custom_times && isset($saved_times[$student['id']])) {
            $duration = $saved_times[$student['id']];
        } else {
            $duration = $per_student + ($index == 0 ? $remaining : 0);
        }
        
        $slot_end = $current_time + ($duration * 60);
        
        $stmt = $pdo->prepare("
            INSERT INTO scattered_session_slots 
            (session_id, student_id, slot_order, slot_start, slot_end, duration_minutes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $session_id, $student['id'], $order,
            date('H:i:s', $current_time), date('H:i:s', $slot_end), $duration
        ]);
        
        $current_time = $slot_end;
        $order++;
    }
    
    return true;
}

function startScatteredSession($pdo, $session_id) {
    // بدء أول طالب
    $first_slot = $pdo->prepare("
        SELECT id FROM scattered_session_slots 
        WHERE session_id = ? AND slot_order = 1
    ");
    $first_slot->execute([$session_id]);
    $first = $first_slot->fetch();
    
    if ($first) {
        $pdo->prepare("
            UPDATE scattered_session_slots 
            SET status = 'active', started_at = NOW()
            WHERE id = ?
        ")->execute([$first['id']]);
        
        $pdo->prepare("
            UPDATE scattered_daily_sessions SET status = 'active' WHERE id = ?
        ")->execute([$session_id]);
        
        return $first['id'];
    }
    return null;
}

function autoStartSessions($pdo, $teacher_id, $rings_list, $students_with_times) {
    $started_sessions = [];
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $saved_times = $_SESSION['scattered_times_' . ($rings_list[0]['id'] ?? 0)] ?? [];
    
    foreach ($rings_list as $ring) {
        $start_time = $ring['scattered_start_time'];
        $end_time = $ring['scattered_end_time'];
        
        // التحقق من أن الوقت الحالي بين وقت البداية والنهاية
        if ($current_time >= $start_time && $current_time <= $end_time) {
            
            // جلب الجلسة النشطة لهذا اليوم
            $stmt = $pdo->prepare("
                SELECT id, status FROM scattered_daily_sessions 
                WHERE ring_id = ? AND session_date = ? AND status IN ('pending', 'active')
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$ring['id'], $current_date]);
            $session = $stmt->fetch();
            
            if (!$session) {
                // إنشاء جلسة جديدة
                $stmt = $pdo->prepare("
                    INSERT INTO scattered_daily_sessions 
                    (ring_id, session_date, start_time, end_time, total_minutes, status)
                    VALUES (?, CURDATE(), ?, ?, ?, 'pending')
                ");
                $total_minutes = (strtotime($end_time) - strtotime($start_time)) / 60;
                $stmt->execute([$ring['id'], $start_time, $end_time, $total_minutes]);
                $session_id = $pdo->lastInsertId();
                
                // إنشاء فترات الطلاب
                createScatteredSessionSlots($pdo, $session_id, $ring['id'], $students_with_times, $saved_times);
                $started_sessions[] = $ring['id'];
                
            } elseif ($session['status'] == 'pending') {
                // بدء الجلسة تلقائياً
                startScatteredSession($pdo, $session['id']);
                $started_sessions[] = $ring['id'];
            }
        }
    }
    
    return $started_sessions;
}

// تشغيل الفحص التلقائي
$auto_started = autoStartSessions($pdo, $teacher_id, $rings_list, $students_with_times);

// ============================================
// 4. جلب الجلسة النشطة
// ============================================

$active_session = null;
$current_slot = null;
$next_slot = null;
$session_slots = [];
$time_remaining = 0;
$time_elapsed = 0;
$total_duration = 0;
$progress_percent = 0;

if ($ring_id > 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM scattered_daily_sessions 
        WHERE ring_id = ? AND session_date = CURDATE() AND status IN ('pending', 'active')
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$ring_id]);
    $active_session = $stmt->fetch();
    
    if ($active_session) {
        $slots = $pdo->prepare("
            SELECT ss.*, s.name, s.level, s.id as student_id,
                   CASE 
                       WHEN ss.status = 'active' THEN 1
                       WHEN ss.status = 'completed' THEN 2
                       ELSE 3
                   END as sort_order
            FROM scattered_session_slots ss
            JOIN students s ON ss.student_id = s.id
            WHERE ss.session_id = ?
            ORDER BY sort_order, ss.slot_order
        ");
        $slots->execute([$active_session['id']]);
        $session_slots = $slots->fetchAll();
        
        foreach ($session_slots as $slot) {
            if ($slot['status'] == 'active') {
                $current_slot = $slot;
                break;
            }
        }
        
        // حساب الوقت المتبقي للجلسة الحالية
        if ($current_slot && $current_slot['started_at']) {
            $start = strtotime($current_slot['started_at']);
            $now = time();
            $time_elapsed = $now - $start;
            $total_duration = $current_slot['duration_minutes'] * 60;
            $time_remaining = max(0, $total_duration - $time_elapsed);
            $progress_percent = min(100, ($time_elapsed / $total_duration) * 100);
        }
        
        // جلب الطالب التالي
        if ($current_slot) {
            $next = $pdo->prepare("
                SELECT ss.*, s.name
                FROM scattered_session_slots ss
                JOIN students s ON ss.student_id = s.id
                WHERE ss.session_id = ? AND ss.slot_order > ? AND ss.status = 'pending'
                ORDER BY ss.slot_order ASC LIMIT 1
            ");
            $next->execute([$active_session['id'], $current_slot['slot_order']]);
            $next_slot = $next->fetch();
        }
    }
}

// ============================================
// 5. معالجة الإجراءات (AJAX)
// ============================================

if (isset($_GET['ajax_check'])) {
    header('Content-Type: application/json');
    
    $response = [
        'has_active_session' => false,
        'current_student' => null,
        'time_remaining' => 0,
        'needs_refresh' => false
    ];
    
    // فحص الجلسات النشطة
    foreach ($rings_list as $ring) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.status, ss.id as slot_id, ss.student_id, ss.duration_minutes,
                   ss.started_at, st.name as student_name
            FROM scattered_daily_sessions s
            LEFT JOIN scattered_session_slots ss ON s.id = ss.session_id AND ss.status = 'active'
            LEFT JOIN students st ON ss.student_id = st.id
            WHERE s.ring_id = ? AND s.session_date = CURDATE() AND s.status = 'active'
            ORDER BY s.id DESC LIMIT 1
        ");
        $stmt->execute([$ring['id']]);
        $active = $stmt->fetch();
        
        if ($active) {
            $response['has_active_session'] = true;
            if ($active['started_at']) {
                $elapsed = time() - strtotime($active['started_at']);
                $remaining = max(0, ($active['duration_minutes'] * 60) - $elapsed);
                $response['time_remaining'] = $remaining;
                $response['current_student'] = [
                    'id' => $active['student_id'],
                    'name' => $active['student_name'],
                    'duration' => $active['duration_minutes']
                ];
            }
            break;
        }
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// 6. معالجة حفظ التقييم وإنهاء الجلسة
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation'])) {
    $slot_id = (int)$_POST['slot_id'];
    $student_id = (int)$_POST['student_id'];
    
    // بيانات التقييم المتقدم
    $ayah_mistakes = (int)$_POST['ayah_mistakes'];
    $haraka_mistakes = (int)$_POST['haraka_mistakes'];
    $tajweed_mistakes = (int)$_POST['tajweed_mistakes'];
    $hesitation_count = (int)$_POST['hesitation_count'];
    $new_memorized_ayahs = (int)$_POST['new_memorized_ayahs'];
    $recent_reviewed_ayahs = (int)$_POST['recent_reviewed_ayahs'];
    $old_reviewed_ayahs = (int)$_POST['old_reviewed_ayahs'];
    $behavior_rating = (int)$_POST['behavior_rating'];
    $notes = trim($_POST['notes'] ?? '');
    $general_notes = trim($_POST['general_notes'] ?? '');
    
    // حساب الدرجات
    $new_score = calculateMemorizationScore($ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count, $new_memorized_ayahs);
    $recent_score = calculateReviewScore($recent_reviewed_ayahs, $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count);
    $old_score = calculateReviewScore($old_reviewed_ayahs, $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count);
    $behavior_score = ($behavior_rating / 5) * 100;
    
    // الوزن: حضور 30% (للمتفرقين يحسب كحاضر تلقائياً)، حفظ 20%، مراجعة قريبة 15%، مراجعة بعيدة 15%، سلوك 20%
    $attendance_score = 100; // في حلقة المتفرقين، الطالب حاضر تلقائياً
    $total_score = round(($attendance_score * 0.30) + ($new_score * 0.20) + ($recent_score * 0.15) + ($old_score * 0.15) + ($behavior_score * 0.20), 2);
    
    // تحديد التقدير
    if ($total_score >= 95) $grade = 'ممتاز';
    elseif ($total_score >= 85) $grade = 'جيد جداً';
    elseif ($total_score >= 75) $grade = 'جيد';
    elseif ($total_score >= 60) $grade = 'مقبول';
    else $grade = 'ضعيف';
    
    try {
        $pdo->beginTransaction();
        
        // 1. إنهاء الجلسة الحالية
        $pdo->prepare("
            UPDATE scattered_session_slots 
            SET status = 'completed', ended_at = NOW(),
                actual_duration = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                evaluation_score = ?, evaluation_notes = ?
            WHERE id = ?
        ")->execute([$behavior_rating, $notes, $slot_id]);
        
        // 2. حفظ التقييم المتقدم في جدول student_daily_evaluations
        $today = date('Y-m-d');
        $check = $pdo->prepare("SELECT id FROM student_daily_evaluations WHERE student_id = ? AND evaluation_date = ?");
        $check->execute([$student_id, $today]);
        
        $teacher_id = $_SESSION['user_id'];
        
        if ($check->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE student_daily_evaluations SET
                    attendance_status = 'present',
                    attendance_score = ?,
                    new_ayah_mistakes = ?,
                    new_haraka_mistakes = ?,
                    new_tajweed_mistakes = ?,
                    new_hesitation_count = ?,
                    new_memorized_ayahs = ?,
                    new_memorization_score = ?,
                    recent_ayah_mistakes = ?,
                    recent_haraka_mistakes = ?,
                    recent_tajweed_mistakes = ?,
                    recent_hesitation_count = ?,
                    recent_reviewed_ayahs = ?,
                    recent_review_score = ?,
                    old_ayah_mistakes = ?,
                    old_haraka_mistakes = ?,
                    old_tajweed_mistakes = ?,
                    old_hesitation_count = ?,
                    old_reviewed_ayahs = ?,
                    old_review_score = ?,
                    behavior_rating = ?,
                    behavior_score = ?,
                    general_notes = CONCAT(IFNULL(general_notes, ''), '\n', ?),
                    total_score = ?,
                    grade = ?
                WHERE student_id = ? AND evaluation_date = ?
            ");
            $stmt->execute([
                $attendance_score,
                $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count, $new_memorized_ayahs, $new_score,
                $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count, $recent_reviewed_ayahs, $recent_score,
                $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count, $old_reviewed_ayahs, $old_score,
                $behavior_rating, $behavior_score,
                "تقييم حلقة متفرقين: " . $general_notes,
                $total_score, $grade,
                $student_id, $today
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO student_daily_evaluations (
                    student_id, teacher_id, evaluation_date, attendance_status, attendance_score,
                    new_ayah_mistakes, new_haraka_mistakes, new_tajweed_mistakes, new_hesitation_count,
                    new_memorized_ayahs, new_memorization_score,
                    recent_ayah_mistakes, recent_haraka_mistakes, recent_tajweed_mistakes,
                    recent_hesitation_count, recent_reviewed_ayahs, recent_review_score,
                    old_ayah_mistakes, old_haraka_mistakes, old_tajweed_mistakes,
                    old_hesitation_count, old_reviewed_ayahs, old_review_score,
                    behavior_rating, behavior_score, general_notes, total_score, grade
                ) VALUES (
                    ?, ?, ?, 'present', ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $student_id, $teacher_id, $today,
                $attendance_score,
                $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count,
                $new_memorized_ayahs, $new_score,
                $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count,
                $recent_reviewed_ayahs, $recent_score,
                $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count,
                $old_reviewed_ayahs, $old_score,
                $behavior_rating, $behavior_score,
                "تقييم حلقة متفرقين: " . $general_notes,
                $total_score, $grade
            ]);
        }
        
        // 3. بدء الطالب التالي
        $slot_info = $pdo->prepare("SELECT session_id FROM scattered_session_slots WHERE id = ?");
        $slot_info->execute([$slot_id]);
        $session_id = $slot_info->fetchColumn();
        
        $next_slot = $pdo->prepare("
            SELECT id FROM scattered_session_slots 
            WHERE session_id = ? AND status = 'pending'
            ORDER BY slot_order ASC LIMIT 1
        ");
        $next_slot->execute([$session_id]);
        $next = $next_slot->fetch();
        
        if ($next) {
            $pdo->prepare("
                UPDATE scattered_session_slots 
                SET status = 'active', started_at = NOW()
                WHERE id = ?
            ")->execute([$next['id']]);
        } else {
            $pdo->prepare("
                UPDATE scattered_daily_sessions SET status = 'completed' WHERE id = ?
            ")->execute([$session_id]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "✅ تم حفظ التقييم وإكمال جلسة الطالب";
        header("Location: scattered_auto_timer.php?ring_id=$ring_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// ============================================
// 7. دوال حساب الدرجات المساعدة
// ============================================

function calculateMemorizationScore($ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count, $memorized_ayahs) {
    $base = 100;
    $deduction = ($ayah_mistakes * 3) + ($haraka_mistakes * 1) + ($tajweed_mistakes * 1) + ($hesitation_count * 1);
    $score = max(0, $base - $deduction);
    
    // مكافأة على عدد الآيات (كل 10 آيات = +1 نقطة بحد أقصى 5)
    $bonus = min(5, floor($memorized_ayahs / 10));
    return min(100, round($score + $bonus));
}

function calculateReviewScore($reviewed_ayahs, $ayah_mistakes, $haraka_mistakes, $tajweed_mistakes, $hesitation_count) {
    $base = 100;
    $deduction = ($ayah_mistakes * 2) + ($haraka_mistakes * 0.5) + ($tajweed_mistakes * 0.5) + ($hesitation_count * 0.5);
    $score = max(0, $base - $deduction);
    
    // مكافأة على عدد الآيات المراجعة
    $bonus = min(5, floor($reviewed_ayahs / 20));
    return min(100, round($score + $bonus));
}

// ============================================
// 8. إعادة تعيين الجلسة
// ============================================

if (isset($_GET['reset_session']) && $ring_id > 0) {
    $pdo->prepare("DELETE FROM scattered_daily_sessions WHERE ring_id = ? AND session_date = CURDATE()")->execute([$ring_id]);
    $pdo->prepare("DELETE FROM scattered_session_slots WHERE session_id IN (SELECT id FROM scattered_daily_sessions WHERE ring_id = ?)")->execute([$ring_id]);
    header("Location: scattered_auto_timer.php?ring_id=$ring_id");
    exit;
}

$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

// جلب إعدادات المؤقت للمعلم
$warning_minutes = $_SESSION['scattered_warning_' . $ring_id] ?? 2;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>المؤقت الذكي - الطلاب المتفرقين</title>
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

        .timer-page {
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

        .current-time {
            background: rgba(255,255,255,0.15);
            padding: 10px 25px;
            border-radius: 50px;
            font-family: monospace;
            font-size: 1.2rem;
        }

        /* ===== حلقات المتفرقين ===== */
        .rings-scroll {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 5px 20px;
            margin-bottom: 25px;
            scrollbar-width: thin;
        }

        .ring-card-select {
            min-width: 220px;
            background: white;
            border-radius: 20px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .ring-card-select:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .ring-card-select.active {
            border-color: var(--secondary);
            background: linear-gradient(135deg, #fff8e7, #fff3d6);
        }

        .ring-name {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .ring-time {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 8px;
        }

        .ring-status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .ring-status.active { background: var(--success); color: white; }
        .ring-status.waiting { background: var(--warning); color: #212529; }
        .ring-status.completed { background: #6c757d; color: white; }

        /* ===== المؤقت الرئيسي ===== */
        .timer-main-card {
            background: linear-gradient(145deg, #1a1a2e, #16213e);
            border-radius: 40px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .timer-main-card::before {
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

        .current-student-name {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .current-student-info {
            color: rgba(255,255,255,0.7);
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }

        .timer-display {
            font-size: 6rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 10px;
            color: var(--secondary);
            margin: 20px 0;
            position: relative;
            z-index: 2;
            text-shadow: 0 0 20px rgba(201,169,107,0.5);
        }

        .timer-warning {
            color: var(--warning);
            animation: blink 1s infinite;
        }

        .timer-danger {
            color: var(--danger);
            animation: blink 0.5s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .progress-container {
            width: 80%;
            margin: 20px auto;
            position: relative;
            z-index: 2;
        }

        .progress-bar-bg {
            height: 12px;
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

        .next-student {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 30px;
            position: relative;
            z-index: 2;
        }

        /* ===== نموذج التقييم المتقدم ===== */
        .evaluation-card {
            background: white;
            border-radius: 30px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .evaluation-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary);
        }

        .evaluation-header i {
            font-size: 1.5rem;
            color: var(--secondary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }

        .form-group label i {
            color: var(--secondary);
            margin-left: 5px;
        }

        .mistakes-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .mistake-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .mistake-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .mistake-deduction {
            font-size: 0.7rem;
            color: #666;
            margin-bottom: 10px;
        }

        .counter-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .counter-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .counter-btn.minus { background: var(--danger); color: white; }
        .counter-btn.plus { background: var(--success); color: white; }
        .counter-value { font-size: 1.3rem; font-weight: 700; min-width: 50px; text-align: center; color: var(--primary); }

        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 15px 0;
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

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            font-size: 1rem;
            resize: vertical;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .btn-success { background: linear-gradient(135deg, var(--success), #20c997); color: white; }
        .btn-warning { background: var(--warning); color: #212529; }
        .btn-large { padding: 15px 35px; font-size: 1.1rem; width: 100%; }

        /* ===== قائمة الطلاب مع أوقاتهم ===== */
        .students-list-card {
            background: white;
            border-radius: 30px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary);
            color: var(--primary);
        }

        .students-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .student-timeline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 20px;
            transition: all 0.3s;
            border-right: 4px solid;
        }

        .student-timeline-item.active {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-right-color: var(--success);
        }

        .student-timeline-item.completed {
            opacity: 0.7;
            background: #f8f9fa;
            border-right-color: #6c757d;
        }

        .timeline-order {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .student-timeline-item.active .timeline-order {
            background: var(--success);
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .timeline-info { flex: 1; }
        .timeline-name { font-weight: 700; color: var(--primary); font-size: 1.1rem; }
        .timeline-time { font-size: 0.8rem; color: #666; margin-top: 3px; }
        .timeline-duration { background: var(--secondary); color: white; padding: 5px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; }
        .timeline-status { font-size: 0.75rem; padding: 4px 12px; border-radius: 30px; }
        .status-active { background: var(--success); color: white; }
        .status-completed { background: #6c757d; color: white; }
        .status-pending { background: #e9ecef; color: #6c757d; }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 30px;
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

        @media (max-width: 768px) {
            .timer-page { padding: 15px; }
            .timer-display { font-size: 3rem; letter-spacing: 5px; }
            .current-student-name { font-size: 1.5rem; }
            .rings-scroll { padding-bottom: 10px; }
            .mistakes-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 480px) {
            .mistakes-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="timer-page">
    <div class="page-header">
        <h1><i class="fas fa-hourglass-half"></i> المؤقت الذكي - الطلاب المتفرقين</h1>
        <div class="current-time" id="currentTimeDisplay"></div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- حلقات المتفرقين -->
    <?php if (!empty($rings_list)): ?>
    <div class="rings-scroll" id="ringsScroll">
        <?php foreach ($rings_list as $ring): 
            $has_active = $ring['has_active_session'] > 0;
            $is_current = $ring_id == $ring['id'];
        ?>
            <div class="ring-card-select <?php echo $is_current ? 'active' : ''; ?>" 
                 data-ring-id="<?php echo $ring['id']; ?>"
                 onclick="window.location.href='?ring_id=<?php echo $ring['id']; ?>'">
                <div class="ring-name">
                    <i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?>
                </div>
                <div class="ring-time">
                    <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($ring['scattered_start_time'])); ?> - 
                    <?php echo date('h:i A', strtotime($ring['scattered_end_time'])); ?>
                </div>
                <div class="ring-status <?php echo $has_active ? 'active' : 'waiting'; ?>">
                    <?php if ($has_active): ?>
                        <i class="fas fa-play-circle"></i> جلسة نشطة
                    <?php else: ?>
                        <i class="fas fa-clock"></i> في انتظار الوقت
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($ring_id == 0 && !empty($rings_list)): ?>
        <div class="empty-state">
            <i class="fas fa-hand-point-left"></i>
            <h3>اختر حلقة من القائمة</h3>
            <p>يرجى اختيار حلقة متفرقين من القائمة أعلاه</p>
        </div>
    <?php elseif ($ring_id == 0 && empty($rings_list)): ?>
        <div class="empty-state">
            <i class="fas fa-ring"></i>
            <h3>لا توجد حلقات متفرقين</h3>
            <p>قم بإنشاء حلقة وتفعيلها كحلقة متفرقين من إعدادات الحلقة</p>
            <a href="rings.php" class="btn btn-primary" style="margin-top: 15px;">إدارة الحلقات</a>
        </div>
    <?php elseif ($current_slot): ?>
        <!-- جلسة نشطة مع طالب حالي -->
        <div class="timer-main-card">
            <div class="current-student-name">
                <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($current_slot['name']); ?>
            </div>
            <div class="current-student-info">
                <i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($current_slot['level'] ?? 'مبتدئ'); ?>
                | الوقت المخصص: <?php echo $current_slot['duration_minutes']; ?> دقيقة
            </div>
            <div class="timer-display" id="timerDisplay">
                <?php echo sprintf("%02d:%02d", floor($time_remaining / 60), $time_remaining % 60); ?>
            </div>
            <div class="progress-container">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progressFill" style="width: <?php echo $progress_percent; ?>%;"></div>
                </div>
            </div>
            <?php if ($next_slot): ?>
            <div class="next-student">
                <i class="fas fa-arrow-left"></i> التالي: <?php echo htmlspecialchars($next_slot['name']); ?> 
                (بعد <?php echo $next_slot['duration_minutes']; ?> دقيقة)
            </div>
            <?php endif; ?>
        </div>

        <!-- نموذج التقييم المتقدم -->
        <div class="evaluation-card">
            <div class="evaluation-header">
                <i class="fas fa-star"></i>
                <h3>تقييم الطالب المتقدم: <?php echo htmlspecialchars($current_slot['name']); ?></h3>
            </div>
            
            <form method="post" id="evaluationForm">
                <input type="hidden" name="slot_id" value="<?php echo $current_slot['id']; ?>">
                <input type="hidden" name="student_id" value="<?php echo $current_slot['student_id']; ?>">
                <input type="hidden" name="save_evaluation" value="1">
                
                <!-- أخطاء الحفظ الجديد -->
                <div class="form-group">
                    <label><i class="fas fa-book-open"></i> الحفظ الجديد</label>
                    <div class="mistakes-grid">
                        <div class="mistake-card">
                            <div class="mistake-title">خطأ في الآية</div>
                            <div class="mistake-deduction">-3 نقاط</div>
                            <div class="counter-container">
                                <button type="button" class="counter-btn minus" onclick="updateCounter('ayah', -1)">−</button>
                                <span class="counter-value" id="ayahCounter">0</span>
                                <button type="button" class="counter-btn plus" onclick="updateCounter('ayah', 1)">+</button>
                            </div>
                        </div>
                        <div class="mistake-card">
                            <div class="mistake-title">خطأ في التشكيل</div>
                            <div class="mistake-deduction">-1 نقطة</div>
                            <div class="counter-container">
                                <button type="button" class="counter-btn minus" onclick="updateCounter('haraka', -1)">−</button>
                                <span class="counter-value" id="harakaCounter">0</span>
                                <button type="button" class="counter-btn plus" onclick="updateCounter('haraka', 1)">+</button>
                            </div>
                        </div>
                        <div class="mistake-card">
                            <div class="mistake-title">خطأ في التجويد</div>
                            <div class="mistake-deduction">-1 نقطة</div>
                            <div class="counter-container">
                                <button type="button" class="counter-btn minus" onclick="updateCounter('tajweed', -1)">−</button>
                                <span class="counter-value" id="tajweedCounter">0</span>
                                <button type="button" class="counter-btn plus" onclick="updateCounter('tajweed', 1)">+</button>
                            </div>
                        </div>
                        <div class="mistake-card">
                            <div class="mistake-title">تردد / شك</div>
                            <div class="mistake-deduction">-1 نقطة</div>
                            <div class="counter-container">
                                <button type="button" class="counter-btn minus" onclick="updateCounter('hesitation', -1)">−</button>
                                <span class="counter-value" id="hesitationCounter">0</span>
                                <button type="button" class="counter-btn plus" onclick="updateCounter('hesitation', 1)">+</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>عدد الآيات المحفوظة</label>
                        <input type="number" name="new_memorized_ayahs" id="newAyahs" class="form-control" value="0" min="0" onchange="updateScores()">
                    </div>
                </div>
                
                <!-- المراجعة -->
                <div class="form-group">
                    <label><i class="fas fa-history"></i> المراجعة (قريبة وبعيدة)</label>
                    <div class="form-group">
                        <label>عدد آيات المراجعة القريبة</label>
                        <input type="number" name="recent_reviewed_ayahs" id="recentAyahs" class="form-control" value="0" min="0" onchange="updateScores()">
                    </div>
                    <div class="form-group">
                        <label>عدد آيات المراجعة البعيدة</label>
                        <input type="number" name="old_reviewed_ayahs" id="oldAyahs" class="form-control" value="0" min="0" onchange="updateScores()">
                    </div>
                </div>
                
                <!-- حقول مخفية للأخطاء -->
                <input type="hidden" name="ayah_mistakes" id="ayahMistakes" value="0">
                <input type="hidden" name="haraka_mistakes" id="harakaMistakes" value="0">
                <input type="hidden" name="tajweed_mistakes" id="tajweedMistakes" value="0">
                <input type="hidden" name="hesitation_count" id="hesitationMistakes" value="0">
                
                <!-- السلوك -->
                <div class="form-group">
                    <label><i class="fas fa-smile"></i> تقييم السلوك</label>
                    <div class="rating-stars" id="ratingStars">
                        <span class="rating-star" data-value="1">★</span>
                        <span class="rating-star" data-value="2">★</span>
                        <span class="rating-star" data-value="3">★</span>
                        <span class="rating-star" data-value="4">★</span>
                        <span class="rating-star" data-value="5">★</span>
                    </div>
                    <input type="hidden" name="behavior_rating" id="behaviorValue" value="3">
                </div>
                
                <!-- ملاحظات -->
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> ملاحظات الجلسة</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="أي ملاحظات عن أداء الطالب..."></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> ملاحظات عامة</label>
                    <textarea name="general_notes" class="form-control" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                </div>
                
                <!-- معاينة الدرجة النهائية -->
                <div class="evaluation-header" style="margin-top: 20px;">
                    <i class="fas fa-chart-line"></i>
                    <h3>الدرجة النهائية</h3>
                </div>
                <div class="final-score-preview" id="finalScorePreview" style="background: #e8f5e9; padding: 15px; border-radius: 15px; text-align: center;">
                    <span style="font-size: 1.5rem; font-weight: bold;" id="finalScore">0</span>%
                    <span id="finalGrade" style="display: block; font-size: 1rem;"></span>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large" id="completeBtn" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> حفظ التقييم وإكمال الجلسة
                </button>
            </form>
        </div>

        <!-- قائمة الطلاب مع أوقاتهم -->
        <?php if (!empty($session_slots)): ?>
        <div class="students-list-card">
            <div class="section-title">
                <i class="fas fa-list-ol"></i>
                <h3>ترتيب الطلاب اليوم مع أوقاتهم</h3>
            </div>
            <div class="students-timeline">
                <?php foreach ($session_slots as $slot): 
                    $status_class = '';
                    $status_text = '';
                    if ($slot['status'] == 'active') {
                        $status_class = 'active';
                        $status_text = 'جاري الآن';
                    } elseif ($slot['status'] == 'completed') {
                        $status_class = 'completed';
                        $status_text = 'مكتمل';
                    } else {
                        $status_class = 'pending';
                        $status_text = 'قادم';
                    }
                ?>
                    <div class="student-timeline-item <?php echo $status_class; ?>">
                        <div class="timeline-order"><?php echo $slot['slot_order']; ?></div>
                        <div class="timeline-info">
                            <div class="timeline-name"><?php echo htmlspecialchars($slot['name']); ?></div>
                            <div class="timeline-time">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('h:i A', strtotime($slot['slot_start'])); ?> - 
                                <?php echo date('h:i A', strtotime($slot['slot_end'])); ?>
                                <span style="margin-right: 10px; background: var(--secondary); padding: 2px 8px; border-radius: 20px; color: white;">
                                    <?php echo $slot['duration_minutes']; ?> دقيقة
                                </span>
                            </div>
                        </div>
                        <div class="timeline-status status-<?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_class == 'active' ? 'fa-play' : ($status_class == 'completed' ? 'fa-check' : 'fa-clock'); ?>"></i> <?php echo $status_text; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($active_session && $active_session['status'] == 'pending'): ?>
        <!-- جلسة في انتظار الوقت -->
        <div class="timer-main-card">
            <div class="current-student-name">
                <i class="fas fa-clock"></i> في انتظار وقت الجلسة
            </div>
            <div class="current-student-info">
                وقت بدء الجلسة: <?php echo date('h:i A', strtotime($active_session['start_time'])); ?>
            </div>
            <div class="timer-display" id="waitingTimer">00:00</div>
            <div class="next-student">
                سيبدأ المؤقت تلقائياً عند بدء وقت الجلسة
            </div>
        </div>

        <!-- قائمة الطلاب مع أوقاتهم (في انتظار) -->
        <?php if (!empty($session_slots)): ?>
        <div class="students-list-card">
            <div class="section-title">
                <i class="fas fa-list-ol"></i>
                <h3>ترتيب الطلاب اليوم مع أوقاتهم (قيد الانتظار)</h3>
            </div>
            <div class="students-timeline">
                <?php foreach ($session_slots as $slot): ?>
                    <div class="student-timeline-item pending">
                        <div class="timeline-order"><?php echo $slot['slot_order']; ?></div>
                        <div class="timeline-info">
                            <div class="timeline-name"><?php echo htmlspecialchars($slot['name']); ?></div>
                            <div class="timeline-time">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('h:i A', strtotime($slot['slot_start'])); ?> - 
                                <?php echo date('h:i A', strtotime($slot['slot_end'])); ?>
                                <span style="margin-right: 10px; background: var(--secondary); padding: 2px 8px; border-radius: 20px; color: white;">
                                    <?php echo $slot['duration_minutes']; ?> دقيقة
                                </span>
                            </div>
                        </div>
                        <div class="timeline-status status-pending"><i class="fas fa-clock"></i> قادم</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($ring_id > 0): ?>
        <div class="empty-state">
            <i class="fas fa-clock"></i>
            <h3>لا توجد جلسة نشطة</h3>
            <p>سيتم إنشاء الجلسة تلقائياً عند بدء وقت الحلقة</p>
            <?php 
            $ring = null;
            foreach ($rings_list as $r) {
                if ($r['id'] == $ring_id) {
                    $ring = $r;
                    break;
                }
            }
            if ($ring):
            ?>
            <div class="ring-time-info" style="margin-top: 15px;">
                <strong>وقت الحلقة:</strong> 
                <?php echo date('h:i A', strtotime($ring['scattered_start_time'])); ?> - 
                <?php echo date('h:i A', strtotime($ring['scattered_end_time'])); ?>
            </div>
            <?php endif; ?>
            <a href="?ring_id=<?php echo $ring_id; ?>&reset_session=1" class="btn btn-warning" style="margin-top: 15px;" onclick="return confirm('إعادة تعيين الجلسة؟')">
                <i class="fas fa-sync-alt"></i> إعادة تعيين
            </a>
        </div>
        
        <!-- عرض قائمة الطلاب مع أوقاتهم حتى بدون جلسة -->
        <?php if (!empty($students_with_times)): ?>
        <div class="students-list-card">
            <div class="section-title">
                <i class="fas fa-list-ol"></i>
                <h3>طلاب الحلقة مع أوقاتهم المخصصة</h3>
            </div>
            <div class="students-timeline">
                <?php foreach ($students_with_times as $index => $student): ?>
                    <div class="student-timeline-item pending">
                        <div class="timeline-order"><?php echo $index + 1; ?></div>
                        <div class="timeline-info">
                            <div class="timeline-name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="timeline-time">
                                <i class="fas fa-hourglass-half"></i> 
                                الوقت المخصص: <strong><?php echo $student['duration']; ?> دقيقة</strong>
                            </div>
                        </div>
                        <div class="timeline-duration"><?php echo $student['duration']; ?> دقيقة</div>
                        <div class="timeline-status status-pending"><i class="fas fa-clock"></i> في انتظار الجلسة</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// ============================================
// تحديث الوقت الحالي
// ============================================
function updateCurrentTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('currentTimeDisplay').innerHTML = '<i class="fas fa-clock"></i> ' + timeStr;
}
updateCurrentTime();
setInterval(updateCurrentTime, 1000);

// ============================================
// المؤقت
// ============================================
let timerInterval;
let timeRemaining = <?php echo $time_remaining; ?>;
let totalDuration = <?php echo $total_duration; ?>;

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    
    timerInterval = setInterval(function() {
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            document.getElementById('timerDisplay').innerHTML = "00:00";
            document.getElementById('progressFill').style.width = "100%";
            
            // تنبيه انتهاء الوقت
            const audio = new Audio('data:audio/wav;base64,U3RlYWx0aCwgVGhpcyBpcyBhIHRlc3Qu');
            audio.play().catch(e => console.log('لا يمكن تشغيل الصوت'));
            
            alert('⏰ انتهى وقت الطالب!');
            return;
        }
        
        timeRemaining--;
        let minutes = Math.floor(timeRemaining / 60);
        let seconds = timeRemaining % 60;
        document.getElementById('timerDisplay').innerHTML = 
            String(minutes).padStart(2, '0') + ":" + String(seconds).padStart(2, '0');
        
        let newProgress = ((totalDuration - timeRemaining) / totalDuration) * 100;
        document.getElementById('progressFill').style.width = Math.min(100, newProgress) + "%";
        
        // تنبيه عند قرب انتهاء الوقت
        if (timeRemaining === 60) {
            const audio = new Audio('data:audio/wav;base64,U3RlYWx0aCwgVGhpcyBpcyBhIHRlc3Qu');
            audio.play().catch(e => console.log('لا يمكن تشغيل الصوت'));
        }
        
        // تغيير اللون عند قرب انتهاء الوقت
        let fill = document.getElementById('progressFill');
        let timerDisplay = document.getElementById('timerDisplay');
        if (timeRemaining <= 60) {
            fill.classList.add('danger');
            timerDisplay.classList.add('timer-danger');
        } else if (timeRemaining <= 120) {
            fill.classList.add('warning');
            timerDisplay.classList.add('timer-warning');
        } else {
            fill.classList.remove('warning', 'danger');
            timerDisplay.classList.remove('timer-warning', 'timer-danger');
        }
    }, 1000);
}

// بدء المؤقت إذا كان هناك وقت متبقي
<?php if ($current_slot && $time_remaining > 0): ?>
startTimer();
<?php endif; ?>

// ============================================
// عدادات الأخطاء
// ============================================
let mistakes = {
    ayah: 0,
    haraka: 0,
    tajweed: 0,
    hesitation: 0
};

function updateCounter(type, change) {
    let newVal = mistakes[type] + change;
    if (newVal < 0) return;
    mistakes[type] = newVal;
    
    // تحديث العرض
    document.getElementById(type + 'Counter').innerText = newVal;
    document.getElementById(type + 'Mistakes').value = newVal;
    
    updateScores();
}

function updateScores() {
    let ayahMistakes = mistakes.ayah;
    let harakaMistakes = mistakes.haraka;
    let tajweedMistakes = mistakes.tajweed;
    let hesitationCount = mistakes.hesitation;
    let newAyahs = parseInt(document.getElementById('newAyahs')?.value) || 0;
    let recentAyahs = parseInt(document.getElementById('recentAyahs')?.value) || 0;
    let oldAyahs = parseInt(document.getElementById('oldAyahs')?.value) || 0;
    let behaviorRating = parseInt(document.getElementById('behaviorValue')?.value) || 3;
    
    // حساب درجة الحفظ الجديد
    let newScore = 100 - ((ayahMistakes * 3) + (harakaMistakes * 1) + (tajweedMistakes * 1) + (hesitationCount * 1));
    newScore = Math.max(0, newScore);
    let newBonus = Math.min(5, Math.floor(newAyahs / 10));
    newScore = Math.min(100, newScore + newBonus);
    
    // حساب درجة المراجعة (نفس الأخطاء للتبسيط)
    let reviewScore = 100 - ((ayahMistakes * 2) + (harakaMistakes * 0.5) + (tajweedMistakes * 0.5) + (hesitationCount * 0.5));
    reviewScore = Math.max(0, reviewScore);
    let reviewBonus = Math.min(5, Math.floor((recentAyahs + oldAyahs) / 20));
    reviewScore = Math.min(100, reviewScore + reviewBonus);
    
    // درجة السلوك
    let behaviorScore = (behaviorRating / 5) * 100;
    
    // درجة الحضور (في حلقة المتفرقين، الطالب حاضر تلقائياً)
    let attendanceScore = 100;
    
    // الدرجة النهائية حسب الأوزان
    let totalScore = (attendanceScore * 0.30) + (newScore * 0.20) + (reviewScore * 0.30) + (behaviorScore * 0.20);
    totalScore = Math.round(totalScore);
    
    // التقدير
    let grade = '';
    if (totalScore >= 95) grade = 'ممتاز 🌟🌟🌟';
    else if (totalScore >= 85) grade = 'جيد جداً 🌟🌟';
    else if (totalScore >= 75) grade = 'جيد 🌟';
    else if (totalScore >= 60) grade = 'مقبول';
    else grade = 'ضعيف ⚠️';
    
    document.getElementById('finalScore').innerText = totalScore;
    document.getElementById('finalGrade').innerHTML = grade;
}

// ============================================
// تقييم النجوم
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
document.querySelector('.rating-star[data-value="3"]')?.classList.add('active');

// ============================================
// الفحص التلقائي للجلسات الجديدة (AJAX)
// ============================================
let lastRingId = <?php echo $ring_id; ?>;

function checkForNewSessions() {
    fetch('scattered_auto_timer.php?ajax_check=1')
        .then(response => response.json())
        .then(data => {
            if (data.needs_refresh) {
                location.reload();
            }
            
            if (data.has_active_session && !<?php echo $current_slot ? 'true' : 'false'; ?>) {
                location.reload();
            }
        })
        .catch(error => console.log('AJAX error:', error));
}

// فحص كل 10 ثوانٍ
setInterval(checkForNewSessions, 10000);

// ============================================
// تأكيد إنهاء الجلسة
// ============================================
document.getElementById('completeBtn')?.addEventListener('click', function(e) {
    if (!confirm('هل أنت متأكد من إنهاء جلسة هذا الطالب وتقييمه؟')) {
        e.preventDefault();
    }
});

// تحديث الدرجات عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updateScores();
});

console.log('✅ نظام المؤقت الذكي مع التقييم المتقدم جاهز');
console.log('الوقت المتبقي: <?php echo $time_remaining; ?> ثانية');
</script>

<?php require_once 'includes/footer.php'; ?>