<?php
// ============================================
// ملف: ring_details.php - تفاصيل الحلقة (نسخة محسنة)
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');
$pageTitle = 'تفاصيل الحلقة';
require_once 'includes/header.php';

$ring_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب معلومات الحلقة
$stmt = $pdo->prepare("
    SELECT r.*, t.name as teacher_name, t.id as teacher_id, t.gender as teacher_gender
    FROM rings r
    LEFT JOIN teachers t ON r.teacher_id = t.id
    WHERE r.id = ?
");
$stmt->execute([$ring_id]);
$ring = $stmt->fetch();

if (!$ring) {
    echo '<div class="alert alert-error">الحلقة غير موجودة.</div>';
    require_once 'includes/footer.php';
    exit;
}

$canManage = isAdmin() || (isTeacher() && $ring['teacher_id'] == $_SESSION['user_id']);

// جلب الطلاب المسجلين في الحلقة مع إحصائيات
$studentsInRing = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_count,
           (SELECT COUNT(*) FROM attendance WHERE person_type='student' AND person_id = s.id AND status='present' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_count
    FROM students s
    JOIN ring_students rs ON s.id = rs.student_id
    WHERE rs.ring_id = ?
    ORDER BY s.name
");
$studentsInRing->execute([$ring_id]);
$studentsInRing = $studentsInRing->fetchAll();

// جلب المواعيد
$schedules = $pdo->prepare("
    SELECT * FROM ring_schedules 
    WHERE ring_id = ? 
    ORDER BY day_of_week
");
$schedules->execute([$ring_id]);
$schedules = $schedules->fetchAll();

// جلب الطلاب المتاحين للإضافة
$availableStudents = [];
if ($canManage) {
    if (isAdmin()) {
        $availableStudents = $pdo->query("
            SELECT id, name, category, level 
            FROM students 
            WHERE id NOT IN (SELECT student_id FROM ring_students WHERE ring_id = ?)
            ORDER BY name
        ")->fetchAll();
    } else {
        $availableStudents = $pdo->prepare("
            SELECT id, name, category, level 
            FROM students 
            WHERE teacher_id = ? AND id NOT IN (SELECT student_id FROM ring_students WHERE ring_id = ?)
            ORDER BY name
        ");
        $availableStudents->execute([$_SESSION['user_id'], $ring_id]);
        $availableStudents = $availableStudents->fetchAll();
    }
}

// معالجة إضافة طالب
if ($canManage && isset($_GET['add_student'])) {
    $student_id = (int)$_GET['add_student'];
    
    if (isTeacher()) {
        $checkTeacher = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
        $checkTeacher->execute([$student_id, $_SESSION['user_id']]);
        if (!$checkTeacher->fetch()) {
            header("Location: ring_details.php?id=$ring_id&error=not_allowed");
            exit;
        }
    }
    
    $check = $pdo->prepare("SELECT id FROM ring_students WHERE ring_id = ? AND student_id = ?");
    $check->execute([$ring_id, $student_id]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
        $stmt->execute([$ring_id, $student_id]);
        
        // تحديث عدد الطلاب في الحلقة
        if (function_exists('updateRingStudentsCount')) {
            updateRingStudentsCount($pdo, $ring_id);
        }
        
        // تحديث عدد طلاب المعلم
        if (function_exists('updateTeacherStudentsCount')) {
            updateTeacherStudentsCount($pdo, $ring['teacher_id']);
        }
    }
    header("Location: ring_details.php?id=$ring_id");
    exit;
}

// معالجة إزالة طالب
if ($canManage && isset($_GET['remove_student'])) {
    $student_id = (int)$_GET['remove_student'];
    $stmt = $pdo->prepare("DELETE FROM ring_students WHERE ring_id = ? AND student_id = ?");
    $stmt->execute([$ring_id, $student_id]);
    
    // تحديث عدد الطلاب في الحلقة
    if (function_exists('updateRingStudentsCount')) {
        updateRingStudentsCount($pdo, $ring_id);
    }
    
    // تحديث عدد طلاب المعلم
    if (function_exists('updateTeacherStudentsCount')) {
        updateTeacherStudentsCount($pdo, $ring['teacher_id']);
    }
    
    header("Location: ring_details.php?id=$ring_id");
    exit;
}

$days_of_week = [1 => 'الأحد', 2 => 'الإثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'];

function formatTimeArabic($time) {
    if (empty($time)) return '';
    $timestamp = strtotime($time);
    $hour = date('H', $timestamp);
    $minute = date('i', $timestamp);
    if ($hour < 12) {
        $period = 'صباحاً';
        $display_hour = $hour == 0 ? 12 : $hour;
    } else {
        $period = 'مساءً';
        $display_hour = $hour == 12 ? 12 : $hour - 12;
    }
    return $display_hour . ':' . $minute . ' ' . $period;
}

$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<style>
.ring-details-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.5rem;
}

.back-link {
    background: rgba(255,255,255,0.15);
    padding: 8px 20px;
    border-radius: 30px;
    color: white;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.3s;
}

.back-link:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.ring-info-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.ring-name {
    font-size: 1.4rem;
    font-weight: bold;
    color: #1e3c3f;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ring-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8f9fa;
    padding: 6px 15px;
    border-radius: 30px;
    color: #1e3c3f;
    font-size: 0.9rem;
}

.meta-item i {
    color: #c9a96b;
}

.schedules-section, .students-section, .add-student-section {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e3c3f;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #c9a96b;
}

.schedules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.schedule-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    border: 1px solid #e9ecef;
    transition: 0.3s;
}

.schedule-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #c9a96b;
}

.schedule-day {
    font-size: 1.1rem;
    font-weight: bold;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.schedule-time {
    color: #c9a96b;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.student-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: 0.3s;
    border: 1px solid #e9ecef;
}

.student-card:hover {
    transform: translateX(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #c9a96b;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.student-name {
    font-weight: bold;
    color: #1e3c3f;
}

.student-stats {
    font-size: 0.75rem;
    color: #666;
    margin-top: 3px;
}

.student-stats i {
    color: #c9a96b;
}

.remove-student {
    color: #dc3545;
    text-decoration: none;
    padding: 8px;
    border-radius: 50%;
    transition: 0.3s;
}

.remove-student:hover {
    background: #dc3545;
    color: white;
    transform: scale(1.1);
}

.add-student-form {
    display: flex;
    gap: 15px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.add-student-form select {
    flex: 2;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
}

.add-student-form button {
    flex: 1;
    padding: 12px 25px;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    border-radius: 30px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.add-student-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-transfer {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #212529;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.alert {
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border-right: 5px solid #17a2b8;
}

@media (max-width: 768px) {
    .ring-details-page { padding: 15px; }
    .page-header { flex-direction: column; text-align: center; }
    .ring-name { font-size: 1.2rem; }
    .schedules-grid { grid-template-columns: 1fr; }
    .students-grid { grid-template-columns: 1fr; }
    .add-student-form { flex-direction: column; }
    .add-student-form select, .add-student-form button { width: 100%; }
    .action-buttons { flex-direction: column; }
    .btn { width: 100%; }
}
</style>

<section class="ring-details-page">
    <div class="page-header">
        <h1><i class="fas fa-ring"></i> تفاصيل الحلقة</h1>
        <a href="rings.php" class="back-link"><i class="fas fa-arrow-right"></i> العودة للحلقات</a>
    </div>

    <?php if ($error == 'not_allowed'): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> لا يمكنك إضافة هذا الطالب لأنه ليس من طلابك.</div>
    <?php endif; ?>

    <div class="ring-info-card">
        <div class="ring-name"><i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?></div>
        <div class="ring-meta">
            <div class="meta-item"><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($ring['teacher_name'] ?? 'غير محدد'); ?></div>
            <?php if ($ring['location']): ?>
            <div class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ring['location']); ?></div>
            <?php endif; ?>
            <div class="meta-item"><i class="fas fa-users"></i> <?php echo count($studentsInRing); ?> طالب</div>
        </div>
        <?php if (!empty($ring['description'])): ?>
            <div class="ring-description" style="background: #f8f9fa; padding: 15px; border-radius: 12px; border-right: 3px solid #c9a96b;">
                <i class="fas fa-align-left"></i> <?php echo nl2br(htmlspecialchars($ring['description'])); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($schedules)): ?>
    <div class="schedules-section">
        <div class="section-title"><i class="fas fa-calendar-alt"></i> <h3>مواعيد الانعقاد</h3></div>
        <div class="schedules-grid">
            <?php foreach ($schedules as $sch): ?>
                <div class="schedule-card">
                    <div class="schedule-day"><?php echo $days_of_week[$sch['day_of_week']]; ?></div>
                    <div class="schedule-time"><i class="fas fa-clock"></i> <?php echo formatTimeArabic($sch['start_time']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="students-section">
        <div class="section-title"><i class="fas fa-users"></i> <h3>الطلاب المسجلين (<?php echo count($studentsInRing); ?>)</h3></div>
        
        <?php if (empty($studentsInRing)): ?>
            <div class="empty-state"><i class="fas fa-users-slash"></i> <p>لا يوجد طلاب في هذه الحلقة</p></div>
        <?php else: ?>
            <div class="students-grid">
                <?php foreach ($studentsInRing as $student): ?>
                    <div class="student-card">
                        <div class="student-info">
                            <div class="student-avatar"><?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?></div>
                            <div>
                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                <div class="student-stats">
                                    <span><i class="fas fa-quran"></i> <?php echo $student['memorized_count']; ?> سورة</span>
                                    <span><i class="fas fa-calendar-check"></i> حضور <?php echo $student['attendance_count']; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                            <a href="?id=<?php echo $ring_id; ?>&remove_student=<?php echo $student['id']; ?>" class="remove-student" onclick="return confirm('إزالة الطالب من الحلقة؟')"><i class="fas fa-times-circle"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage && !empty($availableStudents)): ?>
    <div class="add-student-section">
        <div class="section-title"><i class="fas fa-user-plus"></i> <h3>إضافة طالب جديد</h3></div>
        <form method="get" class="add-student-form">
            <input type="hidden" name="id" value="<?php echo $ring_id; ?>">
            <select name="add_student" required>
                <option value="">-- اختر طالباً --</option>
                <?php foreach ($availableStudents as $student): ?>
                    <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fas fa-plus-circle"></i> إضافة الطالب</button>
        </form>
    </div>
    <?php elseif ($canManage && empty($availableStudents)): ?>
    <div class="add-student-section">
        <div class="section-title"><i class="fas fa-user-plus"></i> <h3>إضافة طالب جديد</h3></div>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> لا يوجد طلاب متاحون للإضافة. يمكنك إضافة طلاب من خلال صفحة الطلاب.</div>
    </div>
    <?php endif; ?>

    <div class="action-buttons">
        <a href="rings.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> العودة للحلقات</a>
        <?php if ($canManage): ?>
            <a href="edit_ring.php?id=<?php echo $ring_id; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> تعديل الحلقة</a>
            <a href="transfer_ring.php?ring_id=<?php echo $ring_id; ?>" class="btn btn-transfer"><i class="fas fa-exchange-alt"></i> نقل الحلقة</a>
            <a href="delete_ring.php?id=<?php echo $ring_id; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الحلقة؟')"><i class="fas fa-trash"></i> حذف الحلقة</a>
        <?php endif; ?>
        <?php if (count($studentsInRing) > 0): ?>
            <a href="attendance_students_teacher.php?ring_id=<?php echo $ring_id; ?>" class="btn btn-primary"><i class="fas fa-calendar-check"></i> تسجيل حضور</a>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>