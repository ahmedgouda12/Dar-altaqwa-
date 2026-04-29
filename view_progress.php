<?php
// ============================================
// ملف: view_progress.php - عرض تقدم الطالب
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

// حساب الأجزاء
$total_pages = 0;
$total_parts = 0;
$memorization_text = '';
if ($student_id > 0) {
    $total_pages = getStudentUniquePages($pdo, $student_id);
    $total_parts = calculatePartsFromUniquePages($total_pages);
    $memorization_text = getMemorizationDescription($total_pages, $total_parts);
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
    grid-template-columns: repeat(3, 1fr);
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
.info-box {
    background: #e8f5e9;
    padding: 15px;
    border-radius: 15px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
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
}
@media (max-width: 768px) {
    .stats-cards { grid-template-columns: 1fr; }
    .student-card { flex-direction: column; text-align: center; }
}
</style>

<section class="progress-page">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> تقدم الطالب</h1>
    </div>

    <?php if (!$student_id): ?>
        <div class="alert alert-warning">الرجاء اختيار طالب لعرض تقدمه</div>
    <?php elseif (!$student_info): ?>
        <div class="alert alert-error">الطالب غير موجود</div>
    <?php else: ?>

    <!-- بطاقة معلومات الطالب -->
    <div class="student-card">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="student-avatar"><?php echo mb_substr($student_info['name'], 0, 1, 'UTF-8'); ?></div>
            <div>
                <h2 style="color: #1e3c3f;"><?php echo htmlspecialchars($student_info['name']); ?></h2>
                <p><i class="fas fa-chalkboard-teacher"></i> المعلم: <?php echo htmlspecialchars($student_info['teacher_name'] ?? 'غير محدد'); ?></p>
                <p><i class="fas fa-tag"></i> الفئة: <?php echo $student_info['category'] == 'boy' ? 'أولاد' : ($student_info['category'] == 'girl' ? 'بنات' : ($student_info['category'] == 'child' ? 'أطفال' : 'نساء')); ?></p>
            </div>
        </div>
        <?php if (isTeacher() || isAdmin()): ?>
            <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn" style="background: #ffc107; color:#212529; padding:10px 20px; border-radius:30px; text-decoration:none;">✏️ تعديل</a>
        <?php endif; ?>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-cards">
        <div class="stat-card"><div class="stat-number"><?php echo count($memorized_surahs); ?></div><div>سورة محفوظة</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo number_format($total_pages); ?></div><div>صفحة فريدة</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $total_parts >= 30 ? '🎓' : $total_parts; ?></div><div><?php echo $total_parts >= 30 ? 'ختم القرآن' : 'جزء'; ?></div></div>
    </div>

    <!-- ملخص الحفظ -->
    <div class="info-box">
        <i class="fas fa-chart-line"></i>
        <strong>ملخص الحفظ:</strong> <?php echo $memorization_text; ?>
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
    </div>

    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>