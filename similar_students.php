<?php
// ============================================
// ملف: similar_students.php - تجميع الطلاب المتشابهين في الحفظ
// يعتمد على عدد الأجزاء المحفوظة بدقة
// ============================================

require_once 'config.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تجميع الطلاب المتشابهين في الحفظ';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// تحديث إحصائيات الأجزاء لجميع الطلاب
$all_students_ids = $pdo->query("SELECT id FROM students")->fetchAll(PDO::FETCH_COLUMN);
foreach ($all_students_ids as $student_id) {
    updateStudentPartsStats($pdo, $student_id);
}

// ============================================
// جلب الطلاب مع إحصائيات الأجزاء
// ============================================
$sql = "
    SELECT 
        s.id,
        s.name,
        s.category,
        s.level,
        t.name as teacher_name,
        sps.total_parts,
        sps.total_pages,
        COUNT(sp.id) as surahs_count
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    LEFT JOIN student_parts_stats sps ON s.id = sps.student_id
    WHERE 1=1
";

$params = [];

if ($teacher_id) {
    $sql .= " AND s.teacher_id = :teacher_id";
    $params[':teacher_id'] = $teacher_id;
}

if ($selected_category != 'all') {
    $sql .= " AND s.category = :category";
    $params[':category'] = $selected_category;
}

$sql .= " GROUP BY s.id
          ORDER BY sps.total_parts DESC, sps.total_pages DESC, s.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// ============================================
// تجميع الطلاب حسب عدد الأجزاء
// ============================================
$groups = [];
foreach ($students as $student) {
    $parts = $student['total_parts'] ?? 0;
    $range = getPartsRange($parts);
    
    if (!isset($groups[$range])) {
        $groups[$range] = [
            'range' => $range,
            'parts' => $parts,
            'students' => []
        ];
    }
    $groups[$range]['students'][] = $student;
}

// ترتيب المجموعات
ksort($groups);

// ============================================
// دالة تحديد نطاق الأجزاء
// ============================================
function getPartsRange($parts) {
    if ($parts >= 30) return '🎓 ختم القرآن (30 جزء)';
    if ($parts >= 25) return '25-29 جزء';
    if ($parts >= 20) return '20-24 جزء';
    if ($parts >= 15) return '15-19 جزء';
    if ($parts >= 10) return '10-14 جزء';
    if ($parts >= 5) return '5-9 أجزاء';
    if ($parts >= 1) return '1-4 أجزاء';
    return '🌱 مبتدئ (لا حفظ)';
}

$categories = [
    'all' => 'الكل',
    'boy' => 'أولاد',
    'girl' => 'بنات',
    'child' => 'أطفال',
    'woman' => 'نساء'
];
?>

<style>
.similar-page {
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
}

.filter-bar {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-btn {
    padding: 8px 20px;
    border-radius: 30px;
    background: #f8f9fa;
    color: #666;
    text-decoration: none;
    transition: 0.3s;
}

.filter-btn.active {
    background: #1e3c3f;
    color: white;
}

.filter-btn:hover:not(.active) {
    background: #e9ecef;
}

.group-card {
    background: white;
    border-radius: 20px;
    margin-bottom: 25px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.group-header {
    background: linear-gradient(135deg, #c9a96b, #dbb87c);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    color: #1e3c3f;
}

.group-title {
    font-size: 1.2rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.group-count {
    background: rgba(0,0,0,0.1);
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.8rem;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    padding: 20px;
}

.student-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: 0.3s;
    border: 1px solid #e9ecef;
}

.student-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    background: white;
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: bold;
    color: white;
    flex-shrink: 0;
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 700;
    color: #1e3c3f;
    font-size: 0.95rem;
}

.student-details {
    font-size: 0.7rem;
    color: #666;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 3px;
}

.student-parts {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

@media (max-width: 768px) {
    .students-grid {
        grid-template-columns: 1fr;
    }
    .group-header {
        flex-direction: column;
        text-align: center;
    }
    .filter-bar {
        justify-content: center;
    }
}
</style>

<section class="similar-page">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> تجميع الطلاب المتشابهين في الحفظ</h1>
        <p>تجميع الطلاب حسب عدد الأجزاء المحفوظة (كل 20 صفحة = جزء واحد)</p>
    </div>

    <!-- شريط التصفية -->
    <div class="filter-bar">
        <?php foreach ($categories as $key => $name): ?>
            <a href="?category=<?php echo $key; ?>" class="filter-btn <?php echo $selected_category == $key ? 'active' : ''; ?>">
                <?php echo $name; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($groups)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3>لا يوجد طلاب</h3>
            <p>لم يتم العثور على طلاب في هذه الفئة</p>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $range => $group): ?>
            <?php if (!empty($group['students'])): ?>
                <div class="group-card">
                    <div class="group-header">
                        <div class="group-title">
                            <i class="fas fa-layer-group"></i>
                            <?php echo $range; ?>
                        </div>
                        <div class="group-count">
                            <i class="fas fa-users"></i> <?php echo count($group['students']); ?> طالب
                        </div>
                    </div>
                    <div class="students-grid">
                        <?php foreach ($group['students'] as $student): 
                            $avatar_color = $student['category'] == 'boy' ? '#3498db' : ($student['category'] == 'girl' ? '#9b59b6' : ($student['category'] == 'child' ? '#f39c12' : '#e84342'));
                        ?>
                            <div class="student-card">
                                <div class="student-avatar" style="background: <?php echo $avatar_color; ?>;">
                                    <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                                </div>
                                <div class="student-info">
                                    <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <div class="student-details">
                                        <span><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></span>
                                        <span><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                                    </div>
                                </div>
                                <div class="student-parts">
                                    <?php 
                                    $parts = $student['total_parts'] ?? 0;
                                    if ($parts >= 30) {
                                        echo '🎓 ختم القرآن';
                                    } else {
                                        echo $parts . ' جزء';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- معلومات عن طريقة الحساب -->
    <div style="margin-top: 30px; background: #e7f3ff; border-radius: 20px; padding: 20px;">
        <h4 style="color: #0c5460; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-info-circle"></i> طريقة حساب الأجزاء
        </h4>
        <ul style="margin-top: 10px; margin-right: 25px; color: #0c5460;">
            <li><strong>📖 يتم حساب الأجزاء بناءً على الصفحات الفريدة المحفوظة</strong> (كل 20 صفحة = جزء واحد)</li>
            <li><strong>📊 الطالب الخاتم للقرآن</strong> لديه 604 صفحة فريدة = 30 جزءاً</li>
            <li><strong>🔄 يتم تحديث الإحصائيات تلقائياً</strong> عند إضافة أو حذف سور محفوظة</li>
            <li><strong>👥 يتم تجميع الطلاب حسب مستوى الحفظ</strong> لتسهيل تكوين المجموعات المتجانسة</li>
        </ul>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>