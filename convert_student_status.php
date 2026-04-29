<?php
// ============================================
// ملف: convert_student_status.php
// تحويل حالة الطالب (خاص / عادي) - مع الرجوع إلى صفحة الطلاب
// آخر تحديث: 2026-04-03
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تحويل حالة الطالب';
require_once 'includes/header.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// جلب معلومات الطالب
$student = $pdo->prepare("
    SELECT s.*, t.name as teacher_name, t.phone as teacher_phone
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.id = ?
");
$student->execute([$student_id]);
$student_data = $student->fetch();

if (!$student_data) {
    $_SESSION['error'] = "❌ الطالب غير موجود";
    header("Location: students.php");
    exit;
}

// التحقق من الصلاحية
if (isTeacher() && $student_data['teacher_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "❌ لا يمكنك تعديل طالب ليس من طلابك";
    header("Location: students.php");
    exit;
}

// تحديد صفحة العودة - دائماً إلى students.php
$return_url = 'students.php';

$current_status = $student_data['is_special'] ? 'خاص' : 'عادي';
$target_status = $current_status == 'خاص' ? 'عادي' : 'خاص';
$action_type = $current_status == 'خاص' ? 'remove' : 'make';

// معالجة التحويل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
    $notes = trim($_POST['notes'] ?? '');
    $send_notification = isset($_POST['send_notification']);
    
    try {
        $pdo->beginTransaction();
        
        // تحديث حالة الطالب
        $new_status = ($current_status == 'خاص') ? 0 : 1;
        $stmt = $pdo->prepare("
            UPDATE students SET 
                is_special = ?,
                special_approved_by = ?,
                special_approved_at = ?,
                special_notes = ?
            WHERE id = ?
        ");
        
        $approved_by = ($new_status == 1) ? $_SESSION['user_id'] : null;
        $approved_at = ($new_status == 1) ? date('Y-m-d H:i:s') : null;
        $special_notes = ($new_status == 1) ? $notes : null;
        
        $stmt->execute([$new_status, $approved_by, $approved_at, $special_notes, $student_id]);
        
        // تسجيل في سجل التحويلات
        $log = $pdo->prepare("
            INSERT INTO special_conversion_logs 
            (student_id, teacher_id, action, notes, performed_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $log->execute([
            $student_id,
            $student_data['teacher_id'],
            $action_type,
            $notes,
            $_SESSION['user_id']
        ]);
        
        // إضافة إشعار للمعلم
        $notification_title = ($new_status == 1) ? '👑 طالب خاص جديد' : '📋 إلغاء خاصية الطالب الخاص';
        $notification_message = ($new_status == 1) 
            ? "تم تحويل الطالب {$student_data['name']} إلى طالب خاص" 
            : "تم إلغاء خاصية الطالب الخاص عن {$student_data['name']}";
        
        $notify = $pdo->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, type, link)
            VALUES (?, 'teacher', ?, ?, 'info', ?)
        ");
        $notify->execute([
            $student_data['teacher_id'],
            $notification_title,
            $notification_message,
            "view_progress.php?student_id={$student_id}"
        ]);
        
        // إرسال إشعار واتساب
        $whatsapp_url = '';
        if ($send_notification && !empty($student_data['parent_phone'])) {
            $phone = formatWhatsAppNumber($student_data['parent_phone']);
            if ($phone) {
                $status_text = ($new_status == 1) ? 'طالب خاص' : 'طالب عادي';
                $message = "السلام عليكم ورحمة الله وبركاته\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "📋 *تحديث حالة الطالب*\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "👤 *الطالب/ة:* {$student_data['name']}\n";
                $message .= "📌 *الحالة الجديدة:* {$status_text}\n";
                if ($notes) {
                    $message .= "📝 *ملاحظات:* {$notes}\n";
                }
                $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "دار التقوى لتحفيظ القرآن الكريم";
                
                $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "✅ تم تحويل الطالب إلى {$target_status} بنجاح";
        if ($whatsapp_url) {
            $_SESSION['whatsapp_url'] = $whatsapp_url;
        }
        
        // الرجوع التلقائي إلى صفحة الطلاب
        echo "<!DOCTYPE html>
        <html dir='rtl' lang='ar'>
        <head>
            <meta charset='UTF-8'>
            <meta http-equiv='refresh' content='2;url=students.php'>
            <title>جاري التحويل...</title>
            <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
            <style>
                body {
                    font-family: 'Cairo', sans-serif;
                    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .success-card {
                    background: white;
                    border-radius: 30px;
                    padding: 40px;
                    text-align: center;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    max-width: 500px;
                    width: 100%;
                    animation: fadeIn 0.5s ease;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .success-icon {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #28a745, #20c997);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                }
                .success-icon i {
                    font-size: 3rem;
                    color: white;
                }
                h2 {
                    color: #1e3c3f;
                    margin-bottom: 10px;
                }
                p {
                    color: #666;
                    margin-bottom: 20px;
                }
                .loader {
                    width: 40px;
                    height: 40px;
                    border: 3px solid #e9ecef;
                    border-top: 3px solid #c9a96b;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 20px auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .redirect-link {
                    color: #c9a96b;
                    text-decoration: none;
                    font-weight: 600;
                }
                .redirect-link:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class='success-card'>
                <div class='success-icon'>
                    <i class='fas fa-check-circle'></i>
                </div>
                <h2>✅ تم التحويل بنجاح!</h2>
                <p>تم تحويل الطالب <strong>{$student_data['name']}</strong> إلى <strong>{$target_status}</strong></p>
                <div class='loader'></div>
                <p>جاري إعادتك إلى صفحة الطلاب...</p>
                <p><a href='students.php' class='redirect-link'>اضغط هنا إذا لم يتم تحويلك تلقائياً</a></p>
            </div>
        </body>
        </html>";
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

$success_message = $_SESSION['success'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
unset($_SESSION['success'], $_SESSION['whatsapp_url']);
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
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.convert-page {
    max-width: 700px;
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
    font-size: 1.8rem;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.page-header h1 i {
    color: var(--secondary);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.student-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid #eee;
}

.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
    border: 3px solid var(--secondary);
}

.student-name {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 5px;
}

.student-details {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    color: #666;
    font-size: 0.9rem;
}

.student-details i {
    color: var(--secondary);
    margin-left: 5px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1rem;
}

.status-special {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.status-normal {
    background: #e9ecef;
    color: #6c757d;
}

.convert-card {
    background: white;
    border-radius: 25px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid #eee;
}

.convert-header {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--secondary);
}

.convert-header h2 {
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.convert-arrow {
    text-align: center;
    font-size: 3rem;
    color: var(--secondary);
    margin: 20px 0;
}

.status-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.status-box-item {
    flex: 1;
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 20px;
}

.status-box-item.current {
    border-right: 4px solid var(--warning);
}

.status-box-item.target {
    border-left: 4px solid var(--success);
}

.status-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 10px;
}

.status-value {
    font-size: 1.3rem;
    font-weight: 800;
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

.form-group label i {
    color: var(--secondary);
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    font-size: 1rem;
    transition: 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.options-group {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 20px 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 10px;
    background: white;
    border-radius: 12px;
    transition: 0.3s;
}

.checkbox-label:hover {
    background: #e9ecef;
}

.checkbox-label input {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-label i {
    font-size: 1.3rem;
    color: var(--whatsapp);
}

.info-box {
    background: #e7f3ff;
    border-radius: 15px;
    padding: 15px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    border-right: 4px solid var(--info);
}

.info-box i {
    font-size: 1.5rem;
    color: var(--info);
}

.info-box-content {
    flex: 1;
}

.info-box-content strong {
    color: #0c5460;
}

.info-box-content p {
    color: #0c5460;
    font-size: 0.85rem;
    margin-top: 5px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    padding: 14px 25px;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    .convert-page { padding: 15px; }
    .student-card { flex-direction: column; text-align: center; }
    .student-details { justify-content: center; }
    .status-box { flex-direction: column; }
    .status-box-item { width: 100%; }
    .status-box-item.current { border-right: none; border-bottom: 4px solid var(--warning); }
    .status-box-item.target { border-left: none; border-top: 4px solid var(--success); }
    .action-buttons { flex-direction: column; }
    .btn { width: 100%; }
    .convert-arrow { transform: rotate(90deg); }
}
</style>

<section class="convert-page">
    <div class="page-header">
        <h1><i class="fas fa-exchange-alt"></i> تحويل حالة الطالب</h1>
        <p>تحويل الطالب بين الحالتين (خاص / عادي)</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($whatsapp_url): ?>
        <div class="alert alert-whatsapp">
            <i class="fab fa-whatsapp" style="font-size: 1.5rem;"></i>
            <div style="flex: 1;"><strong>تم التحويل بنجاح!</strong> هل تريد إرسال إشعار واتساب لولي الأمر؟</div>
            <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="whatsapp-link"><i class="fab fa-whatsapp"></i> إرسال</a>
        </div>
    <?php endif; ?>

    <div class="student-card">
        <div class="student-avatar"><?php echo mb_substr($student_data['name'], 0, 1, 'UTF-8'); ?></div>
        <div class="student-info">
            <div class="student-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
            <div class="student-details">
                <span><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student_data['teacher_name'] ?? 'غير محدد'); ?></span>
                <span><i class="fas fa-phone"></i> <?php echo $student_data['parent_phone'] ?: 'لا يوجد'; ?></span>
                <span><i class="fas fa-calendar"></i> منذ <?php echo date('Y-m-d', strtotime($student_data['created_at'])); ?></span>
            </div>
        </div>
        <div class="status-badge <?php echo $current_status == 'خاص' ? 'status-special' : 'status-normal'; ?>">
            <i class="fas <?php echo $current_status == 'خاص' ? 'fa-crown' : 'fa-user'; ?>"></i> <?php echo $current_status; ?>
        </div>
    </div>

    <div class="convert-card">
        <div class="convert-header"><h2><i class="fas fa-exchange-alt"></i> تحويل حالة الطالب</h2></div>
        <div class="status-box">
            <div class="status-box-item current"><div class="status-label">الحالة الحالية</div><div class="status-value"><i class="fas <?php echo $current_status == 'خاص' ? 'fa-crown' : 'fa-user'; ?>"></i> <?php echo $current_status; ?></div></div>
            <div class="convert-arrow"><i class="fas fa-arrow-left"></i></div>
            <div class="status-box-item target"><div class="status-label">الحالة بعد التحويل</div><div class="status-value"><i class="fas <?php echo $target_status == 'خاص' ? 'fa-crown' : 'fa-user'; ?>"></i> <?php echo $target_status; ?></div></div>
        </div>
        <form method="post">
            <div class="form-group"><label><i class="fas fa-sticky-note"></i> سبب التحويل (اختياري)</label><textarea name="notes" class="form-control" rows="3" placeholder="اكتب سبب التحويل..."></textarea></div>
            <div class="options-group"><label class="checkbox-label"><input type="checkbox" name="send_notification" value="1" checked> <i class="fab fa-whatsapp"></i> <span>إرسال إشعار واتساب لولي الأمر</span></label></div>
            <div class="info-box"><i class="fas fa-info-circle"></i><div class="info-box-content"><strong>ملاحظة مهمة:</strong><p>تحويل الطالب إلى <?php echo $target_status; ?> سيؤثر على صلاحياته والميزات المتاحة له في المنصة.</p></div></div>
            <div class="action-buttons">
                <a href="students.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> إلغاء</a>
                <button type="submit" name="convert" class="btn btn-success" onclick="return confirm('هل أنت متأكد من تحويل الطالب إلى <?php echo $target_status; ?>؟')"><i class="fas fa-exchange-alt"></i> تأكيد التحويل إلى <?php echo $target_status; ?></button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>