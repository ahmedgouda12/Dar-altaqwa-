<?php
// ============================================
// ملف: teacher_quick_absence.php
// زر الاعتذار السريع للمعلم (غياب معذور)
// آخر تحديث: 2026-04-15
// ============================================

require_once 'config.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تسجيل اعتذار سريع';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$message = '';
$message_type = '';

// جلب معلومات المعلم
$teacher = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$teacher->execute([$teacher_id]);
$teacher_name = $teacher->fetchColumn();

// التحقق من وجود اعتذار مسجل مسبقاً لليوم
$check_excuse = $pdo->prepare("
    SELECT id, excuse_reason FROM attendance 
    WHERE person_type = 'teacher' AND person_id = ? AND date = ? AND status = 'absent' AND is_excused = 1
");
$check_excuse->execute([$teacher_id, $today]);
$existing_excuse = $check_excuse->fetch();

// جلب إحصائيات اليوم
$students_count = $pdo->prepare("
    SELECT COUNT(*) FROM students WHERE teacher_id = ?
");
$students_count->execute([$teacher_id]);
$total_students = $students_count->fetchColumn();

// ============================================
// معالجة تسجيل الاعتذار (بدون updated_at)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_excuse'])) {
        $reason = trim($_POST['reason']) ?: 'اعتذار سريع';
        
        try {
            $pdo->beginTransaction();
            
            // التحقق من وجود سجل حضور للمعلم اليوم
            $check = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE person_type = 'teacher' AND person_id = ? AND date = ?
            ");
            $check->execute([$teacher_id, $today]);
            $existing = $check->fetch();
            
            if ($existing) {
                // تحديث السجل الموجود - بدون updated_at
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = 'absent', 
                        notes = ?, 
                        is_excused = 1, 
                        excuse_reason = ?
                    WHERE person_type = 'teacher' AND person_id = ? AND date = ?
                ");
                $stmt->execute([$reason, $reason, $teacher_id, $today]);
            } else {
                // إدراج سجل جديد
                $stmt = $pdo->prepare("
                    INSERT INTO attendance 
                    (person_type, person_id, date, status, notes, is_excused, excuse_reason) 
                    VALUES ('teacher', ?, ?, 'absent', ?, 1, ?)
                ");
                $stmt->execute([$teacher_id, $today, $reason, $reason]);
            }
            
            // تسجيل غياب معذور لجميع طلاب المعلم
            $students = $pdo->prepare("SELECT id FROM students WHERE teacher_id = ?");
            $students->execute([$teacher_id]);
            $students_list = $students->fetchAll();
            
            $excused_count = 0;
            $student_excuse_reason = "غياب معذور - المعلم {$teacher_name} معتذر";
            
            foreach ($students_list as $student) {
                // التحقق من وجود سجل حضور للطالب اليوم
                $check_student = $pdo->prepare("
                    SELECT id FROM attendance 
                    WHERE person_type = 'student' AND person_id = ? AND date = ?
                ");
                $check_student->execute([$student['id'], $today]);
                
                if ($check_student->fetch()) {
                    // تحديث السجل الموجود
                    $update = $pdo->prepare("
                        UPDATE attendance 
                        SET is_excused = 1, 
                            excuse_reason = ?, 
                            notes = ?
                        WHERE person_type = 'student' AND person_id = ? AND date = ?
                    ");
                    $update->execute([$student_excuse_reason, $student_excuse_reason, $student['id'], $today]);
                    $excused_count++;
                } else {
                    // إدراج سجل جديد
                    $insert = $pdo->prepare("
                        INSERT INTO attendance 
                        (person_type, person_id, date, status, notes, is_excused, excuse_reason) 
                        VALUES ('student', ?, ?, 'absent', ?, 1, ?)
                    ");
                    $insert->execute([$student['id'], $today, $student_excuse_reason, $student_excuse_reason]);
                    $excused_count++;
                }
            }
            
            $pdo->commit();
            
            $message = "✅ تم تسجيل اعتذارك بنجاح! تم إعذار $excused_count طالب من الغياب.";
            $message_type = 'success';
            
            // تحديث الصفحة لإظهار حالة الاعتذار
            echo "<meta http-equiv='refresh' content='2'>";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ============================================
    // معالجة إلغاء الاعتذار
    // ============================================
    if (isset($_POST['cancel_excuse'])) {
        try {
            $pdo->beginTransaction();
            
            // حذف اعتذار المعلم (أو تحديثه إلى غير معذور)
            $pdo->prepare("
                DELETE FROM attendance 
                WHERE person_type = 'teacher' AND person_id = ? AND date = ? AND is_excused = 1
            ")->execute([$teacher_id, $today]);
            
            // حذف إعذار الطلاب
            $pdo->prepare("
                DELETE FROM attendance 
                WHERE person_type = 'student' AND date = ? 
                AND excuse_reason LIKE 'غياب معذور - المعلم%'
            ")->execute([$today]);
            
            $pdo->commit();
            
            $message = "✅ تم إلغاء الاعتذار بنجاح";
            $message_type = 'success';
            echo "<meta http-equiv='refresh' content='2'>";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// تحديث المتغيرات بعد المعالجة
if ($message_type == 'success') {
    // إعادة جلب حالة الاعتذار
    $check_excuse = $pdo->prepare("
        SELECT id, excuse_reason FROM attendance 
        WHERE person_type = 'teacher' AND person_id = ? AND date = ? AND status = 'absent' AND is_excused = 1
    ");
    $check_excuse->execute([$teacher_id, $today]);
    $existing_excuse = $check_excuse->fetch();
}
?>

<style>
.quick-absence-page {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 25px;
    margin-bottom: 25px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.page-header h1 {
    margin: 0;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.page-header h1 i {
    color: #c9a96b;
}

.page-header p {
    position: relative;
    z-index: 2;
    margin-top: 10px;
    opacity: 0.9;
}

.info-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    border-right: 5px solid #17a2b8;
}

.info-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e7f3ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #17a2b8;
}

.info-content {
    flex: 1;
}

.info-content h3 {
    color: #1e3c3f;
    margin-bottom: 5px;
}

.info-content p {
    color: #666;
    font-size: 0.85rem;
}

.status-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.status-card.excused {
    background: linear-gradient(135deg, #fff3cd, #ffe69c);
    border-right: 5px solid #ffc107;
}

.status-card.normal {
    background: linear-gradient(135deg, #d4edda, #c8e6c9);
    border-right: 5px solid #28a745;
}

.status-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.status-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.status-desc {
    color: #666;
    margin-bottom: 15px;
}

.form-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    resize: vertical;
}

.form-control:focus {
    outline: none;
    border-color: #c9a96b;
}

.btn {
    width: 100%;
    padding: 14px;
    border-radius: 50px;
    border: none;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-excuse {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #212529;
}

.btn-excuse:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(255,193,7,0.3);
}

.btn-cancel {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

.btn-cancel:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(220,53,69,0.3);
}

.btn-back {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
    margin-top: 10px;
    text-decoration: none;
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
    border-right: 5px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.stats-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 12px;
    margin: 15px 0;
}

.stats-label {
    font-weight: 600;
    color: #1e3c3f;
}

.stats-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #c9a96b;
}

@media (max-width: 768px) {
    .quick-absence-page {
        padding: 15px;
    }
    
    .page-header h1 {
        font-size: 1.4rem;
    }
    
    .info-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<section class="quick-absence-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-calendar-times"></i>
            اعتذار سريع
        </h1>
        <p>تسجيل اعتذار عن الحضور اليوم مع إعذار طلابك تلقائياً</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- بطاقة المعلومات -->
    <div class="info-card">
        <div class="info-icon">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="info-content">
            <h3>ماذا يحدث عند تسجيل الاعتذار؟</h3>
            <p>
                • يتم تسجيل غياب معذور لك<br>
                • لا يحسب لك غياب<br>
                • يتم إعذار جميع طلابك تلقائياً<br>
                • لا يحسب لطلابك غياب<br>
                • لن تظهر في قائمة الحضور لهذا اليوم
            </p>
        </div>
    </div>

    <!-- الحالة الحالية -->
    <?php if ($existing_excuse): ?>
        <div class="status-card excused">
            <div class="status-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="status-title">أنت معتذر اليوم</div>
            <div class="status-desc">
                تم تسجيل اعتذارك بتاريخ <?php echo $today; ?>
                <?php if ($existing_excuse['excuse_reason']): ?>
                    <br><strong>السبب:</strong> <?php echo htmlspecialchars($existing_excuse['excuse_reason']); ?>
                <?php endif; ?>
            </div>
            <div class="stats-row">
                <span class="stats-label">طلابك المعذورين:</span>
                <span class="stats-value"><?php echo $total_students; ?> طالب</span>
            </div>
            <form method="post">
                <button type="submit" name="cancel_excuse" class="btn btn-cancel" onclick="return confirm('هل أنت متأكد من إلغاء الاعتذار؟')">
                    <i class="fas fa-undo-alt"></i> إلغاء الاعتذار
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="status-card normal">
            <div class="status-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="status-title">لا يوجد اعتذار اليوم</div>
            <div class="status-desc">
                أنت غير معتذر اليوم. يمكنك تسجيل اعتذار إذا كنت غير قادر على الحضور.
            </div>
            <div class="stats-row">
                <span class="stats-label">عدد طلابك:</span>
                <span class="stats-value"><?php echo $total_students; ?> طالب</span>
            </div>
            <div class="stats-row">
                <span class="stats-label">سيتم إعذارهم تلقائياً:</span>
                <span class="stats-value" style="color: #28a745;">✅ نعم</span>
            </div>
        </div>

        <!-- نموذج تسجيل الاعتذار -->
        <div class="form-card">
            <form method="post">
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> سبب الاعتذار (اختياري)</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="مثال: ظرف صحي طارئ، عذر عائلي، ..."></textarea>
                </div>
                <button type="submit" name="record_excuse" class="btn btn-excuse" onclick="return confirm('هل أنت متأكد من تسجيل الاعتذار اليوم؟ سيتم إعذار جميع طلابك تلقائياً.')">
                    <i class="fas fa-calendar-times"></i> تسجيل الاعتذار اليوم
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- زر العودة -->
    <a href="teacher_dashboard.php" class="btn btn-back" style="display: flex; text-decoration: none;">
        <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
    </a>
</section>

<?php require_once 'includes/footer.php'; ?>