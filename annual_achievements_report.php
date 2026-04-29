<?php
// ============================================
// annual_achievements_report.php - نسخة مصححة
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// التحقق من أن المستخدم إدارة
if (!isAdmin()) {
    echo "❌ هذه الصفحة مخصصة للإدارة فقط";
    exit;
}

$pageTitle = 'التقرير السنوي الشامل';
$current_year = date('Y');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// إحصائيات عامة
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

// إنجازات الحلقات
$rings_achievements = $pdo->prepare("
    SELECT 
        ra.*,
        r.name as ring_name,
        t.name as teacher_name
    FROM ring_achievements ra
    JOIN rings r ON ra.ring_id = r.id
    JOIN teachers t ON ra.teacher_id = t.id
    WHERE ra.hijri_year = ?
    ORDER BY t.name, r.name
");
$rings_achievements->execute([$selected_year]);
$rings_achievements = $rings_achievements->fetchAll();

// إجمالي الإنجازات
$total_surahs = array_sum(array_column($rings_achievements, 'total_memorized_surahs'));
$total_completed = array_sum(array_column($rings_achievements, 'students_completed_quran'));

// الطلاب الخاتمين (الذين أتموا 114 سورة)
$completers = $pdo->query("
    SELECT s.name, s.category, t.name as teacher_name
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.id IN (
        SELECT student_id FROM student_surah_progress 
        WHERE completed = 1 
        GROUP BY student_id 
        HAVING COUNT(*) >= 114
    )
    ORDER BY s.name
")->fetchAll();

require_once 'includes/header.php';
?>

<style>
    .report-container {
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
        text-align: center;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        border-top: 4px solid #c9a96b;
    }
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #1e3c3f;
    }
    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 30px 0 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #c9a96b;
    }
    table {
        width: 100%;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    th {
        background: #1e3c3f;
        color: white;
        padding: 12px;
    }
    td {
        padding: 10px;
        border-bottom: 1px solid #eee;
        text-align: center;
    }
    .completers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .completer-card {
        background: white;
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        border-right: 4px solid #28a745;
    }
    .completer-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #28a745, #20c997);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    .year-selector {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 15px;
    }
    .year-selector select {
        padding: 8px 20px;
        border-radius: 30px;
        border: none;
        background: rgba(255,255,255,0.2);
        color: white;
    }
    .year-selector button {
        padding: 8px 20px;
        border-radius: 30px;
        border: none;
        background: #c9a96b;
        color: #1e3c3f;
        font-weight: bold;
        cursor: pointer;
    }
    .empty-state {
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 15px;
    }
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<section class="report-container">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> التقرير السنوي الشامل</h1>
        <p>إنجازات دار التقوى لتحفيظ القرآن الكريم - عام <?php echo $selected_year; ?></p>
        <div class="year-selector">
            <form method="get">
                <select name="year">
                    <?php for ($y = 2023; $y <= $current_year; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit"><i class="fas fa-search"></i> عرض</button>
            </form>
        </div>
    </div>

    <!-- الإحصائيات العامة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_students); ?></div>
            <div>إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_teachers); ?></div>
            <div>المعلمون</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_surahs); ?></div>
            <div>سور محفوظة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_completed); ?></div>
            <div>طلاب خاتمين</div>
        </div>
    </div>

    <!-- إنجازات الحلقات -->
    <div class="section-title">
        <i class="fas fa-ring"></i>
        <h2>إنجازات الحلقات القرآنية</h2>
    </div>
    
    <?php if (empty($rings_achievements)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-line" style="font-size: 3rem; color: #dee2e6;"></i>
            <p>لا توجد إنجازات مسجلة لهذا العام</p>
            <a href="teacher_ring_achievements.php" class="btn" style="background: #1e3c3f; color: white; padding: 8px 20px; border-radius: 30px; text-decoration: none;">تسجيل إنجازات</a>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>المعلم</th>
                    <th>الحلقة</th>
                    <th>نطاق الحفظ</th>
                    <th>السور المحفوظة</th>
                    <th>الطلاب الخاتمين</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rings_achievements as $ra): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ra['teacher_name']); ?></td>
                        <td><?php echo htmlspecialchars($ra['ring_name']); ?></td>
                        <td>
                            من سورة <?php echo getSurahName($ra['start_surah']); ?>
                            إلى سورة <?php echo getSurahName($ra['end_surah']); ?>
                        </td>
                        <td><strong><?php echo number_format($ra['total_memorized_surahs']); ?></strong></td>
                        <td>
                            <?php if ($ra['students_completed_quran'] > 0): ?>
                                <span style="color: #28a745; font-weight: bold;"><?php echo $ra['students_completed_quran']; ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- الطلاب الخاتمين للقرآن -->
    <div class="section-title">
        <i class="fas fa-crown"></i>
        <h2>الطلاب الخاتمين للقرآن الكريم</h2>
    </div>
    
    <?php if (empty($completers)): ?>
        <div class="empty-state">
            <i class="fas fa-star" style="font-size: 3rem; color: #dee2e6;"></i>
            <p>لا يوجد طلاب خاتمين للقرآن حتى الآن</p>
        </div>
    <?php else: ?>
        <div class="completers-grid">
            <?php foreach ($completers as $completer): ?>
                <div class="completer-card">
                    <div class="completer-icon">
                        <i class="fas fa-quran"></i>
                    </div>
                    <div>
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($completer['name']); ?></div>
                        <div style="font-size: 0.8rem; color: #666;">
                            <?php echo htmlspecialchars($completer['teacher_name'] ?? 'غير محدد'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>