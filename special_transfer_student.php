<?php
// ============================================
// ملف: special_transfer_student.php
// نقل طالب خاص إلى معلم آخر مع إشعار تلقائي
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'نقل طالب خاص';
require_once 'includes/header.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$student = $pdo->prepare("
    SELECT s.*, t.name as current_teacher_name, t.phone as current_teacher_phone
    FROM special_students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.id = ?
");
$student->execute([$student_id]);
$student = $student->fetch();

if (!$student) {
    echo '<div class="alert alert-error">الطالب غير موجود</div>';
    require_once 'includes/footer.php';
    exit;
}

$teachers = $pdo->query("SELECT id, name, phone FROM teachers WHERE can_login = 1 AND id != " . ($student['teacher_id'] ?: 0) . " ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_teacher_id = (int)$_POST['new_teacher_id'];
    $transfer_reason = trim($_POST['transfer_reason'] ?? '');
    $send_whatsapp = isset($_POST['send_whatsapp']);
    
    $new_teacher = $pdo->prepare("SELECT id, name, phone FROM teachers WHERE id = ?");
    $new_teacher->execute([$new_teacher_id]);
    $new_teacher_data = $new_teacher->fetch();
    
    if (!$new_teacher_data) {
        $error = "❌ المعلم غير موجود";
    } else {
        try {
            $pdo->beginTransaction();
            
            // تحديث معلم الطالب
            $pdo->prepare("UPDATE special_students SET teacher_id = ? WHERE id = ?")->execute([$new_teacher_id, $student_id]);
            
            // تسجيل النقل
            $pdo->exec("CREATE TABLE IF NOT EXISTS special_transfer_logs (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT, old_teacher_id INT, new_teacher_id INT, transfer_reason TEXT, transferred_by INT, transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $pdo->prepare("INSERT INTO special_transfer_logs (student_id, old_teacher_id, new_teacher_id, transfer_reason, transferred_by) VALUES (?, ?, ?, ?, ?)")->execute([$student_id, $student['teacher_id'], $new_teacher_id, $transfer_reason, $_SESSION['user_id']]);
            
            $pdo->commit();
            
            // إرسال إشعارات واتساب
            if ($send_whatsapp) {
                $message = "السلام عليكم ورحمة الله وبركاته\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📋 *إشعار نقل طالب خاص*\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n👤 *الطالب:* {$student['student_name']}\n👨‍🏫 *المعلم السابق:* {$student['current_teacher_name']}\n👨‍🏫 *المعلم الجديد:* {$new_teacher_data['name']}\n📝 *السبب:* " . ($transfer_reason ?: 'لا يوجد') . "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nدار التقوى لتحفيظ القرآن الكريم";
                
                if (!empty($new_teacher_data['phone'])) {
                    $phone = formatWhatsAppNumber($new_teacher_data['phone']);
                    if ($phone) $_SESSION['whatsapp_url'] = "https://wa.me/{$phone}?text=" . urlencode($message);
                }
                if (!empty($student['parent_phone'])) {
                    $parent_phone = formatWhatsAppNumber($student['parent_phone']);
                    if ($parent_phone) $_SESSION['whatsapp_url_parent'] = "https://wa.me/{$parent_phone}?text=" . urlencode($message);
                }
            }
            
            $_SESSION['success'] = "✅ تم نقل الطالب بنجاح إلى المعلم {$new_teacher_data['name']}";
            header("Location: special_students.php");
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}

$success_message = $_SESSION['success'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
$whatsapp_url_parent = $_SESSION['whatsapp_url_parent'] ?? '';
unset($_SESSION['success'], $_SESSION['whatsapp_url'], $_SESSION['whatsapp_url_parent']);
?>

<style>
.transfer-page {
    max-width: 700px;
    margin: 0 auto;
    padding: 20px;
}
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    text-align: center;
}
.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e3c3f; }
.form-control { width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 12px; }
.btn { width: 100%; padding: 12px; border-radius: 30px; border: none; font-weight: 600; cursor: pointer; transition: 0.3s; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.whatsapp-notice { background: #e8f5e9; border-right: 5px solid #25d366; margin-bottom: 20px; }
</style>

<section class="transfer-page">
    <div class="page-header"><h1><i class="fas fa-exchange-alt"></i> نقل طالب خاص</h1><p><?php echo htmlspecialchars($student['student_name']); ?></p></div>
    <?php if ($whatsapp_url): ?><div class="alert alert-success whatsapp-notice"><i class="fab fa-whatsapp"></i> <a href="<?php echo $whatsapp_url; ?>" target="_blank">إرسال إشعار للمعلم الجديد</a></div><?php endif; ?>
    <?php if ($whatsapp_url_parent): ?><div class="alert alert-success whatsapp-notice"><i class="fab fa-whatsapp"></i> <a href="<?php echo $whatsapp_url_parent; ?>" target="_blank">إرسال إشعار لولي الأمر</a></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
    <form method="post" class="form-card">
        <div class="form-group"><label><i class="fas fa-chalkboard-teacher"></i> المعلم الجديد</label><select name="new_teacher_id" class="form-control" required><option value="">-- اختر --</option><?php foreach ($teachers as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label><i class="fas fa-sticky-note"></i> سبب النقل</label><textarea name="transfer_reason" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label class="checkbox-label"><input type="checkbox" name="send_whatsapp" value="1" checked> <i class="fab fa-whatsapp"></i> إرسال إشعار واتساب للمعلم الجديد وولي الأمر</label></div>
        <button type="submit" class="btn">تأكيد النقل</button>
    </form>
</section>

<?php require_once 'includes/footer.php'; ?>