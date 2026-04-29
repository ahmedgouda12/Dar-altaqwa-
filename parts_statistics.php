<?php
// ============================================
// ملف: parts_statistics.php - إحصائيات الأجزاء
// ============================================

require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إحصائيات الأجزاء';
require_once 'includes/header.php';

// تحديث إحصائيات جميع الطلاب
$all_students = $pdo->query("SELECT id FROM students")->fetchAll();
foreach ($all_students as $student) {
    updateStudentPartsStats($pdo, $student['id']);
}

// توزيع الأجزاء
$parts_distribution = getPartsDistribution($pdo);
$total_students = array_sum($parts_distribution);

// تفاصيل الطلاب لكل جزء
$students_by_part = [];
for ($i = 1; $i <= 30; $i++) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.category, t.name as teacher_name, sps.total_pages
        FROM students s
        JOIN student_parts_stats sps ON s.id = sps.student_id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE sps.total_parts = ?
        ORDER BY sps.total_pages DESC, s.name
    ");
    $stmt->execute([$i]);
    $students_by_part[$i] = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<style>
.parts-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
}
.parts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}
.part-card {
    background: white;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
}
.part-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
.part-card.active { border-color: #c9a96b; background: #fff8e7; }
.part-number { font-size: 1.5rem; font-weight: 800; color: #1e3c3f; }
.part-count { font-size: 0.75rem; color: #666; }
.students-table {
    background: white;
    border-radius: 20px;
    overflow-x: auto;
    padding: 20px;
    display: none;
}
.students-table.active { display: block; }
table { width: 100%; border-collapse: collapse; }
th { background: #1e3c3f; color: white; padding: 12px; }
td { padding: 10px; border-bottom: 1px solid #eee; }
@media (max-width: 768px) { .parts-grid { grid-template-columns: repeat(3, 1fr); } }
</style>

<section class="parts-page">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> إحصائيات الأجزاء المحفوظة</h1>
        <p>توزيع الطلاب حسب عدد الأجزاء المحفوظة (كل 20 صفحة = جزء واحد)</p>
    </div>

    <div class="parts-grid">
        <?php for ($i = 1; $i <= 30; $i++): 
            $count = $parts_distribution[$i] ?? 0;
            $percentage = $total_students > 0 ? round(($count / $total_students) * 100) : 0;
        ?>
            <div class="part-card" onclick="showPart(<?php echo $i; ?>)">
                <div class="part-number"><?php echo $i; ?></div>
                <div class="part-count"><?php echo $count; ?> طالب (<?php echo $percentage; ?>%)</div>
                <?php if ($i == 30 && $count > 0): ?>
                    <div style="font-size: 0.7rem; color: #28a745; margin-top: 5px;">🎓 ختم القرآن</div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <?php for ($i = 1; $i <= 30; $i++): ?>
        <div id="part-<?php echo $i; ?>" class="students-table">
            <h3 style="margin-bottom: 15px;">📖 الطلاب الحافظون للجزء <?php echo $i; ?></h3>
            <?php if (empty($students_by_part[$i])): ?>
                <p style="text-align: center; padding: 40px;">لا يوجد طلاب في هذا الجزء</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>الطالب</th><th>الفئة</th><th>المعلم</th><th>الصفحات</th></tr></thead>
                    <tbody>
                        <?php foreach ($students_by_part[$i] as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo $student['category'] == 'boy' ? 'أولاد' : ($student['category'] == 'girl' ? 'بنات' : ($student['category'] == 'child' ? 'أطفال' : 'نساء')); ?></td>
                                <td><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></td>
                                <td><?php echo number_format($student['total_pages']); ?> / 604</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endfor; ?>
</section>

<script>
function showPart(part) {
    document.querySelectorAll('.part-card').forEach(card => card.classList.remove('active'));
    document.querySelector(`.part-card:nth-child(${part})`).classList.add('active');
    document.querySelectorAll('.students-table').forEach(table => table.classList.remove('active'));
    document.getElementById(`part-${part}`).classList.add('active');
}
// إظهار الجزء الأول افتراضياً
showPart(1);
</script>

<?php require_once 'includes/footer.php'; ?>