<?php
// ============================================
// ملف: check_auto_attendance_status.php
// فحص حالة نظام الغياب التلقائي
// ============================================

require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'فحص حالة الغياب التلقائي';
require_once 'includes/header.php';

// ============================================
// 1. فحص آخر مرة تم فيها تشغيل الكرون
// ============================================
$last_cron_run = 'غير معروف';
$last_cron_log = '';

if (file_exists('attendance_cron.log')) {
    $log_content = file('attendance_cron.log');
    $last_cron_log = end($log_content);
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $last_cron_log, $matches)) {
        $last_cron_run = $matches[1];
    }
}

// ============================================
// 2. آخر تاريخ تمت معالجته
// ============================================
$last_processed = file_exists('last_attendance_date.txt') ? file_get_contents('last_attendance_date.txt') : 'لا يوجد';

// ============================================
// 3. إحصائيات الغياب التلقائي
// ============================================
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_auto,
        COUNT(CASE WHEN person_type = 'teacher' THEN 1 END) as teachers_auto,
        COUNT(CASE WHEN person_type = 'student' THEN 1 END) as students_auto,
        MAX(date) as last_auto_date
    FROM attendance 
    WHERE auto_generated = 1
")->fetch();

// ============================================
// 4. آخر 10 تسجيلات غياب تلقائي
// ============================================
$recent_auto = $pdo->query("
    SELECT a.*, 
           CASE WHEN a.person_type = 'teacher' THEN (SELECT name FROM teachers WHERE id = a.person_id)
                ELSE (SELECT name FROM students WHERE id = a.person_id)
           END as person_name
    FROM attendance a
    WHERE a.auto_generated = 1
    ORDER BY a.date DESC
    LIMIT 10
")->fetchAll();

// ============================================
// 5. الغياب المتوقع لليوم (لم يتم تسجيله بعد)
// ============================================
$today = date('Y-m-d');
$today_day = date('w') + 1;

// المعلمين الذين يمكنهم الحضور اليوم ولم يسجلوا
$expected_teachers = $pdo->prepare("
    SELECT COUNT(*) 
    FROM teachers t
    LEFT JOIN attendance a ON a.person_type = 'teacher' AND a.person_id = t.id AND a.date = ?
    WHERE a.id IS NULL 
    AND t.can_login = 1
    AND t.on_leave = 0
    AND FIND_IN_SET(?, t.work_days)
");
$expected_teachers->execute([$today, $today_day]);
$expected_teachers_count = $expected_teachers->fetchColumn();

// الطلاب الذين يمكنهم الحضور اليوم ولم يسجلوا
$expected_students = $pdo->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN ring_students rs ON s.id = rs.student_id
    JOIN ring_schedules rsch ON rs.ring_id = rsch.ring_id
    LEFT JOIN attendance a ON a.person_type = 'student' AND a.person_id = s.id AND a.date = ?
    WHERE a.id IS NULL 
    AND rsch.day_of_week = ?
");
$expected_students->execute([$today, $today_day]);
$expected_students_count = $expected_students->fetchColumn();

// ============================================
// 6. اختبار تشغيل الكرون يدوياً
// ============================================
$test_result = '';
if (isset($_GET['test_cron'])) {
    $cron_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/cron_auto_attendance.php?force=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $cron_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $test_result = '<div class="alert alert-success">✅ تم تشغيل الكرون بنجاح! تم تحديث الغياب التلقائي.</div>';
        // تحديث الصفحة بعد ثانيتين
        echo '<meta http-equiv="refresh" content="2">';
    } else {
        $test_result = '<div class="alert alert-error">❌ فشل تشغيل الكرون. تأكد من وجود ملف cron_auto_attendance.php</div>';
    }
}
?>

<style>
.status-page {
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
    text-align: center;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e3c3f;
}
.stat-label {
    color: #666;
    font-size: 0.85rem;
}
.info-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.info-row:last-child {
    border-bottom: none;
}
.status-ok {
    color: #28a745;
    font-weight: bold;
}
.status-warning {
    color: #ffc107;
    font-weight: bold;
}
.status-error {
    color: #dc3545;
    font-weight: bold;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th {
    background: #1e3c3f;
    color: white;
    padding: 12px;
    text-align: center;
}
td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: center;
}
.alert {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
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
.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-right: 5px solid #ffc107;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 30px;
    text-decoration: none;
    margin: 10px;
}
.btn-primary {
    background: #1e3c3f;
    color: white;
}
.btn-warning {
    background: #ffc107;
    color: #212529;
}
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<section class="status-page">
    <div class="page-header">
        <h1><i class="fas fa-robot"></i> فحص حالة الغياب التلقائي</h1>
        <p>تأكد من أن نظام الغياب التلقائي يعمل بشكل صحيح</p>
    </div>

    <?php echo $test_result; ?>

    <!-- إحصائيات عامة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total_auto']); ?></div>
            <div class="stat-label">إجمالي الغياب التلقائي</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #dc3545;"><?php echo $stats['teachers_auto']; ?></div>
            <div class="stat-label">غياب معلمين تلقائي</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #dc3545;"><?php echo $stats['students_auto']; ?></div>
            <div class="stat-label">غياب طلاب تلقائي</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['last_auto_date'] ?? '-'; ?></div>
            <div class="stat-label">آخر تاريخ غياب تلقائي</div>
        </div>
    </div>

    <!-- معلومات التشغيل -->
    <div class="info-card">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-clock"></i> معلومات التشغيل</h3>
        <div class="info-row">
            <span>آخر تشغيل للكرون:</span>
            <span class="<?php echo $last_cron_run != 'غير معروف' ? 'status-ok' : 'status-warning'; ?>">
                <?php echo $last_cron_run; ?>
            </span>
        </div>
        <div class="info-row">
            <span>آخر تاريخ تمت معالجته:</span>
            <span><?php echo $last_processed; ?></span>
        </div>
        <div class="info-row">
            <span>المعلمين المتوقع غيابهم اليوم (لم يسجلوا):</span>
            <span class="status-warning"><?php echo $expected_teachers_count; ?> معلم</span>
        </div>
        <div class="info-row">
            <span>الطلاب المتوقع غيابهم اليوم (لم يسجلوا):</span>
            <span class="status-warning"><?php echo $expected_students_count; ?> طالب</span>
        </div>
    </div>

    <!-- آخر تسجيلات الغياب التلقائي -->
    <div class="info-card">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-history"></i> آخر 10 تسجيلات غياب تلقائي</h3>
        <?php if (empty($recent_auto)): ?>
            <div class="alert alert-warning">لا توجد تسجيلات غياب تلقائي بعد</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>التاريخ</th><th>النوع</th><th>الاسم</th> </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_auto as $row): ?>
                    <tr>
                        <td><?php echo $row['date']; ?></td>
                        <td><?php echo $row['person_type'] == 'teacher' ? 'معلم' : 'طالب'; ?></td>
                        <td><?php echo htmlspecialchars($row['person_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- سجل العمليات -->
    <div class="info-card">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-file-alt"></i> آخر سجل للعمليات</h3>
        <pre style="background: #f8f9fa; padding: 15px; border-radius: 10px; overflow-x: auto; font-size: 0.8rem; max-height: 200px;"><?php 
        if (file_exists('attendance_cron.log')) {
            echo htmlspecialchars(file_get_contents('attendance_cron.log'));
        } else {
            echo 'لا يوجد سجل للعمليات';
        }
        ?></pre>
    </div>

    <!-- أزرار الإجراءات -->
    <div style="text-align: center;">
        <a href="?test_cron=1" class="btn btn-primary" onclick="return confirm('تشغيل الكرون يدوياً الآن؟')">
            <i class="fas fa-play"></i> تشغيل الكرون يدوياً
        </a>
        <a href="cron_setup.php" class="btn btn-primary">
            <i class="fas fa-cog"></i> إعدادات الكرون
        </a>
        <a href="dashboard.php" class="btn btn-warning">
            <i class="fas fa-home"></i> العودة للرئيسية
        </a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>