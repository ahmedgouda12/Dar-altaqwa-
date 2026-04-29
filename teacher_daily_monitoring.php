<?php
// ============================================
// ملف: teacher_daily_monitoring.php
// متابعة المعلم للحفظ اليومي للطلاب
// آخر تحديث: 2026-04-03
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'متابعة الحفظ اليومي';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('last saturday'));

// جلب جميع طلاب المعلم
$students = $pdo->prepare("
    SELECT s.id, s.name, s.level,
           dms.weekly_target_ayahs, dms.weekly_target_pages
    FROM students s
    LEFT JOIN student_daily_memorization_settings dms ON s.id = dms.student_id
    WHERE s.teacher_id = ?
    ORDER BY s.name
");
$students->execute([$teacher_id]);
$students_list = $students->fetchAll();

// جلب إحصائيات الحفظ للطلاب
function getStudentWeeklyStats($pdo, $student_id, $week_start) {
    $week_end = date('Y-m-d', strtotime('next friday', strtotime($week_start)));
    
    $stats = $pdo->prepare("
        SELECT 
            record_date,
            memorized_ayahs,
            memorized_pages,
            memorized_surahs,
            review_ayahs,
            quality_rating,
            notes,
            recorded_by_type
        FROM daily_memorization_records
        WHERE student_id = ? AND record_date BETWEEN ? AND ?
        ORDER BY record_date ASC
    ");
    $stats->execute([$student_id, $week_start, $week_end]);
    return $stats->fetchAll();
}

// جلب إحصائيات لجميع الطلاب
$all_stats = [];
foreach ($students_list as $student) {
    $all_stats[$student['id']] = getStudentWeeklyStats($pdo, $student['id'], $week_start);
}

// أيام الأسبوع للتقرير
$week_days = [];
$start = new DateTime($week_start);
for ($i = 0; $i < 7; $i++) {
    $date = clone $start;
    $date->modify("+$i days");
    $day_name = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'][$date->format('w')];
    $week_days[] = [
        'date' => $date->format('Y-m-d'),
        'name' => $day_name,
        'is_friday' => ($date->format('w') == 5)
    ];
}
?>

<style>
.monitoring-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.week-selector {
    display: flex;
    gap: 10px;
    align-items: center;
}

.week-selector input {
    padding: 8px 15px;
    border-radius: 30px;
    border: none;
}

.week-selector button {
    padding: 8px 20px;
    border-radius: 30px;
    border: none;
    background: #c9a96b;
    color: #1e3c3f;
    font-weight: bold;
    cursor: pointer;
}

.summary-stats {
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
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #1e3c3f;
}

.students-table {
    background: white;
    border-radius: 20px;
    overflow-x: auto;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    background: #1e3c3f;
    color: white;
    padding: 15px;
    text-align: center;
}

td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

.student-cell {
    font-weight: bold;
    color: #1e3c3f;
    text-align: right;
}

.record-cell {
    text-align: center;
}

.record-present {
    background: #d4edda;
    color: #155724;
    padding: 5px 10px;
    border-radius: 20px;
    display: inline-block;
    font-size: 0.8rem;
}

.record-absent {
    background: #f8d7da;
    color: #721c24;
    padding: 5px 10px;
    border-radius: 20px;
    display: inline-block;
    font-size: 0.8rem;
}

.record-excellent { color: #ffd700; }
.record-good { color: #28a745; }
.record-average { color: #ffc107; }
.record-poor { color: #dc3545; }

.friday-cell {
    background: #f0f0f0;
    color: #999;
}

@media (max-width: 768px) {
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php
// حساب الإحصائيات الإجمالية
$total_students = count($students_list);
$total_records = 0;
$total_ayahs = 0;
$total_committed = 0;

foreach ($students_list as $student) {
    $stats = $all_stats[$student['id']];
    $total_records += count($stats);
    $total_ayahs += array_sum(array_column($stats, 'memorized_ayahs'));
    if (count($stats) >= 4) $total_committed++;
}
?>

<section class="monitoring-page">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> متابعة الحفظ اليومي</h1>
        <div class="week-selector">
            <form method="get">
                <input type="week" name="week" value="<?php echo date('Y-\WW', strtotime($week_start)); ?>">
                <button type="submit"><i class="fas fa-calendar-alt"></i> عرض</button>
            </form>
        </div>
    </div>

    <div class="summary-stats">
        <div class="stat-card"><div class="stat-number"><?php echo $total_students; ?></div><div>إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $total_records; ?></div><div>تسجيلات هذا الأسبوع</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $total_ayahs; ?></div><div>آيات محفوظة</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $total_committed; ?></div><div>طلاب ملتزمون (4+ أيام)</div></div>
    </div>

    <div class="students-table">
        <table>
            <thead>
                <tr>
                    <th>الطالب</th>
                    <?php foreach ($week_days as $day): ?>
                        <th><?php echo $day['name']; ?><br><small><?php echo $day['date']; ?></small></th>
                    <?php endforeach; ?>
                    <th>الإجمالي</th>
                    <th>النسبة</th>
                </thead>
            <tbody>
                <?php foreach ($students_list as $student): 
                    $stats = $all_stats[$student['id']];
                    $stats_by_date = [];
                    foreach ($stats as $stat) {
                        $stats_by_date[$stat['record_date']] = $stat;
                    }
                    $recorded_days = count($stats);
                    $percentage = round(($recorded_days / 6) * 100);
                ?>
                    <tr>
                        <td class="student-cell">
                            <?php echo htmlspecialchars($student['name']); ?>
                            <br><small>الهدف: <?php echo $student['weekly_target_ayahs'] ?? 30; ?> آية/أسبوع</small>
                        </td>
                        <?php foreach ($week_days as $day): ?>
                            <td class="record-cell <?php echo $day['is_friday'] ? 'friday-cell' : ''; ?>">
                                <?php if ($day['is_friday']): ?>
                                    <i class="fas fa-mosque"></i> إجازة
                                <?php elseif (isset($stats_by_date[$day['date']])): 
                                    $record = $stats_by_date[$day['date']];
                                    $quality_class = '';
                                    if ($record['quality_rating'] == 'excellent') $quality_class = 'record-excellent';
                                    elseif ($record['quality_rating'] == 'good') $quality_class = 'record-good';
                                    elseif ($record['quality_rating'] == 'average') $quality_class = 'record-average';
                                    else $quality_class = 'record-poor';
                                ?>
                                    <div class="record-present">
                                        <i class="fas fa-check-circle"></i> تم
                                    </div>
                                    <div class="<?php echo $quality_class; ?>">
                                        <i class="fas <?php echo $record['quality_rating'] == 'excellent' ? 'fa-crown' : ($record['quality_rating'] == 'good' ? 'fa-smile' : ($record['quality_rating'] == 'average' ? 'fa-meh' : 'fa-frown')); ?>"></i>
                                        <?php echo $record['memorized_ayahs']; ?> آية
                                    </div>
                                <?php else: ?>
                                    <div class="record-absent">
                                        <i class="fas fa-times-circle"></i> لم يسجل
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td><strong><?php echo $recorded_days; ?>/6</strong></td>
                        <td>
                            <div style="background: #e9ecef; border-radius: 30px; height: 8px; width: 100px; margin: 0 auto;">
                                <div style="background: <?php echo $percentage >= 80 ? '#28a745' : ($percentage >= 50 ? '#ffc107' : '#dc3545'); ?>; width: <?php echo $percentage; ?>%; height: 100%; border-radius: 30px;"></div>
                            </div>
                            <small><?php echo $percentage; ?>%</small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px; background: #e7f3ff; padding: 15px; border-radius: 15px;">
        <i class="fas fa-info-circle"></i>
        <strong>ملاحظة:</strong> يوم الجمعة إجازة رسمية. الطالب الملتزم هو من يسجل حفظه 4 أيام على الأقل في الأسبوع.
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>