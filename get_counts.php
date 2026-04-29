<?php
// ============================================
// ملف: get_counts.php - جلب جميع الإحصائيات دفعة واحدة
// ============================================

function getAllCounts($pdo, $user_id, $user_type) {
    $counts = [];
    
    if ($user_type == 'admin') {
        // استعلام واحد يجلب عدة إحصائيات
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM teachers) as teachers_count,
                (SELECT COUNT(*) FROM students) as students_count,
                (SELECT COUNT(*) FROM student_surah_progress WHERE completed = 1) as memorized_count,
                (SELECT COUNT(*) FROM memorization_challenges WHERE status = 'active') as challenges_count,
                (SELECT COUNT(*) FROM final_student_exams WHERE status = 'in_progress') as active_exams
        ");
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } elseif ($user_type == 'teacher') {
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM students WHERE teacher_id = ?) as students_count,
                (SELECT COUNT(*) FROM students WHERE teacher_id = ? AND id NOT IN (SELECT student_id FROM student_surah_progress WHERE MONTH(completed_at) = MONTH(CURDATE()))) as pending_memorization,
                (SELECT COUNT(*) FROM student_monthly_goals g JOIN students s ON g.student_id = s.id WHERE s.teacher_id = ? AND g.hijri_year = ? AND g.hijri_month_id = ?) as goals_count,
                (SELECT COUNT(*) FROM student_achievements a JOIN student_monthly_goals g ON a.goal_id = g.id JOIN students s ON g.student_id = s.id WHERE s.teacher_id = ?) as certificates_count
        ");
        $hijri = getHijriDate();
        $stmt->execute([$user_id, $user_id, $user_id, $hijri['year'], $hijri['month'], $user_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $counts;
}
?>