<?php
// ============================================
// ملف: teacher_achievement.php
// إنجازات المعلم - تقرير شهري
// آخر تحديث: 2026-04-01
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'إنجازاتي - التقارير الشهرية';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$current_year = getCurrentHijriYear();
$current_month = getHijriMonth();

// ============================================
// جلب حلقات المعلم
// ============================================
$rings = $pdo->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
    FROM rings r
    WHERE r.teacher_id = ?
    ORDER BY r.name
");
$rings->execute([$teacher_id]);
$rings = $rings->fetchAll();

// ============================================
// جلب طلاب المعلم
// ============================================
$students = $pdo->prepare("
    SELECT s.id, s.name, s.level,
           (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_surahs
    FROM students s
    WHERE s.teacher_id = ?
    ORDER BY memorized_surahs DESC, s.name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// ============================================
// جلب التقارير السابقة
// ============================================
$reports = $pdo->prepare("
    SELECT * FROM teacher_achievement_reports
    WHERE teacher_id = ?
    ORDER BY hijri_year DESC, hijri_month DESC
");
$reports->execute([$teacher_id]);
$reports = $reports->fetchAll();

// ============================================
// معالجة حفظ التقرير
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    $hijri_year = (int)$_POST['hijri_year'];
    $hijri_month = (int)$_POST['hijri_month'];
    $report_title = trim($_POST['report_title']);
    $report_content = trim($_POST['report_content']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status'];
    
    // حساب الإحصائيات
    $total_students = count($students);
    $new_memorized_surahs = $pdo->prepare("
        SELECT COUNT(*) FROM student_surah_progress sp
        JOIN students s ON sp.student_id = s.id
        WHERE s.teacher_id = ? AND MONTH(sp.completed_at) = ? AND YEAR(sp.completed_at) = ?
    ");
    $new_memorized_surahs->execute([$teacher_id, date('m'), date('Y')]);
    $new_memorized_surahs = $new_memorized_surahs->fetchColumn();
    
    $completed_quran_count = 0;
    foreach ($students as $s) {
        if ($s['memorized_surahs'] >= 114) $completed_quran_count++;
    }
    
    // جلب أفضل 5 طلاب
    $top_students = array_slice($students, 0, 5);
    $top_students_json = json_encode(array_map(function($s) {
        return ['name' => $s['name'], 'surahs' => $s['memorized_surahs']];
    }, $top_students));
    
    // التحقق من وجود تقرير لنفس الشهر
    $check = $pdo->prepare("
        SELECT id FROM teacher_achievement_reports
        WHERE teacher_id = ? AND hijri_year = ? AND hijri_month = ?
    ");
    $check->execute([$teacher_id, $hijri_year, $hijri_month]);
    $existing = $check->fetch();
    
    if ($existing) {
        // تحديث التقرير
        $stmt = $pdo->prepare("
            UPDATE teacher_achievement_reports SET
                report_title = ?, report_content = ?, total_students = ?,
                new_memorized_surahs = ?, completed_quran_count = ?,
                top_students = ?, notes = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $report_title, $report_content, $total_students,
            $new_memorized_surahs, $completed_quran_count,
            $top_students_json, $notes, $status, $existing['id']
        ]);
        $report_id = $existing['id'];
    } else {
        // إضافة تقرير جديد
        $stmt = $pdo->prepare("
            INSERT INTO teacher_achievement_reports
            (teacher_id, hijri_year, hijri_month, report_title, report_content,
             total_students, new_memorized_surahs, completed_quran_count,
             top_students, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $teacher_id, $hijri_year, $hijri_month, $report_title, $report_content,
            $total_students, $new_memorized_surahs, $completed_quran_count,
            $top_students_json, $notes, $status
        ]);
        $report_id = $pdo->lastInsertId();
    }
    
    $_SESSION['success'] = "✅ تم حفظ التقرير بنجاح";
    header("Location: teacher_achievement.php?report_id=$report_id");
    exit;
}

// ============================================
// جلب تقرير معين للعرض
// ============================================
$view_report = null;
if (isset($_GET['report_id'])) {
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as teacher_name
        FROM teacher_achievement_reports r
        JOIN teachers t ON r.teacher_id = t.id
        WHERE r.id = ? AND r.teacher_id = ?
    ");
    $stmt->execute([$_GET['report_id'], $teacher_id]);
    $view_report = $stmt->fetch();
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

.achievement-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
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

.page-header h1 i {
    color: var(--secondary);
    animation: starPulse 2s infinite;
}

@keyframes starPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* ===== بطاقات الإحصائيات ===== */
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
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    margin-top: 5px;
}

/* ===== التقارير ===== */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.report-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    border-right: 5px solid var(--secondary);
    position: relative;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.report-date {
    font-size: 0.8rem;
    color: var(--secondary);
    margin-bottom: 5px;
}

.report-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 10px;
}

.report-stats {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.stat-badge {
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.status-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-draft { background: #e9ecef; color: #6c757d; }
.status-submitted { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }

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

.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-info { background: var(--info); color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-warning { background: var(--warning); color: #212529; }

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== نموذج التقرير ===== */
.form-card {
    background: white;
    border-radius: 25px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--primary);
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    transition: 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--secondary);
}

textarea.form-control {
    min-height: 200px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* ===== عرض التقرير ===== */
.report-view {
    background: white;
    border-radius: 30px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.report-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--secondary);
}

.report-header h1 {
    color: var(--primary);
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.report-meta {
    color: #666;
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.top-students-list {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 20px;
    margin: 20px 0;
}

.top-student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

.top-student-item:last-child {
    border-bottom: none;
}

.top-student-rank {
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

/* ===== حالة فارغة ===== */
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
    .form-row {
        grid-template-columns: 1fr;
    }
    .reports-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="achievement-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-trophy"></i>
            إنجازاتي - التقارير الشهرية
        </h1>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?php echo getHijriDateFormatted(); ?>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($students); ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($rings); ?></div>
            <div class="stat-label">عدد الحلقات</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($reports); ?></div>
            <div class="stat-label">التقارير المسجلة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($students, fn($s) => $s['memorized_surahs'] >= 114)); ?></div>
            <div class="stat-label">خاتمين للقرآن</div>
        </div>
    </div>

    <?php if ($view_report): ?>
        <!-- عرض التقرير -->
        <div class="report-view">
            <div class="report-header">
                <h1><?php echo htmlspecialchars($view_report['report_title']); ?></h1>
                <div class="report-meta">
                    <span><i class="fas fa-calendar"></i> <?php echo $view_report['hijri_year']; ?> هـ - الشهر <?php echo $view_report['hijri_month']; ?></span>
                    <span><i class="fas fa-user"></i> المعلم: <?php echo htmlspecialchars($view_report['teacher_name']); ?></span>
                    <span><i class="fas fa-clock"></i> تاريخ الإعداد: <?php echo date('Y-m-d', strtotime($view_report['created_at'])); ?></span>
                </div>
            </div>

            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $view_report['total_students']; ?></div>
                    <div class="stat-label">إجمالي الطلاب</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $view_report['new_memorized_surahs']; ?></div>
                    <div class="stat-label">سور جديدة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $view_report['completed_quran_count']; ?></div>
                    <div class="stat-label">خاتمين</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $view_report['status'] == 'approved' ? '✓ معتمد' : ($view_report['status'] == 'submitted' ? '⏳ قيد المراجعة' : '📝 مسودة'); ?></div>
                    <div class="stat-label">حالة التقرير</div>
                </div>
            </div>

            <?php
            $top_students = json_decode($view_report['top_students'], true);
            if (!empty($top_students)):
            ?>
            <div class="top-students-list">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-crown" style="color: gold;"></i> أفضل 5 طلاب في الحفظ</h3>
                <?php foreach ($top_students as $index => $student): ?>
                <div class="top-student-item">
                    <div class="top-student-rank rank-<?php echo $index + 1; ?>">
                        <?php echo $index + 1; ?>
                    </div>
                    <div style="flex: 1; margin-right: 15px;">
                        <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                    </div>
                    <div>
                        <span class="stat-badge"><?php echo $student['surahs']; ?> سورة</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="margin: 20px 0;">
                <h3><i class="fas fa-align-left"></i> تفاصيل الإنجازات</h3>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 15px; margin-top: 10px; white-space: pre-wrap;">
                    <?php echo nl2br(htmlspecialchars($view_report['report_content'])); ?>
                </div>
            </div>

            <?php if ($view_report['notes']): ?>
            <div style="margin: 20px 0;">
                <h3><i class="fas fa-sticky-note"></i> ملاحظات</h3>
                <div style="background: #fff3cd; padding: 15px; border-radius: 15px; margin-top: 10px;">
                    <?php echo nl2br(htmlspecialchars($view_report['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <a href="teacher_achievement.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> العودة</a>
                <a href="export_achievement_pdf.php?report_id=<?php echo $view_report['id']; ?>" class="btn btn-primary" target="_blank"><i class="fas fa-file-pdf"></i> تصدير PDF</a>
                <?php if ($view_report['status'] != 'submitted'): ?>
                    <a href="?submit_report=<?php echo $view_report['id']; ?>" class="btn btn-success" onclick="return confirm('هل أنت متأكد من إرسال التقرير للإدارة؟')"><i class="fas fa-paper-plane"></i> إرسال للإدارة</a>
                <?php endif; ?>
                <a href="?edit_report=<?php echo $view_report['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> تعديل</a>
            </div>
        </div>

    <?php elseif (isset($_GET['new_report']) || isset($_GET['edit_report'])): 
        $edit_report = null;
        if (isset($_GET['edit_report'])) {
            $stmt = $pdo->prepare("SELECT * FROM teacher_achievement_reports WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$_GET['edit_report'], $teacher_id]);
            $edit_report = $stmt->fetch();
        }
    ?>
        <!-- نموذج إنشاء/تعديل التقرير -->
        <form method="post" class="form-card">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> السنة الهجرية</label>
                    <input type="number" name="hijri_year" class="form-control" 
                           value="<?php echo $edit_report ? $edit_report['hijri_year'] : $current_year; ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> الشهر الهجري</label>
                    <input type="number" name="hijri_month" class="form-control" min="1" max="12"
                           value="<?php echo $edit_report ? $edit_report['hijri_month'] : $current_month; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-heading"></i> عنوان التقرير</label>
                <input type="text" name="report_title" class="form-control" 
                       value="<?php echo $edit_report ? htmlspecialchars($edit_report['report_title']) : 'تقرير إنجازات الشهر ' . $current_month . ' - ' . $current_year . ' هـ'; ?>" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-file-alt"></i> تفاصيل الإنجازات</label>
                <textarea name="report_content" class="form-control" rows="10" placeholder="اكتب تفاصيل الإنجازات هنا..."><?php echo $edit_report ? htmlspecialchars($edit_report['report_content']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo $edit_report ? htmlspecialchars($edit_report['notes']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label><i class="fas fa-circle"></i> حالة التقرير</label>
                <select name="status" class="form-control">
                    <option value="draft" <?php echo ($edit_report && $edit_report['status'] == 'draft') ? 'selected' : ''; ?>>مسودة</option>
                    <option value="submitted" <?php echo ($edit_report && $edit_report['status'] == 'submitted') ? 'selected' : ''; ?>>مرسل للإدارة</option>
                </select>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" name="save_report" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التقرير</button>
                <a href="teacher_achievement.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>

    <?php else: ?>
        <!-- قائمة التقارير -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: var(--primary);">التقارير السابقة</h2>
            <a href="?new_report=1" class="btn btn-success"><i class="fas fa-plus-circle"></i> إنشاء تقرير جديد</a>
        </div>

        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>لا توجد تقارير بعد</h3>
                <p>قم بإنشاء أول تقرير إنجازات شهرية</p>
                <a href="?new_report=1" class="btn btn-success" style="margin-top: 15px;"><i class="fas fa-plus-circle"></i> إنشاء تقرير جديد</a>
            </div>
        <?php else: ?>
            <div class="reports-grid">
                <?php foreach ($reports as $report): 
                    $status_class = $report['status'] == 'approved' ? 'approved' : ($report['status'] == 'submitted' ? 'submitted' : 'draft');
                    $status_text = $report['status'] == 'approved' ? 'معتمد' : ($report['status'] == 'submitted' ? 'مرسل للإدارة' : 'مسودة');
                ?>
                    <div class="report-card">
                        <div class="status-badge status-<?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                        <div class="report-date">
                            <i class="fas fa-calendar-alt"></i> <?php echo $report['hijri_year']; ?> هـ - الشهر <?php echo $report['hijri_month']; ?>
                        </div>
                        <div class="report-title"><?php echo htmlspecialchars($report['report_title']); ?></div>
                        <div class="report-stats">
                            <span class="stat-badge"><i class="fas fa-users"></i> <?php echo $report['total_students']; ?> طالب</span>
                            <span class="stat-badge"><i class="fas fa-quran"></i> <?php echo $report['new_memorized_surahs']; ?> سورة</span>
                            <span class="stat-badge"><i class="fas fa-crown"></i> <?php echo $report['completed_quran_count']; ?> خاتم</span>
                        </div>
                        <div class="report-actions">
                            <a href="?report_id=<?php echo $report['id']; ?>" class="btn btn-info"><i class="fas fa-eye"></i> عرض</a>
                            <a href="?edit_report=<?php echo $report['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> تعديل</a>
                            <a href="export_achievement_pdf.php?report_id=<?php echo $report['id']; ?>" class="btn btn-primary" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>