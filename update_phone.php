<?php
require_once 'config.php';
require_once 'functions.php';

if (!isStudent() && !isGuardian()) {
    redirect('login.php');
}

$pageTitle = 'تحديث رقم الهاتف';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message = '';
$message_type = '';

// جلب الرقم الحالي
if ($user_type == 'student') {
    $stmt = $pdo->prepare("SELECT name, parent_phone FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $current_phone = $user['parent_phone'] ?? '';
    $user_name = $user['name'];
} else {
    $stmt = $pdo->prepare("SELECT name, phone FROM guardians WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $current_phone = $user['phone'] ?? '';
    $user_name = $user['name'];
}

// معالجة تحديث الرقم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_phone = trim($_POST['phone'] ?? '');
    
    if (empty($new_phone)) {
        $message = '❌ رقم الهاتف مطلوب';
        $message_type = 'error';
    } elseif (!validatePhone($new_phone)) {
        $message = '❌ رقم الهاتف غير صالح (يجب أن يحتوي على 10-15 رقماً)';
        $message_type = 'error';
    } else {
        try {
            if ($user_type == 'student') {
                $stmt = $pdo->prepare("UPDATE students SET parent_phone = ? WHERE id = ?");
                $stmt->execute([$new_phone, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE guardians SET phone = ? WHERE id = ?");
                $stmt->execute([$new_phone, $user_id]);
            }
            
            $message = '✅ تم تحديث رقم الهاتف بنجاح';
            $message_type = 'success';
            $current_phone = $new_phone;
            
            // إضافة إشعار للمعلم إذا كان الطالب
            if ($user_type == 'student') {
                // جلب معرف المعلم
                $teacher = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
                $teacher->execute([$user_id]);
                $teacher_id = $teacher->fetchColumn();
                
                if ($teacher_id) {
                    require_once 'notifications_functions.php';
                    addNotification($pdo, $teacher_id, 'teacher', 
                        '📱 تحديث رقم هاتف', 
                        "قام الطالب {$user_name} بتحديث رقم هاتف ولي الأمر: {$new_phone}", 
                        'info', 
                        'view_progress.php?student_id=' . $user_id
                    );
                }
            }
            
        } catch (PDOException $e) {
            $message = '❌ حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<style>
.phone-update-container {
    max-width: 500px;
    margin: 40px auto;
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.user-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.user-info h3 {
    margin: 0;
    color: #1e3c3f;
}

.user-info p {
    margin: 5px 0 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.current-phone {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-right: 4px solid #17a2b8;
}

.current-phone label {
    font-weight: bold;
    color: #1e3c3f;
    display: block;
    margin-bottom: 5px;
}

.current-phone .phone-number {
    font-size: 1.3rem;
    color: #17a2b8;
    font-family: monospace;
    direction: ltr;
    text-align: left;
}

.phone-input-group {
    margin-bottom: 20px;
}

.phone-input-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.phone-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.phone-input-wrapper .flag-icon {
    position: absolute;
    right: 15px;
    color: #1e3c3f;
    font-size: 1.2rem;
    z-index: 2;
}

.phone-input-wrapper input {
    width: 100%;
    padding: 15px 50px 15px 15px;
    border: 2px solid #dee2e6;
    border-radius: 50px;
    font-size: 1.1rem;
    font-family: monospace;
    direction: ltr;
    transition: 0.3s;
}

.phone-input-wrapper input:focus {
    border-color: #1e3c3f;
    outline: none;
    box-shadow: 0 5px 15px rgba(30,60,63,0.2);
}

.whatsapp-note {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 10px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border-right: 5px solid #28a745;
}

.whatsapp-note i {
    font-size: 1.5rem;
}

.update-btn {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    border: none;
    padding: 15px 25px;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    width: 100%;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.update-btn:hover {
    background: linear-gradient(135deg, #2a5f5a, #1e3c3f);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.update-btn:active {
    transform: translateY(0);
}

.message {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.info-box {
    background: #e7f3ff;
    color: #0c5460;
    padding: 15px;
    border-radius: 10px;
    margin-top: 20px;
    font-size: 0.9rem;
    border-right: 5px solid #17a2b8;
}

.test-btn {
    background: #25d366;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 40px;
    cursor: pointer;
    font-size: 0.9rem;
    margin-top: 10px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.3s;
}

.test-btn:hover {
    background: #128C7E;
    transform: scale(1.05);
}

.test-btn i {
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .phone-update-container {
        margin: 20px 15px;
        padding: 20px;
    }
}
</style>

<section class="phone-update-container">
    <div class="user-header">
        <div class="user-avatar">
            <i class="fas <?php echo $user_type == 'student' ? 'fa-user-graduate' : 'fa-user-tie'; ?>"></i>
        </div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($user_name); ?></h3>
            <p><?php echo $user_type == 'student' ? 'طالب' : 'ولي أمر'; ?></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- عرض الرقم الحالي -->
    <div class="current-phone">
        <label><i class="fas fa-phone"></i> الرقم الحالي</label>
        <div class="phone-number">
            <?php echo !empty($current_phone) ? $current_phone : 'غير محدد'; ?>
        </div>
    </div>

    <!-- نموذج تحديث الرقم -->
    <form method="post" onsubmit="return validatePhoneNumber()">
        <div class="phone-input-group">
            <label for="phone">رقم الهاتف الجديد</label>
            <div class="phone-input-wrapper">
                <span class="flag-icon">🇪🇬</span>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       value="<?php echo htmlspecialchars($current_phone); ?>"
                       placeholder="01012345678" 
                       required
                       pattern="[0-9]{10,15}"
                       title="أدخل رقم هاتف صحيح (10-15 رقماً)">
            </div>
            <small style="color: #6c757d; margin-top: 5px; display: block;">
                <i class="fas fa-info-circle"></i> أدخل الرقم بدون صفر بادئ أو مع الصفر (مثال: 01012345678 أو 201012345678)
            </small>
        </div>

        <!-- ملاحظة واتساب -->
        <div class="whatsapp-note">
            <i class="fab fa-whatsapp"></i>
            <div>
                <strong>ملاحظة مهمة:</strong> سيتم استخدام هذا الرقم لإرسال تنبيهات واتساب عند الغياب المتكرر أو المناسبات.
            </div>
        </div>

        <!-- زر اختبار واتساب -->
        <?php if (!empty($current_phone)): ?>
            <div style="text-align: center; margin-bottom: 15px;">
                <button type="button" class="test-btn" onclick="testWhatsApp()">
                    <i class="fab fa-whatsapp"></i> اختبار الرقم على واتساب
                </button>
            </div>
        <?php endif; ?>

        <button type="submit" class="update-btn">
            <i class="fas fa-save"></i>
            تحديث رقم الهاتف
        </button>
    </form>

    <!-- معلومات إضافية -->
    <div class="info-box">
        <i class="fas fa-shield-alt"></i>
        <strong>لماذا نطلب رقم الهاتف؟</strong>
        <ul style="margin-top: 10px; margin-right: 20px;">
            <li>إرسال تنبيهات عند غياب الطالب المتكرر.</li>
            <li>إشعارات بالمواعيد الهامة والإجازات.</li>
            <li>تحديثات حول تقدم الطالب في الحفظ.</li>
            <li>التواصل السريع في الحالات الطارئة.</li>
        </ul>
    </div>
</section>

<script>
function validatePhoneNumber() {
    const phone = document.getElementById('phone').value.replace(/\D/g, '');
    if (phone.length < 10 || phone.length > 15) {
        alert('❌ رقم الهاتف يجب أن يكون بين 10 و 15 رقماً');
        return false;
    }
    return true;
}

function testWhatsApp() {
    const phone = '<?php echo formatWhatsAppNumber($current_phone); ?>';
    if (!phone) {
        alert('لا يوجد رقم هاتف مسجل للاختبار');
        return;
    }
    
    const message = encodeURIComponent(
        'السلام عليكم 👋\nهذه رسالة تجريبية من دار التقوى.\nتم تحديث رقم الهاتف بنجاح.'
    );
    
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
}

// تنسيق الرقم أثناء الكتابة
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 0) {
        if (value.startsWith('0')) {
            // يبقى كما هو
        } else if (value.length <= 10) {
            value = '0' + value;
        }
        e.target.value = value;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>