<?php
// ============================================
// ملف: auto_close_day.php
// تسجيل غياب تلقائي للأيام السابقة
// ============================================

require_once 'config.php';
require_once 'functions.php';

$today = date('Y-m-d');
$last_processed = isset($_SESSION['last_attendance_date']) ? $_SESSION['last_attendance_date'] : '';

if ($last_processed != $today) {
    // تسجيل غياب لجميع الأيام بين التاريخين
    $current = new DateTime($last_processed ?: $today);
    $end = new DateTime($today);
    $end->modify('-1 day');
    
    while ($current <= $end) {
        $date_to_close = $current->format('Y-m-d');
        
        // تسجيل غياب المعلمين
        $day_of_week = $current->format('w') + 1;
        
        $teachers = $pdo->prepare("
            SELECT t.id, t.work_days
            FROM teachers t
            LEFT JOIN attendance a ON a.person_type = 'teacher' AND a.person_id = t.id AND a.date = ?
            WHERE a.id IS NULL AND t.on_leave = 0
        ");
        $teachers->execute([$date_to_close]);
        
        foreach ($teachers as $teacher) {
            $work_days = explode(',', $teacher['work_days'] ?? '1,2,3,4,5,6,7');
            if (in_array($day_of_week, $work_days)) {
                $pdo->prepare("
                    INSERT INTO attendance (person_type, person_id, date, status, notes, auto_generated)
                    VALUES ('teacher', ?, ?, 'absent', 'تسجيل تلقائي', 1)
                ")->execute([$teacher['id'], $date_to_close]);
            }
        }
        
        // تسجيل غياب الطلاب
        $students = $pdo->prepare("
            SELECT s.id
            FROM students s
            LEFT JOIN attendance a ON a.person_type = 'student' AND a.person_id = s.id AND a.date = ?
            WHERE a.id IS NULL AND s.on_leave = 0
        ");
        $students->execute([$date_to_close]);
        
        foreach ($students as $student) {
            if (isRingDayForStudent($pdo, $student['id'], $date_to_close)) {
                $pdo->prepare("
                    INSERT INTO attendance (person_type, person_id, date, status, notes, auto_generated)
                    VALUES ('student', ?, ?, 'absent', 'تسجيل تلقائي', 1)
                ")->execute([$student['id'], $date_to_close]);
            }
        }
        
        $current->modify('+1 day');
    }
    
    $_SESSION['last_attendance_date'] = $today;
}

echo json_encode(['success' => true]);
?>