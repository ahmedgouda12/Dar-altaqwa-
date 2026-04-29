<?php
// ============================================
// ملف: cron_setup.php
// إعداد نظام الكرون وعرض التعليمات
// آخر تحديث: 2026-04-06
// ============================================

require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إعداد نظام الغياب التلقائي';
require_once 'includes/header.php';

// إنشاء مفتاح سري عشوائي إذا لم يكن موجوداً
$key_file = 'cron_key.txt';
if (!file_exists($key_file)) {
    $secret_key = bin2hex(random_bytes(16));
    file_put_contents($key_file, $secret_key);
} else {
    $secret_key = file_get_contents($key_file);
}

$site_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$cron_url = $site_url . '/cron_auto_attendance.php?cron_key=' . $secret_key;

// اختبار تشغيل الكرون
$test_result = null;
if (isset($_GET['test_cron'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $cron_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $test_result = '<div class="success">✅ تم اختبار الكرون بنجاح!</div>';
    } else {
        $test_result = '<div class="error">❌ فشل اختبار الكرون. تأكد من أن الملف موجود.</div>';
    }
}

// عرض آخر سجل
$log_content = '';
if (file_exists('attendance_cron.log')) {
    $log_lines = file('attendance_cron.log');
    $log_content = implode('', array_slice($log_lines, -30));
}

// عرض آخر تاريخ تمت معالجته
$last_processed = file_exists('last_attendance_date.txt') ? file_get_contents('last_attendance_date.txt') : 'لا يوجد';
?>

<style>
.cron-container {
    max-width: 900px;
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

.success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    border-right: 4px solid #28a745;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    border-right: 4px solid #dc3545;
}

.info {
    background: #d1ecf1;
    color: #0c5460;
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    border-right: 4px solid #17a2b8;
}

.cron-box {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
    border: 2px solid #e9ecef;
}

.cron-command {
    background: #2c3e50;
    color: #ffd700;
    padding: 15px;
    border-radius: 10px;
    font-family: monospace;
    font-size: 0.9rem;
    overflow-x: auto;
    direction: ltr;
    text-align: left;
}

.copy-btn {
    background: #c9a96b;
    color: #1e3c3f;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    cursor: pointer;
    margin-top: 10px;
    font-weight: bold;
}

.log-box {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 0.8rem;
    direction: ltr;
    text-align: left;
}

.btn {
    display: inline-block;
    background: #1e3c3f;
    color: white;
    padding: 10px 20px;
    border-radius: 30px;
    text-decoration: none;
    margin-top: 10px;
}

@media (max-width: 768px) {
    .cron-container { padding: 15px; }
    .cron-command { font-size: 0.7rem; }
}
</style>

<section class="cron-container">
    <div class="page-header">
        <h1><i class="fas fa-clock"></i> إعداد نظام الغياب التلقائي</h1>
        <p>قم بإعداد Cron Job لتسجيل الغياب التلقائي للمعلمين والطلاب</p>
    </div>

    <?php if ($test_result): echo $test_result; endif; ?>

    <div class="info">
        <i class="fas fa-info-circle"></i>
        <strong>طريقة العمل:</strong> يقوم هذا النظام تلقائياً بتسجيل غياب للمعلمين والطلاب الذين لم يسجلوا حضورهم في نهاية كل يوم.
    </div>

    <!-- رابط الكرون -->
    <div class="cron-box">
        <h3><i class="fas fa-link"></i> رابط Cron Job</h3>
        <p>استخدم هذا الرابط لإعداد الكرون:</p>
        <div class="cron-command" id="cronUrl"><?php echo $cron_url; ?></div>
        <button class="copy-btn" onclick="copyToClipboard()"><i class="fas fa-copy"></i> نسخ الرابط</button>
    </div>

    <!-- أوامر الكرون حسب نوع الاستضافة -->
    <div class="cron-box">
        <h3><i class="fas fa-terminal"></i> أوامر Cron Job</h3>
        
        <h4>للاستضافات التي تدعم PHP CLI:</h4>
        <div class="cron-command">
            <?php echo "0 22 * * * php " . __DIR__ . "/cron_auto_attendance.php > /dev/null 2>&1"; ?>
        </div>
        
        <h4 style="margin-top: 15px;">للاستضافات التي تدعم wget:</h4>
        <div class="cron-command">
            <?php echo "0 22 * * * wget -q -O /dev/null '$cron_url'"; ?>
        </div>
        
        <h4 style="margin-top: 15px;">للاستضافات التي تدعم curl:</h4>
        <div class="cron-command">
            <?php echo "0 22 * * * curl -s -o /dev/null '$cron_url'"; ?>
        </div>
        
        <p class="info" style="margin-top: 15px;">
            <i class="fas fa-clock"></i> 
            <strong>ملاحظة:</strong> الأمر أعلاه يشغل الكرون كل يوم في الساعة 10 مساءً (22:00). يمكنك تغيير الوقت حسب رغبتك.
        </p>
    </div>

    <!-- خدمات كرون مجانية -->
    <div class="cron-box">
        <h3><i class="fas fa-cloud"></i> خدمات كرون مجانية (للاستضافات التي لا تدعم الكرون)</h3>
        <p>إذا كانت استضافتك لا تدعم Cron Job، يمكنك استخدام هذه الخدمات المجانية:</p>
        <ul style="margin-top: 10px; margin-right: 20px;">
            <li><a href="https://cron-job.org" target="_blank">cron-job.org</a> - مجاني، يسمح بـ 5 مهام كرون</li>
            <li><a href="https://www.easycron.com" target="_blank">easycron.com</a> - مجاني، يسمح بمهمة واحدة</li>
            <li><a href="https://cronless.com" target="_blank">cronless.com</a> - مجاني</li>
        </ul>
        
        <h4 style="margin-top: 15px;">طريقة الإعداد في cron-job.org:</h4>
        <ol style="margin-top: 10px; margin-right: 20px;">
            <li>سجل حساب في <a href="https://cron-job.org" target="_blank">cron-job.org</a></li>
            <li>اضغط على "Create Cron Job"</li>
            <li>أدخل عنوان URL: <strong><?php echo $cron_url; ?></strong></li>
            <li>اختر التوقيت: كل يوم في الساعة 22:00</li>
            <li>احفظ الإعدادات</li>
        </ol>
    </div>

    <!-- اختبار الكرون -->
    <div class="cron-box">
        <h3><i class="fas fa-vial"></i> اختبار الكرون</h3>
        <p>يمكنك اختبار الكرون يدوياً بالضغط على الزر أدناه:</p>
        <a href="?test_cron=1" class="btn"><i class="fas fa-play"></i> اختبار الآن</a>
    </div>

    <!-- آخر سجل -->
    <div class="cron-box">
        <h3><i class="fas fa-history"></i> آخر سجل للعمليات</h3>
        <p>آخر تاريخ تمت معالجته: <strong><?php echo $last_processed; ?></strong></p>
        <div class="log-box">
            <?php echo nl2br(htmlspecialchars($log_content)); ?>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="cron-box">
        <h3><i class="fas fa-chart-bar"></i> إحصائيات الغياب التلقائي</h3>
        <?php
        $auto_absent_teachers = $pdo->query("
            SELECT COUNT(*) FROM attendance 
            WHERE person_type = 'teacher' AND auto_generated = 1 
            AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
        
        $auto_absent_students = $pdo->query("
            SELECT COUNT(*) FROM attendance 
            WHERE person_type = 'student' AND auto_generated = 1 
            AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
        ?>
        <p>📊 آخر 30 يوم:</p>
        <ul style="margin-top: 10px; margin-right: 20px;">
            <li>غياب تلقائي للمعلمين: <strong><?php echo $auto_absent_teachers; ?></strong></li>
            <li>غياب تلقائي للطلاب: <strong><?php echo $auto_absent_students; ?></strong></li>
        </ul>
    </div>

    <!-- تنبيهات مهمة -->
    <div class="info">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>تنبيهات مهمة:</strong>
        <ul style="margin-top: 10px; margin-right: 20px;">
            <li>تأكد من أن ملف <strong>cron_auto_attendance.php</strong> موجود في مجلد المشروع</li>
            <li>الملف يستخدم مفتاح سري لمنع الوصول المباشر. المفتاح الحالي: <code><?php echo $secret_key; ?></code></li>
            <li>يمكنك تغيير وقت الإغلاق التلقائي من داخل الملف (المتغير $auto_close_time)</li>
            <li>الغياب التلقائي لا يسجل في أيام الجمعة والعطل الرسمية</li>
        </ul>
    </div>
</section>

<script>
function copyToClipboard() {
    const text = document.getElementById('cronUrl').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('✅ تم نسخ الرابط بنجاح');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>