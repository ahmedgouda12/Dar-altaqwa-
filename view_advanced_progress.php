<?php
// ============================================
// ملف: view_progress.php - عرض تقدم الطالب (نسخة محدثة)
// مع حساب دقيق للأجزاء
// ============================================

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'تقدم الطالب';
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// التحقق من الصلاحية
if ($student_id > 0) {
    $can_view = false;
    if (isAdmin()) {
        $can_view = true;
    } elseif (isTeacher()) {
        $stmt = $pdo->prepare("SELECT teacher_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $can_view = ($stmt->fetchColumn() == $_SESSION['user_id']);
    } elseif (isStudent()) {
        $can_view = ($_SESSION['user_id'] == $student_id);
    } elseif (isGuardian()) {
        $stmt = $pdo->prepare("SELECT guardian_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $can_view = ($stmt->fetchColumn() == $_SESSION['user_id']);
    }
    
    if (!$can_view) {
        $_SESSION['error'] = "لا تملك صلاحية رؤية هذا الطالب";
        redirect('dashboard.php');
    }
}

// جلب معلومات الطالب
$student_info = null;
if ($student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as teacher_name 
        FROM students s 
        LEFT JOIN teachers t ON s.teacher_id = t.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch();
}

// جلب السور المحفوظة
$memorized_surahs = [];
if ($student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT surah_number, completed_at 
        FROM student_surah_progress 
        WHERE student_id = ? AND completed = 1
        ORDER BY surah_number
    ");
    $stmt->execute([$student_id]);
    $memorized_surahs = $stmt->fetchAll();
}

// حساب الأجزاء بدقة
$total_pages = 0;
$total_parts_precise = 0;
$full_parts = 0;
$remaining_pages = 0;
$current_part_number = 0;
$current_part_progress = 0;
$total_progress_percent = 0;
$memorization_text = '';

if ($student_id > 0) {
    $total_pages = getStudentUniquePages($pdo, $student_id);
    $total_parts_precise = calculatePartsPrecise($total_pages);
    $full_parts = calculateFullParts($total_pages);
    $remaining_pages = getRemainingPagesToNextPart($total_pages);
    $current_part_number = getCurrentPartNumber($total_pages);
    $current_part_progress = getCurrentPartProgress($total_pages);
    $total_progress_percent = round(($total_pages / 604) * 100, 1);
    $memorization_text = getDetailedMemorizationDescription($total_pages);
}

require_once 'includes/header.php';
?>

<style>
.progress-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 25px;
    margin-bottom: 25px;
}
.student-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.student-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
}
.stats-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.stat-number { font-size: 2rem; font-weight: 800; color: #1e3c3f; }
.stat-label { color: #666; font-size: 0.85rem; margin-top: 5px; }
.info-box {
    background: #e8f5e9;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.info-box i { font-size: 2rem; color: #2e7d32; }
.progress-bar {
    height: 10px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 10px;
    transition: width 0.3s;
}
.surahs-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}
.surah-badge {
    background: #e8f5e9;
    color: #2e7d32;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    transition: 0.3s;
}
.surah-badge:hover {
    background: #c8e6c9;
    transform: translateY(-2px);
}
.parts-detail {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 15px;
    margin: 15px 0;
}
.parts-detail h4 {
    color: #0c5460;
    margin-bottom: 10px;
}
.parts-detail .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #b8daff;
}
.parts-detail .detail-row:last-child {
    border-bottom: none;
}
@media (max-width: 768px) {
    .stats-cards { grid-template-columns: repeat(2, 1fr); }
    .student-card { flex-direction: column; text-align: center; }
    .info-box { flex-direction: column; text-align: center; }
}
@media (max-width: 480px) {
    .stats-cards { grid-template-columns: 1fr; }
}
</style>

<section class="progress-page">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> تقدم الطالب</h1>
    </div>

    <?php if (!$student_id): ?>
        <div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 15px;">
            ⚠️ الرجاء اختيار طالب لعرض تقدمه
        </div>
    <?php elseif (!$student_info): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 15px;">
            ❌ الطالب غير موجود
        </div>
    <?php else: ?>

    <!-- بطاقة معلومات الطالب -->
    <div class="student-card">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="student-avatar"><?php echo mb_substr($student_info['name'], 0, 1, 'UTF-8'); ?></div>
            <div>
                <h2 style="color: #1e3c3f;"><?php echo htmlspecialchars($student_info['name']); ?></h2>
                <p><i class="fas fa-chalkboard-teacher"></i> المعلم: <?php echo htmlspecialchars($student_info['teacher_name'] ?? 'غير محدد'); ?></p>
                <p><i class="fas fa-tag"></i> الفئة: 
                    <?php 
                    $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
                    echo $cat_names[$student_info['category']] ?? $student_info['category'];
                    ?>
                </p>
            </div>
        </div>
        <?php if (isTeacher() || isAdmin()): ?>
            <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn" style="background: #ffc107; color:#212529; padding:10px 20px; border-radius:30px; text-decoration:none;">
                <i class="fas fa-edit"></i> تعديل
            </a>
        <?php endif; ?>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($memorized_surahs); ?></div>
            <div class="stat-label">سورة محفوظة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_pages); ?></div>
            <div class="stat-label">صفحة فريدة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_parts_precise, 2); ?></div>
            <div class="stat-label">أجزاء (بدقة)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $full_parts; ?></div>
            <div class="stat-label">أجزاء كاملة</div>
        </div>
    </div>

    <!-- ملخص الحفظ الدقيق -->
    <div class="info-box">
        <i class="fas fa-chart-line"></i>
        <div style="flex: 1;">
            <strong>ملخص الحفظ الدقيق:</strong><br>
            <?php echo $memorization_text; ?>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $total_progress_percent; ?>%;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                <span>المتقدّم في القرآن الكريم: <?php echo $total_progress_percent; ?>%</span>
                <span><?php echo $total_pages; ?>/604 صفحة</span>
            </div>
        </div>
    </div>

    <!-- تفاصيل الأجزاء -->
    <div class="stat-card" style="text-align: right;">
        <h3><i class="fas fa-layer-group"></i> تفاصيل الأجزاء المحفوظة</h3>
        <div class="parts-detail">
            <div class="detail-row">
                <span><strong>📊 إجمالي الأجزاء (بدقة):</strong></span>
                <span><?php echo number_format($total_parts_precise, 2); ?> جزء</span>
            </div>
            <div class="detail-row">
                <span><strong>✅ الأجزاء الكاملة:</strong></span>
                <span><?php echo $full_parts; ?> جزء</span>
            </div>
            <?php if ($remaining_pages > 0 && $remaining_pages < 20): ?>
            <div class="detail-row">
                <span><strong>📖 الجزء الحالي:</strong></span>
                <span>الجزء <?php echo $current_part_number; ?></span>
            </div>
            <div class="detail-row">
                <span><strong>📄 صفحات الجزء الحالي:</strong></span>
                <span><?php echo $total_pages % 20; ?> / 20 صفحة</span>
            </div>
            <div class="detail-row">
                <span><strong>📈 نسبة إنجاز الجزء الحالي:</strong></span>
                <span>
                    <div style="display: inline-block; width: 100px;">
                        <div class="progress-bar" style="height: 6px;">
                            <div class="progress-fill" style="width: <?php echo $current_part_progress; ?>%;"></div>
                        </div>
                    </div>
                    <?php echo $current_part_progress; ?>%
                </span>
            </div>
            <div class="detail-row">
                <span><strong>⏳ المتبقي لإكمال الجزء:</strong></span>
                <span><?php echo $remaining_pages; ?> صفحة</span>
            </div>
            <?php endif; ?>
            <?php if ($total_parts_precise >= 30): ?>
            <div class="detail-row" style="color: #28a745;">
                <span><strong>🎓 إنجاز عظيم:</strong></span>
                <span>ختم القرآن الكريم كاملاً!</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- السور المحفوظة -->
    <div class="stat-card" style="text-align: right;">
        <h3><i class="fas fa-book-open"></i> السور المحفوظة</h3>
        <div class="surahs-grid">
            <?php foreach ($memorized_surahs as $surah): ?>
                <span class="surah-badge"><?php echo getSurahName($surah['surah_number']); ?></span>
            <?php endforeach; ?>
            <?php if (empty($memorized_surahs)): ?>
                <p style="color:#666;">لم يحفظ أي سورة بعد</p>
            <?php endif; ?>
        </div>
        <?php if (!empty($memorized_surahs)): ?>
            <div style="margin-top: 15px; font-size: 0.8rem; color: #666;">
                إجمالي السور: <?php echo count($memorized_surahs); ?> / 114 سورة
                (<?php echo round((count($memorized_surahs) / 114) * 100, 1); ?>% من القرآن)
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>