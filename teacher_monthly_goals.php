<?php
// ============================================
// ملف: teacher_monthly_goals.php - إدارة الأهداف
// آخر تحديث: 2026-03-14
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isTeacher() && !isAdmin()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'الأهداف الشهرية للطلاب';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$is_admin = isAdmin();

$current_hijri = getHijriDate();
$hijri_year = $current_hijri['year'];
$hijri_month = $current_hijri['month'];

// ============================================
// معالجة حفظ الهدف
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_goal'])) {
    $student_id = (int)$_POST['student_id'];
    
    $from_surah = !empty($_POST['from_surah']) ? (int)$_POST['from_surah'] : null;
    $from_ayah = !empty($_POST['from_ayah']) ? (int)$_POST['from_ayah'] : null;
    $to_surah = !empty($_POST['to_surah']) ? (int)$_POST['to_surah'] : null;
    $to_ayah = !empty($_POST['to_ayah']) ? (int)$_POST['to_ayah'] : null;
    
    $notes = trim($_POST['notes'] ?? '');
    
    if (isTeacher()) {
        $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
        $check->execute([$student_id, $teacher_id]);
        if (!$check->fetch()) {
            $_SESSION['error'] = "❌ هذا الطالب ليس من طلابك";
            header("Location: teacher_monthly_goals.php");
            exit;
        }
    }
    
    // حساب إجمالي الآيات
    $total_ayahs = calculateTotalAyahs($from_surah, $from_ayah, $to_surah, $to_ayah);
    
    $exists = $pdo->prepare("
        SELECT id FROM student_monthly_goals 
        WHERE student_id = ? AND hijri_year = ? AND hijri_month_id = ?
    ");
    $exists->execute([$student_id, $hijri_year, $hijri_month]);
    
    if ($exists->fetch()) {
        $stmt = $pdo->prepare("
            UPDATE student_monthly_goals SET
                from_surah = ?,
                from_ayah = ?,
                to_surah = ?,
                to_ayah = ?,
                total_ayahs = ?,
                notes = ?,
                updated_at = NOW()
            WHERE student_id = ? AND hijri_year = ? AND hijri_month_id = ?
        ");
        $stmt->execute([
            $from_surah, $from_ayah, $to_surah, $to_ayah,
            $total_ayahs, $notes, $student_id, $hijri_year, $hijri_month
        ]);
        $_SESSION['success'] = "✅ تم تحديث الهدف الشهري للطالب";
    } else {
        $teacher_field = isTeacher() ? $teacher_id : ($_POST['teacher_id'] ?? null);
        $stmt = $pdo->prepare("
            INSERT INTO student_monthly_goals 
            (student_id, teacher_id, hijri_year, hijri_month_id, 
             from_surah, from_ayah, to_surah, to_ayah, total_ayahs, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $student_id, $teacher_field, $hijri_year, $hijri_month,
            $from_surah, $from_ayah, $to_surah, $to_ayah, $total_ayahs, $notes
        ]);
        $_SESSION['success'] = "✅ تم إضافة الهدف الشهري للطالب";
    }
    
    header("Location: teacher_monthly_goals.php");
    exit;
}

// ============================================
// دالة حساب إجمالي الآيات
// ============================================
function calculateTotalAyahs($from_surah, $from_ayah, $to_surah, $to_ayah) {
    $surah_ayahs = [
        1 => 7, 2 => 286, 3 => 200, 4 => 176, 5 => 120, 6 => 165, 7 => 206,
        8 => 75, 9 => 129, 10 => 109, 11 => 123, 12 => 111, 13 => 43, 14 => 52,
        15 => 99, 16 => 128, 17 => 111, 18 => 110, 19 => 98, 20 => 135,
        21 => 112, 22 => 78, 23 => 118, 24 => 64, 25 => 77, 26 => 227, 27 => 93,
        28 => 88, 29 => 69, 30 => 60, 31 => 34, 32 => 30, 33 => 73, 34 => 54,
        35 => 45, 36 => 83, 37 => 182, 38 => 88, 39 => 75, 40 => 85, 41 => 54,
        42 => 53, 43 => 89, 44 => 59, 45 => 37, 46 => 35, 47 => 38, 48 => 29,
        49 => 18, 50 => 45, 51 => 60, 52 => 49, 53 => 62, 54 => 55, 55 => 78,
        56 => 96, 57 => 29, 58 => 22, 59 => 24, 60 => 13, 61 => 14, 62 => 11,
        63 => 11, 64 => 18, 65 => 12, 66 => 12, 67 => 30, 68 => 52, 69 => 52,
        70 => 44, 71 => 28, 72 => 28, 73 => 20, 74 => 56, 75 => 40, 76 => 31,
        77 => 50, 78 => 40, 79 => 46, 80 => 42, 81 => 29, 82 => 19, 83 => 36,
        84 => 25, 85 => 22, 86 => 17, 87 => 19, 88 => 26, 89 => 30, 90 => 20,
        91 => 15, 92 => 21, 93 => 11, 94 => 8, 95 => 8, 96 => 19, 97 => 5,
        98 => 8, 99 => 8, 100 => 11, 101 => 11, 102 => 8, 103 => 3, 104 => 9,
        105 => 5, 106 => 4, 107 => 7, 108 => 3, 109 => 6, 110 => 3, 111 => 5,
        112 => 4, 113 => 5, 114 => 6
    ];
    
    if (!$from_surah || !$to_surah) return 0;
    
    if ($from_surah == $to_surah) {
        $to = $to_ayah ?: $surah_ayahs[$from_surah];
        $from = $from_ayah ?: 1;
        return ($to - $from + 1);
    } else {
        $count = 0;
        // آيات السورة الأولى
        $first_to = $surah_ayahs[$from_surah];
        $count += ($first_to - ($from_ayah ?: 1) + 1);
        
        // السور الكاملة
        for ($i = $from_surah + 1; $i < $to_surah; $i++) {
            $count += $surah_ayahs[$i] ?? 0;
        }
        
        // آيات السورة الأخيرة
        $count += $to_ayah ?: $surah_ayahs[$to_surah];
        
        return $count;
    }
}

// جلب قائمة المعلمين
$teachers = [];
if ($is_admin) {
    $teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
}

// جلب الطلاب مع أهدافهم
if (isTeacher()) {
    $students = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.level,
            s.parent_phone,
            g.id as goal_id,
            g.from_surah,
            g.from_ayah,
            g.to_surah,
            g.to_ayah,
            g.total_ayahs,
            g.memorized_ayahs,
            g.completed,
            g.completed_at,
            g.notes as goal_notes,
            a.id as achievement_id,
            a.certificate_number,
            a.achieved_at
        FROM students s
        LEFT JOIN student_monthly_goals g ON s.id = g.student_id 
            AND g.hijri_year = ? AND g.hijri_month_id = ?
        LEFT JOIN student_achievements a ON g.id = a.goal_id
        WHERE s.teacher_id = ?
        GROUP BY s.id
        ORDER BY s.name
    ");
    $students->execute([$hijri_year, $hijri_month, $teacher_id]);
    $students = $students->fetchAll();
} else {
    $filter_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    
    $query = "
        SELECT 
            s.id,
            s.name,
            s.level,
            s.parent_phone,
            t.name as teacher_name,
            t.id as teacher_id,
            g.id as goal_id,
            g.from_surah,
            g.from_ayah,
            g.to_surah,
            g.to_ayah,
            g.total_ayahs,
            g.memorized_ayahs,
            g.completed,
            g.completed_at,
            g.notes as goal_notes,
            a.id as achievement_id,
            a.certificate_number,
            a.achieved_at
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN student_monthly_goals g ON s.id = g.student_id 
            AND g.hijri_year = ? AND g.hijri_month_id = ?
        LEFT JOIN student_achievements a ON g.id = a.goal_id
        WHERE 1=1
    ";
    
    $params = [$hijri_year, $hijri_month];
    
    if ($filter_teacher > 0) {
        $query .= " AND s.teacher_id = ?";
        $params[] = $filter_teacher;
    }
    
    $query .= " GROUP BY s.id ORDER BY t.name, s.name";
    
    $students = $pdo->prepare($query);
    $students->execute($params);
    $students = $students->fetchAll();
}

$hijri_months = $pdo->query("SELECT * FROM hijri_months ORDER BY order_num")->fetchAll();

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$total_students = is_array($students) ? count($students) : 0;
$students_with_goals = count(array_filter($students, fn($s) => !empty($s['goal_id'])));
$students_achieved = count(array_filter($students, fn($s) => !empty($s['achievement_id'])));
$students_completed = count(array_filter($students, fn($s) => !empty($s['completed']) && empty($s['achievement_id'])));
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --success: #28a745;
    --success-light: #d4edda;
    --warning: #ffc107;
    --warning-light: #fff3cd;
    --danger: #dc3545;
    --info: #17a2b8;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
.goals-page { max-width: 1200px; margin: 0 auto; padding: 10px; }

/* رأس الصفحة */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 20px;
    border-radius: 25px;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.page-header h1 { font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
.page-header h1 i { color: var(--secondary); }

.hijri-date {
    background: rgba(255,255,255,0.15);
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
}

/* إحصائيات */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
}
.stat-number { font-size: 1.4rem; font-weight: 700; color: var(--primary); }
.stat-label { font-size: 0.8rem; color: #666; }

/* بطاقة الطالب */
.student-card {
    background: white;
    border-radius: 20px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.student-card.completed { border-right: 6px solid var(--success); background: var(--success-light); }
.student-card.progress { border-right: 6px solid var(--warning); background: var(--warning-light); }

.student-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.student-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
    border: 2px solid var(--secondary);
}
.student-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.status-badge {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.status-badge.completed { background: var(--success-light); color: #155724; }
.status-badge.progress { background: var(--warning-light); color: #856404; }

/* نطاق الحفظ */
.goal-range {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 12px;
    margin: 12px 0;
    border-right: 4px solid var(--secondary);
    font-size: 0.95rem;
    color: var(--primary);
    font-weight: 600;
}
.goal-range i { color: var(--secondary); margin-left: 8px; }

/* شريط التقدم */
.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 8px 0;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #20c997);
    border-radius: 10px;
    transition: width 0.3s;
}

/* أزرار الإجراءات */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}
.btn {
    padding: 10px;
    border: none;
    border-radius: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    width: 100%;
}
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-info { background: var(--info); color: white; }

/* نافذة منبثقة */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    padding: 10px;
}
.modal.show { display: flex; align-items: center; justify-content: center; }
.modal-content {
    background: white;
    border-radius: 25px;
    padding: 20px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--secondary);
}
.modal-header h3 { color: var(--primary); font-size: 1.2rem; }
.close-btn { background: none; border: none; font-size: 2rem; cursor: pointer; color: #999; }

.student-name-box {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 12px;
    border-radius: 15px;
    margin-bottom: 15px;
    text-align: center;
}

.input-group { margin-bottom: 15px; }
.input-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--primary); }
.input-group input, .input-group select {
    width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 10px;
}

.range-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 15px;
    margin-bottom: 15px;
}
.range-box.from { border-right: 4px solid var(--success); }
.range-box.to { border-right: 4px solid var(--danger); }

.range-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.example-box {
    background: var(--warning-light);
    padding: 10px;
    border-radius: 10px;
    font-size: 0.85rem;
    color: #856404;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 10px 0;
}

.modal-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 15px;
}

@media (min-width: 480px) {
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
    .action-buttons { flex-direction: row; }
    .modal-actions { flex-direction: row; }
}
</style>

<section class="goals-page">
    <div class="page-header">
        <h1><i class="fas fa-bullseye"></i> الأهداف الشهرية</h1>
        <div class="hijri-date">
            <i class="fas fa-calendar-alt"></i>
            <?php echo isset($hijri_months[$hijri_month-1]['name_ar']) ? $hijri_months[$hijri_month-1]['name_ar'] : ''; ?> <?php echo $hijri_year; ?> هـ
        </div>
    </div>

    <?php if ($success_message): ?>
        <div style="background: var(--success-light); color: #155724; padding: 12px; border-radius: 15px; margin-bottom: 15px;">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 15px; margin-bottom: 15px;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($is_admin && !empty($teachers)): ?>
    <div style="background: white; border-radius: 20px; padding: 15px; margin-bottom: 20px;">
        <form method="get">
            <select name="teacher_id" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 15px; margin-bottom: 10px;">
                <option value="0">جميع المعلمين</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo (isset($_GET['teacher_id']) && $_GET['teacher_id'] == $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 15px;">
                <i class="fas fa-filter"></i> عرض
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (empty($students)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 25px;">
            <i class="fas fa-users-slash" style="font-size: 3rem; color: #dee2e6;"></i>
            <h3 style="margin: 10px 0; color: var(--primary);">لا يوجد طلاب</h3>
        </div>
    <?php else: ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-number"><?php echo $total_students; ?></div><div class="stat-label">إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-bullseye"></i></div><div class="stat-number"><?php echo $students_with_goals; ?></div><div class="stat-label">لديهم أهداف</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-spinner"></i></div><div class="stat-number"><?php echo $students_completed; ?></div><div class="stat-label">أكملوا الهدف</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-certificate"></i></div><div class="stat-number"><?php echo $students_achieved; ?></div><div class="stat-label">حصلوا على شهادة</div></div>
    </div>

    <div style="margin-bottom: 20px;">
        <input type="text" id="searchStudent" placeholder="ابحث عن طالب..." style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 20px;">
    </div>

    <?php foreach ($students as $student): 
        $student_id = $student['id'] ?? 0;
        $student_name = $student['name'] ?? 'غير معروف';
        $has_goal = !empty($student['goal_id']);
        $has_achievement = !empty($student['achievement_id']);
        $is_completed = !empty($student['completed']);
        
        $card_class = $has_achievement ? 'completed' : ($is_completed ? 'completed' : ($has_goal ? 'progress' : ''));
        
        $from_surah = $student['from_surah'] ?? null;
        $from_ayah = $student['from_ayah'] ?? null;
        $to_surah = $student['to_surah'] ?? null;
        $to_ayah = $student['to_ayah'] ?? null;
        
        $range_text = '';
        if ($from_surah && $to_surah) {
            $from_name = getSurahName($from_surah);
            $to_name = getSurahName($to_surah);
            $range_text = "من ";
            if ($from_ayah) $range_text .= "الآية {$from_ayah} ";
            $range_text .= "من سورة {$from_name} إلى ";
            if ($to_ayah) $range_text .= "الآية {$to_ayah} ";
            $range_text .= "من سورة {$to_name}";
        }
        
        $progress = $student['total_ayahs'] ? round(($student['memorized_ayahs'] / $student['total_ayahs']) * 100) : 0;
    ?>
        <div class="student-card <?php echo $card_class; ?>" data-student-name="<?php echo strtolower($student_name); ?>">
            <div class="student-header">
                <div class="student-avatar"><?php echo mb_substr($student_name, 0, 1, 'UTF-8'); ?></div>
                <div style="flex: 1;">
                    <div class="student-name">
                        <?php echo htmlspecialchars($student_name); ?>
                        <?php if ($has_achievement): ?><span class="status-badge completed"><i class="fas fa-certificate"></i> شهادة</span><?php endif; ?>
                        <?php if ($is_completed && !$has_achievement): ?><span class="status-badge completed"><i class="fas fa-check-circle"></i> اكتمل</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($has_goal && $range_text): ?>
               <div class="goal-range"><i class="fas fa-quran"></i> <?php echo $range_text; ?></div>
                <?php if ($student['total_ayahs'] > 0): ?>
                    <div style="margin: 10px 0;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem;">
                            <span>التقدم: <?php echo $student['memorized_ayahs']; ?>/<?php echo $student['total_ayahs']; ?> آية</span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openGoalModal(<?php echo $student_id; ?>, '<?php echo addslashes($student_name); ?>', <?php echo $from_surah ?: 'null'; ?>, <?php echo $from_ayah ?: 'null'; ?>, <?php echo $to_surah ?: 'null'; ?>, <?php echo $to_ayah ?: 'null'; ?>, '<?php echo addslashes($student['goal_notes'] ?? ''); ?>')">
                    <i class="fas fa-bullseye"></i> <?php echo $has_goal ? 'تعديل الهدف' : 'تحديد هدف'; ?>
                </button>

                <?php if ($is_completed && !$has_achievement): ?>
                    <a href="grant_certificate.php?student_id=<?php echo $student_id; ?>" class="btn btn-success"><i class="fas fa-certificate"></i> منح الشهادة</a>
                <?php endif; ?>

                <?php if ($has_achievement): ?>
                    <a href="certificate_view.php?id=<?php echo $student['achievement_id']; ?>" target="_blank" class="btn btn-info"><i class="fas fa-eye"></i> عرض الشهادة</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- نافذة تحديد الهدف -->
    <div class="modal" id="goalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-bullseye"></i> تحديد نطاق الحفظ</h3>
                <button class="close-btn" onclick="closeGoalModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="student_id" id="goalStudentId">
                <div class="student-name-box" id="goalStudentName"></div>

                <div class="range-box from">
                    <h4><i class="fas fa-play" style="color: var(--success);"></i> من</h4>
                    <div class="range-grid">
                        <div class="input-group">
                            <label>السورة</label>
                            <select name="from_surah" id="fromSurah" required>
                                <option value="">اختر</option>
                                <?php for ($i = 1; $i <= 114; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>رقم الآية</label>
                            <input type="number" name="from_ayah" id="fromAyah" min="1" placeholder="اختياري">
                        </div>
                    </div>
                </div>
                
                <div class="range-box to">
                    <h4><i class="fas fa-stop" style="color: var(--danger);"></i> إلى</h4>
                    <div class="range-grid">
                        <div class="input-group">
                            <label>السورة</label>
                            <select name="to_surah" id="toSurah" required>
                                <option value="">اختر</option>
                                <?php for ($i = 1; $i <= 114; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>رقم الآية</label>
                            <input type="number" name="to_ayah" id="toAyah" min="1" placeholder="اختياري">
                        </div>
                    </div>
                </div>

                <div class="example-box"><i class="fas fa-info-circle"></i> مثال: من الآية 30 سورة النبأ إلى الآية 32 سورة النازعات</div>

                <div class="input-group">
                    <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                    <textarea name="notes" id="goalNotes" rows="3" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 10px;"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeGoalModal()" style="background: #6c757d; color: white;">إلغاء</button>
                    <button type="submit" name="save_goal" class="btn btn-primary">حفظ الهدف</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function openGoalModal(studentId, studentName, fromSurah, fromAyah, toSurah, toAyah, notes) {
    document.getElementById('goalStudentId').value = studentId;
    document.getElementById('goalStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('fromSurah').value = fromSurah || '';
    document.getElementById('fromAyah').value = fromAyah || '';
    document.getElementById('toSurah').value = toSurah || '';
    document.getElementById('toAyah').value = toAyah || '';
    document.getElementById('goalNotes').value = notes || '';
    document.getElementById('goalModal').classList.add('show');
}
function closeGoalModal() { document.getElementById('goalModal').classList.remove('show'); }
window.onclick = function(event) { if (event.target.classList.contains('modal')) closeGoalModal(); }

document.getElementById('searchStudent').addEventListener('keyup', function() {
    const search = this.value.toLowerCase().trim();
    document.querySelectorAll('.student-card').forEach(card => {
        const name = card.getAttribute('data-student-name') || '';
        card.style.display = name.includes(search) || search === '' ? 'block' : 'none';
    });
});
</script>

<?php ob_end_flush(); require_once 'includes/footer.php'; ?>