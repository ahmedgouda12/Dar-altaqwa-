<?php
// ============================================
// ملف: admin_achievements.php
// إدارة التقارير - تجميع إنجازات المعلمين
// آخر تحديث: 2026-04-01
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إنجازات الدار - التقارير';
require_once 'includes/header.php';

$current_year = getCurrentHijriYear();
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// ============================================
// جلب جميع التقارير المقدمة من المعلمين
// ============================================
$reports = $pdo->prepare("
    SELECT r.*, t.name as teacher_name, t.id as teacher_id
    FROM teacher_achievement_reports r
    JOIN teachers t ON r.teacher_id = t.id
    WHERE r.hijri_year = ?
    ORDER BY r.hijri_month DESC, r.status ASC
");
$reports->execute([$selected_year]);
$reports = $reports->fetchAll();

// ============================================
// إحصائيات عامة
// ============================================
$total_reports = count($reports);
$submitted_reports = count(array_filter($reports, fn($r) => $r['status'] == 'submitted'));
$approved_reports = count(array_filter($reports, fn($r) => $r['status'] == 'approved'));
$total_students = array_sum(array_column($reports, 'total_students'));
$total_memorized = array_sum(array_column($reports, 'new_memorized_surahs'));
$total_completed = array_sum(array_column($reports, 'completed_quran_count'));

// ============================================
// جلب أفضل 10 طلاب من جميع التقارير
// ============================================
$top_students = [];
foreach ($reports as $report) {
    $top = json_decode($report['top_students'], true);
    if (is_array($top)) {
        foreach ($top as $student) {
            $top_students[$student['name']] = ($top_students[$student['name']] ?? 0) + $student['surahs'];
        }
    }
}
arsort($top_students);
$top_students = array_slice($top_students, 0, 10, true);

// ============================================
// معالجة الموافقة على التقرير
// ============================================
if (isset($_GET['approve']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['approve'];
    $stmt = $pdo->prepare("
        UPDATE teacher_achievement_reports 
        SET status = 'approved', approved_by = ?, approved_at = NOW()
        WHERE id = ? AND status = 'submitted'
    ");
    $stmt->execute([$_SESSION['user_id'], $report_id]);
    $_SESSION['success'] = "✅ تم اعتماد التقرير بنجاح";
    header("Location: admin_achievements.php?year=$selected_year");
    exit;
}

// ============================================
// معالجة رفض التقرير
// ============================================
if (isset($_GET['reject']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['reject'];
    $stmt = $pdo->prepare("UPDATE teacher_achievement_reports SET status = 'draft' WHERE id = ?");
    $stmt->execute([$report_id]);
    $_SESSION['success'] = "✅ تم إعادة التقرير للمعلم للتعديل";
    header("Location: admin_achievements.php?year=$selected_year");
    exit;
}

$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.admin-achievements {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.year-selector {
    display: flex;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    padding: 8px;
    border-radius: 50px;
}

.year-selector input {
    padding: 8px 15px;
    border: none;
    border-radius: 30px;
    font-size: 1rem;
}

.year-selector button {
    padding: 8px 20px;
    border: none;
    border-radius: 30px;
    background: var(--secondary);
    color: var(--primary);
    font-weight: 600;
    cursor: pointer;
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    margin-top: 5px;
}

/* ===== قائمة المعلمين ===== */
.teachers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.teacher-card {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: 0.3s;
    border: 1px solid rgba(0,0,0,0.05);
}

.teacher-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.teacher-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.teacher-name {
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.month-badge {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
}

.report-content {
    padding: 20px;
}

.report-stats {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.stat-tag {
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.report-summary {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
    max-height: 150px;
    overflow-y: auto;
    font-size: 0.9rem;
    line-height: 1.6;
}

.report-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn {
    flex: 1;
    padding: 8px 12px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 0.85rem;
    transition: 0.3s;
    border: none;
    cursor: pointer;
}

.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-info { background: var(--info); color: white; }
.btn-primary { background: var(--primary); color: white; }

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== أفضل الطلاب ===== */
.top-students-section {
    background: white;
    border-radius: 25px;
    padding: 25px;
    margin-top: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.top-students-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.top-student-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 15px;
    transition: 0.3s;
}

.top-student-item:hover {
    transform: translateX(-5px);
    background: #e9ecef;
}

.top-student-rank {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    font-size: 1.2rem;
}

.rank-1 { background: gold; color: #212529; }
.rank-2 { background: silver; color: #212529; }
.rank-3 { background: #cd7f32; }
.rank-other { background: #6c757d; }

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 25px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .teachers-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="admin-achievements">
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            إنجازات الدار - التقارير السنوية
        </h1>
        <div class="year-selector">
            <form method="get">
                <input type="number" name="year" value="<?php echo $selected_year; ?>" min="1440" max="1500">
                <button type="submit"><i class="fas fa-search"></i> عرض</button>
            </form>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <!-- إحصائيات عامة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_reports; ?></div>
            <div class="stat-label">إجمالي التقارير</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $submitted_reports; ?></div>
            <div class="stat-label">قيد المراجعة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $approved_reports; ?></div>
            <div class="stat-label">معتمدة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_students; ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_completed; ?></div>
            <div class="stat-label">خاتمين للقرآن</div>
        </div>
    </div>

    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-line"></i>
            <h3>لا توجد تقارير للسنة <?php echo $selected_year; ?> هـ</h3>
            <p>لم يقم أي معلم بتقديم تقارير إنجازات لهذه السنة</p>
        </div>
    <?php else: ?>
        <!-- قائمة تقارير المعلمين -->
        <div class="teachers-grid">
            <?php 
            $grouped_reports = [];
            foreach ($reports as $report) {
                $grouped_reports[$report['teacher_name']][] = $report;
            }
            foreach ($grouped_reports as $teacher_name => $teacher_reports):
                $teacher_total_students = array_sum(array_column($teacher_reports, 'total_students'));
                $teacher_total_memorized = array_sum(array_column($teacher_reports, 'new_memorized_surahs'));
            ?>
                <div class="teacher-card">
                    <div class="teacher-header">
                        <div class="teacher-name">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <?php echo htmlspecialchars($teacher_name); ?>
                        </div>
                        <div class="month-badge">
                            <?php echo count($teacher_reports); ?> تقرير
                        </div>
                    </div>
                    <div class="report-content">
                        <div class="report-stats">
                            <span class="stat-tag"><i class="fas fa-users"></i> <?php echo $teacher_total_students; ?> طالب</span>
                            <span class="stat-tag"><i class="fas fa-quran"></i> <?php echo $teacher_total_memorized; ?> سورة</span>
                        </div>
                        
                        <?php foreach ($teacher_reports as $report): ?>
                            <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 12px; border-right: 3px solid <?php echo $report['status'] == 'approved' ? '#28a745' : ($report['status'] == 'submitted' ? '#ffc107' : '#6c757d'); ?>;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <strong>الشهر <?php echo $report['hijri_month']; ?></strong>
                                    <span class="stat-tag">
                                        <?php if ($report['status'] == 'approved'): ?>
                                            <i class="fas fa-check-circle" style="color: #28a745;"></i> معتمد
                                        <?php elseif ($report['status'] == 'submitted'): ?>
                                            <i class="fas fa-clock" style="color: #ffc107;"></i> قيد المراجعة
                                        <?php else: ?>
                                            <i class="fas fa-pencil-alt" style="color: #6c757d;"></i> مسودة
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="report-summary">
                                    <?php echo nl2br(htmlspecialchars(mb_substr($report['report_content'], 0, 100))) . (mb_strlen($report['report_content']) > 100 ? '...' : ''); ?>
                                </div>
                                <div class="report-actions">
                                    <a href="teacher_achievement.php?report_id=<?php echo $report['id']; ?>" class="btn btn-info" target="_blank">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                    <?php if ($report['status'] == 'submitted'): ?>
                                        <a href="?approve=<?php echo $report['id']; ?>&year=<?php echo $selected_year; ?>" class="btn btn-success" onclick="return confirm('اعتماد هذا التقرير؟')">
                                            <i class="fas fa-check"></i> اعتماد
                                        </a>
                                        <a href="?reject=<?php echo $report['id']; ?>&year=<?php echo $selected_year; ?>" class="btn btn-danger" onclick="return confirm('إعادة هذا التقرير للمعلم للتعديل؟')">
                                            <i class="fas fa-undo"></i> إعادة
                                        </a>
                                    <?php endif; ?>
                                    <a href="export_achievement_pdf.php?report_id=<?php echo $report['id']; ?>" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- أفضل الطلاب -->
        <?php if (!empty($top_students)): ?>
        <div class="top-students-section">
            <h2><i class="fas fa-crown" style="color: gold;"></i> أفضل الطلاب حفظاً للقرآن</h2>
            <div class="top-students-list">
                <?php $rank = 1; foreach ($top_students as $name => $surahs): ?>
                    <div class="top-student-item">
                        <div class="top-student-rank rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                            <?php echo $rank; ?>
                        </div>
                        <div style="flex: 1;">
                            <strong><?php echo htmlspecialchars($name); ?></strong>
                        </div>
                        <div>
                            <span class="stat-tag"><?php echo $surahs; ?> سورة</span>
                        </div>
                    </div>
                <?php $rank++; endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>