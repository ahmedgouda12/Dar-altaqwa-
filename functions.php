<?php
// ============================================
// ملف: functions.php - دوال النظام المتكاملة (نسخة مصححة بالكامل)
// آخر تحديث: 2026-04-20
// ============================================

// ============================================
// دوال التحقق من الأدوار
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

function isTeacher() {
    return isLoggedIn() && $_SESSION['user_type'] === 'teacher';
}

function isGuardian() {
    return isLoggedIn() && $_SESSION['user_type'] === 'guardian';
}

function isStudent() {
    return isLoggedIn() && $_SESSION['user_type'] === 'student';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// ============================================
// دالة تسجيل الدخول
// ============================================

function loginUser($user_id, $user_type, $user_name, $remember_me = false) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_type'] = $user_type;
    $_SESSION['user_name'] = $user_name;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    if ($remember_me) {
        ini_set('session.cookie_lifetime', 86400 * 30);
        session_regenerate_id(true);
    }
    
    return true;
}

// ============================================
// دوال التحقق من صحة المدخلات
// ============================================

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// ============================================
// دوال الواتساب والهاتف
// ============================================

function formatWhatsAppNumber($phone) {
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '20' . substr($phone, 1);
    } elseif (strlen($phone) == 10) {
        $phone = '20' . $phone;
    }
    return $phone;
}

function createWhatsAppLink($phone, $message) {
    $formatted = formatWhatsAppNumber($phone);
    if (!$formatted) return null;
    return "https://wa.me/{$formatted}?text=" . urlencode($message);
}

// ============================================
// دوال أسماء السور
// ============================================

function getSurahName($number) {
    $surahs = [
        1 => 'الفاتحة', 2 => 'البقرة', 3 => 'آل عمران', 4 => 'النساء', 5 => 'المائدة',
        6 => 'الأنعام', 7 => 'الأعراف', 8 => 'الأنفال', 9 => 'التوبة', 10 => 'يونس',
        11 => 'هود', 12 => 'يوسف', 13 => 'الرعد', 14 => 'إبراهيم', 15 => 'الحجر',
        16 => 'النحل', 17 => 'الإسراء', 18 => 'الكهف', 19 => 'مريم', 20 => 'طه',
        21 => 'الأنبياء', 22 => 'الحج', 23 => 'المؤمنون', 24 => 'النور', 25 => 'الفرقان',
        26 => 'الشعراء', 27 => 'النمل', 28 => 'القصص', 29 => 'العنكبوت', 30 => 'الروم',
        31 => 'لقمان', 32 => 'السجدة', 33 => 'الأحزاب', 34 => 'سبأ', 35 => 'فاطر',
        36 => 'يس', 37 => 'الصافات', 38 => 'ص', 39 => 'الزمر', 40 => 'غافر',
        41 => 'فصلت', 42 => 'الشورى', 43 => 'الزخرف', 44 => 'الدخان', 45 => 'الجاثية',
        46 => 'الأحقاف', 47 => 'محمد', 48 => 'الفتح', 49 => 'الحجرات', 50 => 'ق',
        51 => 'الذاريات', 52 => 'الطور', 53 => 'النجم', 54 => 'القمر', 55 => 'الرحمن',
        56 => 'الواقعة', 57 => 'الحديد', 58 => 'المجادلة', 59 => 'الحشر', 60 => 'الممتحنة',
        61 => 'الصف', 62 => 'الجمعة', 63 => 'المنافقون', 64 => 'التغابن', 65 => 'الطلاق',
        66 => 'التحريم', 67 => 'الملك', 68 => 'القلم', 69 => 'الحاقة', 70 => 'المعارج',
        71 => 'نوح', 72 => 'الجن', 73 => 'المزمل', 74 => 'المدثر', 75 => 'القيامة',
        76 => 'الإنسان', 77 => 'المرسلات', 78 => 'النبأ', 79 => 'النازعات', 80 => 'عبس',
        81 => 'التكوير', 82 => 'الانفطار', 83 => 'المطففين', 84 => 'الانشقاق', 85 => 'البروج',
        86 => 'الطارق', 87 => 'الأعلى', 88 => 'الغاشية', 89 => 'الفجر', 90 => 'البلد',
        91 => 'الشمس', 92 => 'الليل', 93 => 'الضحى', 94 => 'الشرح', 95 => 'التين',
        96 => 'العلق', 97 => 'القدر', 98 => 'البينة', 99 => 'الزلزلة', 100 => 'العاديات',
        101 => 'القارعة', 102 => 'التكاثر', 103 => 'العصر', 104 => 'الهمزة', 105 => 'الفيل',
        106 => 'قريش', 107 => 'الماعون', 108 => 'الكوثر', 109 => 'الكافرون', 110 => 'النصر',
        111 => 'المسد', 112 => 'الإخلاص', 113 => 'الفلق', 114 => 'الناس'
    ];
    return $surahs[$number] ?? 'غير معروف';
}

// ============================================
// دوال نطاق صفحات السور (للأجزاء)
// ============================================

function getSurahPageRange($surah_number) {
    $ranges = [
        1 => [1, 1], 2 => [2, 49], 3 => [50, 76], 4 => [77, 106], 5 => [106, 127],
        6 => [128, 150], 7 => [151, 176], 8 => [177, 186], 9 => [187, 207], 10 => [208, 221],
        11 => [221, 235], 12 => [235, 248], 13 => [249, 255], 14 => [255, 261], 15 => [262, 266],
        16 => [267, 281], 17 => [282, 293], 18 => [293, 304], 19 => [305, 311], 20 => [312, 321],
        21 => [322, 331], 22 => [332, 341], 23 => [342, 349], 24 => [350, 359], 25 => [359, 366],
        26 => [367, 376], 27 => [377, 385], 28 => [385, 396], 29 => [396, 404], 30 => [404, 410],
        31 => [411, 414], 32 => [415, 417], 33 => [418, 427], 34 => [428, 434], 35 => [434, 439],
        36 => [440, 445], 37 => [446, 452], 38 => [453, 458], 39 => [458, 467], 40 => [467, 476],
        41 => [477, 482], 42 => [483, 489], 43 => [489, 495], 44 => [496, 498], 45 => [499, 502],
        46 => [502, 506], 47 => [507, 510], 48 => [511, 515], 49 => [515, 517], 50 => [518, 520],
        51 => [520, 523], 52 => [523, 525], 53 => [526, 528], 54 => [528, 531], 55 => [531, 534],
        56 => [534, 537], 57 => [537, 541], 58 => [542, 545], 59 => [545, 548], 60 => [549, 551],
        61 => [551, 552], 62 => [553, 554], 63 => [554, 555], 64 => [556, 557], 65 => [558, 559],
        66 => [560, 561], 67 => [562, 564], 68 => [564, 566], 69 => [566, 568], 70 => [568, 570],
        71 => [570, 571], 72 => [572, 573], 73 => [574, 575], 74 => [575, 577], 75 => [577, 578],
        76 => [578, 580], 77 => [580, 581], 78 => [582, 583], 79 => [583, 584], 80 => [585, 585],
        81 => [586, 586], 82 => [587, 587], 83 => [587, 589], 84 => [589, 590], 85 => [590, 590],
        86 => [591, 591], 87 => [591, 592], 88 => [592, 593], 89 => [593, 594], 90 => [594, 595],
        91 => [595, 595], 92 => [595, 596], 93 => [596, 596], 94 => [596, 596], 95 => [597, 597],
        96 => [597, 598], 97 => [598, 598], 98 => [598, 599], 99 => [599, 599], 100 => [599, 600],
        101 => [600, 600], 102 => [600, 600], 103 => [601, 601], 104 => [601, 601], 105 => [601, 601],
        106 => [602, 602], 107 => [602, 602], 108 => [602, 602], 109 => [603, 603], 110 => [603, 603],
        111 => [603, 603], 112 => [604, 604], 113 => [604, 604], 114 => [604, 604]
    ];
    return $ranges[$surah_number] ?? [1, 1];
}

// ============================================
// دوال حساب الأجزاء (الدقيقة)
// ============================================

function getStudentUniquePages($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT surah_number 
        FROM student_surah_progress 
        WHERE student_id = ? AND completed = 1
    ");
    $stmt->execute([$student_id]);
    $surahs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($surahs)) {
        return 0;
    }
    
    $unique_pages = [];
    foreach ($surahs as $surah) {
        list($start, $end) = getSurahPageRange($surah);
        for ($page = $start; $page <= $end; $page++) {
            $unique_pages[$page] = true;
        }
    }
    return count($unique_pages);
}

function calculatePartsPrecise($total_pages) {
    if ($total_pages == 0) return 0;
    if ($total_pages >= 604) return 30;
    return round($total_pages / 20, 2);
}

function calculateFullParts($total_pages) {
    return floor($total_pages / 20);
}

function getRemainingPagesToNextPart($total_pages) {
    $remaining = 20 - ($total_pages % 20);
    return ($remaining == 20) ? 0 : $remaining;
}

function getCurrentPartProgress($total_pages) {
    $pages_in_current_part = $total_pages % 20;
    if ($pages_in_current_part == 0 && $total_pages > 0) return 100;
    return round(($pages_in_current_part / 20) * 100, 1);
}

function getCurrentPartNumber($total_pages) {
    if ($total_pages >= 604) return 30;
    return floor($total_pages / 20) + 1;
}

function getDetailedMemorizationDescription($total_pages) {
    if ($total_pages == 0) {
        return "🌱 لم يحفظ بعد";
    }
    
    if ($total_pages >= 604) {
        return "🎓 ختم القرآن الكريم كاملاً (30 جزء - 604 صفحة)";
    }
    
    $full_parts = floor($total_pages / 20);
    $remaining_pages = $total_pages % 20;
    $current_part = $full_parts + 1;
    $percentage = round(($remaining_pages / 20) * 100, 1);
    
    if ($remaining_pages == 0) {
        return "📖 أكمل {$full_parts} جزء كاملاً ({$total_pages} صفحة)";
    } elseif ($full_parts == 0) {
        return "📖 يحفظ الجزء الأول بنسبة {$percentage}% ({$remaining_pages}/20 صفحة)";
    } else {
        return "📖 أكمل {$full_parts} جزء كاملاً، ويحفظ الجزء {$current_part} بنسبة {$percentage}% ({$remaining_pages}/20 صفحة)";
    }
}

function updateStudentPartsStats($pdo, $student_id) {
    $total_pages = getStudentUniquePages($pdo, $student_id);
    $total_parts_precise = calculatePartsPrecise($total_pages);
    $total_parts_full = calculateFullParts($total_pages);
    $remaining_pages = getRemainingPagesToNextPart($total_pages);
    $current_part_progress = getCurrentPartProgress($total_pages);
    $current_part_number = getCurrentPartNumber($total_pages);
    $description = getDetailedMemorizationDescription($total_pages);
    
    try {
        // التحقق من وجود الجدول والأعمدة
        $check_table = $pdo->query("SHOW TABLES LIKE 'student_parts_stats'");
        if ($check_table->rowCount() > 0) {
            // التحقق من وجود الأعمدة وإضافتها إذا لزم الأمر
            $columns = $pdo->query("SHOW COLUMNS FROM student_parts_stats");
            $existing_columns = $columns->fetchAll(PDO::FETCH_COLUMN);
            
            $required_columns = ['total_pages', 'full_parts', 'remaining_pages', 'current_part_progress', 'current_part_number', 'description'];
            foreach ($required_columns as $col) {
                if (!in_array($col, $existing_columns)) {
                    $type = ($col == 'description') ? 'TEXT' : 'INT DEFAULT 0';
                    $pdo->exec("ALTER TABLE student_parts_stats ADD COLUMN $col $type");
                }
            }
            
            $check = $pdo->prepare("SELECT id FROM student_parts_stats WHERE student_id = ?");
            $check->execute([$student_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE student_parts_stats SET 
                        total_pages = ?,
                        total_parts = ?,
                        full_parts = ?,
                        remaining_pages = ?,
                        current_part_progress = ?,
                        current_part_number = ?,
                        description = ?,
                        updated_at = NOW()
                    WHERE student_id = ?
                ");
                $stmt->execute([
                    $total_pages, $total_parts_precise, $total_parts_full,
                    $remaining_pages, $current_part_progress, $current_part_number,
                    $description, $student_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO student_parts_stats 
                    (student_id, total_pages, total_parts, full_parts, remaining_pages, 
                     current_part_progress, current_part_number, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $student_id, $total_pages, $total_parts_precise, $total_parts_full,
                    $remaining_pages, $current_part_progress, $current_part_number, $description
                ]);
            }
        }
    } catch (PDOException $e) {
        // تجاهل الأخطاء
    }
    
    return [
        'total_pages' => $total_pages,
        'total_parts' => $total_parts_precise,
        'full_parts' => $total_parts_full,
        'remaining_pages' => $remaining_pages,
        'current_part_number' => $current_part_number,
        'current_part_progress' => $current_part_progress,
        'description' => $description
    ];
}

function getPartsDistribution($pdo) {
    $distribution = array_fill(0, 31, 0);
    
    $check_table = $pdo->query("SHOW TABLES LIKE 'student_parts_stats'");
    if ($check_table->rowCount() > 0) {
        try {
            $stmt = $pdo->query("
                SELECT 
                    CASE 
                        WHEN total_parts >= 30 THEN 30
                        ELSE FLOOR(total_parts)
                    END as part_number,
                    COUNT(*) as count
                FROM student_parts_stats
                WHERE total_parts > 0
                GROUP BY part_number
            ");
            
            foreach ($stmt->fetchAll() as $row) {
                $distribution[$row['part_number']] = $row['count'];
            }
        } catch (PDOException $e) {}
    }
    
    return $distribution;
}

// ============================================
// دوال أيام الأسبوع
// ============================================

function getDayNameArabic($day) {
    $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    return $days[$day] ?? 'غير معروف';
}

// ============================================
// دوال الحضور
// ============================================

function getStudentRingDays($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT rsch.day_of_week 
        FROM ring_students rs
        JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
        WHERE rs.student_id = ?
        ORDER BY rsch.day_of_week
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getStudentRingDaysText($pdo, $student_id) {
    $days = getStudentRingDays($pdo, $student_id);
    if (empty($days)) return 'لا يوجد حلقات';
    
    $days_names = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $result = [];
    foreach ($days as $day) {
        $result[] = $days_names[$day - 1] ?? '';
    }
    return implode(' - ', $result);
}

function isRingDayForStudent($pdo, $student_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    $day_of_week = date('w', strtotime($date)) + 1;
    $days = getStudentRingDays($pdo, $student_id);
    return in_array($day_of_week, $days);
}

// ============================================
// دوال التحقق من الإجازات
// ============================================

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function isTeacherOnLeave($pdo, $teacher_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    
    if (!columnExists($pdo, 'teachers', 'on_leave')) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM teachers 
            WHERE id = ? AND on_leave = 1 
            AND leave_start_date <= ? AND leave_end_date >= ?
        ");
        $stmt->execute([$teacher_id, $date, $date]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function isStudentOnLeave($pdo, $student_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    
    if (!columnExists($pdo, 'students', 'on_leave')) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM students 
            WHERE id = ? AND on_leave = 1 
            AND leave_start_date <= ? AND leave_end_date >= ?
        ");
        $stmt->execute([$student_id, $date, $date]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function isHoliday($pdo, $date = null, $person_type = null) {
    if (!$date) $date = date('Y-m-d');
    
    try {
        $sql = "SELECT id FROM holidays WHERE start_date <= ? AND end_date >= ?";
        $params = [$date, $date];
        
        if ($person_type) {
            $sql .= " AND (apply_to = 'both' OR apply_to = ?)";
            $params[] = $person_type;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// ============================================
// دوال رسائل التنبيه
// ============================================

function displayMessages() {
    $output = '';
    if (isset($_SESSION['success'])) {
        $output .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        $output .= '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['warning'])) {
        $output .= '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['warning']) . '</div>';
        unset($_SESSION['warning']);
    }
    return $output;
}

function setSuccess($message) {
    $_SESSION['success'] = $message;
}

function setError($message) {
    $_SESSION['error'] = $message;
}

function setWarning($message) {
    $_SESSION['warning'] = $message;
}

function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}

// ============================================
// دوال التقييم المتقدم
// ============================================

function getGradeFromScore($score) {
    if ($score >= 95) return 'ممتاز';
    if ($score >= 85) return 'جيد جداً';
    if ($score >= 75) return 'جيد';
    if ($score >= 60) return 'مقبول';
    return 'ضعيف';
}

function generateCertificateNumber($student_id, $hijri_year, $hijri_month) {
    return 'CER-' . $hijri_year . '-' . str_pad($hijri_month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT) . '-' . uniqid();
}

// ============================================
// دوال الحلقات
// ============================================

function updateRingStudentsCount($pdo, $ring_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ring_students WHERE ring_id = ?");
    $stmt->execute([$ring_id]);
    $count = $stmt->fetchColumn();
    $pdo->prepare("UPDATE rings SET student_count = ? WHERE id = ?")->execute([$count, $ring_id]);
    return $count;
}

function removeStudentFromRing($pdo, $student_id, $ring_id) {
    $stmt = $pdo->prepare("DELETE FROM ring_students WHERE ring_id = ? AND student_id = ?");
    $stmt->execute([$ring_id, $student_id]);
    updateRingStudentsCount($pdo, $ring_id);
    return $stmt->rowCount() > 0;
}

// ============================================
// دوال الإضافة الجماعية للحلقات (لحفظ السور دفعة واحدة)
// ============================================

/**
 * إضافة سورة واحدة لجميع طلاب حلقة معينة
 */
function addBulkMemorizationToRing($pdo, $ring_id, $surah_number, $from_ayah = null, $to_ayah = null, $from_page = null, $to_page = null, $notes = '') {
    // جلب جميع طلاب الحلقة
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.teacher_id
        FROM ring_students rs
        JOIN students s ON rs.student_id = s.id
        WHERE rs.ring_id = ?
        GROUP BY s.id, s.teacher_id
    ");
    $stmt->execute([$ring_id]);
    $students = $stmt->fetchAll();
    
    if (empty($students)) {
        return ['success' => false, 'message' => 'لا يوجد طلاب في هذه الحلقة'];
    }
    
    $added_count = 0;
    $skipped_count = 0;
    $added_students = [];
    
    foreach ($students as $student) {
        // التحقق من عدم تكرار السورة
        $check = $pdo->prepare("
            SELECT id FROM student_surah_progress 
            WHERE student_id = ? AND surah_number = ? AND completed = 1
        ");
        $check->execute([$student['id'], $surah_number]);
        
        if ($check->fetch()) {
            $skipped_count++;
            continue;
        }
        
        // إضافة السورة
        $insert = $pdo->prepare("
            INSERT INTO student_surah_progress 
            (student_id, teacher_id, surah_number, completed, completed_at, notes)
            VALUES (?, ?, ?, 1, NOW(), ?)
        ");
        $insert->execute([$student['id'], $student['teacher_id'], $surah_number, $notes]);
        $added_count++;
        $added_students[] = $student['id'];
        
        // تحديث إحصائيات الأجزاء للطالب
        updateStudentPartsStats($pdo, $student['id']);
    }
    
    // تسجيل عملية الإضافة الجماعية في سجل خاص (اختياري)
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO ring_memorization_bulk 
            (ring_id, surah_number, from_page, to_page, notes, students_count, added_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([
            $ring_id, $surah_number, $from_page, $to_page, 
            $notes, $added_count
        ]);
    } catch (PDOException $e) {
        // تجاهل الأخطاء إذا كان الجدول غير موجود
    }
    
    return [
        'success' => true,
        'added_count' => $added_count,
        'skipped_count' => $skipped_count,
        'added_students' => $added_students,
        'message' => "✅ تمت إضافة السورة لـ $added_count طالب" . 
                     ($skipped_count > 0 ? " (تخطي $skipped_count طالب مضاف مسبقاً)" : "")
    ];
}

/**
 * إضافة عدة سور دفعة واحدة لجميع طلاب حلقة معينة
 */
function addMultipleBulkMemorizationToRing($pdo, $ring_id, $surahs_array, $notes = '') {
    if (empty($surahs_array)) {
        return ['success' => false, 'message' => 'لم يتم تحديد أي سور'];
    }
    
    $total_added = 0;
    $total_skipped = 0;
    $results = [];
    $all_added_surahs = [];
    
    foreach ($surahs_array as $surah_number) {
        $result = addBulkMemorizationToRing($pdo, $ring_id, $surah_number, null, null, null, null, $notes);
        $total_added += $result['added_count'];
        $total_skipped += $result['skipped_count'];
        $results[] = $result;
        
        if ($result['added_count'] > 0) {
            $all_added_surahs[] = $surah_number;
        }
    }
    
    return [
        'success' => true,
        'total_added' => $total_added,
        'total_skipped' => $total_skipped,
        'added_surahs' => $all_added_surahs,
        'results' => $results,
        'message' => "✅ تمت إضافة " . count($surahs_array) . " سورة بنجاح (إجمالي $total_added تسجيل)"
    ];
}

/**
 * إضافة سور بناءً على نطاق صفحات (من صفحة إلى صفحة)
 */
function addBulkMemorizationByPages($pdo, $ring_id, $from_page, $to_page, $notes = '') {
    if ($from_page < 1 || $to_page > 604 || $from_page > $to_page) {
        return ['success' => false, 'message' => 'نطاق الصفحات غير صحيح'];
    }
    
    // جلب السور الموجودة في نطاق الصفحات
    $surahs_in_range = [];
    for ($surah = 1; $surah <= 114; $surah++) {
        list($start_page, $end_page) = getSurahPageRange($surah);
        if ($start_page <= $to_page && $end_page >= $from_page) {
            $surahs_in_range[] = $surah;
        }
    }
    
    if (empty($surahs_in_range)) {
        return ['success' => false, 'message' => 'لا توجد سور في نطاق الصفحات المحدد'];
    }
    
    return addMultipleBulkMemorizationToRing($pdo, $ring_id, $surahs_in_range, $notes);
}

/**
 * إنشاء جدول سجل الإضافات الجماعية (إذا لم يكن موجوداً)
 */
function ensureRingMemorizationBulkTable($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ring_memorization_bulk (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ring_id INT NOT NULL,
                surah_number INT,
                from_page INT,
                to_page INT,
                notes TEXT,
                students_count INT DEFAULT 0,
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ring (ring_id),
                INDEX idx_date (added_at)
            )
        ");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// التأكد من وجود الجدول عند تحميل الدوال
ensureRingMemorizationBulkTable($pdo);

// ============================================
// دوال إدارة الاشتراكات والدفع
// ============================================

/**
 * حساب المبلغ المستحق للطالب في شهر معين
 */
function getStudentMonthlyAmount($pdo, $student_id, $category = null, $is_exempted = false) {
    if ($is_exempted) return 0;
    
    // جلب الإعدادات
    $settings_file = __DIR__ . '/payment_settings.json';
    $discounts_file = __DIR__ . '/payment_discounts.json';
    $exceptions_file = __DIR__ . '/payment_exceptions.json';
    
    $settings = [];
    if (file_exists($settings_file)) {
        $settings = json_decode(file_get_contents($settings_file), true);
    }
    $default_amount = $settings['default_monthly_fee'] ?? 200;
    $global_discount = $settings['global_discount_percent'] ?? 0;
    
    // جلب خصومات الفئات
    $discounts = [];
    if (file_exists($discounts_file)) {
        $discounts = json_decode(file_get_contents($discounts_file), true);
    }
    $category_discount = $discounts['category'][$category] ?? 0;
    
    // جلب الاستثناءات الفردية
    $exceptions = [];
    if (file_exists($exceptions_file)) {
        $exceptions = json_decode(file_get_contents($exceptions_file), true);
    }
    
    // إذا كان هناك استثناء فردي
    if (isset($exceptions[$student_id]) && !empty($exceptions[$student_id]['amount'])) {
        return (float)$exceptions[$student_id]['amount'];
    }
    
    // حساب المبلغ
    $amount = $default_amount;
    $amount = $amount * (1 - $category_discount / 100);
    $amount = $amount * (1 - $global_discount / 100);
    
    return round($amount, 2);
}

/**
 * الحصول على إعدادات الدفع العامة
 */
function getPaymentSettings() {
    $settings_file = __DIR__ . '/payment_settings.json';
    if (file_exists($settings_file)) {
        return json_decode(file_get_contents($settings_file), true);
    }
    return [
        'default_monthly_fee' => 200,
        'global_discount_percent' => 0,
        'currency' => 'ج.م',
        'payment_due_day' => 15,
        'late_fee' => 10,
        'late_fee_after_days' => 7,
        'auto_overdue_days' => 15
    ];
}

/**
 * تحديث حالة الدفع إلى "متأخر" تلقائياً للطلاب الذين تجاوزوا تاريخ الاستحقاق
 */
function autoUpdateOverdueStatus($pdo) {
    $settings = getPaymentSettings();
    $overdue_days = $settings['auto_overdue_days'] ?? 15;
    $due_date = date('Y-m-d', strtotime('-' . $overdue_days . ' days'));
    $current_month = date('Y-m') . '-01';
    
    $stmt = $pdo->prepare("
        UPDATE student_payments 
        SET payment_status = 'overdue', updated_at = NOW()
        WHERE month_year = ? 
        AND payment_status = 'pending' 
        AND (payment_date IS NULL OR payment_date < ?)
        AND is_exempted = 0
    ");
    $stmt->execute([$current_month, $due_date]);
    
    return $stmt->rowCount();
}
/**
 * جلب بيانات الطلاب مع اشتراكاتهم لشهر محدد
 * بدون تكرار - مع دعم فلترة المعلمين
 */
function getStudentsWithPayments($pdo, $month_year, $teacher_id = null, $status_filter = 'all', $search = '') {
    // 1. جلب الطلاب (مع فلترة المعلمين)
    $students_sql = "
        SELECT 
            s.id,
            s.name,
            s.category,
            s.level,
            s.parent_phone,
            s.is_exempted as student_exempted,
            s.exempted_reason as student_exempted_reason,
            t.id as teacher_id,
            t.name as teacher_name,
            t.phone as teacher_phone
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // فلترة حسب المعلم (إذا تم تحديد معلم)
    if ($teacher_id !== null && $teacher_id > 0) {
        $students_sql .= " AND s.teacher_id = ?";
        $params[] = $teacher_id;
    }
    
    // فلترة حسب البحث
    if (!empty($search)) {
        $students_sql .= " AND (s.name LIKE ? OR s.parent_phone LIKE ? OR t.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $students_sql .= " ORDER BY s.name";
    
    $stmt = $pdo->prepare($students_sql);
    $stmt->execute($params);
    $all_students = $stmt->fetchAll();
    
    // 2. جلب سجلات الدفع (مرة واحدة فقط)
    $payments_sql = "
        SELECT 
            student_id,
            MAX(id) as payment_id,
            MAX(amount) as amount,
            MAX(payment_date) as payment_date,
            MAX(payment_status) as payment_status,
            MAX(notes) as payment_notes,
            MAX(is_exempted) as payment_exempted,
            MAX(exempted_reason) as payment_exempted_reason
        FROM student_payments
        WHERE month_year = ?
        GROUP BY student_id
    ";
    
    $payments_stmt = $pdo->prepare($payments_sql);
    $payments_stmt->execute([$month_year]);
    $payments_data = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحويل إلى مصفوفة للبحث السريع
    $payments_by_student = [];
    foreach ($payments_data as $payment) {
        $payments_by_student[$payment['student_id']] = $payment;
    }
    
    // 3. دمج البيانات وتحديد الحالة
    $students = [];
    foreach ($all_students as $student) {
        $payment = $payments_by_student[$student['id']] ?? null;
        
        // تحديد الحالة
        if ($student['student_exempted'] == 1) {
            $status = 'exempted';
        } elseif ($payment && $payment['payment_exempted'] == 1) {
            $status = 'exempted';
        } elseif ($payment && $payment['payment_status'] == 'paid') {
            $status = 'paid';
        } elseif ($payment && $payment['payment_status'] == 'overdue') {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }
        
        // فلترة حسب الحالة المطلوبة
        if ($status_filter != 'all' && $status != $status_filter) {
            continue;
        }
        
        $students[] = [
            'id' => $student['id'],
            'name' => $student['name'],
            'category' => $student['category'],
            'level' => $student['level'],
            'parent_phone' => $student['parent_phone'],
            'student_exempted' => $student['student_exempted'],
            'student_exempted_reason' => $student['student_exempted_reason'],
            'teacher_id' => $student['teacher_id'],
            'teacher_name' => $student['teacher_name'],
            'teacher_phone' => $student['teacher_phone'],
            'payment_id' => $payment ? $payment['payment_id'] : null,
            'amount' => $payment ? $payment['amount'] : null,
            'payment_date' => $payment ? $payment['payment_date'] : null,
            'payment_status' => $payment ? $payment['payment_status'] : null,
            'payment_notes' => $payment ? $payment['payment_notes'] : null,
            'payment_exempted' => $payment ? $payment['payment_exempted'] : null,
            'payment_exempted_reason' => $payment ? $payment['payment_exempted_reason'] : null,
            'status' => $status,
            'due_amount' => getStudentMonthlyAmount($pdo, $student['id'], $student['category'], $status == 'exempted')
        ];
    }
    
    return $students;
}
// ============================================
// التحقق من تعريف الدوال المفقودة وتجنب التكرار
// ============================================

if (!function_exists('calculatePartsFromUniquePages')) {
    function calculatePartsFromUniquePages($total_pages) {
        if ($total_pages == 0) return 0;
        if ($total_pages >= 604) return 30;
        return round($total_pages / 20, 2);
    }
}

if (!function_exists('calculateFullPartsFromPages')) {
    function calculateFullPartsFromPages($total_pages) {
        return floor($total_pages / 20);
    }
}

if (!function_exists('getRemainingPagesToNextPart')) {
    function getRemainingPagesToNextPart($total_pages) {
        $remaining = 20 - ($total_pages % 20);
        return ($remaining == 20) ? 0 : $remaining;
    }
}

if (!function_exists('getCurrentPartProgress')) {
    function getCurrentPartProgress($total_pages) {
        $pages_in_current_part = $total_pages % 20;
        if ($pages_in_current_part == 0 && $total_pages > 0) return 100;
        return round(($pages_in_current_part / 20) * 100, 1);
    }
}

if (!function_exists('getCurrentPartNumber')) {
    function getCurrentPartNumber($total_pages) {
        if ($total_pages >= 604) return 30;
        return floor($total_pages / 20) + 1;
    }
}

if (!function_exists('getDetailedMemorizationDescription')) {
    function getDetailedMemorizationDescription($total_pages) {
        if ($total_pages == 0) {
            return "🌱 لم يحفظ بعد";
        }
        
        if ($total_pages >= 604) {
            return "🎓 ختم القرآن الكريم كاملاً (30 جزء - 604 صفحة)";
        }
        
        $full_parts = floor($total_pages / 20);
        $remaining_pages = $total_pages % 20;
        $current_part = $full_parts + 1;
        $percentage = round(($remaining_pages / 20) * 100, 1);
        
        if ($remaining_pages == 0) {
            return "📖 أكمل {$full_parts} جزء كاملاً ({$total_pages} صفحة)";
        } elseif ($full_parts == 0) {
            return "📖 يحفظ الجزء الأول بنسبة {$percentage}% ({$remaining_pages}/20 صفحة)";
        } else {
            return "📖 أكمل {$full_parts} جزء كاملاً، ويحفظ الجزء {$current_part} بنسبة {$percentage}% ({$remaining_pages}/20 صفحة)";
        }
    }
}

if (!function_exists('getMemorizationDescription')) {
    function getMemorizationDescription($total_pages, $total_parts) {
        if ($total_pages == 0) {
            return "لم يحفظ أي شيء بعد. ابدأ رحلة الحفظ مع القرآن الكريم.";
        }
        
        if ($total_pages >= 604) {
            return "🎉 ماشاء الله! ختم القرآن الكريم كاملاً. بارك الله فيك.";
        }
        
        $percentage = round(($total_pages / 604) * 100, 1);
        $full_parts = floor($total_parts);
        $remaining_pages = 20 - ($total_pages % 20);
        if ($remaining_pages == 20) $remaining_pages = 0;
        
        if ($full_parts == 0) {
            return "📖 بداية ممتازة! حفظت {$total_pages} صفحة (" . $percentage . "% من القرآن). " .
                   "أكمل {$remaining_pages} صفحة لإكمال الجزء الأول.";
        } else {
            return "📖 حفظت {$total_pages} صفحة (" . $percentage . "% من القرآن). " .
                   "أكملت {$full_parts} أجزاء كاملة. متبقي {$remaining_pages} صفحة لإكمال الجزء التالي.";
        }
    }
}

if (!function_exists('getStudentUniquePages')) {
    function getStudentUniquePages($pdo, $student_id) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT surah_number 
            FROM student_surah_progress 
            WHERE student_id = ? AND completed = 1
        ");
        $stmt->execute([$student_id]);
        $surahs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($surahs)) {
            return 0;
        }
        
        $unique_pages = [];
        foreach ($surahs as $surah) {
            list($start, $end) = getSurahPageRange($surah);
            for ($page = $start; $page <= $end; $page++) {
                $unique_pages[$page] = true;
            }
        }
        return count($unique_pages);
    }
}

if (!function_exists('getSurahPageRange')) {
    function getSurahPageRange($surah_number) {
        $ranges = [
            1 => [1, 1], 2 => [2, 49], 3 => [50, 76], 4 => [77, 106], 5 => [106, 127],
            6 => [128, 150], 7 => [151, 176], 8 => [177, 186], 9 => [187, 207], 10 => [208, 221],
            11 => [221, 235], 12 => [235, 248], 13 => [249, 255], 14 => [255, 261], 15 => [262, 266],
            16 => [267, 281], 17 => [282, 293], 18 => [293, 304], 19 => [305, 311], 20 => [312, 321],
            21 => [322, 331], 22 => [332, 341], 23 => [342, 349], 24 => [350, 359], 25 => [359, 366],
            26 => [367, 376], 27 => [377, 385], 28 => [385, 396], 29 => [396, 404], 30 => [404, 410],
            31 => [411, 414], 32 => [415, 417], 33 => [418, 427], 34 => [428, 434], 35 => [434, 439],
            36 => [440, 445], 37 => [446, 452], 38 => [453, 458], 39 => [458, 467], 40 => [467, 476],
            41 => [477, 482], 42 => [483, 489], 43 => [489, 495], 44 => [496, 498], 45 => [499, 502],
            46 => [502, 506], 47 => [507, 510], 48 => [511, 515], 49 => [515, 517], 50 => [518, 520],
            51 => [520, 523], 52 => [523, 525], 53 => [526, 528], 54 => [528, 531], 55 => [531, 534],
            56 => [534, 537], 57 => [537, 541], 58 => [542, 545], 59 => [545, 548], 60 => [549, 551],
            61 => [551, 552], 62 => [553, 554], 63 => [554, 555], 64 => [556, 557], 65 => [558, 559],
            66 => [560, 561], 67 => [562, 564], 68 => [564, 566], 69 => [566, 568], 70 => [568, 570],
            71 => [570, 571], 72 => [572, 573], 73 => [574, 575], 74 => [575, 577], 75 => [577, 578],
            76 => [578, 580], 77 => [580, 581], 78 => [582, 583], 79 => [583, 584], 80 => [585, 585],
            81 => [586, 586], 82 => [587, 587], 83 => [587, 589], 84 => [589, 590], 85 => [590, 590],
            86 => [591, 591], 87 => [591, 592], 88 => [592, 593], 89 => [593, 594], 90 => [594, 595],
            91 => [595, 595], 92 => [595, 596], 93 => [596, 596], 94 => [596, 596], 95 => [597, 597],
            96 => [597, 598], 97 => [598, 598], 98 => [598, 599], 99 => [599, 599], 100 => [599, 600],
            101 => [600, 600], 102 => [600, 600], 103 => [601, 601], 104 => [601, 601], 105 => [601, 601],
            106 => [602, 602], 107 => [602, 602], 108 => [602, 602], 109 => [603, 603], 110 => [603, 603],
            111 => [603, 603], 112 => [604, 604], 113 => [604, 604], 114 => [604, 604]
        ];
        return $ranges[$surah_number] ?? [1, 1];
    }
}

if (!function_exists('updateStudentPartsStats')) {
    function updateStudentPartsStats($pdo, $student_id) {
        $total_pages = getStudentUniquePages($pdo, $student_id);
        $total_parts = calculatePartsFromUniquePages($total_pages);
        
        try {
            $check = $pdo->prepare("SELECT id FROM student_parts_stats WHERE student_id = ?");
            $check->execute([$student_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE student_parts_stats SET 
                        total_pages = ?,
                        total_parts = ?,
                        updated_at = NOW()
                    WHERE student_id = ?
                ");
                $stmt->execute([$total_pages, $total_parts, $student_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO student_parts_stats (student_id, total_pages, total_parts)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$student_id, $total_pages, $total_parts]);
            }
        } catch (PDOException $e) {
            // تجاهل الأخطاء
        }
        
        return [
            'total_pages' => $total_pages,
            'total_parts' => $total_parts
        ];
    }
}
// ============================================
// دوال نظام النقاط والمستويات - أضفها في نهاية functions.php
// ============================================

/**
 * إضافة سجل نقاط للطالب
 */
function addPointsLog($pdo, $student_id, $points, $type, $description = '') {
    // التحقق من وجود الجدول
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS points_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                points_earned INT NOT NULL,
                points_type VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student (student_id),
                INDEX idx_type (points_type)
            )
        ");
    } catch (PDOException $e) {
        // تجاهل الأخطاء إذا كان الجدول موجوداً
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO points_log (student_id, points_earned, points_type, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$student_id, $points, $type, $description]);
}

/**
 * تحديث إجمالي نقاط الطالب من جميع المصادر
 */
function updateStudentPoints($pdo, $student_id) {
    $points = 0;
    
    // 1. نقاط السور المحفوظة (كل سورة = 10 نقاط)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_surah_progress WHERE student_id = ? AND completed = 1");
    $stmt->execute([$student_id]);
    $points += $stmt->fetchColumn() * 10;
    
    // 2. نقاط الأوراد اليومية
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) FROM student_wird_records WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $points += $stmt->fetchColumn();
    
    // 3. نقاط الصلوات
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) FROM student_prayer_records WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $points += $stmt->fetchColumn();
    
    // 4. نقاط الحضور (كل يوم حضور = 5 نقاط)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance 
        WHERE person_type = 'student' AND person_id = ? AND status = 'present'
    ");
    $stmt->execute([$student_id]);
    $points += $stmt->fetchColumn() * 5;
    
    // 5. نقاط الحفظ اليومي (كل يوم تسجيل = 3 نقاط أساسية + نقاط إضافية)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM daily_memorization_records 
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $points += $stmt->fetchColumn() * 3;
    
    // تحديث جدول النقاط
    $check = $pdo->prepare("SELECT id FROM student_points WHERE student_id = ?");
    $check->execute([$student_id]);
    
    // تحديد المستوى
    $level = 1;
    $level_name = 'مبتدئ';
    $level_icon = '🌱';
    
    if ($points >= 1000) {
        $level = 5;
        $level_name = 'مجيد';
        $level_icon = '👑';
    } elseif ($points >= 500) {
        $level = 4;
        $level_name = 'حافظ';
        $level_icon = '🏆';
    } elseif ($points >= 200) {
        $level = 3;
        $level_name = 'متميز';
        $level_icon = '⭐';
    } elseif ($points >= 50) {
        $level = 2;
        $level_name = 'طالب';
        $level_icon = '📚';
    }
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("
            UPDATE student_points SET 
                total_points = ?,
                level = ?,
                level_name = ?
            WHERE student_id = ?
        ");
        $stmt->execute([$points, $level, $level_name, $student_id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO student_points (student_id, total_points, level, level_name)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $points, $level, $level_name]);
    }
    
    return ['points' => $points, 'level' => $level, 'level_name' => $level_name, 'level_icon' => $level_icon];
}

/**
 * تحديث السلسلة اليومية (Daily Streak)
 */
function updateDailyStreak($pdo, $student_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // جلب السلسلة الحالية
    $stmt = $pdo->prepare("SELECT daily_streak, best_streak, last_activity_date FROM student_points WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        // إنشاء سجل جديد
        $stmt = $pdo->prepare("
            INSERT INTO student_points (student_id, daily_streak, best_streak, last_activity_date) 
            VALUES (?, 1, 1, ?)
        ");
        $stmt->execute([$student_id, $today]);
        return 1;
    }
    
    $current_streak = $data['daily_streak'];
    $best_streak = $data['best_streak'];
    $last_date = $data['last_activity_date'];
    
    if ($last_date == $today) {
        // تم التحديث اليوم بالفعل
        return $current_streak;
    } elseif ($last_date == $yesterday) {
        // يوم متتالي - زيادة السلسلة
        $new_streak = $current_streak + 1;
        $new_best = max($best_streak, $new_streak);
        
        // مكافأة عند تحقيق إنجازات
        if ($new_streak == 7) {
            addPointsLog($pdo, $student_id, 20, 'streak_bonus', '🔥 مكافأة تحقيق 7 أيام متتالية');
        } elseif ($new_streak == 30) {
            addPointsLog($pdo, $student_id, 50, 'streak_bonus', '🏆 مكافأة تحقيق شهر كامل (30 يوم)');
        } elseif ($new_streak == 100) {
            addPointsLog($pdo, $student_id, 100, 'streak_bonus', '👑 مكافأة تحقيق 100 يوم متتالية');
        }
    } else {
        // انقطعت السلسلة - إعادة تعيين
        $new_streak = 1;
        $new_best = $best_streak;
    }
    
    $stmt = $pdo->prepare("
        UPDATE student_points SET 
            daily_streak = ?,
            best_streak = ?,
            last_activity_date = ?
        WHERE student_id = ?
    ");
    $stmt->execute([$new_streak, $new_best, $today, $student_id]);
    
    return $new_streak;
}

/**
 * جلب بيانات نقاط الطالب
 */
function getStudentPointsData($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT sp.*, 
               (SELECT COUNT(*) FROM points_log WHERE student_id = sp.student_id) as total_achievements
        FROM student_points sp
        WHERE sp.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        $data = updateStudentPoints($pdo, $student_id);
        $stmt = $pdo->prepare("SELECT * FROM student_points WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $data = $stmt->fetch();
    }
    
    // إضافة أيقونة المستوى
    $level_icons = [
        1 => '🌱',
        2 => '📚',
        3 => '⭐',
        4 => '🏆',
        5 => '👑'
    ];
    $data['level_icon'] = $level_icons[$data['level']] ?? '🌟';
    
    // حساب النسبة المئوية للمستوى التالي
    $next_level_points = [50, 200, 500, 1000, 1500];
    $current_level_index = $data['level'] - 1;
    $points_needed = isset($next_level_points[$current_level_index]) ? $next_level_points[$current_level_index] : 0;
    $points_to_next = max(0, $points_needed - $data['total_points']);
    $progress_percent = $points_needed > 0 ? min(100, round(($data['total_points'] / $points_needed) * 100)) : 100;
    
    $data['points_to_next'] = $points_to_next;
    $data['progress_percent'] = $progress_percent;
    
    return $data;
}
// ============================================
// دوال خاصة بأولياء الأمور
// ============================================

/**
 * الحصول على جميع أبناء ولي الأمر
 */
function getGuardianStudents($pdo, $guardian_id) {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as teacher_name
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.guardian_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$guardian_id]);
    return $stmt->fetchAll();
}

/**
 * إنشاء أو تحديث حساب ولي أمر من رقم الهاتف
 */
function getOrCreateGuardian($pdo, $phone, $student_name = null) {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean_phone) < 10) return null;
    
    // البحث عن ولي أمر موجود
    $stmt = $pdo->prepare("SELECT * FROM guardians WHERE phone = ?");
    $stmt->execute([$clean_phone]);
    $guardian = $stmt->fetch();
    
    if ($guardian) {
        return $guardian['id'];
    }
    
    // إنشاء ولي أمر جديد
    $guardian_name = $student_name ? "ولي أمر $student_name" : "ولي أمر (رقم: " . substr($clean_phone, -6) . ")";
    $username = 'guardian_' . time() . '_' . rand(100, 999);
    $hashed_password = password_hash($clean_phone, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO guardians (name, phone, username, password, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$guardian_name, $clean_phone, $username, $hashed_password]);
    
    return $pdo->lastInsertId();
}

/**
 * ربط طالب بولي أمره
 */
function linkStudentToGuardian($pdo, $student_id, $phone) {
    $guardian_id = getOrCreateGuardian($pdo, $phone);
    if ($guardian_id) {
        $stmt = $pdo->prepare("UPDATE students SET guardian_id = ? WHERE id = ?");
        $stmt->execute([$guardian_id, $student_id]);
        return true;
    }
    return false;
}
// ============================================
// أضف هذه الدوال في نهاية ملف functions.php
// ============================================

/**
 * إنشاء جدول الأوراد الإضافية إذا لم يكن موجوداً
 */
function createCustomWirdsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS custom_wirds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            wird_name VARCHAR(255) NOT NULL,
            wird_type ENUM('quran', 'adkar', 'prayer', 'other') DEFAULT 'other',
            points_per_unit INT DEFAULT 1,
            target_units INT DEFAULT 1,
            unit_type ENUM('page', 'ayah', 'time', 'count') DEFAULT 'count',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_type (wird_type)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS custom_wird_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            wird_id INT NOT NULL,
            record_date DATE NOT NULL,
            units_completed INT DEFAULT 0,
            points_earned INT DEFAULT 0,
            notes TEXT,
            recorded_by VARCHAR(50) DEFAULT 'student',
            recorded_by_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_wird (wird_id),
            INDEX idx_date (record_date),
            UNIQUE KEY unique_student_wird_date (student_id, wird_id, record_date)
        )
    ");
}



/**
 * جلب أبناء ولي الأمر (لولي الأمر)
 */
function getGuardianChildren($pdo, $guardian_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.category, t.name as teacher_name
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.guardian_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$guardian_id]);
    return $stmt->fetchAll();
}

// تأكد من وجود الجداول عند تحميل الملف
createCustomWirdsTable($pdo);

// ============================================
// دوال حفظ واسترجاع الحسابات
// ============================================

function saveAccountForUser($pdo, $saved_by_user_id, $account_id, $account_type, $account_name, $account_identifier) {
    // التحقق من وجود الجدول
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS saved_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                saved_by INT NOT NULL,
                account_id INT NOT NULL,
                account_type VARCHAR(50) NOT NULL,
                account_name VARCHAR(255) NOT NULL,
                account_identifier VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_saved_account (saved_by, account_id, account_type)
            )
        ");
    } catch (PDOException $e) {
        // الجدول موجود
    }
    
    $check = $pdo->prepare("SELECT id FROM saved_accounts WHERE saved_by = ? AND account_id = ? AND account_type = ?");
    $check->execute([$saved_by_user_id, $account_id, $account_type]);
    
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO saved_accounts (saved_by, account_id, account_type, account_name, account_identifier)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$saved_by_user_id, $account_id, $account_type, $account_name, $account_identifier]);
        return true;
    }
    return false;
}

function getSavedAccounts($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM saved_accounts WHERE saved_by = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getAccountTypeName($type) {
    $names = [
        'admin' => 'إدارة',
        'teacher' => 'معلم',
        'student' => 'طالب',
        'guardian' => 'ولي أمر'
    ];
    return $names[$type] ?? 'حساب';
}

function getAccountTypeIcon($type) {
    $icons = [
        'admin' => 'fa-user-cog',
        'teacher' => 'fa-chalkboard-teacher',
        'student' => 'fa-user-graduate',
        'guardian' => 'fa-user-tie'
    ];
    return $icons[$type] ?? 'fa-user';
}
?>