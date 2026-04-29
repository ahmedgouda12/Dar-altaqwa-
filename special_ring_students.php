<?php
// ============================================
// ملف: special_ring_students.php
// عرض طلاب الحلقة الخاصة وإرسال إشعارات واتساب
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'send_special_whatsapp.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'طلاب الحلقة الخاصة';
require_once 'includes/header.php';

$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;
$is_admin = isAdmin();
$teacher_id = isTeacher() ? $_SESSION['user_id'] : 0;

// جلب معلومات الحلقة
$ring = $pdo->prepare("
    SELECT r.*, t.name as teacher_name, st.name_ar as type_name
    FROM special_rings r
    JOIN teachers t ON r.teacher_id = t.id
    JOIN special_student_types st ON r.type_id = st.id
    WHERE r.id = ?
");
$ring->execute([$ring_id]);
$ring = $ring->fetch();

if (!$ring) {
    echo '<div class="alert alert-error">الحلقة غير موجودة</div>';
    require_once 'includes/footer.php';
    exit;
}

// التحقق من الصلاحية
if (isTeacher() && $ring['teacher_id'] != $teacher_id) {
    echo '<div class="alert alert-error">لا تملك صلاحية الوصول لهذه الحلقة</div>';
    require_once 'includes/footer.php';
    exit;
}

// جلب طلاب الحلقة
$students = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM special_whatsapp_logs WHERE student_id = s.id AND ring_id = ?) as whatsapp_count,
           (SELECT MAX(sent_at) FROM special_whatsapp_logs WHERE student_id = s.id AND ring_id = ?) as last_whatsapp
    FROM special_students s
    WHERE s.ring_id = ?
    ORDER BY s.student_name
");
$students->execute([$ring_id, $ring_id, $ring_id]);
$students = $students->fetchAll();

// معالجة إرسال إشعار واتساب
if (isset($_POST['send_whatsapp'])) {
    $student_id = (int)$_POST['student_id'];
    $custom_message = trim($_POST['custom_message'] ?? '');
    
    $result = sendSpecialWhatsApp($pdo, $student_id, $ring_id, $custom_message);
    
    if ($result['success']) {
        $_SESSION['whatsapp_url'] = $result['url'];
        $_SESSION['whatsapp_message'] = $result['message'];
        $_SESSION['whatsapp_success'] = "✅ تم إرسال إشعار واتساب للطالب";
    } else {
        $_SESSION['whatsapp_error'] = "❌ " . $result['message'];
    }
    
    header("Location: special_ring_students.php?ring_id=$ring_id");
    exit;
}

// معالجة إرسال إشعار جماعي
if (isset($_POST['send_bulk_whatsapp'])) {
    $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];
    $custom_message = trim($_POST['custom_message'] ?? '');
    $sent_count = 0;
    $failed_count = 0;
    $urls = [];
    
    foreach ($selected_students as $student_id) {
        $result = sendSpecialWhatsApp($pdo, $student_id, $ring_id, $custom_message);
        if ($result['success']) {
            $sent_count++;
            $urls[] = $result['url'];
        } else {
            $failed_count++;
        }
    }
    
    if ($sent_count > 0) {
        $_SESSION['whatsapp_success'] = "✅ تم إرسال إشعارات واتساب لـ $sent_count طالب" . ($failed_count > 0 ? " (فشل $failed_count)" : "");
        if (count($urls) == 1) {
            $_SESSION['whatsapp_url'] = $urls[0];
        }
    } else {
        $_SESSION['whatsapp_error'] = "❌ لم يتم إرسال أي إشعار";
    }
    
    header("Location: special_ring_students.php?ring_id=$ring_id");
    exit;
}

$success_message = $_SESSION['whatsapp_success'] ?? '';
$error_message = $_SESSION['whatsapp_error'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
unset($_SESSION['whatsapp_success'], $_SESSION['whatsapp_error'], $_SESSION['whatsapp_url']);
?>

<style>
.students-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
}

.ring-info {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.ring-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #1e3c3f;
}

.ring-time {
    color: #666;
    font-size: 0.9rem;
}

.student-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.student-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.student-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.student-name {
    font-size: 1.2rem;
    font-weight: bold;
    color: #1e3c3f;
}

.student-phone {
    direction: ltr;
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

.student-details {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin: 15px 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.detail-item i {
    width: 25px;
    color: #c9a96b;
}

.whatsapp-info {
    background: #e8f5e9;
    border-radius: 10px;
    padding: 10px;
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.85rem;
}

.whatsapp-btn {
    background: #25d366;
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: 0.3s;
    border: none;
    cursor: pointer;
}

.whatsapp-btn:hover {
    background: #128C7E;
    transform: translateY(-2px);
}

.bulk-actions {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.message-template {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-top: 15px;
    font-size: 0.85rem;
    white-space: pre-wrap;
    font-family: monospace;
    direction: ltr;
    text-align: left;
    max-height: 200px;
    overflow-y: auto;
}

.btn {
    padding: 10px 20px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.btn-primary {
    background: #1e3c3f;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

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
}

.modal.show { display: flex; }

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 25px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
}

textarea.form-control {
    min-height: 150px;
    font-family: monospace;
    direction: ltr;
    text-align: left;
}

.student-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    margin-left: 10px;
}

@media (max-width: 768px) {
    .student-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .student-details {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="students-page">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> طلاب الحلقة الخاصة</h1>
        <p><?php echo htmlspecialchars($ring['name']); ?></p>
    </div>

    <?php if ($whatsapp_url): ?>
        <div class="alert alert-success" style="background: #e8f5e9; border-right-color: #25d366; margin-bottom: 20px;">
            <i class="fab fa-whatsapp" style="color: #25d366;"></i>
            <div style="flex: 1;">
                <?php echo $success_message; ?>
            </div>
            <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn" style="background: #25d366; color: white;">
                <i class="fab fa-whatsapp"></i> فتح واتساب
            </a>
        </div>
    <?php elseif ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="ring-info">
        <div>
            <div class="ring-name">
                <i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?>
            </div>
            <div class="ring-time">
                <i class="fas fa-clock"></i>
                <?php if ($ring['start_time']): ?>
                    <?php echo date('h:i A', strtotime($ring['start_time'])); ?> - 
                    <?php echo date('h:i A', strtotime($ring['end_time'])); ?>
                <?php else: ?>
                    لم يتم تحديد موعد
                <?php endif; ?>
            </div>
        </div>
        <div>
            <span class="badge" style="background: #c9a96b; color: #1e3c3f;">
                <i class="fas fa-users"></i> <?php echo count($students); ?> طالب
            </span>
        </div>
    </div>

    <!-- الإجراءات الجماعية -->
    <div class="bulk-actions">
        <h3><i class="fas fa-envelope"></i> إشعارات جماعية</h3>
        <form method="post" onsubmit="return confirm('هل أنت متأكد من إرسال إشعارات واتساب لجميع الطلاب المحددين؟')">
            <div style="margin-bottom: 15px;">
                <label>
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    تحديد الكل
                </label>
            </div>
            
            <div id="studentsList">
                <?php foreach ($students as $student): ?>
                    <div style="margin-bottom: 10px;">
                        <label>
                            <input type="checkbox" name="selected_students[]" value="<?php echo $student['id']; ?>" class="student-checkbox">
                            <strong><?php echo htmlspecialchars($student['student_name']); ?></strong>
                            (<?php echo $student['parent_phone']; ?>)
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-edit"></i> نص الرسالة (اختياري - اتركه فارغاً لاستخدام الرسالة الافتراضية)</label>
                <textarea name="custom_message" class="form-control" rows="3" placeholder="اكتب رسالة مخصصة أو اتركها فارغة لاستخدام الرسالة الافتراضية"></textarea>
            </div>
            
            <button type="submit" name="send_bulk_whatsapp" class="btn btn-success">
                <i class="fab fa-whatsapp"></i> إرسال للطلاب المحددين
            </button>
        </form>
    </div>

    <!-- قائمة الطلاب -->
    <?php foreach ($students as $student): ?>
        <div class="student-card">
            <div class="student-header">
                <div class="student-name">
                    <i class="fas fa-user-graduate"></i>
                    <?php echo htmlspecialchars($student['student_name']); ?>
                </div>
                <div class="student-phone" dir="ltr">
                    <i class="fas fa-phone"></i> <?php echo $student['parent_phone'] ?? 'لا يوجد'; ?>
                </div>
            </div>
            
            <div class="student-details">
                <div class="detail-item">
                    <i class="fas fa-venus-mars"></i>
                    <span><?php echo $student['student_gender'] == 'male' ? 'ذكر' : 'أنثى'; ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>العمر: <?php echo $student['student_age']; ?> سنة</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-level-up-alt"></i>
                    <span>المستوى: <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>تاريخ التسجيل: <?php echo $student['enrollment_date']; ?></span>
                </div>
            </div>
            
            <div class="whatsapp-info">
                <div>
                    <i class="fab fa-whatsapp" style="color: #25d366;"></i>
                    <?php if ($student['whatsapp_count'] > 0): ?>
                        <span>تم إرسال <?php echo $student['whatsapp_count']; ?> إشعار</span>
                        <?php if ($student['last_whatsapp']): ?>
                            <br><small>آخر إرسال: <?php echo date('Y-m-d H:i', strtotime($student['last_whatsapp'])); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span>لم يتم إرسال أي إشعار بعد</span>
                    <?php endif; ?>
                </div>
                <div>
                    <button class="whatsapp-btn" onclick="openWhatsappModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['student_name']); ?>')">
                        <i class="fab fa-whatsapp"></i> إرسال إشعار
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>لا يوجد طلاب في هذه الحلقة</h3>
            <p>يمكنك إضافة طلاب من خلال طلبات الالتحاق</p>
        </div>
    <?php endif; ?>
</section>

<!-- نافذة إرسال إشعار فردي -->
<div class="modal" id="whatsappModal">
    <div class="modal-content">
        <h3><i class="fab fa-whatsapp"></i> إرسال إشعار واتساب</h3>
        <div id="modalStudentName" style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 10px;"></div>
        
        <form method="post">
            <input type="hidden" name="student_id" id="modalStudentId">
            <div class="form-group">
                <label>نص الرسالة (اختياري)</label>
                <textarea name="custom_message" class="form-control" rows="5" id="customMessage"
                    placeholder="اكتب رسالة مخصصة أو اتركها فارغة لاستخدام الرسالة الافتراضية"></textarea>
            </div>
            
            <div class="message-template" id="messagePreview">
                <strong>معاينة الرسالة الافتراضية:</strong>
                <div id="previewContent" style="margin-top: 10px; white-space: pre-wrap;"></div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-warning" onclick="closeWhatsappModal()">إلغاء</button>
                <button type="submit" name="send_whatsapp" class="btn btn-success">إرسال</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
}

function openWhatsappModal(studentId, studentName) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('whatsappModal').classList.add('show');
    
    // عرض معاينة الرسالة الافتراضية
    const previewContent = document.getElementById('previewContent');
    const ringName = "<?php echo addslashes($ring['name']); ?>";
    const teacherName = "<?php echo addslashes($ring['teacher_name']); ?>";
    const startTime = "<?php echo $ring['start_time'] ? date('h:i A', strtotime($ring['start_time'])) : ''; ?>";
    const endTime = "<?php echo $ring['end_time'] ? date('h:i A', strtotime($ring['end_time'])) : ''; ?>";
    const location = "<?php echo addslashes($ring['location']); ?>";
    const meetingLink = "<?php echo addslashes($ring['meeting_link']); ?>";
    
    let daysText = "";
    <?php if ($ring['days_of_week']): 
        $days_map = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        $days_array = explode(',', $ring['days_of_week']);
        $days_list = [];
        foreach ($days_array as $d) {
            if (isset($days_map[$d-1])) $days_list[] = $days_map[$d-1];
        }
        $days_text = implode(' - ', $days_list);
    ?>
        daysText = "📅 أيام الانعقاد: <?php echo $days_text; ?>\n";
    <?php endif; ?>
    
    let message = `السلام عليكم ورحمة الله وبركاته\n`;
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    message += `📋 *تأكيد موعد الحلقة الخاصة*\n`;
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    message += `👤 *الطالب/ة:* ${studentName}\n`;
    message += `👨‍🏫 *المعلم:* ${teacherName}\n\n`;
    message += `📚 *معلومات الحلقة:*\n`;
    message += `┌─────────────────────────────────\n`;
    message += `│ 🏷️ *اسم الحلقة:* ${ringName}\n`;
    
    if (daysText) {
        message += `│ \n`;
        message += `│ ${daysText}`;
    }
    
    if (startTime && endTime) {
        message += `│ \n`;
        message += `│ 🕐 *الموعد:* ${startTime} - ${endTime}\n`;
    }
    
    if (location) {
        message += `│ \n`;
        message += `│ 📍 *المكان:* ${location}\n`;
    }
    
    if (meetingLink) {
        message += `│ \n`;
        message += `│ 💻 *رابط الاجتماع:*\n`;
        message += `│    ${meetingLink}\n`;
    }
    
    message += `└─────────────────────────────────\n\n`;
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    message += `دار التقوى لتحفيظ القرآن الكريم`;
    
    previewContent.innerHTML = message.replace(/\n/g, '<br>');
}

function closeWhatsappModal() {
    document.getElementById('whatsappModal').classList.remove('show');
    document.getElementById('customMessage').value = '';
}

// إغلاق النافذة بالنقر خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeWhatsappModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>