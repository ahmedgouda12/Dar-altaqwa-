<?php
// ============================================
// ملف: my_badges.php
// صفحة عرض شارات الطالب
// آخر تحديث: 2026-03-18
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'badge_functions.php';

if (!isStudent() && !isGuardian()) {
    redirect('login.php');
}

$pageTitle = 'إنجازاتي وشاراتي';
require_once 'includes/header.php';

$student_id = isStudent() ? $_SESSION['user_id'] : (isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0);

if (isGuardian() && $student_id == 0) {
    redirect('guardian_dashboard.php');
}

// تحديث مشاهدة الشارات
if (isStudent()) {
    markBadgesAsSeen($pdo, $student_id);
}

// جلب بيانات الطالب
$student = $pdo->prepare("SELECT name, level FROM students WHERE id = ?");
$student->execute([$student_id]);
$student_data = $student->fetch();

// جلب شارات الطالب
$badges = getStudentBadges($pdo, $student_id);

// جلب إحصائيات الشارات
$stats = getBadgeStats($pdo, $student_id);

// جلب تقدم الشارات
$progress = getBadgeProgress($pdo, $student_id);
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --secondary-light: #dbb87c;
    --gold: #ffd700;
    --silver: #c0c0c0;
    --bronze: #cd7f32;
}

.badges-page {
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
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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

.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    border: 3px solid var(--secondary);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    position: relative;
    z-index: 2;
}

.header-content {
    flex: 1;
    position: relative;
    z-index: 2;
}

.header-content h1 {
    margin: 0 0 5px;
    font-size: 2rem;
}

.header-content p {
    opacity: 0.9;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    color: white;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== قسم الشارات ===== */
.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary);
    margin: 30px 0 20px;
    font-size: 1.3rem;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--secondary);
}

.section-title i {
    color: var(--secondary);
    font-size: 1.5rem;
}

.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* ===== بطاقة الشارة ===== */
.badge-card {
    background: white;
    border-radius: 25px;
    padding: 25px 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.badge-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.badge-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--secondary), var(--gold));
}

.badge-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 3rem;
    color: white;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    transition: 0.3s;
}

.badge-card:hover .badge-icon {
    transform: scale(1.1) rotate(5deg);
}

.badge-name {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 5px;
}

.badge-description {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
    padding: 0 10px;
}

.badge-points {
    display: inline-block;
    background: var(--secondary);
    color: var(--primary);
    padding: 5px 15px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.badge-level {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin: 10px 0;
}

.level-star {
    color: #ddd;
    font-size: 1.2rem;
}

.level-star.filled {
    color: var(--gold);
}

.badge-date {
    color: #999;
    font-size: 0.8rem;
    margin-top: 10px;
}

.new-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: var(--gold);
    color: var(--primary);
    padding: 3px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* ===== شارات قيد التقدم ===== */
.progress-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.progress-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
}

.progress-content {
    flex: 1;
}

.progress-name {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.progress-bar {
    height: 10px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--secondary), var(--gold));
    border-radius: 10px;
    transition: width 0.3s;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    color: #666;
    font-size: 0.85rem;
}

/* ===== حالة عدم وجود شارات ===== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 30px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--primary);
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    margin-bottom: 20px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .badges-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .progress-card {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="badges-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <div class="student-avatar">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="header-content">
            <h1>إنجازاتي وشاراتي</h1>
            <p>
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($student_data['name']); ?>
                <span class="category-badge" style="background: var(--secondary); color: var(--primary); padding: 3px 10px; border-radius: 30px;">
                    <i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student_data['level'] ?? 'مبتدئ'); ?>
                </span>
            </p>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #1e3c3f, #2a5f5a);">
                <i class="fas fa-medal"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_badges']; ?></div>
            <div class="stat-label">إجمالي الشارات</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #c9a96b, #dbb87c);">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_points']; ?></div>
            <div class="stat-label">نقاط الإنجاز</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-number"><?php echo $stats['unseen_badges']; ?></div>
            <div class="stat-label">شارات جديدة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-number">
                <?php 
                $max_level = 0;
                foreach ($stats['level_distribution'] as $level) {
                    if ($level['level'] > $max_level) $max_level = $level['level'];
                }
                echo $max_level > 0 ? 'المستوى ' . $max_level : 'مبتدئ';
                ?>
            </div>
            <div class="stat-label">أعلى مستوى</div>
        </div>
    </div>

    <!-- الشارات الجديدة (غير المقروءة) -->
    <?php 
    $new_badges = array_filter($badges, fn($b) => $b['seen'] == 0);
    if (!empty($new_badges)): 
    ?>
        <div class="section-title">
            <i class="fas fa-gift" style="color: var(--gold);"></i>
            <h3>شارات جديدة! (<?php echo count($new_badges); ?>)</h3>
        </div>

        <div class="badges-grid">
            <?php foreach ($new_badges as $badge): ?>
                <div class="badge-card">
                    <span class="new-badge">جديد!</span>
                    <div class="badge-icon" style="background: <?php echo $badge['color']; ?>;">
                        <i class="fas <?php echo $badge['icon']; ?>"></i>
                    </div>
                    <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                    <div class="badge-description"><?php echo htmlspecialchars($badge['description']); ?></div>
                    <div class="badge-points">+<?php echo $badge['points_reward']; ?> نقطة</div>
                    <div class="badge-level">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star level-star <?php echo $i <= $badge['level'] ? 'filled' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="badge-date">تم الحصول عليها: <?php echo date('Y-m-d', strtotime($badge['earned_date'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- جميع الشارات -->
    <div class="section-title">
        <i class="fas fa-medal"></i>
        <h3>جميع الشارات (<?php echo count($badges); ?>)</h3>
    </div>

    <?php if (empty($badges)): ?>
        <div class="empty-state">
            <i class="fas fa-medal"></i>
            <h3>لا توجد شارات بعد</h3>
            <p>استمر في الحفظ والمشاركة لتحصل على أول شارة لك!</p>
            <a href="student_dashboard.php" class="btn" style="background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none;">
                <i class="fas fa-home"></i> العودة للرئيسية
            </a>
        </div>
    <?php else: ?>
        <div class="badges-grid">
            <?php foreach ($badges as $badge): ?>
                <div class="badge-card">
                    <div class="badge-icon" style="background: <?php echo $badge['color']; ?>;">
                        <i class="fas <?php echo $badge['icon']; ?>"></i>
                    </div>
                    <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                    <div class="badge-description"><?php echo htmlspecialchars($badge['description']); ?></div>
                    <div class="badge-points">+<?php echo $badge['points_reward']; ?> نقطة</div>
                    <div class="badge-level">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star level-star <?php echo $i <= $badge['level'] ? 'filled' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="badge-date"><?php echo date('Y-m-d', strtotime($badge['earned_date'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- شارات قيد التقدم -->
    <?php if (!empty($progress)): ?>
        <div class="section-title">
            <i class="fas fa-spinner"></i>
            <h3>قيد التقدم</h3>
        </div>

        <div class="progress-list">
            <?php foreach ($progress as $item): ?>
                <div class="progress-card">
                    <div class="progress-icon" style="background: <?php echo $item['color']; ?>;">
                        <i class="fas <?php echo $item['icon']; ?>"></i>
                    </div>
                    <div class="progress-content">
                        <div class="progress-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="progress-description"><?php echo htmlspecialchars($item['description']); ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, $item['percentage']); ?>%;"></div>
                        </div>
                        <div class="progress-stats">
                            <span>التقدم: <?php echo $item['current_value']; ?>/<?php echo $item['target']; ?></span>
                            <span><?php echo round($item['percentage'], 1); ?>%</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>