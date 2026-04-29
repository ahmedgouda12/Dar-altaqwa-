<?php
// ============================================
// ملف: final_exam_results.php
// نتائج الاختبارات النهائية
// آخر تحديث: 2026-03-16
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'نتائج الاختبارات النهائية';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$is_admin = isAdmin();

// جلب جميع الاختبارات المكتملة
if (isAdmin()) {
    // للإدارة - كل الاختبارات
    $exams = $pdo->query("
        SELECT 
            e.*,
            s.name as student_name,
            s.level as student_level,
            t.name as teacher_name,
            (SELECT COUNT(*) FROM memorization_sessions WHERE student_exam_id = e.id) as sessions_done,
            (SELECT AVG(session_score) FROM memorization_sessions WHERE student_exam_id = e.id) as avg_score,
            r.grade,
            r.completed_at as result_date
        FROM final_student_exams e
        JOIN students s ON e.student_id = s.id
        LEFT JOIN teachers t ON e.teacher_id = t.id
        LEFT JOIN exam_results r ON e.id = r.student_exam_id
        WHERE e.status = 'completed'
        ORDER BY e.end_date DESC
    ")->fetchAll();
} else {
    // للمعلم - اختبارات طلابه فقط
    $exams = $pdo->prepare("
        SELECT 
            e.*,
            s.name as student_name,
            s.level as student_level,
            (SELECT COUNT(*) FROM memorization_sessions WHERE student_exam_id = e.id) as sessions_done,
            (SELECT AVG(session_score) FROM memorization_sessions WHERE student_exam_id = e.id) as avg_score,
            r.grade,
            r.completed_at as result_date
        FROM final_student_exams e
        JOIN students s ON e.student_id = s.id
        LEFT JOIN exam_results r ON e.id = r.student_exam_id
        WHERE e.teacher_id = ? AND e.status = 'completed'
        ORDER BY e.end_date DESC
    ");
    $exams->execute([$teacher_id]);
    $exams = $exams->fetchAll();
}

// إحصائيات سريعة
$total_exams = count($exams);
$avg_score_all = 0;
$excellent_count = 0;
$very_good_count = 0;
$good_count = 0;
$acceptable_count = 0;

foreach ($exams as $exam) {
    $score = $exam['avg_score'] ?? 0;
    $avg_score_all += $score;
    
    if ($score >= 95) $excellent_count++;
    elseif ($score >= 85) $very_good_count++;
    elseif ($score >= 75) $good_count++;
    elseif ($score >= 60) $acceptable_count++;
}

$avg_score_all = $total_exams > 0 ? round($avg_score_all / $total_exams, 2) : 0;
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --secondary-light: #dbb87c;
    --success: #28a745;
    --success-light: #d4edda;
    --danger: #dc3545;
    --danger-light: #f8d7da;
    --warning: #ffc107;
    --warning-light: #fff3cd;
    --info: #17a2b8;
    --info-light: #d1ecf1;
    --dark: #2c3e50;
    --light: #f8f9fa;
    --white: #ffffff;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.results-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 25px 30px;
    border-radius: 30px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.page-header h1 {
    margin: 0;
    font-size: 2rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: var(--secondary);
}

.header-stats {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.stat-badge {
    background: rgba(255,255,255,0.15);
    padding: 8px 20px;
    border-radius: 40px;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(5px);
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.5rem;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== قسم البحث والفلترة ===== */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.search-box input {
    flex: 1;
    padding: 12px 20px;
    border: 2px solid #e9ecef;
    border-radius: 50px;
    font-size: 1rem;
}

.search-box button {
    padding: 12px 30px;
    border: none;
    border-radius: 50px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.search-box button:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}

.filter-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-tab {
    padding: 8px 20px;
    border-radius: 40px;
    background: #f8f9fa;
    color: var(--dark);
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
}

.filter-tab:hover {
    background: #e9ecef;
}

.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--secondary);
}

/* ===== بطاقات النتائج ===== */
.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.result-card {
    background: white;
    border-radius: 25px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.result-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.result-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
}

.result-card.excellent::before { background: linear-gradient(90deg, #28a745, #20c997); }
.result-card.very-good::before { background: linear-gradient(90deg, #17a2b8, #138496); }
.result-card.good::before { background: linear-gradient(90deg, #ffc107, #e0a800); }
.result-card.acceptable::before { background: linear-gradient(90deg, #6c757d, #5a6268); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px dashed #e9ecef;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: bold;
}

.student-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.student-level {
    font-size: 0.8rem;
    color: #666;
}

.score-badge {
    padding: 8px 15px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 1.1rem;
}

.score-badge.excellent { background: var(--success-light); color: #155724; }
.score-badge.very-good { background: var(--info-light); color: #0c5460; }
.score-badge.good { background: var(--warning-light); color: #856404; }
.score-badge.acceptable { background: #e2e3e5; color: #383d41; }

.card-body {
    margin: 15px 0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-label {
    color: #666;
    font-weight: 600;
}

.info-value {
    font-weight: 700;
    color: var(--primary);
}

.teacher-name {
    margin-top: 10px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 10px;
    text-align: center;
    color: var(--primary);
    font-weight: 600;
}

.card-footer {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.btn {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #138496);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 30px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .results-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-tabs {
        flex-direction: column;
    }
    
    .filter-tab {
        width: 100%;
        text-align: center;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .student-info {
        width: 100%;
        justify-content: center;
    }
}
</style>

<section class="results-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-trophy"></i>
            نتائج الاختبارات النهائية
        </h1>
        <div class="header-stats">
            <span class="stat-badge">
                <i class="fas fa-file-alt"></i> <?php echo $total_exams; ?> اختبار
            </span>
            <span class="stat-badge">
                <i class="fas fa-star"></i> <?php echo $avg_score_all; ?>%
            </span>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #28a74520; color: #28a745;">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-number"><?php echo $excellent_count; ?></div>
            <div class="stat-label">ممتاز</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #17a2b820; color: #17a2b8;">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-number"><?php echo $very_good_count; ?></div>
            <div class="stat-label">جيد جداً</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ffc10720; color: #ffc107;">
                <i class="fas fa-smile"></i>
            </div>
            <div class="stat-number"><?php echo $good_count; ?></div>
            <div class="stat-label">جيد</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #6c757d20; color: #6c757d;">
                <i class="fas fa-meh"></i>
            </div>
            <div class="stat-number"><?php echo $acceptable_count; ?></div>
            <div class="stat-label">مقبول</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #1e3c3f20; color: #1e3c3f;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo count(array_unique(array_column($exams, 'student_id'))); ?></div>
            <div class="stat-label">طلاب مختبرين</div>
        </div>
    </div>

    <!-- فلترة وبحث -->
    <div class="filter-section">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="ابحث عن طالب...">
            <button onclick="searchExams()"><i class="fas fa-search"></i> بحث</button>
        </div>
        <div class="filter-tabs">
            <span class="filter-tab active" onclick="filterExams('all')">الكل</span>
            <span class="filter-tab" onclick="filterExams('excellent')">ممتاز</span>
            <span class="filter-tab" onclick="filterExams('very-good')">جيد جداً</span>
            <span class="filter-tab" onclick="filterExams('good')">جيد</span>
            <span class="filter-tab" onclick="filterExams('acceptable')">مقبول</span>
        </div>
    </div>

    <!-- نتائج الاختبارات -->
    <?php if (empty($exams)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h2>لا توجد نتائج بعد</h2>
            <p>لم يتم إكمال أي اختبار نهائي حتى الآن</p>
        </div>
    <?php else: ?>
        <div class="results-grid" id="resultsGrid">
            <?php foreach ($exams as $exam): 
                $score = $exam['avg_score'] ?? 0;
                $grade_class = 'acceptable';
                if ($score >= 95) $grade_class = 'excellent';
                elseif ($score >= 85) $grade_class = 'very-good';
                elseif ($score >= 75) $grade_class = 'good';
                
                $grade_text = 'مقبول';
                if ($score >= 95) $grade_text = 'ممتاز';
                elseif ($score >= 85) $grade_text = 'جيد جداً';
                elseif ($score >= 75) $grade_text = 'جيد';
            ?>
                <div class="result-card <?php echo $grade_class; ?>" data-student="<?php echo strtolower($exam['student_name']); ?>" data-grade="<?php echo $grade_class; ?>">
                    <div class="card-header">
                        <div class="student-info">
                            <div class="student-avatar">
                                <?php echo mb_substr($exam['student_name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div>
                                <div class="student-name"><?php echo htmlspecialchars($exam['student_name']); ?></div>
                                <div class="student-level"><?php echo htmlspecialchars($exam['student_level'] ?? 'مبتدئ'); ?></div>
                            </div>
                        </div>
                        <div class="score-badge <?php echo $grade_class; ?>">
                            <?php echo round($score, 1); ?>%
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">التقدير</span>
                            <span class="info-value"><?php echo $grade_text; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">الأجزاء</span>
                            <span class="info-value"><?php echo $exam['sessions_done']; ?>/<?php echo $exam['total_parts']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">تاريخ الانتهاء</span>
                            <span class="info-value"><?php echo $exam['result_date'] ?? $exam['end_date']; ?></span>
                        </div>
                        <?php if (isset($exam['teacher_name'])): ?>
                            <div class="teacher-name">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <?php echo htmlspecialchars($exam['teacher_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <a href="certificate_view.php?achievement_id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-certificate"></i> شهادة
                        </a>
                        <a href="view_progress.php?student_id=<?php echo $exam['student_id']; ?>" class="btn btn-info">
                            <i class="fas fa-chart-line"></i> تقدم
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
function searchExams() {
    const searchText = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.result-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const studentName = card.getAttribute('data-student') || '';
        if (studentName.includes(searchText) || searchText === '') {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
}

function filterExams(grade) {
    // تحديث التبويبات
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // تصفية البطاقات
    const cards = document.querySelectorAll('.result-card');
    cards.forEach(card => {
        if (grade === 'all') {
            card.style.display = 'block';
        } else {
            const cardGrade = card.getAttribute('data-grade');
            card.style.display = cardGrade === grade ? 'block' : 'none';
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>