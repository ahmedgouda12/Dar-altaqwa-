<?php
// ============================================
// ملف: cron_auto_attendance.php
// نظام الغياب التلقائي - يعمل مباشرة عند الزيارة
// لا يحتاج إلى ?force=1 أو أي ضغط على أزرار
// آخر تحديث: 2026-04-16
// ============================================

// ============================================
// إزالة أي شرط يمنع التشغيل المباشر
// الملف يعمل فور زيارته بدون أي معاملات
// ============================================

require_once 'config.php';
require_once 'functions.php';

// تعطيل الوقت المحدد للتنفيذ الطويل
set_time_limit(0);
ini_set('memory_limit', '256M');

// ============================================
// إعدادات التسجيل
// ============================================
$log_file = 'attendance_cron.log';
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$current_time = date('H:i:s');

// بدء التسجيل
$log = date('Y-m-d H:i:s') . " =========================================\n";
$log .= date('Y-m-d H:i:s') . " - بدء تشغيل نظام الغياب التلقائي\n";
$log .= date('Y-m-d H:i:s') . " - التاريخ الحالي: $today\n";
$log .= date('Y-m-d H:i:s') . " - تاريخ الأمس: $yesterday\n";
$log .= date('Y-m-d H:i:s') . " - الوقت الحالي: $current_time\n";
file_put_contents($log_file, $log, FILE_APPEND);

try {
    // ============================================
    // 1. تسجيل غياب لليوم السابق (إذا لم يسجل)
    // ============================================
    $last_processed_file = 'last_attendance_date.txt';
    $last_processed = file_exists($last_processed_file) ? file_get_contents($last_processed_file) : '';
    
    $log .= date('Y-m-d H:i:s') . " - آخر تاريخ تمت معالجته: " . ($last_processed ?: 'لا يوجد') . "\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    // معالجة الأمس إذا لم تتم معالجته
    if ($last_processed != $yesterday) {
        $log .= date('Y-m-d H:i:s') . " - جاري معالجة يوم: $yesterday\n";
        file_put_contents($log_file, $log, FILE_APPEND);
        
        $result = processDayAttendance($pdo, $yesterday);
        $log .= date('Y-m-d H:i:s') . " - $result\n";
        file_put_contents($log_file, $log, FILE_APPEND);
        
        file_put_contents($last_processed_file, $yesterday);
        $log .= date('Y-m-d H:i:s') . " - تم تحديث آخر تاريخ معالج إلى: $yesterday\n";
        file_put_contents($log_file, $log, FILE_APPEND);
    } else {
        $log .= date('Y-m-d H:i:s') . " - يوم $yesterday تمت معالجته مسبقاً\n";
        file_put_contents($log_file, $log, FILE_APPEND);
    }
    
    // ============================================
    // 2. معالجة الأيام الفائتة (أهم جزء)
    // هذه المعالجة ستشغل للأيام 13، 14، 15 تلقائياً
    // ============================================
    $log .= date('Y-m-d H:i:s') . " - جاري فحص الأيام الفائتة...\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    // نبدأ من أول الشهر أو من آخر تاريخ معالج
    $start_check = empty($last_processed) ? date('Y-m-d', strtotime('-7 days')) : $last_processed;
    $current = new DateTime($start_check);
    $current->modify('+1 day'); // نبدأ من اليوم التالي لآخر تاريخ معالج
    $end = new DateTime($today);
    $processed_count = 0;
    
    while ($current <= $end) {
        $date_to_check = $current->format('Y-m-d');
        
        // لا نعالج اليوم الحالي (لأن اليوم لم ينته بعد)
        if ($date_to_check < $today) {
            $log .= date('Y-m-d H:i:s') . " - معالجة يوم فائت: $date_to_check\n";
            file_put_contents($log_file, $log, FILE_APPEND);
            
            $result = processDayAttendance($pdo, $date_to_check);
            $log .= date('Y-m-d H:i:s') . " - $result\n";
            file_put_contents($log_file, $log, FILE_APPEND);
            $processed_count++;
        }
        $current->modify('+1 day');
    }
    
    if ($processed_count > 0) {
        $log .= date('Y-m-d H:i:s') . " - ✅ تم معالجة $processed_count يوم فائت\n";
        file_put_contents($log_file, $log, FILE_APPEND);
        
        // تحديث آخر تاريخ معالج إلى أمس
        file_put_contents($last_processed_file, $yesterday);
    }
    
    $log .= date('Y-m-d H:i:s') . " - ✅ اكتمل تشغيل نظام الغياب التلقائي بنجاح\n";
    $log .= date('Y-m-d H:i:s') . " =========================================\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    // ============================================
    // إرجاع استجابة ناجحة لخدمات الكرون (مهم!)
    // ============================================
    echo "SUCCESS: تم تشغيل نظام الغياب التلقائي بنجاح في " . date('Y-m-d H:i:s');
    
} catch (Exception $e) {
    $error_log = date('Y-m-d H:i:s') . " - ❌ خطأ: " . $e->getMessage() . "\n";
    $error_log .= date('Y-m-d H:i:s') . " - Stack trace: " . $e->getTraceAsString() . "\n";
    file_put_contents($log_file, $error_log, FILE_APPEND);
    
    echo "ERROR: " . $e->getMessage();
}

// ============================================
// دالة معالجة الغياب ليوم محدد
// ============================================
function processDayAttendance($pdo, $date) {
    $day_of_week = date('w', strtotime($date)) + 1;
    $is_friday = ($day_of_week == 6);
    
    // التحقق من العطل الرسمية
    $holiday_check = $pdo->prepare("SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?");
    $holiday_check->execute([$date, $date]);
    $is_holiday = $holiday_check->fetch();
    
    if ($is_friday) {
        return "⚠️ يوم الجمعة إجازة - تم تخطي يوم $date";
    }
    
    if ($is_holiday) {
        return "⚠️ يوم عطلة رسمية - تم تخطي يوم $date";
    }
    
    $teachers_absent = 0;
    $teachers_excused = 0;
    $students_absent = 0;
    $students_excused = 0;
    $excused_teachers_ids = [];
    
    // ============================================
    // 1. معالجة المعلمين
    // ============================================
    $teachers = $pdo->prepare("
        SELECT t.id, t.work_days, t.on_leave
        FROM teachers t
        LEFT JOIN attendance a ON a.person_type = 'teacher' AND a.person_id = t.id AND a.date = ?
        WHERE a.id IS NULL AND t.can_login = 1
    ");
    $teachers->execute([$date]);
    
    foreach ($teachers as $teacher) {
        if ($teacher['on_leave']) continue;
        
        $work_days = explode(',', $teacher['work_days'] ?? '1,2,3,4,5,6,7');
        if (!in_array($day_of_week, $work_days)) continue;
        
        // التحقق: هل هذا المعلم معتذر؟
        $excused_check = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE person_type = 'teacher' AND person_id = ? AND date = ? AND is_excused = 1
        ");
        $excused_check->execute([$teacher['id'], $date]);
        
        if ($excused_check->fetch()) {
            $teachers_excused++;
            $excused_teachers_ids[] = $teacher['id'];
            continue;
        }
        
        // تسجيل غياب للمعلم غير المعتذر
        $stmt = $pdo->prepare("
            INSERT INTO attendance (person_type, person_id, date, status, notes, auto_generated)
            VALUES ('teacher', ?, ?, 'absent', 'تسجيل غياب تلقائي', 1)
        ");
        $stmt->execute([$teacher['id'], $date]);
        $teachers_absent++;
    }
    
    // ============================================
    // 2. معالجة الطلاب
    // ============================================
    $students = $pdo->prepare("
        SELECT s.id, s.on_leave, s.teacher_id
        FROM students s
        LEFT JOIN attendance a ON a.person_type = 'student' AND a.person_id = s.id AND a.date = ?
        WHERE a.id IS NULL
    ");
    $students->execute([$date]);
    
    foreach ($students as $student) {
        if ($student['on_leave']) continue;
        
        // التحقق: هل هذا الطالب يمكنه الحضور في هذا اليوم؟
        if (!isRingDayForStudent($pdo, $student['id'], $date)) continue;
        
        // التحقق: هل معلم هذا الطالب معتذر اليوم؟
        $teacher_excused = in_array($student['teacher_id'], $excused_teachers_ids);
        
        // التحقق: هل الطالب نفسه لديه إجازة معذورة؟
        $student_excused_check = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE person_type = 'student' AND person_id = ? AND date = ? AND is_excused = 1
        ");
        $student_excused_check->execute([$student['id'], $date]);
        $is_student_excused = $student_excused_check->fetch();
        
        if ($teacher_excused || $is_student_excused) {
            // الطالب معذور
            $pdo->prepare("
                INSERT INTO attendance (person_type, person_id, date, status, notes, auto_generated, is_excused, excuse_reason)
                VALUES ('student', ?, ?, 'absent', ?, 1, 1, ?)
            ")->execute([
                $student['id'], 
                $date, 
                $teacher_excused ? 'غياب معذور - المعلم معتذر' : 'إجازة معذورة',
                $teacher_excused ? 'غياب معذور - المعلم معتذر' : 'إجازة معذورة'
            ]);
            $students_excused++;
        } else {
            // الطالب غير معذور
            $stmt = $pdo->prepare("
                INSERT INTO attendance (person_type, person_id, date, status, notes, auto_generated)
                VALUES ('student', ?, ?, 'absent', 'تسجيل غياب تلقائي', 1)
            ");
            $stmt->execute([$student['id'], $date]);
            $students_absent++;
        }
    }
    
    return "✅ يوم $date: تم تسجيل غياب $teachers_absent معلم ($teachers_excused معتذر) و $students_absent طالب ($students_excused معذور)";
}

// ============================================
// إذا تم الوصول عبر المتصفح، نعرض واجهة بسيطة (اختياري)
// لكن الكرون سيعمل حتى بدون عرض هذه الواجهة
// ============================================
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_USER_AGENT']) || strpos($_SERVER['HTTP_USER_AGENT'], 'cron-job') === false) {
    // فقط نعرض واجهة إذا كان المستخدم يزور الملف يدوياً
    ?>
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <title>نظام الغياب التلقائي</title>
        <style>
            body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; }
            .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 10px; }
            .log-box { background: #f8f9fa; padding: 15px; border-radius: 10px; font-family: monospace; font-size: 0.8rem; max-height: 300px; overflow-y: auto; margin: 15px 0; }
            .btn { display: inline-block; background: #1e3c3f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
    <div class='container'>
        <h1>🤖 نظام الغياب التلقائي</h1>
        <div class='success'>✅ تم تشغيل النظام بنجاح!</div>
        <div class='log-box'>
            <?php 
            if (file_exists($log_file)) {
                $log_content = file($log_file);
                $last_lines = array_slice($log_content, -30);
                echo nl2br(htmlspecialchars(implode('', $last_lines)));
            } else {
                echo "لا يوجد سجل للعمليات بعد";
            }
            ?>
        </div>
        <a href='dashboard.php' class='btn'>🏠 العودة للوحة التحكم</a>
    </div>
    </body>
    </html>
    <?php
}
?>