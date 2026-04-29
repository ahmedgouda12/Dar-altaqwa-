<?php
// ============================================
// ملف: badge_functions.php
// دوال نظام الإنجازات والشارات
// آخر تحديث: 2026-03-18
// ============================================

/**
 * تحديث تقدم الطالب نحو الشارات
 */
function updateBadgeProgress($pdo, $student_id, $requirement_type, $value) {
    // جلب جميع الشارات من هذا النوع
    $badges = $pdo->prepare("
        SELECT * FROM badges 
        WHERE requirement_type = ? AND is_active = 1
    ");
    $badges->execute([$requirement_type]);
    $badges = $badges->fetchAll();
    
    foreach ($badges as $badge) {
        // التحقق من عدم حصول الطالب على الشارة مسبقاً
        $check = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = ?");
        $check->execute([$student_id, $badge['id']]);
        
        if (!$check->fetch()) {
            // تحديث التقدم
            $progress = $pdo->prepare("
                INSERT INTO badge_progress (student_id, badge_id, current_value, target_value, percentage)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                current_value = VALUES(current_value),
                percentage = (VALUES(current_value) / target_value) * 100
            ");
            $progress->execute([
                $student_id, 
                $badge['id'], 
                $value, 
                $badge['requirement_value'],
                min(100, ($value / $badge['requirement_value']) * 100)
            ]);
            
            // التحقق من تحقيق الشارة
            if ($value >= $badge['requirement_value']) {
                awardBadge($pdo, $student_id, $badge['id'], 'system', 'تحقيق المتطلبات');
            }
        }
    }
}

/**
 * منح شارة للطالب
 */
function awardBadge($pdo, $student_id, $badge_id, $awarded_by = 'system', $reason = '') {
    // التحقق من عدم وجود الشارة مسبقاً
    $check = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = ?");
    $check->execute([$student_id, $badge_id]);
    
    if ($check->fetch()) {
        return false;
    }
    
    // جلب معلومات الشارة
    $badge = $pdo->prepare("SELECT * FROM badges WHERE id = ?");
    $badge->execute([$badge_id]);
    $badge_data = $badge->fetch();
    
    if (!$badge_data) {
        return false;
    }
    
    // منح الشارة
    $stmt = $pdo->prepare("
        INSERT INTO student_badges (student_id, badge_id, seen, awarded_by, awarded_reason)
        VALUES (?, ?, 0, ?, ?)
    ");
    $stmt->execute([$student_id, $badge_id, $awarded_by, $reason]);
    
    // تسجيل في سجل الإنجازات
    $log = $pdo->prepare("
        INSERT INTO achievements_log (student_id, badge_id, achievement_type, description, points_earned)
        VALUES (?, ?, 'badge', ?, ?)
    ");
    $log->execute([
        $student_id, 
        $badge_id, 
        "حصل على شارة: " . $badge_data['name'],
        $badge_data['points_reward']
    ]);
    
    // إضافة إشعار للطالب
    if (file_exists('notifications_functions.php')) {
        require_once 'notifications_functions.php';
        addNotification($pdo, $student_id, 'student', 
            '🏆 شارة جديدة!', 
            "تهانينا! حصلت على شارة " . $badge_data['name'],
            'success',
            'my_badges.php'
        );
    }
    
    return true;
}
/**
 * تحديث شارات الحفظ عند إضافة سورة جديدة
 */
function checkMemorizationBadges($pdo, $student_id, $total_surahs) {
    updateBadgeProgress($pdo, $student_id, 'surah_count', $total_surahs);
    
    // التحقق من إكمال أجزاء محددة
    if ($total_surahs >= 78) { // جزء عم (من سورة 78 إلى 114)
        awardBadge($pdo, $student_id, 3, 'system', 'إكمال جزء عم'); // افترض أن id=3 لجزء عم
    }
    // ... إلخ
}

/**
 * تحديث شارات الحضور
 */
function checkAttendanceBadges($pdo, $student_id, $consecutive_days, $monthly_days) {
    updateBadgeProgress($pdo, $student_id, 'consecutive_days', $consecutive_days);
    updateBadgeProgress($pdo, $student_id, 'monthly_days', $monthly_days);
}

/**
 * تحديث شارات الاختبارات
 */
function checkExamBadges($pdo, $student_id, $exam_score) {
    if ($exam_score >= 95) {
        awardBadge($pdo, $student_id, 15, 'system', 'الحصول على 95% في الاختبار'); // افترض أن id=15 للشارة
    }
    if ($exam_score == 100) {
        awardBadge($pdo, $student_id, 16, 'system', 'الحصول على درجة كاملة'); // افترض أن id=16 للشارة
    }
}
/**
 * جلب شارات الطالب
 */
function getStudentBadges($pdo, $student_id, $only_unseen = false) {
    $sql = "
        SELECT sb.*, b.*,
               DATE(sb.earned_at) as earned_date
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = ?
    ";
    
    if ($only_unseen) {
        $sql .= " AND sb.seen = 0";
    }
    
    $sql .= " ORDER BY b.level DESC, sb.earned_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

/**
 * تحديث حالة مشاهدة الشارات
 */
function markBadgesAsSeen($pdo, $student_id) {
    $stmt = $pdo->prepare("
        UPDATE student_badges SET seen = 1 
        WHERE student_id = ? AND seen = 0
    ");
    return $stmt->execute([$student_id]);
}

/**
 * جلب تقدم الطالب نحو الشارات
 */
function getBadgeProgress($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT bp.*, b.name, b.icon, b.color, b.description,
               b.requirement_value as target
        FROM badge_progress bp
        JOIN badges b ON bp.badge_id = b.id
        WHERE bp.student_id = ?
        ORDER BY bp.percentage DESC
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

/**
 * حساب إحصائيات الشارات للطالب
 */
function getBadgeStats($pdo, $student_id) {
    $stats = [];
    
    // عدد الشارات المحصلة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_badges WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $stats['total_badges'] = $stmt->fetchColumn();
    
    // مجموع النقاط
    $stmt = $pdo->prepare("
        SELECT SUM(b.points_reward) 
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $stats['total_points'] = $stmt->fetchColumn() ?: 0;
    
    // الشارات غير المقروءة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_badges WHERE student_id = ? AND seen = 0");
    $stmt->execute([$student_id]);
    $stats['unseen_badges'] = $stmt->fetchColumn();
    
    // توزيع الشارات حسب المستوى
    $stmt = $pdo->prepare("
        SELECT b.level, COUNT(*) as count
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = ?
        GROUP BY b.level
    ");
    $stmt->execute([$student_id]);
    $stats['level_distribution'] = $stmt->fetchAll();
    
    
    return $stats;
}
?>