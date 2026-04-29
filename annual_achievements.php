<?php
// ============================================
// ملف: annual_achievements.php
// إنجازات دار التقوى السنوية - تقرير تلقائي
// آخر تحديث: 2026-04-02
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الإنجازات السنوية - دار التقوى';
require_once 'includes/header.php';

$current_year = date('Y');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// ============================================
// إحصائيات عامة للدار
// ============================================

// إجمالي الطلاب المسجلين
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// إجمالي المعلمين
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

// عدد الطلاب النشطين (لديهم حضور في آخر 30 يوم)
$active_students = $pdo->query("
    SELECT COUNT(DISTINCT person_id) FROM attendance 
    WHERE person_type = 'student' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();

// عدد الخاتمين (أكملوا 114 سورة)
$completed_quran = $pdo->query("
    SELECT COUNT(DISTINCT student_id) 
    FROM student_surah_progress 
    WHERE completed = 1 
    GROUP BY student_id 
    HAVING COUNT(*) >= 114
")->rowCount();

// إجمالي السور المحفوظة
$total_memorized = $pdo->query("
    SELECT COUNT(*) FROM student_surah_progress WHERE completed = 1
")->fetchColumn();

// عدد الشهادات الممنوحة
$total_certificates = $pdo->query("
    SELECT COUNT(*) FROM student_achievements
")->fetchColumn();

// ============================================
// إحصائيات حسب الفئة
// ============================================

$category_stats = $pdo->query("
    SELECT 
        SUM(CASE WHEN category = 'boy' THEN 1 ELSE 0 END) as boys,
        SUM(CASE WHEN category = 'girl' THEN 1 ELSE 0 END) as girls,
        SUM(CASE WHEN category = 'child' THEN 1 ELSE 0 END) as children,
        SUM(CASE WHEN category = 'woman' THEN 1 ELSE 0 END) as women
    FROM students
")->fetch();

// ============================================
// أفضل 10 طلاب حفظاً
// ============================================

$top_students = $pdo->query("
    SELECT s.id, s.name, s.category, s.level, COUNT(sp.id) as memorized_count
    FROM students s
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    GROUP BY s.id
    ORDER BY memorized_count DESC
    LIMIT 10
")->fetchAll();

// ============================================
// إحصائيات المعلمين
// ============================================

$teacher_stats = $pdo->query("
    SELECT 
        t.id,
        t.name,
        t.gender,
        COUNT(DISTINCT s.id) as student_count,
        COUNT(DISTINCT sp.id) as total_memorized,
        COUNT(DISTINCT CASE WHEN sp.completed = 1 AND sp.id IS NOT NULL THEN sp.student_id END) as students_with_memorization,
        (SELECT COUNT(*) FROM student_surah_progress sp2 
         JOIN students s2 ON sp2.student_id = s2.id 
         WHERE s2.teacher_id = t.id AND sp2.completed = 1) as teacher_total_memorized,
        (SELECT COUNT(DISTINCT sp3.student_id) FROM student_surah_progress sp3 
         JOIN students s3 ON sp3.student_id = s3.id 
         WHERE s3.teacher_id = t.id AND sp3.completed = 1 
         GROUP BY sp3.student_id HAVING COUNT(*) >= 114) as teacher_completed
    FROM teachers t
    LEFT JOIN students s ON t.id = s.teacher_id
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    GROUP BY t.id
    ORDER BY teacher_total_memorized DESC
")->fetchAll();

// ============================================
// إحصائيات الحلقات
// ============================================

$rings_stats = $pdo->query("
    SELECT 
        r.id,
        r.name,
        r.is_scattered,
        COUNT(DISTINCT rs.student_id) as student_count,
        (SELECT COUNT(DISTINCT sp.student_id) FROM student_surah_progress sp 
         JOIN ring_students rs2 ON sp.student_id = rs2.student_id 
         WHERE rs2.ring_id = r.id AND sp.completed = 1) as students_with_memorization,
        (SELECT COUNT(*) FROM student_surah_progress sp 
         JOIN ring_students rs2 ON sp.student_id = rs2.student_id 
         WHERE rs2.ring_id = r.id AND sp.completed = 1) as total_memorized
    FROM rings r
    LEFT JOIN ring_students rs ON r.id = rs.ring_id
    GROUP BY r.id
    ORDER BY student_count DESC
")->fetchAll();

// ============================================
// الإنجازات الشهرية (آخر 12 شهر)
// ============================================

$monthly_achievements = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_memorized,
        COUNT(DISTINCT student_id) as students_count
    FROM student_surah_progress 
    WHERE completed = 1 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();

// ============================================
// إنجازات السور الأكثر حفظاً
// ============================================

$top_surahs = $pdo->query("
    SELECT 
        surah_number,
        COUNT(*) as memorized_count
    FROM student_surah_progress 
    WHERE completed = 1
    GROUP BY surah_number
    ORDER BY memorized_count DESC
    LIMIT 10
")->fetchAll();
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --primary-dark: #0a2a2c;
    --secondary: #c9a96b;
    --secondary-light: #dbb87c;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --gold: #ffd700;
    --silver: #c0c0c0;
    --bronze: #cd7f32;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.annual-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 35px;
    border-radius: 30px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.page-header h1 {
    font-size: 2.2rem;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.page-header h1 i {
    color: var(--secondary);
}

.page-header p {
    position: relative;
    z-index: 2;
    opacity: 0.9;
}

.year-selector {
    margin-top: 20px;
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.year-selector select {
    padding: 10px 25px;
    border-radius: 50px;
    border: none;
    font-size: 1rem;
    background: rgba(255,255,255,0.2);
    color: white;
    cursor: pointer;
}

.year-selector select option {
    color: var(--primary);
}

.year-selector button {
    padding: 10px 25px;
    border-radius: 50px;
    border: none;
    background: var(--secondary);
    color: var(--primary-dark);
    font-weight: bold;
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
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    margin-top: 5px;
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

/* ===== أقسام التقرير ===== */
.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 35px 0 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--secondary);
}

.section-title i {
    font-size: 1.8rem;
    color: var(--secondary);
}

.section-title h2 {
    color: var(--primary);
    font-size: 1.5rem;
    margin: 0;
}

/* ===== توزيع الفئات ===== */
.category-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.category-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.category-card.boy { border-top: 5px solid #3498db; }
.category-card.girl { border-top: 5px solid #9b59b6; }
.category-card.child { border-top: 5px solid #f39c12; }
.category-card.woman { border-top: 5px solid #e84342; }

.category-number {
    font-size: 2rem;
    font-weight: 800;
}

.category-label {
    color: #666;
    margin-top: 5px;
}

/* ===== جدول المعلمين ===== */
.teachers-table {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: var(--primary);
    color: white;
    padding: 15px;
    text-align: center;
    font-weight: 600;
}

td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #f8f9fa;
}

/* ===== أفضل الطلاب ===== */
.top-students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.top-student-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: 0.3s;
    border-right: 5px solid;
}

.top-student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.top-student-rank {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
}

.rank-1 { background: var(--gold); color: #212529; }
.rank-2 { background: var(--silver); color: #212529; }
.rank-3 { background: var(--bronze); }
.rank-other { background: var(--info); }

.top-student-info {
    flex: 1;
}

.top-student-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
}

.top-student-count {
    color: var(--secondary);
    font-weight: 600;
    font-size: 0.9rem;
}

/* ===== الرسم البياني ===== */
.chart-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

/* ===== حلقات المتفرقين ===== */
.rings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ring-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: 0.3s;
    border-right: 4px solid var(--secondary);
}

.ring-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.ring-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ring-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    color: #666;
    font-size: 0.85rem;
}

/* ===== أزرار التصدير ===== */
.export-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 30px;
    border-radius: 50px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-pdf {
    background: #dc3545;
    color: white;
}

.btn-print {
    background: #6c757d;
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

/* ===== حالة فارغة ===== */
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .category-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .category-grid {
        grid-template-columns: 1fr;
    }
    .teachers-table {
        overflow-x: auto;
    }
    table {
        min-width: 600px;
    }
}

@media print {
    .export-buttons, .year-selector, .page-header::before, .btn {
        display: none;
    }
    .page-header {
        background: var(--primary);
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .stat-card, .category-card, .top-student-card, .ring-card {
        break-inside: avoid;
    }
}
</style>

<section class="annual-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            إنجازات دار التقوى السنوية
        </h1>
        <p>تقرير شامل للإنجازات المحققة خلال العام <?php echo $selected_year; ?></p>
        <div class="year-selector">
            <form method="get">
                <select name="year">
                    <?php for ($y = 2024; $y <= date('Y'); $y++): ?>
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
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-number"><?php echo number_format($total_students); ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🏫</div>
            <div class="stat-number"><?php echo number_format($total_teachers); ?></div>
            <div class="stat-label">المعلمون</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⭐</div>
            <div class="stat-number"><?php echo number_format($active_students); ?></div>
            <div class="stat-label">طلاب نشطون</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-number"><?php echo number_format($completed_quran); ?></div>
            <div class="stat-label">خاتمين للقرآن</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📖</div>
            <div class="stat-number"><?php echo number_format($total_memorized); ?></div>
            <div class="stat-label">سور محفوظة</div>
        </div>
    </div>

    <!-- توزيع الطلاب حسب الفئة -->
    <div class="section-title">
        <i class="fas fa-chart-pie"></i>
        <h2>توزيع الطلاب حسب الفئة</h2>
    </div>
    <div class="category-grid">
        <div class="category-card boy">
            <div class="category-number"><?php echo number_format($category_stats['boys']); ?></div>
            <div class="category-label">أولاد</div>
        </div>
        <div class="category-card girl">
            <div class="category-number"><?php echo number_format($category_stats['girls']); ?></div>
            <div class="category-label">بنات</div>
        </div>
        <div class="category-card child">
            <div class="category-number"><?php echo number_format($category_stats['children']); ?></div>
            <div class="category-label">أطفال</div>
        </div>
        <div class="category-card woman">
            <div class="category-number"><?php echo number_format($category_stats['women']); ?></div>
            <div class="category-label">نساء</div>
        </div>
    </div>

    <!-- أفضل الطلاب حفظاً -->
    <div class="section-title">
        <i class="fas fa-crown"></i>
        <h2>أفضل الطلاب حفظاً للقرآن الكريم</h2>
    </div>
    <div class="top-students-grid">
        <?php foreach ($top_students as $index => $student): 
            $rank = $index + 1;
            $rank_class = $rank <= 3 ? $rank : 'other';
        ?>
            <div class="top-student-card">
                <div class="top-student-rank rank-<?php echo $rank_class; ?>">
                    <?php if ($rank == 1): ?>🥇
                    <?php elseif ($rank == 2): ?>🥈
                    <?php elseif ($rank == 3): ?>🥉
                    <?php else: echo $rank; endif; ?>
                </div>
                <div class="top-student-info">
                    <div class="top-student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="top-student-count"><?php echo $student['memorized_count']; ?> سورة</div>
                </div>
                <div class="top-student-category">
                    <span class="badge" style="background: <?php 
                        echo $student['category'] == 'boy' ? '#3498db' : 
                            ($student['category'] == 'girl' ? '#9b59b6' : 
                            ($student['category'] == 'child' ? '#f39c12' : '#e84342')); 
                    ?>; color: white; padding: 5px 12px; border-radius: 20px;">
                        <?php 
                        $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
                        echo $cat_names[$student['category']] ?? $student['category'];
                        ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- إحصائيات المعلمين -->
    <div class="section-title">
        <i class="fas fa-chalkboard-teacher"></i>
        <h2>إنجازات المعلمين</h2>
    </div>
    <div class="teachers-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المعلم</th>
                    <th>عدد الطلاب</th>
                    <th>إجمالي السور المحفوظة</th>
                    <th>الطلاب الخاتمين</th>
                    <th>طلاب لديهم حفظ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teacher_stats as $index => $teacher): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($teacher['name']); ?></strong>
                            <br>
                            <small style="color: #666;"><?php echo $teacher['gender'] == 'male' ? 'معلم' : 'معلمة'; ?></small>
                        </td>
                        <td><?php echo number_format($teacher['student_count']); ?></td>
                        <td><?php echo number_format($teacher['teacher_total_memorized']); ?></td>
                        <td>
                            <?php if ($teacher['teacher_completed'] > 0): ?>
                                <span style="color: #28a745; font-weight: bold;"><?php echo $teacher['teacher_completed']; ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($teacher['students_with_memorization']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- إحصائيات الحلقات -->
    <div class="section-title">
        <i class="fas fa-ring"></i>
        <h2>إنجازات الحلقات القرآنية</h2>
    </div>
    <div class="rings-grid">
        <?php foreach ($rings_stats as $ring): ?>
            <div class="ring-card">
                <div class="ring-name">
                    <i class="fas fa-ring"></i>
                    <?php echo htmlspecialchars($ring['name']); ?>
                    <?php if ($ring['is_scattered']): ?>
                        <span style="background: #f39c12; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;">
                            <i class="fas fa-hourglass-half"></i> متفرقين
                        </span>
                    <?php endif; ?>
                </div>
                <div class="ring-stats">
                    <span><i class="fas fa-users"></i> <?php echo $ring['student_count']; ?> طالب</span>
                    <span><i class="fas fa-quran"></i> <?php echo number_format($ring['total_memorized']); ?> سورة</span>
                </div>
                <div class="ring-stats">
                    <span><i class="fas fa-star"></i> طلاب لديهم حفظ: <?php echo $ring['students_with_memorization']; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- الرسم البياني للإنجازات الشهرية -->
    <div class="section-title">
        <i class="fas fa-chart-line"></i>
        <h2>الإنجازات الشهرية (آخر 12 شهر)</h2>
    </div>
    <div class="chart-container">
        <canvas id="monthlyChart" style="width:100%; max-height: 400px;"></canvas>
    </div>

    <!-- السور الأكثر حفظاً -->
    <div class="section-title">
        <i class="fas fa-book-open"></i>
        <h2>السور الأكثر حفظاً</h2>
    </div>
    <div class="chart-container">
        <canvas id="surahsChart" style="width:100%; max-height: 400px;"></canvas>
    </div>

    <!-- أزرار التصدير -->
    <div class="export-buttons">
        <button class="btn btn-pdf" onclick="exportToPDF()">
            <i class="fas fa-file-pdf"></i> تصدير PDF
        </button>
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> طباعة التقرير
        </button>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// الرسم البياني للإنجازات الشهرية
const monthlyData = <?php echo json_encode(array_reverse($monthly_achievements)); ?>;
const monthlyLabels = monthlyData.map(item => item.month);
const monthlyValues = monthlyData.map(item => item.new_memorized);

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'السور المحفوظة شهرياً',
            data: monthlyValues,
            borderColor: '#1e3c3f',
            backgroundColor: 'rgba(30,60,63,0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#c9a96b',
            pointBorderColor: '#1e3c3f',
            pointRadius: 5,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top' }
        }
    }
});

// الرسم البياني للسور الأكثر حفظاً
const surahsData = <?php echo json_encode($top_surahs); ?>;
const surahsLabels = surahsData.map(item => {
    const surahNames = [
        'الفاتحة', 'البقرة', 'آل عمران', 'النساء', 'المائدة', 'الأنعام', 'الأعراف', 'الأنفال', 'التوبة', 'يونس'
    ];
    return surahNames[item.surah_number - 1] || `سورة ${item.surah_number}`;
});
const surahsValues = surahsData.map(item => item.memorized_count);

new Chart(document.getElementById('surahsChart'), {
    type: 'bar',
    data: {
        labels: surahsLabels,
        datasets: [{
            label: 'عدد المرات التي حفظت',
            data: surahsValues,
            backgroundColor: 'rgba(30,60,63,0.8)',
            borderRadius: 8,
            borderColor: '#c9a96b',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top' }
        }
    }
});

// تصدير PDF
function exportToPDF() {
    const element = document.querySelector('.annual-page');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: 'انجازات_دار_التقوى.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php require_once 'includes/footer.php'; ?>