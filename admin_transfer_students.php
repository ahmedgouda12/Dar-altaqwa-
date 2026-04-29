<?php
// ============================================
// ملف: admin_transfer_students.php
// نقل الطلاب بين المعلمين (للمدير فقط)
// مع إمكانية نقل الطالب إلى حلقة مباشرة
// مع إمكانية التراجع عن النقل
// آخر تحديث: 2026-04-11
// ============================================

require_once 'config.php';
require_once 'functions.php';

// فقط للمدير
if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إدارة نقل الطلاب بين المعلمين';
require_once 'includes/header.php';

$message = '';
$message_type = '';
$transfer_result = null;

// ============================================
// التأكد من وجود جدول transfer_logs
// ============================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transfer_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            old_teacher_id INT DEFAULT 0,
            new_teacher_id INT NOT NULL,
            old_ring_id INT DEFAULT 0,
            new_ring_id INT DEFAULT 0,
            transfer_reason TEXT,
            transferred_by INT NOT NULL,
            old_teacher_name VARCHAR(255),
            new_teacher_name VARCHAR(255),
            old_ring_name VARCHAR(255),
            new_ring_name VARCHAR(255),
            is_undone TINYINT DEFAULT 0,
            undone_by INT,
            undone_at DATETIME,
            is_undo_of INT,
            transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // الجدول موجود بالفعل
}

// ============================================
// دالة لجلب حلقات المعلم (للـ AJAX)
// ============================================
if (isset($_GET['ajax']) && isset($_GET['teacher_id'])) {
    header('Content-Type: application/json');
    $teacher_id = (int)$_GET['teacher_id'];
    
    $rings = $pdo->prepare("
        SELECT r.id, r.name, r.location,
               (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as student_count
        FROM rings r
        WHERE r.teacher_id = ?
        ORDER BY r.name
    ");
    $rings->execute([$teacher_id]);
    $rings_list = $rings->fetchAll();
    
    echo json_encode(['success' => true, 'rings' => $rings_list]);
    exit;
}

// ============================================
// معالجة نقل طالب
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_student'])) {
    $student_id = (int)$_POST['student_id'];
    $new_teacher_id = (int)$_POST['new_teacher_id'];
    $new_ring_id = !empty($_POST['new_ring_id']) ? (int)$_POST['new_ring_id'] : 0;
    $transfer_reason = trim($_POST['transfer_reason'] ?? '');
    $transfer_with_ring = isset($_POST['transfer_with_ring']) ? 1 : 0;
    
    // جلب معلومات الطالب قبل النقل
    $student = $pdo->prepare("
        SELECT s.*, t.name as current_teacher_name, t.id as current_teacher_id,
               r.id as current_ring_id, r.name as current_ring_name
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN ring_students rs ON s.id = rs.student_id
        LEFT JOIN rings r ON rs.ring_id = r.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $student->execute([$student_id]);
    $student_data = $student->fetch();
    
    if (!$student_data) {
        $message = "❌ الطالب غير موجود";
        $message_type = 'error';
    } else {
        // جلب معلومات المعلم الجديد
        $new_teacher = $pdo->prepare("SELECT * FROM teachers WHERE id = ? AND can_login = 1");
        $new_teacher->execute([$new_teacher_id]);
        $new_teacher_data = $new_teacher->fetch();
        
        if (!$new_teacher_data) {
            $message = "❌ المعلم المحدد غير موجود";
            $message_type = 'error';
        } else {
            // جلب معلومات الحلقة الجديدة (إذا تم اختيارها)
            $new_ring_data = null;
            if ($transfer_with_ring && $new_ring_id > 0) {
                $new_ring = $pdo->prepare("SELECT * FROM rings WHERE id = ? AND teacher_id = ?");
                $new_ring->execute([$new_ring_id, $new_teacher_id]);
                $new_ring_data = $new_ring->fetch();
                
                if (!$new_ring_data) {
                    $message = "❌ الحلقة المحددة غير موجودة أو لا تخص المعلم الجديد";
                    $message_type = 'error';
                }
            }
            
            if (empty($message)) {
                try {
                    $pdo->beginTransaction();
                    
                    // 1. تسجيل عملية النقل في السجل
                    $log = $pdo->prepare("
                        INSERT INTO transfer_logs 
                        (student_id, old_teacher_id, new_teacher_id, old_ring_id, new_ring_id, 
                         transfer_reason, transferred_by, old_teacher_name, new_teacher_name,
                         old_ring_name, new_ring_name)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $log->execute([
                        $student_id,
                        $student_data['current_teacher_id'] ?? 0,
                        $new_teacher_id,
                        $student_data['current_ring_id'] ?? 0,
                        $transfer_with_ring ? $new_ring_id : 0,
                        $transfer_reason,
                        $_SESSION['user_id'],
                        $student_data['current_teacher_name'] ?? 'بدون معلم',
                        $new_teacher_data['name'],
                        $student_data['current_ring_name'] ?? 'بدون حلقة',
                        $transfer_with_ring && $new_ring_data ? $new_ring_data['name'] : ''
                    ]);
                    
                    $transfer_log_id = $pdo->lastInsertId();
                    
                    // 2. تحديث معلم الطالب
                    $update = $pdo->prepare("UPDATE students SET teacher_id = ? WHERE id = ?");
                    $update->execute([$new_teacher_id, $student_id]);
                    
                    // 3. تحديث معلم الطالب في الجداول الأخرى
                    // student_surah_progress
                    $pdo->prepare("UPDATE student_surah_progress SET teacher_id = ? WHERE student_id = ?")
                        ->execute([$new_teacher_id, $student_id]);
                    
                    // student_daily_evaluations
                    $pdo->prepare("UPDATE student_daily_evaluations SET teacher_id = ? WHERE student_id = ?")
                        ->execute([$new_teacher_id, $student_id]);
                    
                    // student_monthly_goals
                    $pdo->prepare("UPDATE student_monthly_goals SET teacher_id = ? WHERE student_id = ?")
                        ->execute([$new_teacher_id, $student_id]);
                    
                    // student_achievements
                    $pdo->prepare("UPDATE student_achievements SET teacher_id = ? WHERE student_id = ?")
                        ->execute([$new_teacher_id, $student_id]);
                    
                    // 4. معالجة الحلقة
                    if ($transfer_with_ring && $new_ring_id > 0) {
                        // إزالة الطالب من الحلقة الحالية
                        $pdo->prepare("DELETE FROM ring_students WHERE student_id = ?")
                            ->execute([$student_id]);
                        
                        // إضافة الطالب إلى الحلقة الجديدة
                        $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)")
                            ->execute([$new_ring_id, $student_id]);
                    }
                    
                    $pdo->commit();
                    
                    $transfer_result = [
                        'log_id' => $transfer_log_id,
                        'student_name' => $student_data['name'],
                        'old_teacher' => $student_data['current_teacher_name'] ?? 'بدون معلم',
                        'new_teacher' => $new_teacher_data['name'],
                        'old_ring' => $student_data['current_ring_name'] ?? 'بدون حلقة',
                        'new_ring' => ($transfer_with_ring && $new_ring_data) ? $new_ring_data['name'] : 'بدون حلقة',
                        'reason' => $transfer_reason,
                        'date' => date('Y-m-d H:i:s')
                    ];
                    
                    $message = "✅ تم نقل الطالب <strong>{$student_data['name']}</strong> بنجاح";
                    $message .= "<br>📌 من معلم: <strong>" . ($student_data['current_teacher_name'] ?? 'بدون معلم') . "</strong>";
                    $message .= "<br>📌 إلى معلم: <strong>{$new_teacher_data['name']}</strong>";
                    if ($transfer_with_ring && $new_ring_data) {
                        $message .= "<br>🔄 من حلقة: <strong>" . ($student_data['current_ring_name'] ?? 'بدون حلقة') . "</strong>";
                        $message .= "<br>🔄 إلى حلقة: <strong>{$new_ring_data['name']}</strong>";
                    }
                    $message_type = 'success';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "❌ خطأ: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ============================================
// معالجة التراجع عن عملية نقل
// ============================================
if (isset($_GET['undo']) && isset($_GET['log_id'])) {
    $log_id = (int)$_GET['undo'];
    
    $log = $pdo->prepare("SELECT * FROM transfer_logs WHERE id = ?");
    $log->execute([$log_id]);
    $log_data = $log->fetch();
    
    if (!$log_data) {
        $message = "❌ عملية النقل غير موجودة";
        $message_type = 'error';
    } elseif ($log_data['is_undone'] == 1) {
        $message = "⚠️ تم التراجع عن هذه العملية بالفعل";
        $message_type = 'warning';
    } else {
        try {
            $pdo->beginTransaction();
            
            // إعادة الطالب إلى معلمه القديم
            $pdo->prepare("UPDATE students SET teacher_id = ? WHERE id = ?")
                ->execute([$log_data['old_teacher_id'], $log_data['student_id']]);
            
            $pdo->prepare("UPDATE student_surah_progress SET teacher_id = ? WHERE student_id = ?")
                ->execute([$log_data['old_teacher_id'], $log_data['student_id']]);
            
            $pdo->prepare("UPDATE student_daily_evaluations SET teacher_id = ? WHERE student_id = ?")
                ->execute([$log_data['old_teacher_id'], $log_data['student_id']]);
            
            $pdo->prepare("UPDATE student_monthly_goals SET teacher_id = ? WHERE student_id = ?")
                ->execute([$log_data['old_teacher_id'], $log_data['student_id']]);
            
            $pdo->prepare("UPDATE student_achievements SET teacher_id = ? WHERE student_id = ?")
                ->execute([$log_data['old_teacher_id'], $log_data['student_id']]);
            
            // معالجة الحلقة
            if ($log_data['old_ring_id'] > 0) {
                $pdo->prepare("DELETE FROM ring_students WHERE student_id = ?")
                    ->execute([$log_data['student_id']]);
                
                $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)")
                    ->execute([$log_data['old_ring_id'], $log_data['student_id']]);
            } elseif ($log_data['new_ring_id'] > 0) {
                // إذا كان الطالب انتقل إلى حلقة جديدة، نزيله منها
                $pdo->prepare("DELETE FROM ring_students WHERE student_id = ? AND ring_id = ?")
                    ->execute([$log_data['student_id'], $log_data['new_ring_id']]);
            }
            
            // تحديث سجل النقل
            $pdo->prepare("
                UPDATE transfer_logs 
                SET is_undone = 1, undone_by = ?, undone_at = NOW()
                WHERE id = ?
            ")->execute([$_SESSION['user_id'], $log_id]);
            
            $pdo->commit();
            
            $student_name = $pdo->prepare("SELECT name FROM students WHERE id = ?");
            $student_name->execute([$log_data['student_id']]);
            $student_name = $student_name->fetchColumn();
            
            $message = "✅ تم التراجع عن نقل الطالب <strong>{$student_name}</strong> وإعادته إلى المعلم <strong>{$log_data['old_teacher_name']}</strong>";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ خطأ في التراجع: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ============================================
// جلب جميع المعلمين
// ============================================
$all_teachers = $pdo->query("
    SELECT id, name, gender, phone, specialization,
           (SELECT COUNT(*) FROM students WHERE teacher_id = teachers.id) as student_count
    FROM teachers 
    WHERE can_login = 1
    ORDER BY gender, name
")->fetchAll();

// جلب جميع الطلاب
$all_students = $pdo->query("
    SELECT 
        s.id,
        s.name,
        s.category,
        s.level,
        s.parent_phone,
        s.teacher_id as current_teacher_id,
        t.name as current_teacher_name,
        t.gender as current_teacher_gender,
        r.id as current_ring_id,
        r.name as current_ring_name
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN ring_students rs ON s.id = rs.student_id
    LEFT JOIN rings r ON rs.ring_id = r.id
    ORDER BY s.category, s.name
")->fetchAll();

// جلب آخر عمليات النقل
$transfer_logs = $pdo->query("
    SELECT 
        tl.*,
        s.name as student_name
    FROM transfer_logs tl
    JOIN students s ON tl.student_id = s.id
    WHERE tl.is_undo_of IS NULL
    ORDER BY tl.transferred_at DESC
    LIMIT 50
")->fetchAll();

// إحصائيات
$stats = [
    'total_students' => count($all_students),
    'students_without_teacher' => count(array_filter($all_students, fn($s) => empty($s['current_teacher_id']))),
    'total_transfers' => $pdo->query("SELECT COUNT(*) FROM transfer_logs WHERE is_undo_of IS NULL")->fetchColumn(),
    'total_undo' => $pdo->query("SELECT COUNT(*) FROM transfer_logs WHERE is_undone = 1")->fetchColumn()
];

$category_names = [
    'boy' => 'أولاد',
    'girl' => 'بنات',
    'child' => 'أطفال',
    'woman' => 'نساء'
];

$category_colors = [
    'boy' => '#3498db',
    'girl' => '#9b59b6',
    'child' => '#f39c12',
    'woman' => '#e84342'
];

// تجميع الطلاب حسب الفئة
$students_by_category = [];
foreach ($category_names as $key => $name) {
    $students_by_category[$key] = array_filter($all_students, fn($s) => $s['category'] == $key);
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

* { margin: 0; padding: 0; box-sizing: border-box; }

.transfer-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

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
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.6rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
}

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

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-right: 5px solid var(--warning);
}

.transfer-section {
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
    color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
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
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--secondary);
}

.btn {
    padding: 12px 25px;
    border-radius: 30px;
    border: none;
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

.btn-warning {
    background: var(--warning);
    color: #212529;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== تبويبات الفئات ===== */
.category-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.category-tab {
    padding: 10px 25px;
    border-radius: 30px;
    cursor: pointer;
    background: #f8f9fa;
    color: #666;
    transition: 0.3s;
    border: 2px solid transparent;
}

.category-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--secondary);
}

.category-content {
    display: none;
}

.category-content.active {
    display: block;
}

.students-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 15px;
    padding: 10px;
}

.student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    transition: 0.3s;
    flex-wrap: wrap;
    gap: 10px;
}

.student-item:hover {
    background: #f8f9fa;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.student-name {
    font-weight: 600;
    color: var(--primary);
}

.student-details {
    font-size: 0.75rem;
    color: #666;
}

.transfer-btn {
    background: var(--warning);
    color: #212529;
    border: none;
    padding: 6px 15px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: 0.3s;
}

.transfer-btn:hover {
    background: var(--secondary);
}

/* ===== خيارات الحلقة ===== */
.ring-options {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 15px;
    display: none;
}

.ring-options.show {
    display: block;
}

.ring-loading {
    text-align: center;
    padding: 20px;
    color: #666;
}

.rings-list {
    max-height: 200px;
    overflow-y: auto;
}

.ring-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-bottom: 1px solid #e9ecef;
    cursor: pointer;
    transition: 0.3s;
}

.ring-option:hover {
    background: #e9ecef;
}

.ring-option.selected {
    background: #d4edda;
    border-right: 3px solid var(--success);
    }

.ring-option input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.ring-name {
    flex: 1;
    font-weight: 600;
    color: var(--primary);
}

/* ===== سجل النقل ===== */
.logs-table {
    width: 100%;
    border-collapse: collapse;
}

.logs-table th {
    background: var(--primary);
    color: white;
    padding: 12px;
    text-align: center;
}

.logs-table td {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
}

.logs-table tr:hover {
    background: #f8f9fa;
}

.undo-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.undo-badge.yes {
    background: #d4edda;
    color: #155724;
}

.undo-badge.no {
    background: #fff3cd;
    color: #856404;
}

/* ===== نافذة منبثقة ===== */
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
    from { transform: translateY(-50px); opacity: 0; }
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
}

.close-modal {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #999;
}

.modal-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .student-item {
        flex-direction: column;
        text-align: center;
    }
    
    .student-info {
        flex-direction: column;
    }
    
    .logs-table {
        font-size: 0.8rem;
    }
    
    .modal-content {
        padding: 20px;
    }
}
</style>

<section class="transfer-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-exchange-alt"></i>
            إدارة نقل الطلاب بين المعلمين
        </h1>
        <div class="stats-badge">
            <i class="fas fa-calendar-alt"></i>
            آخر تحديث: <?php echo date('Y-m-d H:i'); ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--warning);"><?php echo $stats['students_without_teacher']; ?></div>
            <div class="stat-label">طلاب بدون معلم</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_transfers']; ?></div>
            <div class="stat-label">عمليات النقل</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--success);"><?php echo $stats['total_undo']; ?></div>
            <div class="stat-label">تم التراجع عنها</div>
        </div>
    </div>

    <!-- قسم نقل الطالب -->
    <div class="transfer-section">
        <div class="section-title">
            <i class="fas fa-user-friends"></i>
            <h3>نقل طالب إلى معلم آخر</h3>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-user-graduate"></i> اختر الطالب</label>
                <select id="studentSelect" class="form-control" onchange="showStudentInfo(this.value)">
                    <option value="">-- اختر الطالب --</option>
                    <?php foreach ($all_students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" 
                                data-name="<?php echo htmlspecialchars($student['name']); ?>"
                                data-category="<?php echo $student['category']; ?>"
                                data-current-teacher="<?php echo htmlspecialchars($student['current_teacher_name'] ?? 'بدون معلم'); ?>"
                                data-current-teacher-id="<?php echo $student['current_teacher_id'] ?? 0; ?>"
                                data-current-ring="<?php echo htmlspecialchars($student['current_ring_name'] ?? 'بدون حلقة'); ?>"
                                data-current-ring-id="<?php echo $student['current_ring_id'] ?? 0; ?>">
                            <?php echo htmlspecialchars($student['name']); ?> 
                            (<?php echo $category_names[$student['category']]; ?>) 
                            - معلم: <?php echo htmlspecialchars($student['current_teacher_name'] ?? 'بدون معلم'); ?>
                            <?php if ($student['current_ring_name']): ?>
                                - حلقة: <?php echo htmlspecialchars($student['current_ring_name']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-chalkboard-teacher"></i> اختر المعلم الجديد</label>
                <select id="newTeacherSelect" class="form-control" onchange="loadTeacherRings(this.value)">
                    <option value="">-- اختر المعلم --</option>
                    <?php foreach ($all_teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>"
                                data-name="<?php echo htmlspecialchars($teacher['name']); ?>"
                                data-gender="<?php echo $teacher['gender']; ?>">
                            <?php echo htmlspecialchars($teacher['name']); ?> 
                            (<?php echo $teacher['gender'] == 'male' ? 'معلم' : 'معلمة'; ?>) 
                            - <?php echo $teacher['student_count']; ?> طالب
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- خيار نقل الحلقة -->
        <div class="form-group">
            <label class="checkbox-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="transferWithRing" onchange="toggleRingOptions(this.checked)">
                <i class="fas fa-ring"></i>
                <span>نقل الطالب مع حلقة (اختياري)</span>
            </label>
        </div>

        <!-- خيارات الحلقة -->
        <div id="ringOptions" class="ring-options">
            <div class="form-group">
                <label><i class="fas fa-ring"></i> اختر الحلقة الجديدة</label>
                <div id="ringsContainer">
                    <div class="ring-loading">يرجى اختيار معلم أولاً</div>
                </div>
            </div>
        </div>

        <div id="studentInfoBox" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
            <div class="student-info-display"></div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-sticky-note"></i> سبب النقل (اختياري)</label>
            <textarea id="transferReason" class="form-control" rows="2" placeholder="أدخل سبب نقل الطالب..."></textarea>
        </div>

        <button class="btn btn-primary" onclick="confirmTransfer()" style="width: 100%;">
            <i class="fas fa-exchange-alt"></i> نقل الطالب
        </button>
    </div>

    <!-- قائمة الطلاب حسب الفئة -->
    <div class="transfer-section">
        <div class="section-title">
            <i class="fas fa-list"></i>
            <h3>قائمة الطلاب</h3>
        </div>

        <div class="category-tabs">
            <?php foreach ($category_names as $key => $name): ?>
                <div class="category-tab" data-category="<?php echo $key; ?>" onclick="showCategory('<?php echo $key; ?>')">
                    <?php echo $name; ?> (<?php echo count($students_by_category[$key]); ?>)
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($category_names as $key => $name): ?>
            <div id="category-<?php echo $key; ?>" class="category-content">
                <div class="students-list">
                    <?php if (empty($students_by_category[$key])): ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-users-slash"></i> لا يوجد طلاب في هذه الفئة
                        </div>
                    <?php else: ?>
                        <?php foreach ($students_by_category[$key] as $student): ?>
                            <div class="student-item">
                                <div class="student-info">
                                    <div class="student-avatar" style="background: <?php echo $category_colors[$key]; ?>;">
                                        <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                                    </div>
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                        <div class="student-details">
                                            <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student['current_teacher_name'] ?? 'بدون معلم'); ?>
                                            <?php if ($student['current_ring_name']): ?>
                                                | <i class="fas fa-ring"></i> <?php echo htmlspecialchars($student['current_ring_name']); ?>
                                            <?php endif; ?>
                                            <?php if ($student['level']): ?>
                                                | <i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="transfer-btn" onclick="quickTransfer(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>', '<?php echo addslashes($student['current_teacher_name'] ?? 'بدون معلم'); ?>', '<?php echo addslashes($student['current_ring_name'] ?? ''); ?>')">
                                    <i class="fas fa-exchange-alt"></i> نقل
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <!-- سجل عمليات النقل -->
    <div class="transfer-section">
        <div class="section-title">
            <i class="fas fa-history"></i>
            <h3>سجل عمليات النقل</h3>
        </div>

        <?php if (empty($transfer_logs)): ?>
            <div style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-history"></i> لا توجد عمليات نقل مسجلة بعد
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>الطالب</th>
                            <th>من معلم</th>
                            <th>إلى معلم</th>
                            <th>من حلقة</th>
                            <th>إلى حلقة</th>
                            <th>السبب</th>
                            <th>الحالة</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfer_logs as $index => $log): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($log['transferred_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['old_teacher_name'] ?? 'بدون معلم'); ?></td>
                                <td><?php echo htmlspecialchars($log['new_teacher_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['old_ring_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($log['new_ring_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($log['transfer_reason'] ?: '-'); ?></td>
                                <td>
                                    <span class="undo-badge <?php echo $log['is_undone'] ? 'yes' : 'no'; ?>">
                                        <?php echo $log['is_undone'] ? '✅ تم التراجع' : '⚠️ نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$log['is_undone']): ?>
                                        <a href="?undo=<?php echo $log['id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('هل أنت متأكد من التراجع عن هذه العملية؟')">
                                            <i class="fas fa-undo"></i> تراجع
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- نافذة تأكيد النقل -->
<div class="modal" id="transferModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt"></i> تأكيد نقل الطالب</h3>
            <button class="close-modal" onclick="closeTransferModal()">&times;</button>
        </div>
        
        <div id="modalStudentInfo" class="info-box"></div>
        <div id="modalTeacherInfo" class="info-box"></div>
        <div id="modalRingInfo" class="info-box" style="display: none;"></div>
        
        <form method="post" id="transferForm">
            <input type="hidden" name="transfer_student" value="1">
            <input type="hidden" name="student_id" id="transferStudentId">
            <input type="hidden" name="new_teacher_id" id="transferTeacherId">
            <input type="hidden" name="new_ring_id" id="transferRingId">
            <input type="hidden" name="transfer_with_ring" id="transferWithRingValue" value="0">
            <input type="hidden" name="transfer_reason" id="transferReasonInput">
            
            <div class="modal-actions">
                <button type="button" class="btn btn-warning" onclick="closeTransferModal()" style="flex:1;">إلغاء</button>
                <button type="submit" class="btn btn-primary" style="flex:2;">
                    <i class="fas fa-check-circle"></i> تأكيد النقل
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let selectedStudentData = null;
let selectedRings = [];

// ============================================
// دوال عرض الفئات
// ============================================
function showCategory(category) {
    document.querySelectorAll('.category-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.category-content').forEach(content => content.classList.remove('active'));
    
    document.querySelector(`.category-tab[data-category="${category}"]`).classList.add('active');
    document.getElementById(`category-${category}`).classList.add('active');
}

// ============================================
// عرض معلومات الطالب
// ============================================
function showStudentInfo(studentId) {
    const select = document.getElementById('studentSelect');
    const option = select.options[select.selectedIndex];
    const infoBox = document.getElementById('studentInfoBox');
    
    if (studentId && option && option.value) {
        const categoryNames = {
            'boy': 'أولاد',
            'girl': 'بنات',
            'child': 'أطفال',
            'woman': 'نساء'
        };
        
        infoBox.innerHTML = `
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: #1e3c3f; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; color: white;">
                    ${option.dataset.name ? option.dataset.name.charAt(0) : '?'}
                </div>
                <div>
                    <strong>${option.dataset.name}</strong><br>
                    <span style="color: #666;">الفئة: ${categoryNames[option.dataset.category]}</span><br>
                    <span style="color: #666;">المعلم الحالي: ${option.dataset.currentTeacher}</span><br>
                    <span style="color: #666;">الحلقة الحالية: ${option.dataset.currentRing || 'بدون حلقة'}</span>
                </div>
            </div>
        `;
        infoBox.style.display = 'block';
    } else {
        infoBox.style.display = 'none';
    }
}

// ============================================
// تحميل حلقات المعلم
// ============================================
async function loadTeacherRings(teacherId) {
    const ringOptions = document.getElementById('ringOptions');
    const ringsContainer = document.getElementById('ringsContainer');
    
    if (!teacherId) {
        ringsContainer.innerHTML = '<div class="ring-loading">يرجى اختيار معلم أولاً</div>';
        return;
    }
    
    ringsContainer.innerHTML = '<div class="ring-loading"><i class="fas fa-spinner fa-spin"></i> جاري تحميل الحلقات...</div>';
    
    try {
        const response = await fetch(`?ajax=1&teacher_id=${teacherId}`);
        const data = await response.json();
        
        if (data.success && data.rings.length > 0) {
            selectedRings = data.rings;
            let html = '<div class="rings-list">';
            html += '<div style="padding: 8px; background: #e8f5e9; border-bottom: 1px solid #c8e6c9;">';
            html += `<i class="fas fa-info-circle"></i> ${data.rings.length} حلقة متاحة`;
            html += '</div>';
            
            data.rings.forEach(ring => {
                html += `
                    <div class="ring-option" onclick="selectRing(this, ${ring.id}, '${ring.name.replace(/'/g, "\\'")}')">
                        <input type="radio" name="ring_radio" value="${ring.id}">
                        <div class="ring-name">${ring.name}</div>
                        <div class="ring-stats" style="font-size: 0.7rem; color: #666;">
                            <i class="fas fa-users"></i> ${ring.student_count} طالب
                            ${ring.location ? ` | <i class="fas fa-location-dot"></i> ${ring.location}` : ''}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            ringsContainer.innerHTML = html;
        } else {
            ringsContainer.innerHTML = '<div class="ring-loading">⚠️ لا توجد حلقات لهذا المعلم</div>';
        }
    } catch(error) {
        ringsContainer.innerHTML = '<div class="ring-loading">❌ خطأ في تحميل الحلقات</div>';
    }
}

// ============================================
// اختيار حلقة
// ============================================
let selectedRingId = 0;
let selectedRingName = '';

function selectRing(element, ringId, ringName) {
    document.querySelectorAll('.ring-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    selectedRingId = ringId;
    selectedRingName = ringName;
}

// ============================================
// إظهار/إخفاء خيارات الحلقة
// ============================================
function toggleRingOptions(show) {
    const ringOptions = document.getElementById('ringOptions');
    if (show) {
        ringOptions.classList.add('show');
        const teacherSelect = document.getElementById('newTeacherSelect');
        if (teacherSelect.value) {
            loadTeacherRings(teacherSelect.value);
        }
    } else {
        ringOptions.classList.remove('show');
        selectedRingId = 0;
        selectedRingName = '';
    }
}

// ============================================
// نقل سريع من القائمة
// ============================================
function quickTransfer(studentId, studentName, currentTeacher, currentRing) {
    document.getElementById('transferStudentId').value = studentId;
    document.getElementById('modalStudentInfo').innerHTML = `
        <strong><i class="fas fa-user-graduate"></i> الطالب:</strong> ${studentName}<br>
        <strong><i class="fas fa-chalkboard-teacher"></i> المعلم الحالي:</strong> ${currentTeacher}<br>
        <strong><i class="fas fa-ring"></i> الحلقة الحالية:</strong> ${currentRing || 'بدون حلقة'}
    `;
    document.getElementById('transferModal').classList.add('show');
}

// ============================================
// تأكيد النقل
// ============================================
function confirmTransfer() {
    const studentSelect = document.getElementById('studentSelect');
    const teacherSelect = document.getElementById('newTeacherSelect');
    const transferWithRing = document.getElementById('transferWithRing').checked;
    const reason = document.getElementById('transferReason').value;
    
    if (!studentSelect.value) {
        alert('⚠️ يرجى اختيار الطالب');
        return;
    }
    
    if (!teacherSelect.value) {
        alert('⚠️ يرجى اختيار المعلم الجديد');
        return;
    }
    
    const studentOption = studentSelect.options[studentSelect.selectedIndex];
    const teacherOption = teacherSelect.options[teacherSelect.selectedIndex];
    
    document.getElementById('transferStudentId').value = studentSelect.value;
    document.getElementById('transferTeacherId').value = teacherSelect.value;
    document.getElementById('transferWithRingValue').value = transferWithRing ? 1 : 0;
    document.getElementById('transferRingId').value = transferWithRing ? selectedRingId : 0;
    document.getElementById('transferReasonInput').value = reason;
    
    // عرض معلومات التأكيد
    document.getElementById('modalStudentInfo').innerHTML = `
        <strong><i class="fas fa-user-graduate"></i> الطالب:</strong> ${studentOption.dataset.name}<br>
        <strong><i class="fas fa-chalkboard-teacher"></i> المعلم الحالي:</strong> ${studentOption.dataset.currentTeacher}<br>
        <strong><i class="fas fa-ring"></i> الحلقة الحالية:</strong> ${studentOption.dataset.currentRing || 'بدون حلقة'}
    `;
    
    document.getElementById('modalTeacherInfo').innerHTML = `
        <strong><i class="fas fa-chalkboard-teacher"></i> المعلم الجديد:</strong> ${teacherOption.dataset.name}<br>
        <strong><i class="fas fa-venus-mars"></i> الجنس:</strong> ${teacherOption.dataset.gender == 'male' ? 'معلم' : 'معلمة'}
    `;
    
    const ringInfoDiv = document.getElementById('modalRingInfo');
    if (transferWithRing && selectedRingId > 0 && selectedRingName) {
        ringInfoDiv.innerHTML = `
            <strong><i class="fas fa-ring"></i> الحلقة الجديدة:</strong> ${selectedRingName}
        `;
        ringInfoDiv.style.display = 'block';
    } else {
        ringInfoDiv.style.display = 'none';
    }
    
    document.getElementById('transferModal').classList.add('show');
}

function closeTransferModal() {
    document.getElementById('transferModal').classList.remove('show');
}

// إظهار الفئة الأولى افتراضياً
document.addEventListener('DOMContentLoaded', function() {
    const firstCategory = document.querySelector('.category-tab');
    if (firstCategory) {
        showCategory(firstCategory.dataset.category);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>