<?php
// ============================================
// ملف: add_students_to_ring.php - إضافة طلاب للحلقة (نسخة محسنة)
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');
$pageTitle = 'إضافة طلاب إلى الحلقة';
require_once 'includes/header.php';

$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;

// جلب معلومات الحلقة
$stmt = $pdo->prepare("SELECT r.*, t.name as teacher_name FROM rings r LEFT JOIN teachers t ON r.teacher_id = t.id WHERE r.id = ?");
$stmt->execute([$ring_id]);
$ring = $stmt->fetch();

if (!$ring) {
    echo '<div class="alert alert-error">الحلقة غير موجودة.</div>';
    require_once 'includes/footer.php';
    exit;
}

// التحقق من الصلاحية
$canManage = isAdmin() || (isTeacher() && $ring['teacher_id'] == $_SESSION['user_id']);
if (!$canManage) {
    echo '<div class="alert alert-error">لا تملك صلاحية الوصول لهذه الصفحة.</div>';
    require_once 'includes/footer.php';
    exit;
}

// جلب الطلاب المسجلين بالفعل في الحلقة
$studentsInRing = $pdo->prepare("SELECT student_id FROM ring_students WHERE ring_id = ?");
$studentsInRing->execute([$ring_id]);
$inRing = $studentsInRing->fetchAll(PDO::FETCH_COLUMN);

// جلب الطلاب المتاحين
if (isAdmin()) {
    $availableStudents = $pdo->query("SELECT id, name, category, level FROM students ORDER BY name")->fetchAll();
} else {
    $availableStudents = $pdo->prepare("SELECT id, name, category, level FROM students WHERE teacher_id = ? ORDER BY name");
    $availableStudents->execute([$_SESSION['user_id']]);
    $availableStudents = $availableStudents->fetchAll();
}

$message = '';
$message_type = '';

// معالجة إضافة طلاب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_students'])) {
    $selected_students = isset($_POST['students']) ? $_POST['students'] : [];
    $added = 0;
    $skipped = 0;
    
    foreach ($selected_students as $student_id) {
        if (!in_array($student_id, $inRing)) {
            $stmt = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
            $stmt->execute([$ring_id, $student_id]);
            $added++;
        } else {
            $skipped++;
        }
    }
    
    if ($added > 0) {
        // تحديث عدد الطلاب في الحلقة
        updateRingStudentsCount($pdo, $ring_id);
        
        // تحديث عدد طلاب المعلم
        if (function_exists('updateTeacherStudentsCount')) {
            updateTeacherStudentsCount($pdo, $ring['teacher_id']);
        }
        
        $message = "✅ تم إضافة $added طالب بنجاح" . ($skipped > 0 ? " (تخطي $skipped طالب مضاف مسبقاً)" : "");
        $message_type = 'success';
        
        // تحديث قائمة الطلاب المسجلين
        $studentsInRing = $pdo->prepare("SELECT student_id FROM ring_students WHERE ring_id = ?");
        $studentsInRing->execute([$ring_id]);
        $inRing = $studentsInRing->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $message = $skipped > 0 ? "⚠️ جميع الطلاب المحددين مضافون مسبقاً" : "⚠️ لم يتم إضافة أي طالب";
        $message_type = 'warning';
    }
}

// معالجة إزالة طالب
if (isset($_GET['remove']) && $canManage) {
    $student_id = (int)$_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM ring_students WHERE ring_id = ? AND student_id = ?");
    $stmt->execute([$ring_id, $student_id]);
    
    // تحديث عدد الطلاب في الحلقة
    updateRingStudentsCount($pdo, $ring_id);
    
    // تحديث عدد طلاب المعلم
    if (function_exists('updateTeacherStudentsCount')) {
        updateTeacherStudentsCount($pdo, $ring['teacher_id']);
    }
    
    // تحديث القوائم
    $studentsInRing = $pdo->prepare("SELECT student_id FROM ring_students WHERE ring_id = ?");
    $studentsInRing->execute([$ring_id]);
    $inRing = $studentsInRing->fetchAll(PDO::FETCH_COLUMN);
    
    $message = "✅ تم إزالة الطالب من الحلقة";
    $message_type = 'success';
}

// جلب تفاصيل الطلاب المسجلين
$ring_students_details = [];
if (!empty($inRing)) {
    $placeholders = implode(',', array_fill(0, count($inRing), '?'));
    $stmt = $pdo->prepare("SELECT id, name, category, level FROM students WHERE id IN ($placeholders) ORDER BY name");
    $stmt->execute($inRing);
    $ring_students_details = $stmt->fetchAll();
}
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

.add-students-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
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

.ring-info-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.ring-name {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.ring-stats {
    background: var(--secondary);
    color: var(--primary);
    padding: 5px 20px;
    border-radius: 30px;
    font-weight: bold;
}

.form-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--secondary);
}

.students-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 15px;
    padding: 10px;
    margin-bottom: 20px;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    transition: 0.3s;
}

.student-item:hover {
    background: #f8f9fa;
}

.student-item:last-child {
    border-bottom: none;
}

.student-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 600;
    color: var(--primary);
}

.student-details {
    font-size: 0.75rem;
    color: #666;
    display: flex;
    gap: 10px;
    margin-top: 3px;
}

.student-status {
    font-size: 0.7rem;
    padding: 3px 10px;
    border-radius: 30px;
}

.status-added {
    background: #d4edda;
    color: #155724;
}

.status-available {
    background: #e9ecef;
    color: #6c757d;
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

.btn-primary {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--secondary);
    color: var(--primary);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.alert {
    padding: 15px;
    border-radius: 12px;
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

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-right: 5px solid var(--warning);
}

.registered-students {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}

.registered-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.registered-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 15px;
    transition: 0.3s;
}

.registered-item:hover {
    background: #e9ecef;
}

.remove-link {
    color: var(--danger);
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 20px;
    transition: 0.3s;
}

.remove-link:hover {
    background: var(--danger);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

@media (max-width: 768px) {
    .add-students-page { padding: 15px; }
    .page-header { flex-direction: column; text-align: center; }
    .ring-info-card { flex-direction: column; text-align: center; }
    .action-buttons { flex-direction: column; }
    .btn { width: 100%; }
    .registered-grid { grid-template-columns: 1fr; }
}
</style>

<section class="add-students-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-user-plus"></i>
            إضافة طلاب إلى الحلقة
        </h1>
        <div class="ring-badge">
            <i class="fas fa-ring"></i>
            <?php echo htmlspecialchars($ring['name']); ?>
        </div>
    </div>

    <div class="ring-info-card">
        <div class="ring-name">
            <i class="fas fa-chalkboard-teacher"></i>
            <?php echo htmlspecialchars($ring['teacher_name']); ?>
        </div>
        <div class="ring-stats">
            <i class="fas fa-users"></i> <?php echo count($ring_students_details); ?> طالب مسجل
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="section-title">
            <i class="fas fa-list"></i>
            <h3>اختر الطلاب لإضافتهم</h3>
        </div>

        <form method="post" id="addStudentsForm">
            <div class="students-list">
                <?php if (count($availableStudents) > 0): ?>
                    <?php foreach ($availableStudents as $student): 
                        $isAdded = in_array($student['id'], $inRing);
                        $cat_name = ($student['category'] == 'boy') ? 'أولاد' : (($student['category'] == 'girl') ? 'بنات' : (($student['category'] == 'child') ? 'أطفال' : 'نساء'));
                    ?>
                        <div class="student-item">
                            <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>" 
                                   class="student-checkbox" <?php echo $isAdded ? 'disabled' : ''; ?>>
                            <div class="student-avatar">
                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="student-info">
                                <div class="student-name">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                </div>
                                <div class="student-details">
                                    <span><i class="fas fa-tag"></i> <?php echo $cat_name; ?></span>
                                    <span><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                                </div>
                            </div>
                            <?php if ($isAdded): ?>
                                <div class="student-status status-added">
                                    <i class="fas fa-check-circle"></i> مضاف مسبقاً
                                </div>
                            <?php else: ?>
                                <div class="student-status status-available">
                                    <i class="fas fa-plus-circle"></i> متاح للإضافة
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash" style="font-size: 3rem;"></i>
                        <p>لا يوجد طلاب متاحون للإضافة</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-buttons">
                <button type="button" class="btn btn-outline" onclick="selectAll()">
                    <i class="fas fa-check-double"></i> تحديد الكل
                </button>
                <button type="button" class="btn btn-outline" onclick="deselectAll()">
                    <i class="fas fa-times"></i> إلغاء التحديد
                </button>
                <?php if (count($availableStudents) > 0): ?>
                <button type="submit" name="add_students" class="btn btn-primary">
                    <i class="fas fa-save"></i> إضافة الطلاب المحددين
                </button>
                <?php endif; ?>
                <a href="rings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة
                </a>
            </div>
        </form>
    </div>

    <?php if (!empty($ring_students_details)): ?>
    <div class="registered-students">
        <div class="section-title">
            <i class="fas fa-users"></i>
            <h3>الطلاب المسجلين حالياً (<?php echo count($ring_students_details); ?>)</h3>
        </div>
        <div class="registered-grid">
            <?php foreach ($ring_students_details as $student): 
                $cat_name = ($student['category'] == 'boy') ? 'أولاد' : (($student['category'] == 'girl') ? 'بنات' : (($student['category'] == 'child') ? 'أطفال' : 'نساء'));
            ?>
                <div class="registered-item">
                    <div class="student-avatar" style="width: 35px; height: 35px; font-size: 0.9rem;">
                        <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div class="student-info">
                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                        <div class="student-details">
                            <span><?php echo $cat_name; ?></span>
                            <span><?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                        </div>
                    </div>
                    <?php if ($canManage): ?>
                        <a href="?ring_id=<?php echo $ring_id; ?>&remove=<?php echo $student['id']; ?>" 
                           class="remove-link" onclick="return confirm('هل أنت متأكد من إزالة هذا الطالب من الحلقة؟')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<script>
function selectAll() {
    document.querySelectorAll('input[name="students[]"]:not(:disabled)').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAll() {
    document.querySelectorAll('input[name="students[]"]:not(:disabled)').forEach(cb => {
        cb.checked = false;
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>