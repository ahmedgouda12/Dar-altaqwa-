<?php
// ============================================
// ملف: students.php - عرض الطلاب مع فلترة حسب المعلم
// آخر تحديث: 2026-04-06
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

// معالجة الحذف
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (isAdmin()) {
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        $_SESSION['success_message'] = '✅ تم حذف الطالب بنجاح';
    } elseif (isTeacher()) {
        $pdo->prepare("DELETE FROM students WHERE id = ? AND teacher_id = ?")->execute([$id, $_SESSION['user_id']]);
        $_SESSION['success_message'] = '✅ تم حذف الطالب بنجاح';
    }
    header('Location: students.php');
    exit;
}

$pageTitle = 'الطلاب';
require_once 'includes/header.php';

// ============================================
// فلترة الطلاب حسب المعلم (للمدير فقط)
// ============================================
$filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$special_filter = isset($_GET['special']) ? $_GET['special'] : 'all';
$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// جلب قائمة المعلمين للفلترة (للمدير فقط)
if (isAdmin()) {
    $teachers_list = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
}

// بناء الاستعلام
$query = "SELECT s.*, t.name as teacher_name FROM students s LEFT JOIN teachers t ON s.teacher_id = t.id";
$params = [];

if (isTeacher()) {
    $query .= " WHERE s.teacher_id = ?";
    $params[] = $_SESSION['user_id'];
} elseif (isAdmin() && $selected_teacher > 0) {
    $query .= " WHERE s.teacher_id = ?";
    $params[] = $selected_teacher;
} elseif (!isAdmin() && !isTeacher()) {
    $query .= " WHERE 1=0";
}

if ($filter !== 'all' && strpos($query, 'WHERE') !== false) {
    $query .= " AND s.category = ?";
    $params[] = $filter;
} elseif ($filter !== 'all') {
    $query .= " WHERE s.category = ?";
    $params[] = $filter;
}

if ($special_filter === 'special') {
    if (strpos($query, 'WHERE') !== false) {
        $query .= " AND s.is_special = 1";
    } else {
        $query .= " WHERE s.is_special = 1";
    }
} elseif ($special_filter === 'normal') {
    if (strpos($query, 'WHERE') !== false) {
        $query .= " AND (s.is_special = 0 OR s.is_special IS NULL)";
    } else {
        $query .= " WHERE (s.is_special = 0 OR s.is_special IS NULL)";
    }
}

$query .= " ORDER BY s.is_special DESC, s.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// جلب اسم المعلم المحدد (للعرض)
$current_teacher_name = null;
if (isAdmin() && $selected_teacher > 0) {
    $teacher_stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
    $teacher_stmt->execute([$selected_teacher]);
    $current_teacher_name = $teacher_stmt->fetchColumn();
}

// إحصائيات (للمدير فقط)
if (isAdmin()) {
    $stats = $pdo->query("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN category = 'boy' THEN 1 ELSE 0 END) as boys,
               SUM(CASE WHEN category = 'girl' THEN 1 ELSE 0 END) as girls,
               SUM(CASE WHEN category = 'child' THEN 1 ELSE 0 END) as children,
               SUM(CASE WHEN category = 'woman' THEN 1 ELSE 0 END) as women,
               SUM(CASE WHEN is_special = 1 THEN 1 ELSE 0 END) as special_count
        FROM students
    ")->fetch();
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

function getCategoryName($cat) {
    $map = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
    return $map[$cat] ?? $cat;
}

function getCategoryColor($cat) {
    $map = ['boy' => '#3498db', 'girl' => '#9b59b6', 'child' => '#f39c12', 'woman' => '#e84342'];
    return $map[$cat] ?? '#1e3c3f';
}

function getCategoryIcon($cat) {
    $map = ['boy' => 'fa-male', 'girl' => 'fa-female', 'child' => 'fa-child', 'woman' => 'fa-female'];
    return $map[$cat] ?? 'fa-user';
}
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
    --whatsapp: #25d366;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.students-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f7fa;
    min-height: 100vh;
}

/* ===== رأس الصفحة ===== */
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
    position: relative;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
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

.header-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}

.header-btn {
    padding: 12px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.15);
    color: white;
    transition: all 0.3s;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,0.2);
}

.header-btn:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-3px);
}

/* ===== إحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s;
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
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e3c3f;
}

.stat-label {
    color: #666;
    font-size: 0.85rem;
    font-weight: 600;
}

/* ===== قسم الفلترة ===== */
.filter-section {
    background: white;
    border-radius: 25px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.filter-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 20px;
}

.filter-tab {
    padding: 10px 25px;
    border-radius: 50px;
    background: #f8f9fa;
    color: #2c3e50;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 2px solid transparent;
}

.filter-tab:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.filter-tab.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-color: var(--secondary);
}

.filter-tab.special {
    background: linear-gradient(135deg, #fff3cd, #ffe69c);
    color: #856404;
}

.filter-tab.special.active {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.filter-select {
    padding: 10px 20px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.9rem;
    min-width: 200px;
    background: white;
}

.search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-box i {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.search-box input {
    width: 100%;
    padding: 12px 50px 12px 20px;
    border: 2px solid #e9ecef;
    border-radius: 50px;
    font-size: 1rem;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
}

.teacher-filter {
    display: flex;
    align-items: center;
    gap: 10px;
}

.teacher-filter label {
    font-weight: 600;
    color: var(--primary);
}

/* ===== شبكة الطلاب ===== */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 25px;
}

.student-card {
    background: white;
    border-radius: 25px;
    padding: 0;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    border: 1px solid rgba(0,0,0,0.05);
}

.student-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.student-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
}

.student-card.boy::before { background: linear-gradient(90deg, #3498db, #2980b9); }
.student-card.girl::before { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
.student-card.child::before { background: linear-gradient(90deg, #f39c12, #e67e22); }
.student-card.woman::before { background: linear-gradient(90deg, #e84342, #c0392b); }
.student-card.special::before { background: linear-gradient(90deg, #f39c12, #e67e22); }

.special-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 2;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.card-content {
    padding: 20px;
}

.student-avatar-section {
    display: flex;
    justify-content: center;
    margin-bottom: 15px;
    position: relative;
}

.student-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    border: 4px solid white;
    transition: all 0.3s;
}

.student-card:hover .student-avatar {
    transform: scale(1.05) rotate(5deg);
}

.student-level-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    background: linear-gradient(135deg, var(--secondary), #dbb87c);
    color: #1e3c3f;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.student-info {
    text-align: center;
    margin-bottom: 20px;
}

.student-name {
    font-size: 1.3rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 5px;
}

.student-meta {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 10px 0;
}

.meta-item {
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #2c3e50;
}

.meta-item i {
    color: var(--secondary);
}

.student-details {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
}

.detail-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed #dee2e6;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-icon {
    width: 30px;
    color: var(--secondary);
    font-size: 1rem;
}

.detail-label {
    font-weight: 600;
    color: #2c3e50;
    min-width: 80px;
    font-size: 0.85rem;
}

.detail-value {
    color: #666;
    font-size: 0.85rem;
    flex: 1;
}

.student-actions {
    display: flex;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.action-btn:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.action-btn.edit { background: var(--primary); }
.action-btn.delete { background: var(--danger); }
.action-btn.whatsapp { background: var(--whatsapp); }
.action-btn.progress { background: var(--secondary); color: #1e3c3f; }
.action-btn.transfer { background: var(--warning); color: #212529; }
.action-btn.special { background: linear-gradient(135deg, #f39c12, #e67e22); }
.action-btn.report { background: var(--info); }
.action-btn.convert { background: #6c757d; }

.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px;
    background: white;
    border-radius: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.no-results i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

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

.modal.show { display: flex; }

.modal-content {
    background: white;
    border-radius: 30px;
    padding: 30px;
    width: 90%;
    max-width: 500px;
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--secondary);
}

.modal-header h3 {
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #999;
    transition: all 0.3s;
}

.close-modal:hover {
    color: var(--danger);
    transform: rotate(90deg);
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 992px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .students-page { padding: 15px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .students-grid { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; text-align: center; }
    .header-actions { width: 100%; justify-content: center; }
    .filter-tabs { flex-direction: column; }
    .filter-tab { width: 100%; justify-content: center; }
    .filter-row { flex-direction: column; }
    .search-box { max-width: 100%; width: 100%; }
    .teacher-filter { width: 100%; justify-content: space-between; }
    .filter-select { flex: 1; }
    .student-actions { justify-content: center; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .detail-row { flex-direction: column; align-items: flex-start; gap: 5px; }
    .detail-label { min-width: auto; }
    .teacher-filter { flex-direction: column; align-items: flex-start; }
    .filter-select { width: 100%; }
}
</style>

<section class="students-page">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> الطلاب</h1>
        <div class="header-actions">
            <a href="add_student.php" class="header-btn"><i class="fas fa-user-plus"></i> إضافة طالب</a>
            <a href="add_multiple_students.php" class="header-btn"><i class="fas fa-users-plus"></i> إضافة عدة طلاب</a>
            <?php if (isAdmin()): ?>
                <a href="export_students_csv.php" class="header-btn"><i class="fas fa-file-excel"></i> تصدير Excel</a>
                <a href="special_requests.php" class="header-btn"><i class="fas fa-crown"></i> طلبات خاص</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isAdmin() && $stats): ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-label">الإجمالي</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #3498db;"><?php echo $stats['boys']; ?></div><div class="stat-label">أولاد</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #9b59b6;"><?php echo $stats['girls']; ?></div><div class="stat-label">بنات</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #f39c12;"><?php echo $stats['children']; ?></div><div class="stat-label">أطفال</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #e84342;"><?php echo $stats['women']; ?></div><div class="stat-label">نساء</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #f39c12;"><?php echo $stats['special_count']; ?></div><div class="stat-label">طلاب خاص</div></div>
    </div>
    <?php endif; ?>

    <div class="filter-section">
        <div class="filter-tabs">
            <a href="?category=all&special=all&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab <?php echo $filter == 'all' && $special_filter == 'all' ? 'active' : ''; ?>"><i class="fas fa-list"></i> الكل</a>
            <a href="?category=boy&special=all&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab <?php echo $filter == 'boy' ? 'active' : ''; ?>"><i class="fas fa-male"></i> أولاد</a>
            <a href="?category=girl&special=all&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab <?php echo $filter == 'girl' ? 'active' : ''; ?>"><i class="fas fa-female"></i> بنات</a>
            <a href="?category=child&special=all&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab <?php echo $filter == 'child' ? 'active' : ''; ?>"><i class="fas fa-child"></i> أطفال</a>
            <a href="?category=woman&special=all&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab <?php echo $filter == 'woman' ? 'active' : ''; ?>"><i class="fas fa-female"></i> نساء</a>
            <a href="?category=all&special=special&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab special <?php echo $special_filter == 'special' ? 'active' : ''; ?>"><i class="fas fa-crown"></i> طلاب خاص</a>
            <a href="?category=all&special=normal&teacher_id=<?php echo $selected_teacher; ?>" class="filter-tab <?php echo $special_filter == 'normal' ? 'active' : ''; ?>"><i class="fas fa-user"></i> طلاب عادي</a>
        </div>
        
        <div class="filter-row">
            <?php if (isAdmin()): ?>
            <div class="teacher-filter">
                <label><i class="fas fa-chalkboard-teacher"></i> فلترة حسب المعلم:</label>
                <select id="teacherFilter" class="filter-select" onchange="filterByTeacher()">
                    <option value="0">-- جميع المعلمين --</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($selected_teacher == $t['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="ابحث عن طالب بالاسم، رقم الهاتف، أو اسم المعلم...">
            </div>
        </div>
        
        <?php if ($current_teacher_name): ?>
        <div style="margin-top: 15px; padding: 10px; background: #e8f5e9; border-radius: 10px; text-align: center;">
            <i class="fas fa-chalkboard-teacher"></i> عرض طلاب المعلم: <strong><?php echo htmlspecialchars($current_teacher_name); ?></strong>
            <a href="?category=<?php echo $filter; ?>&special=<?php echo $special_filter; ?>&teacher_id=0" style="margin-right: 10px; color: #dc3545;">إلغاء الفلترة</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (count($students) > 0): ?>
        <div class="students-grid" id="studentsGrid">
            <?php foreach ($students as $student): 
                $category = $student['category'];
                $cat_color = getCategoryColor($category);
                $cat_icon = getCategoryIcon($category);
                $cat_name = getCategoryName($category);
                $is_special = $student['is_special'] ?? 0;
                $canEdit = (isAdmin() || (isTeacher() && $student['teacher_id'] == $_SESSION['user_id']));
            ?>
                <div class="student-card <?php echo $category; ?> <?php echo $is_special ? 'special' : ''; ?>" 
                     data-name="<?php echo strtolower($student['name']); ?>" 
                     data-phone="<?php echo strtolower($student['parent_phone'] ?? ''); ?>" 
                     data-teacher="<?php echo strtolower($student['teacher_name'] ?? ''); ?>">
                    
                    <?php if ($is_special): ?>
                        <div class="special-badge"><i class="fas fa-crown"></i> طالب خاص</div>
                    <?php endif; ?>
                    
                    <div class="card-content">
                        <div class="student-avatar-section">
                            <div class="student-avatar" style="background: <?php echo $cat_color; ?>;"><?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?></div>
                            <div class="student-level-badge"><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></div>
                        </div>
                        <div class="student-info">
                            <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="student-meta">
                                <span class="meta-item"><i class="fas <?php echo $cat_icon; ?>"></i> <?php echo $cat_name; ?></span>
                                <?php if (!empty($student['teacher_name'])): ?>
                                    <span class="meta-item"><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student['teacher_name']); ?></span>
                                <?php endif; ?>
                                <span class="meta-item"><i class="fas fa-calendar-alt"></i> <?php echo date('Y-m-d', strtotime($student['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="student-details">
                            <?php if (!empty($student['parent_phone'])): ?>
                            <div class="detail-row"><div class="detail-icon"><i class="fas fa-phone"></i></div><div class="detail-label">ولي الأمر:</div><div class="detail-value" dir="ltr"><?php echo htmlspecialchars($student['parent_phone']); ?></div></div>
                            <?php endif; ?>
                            <?php if (!empty($student['birth_date'])): ?>
                            <div class="detail-row"><div class="detail-icon"><i class="fas fa-birthday-cake"></i></div><div class="detail-label">الميلاد:</div><div class="detail-value"><?php echo $student['birth_date']; ?></div></div>
                            <?php endif; ?>
                            <?php if (!empty($student['username'])): ?>
                            <div class="detail-row"><div class="detail-icon"><i class="fas fa-user-circle"></i></div><div class="detail-label">اسم المستخدم:</div><div class="detail-value"><?php echo htmlspecialchars($student['username']); ?></div></div>
                            <?php endif; ?>
                        </div>
                        <div class="student-actions">
                            <?php if ($canEdit): ?>
                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="action-btn edit" title="تعديل"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?php echo $student['id']; ?>" class="action-btn delete" onclick="return confirm('هل أنت متأكد من حذف هذا الطالب؟')" title="حذف"><i class="fas fa-trash"></i></a>
                                <a href="transfer_student.php?student_id=<?php echo $student['id']; ?>" class="action-btn transfer" title="نقل الطالب"><i class="fas fa-exchange-alt"></i></a>
                                <?php if ($is_special): ?>
                                    <a href="convert_student_status.php?student_id=<?php echo $student['id']; ?>" class="action-btn convert" title="تحويل إلى طالب عادي"><i class="fas fa-user"></i></a>
                                <?php else: ?>
                                    <a href="convert_student_status.php?student_id=<?php echo $student['id']; ?>" class="action-btn special" title="تحويل إلى طالب خاص"><i class="fas fa-crown"></i></a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($student['parent_phone'])): ?>
                                <a href="send_report.php?student_id=<?php echo $student['id']; ?>" class="action-btn whatsapp" title="إرسال تقرير"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                            <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="action-btn progress" title="عرض التقدم"><i class="fas fa-chart-line"></i></a>
                            <?php if ($student['username']): ?>
                                <button class="action-btn edit" onclick="showLoginInfo('<?php echo htmlspecialchars($student['username']); ?>', '123456')" title="معلومات الدخول" style="background: #17a2b8;"><i class="fas fa-key"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="no-results" id="noResults" style="display: none;">
            <i class="fas fa-search"></i>
            <h3>لا توجد نتائج</h3>
            <p>لم نتمكن من العثور على طالب يطابق بحثك</p>
        </div>
    <?php else: ?>
        <div class="no-results">
            <i class="fas fa-users-slash"></i>
            <h3>لا يوجد طلاب بعد</h3>
            <p>يمكنك البدء بإضافة الطلاب الجدد</p>
            <?php if (isAdmin() || isTeacher()): ?>
                <a href="add_student.php" class="header-btn" style="display: inline-block; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; margin-top: 20px;">
                    <i class="fas fa-user-plus"></i> إضافة أول طالب
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<div class="modal" id="loginInfoModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> معلومات الدخول</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p><strong>اسم المستخدم:</strong> <span id="modalUsername"></span></p>
            <p><strong>كلمة المرور:</strong> <span id="modalPassword">123456</span></p>
            <p style="color: #666; margin-top: 15px;"><i class="fas fa-info-circle"></i> يرجى تغيير كلمة المرور بعد أول تسجيل دخول</p>
        </div>
    </div>
</div>

<script>
// فلترة حسب المعلم
function filterByTeacher() {
    let teacherId = document.getElementById('teacherFilter').value;
    let currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('teacher_id', teacherId);
    window.location.href = currentUrl.toString();
}

// البحث
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    let searchTerm = this.value.toLowerCase().trim();
    let cards = document.querySelectorAll('.student-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        let name = card.getAttribute('data-name') || '';
        let phone = card.getAttribute('data-phone') || '';
        let teacher = card.getAttribute('data-teacher') || '';
        let matches = searchTerm === '' || name.includes(searchTerm) || phone.includes(searchTerm) || teacher.includes(searchTerm);
        card.style.display = matches ? 'block' : 'none';
        if (matches) visibleCount++;
    });
    
    let noResults = document.getElementById('noResults');
    if (noResults) {
        noResults.style.display = visibleCount === 0 && searchTerm !== '' ? 'block' : 'none';
    }
});

// معلومات الدخول
function showLoginInfo(username, password) {
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('loginInfoModal').classList.add('show');
}

function closeModal() {
    document.getElementById('loginInfoModal').classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) closeModal();
}

// تأثير ظهور تدريجي
document.addEventListener('DOMContentLoaded', function() {
    let cards = document.querySelectorAll('.student-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    console.log('✅ صفحة الطلاب جاهزة');
});
</script>

<?php require_once 'includes/footer.php'; ?>