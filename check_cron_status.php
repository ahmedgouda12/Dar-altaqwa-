<?php
// ============================================
// ملف: check_cron_status.php
// مراقبة حالة نظام الغياب التلقائي
// ============================================

require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'مراقبة الغياب التلقائي';
require_once 'includes/header.php';

// جلب آخر 10 تسجيلات غياب تلقائي
$auto_attendance = $pdo->query("
    SELECT *, DATE(date) as att_date
    FROM attendance 
    WHERE auto_generated = 1 
    ORDER BY date DESC 
    LIMIT 20
")->fetchAll();

// إحصائيات
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_auto,
        COUNT(CASE WHEN person_type = 'teacher' THEN 1 END) as teachers_auto,
        COUNT(CASE WHEN person_type = 'student' THEN 1 END) as students_auto,
        MAX(date) as last_auto_date
    FROM attendance 
    WHERE auto_generated = 1
")->fetch();

// آخر مرة تم فيها تشغيل الكرون
$last_cron_run = 'غير معروف';
if (file_exists('attendance_cron.log')) {
    $log = file('attendance_cron.log');
    $last_line = end($log);
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $last_line, $matches)) {
        $last_cron_run = $matches[1];
    }
}
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e3c3f;
}
.table-container {
    background: white;
    border-radius: 15px;
    overflow-x: auto;
    padding: 15px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #eee;
}
th {
    background: #1e3c3f;
    color: white;
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<section style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    <div class="page-header" style="background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 20px; border-radius: 20px; margin-bottom: 20px;">
        <h1><i class="fas fa-chart-line"></i> مراقبة الغياب التلقائي</h1>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo $stats['total_auto']; ?></div><div>إجمالي الغياب التلقائي</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #dc3545;"><?php echo $stats['teachers_auto']; ?></div><div>غياب معلمين تلقائي</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #dc3545;"><?php echo $stats['students_auto']; ?></div><div>غياب طلاب تلقائي</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $stats['last_auto_date'] ?? '-'; ?></div><div>آخر تاريخ غياب تلقائي</div></div>
    </div>

    <div class="info-box" style="background: #d1ecf1; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
        <i class="fas fa-info-circle"></i>
        <strong>آخر تشغيل للكرون:</strong> <?php echo $last_cron_run; ?>
    </div>

    <div class="table-container">
        <h3>📋 آخر 20 تسجيل غياب تلقائي</h3>
        <table>
            <thead>
                <tr><th>التاريخ</th><th>النوع</th><th>الاسم</th><th>ملاحظات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($auto_attendance as $a): 
                    $name = '';
                    if ($a['person_type'] == 'teacher') {
                        $stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
                        $stmt->execute([$a['person_id']]);
                        $name = $stmt->fetchColumn();
                    } else {
                        $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
                        $stmt->execute([$a['person_id']]);
                        $name = $stmt->fetchColumn();
                    }
                ?>
                <tr>
                    <td><?php echo $a['att_date']; ?></td>
                    <td><?php echo $a['person_type'] == 'teacher' ? 'معلم' : 'طالب'; ?></td>
                    <td><?php echo htmlspecialchars($name); ?></td>
                    <td><?php echo htmlspecialchars($a['notes']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($auto_attendance)): ?>
                <tr><td colspan="4" style="text-align: center;">لا توجد تسجيلات غياب تلقائي</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px; text-align: center;">
        <a href="cron_setup.php" class="btn" style="background: #1e3c3f; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: none;">
            <i class="fas fa-cog"></i> إعدادات الكرون
        </a>
        <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: none;">
            <i class="fas fa-home"></i> العودة للرئيسية
        </a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>