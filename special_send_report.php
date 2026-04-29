<?php
// ============================================
// ملف: special_send_report.php
// إرسال تقرير متابعة لطالب خاص
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إرسال تقرير - طالب خاص';
require_once 'includes/header.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$student = $pdo->prepare("SELECT s.*, t.name as teacher_name FROM special_students s LEFT JOIN teachers t ON s.teacher_id = t.id WHERE s.id = ?");
$student->execute([$student_id]);
$student = $student->fetch();

if (!$student) {
    echo '<div class="alert alert-error">الطالب غير موجود</div>';
    require_once 'includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_memorization = trim($_POST['new_memorization'] ?? '');
    $recent_review = trim($_POST['recent_review'] ?? '');
    $old_review = trim($_POST['old_review'] ?? '');
    $evaluation_score = (int)($_POST['evaluation_score'] ?? 3);
    $notes = trim($_POST['notes'] ?? '');
    $send_whatsapp = isset($_POST['send_whatsapp']);
    
    $report_content = "📋 *تقرير متابعة الطالب الخاص*\n━━━━━━━━━━━━━━━━━━━━━━\n📅 التاريخ: " . date('Y-m-d') . "\n👤 الطالب: {$student['student_name']}\n👨‍🏫 المعلم: {$student['teacher_name']}\n━━━━━━━━━━━━━━━━━━━━━━\n\n✅ *الحفظ الجديد:*\n" . ($new_memorization ?: "لم يسجل") . "\n\n🔄 *مراجعة قريبة:*\n" . ($recent_review ?: "لم يسجل") . "\n\n📖 *مراجعة بعيدة:*\n" . ($old_review ?: "لم يسجل") . "\n\n⭐ *التقييم:* " . ($evaluation_score == 5 ? 'ممتاز' : ($evaluation_score == 4 ? 'جيد جداً' : ($evaluation_score == 3 ? 'جيد' : ($evaluation_score == 2 ? 'مقبول' : 'يحتاج متابعة'))) . "\n\n📝 *ملاحظات:*\n" . ($notes ?: "لا توجد") . "\n\n━━━━━━━━━━━━━━━━━━━━━━\nدار التقوى لتحفيظ القرآن الكريم";
    
    $stmt = $pdo->prepare("INSERT INTO special_student_reports (student_id, teacher_id, report_date, report_content, new_memorization, recent_review, old_review, evaluation_score, notes, whatsapp_sent) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$student_id, $_SESSION['user_id'], $report_content, $new_memorization, $recent_review, $old_review, $evaluation_score, $notes, $send_whatsapp ? 0 : 1]);
    
    if ($send_whatsapp && !empty($student['parent_phone'])) {
        $phone = formatWhatsAppNumber($student['parent_phone']);
        if ($phone) {
            $_SESSION['whatsapp_url'] = "https://wa.me/{$phone}?text=" . urlencode($report_content);
            $_SESSION['success'] = "✅ تم حفظ التقرير وفتح واتساب لإرساله";
        } else {
            $_SESSION['success'] = "✅ تم حفظ التقرير (رقم الهاتف غير صالح للإرسال)";
        }
    } else {
        $_SESSION['success'] = "✅ تم حفظ التقرير بنجاح";
    }
    
    header("Location: special_send_report.php?student_id=$student_id");
    exit;
}

$success_message = $_SESSION['success'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
unset($_SESSION['success'], $_SESSION['whatsapp_url']);
?>

<style>
.report-page { max-width: 800px; margin: 0 auto; padding: 20px; }
.page-header { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 25px; border-radius: 20px; margin-bottom: 25px; text-align: center; }
.student-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
.student-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
.form-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e3c3f; }
.form-control { width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; }
.rating { display: flex; gap: 10px; margin: 10px 0; }
.rating-star { font-size: 2rem; cursor: pointer; color: #ddd; transition: 0.2s; }
.rating-star:hover, .rating-star.active { color: #ffc107; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 12px; }
.btn { width: 100%; padding: 12px; border-radius: 30px; border: none; font-weight: 600; cursor: pointer; transition: 0.3s; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.whatsapp-notice { background: #e8f5e9; border-right: 5px solid #25d366; margin-bottom: 20px; }
@media (max-width: 768px) { .student-card { flex-direction: column; text-align: center; } }
</style>

<section class="report-page">
    <div class="page-header"><h1><i class="fas fa-file-alt"></i> إرسال تقرير متابعة</h1><p>طالب خاص</p></div>
    <?php if ($whatsapp_url): ?><div class="alert alert-success whatsapp-notice"><i class="fab fa-whatsapp"></i> <a href="<?php echo $whatsapp_url; ?>" target="_blank">اضغط هنا لإرسال التقرير عبر واتساب</a></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <div class="student-card"><div class="student-avatar"><?php echo mb_substr($student['student_name'], 0, 1, 'UTF-8'); ?></div><div><h3><?php echo htmlspecialchars($student['student_name']); ?></h3><p><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student['teacher_name']); ?> | <i class="fas fa-phone"></i> <?php echo $student['parent_phone']; ?></p></div></div>
    <form method="post" class="form-card">
        <div class="form-group"><label><i class="fas fa-book-open"></i> الحفظ الجديد</label><textarea name="new_memorization" class="form-control" rows="2" placeholder="مثال: سورة الملك آية 1-10"></textarea></div>
        <div class="form-group"><label><i class="fas fa-history"></i> المراجعة القريبة</label><textarea name="recent_review" class="form-control" rows="2" placeholder="مثال: مراجعة سورة الكهف"></textarea></div>
        <div class="form-group"><label><i class="fas fa-archive"></i> المراجعة البعيدة</label><textarea name="old_review" class="form-control" rows="2" placeholder="مثال: مراجعة الأجزاء 1-5"></textarea></div>
        <div class="form-group"><label><i class="fas fa-star"></i> التقييم العام</label><div class="rating" id="ratingStars"><span class="rating-star" data-value="1">★</span><span class="rating-star" data-value="2">★</span><span class="rating-star" data-value="3">★</span><span class="rating-star" data-value="4">★</span><span class="rating-star" data-value="5">★</span></div><input type="hidden" name="evaluation_score" id="evaluationScore" value="3"></div>
        <div class="form-group"><label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label class="checkbox-label"><input type="checkbox" name="send_whatsapp" value="1" checked> <i class="fab fa-whatsapp"></i> إرسال التقرير عبر واتساب</label></div>
        <button type="submit" class="btn"><i class="fas fa-save"></i> حفظ التقرير</button>
    </form>
</section>

<script>
document.querySelectorAll('.rating-star').forEach(star => { star.addEventListener('click', function() { let value = parseInt(this.dataset.value); document.getElementById('evaluationScore').value = value; document.querySelectorAll('.rating-star').forEach(s => { if (parseInt(s.dataset.value) <= value) s.classList.add('active'); else s.classList.remove('active'); }); }); });
document.querySelector('.rating-star[data-value="3"]').classList.add('active');
</script>

<?php require_once 'includes/footer.php'; ?>