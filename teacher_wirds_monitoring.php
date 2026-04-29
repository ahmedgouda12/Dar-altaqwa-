<?php
// ============================================
// ملف: teacher_wirds_monitoring.php
// متابعة المعلم لأوراد و صلوات طلابه
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'includes/wirds_functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'متابعة أوراد و صلوات طلابي';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'wirds';

// جلب طلاب المعلم
$students = $pdo->prepare("
    SELECT s.id, s.name, s.category, s.level,
           (SELECT COUNT(*) FROM student_wird_records WHERE student_id = s.id) as wird_count,
           (SELECT COUNT(*) FROM student_prayer_records WHERE student_id = s.id) as prayer_count,
           (SELECT COALESCE(SUM(points_earned), 0) FROM student_wird_records WHERE student_id = s.id) as wird_points,
           (SELECT COALESCE(SUM(points_earned), 0) FROM student_prayer_records WHERE student_id = s.id) as prayer_points
    FROM students s
    WHERE s.teacher_id = ?
    ORDER BY wird_points DESC, prayer_points DESC
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// جلب الأوراد للعرض
$wirds = getActiveWirds($pdo);

// أفضل الطلاب في الأوراد والصلوات (بين طلاب هذا المعلم)
$top_wirds = getTopStudentsByWirds($pdo, null, 5, $teacher_id);
$top_prayers = getTopStudentsByPrayers($pdo, 5, $teacher_id);

$stats = [
    'total_students' => count($students),
    'total_wird_points' => array_sum(array_column($students, 'wird_points')),
    'total_prayer_points' => array_sum(array_column($students, 'prayer_points')),
    'students_with_wirds' => count(array_filter($students, fn($s) => $s['wird_count'] > 0)),
    'students_with_prayers' => count(array_filter($students, fn($s) => $s['prayer_count'] > 0))
];
?>

<style>
.monitoring-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e3c3f;
}

.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 30px;
    border-radius: 50px;
    background: white;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.tab-btn.active {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
}

.students-table {
    background: white;
    border-radius: 25px;
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
    border-bottom: 1px solid #e9ecef;
}

.student-link {
    color: #1e3c3f;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}

.top-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.top-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 15px;
}

.top-rank {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.rank-1 { background: gold; color: #212529; }
.rank-2 { background: silver; color: #212529; }
.rank-3 { background: #cd7f32; }
.rank-other { background: #6c757d; }

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<section class="monitoring-page">
    <div class="page-header">
        <h1><i class="fas fa-praying-hands"></i> متابعة أوراد و صلوات طلابي</h1>
        <p>تابع تقدم طلابك في الأذكار والصلوات اليومية</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo $stats['total_students']; ?></div><div>إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo number_format($stats['total_wird_points']); ?></div><div>نقاط الأوراد</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo number_format($stats['total_prayer_points']); ?></div><div>نقاط الصلوات</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $stats['students_with_wirds']; ?></div><div>طلاب سجلوا أوراداً</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $stats['students_with_prayers']; ?></div><div>طلاب سجلوا صلوات</div></div>
    </div>

    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab == 'wirds' ? 'active' : ''; ?>" onclick="window.location.href='?tab=wirds'">
            <i class="fas fa-star-and-crescent"></i> الأوراد
        </button>
        <button class="tab-btn <?php echo $active_tab == 'prayers' ? 'active' : ''; ?>" onclick="window.location.href='?tab=prayers'">
            <i class="fas fa-mosque"></i> الصلوات
        </button>
        <button class="tab-btn <?php echo $active_tab == 'ranking' ? 'active' : ''; ?>" onclick="window.location.href='?tab=ranking'">
            <i class="fas fa-crown"></i> الترتيب العام
        </button>
    </div>

    <?php if ($active_tab == 'wirds'): ?>
    <div class="students-table">
        <h3 style="padding: 20px 20px 0;"><i class="fas fa-star-and-crescent"></i> ترتيب الطلاب في الأذكار</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>نقاط الأوراد</th>
                    <th>عدد الأيام</th>
                    <th>أفضل سلسلة</th>
                </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($top_wirds as $student): 
                ?>
                <tr>
                    <td><strong><?php echo $rank++; ?></strong></td>
                    <td>
                        <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="student-link">
                            <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?>
                        </a>
                    </span>
                    <td><span style="color: #c9a96b; font-weight: bold;"><?php echo number_format($student['total_points']); ?></span></td>
                    <td><?php echo $student['days_completed']; ?> يوم</span></td>
                    <td><?php echo $student['best_streak']; ?> يوم</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_wirds)): ?>
                    <tr><td colspan="5" style="text-align: center;">لا توجد بيانات بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($active_tab == 'prayers'): ?>
    <div class="students-table">
        <h3 style="padding: 20px 20px 0;"><i class="fas fa-mosque"></i> ترتيب الطلاب في الصلوات</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>نقاط الصلوات</th>
                    <th>صلاة جماعة</th>
                    <th>في الوقت</th>
                    <th>إجمالي الصلوات</th>
                </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($top_prayers as $student): 
                ?>
                <tr>
                    <td><strong><?php echo $rank++; ?></strong></td>
                    <td>
                        <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="student-link">
                            <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?>
                        </a>
                    </span>
                    <td><span style="color: #17a2b8; font-weight: bold;"><?php echo number_format($student['total_points']); ?></span></td>
                    <td><span style="color: #28a745;"><?php echo $student['jamaa_count']; ?></span></td>
                    <td><span style="color: #17a2b8;"><?php echo $student['prayed_days']; ?></span></td>
                    <td><?php echo $student['prayed_days']; ?> يوم</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_prayers)): ?>
                    <tr><td colspan="6" style="text-align: center;">لا توجد بيانات بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="students-table">
        <h3 style="padding: 20px 20px 0;"><i class="fas fa-chart-line"></i> ترتيب الطلاب العام (نقاط الأوراد + الصلوات)</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>نقاط الأوراد</th>
                    <th>نقاط الصلوات</th>
                    <th>المجموع</th>
                </thead>
            <tbody>
                <?php 
                $sorted_students = $students;
                usort($sorted_students, function($a, $b) {
                    return ($b['wird_points'] + $b['prayer_points']) - ($a['wird_points'] + $a['prayer_points']);
                });
                $rank = 1;
                foreach ($sorted_students as $student): 
                    $total = $student['wird_points'] + $student['prayer_points'];
                    if ($total == 0) continue;
                ?>
                <tr>
                    <td><strong><?php echo $rank++; ?></strong></td>
                    <td>
                        <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="student-link">
                            <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?>
                        </a>
                    </span>
                    <td><?php echo number_format($student['wird_points']); ?></td>
                    <td><?php echo number_format($student['prayer_points']); ?></td>
                    <td><span style="color: gold; font-weight: bold;"><?php echo number_format($total); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($rank == 1): ?>
                    <tr><td colspan="5" style="text-align: center;">لا توجد بيانات بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>