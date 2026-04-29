<?php
// ============================================
// ملف: teachers.php - عرض المعلمين (تصميم عصري)
// ============================================

require_once 'config.php';

// معالجة الحذف قبل أي إخراج
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (isAdmin()) {
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: teachers.php');
    exit;
}

if (!isLoggedIn()) redirect('login.php');
$pageTitle = 'المعلمون';
require_once 'includes/header.php';
// عرض رسائل النجاح والخطأ
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// الفلترة حسب الجنس
$filter = isset($_GET['gender']) ? $_GET['gender'] : 'all';

// بناء الاستعلام حسب الدور
$query = "SELECT * FROM teachers";
$params = [];

if (isTeacher()) {
    $query .= " WHERE id = ?";
    $params[] = $_SESSION['user_id'];
} elseif (!isAdmin()) {
    $query .= " WHERE 1=0";
}

if ($filter == 'male') {
    $query .= (strpos($query, 'WHERE') !== false ? ' AND' : ' WHERE') . " gender = 'male'";
} elseif ($filter == 'female') {
    $query .= (strpos($query, 'WHERE') !== false ? ' AND' : ' WHERE') . " gender = 'female'";
}

$query .= " ORDER BY name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// إحصائيات سريعة للإدارة
if (isAdmin()) {
    $totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $maleCount = $pdo->query("SELECT COUNT(*) FROM teachers WHERE gender = 'male'")->fetchColumn();
    $femaleCount = $pdo->query("SELECT COUNT(*) FROM teachers WHERE gender = 'female'")->fetchColumn();
}
?>

<style>
/* ===== تصميم عصري لصفحة المعلمين ===== */
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
    --gray: #6c757d;
    --gray-light: #e9ecef;
    --dark: #2c3e50;
    --light: #f8f9fa;
    
    --gradient-primary: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    --gradient-secondary: linear-gradient(135deg, #c9a96b, #dbb87c);
    --gradient-success: linear-gradient(135deg, #28a745, #20c997);
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 8px rgba(0,0,0,0.1);
    --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
    --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 20px;
    --radius-xl: 30px;
    --radius-2xl: 40px;
    --radius-full: 9999px;
    
    --transition: 0.3s ease;
}

.teachers-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: var(--gradient-primary);
    color: white;
    padding: 30px;
    border-radius: var(--radius-xl);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
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
    margin: 0;
    font-size: 2rem;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.page-header h1 i {
    color: var(--secondary);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.page-header .btn-add {
    background: var(--gradient-secondary);
    color: var(--primary-dark);
    padding: 12px 30px;
    border-radius: var(--radius-full);
    text-decoration: none;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: var(--shadow-md);
    position: relative;
    z-index: 2;
}

.page-header .btn-add:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    filter: brightness(1.05);
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    border: 1px solid rgba(0,0,0,0.05);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-icon.total { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); }
.stat-icon.male { background: #3498db; }
.stat-icon.female { background: #9b59b6; }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1.2;
}

.stat-label {
    color: var(--gray);
    font-size: 0.9rem;
}

/* ===== أزرار التصفية ===== */
.filter-bar {
    background: white;
    border-radius: var(--radius-xl);
    padding: 15px 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-md);
}

.filter-btn {
    padding: 10px 25px;
    border-radius: var(--radius-full);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--gray-light);
}

.filter-btn.active {
    background: var(--gradient-primary);
    color: white;
    border-color: var(--secondary);
}

.filter-btn:hover:not(.active) {
    background: var(--gray-light);
    transform: translateY(-2px);
}

/* ===== شبكة المعلمين ===== */
.teachers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

/* ===== بطاقة المعلم ===== */
.teacher-card {
    background: white;
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    position: relative;
    animation: fadeInUp 0.5s ease;
    border: 1px solid rgba(0,0,0,0.05);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.teacher-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
}

/* شريط علوي ملون */
.teacher-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: var(--gradient-secondary);
}

/* رأس البطاقة */
.teacher-header {
    padding: 25px 20px 0 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.teacher-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
    border: 3px solid var(--secondary);
    box-shadow: var(--shadow-md);
}

.teacher-info {
    flex: 1;
}

.teacher-name {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 5px;
}

.teacher-gender {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 12px;
    border-radius: var(--radius-full);
    font-size: 0.7rem;
    font-weight: 600;
}

.gender-male {
    background: #d4edda;
    color: #155724;
}

.gender-female {
    background: #f8d7da;
    color: #721c24;
}

/* محتوى البطاقة */
.teacher-content {
    padding: 20px;
}

.teacher-detail {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-light);
}

.teacher-detail:last-child {
    border-bottom: none;
}

.teacher-detail i {
    width: 30px;
    color: var(--secondary);
    font-size: 1rem;
}

.detail-label {
    font-weight: 600;
    color: var(--gray);
    min-width: 70px;
    font-size: 0.85rem;
}

.detail-value {
    color: var(--primary);
    font-size: 0.9rem;
    flex: 1;
}

/* أزرار الإجراءات */
.teacher-actions {
    padding: 15px 20px 25px;
    display: flex;
    gap: 10px;
    border-top: 1px solid var(--gray-light);
}

.btn {
    flex: 1;
    padding: 10px;
    border-radius: var(--radius-full);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: var(--transition);
}

.btn-warning {
    background: var(--warning);
    color: #212529;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
    box-shadow: var(--shadow-md);
}

/* حالة عدم وجود بيانات */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 5rem;
    color: var(--gray-light);
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--primary);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 25px;
}

.empty-state .btn-add-empty {
    background: var(--gradient-primary);
    color: white;
    padding: 12px 30px;
    border-radius: var(--radius-full);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.empty-state .btn-add-empty:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

/* تحسينات للهاتف */
@media (max-width: 768px) {
    .teachers-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .teacher-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .teacher-header {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .teacher-name {
        font-size: 1.1rem;
    }
    
    .teacher-detail {
        flex-wrap: wrap;
    }
    
    .detail-label {
        width: 100%;
    }
}
</style>

<section class="teachers-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-chalkboard-teacher"></i>
            المعلمون
        </h1>
        <?php if (isAdmin()): ?>
            <a href="add_teacher.php" class="btn-add">
                <i class="fas fa-user-plus"></i>
                إضافة معلم جديد
            </a>
        <?php endif; ?>
    </div>

    <?php if (isAdmin()): ?>
        <!-- إحصائيات سريعة للإدارة -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $totalTeachers; ?></div>
                    <div class="stat-label">إجمالي المعلمين</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon male">
                    <i class="fas fa-male"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $maleCount; ?></div>
                    <div class="stat-label">معلمون</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon female">
                    <i class="fas fa-female"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $femaleCount; ?></div>
                    <div class="stat-label">معلمات</div>
                </div>
            </div>
        </div>

        <!-- أزرار التصفية -->
        <div class="filter-bar">
            <a href="?gender=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> الكل
            </a>
            <a href="?gender=male" class="filter-btn <?php echo $filter == 'male' ? 'active' : ''; ?>">
                <i class="fas fa-male"></i> معلمون
            </a>
            <a href="?gender=female" class="filter-btn <?php echo $filter == 'female' ? 'active' : ''; ?>">
                <i class="fas fa-female"></i> معلمات
            </a>
        </div>
    <?php endif; ?>

    <?php if (count($teachers) > 0): ?>
        <div class="teachers-grid">
            <?php foreach ($teachers as $teacher): ?>
                <div class="teacher-card" data-aos="fade-up">
                    <div class="teacher-header">
                        <div class="teacher-avatar">
                            <?php echo mb_substr($teacher['name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div class="teacher-info">
                            <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                            <span class="teacher-gender gender-<?php echo $teacher['gender']; ?>">
                                <i class="fas <?php echo $teacher['gender'] == 'male' ? 'fa-male' : 'fa-female'; ?>"></i>
                                <?php echo $teacher['gender'] == 'male' ? 'معلم' : 'معلمة'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="teacher-content">
                        <div class="teacher-detail">
                            <i class="fas fa-tag"></i>
                            <span class="detail-label">التخصص:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($teacher['specialization'] ?: 'غير محدد'); ?></span>
                        </div>
                        <div class="teacher-detail">
                            <i class="fas fa-clock"></i>
                            <span class="detail-label">المواعيد:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($teacher['schedule'] ?: 'غير محدد'); ?></span>
                        </div>
                        <div class="teacher-detail">
                            <i class="fas fa-phone"></i>
                            <span class="detail-label">الهاتف:</span>
                            <span class="detail-value" dir="ltr"><?php echo htmlspecialchars($teacher['phone'] ?: 'لا يوجد'); ?></span>
                        </div>
                        <div class="teacher-detail">
                            <i class="fas fa-calendar-week"></i>
                            <span class="detail-label">الحصص الأسبوعية:</span>
                            <span class="detail-value"><?php echo $teacher['expected_weekly_sessions']; ?> حصة</span>
                        </div>
                    </div>
                    
                    <?php if (isAdmin()): ?>
                        <div class="teacher-actions">
                            <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <a href="?delete=<?php echo $teacher['id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المعلم؟')">
                                <i class="fas fa-trash"></i> حذف
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <h3>لا يوجد معلمون بعد</h3>
            <p>قم بإضافة أول معلم لبدء رحلة التعليم</p>
            <?php if (isAdmin()): ?>
                <a href="add_teacher.php" class="btn-add-empty">
                    <i class="fas fa-user-plus"></i>
                    إضافة معلم جديد
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<!-- إضافة AOS للأنيميشن -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 600,
        once: true,
        offset: 50
    });
</script>

<?php require_once 'includes/footer.php'; ?>