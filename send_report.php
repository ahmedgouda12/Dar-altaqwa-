<?php
// ============================================
// ملف: send_report.php - إرسال تقرير وحفظه في قاعدة البيانات
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$error = '';
$whatsapp_url = '';

if (!$student_id) {
    $error = 'الطالب غير محدد.';
} else {
    // جلب بيانات الطالب مع التأكد من الصلاحية
    if (isTeacher()) {
        $stmt = $pdo->prepare("
            SELECT s.*, t.name as teacher_name, t.id as teacher_id,
                   (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_surahs,
                   (SELECT COUNT(*) FROM attendance WHERE person_type='student' AND person_id = s.id AND MONTH(date) = MONTH(CURDATE()) AND status='present') as present_days
            FROM students s 
            LEFT JOIN teachers t ON s.teacher_id = t.id 
            WHERE s.id = ? AND s.teacher_id = ?
        ");
        $stmt->execute([$student_id, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, t.name as teacher_name, t.id as teacher_id,
                   (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_surahs,
                   (SELECT COUNT(*) FROM attendance WHERE person_type='student' AND person_id = s.id AND MONTH(date) = MONTH(CURDATE()) AND status='present') as present_days
            FROM students s 
            LEFT JOIN teachers t ON s.teacher_id = t.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$student_id]);
    }
    $student = $stmt->fetch();

    if (!$student) {
        $error = 'الطالب غير موجود أو لا تملك صلاحية الوصول.';
    }
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && isset($student)) {
    $new_memorization = trim($_POST['new_memorization'] ?? '');
    $recent_review = trim($_POST['recent_review'] ?? '');
    $old_review = trim($_POST['old_review'] ?? '');
    $evaluation = (int)($_POST['evaluation'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    $send_whatsapp = isset($_POST['send_whatsapp']) ? true : false;
    $save_report = isset($_POST['save_report']) ? true : true; // دائماً نحفظ التقرير
    
    // بناء نص التقرير
    $evaluation_text = [
        1 => 'ممتاز 🌟🌟🌟',
        2 => 'جيد جداً 🌟🌟',
        3 => 'جيد 🌟',
        4 => 'مقبول',
        5 => 'يحتاج لمتابعة'
    ];
    
    $report_content = "📋 *تقرير متابعة الطالب*\n";
    $report_content .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $report_content .= "📅 التاريخ: " . date('Y-m-d') . "\n";
    $report_content .= "👤 الطالب: *" . $student['name'] . "*\n";
    $report_content .= "👨‍🏫 المعلم: " . ($student['teacher_name'] ?? 'غير محدد') . "\n";
    $report_content .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $report_content .= "✅ *الحفظ الجديد*\n";
    $report_content .= "─────────────────\n";
    $report_content .= ($new_memorization ?: "لم يسجل") . "\n\n";
    
    $report_content .= "🔄 *مراجعة قريبة*\n";
    $report_content .= "─────────────────\n";
    $report_content .= ($recent_review ?: "لم يسجل") . "\n\n";
    
    $report_content .= "📖 *مراجعة بعيدة*\n";
    $report_content .= "─────────────────\n";
    $report_content .= ($old_review ?: "لم يسجل") . "\n\n";
    
    $report_content .= "⭐ *التقييم العام*\n";
    $report_content .= "─────────────────\n";
    $report_content .= $evaluation_text[$evaluation] . "\n\n";
    
    if (!empty($notes)) {
        $report_content .= "📝 *ملاحظات*\n";
        $report_content .= "─────────────────\n";
        $report_content .= $notes . "\n\n";
    }
    
    $report_content .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $report_content .= "جزاكم الله خيراً على متابعتكم.";
    
    // حفظ التقرير في قاعدة البيانات
    if ($save_report) {
        $save_stmt = $pdo->prepare("
            INSERT INTO student_reports 
            (student_id, teacher_id, report_date, report_content, new_memorization, recent_review, old_review, evaluation_score, notes)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)
        ");
        $save_stmt->execute([
            $student['id'],
            $_SESSION['user_id'],
            $report_content,
            $new_memorization,
            $recent_review,
            $old_review,
            $evaluation,
            $notes
        ]);
        
        $_SESSION['success_message'] = "✅ تم حفظ التقرير بنجاح";
    }
    
    // إرسال عبر واتساب إذا تم الاختيار
    if ($send_whatsapp && !empty($student['parent_phone'])) {
        $phone = formatWhatsAppNumber($student['parent_phone']);
        if ($phone) {
            $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($report_content);
            $_SESSION['whatsapp_url'] = $whatsapp_url;
        }
    }
    
    header("Location: send_report.php?student_id=$student_id&success=1");
    exit;
}

// عرض رسائل النجاح
$success_param = isset($_GET['success']) ? true : false;
$success_message = $_SESSION['success_message'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
unset($_SESSION['success_message'], $_SESSION['whatsapp_url']);

$pageTitle = 'إرسال تقرير متابعة';
require_once 'includes/header.php';
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
        --whatsapp: #25d366;
        --gray: #6c757d;
        --gray-light: #e9ecef;
        --radius: 20px;
        --radius-sm: 12px;
        --shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Cairo', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        min-height: 100vh;
        padding: 40px 20px;
    }
    
    .report-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .report-card {
        background: white;
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    
    .report-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
        padding: 30px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .report-header::before {
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
    
    .report-header h1 {
        font-size: 1.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        position: relative;
        z-index: 2;
    }
    
    .report-header h1 i {
        color: var(--secondary);
    }
    
    .report-header p {
        position: relative;
        z-index: 2;
        opacity: 0.9;
        margin-top: 10px;
    }
    
    .report-body {
        padding: 35px;
    }
    
    .student-info-card {
        background: #f8f9fa;
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 25px;
        border-right: 4px solid var(--secondary);
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .student-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: bold;
        border: 3px solid var(--secondary);
    }
    
    .student-details h3 {
        color: var(--primary);
        font-size: 1.3rem;
        margin-bottom: 5px;
    }
    
    .student-details p {
        color: var(--gray);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .view-reports-btn {
        background: var(--info);
        color: white;
        padding: 10px 20px;
        border-radius: 30px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }
    
    .view-reports-btn:hover {
        background: #138496;
        transform: translateY(-2px);
    }
    
    .form-section {
        background: #f8f9fa;
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--primary);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--secondary);
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: var(--primary);
    }
    
    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        font-size: 1rem;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--secondary);
    }
    
    .evaluation-options {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .evaluation-option {
        flex: 1;
        text-align: center;
        padding: 10px;
        background: white;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: 0.3s;
    }
    
    .evaluation-option.selected {
        background: var(--primary);
        color: white;
        border-color: var(--secondary);
    }
    
    .btn {
        padding: 12px 20px;
        border-radius: 50px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
    }
    
    .btn-whatsapp {
        background: var(--whatsapp);
        color: white;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .alert {
        padding: 15px;
        border-radius: var(--radius-sm);
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
    
    @media (max-width: 768px) {
        .report-body { padding: 20px; }
        .action-buttons { flex-direction: column; }
        .evaluation-options { flex-direction: column; }
    }
</style>

<section class="report-container">
    <div class="report-card">
        <div class="report-header">
            <h1><i class="fab fa-whatsapp"></i> إرسال تقرير متابعة</h1>
            <p>قم بإعداد تقرير متابعة للطالب وإرساله عبر واتساب</p>
        </div>
        
        <div class="report-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <div style="text-align: center;">
                    <a href="students.php" class="btn btn-primary">العودة للطلاب</a>
                </div>
            <?php elseif (isset($student)): ?>
                
                <?php if ($success_param): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message ?: "تم حفظ التقرير بنجاح"; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($whatsapp_url): ?>
                    <div class="alert alert-success" style="background: #e8f5e9;">
                        <i class="fab fa-whatsapp"></i> 
                        <a href="<?php echo $whatsapp_url; ?>" target="_blank" style="color: #155724; font-weight: bold;">اضغط هنا لإرسال التقرير عبر واتساب</a>
                    </div>
                <?php endif; ?>
                
                <!-- بطاقة معلومات الطالب -->
                <div class="student-info-card">
                    <div class="student-avatar">
                        <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div class="student-details">
                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                        <p>
                            <span><i class="fas fa-chalkboard-teacher"></i> المعلم: <?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></span>
                            <span><i class="fas fa-phone"></i> <?php echo $student['parent_phone'] ?: 'لا يوجد'; ?></span>
                        </p>
                    </div>
                    <div style="margin-right: auto;">
                        <a href="student_reports.php?student_id=<?php echo $student['id']; ?>" class="view-reports-btn">
                            <i class="fas fa-list-alt"></i> عرض التقارير السابقة
                        </a>
                    </div>
                </div>
                
                <form method="post" id="reportForm">
                    <!-- الحفظ الجديد -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-book-open"></i>
                            <h3>الحفظ الجديد</h3>
                        </div>
                        <textarea name="new_memorization" class="form-control" rows="2" placeholder="مثال: سورة الملك من آية 1 إلى 10"></textarea>
                    </div>
                    
                    <!-- المراجعة القريبة -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-history"></i>
                            <h3>المراجعة القريبة</h3>
                        </div>
                        <textarea name="recent_review" class="form-control" rows="2" placeholder="مثال: مراجعة سورة الكهف كاملة"></textarea>
                    </div>
                    
                    <!-- المراجعة البعيدة -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-backward"></i>
                            <h3>المراجعة البعيدة</h3>
                        </div>
                        <textarea name="old_review" class="form-control" rows="2" placeholder="مثال: مراجعة الأجزاء 1-5"></textarea>
                    </div>
                    
                    <!-- التقييم -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-star"></i>
                            <h3>التقييم العام</h3>
                        </div>
                        <div class="evaluation-options" id="evaluationOptions">
                            <div class="evaluation-option" data-value="1">ممتاز 🌟🌟🌟</div>
                            <div class="evaluation-option" data-value="2">جيد جداً 🌟🌟</div>
                            <div class="evaluation-option" data-value="3">جيد 🌟</div>
                            <div class="evaluation-option" data-value="4">مقبول</div>
                            <div class="evaluation-option" data-value="5">يحتاج لمتابعة</div>
                        </div>
                        <input type="hidden" name="evaluation" id="evaluationValue" value="1">
                    </div>
                    
                    <!-- ملاحظات -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-sticky-note"></i>
                            <h3>ملاحظات إضافية</h3>
                        </div>
                        <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات إضافية..."></textarea>
                    </div>
                    
                    <!-- خيارات -->
                    <div class="form-section" style="background: white;">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="send_whatsapp" value="1" checked>
                            <i class="fab fa-whatsapp"></i> إرسال عبر واتساب
                        </label>
                        <small style="color: #666;">سيتم حفظ التقرير تلقائياً في سجل الطالب</small>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التقرير
                        </button>
                        <button type="submit" name="send_whatsapp_only" class="btn btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> حفظ وإرسال عبر واتساب
                        </button>
                        <a href="student_reports.php?student_id=<?php echo $student['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-list-alt"></i> عرض التقارير السابقة
                        </a>
                    </div>
                </form>
                
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// خيارات التقييم
document.querySelectorAll('.evaluation-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.evaluation-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('evaluationValue').value = this.dataset.value;
    });
});

// تحديد الخيار الافتراضي
document.querySelector('.evaluation-option[data-value="1"]').classList.add('selected');
</script>

<?php require_once 'includes/footer.php'; ?>