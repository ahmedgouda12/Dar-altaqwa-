<?php
// ============================================
// ملف: admin_memorization_report.php - تقرير تقدم الحفظ الشامل
// ============================================

require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تقرير تقدم الحفظ الشامل';

// تحديث إحصائيات جميع الطلاب
$all_students = $pdo->query("SELECT id FROM students")->fetchAll();
foreach ($all_students as $student) {
    updateStudentPartsStats($pdo, $student['id']);
}

// إحصائيات شاملة
$total_memorized = $pdo->query("SELECT COUNT(*) FROM student_surah_progress WHERE completed = 1")->fetchColumn();
$total_pages = $pdo->query("SELECT COALESCE(SUM(total_pages), 0) FROM student_parts_stats")->fetchColumn();
$completed_quran = $pdo->query("SELECT COUNT(*) FROM student_parts_stats WHERE total_parts >= 30")->fetchColumn();

// أفضل 10 طلاب
$top_students = $pdo->query("
    SELECT s.id, s.name, s.category, t.name as teacher_name, sps.total_parts, sps.total_pages, COUNT(sp.id) as surahs_count
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    LEFT JOIN student_parts_stats sps ON s.id = sps.student_id
    GROUP BY s.id
    ORDER BY sps.total_parts DESC, sps.total_pages DESC
    LIMIT 10
")->fetchAll();

// توزيع الأجزاء
$parts_distribution = getPartsDistribution($pdo);
$total_students = array_sum($parts_distribution);
$students_with_memorization = $total_students - $parts_distribution[0];

require_once 'includes/header.php';
?>

<style>
.report-page { max-width: 1400px; margin: 0 auto; padding: 20px; }
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 25px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.stat-number { font-size: 2rem; font-weight: 700; color: #1e3c3f; }
table {
    width: 100%;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 30px;
}
th { background: #1e3c3f; color: white; padding: 12px; }
td { padding: 12px; border-bottom: 1px solid #eee; }
.badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
}
.badge-gold { background: gold; color: #1e3c3f; }
.badge-success { background: #d4edda; color: #155724; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-warning { background: #fff3cd; color: #856404; }
.parts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
    margin: 20px 0;
    background: white;
    padding: 20px;
    border-radius: 20px;
}
.part-item {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 12px;
}
.part-number { font-size: 1.2rem; font-weight: 800; }
.part-count { font-size: 0.7rem; color: #666; }
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .parts-grid { grid-template-columns: repeat(5, 1fr); }
}
</style>

<section class="report-page">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> تقرير تقدم الحفظ الشامل</h1>
        <div>آخر تحديث: <?php echo date('Y-m-d H:i'); ?></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo number_format($total_memorized); ?></div><div>إجمالي السور</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo number_format($total_pages); ?></div><div>إجمالي الصفحات</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo number_format($completed_quran); ?></div><div>خاتمين للقرآن</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $students_with_memorization; ?></div><div>طلاب لديهم حفظ</div></div>
    </div>

    <div class="parts-grid">
        <?php for ($i = 1; $i <= 30; $i++): 
            $count = $parts_distribution[$i] ?? 0;
            $percentage = $total_students > 0 ? round(($count / $total_students) * 100) : 0;
        ?>
            <div class="part-item">
                <div class="part-number"><?php echo $i; ?></div>
                <div class="part-count"><?php echo $count; ?> طالب</div>
                <div class="part-bar"><div class="part-bar-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
            </div>
        <?php endfor; ?>
    </div>

    <h3><i class="fas fa-crown"></i> أفضل 10 طلاب</h3>
    <table>
        <thead><tr><th>#</th><th>الطالب</th><th>الفئة</th><th>المعلم</th><th>السور</th><th>الصفحات</th><th>الأجزاء</th></tr></thead>
        <tbody>
            <?php foreach ($top_students as $index => $s): 
                $parts = $s['total_parts'] ?? 0;
                $badge_class = $parts >= 30 ? 'badge-gold' : ($parts >= 20 ? 'badge-success' : ($parts >= 10 ? 'badge-info' : ($parts >= 1 ? 'badge-warning' : 'badge-danger')));
                $parts_text = $parts >= 30 ? '🎓 ختم القرآن' : $parts . ' جزء';
            ?>
                <tr>
                    <td><strong>#<?php echo $index + 1; ?></strong></td>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo $s['category'] == 'boy' ? 'أولاد' : ($s['category'] == 'girl' ? 'بنات' : ($s['category'] == 'child' ? 'أطفال' : 'نساء')); ?></td>
                    <td><?php echo htmlspecialchars($s['teacher_name'] ?? '-'); ?></td>
                    <td><?php echo $s['surahs_count']; ?> / 114</td>
                    <td><?php echo number_format($s['total_pages']); ?> / 604</td>
                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $parts_text; ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once 'includes/footer.php'; ?>