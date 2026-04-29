<?php
// ============================================
// ملف: check_duplicate_students.php
// نظام الكشف عن الطلاب المكررين (نسخة مصححة)
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إدارة الطلاب المكررين';
require_once 'includes/header.php';

$message = '';
$message_type = '';

// عرض رسائل النجاح/الخطأ من الجلسة
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = 'success';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $message_type = 'error';
    unset($_SESSION['error']);
}

// ============================================
// دالة الكشف عن المكررين (محسنة - تعتمد على الاسم والرقم معاً)
// ============================================
function detectDuplicateStudents($pdo, $update_log = true) {
    $duplicates = [];
    
    // 1. الكشف عن التطابق التام (نفس الاسم ونفس رقم الهاتف)
    $exact_matches = $pdo->query("
        SELECT s1.id as id1, s1.name as name1, s1.parent_phone as phone1,
               s2.id as id2, s2.name as name2, s2.parent_phone as phone2
        FROM students s1
        JOIN students s2 ON s1.id < s2.id
        WHERE s1.name = s2.name 
        AND s1.parent_phone = s2.parent_phone 
        AND s1.parent_phone IS NOT NULL 
        AND s1.parent_phone != ''
    ")->fetchAll();
    
    foreach ($exact_matches as $match) {
        $duplicates[] = [
            'student1' => ['id' => $match['id1'], 'name' => $match['name1'], 'phone' => $match['phone1']],
            'student2' => ['id' => $match['id2'], 'name' => $match['name2'], 'phone' => $match['phone2']],
            'type' => 'exact_match',
            'confidence' => 100,
            'reason' => 'نفس الاسم ونفس رقم الهاتف'
        ];
    }
    
    // 2. الكشف عن أسماء متشابهة مع نفس رقم الهاتف
    $similar_names_same_phone = $pdo->query("
        SELECT s1.id as id1, s1.name as name1, s1.parent_phone as phone1,
               s2.id as id2, s2.name as name2, s2.parent_phone as phone2
        FROM students s1
        JOIN students s2 ON s1.id < s2.id
        WHERE s1.parent_phone = s2.parent_phone 
        AND s1.parent_phone IS NOT NULL 
        AND s1.parent_phone != ''
        AND s1.name != s2.name
        AND SOUNDEX(s1.name) = SOUNDEX(s2.name)
    ")->fetchAll();
    
    foreach ($similar_names_same_phone as $match) {
        similar_text($match['name1'], $match['name2'], $percent);
        if ($percent > 70) {
            $duplicates[] = [
                'student1' => ['id' => $match['id1'], 'name' => $match['name1'], 'phone' => $match['phone1']],
                'student2' => ['id' => $match['id2'], 'name' => $match['name2'], 'phone' => $match['phone2']],
                'type' => 'similar_name_same_phone',
                'confidence' => round($percent),
                'reason' => "أسماء متشابهة ({$percent}%) مع نفس رقم الهاتف"
            ];
        }
    }
    
    // تحديث سجل المكررين (حذف القديم وإضافة الجديد)
    if ($update_log) {
        // حذف السجلات القديمة pending
        $pdo->exec("DELETE FROM duplicate_students_log WHERE status = 'pending'");
        
        $stmt = $pdo->prepare("
            INSERT INTO duplicate_students_log 
            (student_id_1, student_id_2, duplicate_type, confidence_score, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($duplicates as $dup) {
            $stmt->execute([
                $dup['student1']['id'],
                $dup['student2']['id'],
                $dup['type'],
                $dup['confidence'],
                $dup['reason']
            ]);
        }
    }
    
    return $duplicates;
}

// ============================================
// معالجة دمج الطلاب (نسخة مصححة بالكامل)
// ============================================
if (isset($_POST['merge_students'])) {
    $keep_id = (int)$_POST['keep_id'];
    $remove_id = (int)$_POST['remove_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        // 1. جلب أسماء الطالبين للتسجيل
        $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
        $stmt->execute([$keep_id]);
        $keep_name = $stmt->fetchColumn();
        $stmt->execute([$remove_id]);
        $remove_name = $stmt->fetchColumn();
        
        // 2. تحديث جميع الجداول المرتبطة
        $tables_to_update = [
            'attendance' => 'person_id',
            'student_surah_progress' => 'student_id',
            'student_daily_evaluations' => 'student_id',
            'student_achievements' => 'student_id',
            'student_monthly_goals' => 'student_id',
            'ring_students' => 'student_id',
            'student_payments' => 'student_id',
            'student_reports' => 'student_id',
            'daily_memorization_records' => 'student_id'
        ];
        
        $updated_count = 0;
        foreach ($tables_to_update as $table => $column) {
            try {
                $stmt = $pdo->prepare("UPDATE $table SET $column = ? WHERE $column = ?");
                $stmt->execute([$keep_id, $remove_id]);
                $updated_count += $stmt->rowCount();
            } catch (PDOException $e) {
                // تجاهل الأخطاء إذا كان الجدول غير موجود
                error_log("Table $table not found: " . $e->getMessage());
            }
        }
        
        // 3. تحديث حالة التكرارات في السجل (إزالة جميع السجلات التي تخص هذين الطالبين)
        $update_log = $pdo->prepare("
            UPDATE duplicate_students_log 
            SET status = 'resolved', 
                resolved_by = ?, 
                resolved_at = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ' | تم دمج ' , ?, ' مع ', ?)
            WHERE (student_id_1 = ? OR student_id_2 = ? OR student_id_1 = ? OR student_id_2 = ?)
            AND status = 'pending'
        ");
        $update_log->execute([$_SESSION['user_id'], $remove_name, $keep_name, $keep_id, $keep_id, $remove_id, $remove_id]);
        
        // 4. حذف الطالب المكرر
        $delete_stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $delete_stmt->execute([$remove_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "✅ تم دمج الطالبين بنجاح!\n
        تم نقل {$updated_count} سجل من '{$remove_name}' إلى '{$keep_name}'\n
        تم حذف الطالب المكرر '{$remove_name}'";
        
        header("Location: check_duplicate_students.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ في الدمج: " . $e->getMessage();
        header("Location: check_duplicate_students.php");
        exit;
    }
}

// ============================================
// معالجة تجاهل المكرر
// ============================================
if (isset($_GET['ignore']) && isset($_GET['id1']) && isset($_GET['id2'])) {
    $id1 = (int)$_GET['id1'];
    $id2 = (int)$_GET['id2'];
    
    $pdo->prepare("
        UPDATE duplicate_students_log 
        SET status = 'ignored', resolved_by = ?, resolved_at = NOW()
        WHERE (student_id_1 = ? AND student_id_2 = ?) OR (student_id_1 = ? AND student_id_2 = ?)
    ")->execute([$_SESSION['user_id'], $id1, $id2, $id2, $id1]);
    
    $_SESSION['success'] = "✅ تم تجاهل هذا الثنائي المكرر";
    header("Location: check_duplicate_students.php");
    exit;
}

// ============================================
// معالجة إعادة الكشف
// ============================================
if (isset($_GET['rescan'])) {
    detectDuplicateStudents($pdo, true);
    $_SESSION['success'] = "✅ تم إعادة فحص الطلاب المكررين";
    header("Location: check_duplicate_students.php");
    exit;
}

// ============================================
// الكشف عن المكررين
// ============================================
$duplicates = detectDuplicateStudents($pdo, true);

// جلب سجل المكررين pending
$duplicate_log = $pdo->query("
    SELECT d.*, 
           s1.name as name1, s1.parent_phone as phone1,
           s2.name as name2, s2.parent_phone as phone2
    FROM duplicate_students_log d
    JOIN students s1 ON d.student_id_1 = s1.id
    JOIN students s2 ON d.student_id_2 = s2.id
    WHERE d.status = 'pending'
    ORDER BY d.confidence_score DESC
")->fetchAll();

// إحصائيات
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_duplicates,
        SUM(CASE WHEN duplicate_type = 'exact_match' THEN 1 ELSE 0 END) as exact_matches,
        SUM(CASE WHEN duplicate_type = 'similar_name_same_phone' THEN 1 ELSE 0 END) as similar_matches
    FROM duplicate_students_log
    WHERE status = 'pending'
")->fetch();
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

.duplicate-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    text-align: center;
}

.page-header h1 {
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 10px;
}

.page-header p {
    opacity: 0.9;
}

.header-buttons {
    margin-top: 15px;
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-rescan {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.3s;
}

.btn-rescan:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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

.duplicate-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border-right: 6px solid;
}

.duplicate-card.high { border-right-color: var(--danger); }
.duplicate-card.medium { border-right-color: var(--warning); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--secondary);
}

.confidence-badge {
    padding: 5px 15px;
    border-radius: 30px;
    font-weight: bold;
    font-size: 0.85rem;
}

.confidence-high { background: var(--danger); color: white; }
.confidence-medium { background: var(--warning); color: #212529; }

.duplicate-type {
    font-size: 0.85rem;
    color: #666;
}

.students-compare {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 20px;
    margin: 20px 0;
    align-items: center;
}

.student-box {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
}

.student-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.student-phone {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
    direction: ltr;
}

.student-id {
    font-size: 0.7rem;
    color: #999;
    margin-top: 8px;
}

.compare-icon {
    font-size: 2rem;
    color: var(--secondary);
}

.reason-box {
    background: #fff3cd;
    border-radius: 15px;
    padding: 12px;
    margin: 15px 0;
    color: #856404;
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    padding: 12px;
    border-radius: 40px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.9rem;
}

.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-warning { background: var(--warning); color: #212529; }
.btn-primary { background: var(--primary); color: white; }

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 25px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--success);
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--primary);
    margin-bottom: 10px;
}

.alert {
    padding: 15px;
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

/* ===== نافذة الدمج ===== */
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

.modal.show { display: flex; }

.modal-content {
    background: white;
    border-radius: 30px;
    padding: 30px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
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
    font-size: 2rem;
    cursor: pointer;
    color: #999;
    transition: 0.3s;
}

.close-modal:hover {
    color: var(--danger);
    transform: rotate(90deg);
}

.warning-box {
    background: #fff3cd;
    padding: 15px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #856404;
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
    font-family: 'Cairo', sans-serif;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.modal-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

@media (max-width: 768px) {
    .duplicate-page { padding: 15px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .students-compare { grid-template-columns: 1fr; }
    .compare-icon { transform: rotate(90deg); }
    .action-buttons { flex-direction: column; }
    .modal-actions { flex-direction: column; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .card-header { flex-direction: column; align-items: flex-start; }
}
</style>

<section class="duplicate-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-clone"></i>
            إدارة الطلاب المكررين
        </h1>
        <p>الكشف عن الطلاب المكررين (نفس الاسم ونفس رقم الهاتف أو اسم متشابه مع نفس الرقم)</p>
        <div class="header-buttons">
            <a href="?rescan=1" class="btn-rescan">
                <i class="fas fa-sync-alt"></i> إعادة فحص
            </a>
            <a href="students.php" class="btn-rescan">
                <i class="fas fa-users"></i> إدارة الطلاب
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_duplicates'] ?? 0; ?></div>
            <div class="stat-label">إجمالي المكررين</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--danger);"><?php echo $stats['exact_matches'] ?? 0; ?></div>
            <div class="stat-label">تطابق تام (اسم + رقم)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--warning);"><?php echo $stats['similar_matches'] ?? 0; ?></div>
            <div class="stat-label">اسم متشابه + نفس الرقم</div>
        </div>
    </div>

    <?php if (empty($duplicate_log)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3>لا توجد طلاب مكررين</h3>
            <p>جميع الطلاب فريدون في قاعدة البيانات (لا يوجد نفس الاسم مع نفس رقم الهاتف)</p>
            <a href="students.php" class="btn btn-primary" style="margin-top: 15px;">
                <i class="fas fa-arrow-right"></i> العودة للطلاب
            </a>
        </div>
    <?php else: ?>
        <!-- قائمة المكررين -->
        <?php foreach ($duplicate_log as $dup): 
            $confidence = $dup['confidence_score'];
            $level_class = $confidence >= 95 ? 'high' : 'medium';
            $type_names = [
                'exact_match' => 'تطابق تام',
                'similar_name_same_phone' => 'اسم متشابه + نفس الرقم'
            ];
        ?>
            <div class="duplicate-card <?php echo $level_class; ?>">
                <div class="card-header">
                    <div>
                        <span class="confidence-badge confidence-<?php echo $level_class; ?>">
                            نسبة التطابق: <?php echo $confidence; ?>%
                        </span>
                        <span class="duplicate-type" style="margin-right: 10px;">
                            <i class="fas fa-tag"></i>
                            <?php echo $type_names[$dup['duplicate_type']] ?? $dup['duplicate_type']; ?>
                        </span>
                    </div>
                </div>

                <div class="students-compare">
                    <div class="student-box">
                        <div class="student-name">
                            <i class="fas fa-user-graduate"></i>
                            <?php echo htmlspecialchars($dup['name1']); ?>
                        </div>
                        <div class="student-phone" dir="ltr">
                            <i class="fas fa-phone"></i> <?php echo $dup['phone1'] ?? 'لا يوجد'; ?>
                        </div>
                        <div class="student-id">
                            <i class="fas fa-id-card"></i> ID: <?php echo $dup['student_id_1']; ?>
                        </div>
                    </div>
                    
                    <div class="compare-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    
                    <div class="student-box">
                        <div class="student-name">
                            <i class="fas fa-user-graduate"></i>
                            <?php echo htmlspecialchars($dup['name2']); ?>
                        </div>
                        <div class="student-phone" dir="ltr">
                            <i class="fas fa-phone"></i> <?php echo $dup['phone2'] ?? 'لا يوجد'; ?>
                        </div>
                        <div class="student-id">
                            <i class="fas fa-id-card"></i> ID: <?php echo $dup['student_id_1']; ?>
                        </div>
                    </div>
                    
                    <div class="compare-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    
                    <div class="student-box">
                        <div class="student-name">
                            <i class="fas fa-user-graduate"></i>
                            <?php echo htmlspecialchars($dup['name2']); ?>
                        </div>
                        <div class="student-phone" dir="ltr">
                            <i class="fas fa-phone"></i> <?php echo $dup['phone2'] ?? 'لا يوجد'; ?>
                        </div>
                        <div class="student-id">
                            <i class="fas fa-id-card"></i> ID: <?php echo $dup['student_id_2']; ?>
                        </div>
                    </div>
                </div>

                <div class="reason-box">
                    <i class="fas fa-info-circle"></i>
                    <span><?php echo htmlspecialchars($dup['notes']); ?></span>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-success" onclick="openMergeModal(<?php echo $dup['student_id_1']; ?>, <?php echo $dup['student_id_2']; ?>, '<?php echo addslashes($dup['name1']); ?>', '<?php echo addslashes($dup['name2']); ?>')">
                        <i class="fas fa-code-branch"></i> دمج الطالبين
                    </button>
                    <a href="?ignore=1&id1=<?php echo $dup['student_id_1']; ?>&id2=<?php echo $dup['student_id_2']; ?>" class="btn btn-warning" onclick="return confirm('هل أنت متأكد من تجاهل هذا التكرار؟')">
                        <i class="fas fa-eye-slash"></i> تجاهل
                    </a>
                    <a href="edit_student.php?id=<?php echo $dup['student_id_1']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> تعديل الأول
                    </a>
                    <a href="edit_student.php?id=<?php echo $dup['student_id_2']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> تعديل الثاني
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- نافذة دمج الطلاب -->
<div class="modal" id="mergeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-code-branch" style="color: var(--success);"></i> دمج الطلاب المكررين</h3>
            <button class="close-modal" onclick="closeMergeModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>تنبيه مهم:</strong> سيتم دمج جميع بيانات الطالب الثاني في الطالب الأول، ثم حذف الطالب الثاني.
                لا يمكن التراجع عن هذا الإجراء!
            </div>
        </div>
        
        <form method="post">
            <input type="hidden" name="keep_id" id="keepId">
            <input type="hidden" name="remove_id" id="removeId">
            
            <div class="form-group">
                <label><i class="fas fa-user-check"></i> الطالب الذي سيتم الاحتفاظ به</label>
                <select name="keep_id_select" id="keepSelect" class="form-control" onchange="updateKeepId()">
                    <option value="">-- اختر --</option>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات الدمج</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="سبب الدمج..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-warning" onclick="closeMergeModal()">إلغاء</button>
                <button type="submit" name="merge_students" class="btn btn-success" onclick="return confirm('تأكيد دمج الطالبين؟ لا يمكن التراجع!')">تأكيد الدمج</button>
            </div>
        </form>
    </div>
</div>

<script>
let student1Id, student2Id, student1Name, student2Name;

function openMergeModal(id1, id2, name1, name2) {
    student1Id = id1;
    student2Id = id2;
    student1Name = name1;
    student2Name = name2;
    
    const select = document.getElementById('keepSelect');
    select.innerHTML = `
        <option value="${id1}">${name1} (ID: ${id1})</option>
        <option value="${id2}">${name2} (ID: ${id2})</option>
    `;
    
    document.getElementById('keepId').value = id1;
    document.getElementById('mergeModal').classList.add('show');
}

function updateKeepId() {
    const select = document.getElementById('keepSelect');
    document.getElementById('keepId').value = select.value;
}

function closeMergeModal() {
    document.getElementById('mergeModal').classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeMergeModal();
    }
}

// تأثير ظهور تدريجي
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.duplicate-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    console.log('✅ صفحة إدارة المكررين جاهزة');
    console.log('📊 معايير الكشف: نفس الاسم + نفس الرقم، أو اسم متشابه + نفس الرقم');
});
</script>

<?php require_once 'includes/footer.php'; ?>
                          