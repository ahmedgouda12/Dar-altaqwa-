<?php
// ============================================
// ملف: manage_students_without_memorization.php
// إدارة الطلاب الذين ليس لديهم محفوظات
// آخر تحديث: 2026-04-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إدارة الطلاب بدون محفوظات';
require_once 'includes/header.php';

$message = '';
$message_type = '';
$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// جلب جميع المعلمين مع إحصائيات
// ============================================
$teachers = $pdo->query("
    SELECT 
        t.id, 
        t.name, 
        t.gender,
        COUNT(DISTINCT s.id) as total_students,
        SUM(CASE WHEN sp.id IS NULL THEN 1 ELSE 0 END) as students_without_memorization
    FROM teachers t
    LEFT JOIN students s ON t.id = s.teacher_id
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    WHERE t.can_login = 1
    GROUP BY t.id
    ORDER BY t.name
")->fetchAll();

// ============================================
// جلب الطلاب الذين ليس لديهم محفوظات (حسب المعلم المختار)
// ============================================
$students_without_memorization = [];
$teacher_info = null;

if ($selected_teacher > 0) {
    // جلب معلومات المعلم
    $stmt = $pdo->prepare("SELECT name, gender FROM teachers WHERE id = ?");
    $stmt->execute([$selected_teacher]);
    $teacher_info = $stmt->fetch();
    
    // جلب الطلاب
    $stmt = $pdo->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.category, 
            s.level, 
            s.parent_phone,
            s.created_at,
            (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_count
        FROM students s
        WHERE s.teacher_id = ? 
        AND s.id NOT IN (SELECT DISTINCT student_id FROM student_surah_progress WHERE completed = 1)
        ORDER BY s.name
    ");
    $stmt->execute([$selected_teacher]);
    $students_without_memorization = $stmt->fetchAll();
}

// ============================================
// معالجة إضافة سورة واحدة لطالب
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single_surah'])) {
    $student_id = (int)$_POST['student_id'];
    $surah_number = (int)$_POST['surah_number'];
    $notes = trim($_POST['notes'] ?? '');
    
    // التحقق من عدم تكرار السورة
    $check = $pdo->prepare("SELECT id FROM student_surah_progress WHERE student_id = ? AND surah_number = ?");
    $check->execute([$student_id, $surah_number]);
    
    if ($check->fetch()) {
        $message = "⚠️ هذه السورة مسجلة مسبقاً لهذا الطالب";
        $message_type = 'warning';
    } else {
        try {
            // جلب teacher_id للطالب
            $stmt = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $teacher_id = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                INSERT INTO student_surah_progress (student_id, teacher_id, surah_number, completed, completed_at, notes)
                VALUES (?, ?, ?, 1, NOW(), ?)
            ");
            $stmt->execute([$student_id, $teacher_id, $surah_number, $notes]);
            
            // تحديث إحصائيات الأجزاء
            updateStudentPartsStats($pdo, $student_id);
            
            $message = "✅ تم إضافة سورة " . getSurahName($surah_number) . " للطالب بنجاح";
            $message_type = 'success';
            
        } catch (PDOException $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ============================================
// معالجة إضافة عدة سور لطالب
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_multiple_surahs'])) {
    $student_id = (int)$_POST['student_id'];
    $surahs = isset($_POST['surahs']) ? array_map('intval', $_POST['surahs']) : [];
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($surahs)) {
        $message = "⚠️ لم يتم اختيار أي سورة";
        $message_type = 'warning';
    } else {
        try {
            // جلب teacher_id للطالب
            $stmt = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $teacher_id = $stmt->fetchColumn();
            
            $added = 0;
            $skipped = 0;
            
            foreach ($surahs as $surah_number) {
                // التحقق من عدم التكرار
                $check = $pdo->prepare("SELECT id FROM student_surah_progress WHERE student_id = ? AND surah_number = ?");
                $check->execute([$student_id, $surah_number]);
                
                if ($check->fetch()) {
                    $skipped++;
                    continue;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO student_surah_progress (student_id, teacher_id, surah_number, completed, completed_at, notes)
                    VALUES (?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([$student_id, $teacher_id, $surah_number, $notes]);
                $added++;
            }
            
            // تحديث إحصائيات الأجزاء
            updateStudentPartsStats($pdo, $student_id);
            
            $message = "✅ تم إضافة $added سورة بنجاح" . ($skipped > 0 ? " (تخطي $skipped سورة مضافة مسبقاً)" : "");
            $message_type = 'success';
            
        } catch (PDOException $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ============================================
// معالجة إضافة سورة لجميع طلاب معلم
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_to_teacher'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $surah_number = (int)$_POST['surah_number'];
    $notes = trim($_POST['notes'] ?? '');
    
    // جلب جميع طلاب المعلم الذين ليس لديهم محفوظات
    $stmt = $pdo->prepare("
        SELECT id FROM students 
        WHERE teacher_id = ? 
        AND id NOT IN (SELECT DISTINCT student_id FROM student_surah_progress WHERE completed = 1)
    ");
    $stmt->execute([$teacher_id]);
    $students_list = $stmt->fetchAll();
    
    if (empty($students_list)) {
        $message = "⚠️ لا يوجد طلاب بدون محفوظات لهذا المعلم";
        $message_type = 'warning';
    } else {
        $added = 0;
        $skipped = 0;
        
        foreach ($students_list as $student) {
            // التحقق من عدم التكرار
            $check = $pdo->prepare("SELECT id FROM student_surah_progress WHERE student_id = ? AND surah_number = ?");
            $check->execute([$student['id'], $surah_number]);
            
            if ($check->fetch()) {
                $skipped++;
                continue;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO student_surah_progress (student_id, teacher_id, surah_number, completed, completed_at, notes)
                VALUES (?, ?, ?, 1, NOW(), ?)
            ");
            $stmt->execute([$student['id'], $teacher_id, $surah_number, $notes]);
            $added++;
            
            // تحديث إحصائيات الأجزاء لكل طالب
            updateStudentPartsStats($pdo, $student['id']);
        }
        
        $message = "✅ تم إضافة سورة " . getSurahName($surah_number) . " لـ $added طالب بنجاح" . ($skipped > 0 ? " (تخطي $skipped طالب)" : "");
        $message_type = 'success';
    }
}

// ============================================
// معالجة إضافة عدة سور لجميع طلاب معلم
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_multiple_to_teacher'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $surahs = isset($_POST['bulk_surahs']) ? array_map('intval', $_POST['bulk_surahs']) : [];
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($surahs)) {
        $message = "⚠️ لم يتم اختيار أي سورة";
        $message_type = 'warning';
    } else {
        // جلب جميع طلاب المعلم الذين ليس لديهم محفوظات
        $stmt = $pdo->prepare("
            SELECT id FROM students 
            WHERE teacher_id = ? 
            AND id NOT IN (SELECT DISTINCT student_id FROM student_surah_progress WHERE completed = 1)
        ");
        $stmt->execute([$teacher_id]);
        $students_list = $stmt->fetchAll();
        
        if (empty($students_list)) {
            $message = "⚠️ لا يوجد طلاب بدون محفوظات لهذا المعلم";
            $message_type = 'warning';
        } else {
            $total_added = 0;
            
            foreach ($surahs as $surah_number) {
                $added = 0;
                foreach ($students_list as $student) {
                    // التحقق من عدم التكرار
                    $check = $pdo->prepare("SELECT id FROM student_surah_progress WHERE student_id = ? AND surah_number = ?");
                    $check->execute([$student['id'], $surah_number]);
                    
                    if ($check->fetch()) {
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO student_surah_progress (student_id, teacher_id, surah_number, completed, completed_at, notes)
                        VALUES (?, ?, ?, 1, NOW(), ?)
                    ");
                    $stmt->execute([$student['id'], $teacher_id, $surah_number, $notes]);
                    $added++;
                    $total_added++;
                    
                    // تحديث إحصائيات الأجزاء لكل طالب
                    updateStudentPartsStats($pdo, $student['id']);
                }
            }
            
            $message = "✅ تم إضافة " . count($surahs) . " سورة لـ " . count($students_list) . " طالب (إجمالي $total_added سجل)";
            $message_type = 'success';
        }
    }
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
    --gray: #6c757d;
    --gray-light: #e9ecef;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.manage-page {
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
    font-size: 1.6rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: var(--secondary);
}

.stats-badge {
    background: rgba(255,255,255,0.15);
    padding: 10px 25px;
    border-radius: 50px;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ===== شبكة المعلمين ===== */
.teachers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.teacher-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid transparent;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.teacher-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.teacher-card.active {
    border-color: var(--secondary);
    background: linear-gradient(135deg, #fff8e7, #fff3d6);
}

.teacher-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 15px;
}

.teacher-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary);
    text-align: center;
    margin-bottom: 10px;
}

.teacher-stats {
    display: flex;
    justify-content: center;
    gap: 15px;
    font-size: 0.8rem;
    color: #666;
}

.teacher-stats span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* ===== أقسام الصفحة ===== */
.section-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--secondary);
}

.section-title i {
    font-size: 1.3rem;
    color: var(--secondary);
}

/* ===== قائمة الطلاب ===== */
.students-list {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid var(--gray-light);
    border-radius: 15px;
}

.student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid var(--gray-light);
    transition: 0.3s;
}

.student-item:hover {
    background: #f8f9fa;
}

.student-item:last-child {
    border-bottom: none;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.student-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
}

.student-details {
    flex: 1;
}

.student-name {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 3px;
}

.student-meta {
    font-size: 0.7rem;
    color: #666;
    display: flex;
    gap: 10px;
}

.student-meta i {
    color: var(--secondary);
}

.student-actions {
    display: flex;
    gap: 8px;
}

/* ===== أزرار ===== */
.btn {
    padding: 8px 16px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 0.75rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: #212529;
}

.btn-secondary {
    background: var(--gray);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== النوافذ المنبثقة ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 25px;
    padding: 30px;
    width: 90%;
    max-width: 550px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--secondary);
}

.modal-header h3 {
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: #999;
    transition: 0.3s;
}

.close-modal:hover {
    color: var(--danger);
    transform: rotate(90deg);
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

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--gray-light);
    border-radius: 12px;
    font-size: 1rem;
}

.surahs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding: 15px;
    border: 1px solid var(--gray-light);
    border-radius: 12px;
    background: #f8f9fa;
}

.surah-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
}

.surah-checkbox:hover {
    background: #e9ecef;
}

.surah-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.modal-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

/* ===== رسائل ===== */
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

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid var(--danger);
}

/* ===== حالة فارغة ===== */
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .manage-page { padding: 15px; }
    .teachers-grid { grid-template-columns: 1fr; }
    .student-item { flex-direction: column; gap: 10px; text-align: center; }
    .student-info { flex-direction: column; }
    .student-actions { width: 100%; justify-content: center; }
    .page-header { flex-direction: column; text-align: center; }
    .surahs-grid { grid-template-columns: repeat(2, 1fr); }
    .modal-content { padding: 20px; }
    .modal-actions { flex-direction: column; }
}
</style>

<section class="manage-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-quran"></i>
            إدارة الطلاب بدون محفوظات
        </h1>
        <div class="stats-badge">
            <i class="fas fa-users"></i>
            <?php 
            $total_without = array_sum(array_column($teachers, 'students_without_memorization'));
            echo $total_without . ' طالب بدون محفوظات';
            ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- قائمة المعلمين -->
    <div class="teachers-grid">
        <?php foreach ($teachers as $teacher): 
            $has_students = $teacher['students_without_memorization'] > 0;
        ?>
            <div class="teacher-card <?php echo $selected_teacher == $teacher['id'] ? 'active' : ''; ?>" 
                 onclick="window.location.href='?teacher_id=<?php echo $teacher['id']; ?>'">
                <div class="teacher-avatar">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                <div class="teacher-stats">
                    <span><i class="fas fa-users"></i> <?php echo $teacher['total_students']; ?> طالب</span>
                    <span><i class="fas fa-star" style="color: <?php echo $has_students ? '#dc3545' : '#28a745'; ?>"></i> 
                        <?php echo $teacher['students_without_memorization']; ?> بدون محفوظات
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($selected_teacher > 0 && $teacher_info): ?>
        
        <!-- عنوان المعلم -->
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>طلاب المعلم: <?php echo htmlspecialchars($teacher_info['name']); ?></h3>
                <span style="margin-right: auto; background: var(--secondary); color: white; padding: 5px 15px; border-radius: 30px; font-size: 0.8rem;">
                    <i class="fas fa-star"></i> <?php echo count($students_without_memorization); ?> طالب بدون محفوظات
                </span>
            </div>

            <!-- أزرار الإجراءات الجماعية -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <button class="btn btn-success" onclick="openBulkModal(<?php echo $selected_teacher; ?>, '<?php echo addslashes($teacher_info['name']); ?>')">
                    <i class="fas fa-layer-group"></i> إضافة سورة لجميع الطلاب
                </button>
                <button class="btn btn-primary" onclick="openBulkMultipleModal(<?php echo $selected_teacher; ?>, '<?php echo addslashes($teacher_info['name']); ?>')">
                    <i class="fas fa-check-double"></i> إضافة عدة سور لجميع الطلاب
                </button>
            </div>

            <!-- قائمة الطلاب -->
            <?php if (empty($students_without_memorization)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    <h3>لا يوجد طلاب بدون محفوظات</h3>
                    <p>جميع طلاب هذا المعلم لديهم سور محفوظة</p>
                </div>
            <?php else: ?>
                <div class="students-list">
                    <?php foreach ($students_without_memorization as $student): 
                        $avatar_letter = mb_substr($student['name'], 0, 1, 'UTF-8');
                        $cat_name = match($student['category']) {
                            'boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء', default => $student['category']
                        };
                    ?>
                        <div class="student-item">
                            <div class="student-info">
                                <div class="student-avatar"><?php echo $avatar_letter; ?></div>
                                <div class="student-details">
                                    <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <div class="student-meta">
                                        <span><i class="fas fa-tag"></i> <?php echo $cat_name; ?></span>
                                        <span><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                                        <span><i class="fas fa-calendar"></i> منذ <?php echo date('Y-m-d', strtotime($student['created_at'])); ?></span>
                                        <?php if ($student['parent_phone']): ?>
                                            <span><i class="fas fa-phone"></i> <?php echo $student['parent_phone']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="student-actions">
                                <button class="btn btn-info btn-sm" onclick="openSingleModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
                                    <i class="fas fa-plus"></i> إضافة سورة
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="openMultipleModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
                                    <i class="fas fa-layer-group"></i> إضافة عدة
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($selected_teacher > 0 && !$teacher_info): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
            <h3>المعلم غير موجود</h3>
            <p>يرجى اختيار معلم آخر</p>
        </div>
    <?php elseif (empty($teachers)): ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <h3>لا يوجد معلمين</h3>
            <p>قم بإضافة معلمين أولاً</p>
            <a href="add_teacher.php" class="btn btn-primary" style="margin-top: 15px;">إضافة معلم</a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-hand-point-left"></i>
            <h3>اختر معلماً من القائمة</h3>
            <p>يرجى اختيار معلم لعرض الطلاب الذين ليس لديهم محفوظات</p>
        </div>
    <?php endif; ?>
</section>

<!-- ============================================ -->
<!-- نافذة إضافة سورة واحدة لطالب -->
<!-- ============================================ -->
<div class="modal" id="singleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> إضافة سورة محفوظة</h3>
            <button class="close-modal" onclick="closeSingleModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="student_id" id="singleStudentId">
            <div id="singleStudentName" style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-book-open"></i> اختر السورة</label>
                <select name="surah_number" class="form-control" required>
                    <option value="">-- اختر السورة --</option>
                    <?php for ($i = 1; $i <= 114; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeSingleModal()">إلغاء</button>
                <button type="submit" name="add_single_surah" class="btn btn-primary">إضافة السورة</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- نافذة إضافة عدة سور لطالب -->
<!-- ============================================ -->
<div class="modal" id="multipleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> إضافة عدة سور</h3>
            <button class="close-modal" onclick="closeMultipleModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="student_id" id="multipleStudentId">
            <div id="multipleStudentName" style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-quran"></i> اختر السور</label>
                <div class="surahs-grid" id="surahsGrid">
                    <?php for ($i = 1; $i <= 114; $i++): ?>
                        <label class="surah-checkbox">
                            <input type="checkbox" name="surahs[]" value="<?php echo $i; ?>">
                            <span><?php echo $i; ?>. <?php echo getSurahName($i); ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <small>يمكنك اختيار أكثر من سورة (Ctrl + نقرة متعددة)</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllSurahs()">تحديد الكل</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="deselectAllSurahs()">إلغاء التحديد</button>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeMultipleModal()">إلغاء</button>
                <button type="submit" name="add_multiple_surahs" class="btn btn-primary">إضافة السور</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- نافذة إضافة سورة لجميع طلاب معلم -->
<!-- ============================================ -->
<div class="modal" id="bulkModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> إضافة سورة لجميع الطلاب</h3>
            <button class="close-modal" onclick="closeBulkModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="teacher_id" id="bulkTeacherId">
            <div id="bulkTeacherName" style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-book-open"></i> اختر السورة</label>
                <select name="surah_number" class="form-control" required>
                    <option value="">-- اختر السورة --</option>
                    <?php for ($i = 1; $i <= 114; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeBulkModal()">إلغاء</button>
                <button type="submit" name="add_bulk_to_teacher" class="btn btn-success" onclick="return confirm('هل أنت متأكد من إضافة هذه السورة لجميع طلاب هذا المعلم؟')">إضافة للجميع</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- نافذة إضافة عدة سور لجميع طلاب معلم -->
<!-- ============================================ -->
<div class="modal" id="bulkMultipleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-double"></i> إضافة عدة سور لجميع الطلاب</h3>
            <button class="close-modal" onclick="closeBulkMultipleModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="teacher_id" id="bulkMultipleTeacherId">
            <div id="bulkMultipleTeacherName" style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-quran"></i> اختر السور</label>
                <div class="surahs-grid" id="bulkSurahsGrid">
                    <?php for ($i = 1; $i <= 114; $i++): ?>
                        <label class="surah-checkbox">
                            <input type="checkbox" name="bulk_surahs[]" value="<?php echo $i; ?>">
                            <span><?php echo $i; ?>. <?php echo getSurahName($i); ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <small>يمكنك اختيار أكثر من سورة</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllBulkSurahs()">تحديد الكل</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="deselectAllBulkSurahs()">إلغاء التحديد</button>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeBulkMultipleModal()">إلغاء</button>
                <button type="submit" name="add_bulk_multiple_to_teacher" class="btn btn-success" onclick="return confirm('هل أنت متأكد من إضافة هذه السور لجميع طلاب هذا المعلم؟')">إضافة للجميع</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// دوال النوافذ المنبثقة
// ============================================

function openSingleModal(studentId, studentName) {
    document.getElementById('singleStudentId').value = studentId;
    document.getElementById('singleStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('singleModal').classList.add('show');
}

function closeSingleModal() {
    document.getElementById('singleModal').classList.remove('show');
}

function openMultipleModal(studentId, studentName) {
    document.getElementById('multipleStudentId').value = studentId;
    document.getElementById('multipleStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('multipleModal').classList.add('show');
}

function closeMultipleModal() {
    document.getElementById('multipleModal').classList.remove('show');
}

function openBulkModal(teacherId, teacherName) {
    document.getElementById('bulkTeacherId').value = teacherId;
    document.getElementById('bulkTeacherName').innerHTML = '<i class="fas fa-chalkboard-teacher"></i> ' + teacherName;
    document.getElementById('bulkModal').classList.add('show');
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.remove('show');
}

function openBulkMultipleModal(teacherId, teacherName) {
    document.getElementById('bulkMultipleTeacherId').value = teacherId;
    document.getElementById('bulkMultipleTeacherName').innerHTML = '<i class="fas fa-chalkboard-teacher"></i> ' + teacherName;
    document.getElementById('bulkMultipleModal').classList.add('show');
}

function closeBulkMultipleModal() {
    document.getElementById('bulkMultipleModal').classList.remove('show');
}

// ============================================
// دوال تحديد السور
// ============================================

function selectAllSurahs() {
    document.querySelectorAll('#surahsGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllSurahs() {
    document.querySelectorAll('#surahsGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}

function selectAllBulkSurahs() {
    document.querySelectorAll('#bulkSurahsGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllBulkSurahs() {
    document.querySelectorAll('#bulkSurahsGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}

// إغلاق النوافذ بالنقر خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>