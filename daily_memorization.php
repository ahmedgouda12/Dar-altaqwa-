<?php
// ============================================
// ملف: daily_memorization.php
// تسجيل الحفظ اليومي المستقل
// آخر تحديث: 2026-03-17
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تسجيل الحفظ اليومي';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$today = date('Y-m-d');

// جلب طلاب المعلم
if (isTeacher()) {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.parent_phone,
               sc.preferred_time,
               (SELECT COUNT(*) FROM daily_memorization WHERE student_id = s.id AND memorized_date = ?) as recorded_today
        FROM students s
        LEFT JOIN student_daily_schedule sc ON s.id = sc.student_id
        WHERE s.teacher_id = ?
        ORDER BY sc.preferred_time IS NULL, sc.preferred_time, s.name
    ");
    $students->execute([$today, $teacher_id]);
    $students = $students->fetchAll();
} else {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.parent_phone, t.name as teacher_name,
               sc.preferred_time,
               (SELECT COUNT(*) FROM daily_memorization WHERE student_id = s.id AND memorized_date = ?) as recorded_today
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN student_daily_schedule sc ON s.id = sc.student_id
        ORDER BY sc.preferred_time IS NULL, sc.preferred_time, s.name
    ");
    $students->execute([$today]);
    $students = $students->fetchAll();
}

// إحصائيات سريعة
$stats = [
    'total' => count($students),
    'recorded_today' => count(array_filter($students, fn($s) => $s['recorded_today'] > 0)),
    'with_schedule' => count(array_filter($students, fn($s) => !empty($s['preferred_time']))),
    'without_schedule' => count(array_filter($students, fn($s) => empty($s['preferred_time'])))
];

// رسائل النجاح/الخطأ
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

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

.daily-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 25px 30px;
    border-radius: 30px;
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
}

.page-header h1 i {
    color: var(--secondary);
}

.date-badge {
    background: rgba(255,255,255,0.15);
    padding: 10px 25px;
    border-radius: 50px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ===== إحصائيات سريعة ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== أزرار الإجراءات ===== */
.action-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #138496);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* ===== شبكة الطلاب ===== */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.student-card {
    background: white;
    border-radius: 25px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
    position: relative;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.student-card.recorded {
    border-right: 6px solid var(--success);
}

.student-card.late {
    border-right: 6px solid var(--warning);
}

.student-card.missed {
    border-right: 6px solid var(--danger);
}

.student-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: bold;
    border: 3px solid var(--secondary);
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 3px;
}

.student-level {
    color: #666;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.student-level i {
    color: var(--secondary);
}

/* ===== معلومات الموعد ===== */
.schedule-info {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
}

.schedule-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px dashed #dee2e6;
}

.schedule-row:last-child {
    border-bottom: none;
}

.schedule-row i {
    color: var(--secondary);
    width: 25px;
}

.schedule-time {
    font-weight: 700;
    color: var(--primary);
}

/* ===== شريط التقدم ===== */
.progress-container {
    margin: 15px 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 0.85rem;
    color: #666;
}

.progress-bar {
    height: 20px;
    background: #e9ecef;
    border-radius: 30px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #20c997);
    border-radius: 30px;
    transition: width 0.3s;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 8px;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
}

/* ===== أزرار الإجراءات ===== */
.student-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.action-btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-width: 100px;
}

.action-btn.record {
    background: var(--success);
    color: white;
}

.action-btn.schedule {
    background: var(--info);
    color: white;
}

.action-btn.view {
    background: var(--primary);
    color: white;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* ===== رسائل ===== */
.alert {
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid var(--success);
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid var(--danger);
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .student-actions {
        flex-direction: column;
    }
}
</style>

<section class="daily-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-pen-alt"></i>
            تسجيل الحفظ اليومي
        </h1>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?php echo $today; ?>
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--success);"><?php echo $stats['recorded_today']; ?></div>
            <div class="stat-label">سجل اليوم</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['with_schedule']; ?></div>
            <div class="stat-label">لديهم موعد</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--warning);"><?php echo $stats['without_schedule']; ?></div>
            <div class="stat-label">بدون موعد</div>
        </div>
    </div>

    <!-- أزرار الإجراءات -->
    <div class="action-buttons">
        <a href="set_student_schedule.php" class="btn btn-info">
            <i class="fas fa-clock"></i>
            تحديد مواعيد الحفظ
        </a>
        <a href="daily_commitment_report.php" class="btn btn-primary">
            <i class="fas fa-chart-line"></i>
            تقرير الالتزام
        </a>
    </div>

    <!-- قائمة الطلاب -->
    <div class="students-grid">
        <?php foreach ($students as $student): 
            $recorded_today = $student['recorded_today'] > 0;
            $has_schedule = !empty($student['preferred_time']);
            $card_class = $recorded_today ? 'recorded' : ($has_schedule ? 'missed' : '');
        ?>
            <div class="student-card <?php echo $card_class; ?>">
                <div class="student-header">
                    <div class="student-avatar">
                        <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div class="student-info">
                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                        <div class="student-level">
                            <i class="fas fa-level-up-alt"></i>
                            <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?>
                            <?php if (!empty($student['teacher_name'])): ?>
                                <span style="margin-right: 10px;">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?php echo htmlspecialchars($student['teacher_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($has_schedule): ?>
                    <div class="schedule-info">
                        <div class="schedule-row">
                            <i class="fas fa-clock"></i>
                            <span>الموعد المفضل:</span>
                            <span class="schedule-time"><?php echo date('h:i A', strtotime($student['preferred_time'])); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($recorded_today): ?>
                    <div style="background: #d4edda; padding: 12px; border-radius: 12px; color: #155724; margin: 10px 0;">
                        <i class="fas fa-check-circle"></i>
                        تم تسجيل الحفظ اليوم ✅
                    </div>
                <?php elseif ($has_schedule): ?>
                    <div style="background: #f8d7da; padding: 12px; border-radius: 12px; color: #721c24; margin: 10px 0;">
                        <i class="fas fa-exclamation-circle"></i>
                        لم يسجل الحفظ اليوم ⏰
                    </div>
                <?php endif; ?>

                <div class="student-actions">
                    <?php if (!$recorded_today): ?>
                        <button class="action-btn record" onclick="window.location.href='record_daily_memorization.php?student_id=<?php echo $student['id']; ?>'">
                            <i class="fas fa-save"></i>
                            تسجيل الحفظ
                        </button>
                    <?php endif; ?>
                    
                    <a href="set_student_schedule.php?student_id=<?php echo $student['id']; ?>" class="action-btn schedule">
                        <i class="fas fa-clock"></i>
                        تحديد موعد
                    </a>
                    
                    <a href="daily_commitment_report.php?student_id=<?php echo $student['id']; ?>" class="action-btn view">
                        <i class="fas fa-chart-line"></i>
                        التقرير
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>