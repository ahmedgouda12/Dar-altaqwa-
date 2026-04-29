<?php
// ============================================
// ملف: mindmap_list.php
// قائمة الخرائط الذهنية
// آخر تحديث: 2026-03-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isStudent() && !isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'قائمة الخرائط الذهنية';
require_once 'includes/header.php';

$student_id = isStudent() ? $_SESSION['user_id'] : null;

// جلب تقدم الطالب في الخرائط
if ($student_id) {
    $progress = $pdo->prepare("
        SELECT surah_number, viewed_at, completed 
        FROM student_mindmap_progress 
        WHERE student_id = ?
    ");
    $progress->execute([$student_id]);
    $progress_data = [];
    foreach ($progress->fetchAll() as $p) {
        $progress_data[$p['surah_number']] = $p;
    }
}

// تجميع السور في أجزاء
$juz_surahs = [];
for ($i = 1; $i <= 30; $i++) {
    $juz_surahs[$i] = [];
}

for ($surah = 1; $surah <= 114; $surah++) {
    $juz = ceil($surah / 4); // تقريبي
    $juz_surahs[$juz][] = $surah;
}
?>

<style>
.mindmap-list-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.juz-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.juz-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e3c3f;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #c9a96b;
    font-size: 1.3rem;
}

.surahs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.surah-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    transition: 0.3s;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
}

.surah-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    border-color: #c9a96b;
}

.surah-card.viewed {
    background: #e8f5e9;
    border-color: #28a745;
}

.surah-number {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}

.surah-name {
    font-weight: 700;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.surah-name-ar {
    font-family: 'Amiri', serif;
    font-size: 1.1rem;
}

.viewed-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    color: #28a745;
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
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
    font-weight: 800;
    color: #1e3c3f;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}
</style>

<section class="mindmap-list-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-map"></i>
            الخرائط الذهنية للسور
        </h1>
        <?php if (isset($progress_data)): ?>
            <div style="margin-right: auto;">
                <i class="fas fa-check-circle" style="color: #28a745;"></i>
                تمت مشاهدة <?php echo count($progress_data); ?> سورة
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($progress_data)): ?>
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($progress_data); ?></div>
                <div class="stat-label">سورة تمت مشاهدتها</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo round((count($progress_data) / 114) * 100); ?>%
                </div>
                <div class="stat-label">نسبة الإنجاز</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo 114 - count($progress_data); ?></div>
                <div class="stat-label">سورة متبقية</div>
            </div>
        </div>
    <?php endif; ?>

    <?php for ($juz = 1; $juz <= 30; $juz++): ?>
        <?php if (!empty($juz_surahs[$juz])): ?>
            <div class="juz-section">
                <div class="juz-title">
                    <i class="fas fa-bookmark" style="color: #c9a96b;"></i>
                    <h3>الجزء <?php echo $juz; ?></h3>
                </div>
                <div class="surahs-grid">
                    <?php foreach ($juz_surahs[$juz] as $surah): 
                        $viewed = isset($progress_data[$surah]);
                    ?>
                        <div class="surah-card <?php echo $viewed ? 'viewed' : ''; ?>" 
                             onclick="window.location.href='surah_mindmap.php?surah=<?php echo $surah; ?>'">
                            <div class="surah-number"><?php echo $surah; ?></div>
                            <div class="surah-name"><?php echo getSurahName($surah); ?></div>
                            <?php if ($viewed): ?>
                                <div class="viewed-badge">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endfor; ?>
</section>

<?php require_once 'includes/footer.php'; ?>