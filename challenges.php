<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'surah_data.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تحديات الحفظ';
require_once 'includes/header.php';

// تصنيفات الفئات
$categories = [
    'boy' => ['name' => 'الأولاد', 'icon' => 'fa-male', 'color' => '#3498db'],
    'girl' => ['name' => 'البنات', 'icon' => 'fa-female', 'color' => '#9b59b6'],
    'child' => ['name' => 'الأطفال', 'icon' => 'fa-child', 'color' => '#f39c12'],
    'woman' => ['name' => 'النساء', 'icon' => 'fa-female', 'color' => '#6f42c1']
];

// تحديد فئة المعلم (إذا كان مستخدم معلم)
$teacher_category_filter = '';
$allowed_categories = [];
$teacher_gender = '';

if (isTeacher()) {
    // جلب جنس المعلم
    $stmt = $pdo->prepare("SELECT gender FROM teachers WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $teacher_gender = $stmt->fetchColumn();
    
    // تحديد فئة الطلاب حسب جنس المعلم
    if ($teacher_gender == 'male') {
        $teacher_category_filter = "category = 'boy'";
        $allowed_categories = ['boy'];
    } else {
        $teacher_category_filter = "category IN ('girl', 'child', 'woman')";
        $allowed_categories = ['girl', 'child', 'woman'];
    }
}

// ============================================
// معالجة إنشاء تحدي جديد
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_challenge'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $challenge_type = $_POST['challenge_type'] ?? 'individual';
    
    // التحقق من صلاحية المعلم للفئة
    if (isTeacher()) {
        if ($teacher_gender == 'male' && $category != 'boy') {
            $_SESSION['error'] = '❌ لا يمكنك إنشاء تحدي لهذه الفئة';
            header("Location: challenges.php");
            exit;
        }
        if ($teacher_gender == 'female' && !in_array($category, ['girl', 'child', 'woman'])) {
            $_SESSION['error'] = '❌ لا يمكنك إنشاء تحدي لهذه الفئة';
            header("Location: challenges.php");
            exit;
        }
    }
    
    // نوع المقدار المحفوظ
    $measurement_type = $_POST['measurement_type'];
    
    $surah_from = ($measurement_type == 'surah') ? (int)$_POST['surah_from'] : null;
    $surah_to = ($measurement_type == 'surah') ? (int)$_POST['surah_to'] : null;
    $ayah_from = ($measurement_type == 'ayah') ? (int)$_POST['ayah_from'] : null;
    $ayah_to = ($measurement_type == 'ayah') ? (int)$_POST['ayah_to'] : null;
    $pages_from = ($measurement_type == 'page') ? (int)$_POST['pages_from'] : null;
    $pages_to = ($measurement_type == 'page') ? (int)$_POST['pages_to'] : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO memorization_challenges 
        (title, description, category, challenge_type, start_date, end_date, 
         surah_from, surah_to, ayah_from, ayah_to, pages_from, pages_to, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
    ");
    $stmt->execute([
        $title, $description, $category, $challenge_type, $start_date, $end_date,
        $surah_from, $surah_to, $ayah_from, $ayah_to, $pages_from, $pages_to,
        $_SESSION['user_id']
    ]);
    
    $_SESSION['success'] = '✅ تم إنشاء التحدي بنجاح';
    header("Location: challenges.php");
    exit;
}

// ============================================
// تسجيل طالب في تحدي
// ============================================
if (isset($_GET['register']) && isset($_GET['student_id']) && isTeacher()) {
    $challenge_id = (int)$_GET['register'];
    $student_id = (int)$_GET['student_id'];
    
    // التحقق من أن الطالب تابع لهذا المعلم
    $check = $pdo->prepare("SELECT id, category FROM students WHERE id = ? AND teacher_id = ?");
    $check->execute([$student_id, $_SESSION['user_id']]);
    $student = $check->fetch();
    
    if ($student) {
        // التحقق من أن التحدي مناسب لفئة الطالب
        $challenge = $pdo->prepare("SELECT category FROM memorization_challenges WHERE id = ?");
        $challenge->execute([$challenge_id]);
        $challenge_cat = $challenge->fetchColumn();
        
        if ($challenge_cat != $student['category']) {
            $_SESSION['error'] = '❌ هذا التحدي غير مناسب لفئة الطالب';
        } else {
            // التحقق من عدم تسجيل الطالب مسبقاً
            $exists = $pdo->prepare("SELECT id FROM challenge_participants WHERE challenge_id = ? AND student_id = ?");
            $exists->execute([$challenge_id, $student_id]);
            
            if (!$exists->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO challenge_participants (challenge_id, student_id, teacher_id)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$challenge_id, $student_id, $_SESSION['user_id']]);
                $_SESSION['success'] = '✅ تم تسجيل الطالب في التحدي';
            } else {
                $_SESSION['error'] = '❌ الطالب مسجل بالفعل في هذا التحدي';
            }
        }
    }
    header("Location: challenges.php");
    exit;
}

// ============================================
// تحديث نتيجة طالب
// ============================================
if (isset($_POST['update_result']) && isTeacher()) {
    $challenge_id = (int)$_POST['challenge_id'];
    $student_id = (int)$_POST['student_id'];
    $memorized_ayahs = (int)$_POST['memorized_ayahs'];
    $mistakes_count = (int)$_POST['mistakes_count'];
    $completion_time = (int)$_POST['completion_time'];
    $review_quality = $_POST['review_quality'];
    
    // التحقق من أن الطالب تابع لهذا المعلم
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
    $check->execute([$student_id, $_SESSION['user_id']]);
    
    if ($check->fetch()) {
        // حساب النتيجة التقييمية
        $ayah_score = $memorized_ayahs * 10;
        $mistakes_penalty = $mistakes_count * 5;
        $time_score = ($completion_time > 0) ? (100 / $completion_time) * 10 : 0;
        
        $quality_scores = [
            'excellent' => 50,
            'good' => 40,
            'average' => 30,
            'poor' => 20
        ];
        
        $total_score = $ayah_score + $time_score + $quality_scores[$review_quality] - $mistakes_penalty;
        $total_score = max(0, $total_score);
        
        $stmt = $pdo->prepare("
            INSERT INTO challenge_results 
            (challenge_id, student_id, memorized_ayahs, mistakes_count, review_quality, completion_time, evaluation_score, evaluated_by, evaluated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            memorized_ayahs = VALUES(memorized_ayahs),
            mistakes_count = VALUES(mistakes_count),
            review_quality = VALUES(review_quality),
            completion_time = VALUES(completion_time),
            evaluation_score = VALUES(evaluation_score),
            evaluated_by = VALUES(evaluated_by),
            evaluated_at = VALUES(evaluated_at)
        ");
        $stmt->execute([$challenge_id, $student_id, $memorized_ayahs, $mistakes_count, $review_quality, $completion_time, $total_score, $_SESSION['user_id']]);
        
        // تحديث الترتيب
        updateChallengeRanking($pdo, $challenge_id);
        
        $_SESSION['success'] = '✅ تم تحديث نتيجة الطالب';
    }
    header("Location: challenges.php");
    exit;
}

// ============================================
// دالة تحديث ترتيب الطلاب في التحدي
// ============================================
function updateChallengeRanking($pdo, $challenge_id) {
    $results = $pdo->prepare("
        SELECT id, evaluation_score 
        FROM challenge_results 
        WHERE challenge_id = ? 
        ORDER BY evaluation_score DESC
    ");
    $results->execute([$challenge_id]);
    
    $rank = 1;
    $update = $pdo->prepare("UPDATE challenge_results SET rank_position = ? WHERE id = ?");
    
    foreach ($results as $result) {
        $update->execute([$rank, $result['id']]);
        $rank++;
    }
}

// ============================================
// جلب التحديات حسب الفئة والصلاحية
// ============================================
$active_challenges = [];
$categories_to_show = isAdmin() ? array_keys($categories) : $allowed_categories;

foreach ($categories_to_show as $cat) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM challenge_participants WHERE challenge_id = c.id) as participants_count
        FROM memorization_challenges c
        WHERE c.category = ? AND c.status = 'active'
        ORDER BY c.end_date ASC
    ");
    $stmt->execute([$cat]);
    $active_challenges[$cat] = $stmt->fetchAll();
}

// جلب التحديات المنتهية
$completed_challenges = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM challenge_participants WHERE challenge_id = c.id) as participants_count
    FROM memorization_challenges c
    WHERE c.status = 'completed'
    ORDER BY c.end_date DESC
    LIMIT 10
");
$completed_challenges->execute();
$completed_challenges = $completed_challenges->fetchAll();

// جلب طلاب المعلم للتسجيل في التحديات
$teacher_students = [];
if (isTeacher()) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.category 
        FROM students s 
        WHERE s.teacher_id = ? 
        ORDER BY s.category, s.name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $teacher_students = $stmt->fetchAll();
}

// عرض رسائل النجاح/الخطأ
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
/* ===== تصميم صفحة التحديات ===== */
.challenges-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* رأس الصفحة */
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
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
    font-size: 2.2rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: #c9a96b;
}

.create-btn {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 15px 30px;
    border-radius: 60px;
    text-decoration: none;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: 0.3s;
    border: none;
    cursor: pointer;
}

.create-btn:hover {
    background: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

/* رسائل التنبيه */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

/* تبويبات الفئات */
.category-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
    justify-content: center;
}

.category-tab {
    padding: 15px 30px;
    border-radius: 60px;
    text-decoration: none;
    font-weight: bold;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: 0.3s;
    border: 2px solid transparent;
    cursor: pointer;
}

.category-tab.active {
    border-color: #c9a96b;
    transform: scale(1.05);
}

.category-tab:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

/* بطاقات التحديات */
.challenges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.challenge-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid #eee;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.challenge-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.challenge-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #1e3c3f, #c9a96b);
}

.challenge-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.challenge-category {
    padding: 6px 15px;
    border-radius: 30px;
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.challenge-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #e2e3e5;
    color: #383d41;
}

.challenge-title {
    font-size: 1.4rem;
    font-weight: bold;
    color: #1e3c3f;
    margin-bottom: 10px;
}

.challenge-description {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
}

.challenge-details {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px dashed #dee2e6;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-icon {
    width: 30px;
    height: 30px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e3c3f;
}

.detail-label {
    font-weight: 600;
    color: #495057;
    min-width: 100px;
}

.detail-value {
    color: #1e3c3f;
    font-weight: 500;
}

/* لوحة المتصدرين */
.leaderboard {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #eee;
}

.leaderboard-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e3c3f;
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: bold;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.leaderboard-item:last-child {
    border-bottom: none;
}

.leaderboard-rank {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.rank-1 { background: gold; }
.rank-2 { background: silver; }
.rank-3 { background: #cd7f32; }
.rank-other { background: #6c757d; }

.leaderboard-name {
    flex: 1;
    font-weight: 600;
    color: #1e3c3f;
}

.leaderboard-score {
    font-weight: bold;
    color: #28a745;
}

.leaderboard-mistakes {
    color: #dc3545;
    font-size: 0.9rem;
}

/* أزرار الإجراءات */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 20px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}

.btn-primary {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

/* نافذة منبثقة */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 30px;
    width: 90%;
    max-width: 600px;
    padding: 35px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.modal-header h3 {
    color: #1e3c3f;
    font-size: 1.8rem;
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #999;
    transition: 0.3s;
}

.close-btn:hover {
    color: #dc3545;
    transform: rotate(90deg);
}

/* نموذج */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.form-group label i {
    color: #c9a96b;
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    font-size: 1rem;
    transition: 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #c9a96b;
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.radio-group {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* حالة عدم وجود بيانات */
.empty-state {
    text-align: center;
    padding: 60px;
    background: #f8f9fa;
    border-radius: 20px;
}

.empty-state i {
    font-size: 60px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e3c3f;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    margin-bottom: 20px;
}

/* تحسينات للهاتف */
@media (max-width: 768px) {
    .challenges-grid {
        grid-template-columns: 1fr;
    }
    
    .category-tab {
        width: 100%;
        justify-content: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        padding: 20px;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .create-btn {
        width: 100%;
    }
}
</style>

<section class="challenges-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-trophy"></i>
            تحديات الحفظ
        </h1>
        <button class="create-btn" onclick="openCreateModal()">
            <i class="fas fa-plus-circle"></i>
            إنشاء تحدي جديد
        </button>
    </div>

    <!-- عرض رسائل النجاح/الخطأ -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- تبويبات الفئات -->
    <div class="category-tabs">
        <?php 
        $display_categories = isAdmin() ? $categories : array_intersect_key($categories, array_flip($allowed_categories));
        foreach ($display_categories as $key => $cat): 
        ?>
            <div class="category-tab" style="background: <?php echo $cat['color']; ?>;" onclick="showCategory('<?php echo $key; ?>')">
                <i class="fas <?php echo $cat['icon']; ?>"></i>
                <?php echo $cat['name']; ?>
                <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 30px;">
                    <?php echo count($active_challenges[$key] ?? []); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- عرض التحديات حسب الفئة -->
    <?php foreach ($categories_to_show as $cat_key): ?>
        <div id="category-<?php echo $cat_key; ?>" class="category-section" style="display: none;">
            <h2 style="color: <?php echo $categories[$cat_key]['color']; ?>; margin-bottom: 20px;">
                <i class="fas <?php echo $categories[$cat_key]['icon']; ?>"></i>
                تحديات <?php echo $categories[$cat_key]['name']; ?>
            </h2>

            <?php if (empty($active_challenges[$cat_key])): ?>
                <div class="empty-state">
                    <i class="fas fa-trophy"></i>
                    <h3>لا توجد تحديات نشطة</h3>
                    <p>يمكنك إنشاء أول تحدي لهذه الفئة</p>
                    <button class="btn btn-primary" onclick="openCreateModal('<?php echo $cat_key; ?>')">
                        <i class="fas fa-plus-circle"></i> إنشاء تحدي
                    </button>
                </div>
            <?php else: ?>
                <div class="challenges-grid">
                    <?php foreach ($active_challenges[$cat_key] as $challenge): 
                        // جلب أفضل 5 نتائج لهذا التحدي
                        $results = $pdo->prepare("
                            SELECT cr.*, s.name as student_name
                            FROM challenge_results cr
                            JOIN students s ON cr.student_id = s.id
                            WHERE cr.challenge_id = ?
                            ORDER BY cr.rank_position ASC
                            LIMIT 5
                        ");
                        $results->execute([$challenge['id']]);
                        $top_results = $results->fetchAll();
                    ?>
                        <div class="challenge-card">
                            <div class="challenge-header">
                                <span class="challenge-category" style="background: <?php echo $categories[$cat_key]['color']; ?>;">
                                    <i class="fas <?php echo $categories[$cat_key]['icon']; ?>"></i>
                                    <?php echo $categories[$cat_key]['name']; ?>
                                </span>
                                <span class="challenge-status status-active">
                                    <i class="fas fa-clock"></i> نشط
                                </span>
                            </div>

                            <div class="challenge-title"><?php echo htmlspecialchars($challenge['title']); ?></div>
                            
                            <?php if ($challenge['description']): ?>
                                <div class="challenge-description"><?php echo nl2br(htmlspecialchars($challenge['description'])); ?></div>
                            <?php endif; ?>

                            <div class="challenge-details">
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="fas fa-calendar"></i></span>
                                    <span class="detail-label">المدة:</span>
                                    <span class="detail-value">
                                        من <?php echo $challenge['start_date']; ?> 
                                        إلى <?php echo $challenge['end_date']; ?>
                                    </span>
                                </div>
                                
                                <?php if ($challenge['surah_from']): ?>
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="fas fa-book-open"></i></span>
                                    <span class="detail-label">السور:</span>
                                    <span class="detail-value">
                                        من سورة <?php echo getSurahName($challenge['surah_from']); ?>
                                        إلى سورة <?php echo getSurahName($challenge['surah_to']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($challenge['pages_from']): ?>
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="fas fa-file"></i></span>
                                    <span class="detail-label">الصفحات:</span>
                                    <span class="detail-value">
                                        من صفحة <?php echo $challenge['pages_from']; ?>
                                        إلى صفحة <?php echo $challenge['pages_to']; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="fas fa-users"></i></span>
                                    <span class="detail-label">المشاركون:</span>
                                    <span class="detail-value"><?php echo $challenge['participants_count']; ?> طالب</span>
                                </div>
                            </div>

                            <?php if (!empty($top_results)): ?>
                                <div class="leaderboard">
                                    <div class="leaderboard-title">
                                        <i class="fas fa-crown" style="color: gold;"></i>
                                        أفضل 5 متسابقين
                                    </div>
                                    <?php foreach ($top_results as $result): ?>
                                        <div class="leaderboard-item">
                                            <div class="leaderboard-rank rank-<?php echo $result['rank_position'] <= 3 ? $result['rank_position'] : 'other'; ?>">
                                                <?php echo $result['rank_position']; ?>
                                            </div>
                                            <div class="leaderboard-name"><?php echo htmlspecialchars($result['student_name']); ?></div>
                                            <div class="leaderboard-score"><?php echo $result['evaluation_score']; ?> نقطة</div>
                                            <div class="leaderboard-mistakes">
                                                <i class="fas fa-exclamation-circle"></i> <?php echo $result['mistakes_count']; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isTeacher()): ?>
                                <div class="action-buttons">
                                    <button class="btn btn-success" style="flex:1;" onclick="openRegisterModal(<?php echo $challenge['id']; ?>, '<?php echo $cat_key; ?>')">
                                        <i class="fas fa-user-plus"></i> تسجيل طالب
                                    </button>
                                    <button class="btn btn-info" style="flex:1;" onclick="openResultModal(<?php echo $challenge['id']; ?>)">
                                        <i class="fas fa-star"></i> تقييم
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- التحديات المنتهية -->
    <?php if (!empty($completed_challenges)): ?>
        <h2 style="color: #6c757d; margin: 40px 0 20px;">
            <i class="fas fa-history"></i>
            التحديات السابقة
        </h2>
        <div class="challenges-grid">
            <?php foreach ($completed_challenges as $challenge): 
                $cat = $categories[$challenge['category']];
            ?>
                <div class="challenge-card" style="opacity: 0.9;">
                    <div class="challenge-header">
                        <span class="challenge-category" style="background: <?php echo $cat['color']; ?>;">
                            <i class="fas <?php echo $cat['icon']; ?>"></i>
                            <?php echo $cat['name']; ?>
                        </span>
                        <span class="challenge-status status-completed">
                            <i class="fas fa-check-circle"></i> منتهي
                        </span>
                    </div>
                    <div class="challenge-title"><?php echo htmlspecialchars($challenge['title']); ?></div>
                    <div class="challenge-details">
                        <div class="detail-row">
                            <i class="fas fa-users"></i>
                            <span>عدد المشاركين: <?php echo $challenge['participants_count']; ?></span>
                        </div>
                    </div>
                    <a href="challenge_results.php?id=<?php echo $challenge['id']; ?>" class="btn btn-info" style="width:100%;">
                        <i class="fas fa-chart-bar"></i> عرض النتائج
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- نافذة إنشاء تحدي جديد -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: #28a745;"></i> إنشاء تحدي جديد</h3>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>

            <form method="post">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> عنوان التحدي</label>
                    <input type="text" name="title" class="form-control" required placeholder="مثال: تحدي حفظ جزء عم">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> وصف التحدي (اختياري)</label>
                    <textarea name="description" class="form-control" placeholder="شرح تفاصيل التحدي..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> الفئة</label>
                        <?php if (isAdmin()): ?>
                            <select name="category" class="form-control" required id="challengeCategory">
                                <option value="">اختر الفئة</option>
                                <option value="boy">الأولاد</option>
                                <option value="girl">البنات</option>
                                <option value="child">الأطفال</option>
                                <option value="woman">النساء</option>
                            </select>
                        <?php else: ?>
                            <select name="category" class="form-control" required>
                                <?php if ($teacher_gender == 'male'): ?>
                                    <option value="boy" selected>الأولاد</option>
                                <?php else: ?>
                                    <option value="girl">البنات</option>
                                    <option value="child">الأطفال</option>
                                    <option value="woman">النساء</option>
                                <?php endif; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> نوع التحدي</label>
                        <select name="challenge_type" class="form-control">
                            <option value="individual">فردي</option>
                            <option value="group">جماعي</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-start"></i> تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-end"></i> تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-ruler"></i> نوع المقدار</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="measurement_type" value="surah" checked onchange="toggleMeasurementFields()"> سور
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="measurement_type" value="ayah" onchange="toggleMeasurementFields()"> آيات
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="measurement_type" value="page" onchange="toggleMeasurementFields()"> صفحات
                        </label>
                    </div>
                </div>

                <!-- حقول السور -->
                <div class="form-row" id="surahFields">
                    <div class="form-group">
                        <label>من سورة</label>
                        <select name="surah_from" class="form-control">
                            <option value="">اختر</option>
                            <?php for ($i = 1; $i <= 114; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i . '. ' . getSurahName($i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>إلى سورة</label>
                        <select name="surah_to" class="form-control">
                            <option value="">اختر</option>
                            <?php for ($i = 1; $i <= 114; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i . '. ' . getSurahName($i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- حقول الآيات (مخفية ابتداءً) -->
                <div class="form-row" id="ayahFields" style="display: none;">
                    <div class="form-group">
                        <label>من آية</label>
                        <input type="number" name="ayah_from" class="form-control" placeholder="رقم الآية">
                    </div>
                    <div class="form-group">
                        <label>إلى آية</label>
                        <input type="number" name="ayah_to" class="form-control" placeholder="رقم الآية">
                    </div>
                </div>

                <!-- حقول الصفحات (مخفية ابتداءً) -->
                <div class="form-row" id="pageFields" style="display: none;">
                    <div class="form-group">
                        <label>من صفحة</label>
                        <input type="number" name="pages_from" class="form-control" min="1" max="604" placeholder="1-604">
                    </div>
                    <div class="form-group">
                        <label>إلى صفحة</label>
                        <input type="number" name="pages_to" class="form-control" min="1" max="604" placeholder="1-604">
                    </div>
                </div>

                <div class="action-buttons" style="margin-top: 30px;">
                    <button type="button" class="btn" onclick="closeCreateModal()" style="flex:1; background:#6c757d; color:white;">إلغاء</button>
                    <button type="submit" name="create_challenge" class="btn btn-primary" style="flex:2;">إنشاء التحدي</button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة تسجيل طالب في تحدي -->
    <div class="modal" id="registerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus" style="color: #28a745;"></i> تسجيل طالب في التحدي</h3>
                <button class="close-btn" onclick="closeRegisterModal()">&times;</button>
            </div>

            <form method="get">
                <input type="hidden" name="register" id="registerChallengeId">
                
                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> اختر الطالب</label>
                    <select name="student_id" class="form-control" required id="studentSelect">
                        <option value="">-- اختر --</option>
                        <?php foreach ($teacher_students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-category="<?php echo $s['category']; ?>">
                                <?php echo htmlspecialchars($s['name']); ?> (<?php echo $categories[$s['category']]['name']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="action-buttons">
                    <button type="button" class="btn" onclick="closeRegisterModal()" style="flex:1; background:#6c757d; color:white;">إلغاء</button>
                    <button type="submit" class="btn btn-success" style="flex:2;">تسجيل في التحدي</button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة تقييم طالب -->
    <div class="modal" id="resultModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-star" style="color: #ffc107;"></i> تقييم الطالب</h3>
                <button class="close-btn" onclick="closeResultModal()">&times;</button>
            </div>

            <form method="post">
                <input type="hidden" name="challenge_id" id="resultChallengeId">
                
                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> اختر الطالب</label>
                    <select name="student_id" class="form-control" required id="resultStudentId">
                        <option value="">-- اختر --</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-book-open"></i> الآيات المحفوظة</label>
                        <input type="number" name="memorized_ayahs" class="form-control" required min="0">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-exclamation-triangle"></i> عدد الأخطاء</label>
                        <input type="number" name="mistakes_count" class="form-control" required min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> الوقت (بالساعات)</label>
                        <input type="number" name="completion_time" class="form-control" required min="0" step="0.5">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-quality"></i> جودة المراجعة</label>
                        <select name="review_quality" class="form-control">
                            <option value="excellent">ممتاز</option>
                            <option value="good">جيد جداً</option>
                            <option value="average">متوسط</option>
                            <option value="poor">ضعيف</option>
                        </select>
                    </div>
                </div>

                <div style="background: #e7f3ff; padding: 15px; border-radius: 15px; margin: 20px 0;">
                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                    <strong>طريقة احتساب النقاط:</strong>
                    <ul style="margin-top: 10px; margin-right: 20px;">
                        <li>كل آية محفوظة = 10 نقاط</li>
                        <li>كل خطأ = خصم 5 نقاط</li>
                        <li>سرعة الحفظ تضيف نقاط إضافية</li>
                        <li>جودة المراجعة تضيف 20-50 نقطة</li>
                    </ul>
                </div>

                <div class="action-buttons">
                    <button type="button" class="btn" onclick="closeResultModal()" style="flex:1; background:#6c757d; color:white;">إلغاء</button>
                    <button type="submit" name="update_result" class="btn btn-primary" style="flex:2;">حفظ التقييم</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
// إظهار الفئة الأولى افتراضياً
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($categories_to_show)): ?>
    showCategory('<?php echo $categories_to_show[0]; ?>');
    <?php endif; ?>
});

function showCategory(category) {
    // إخفاء كل الفئات
    document.querySelectorAll('.category-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // إظهار الفئة المحددة
    document.getElementById('category-' + category).style.display = 'block';
    
    // تحديث التبويبات
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
}

// تبديل حقول القياس
function toggleMeasurementFields() {
    const type = document.querySelector('input[name="measurement_type"]:checked').value;
    
    document.getElementById('surahFields').style.display = type === 'surah' ? 'grid' : 'none';
    document.getElementById('ayahFields').style.display = type === 'ayah' ? 'grid' : 'none';
    document.getElementById('pageFields').style.display = type === 'page' ? 'grid' : 'none';
}

// نوافذ الإدخال
function openCreateModal(category = '') {
    if (category) {
        const categorySelect = document.getElementById('challengeCategory');
        if (categorySelect) {
            categorySelect.value = category;
        }
    }
    document.getElementById('createModal').classList.add('active');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
}

function openRegisterModal(challengeId, category) {
    document.getElementById('registerChallengeId').value = challengeId;
    
    // فلترة الطلاب حسب الفئة
    const studentSelect = document.getElementById('studentSelect');
    Array.from(studentSelect.options).forEach(option => {
        if (option.value) {
            const studentCategory = option.getAttribute('data-category');
            option.style.display = studentCategory === category ? 'block' : 'none';
        }
    });
    
    document.getElementById('registerModal').classList.add('active');
}

function closeRegisterModal() {
    document.getElementById('registerModal').classList.remove('active');
}

function openResultModal(challengeId) {
    document.getElementById('resultChallengeId').value = challengeId;
    
    // جلب طلاب هذا التحدي
    fetch(`get_challenge_students.php?challenge_id=${challengeId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('resultStudentId');
            select.innerHTML = '<option value="">-- اختر --</option>';
            data.forEach(student => {
                select.innerHTML += `<option value="${student.id}">${student.name}</option>`;
            });
        });
    
    document.getElementById('resultModal').classList.add('active');
}

function closeResultModal() {
    document.getElementById('resultModal').classList.remove('active');
}

// إغلاق النوافذ بالنقر خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>