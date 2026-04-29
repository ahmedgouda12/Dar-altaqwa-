<?php
// ============================================
// ملف: course_students.php - عرض طلاب الدورة
// ============================================
ob_start( );
require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب معلومات الدورة
$course = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$course->execute([$course_id]);
$course = $course->fetch();

if (!$course) {
    redirect('courses.php');
}

// جلب طلاب الدورة
$students = $pdo->prepare("
    SELECT * FROM course_enrollments 
    WHERE course_id = ? 
    ORDER BY enrollment_date DESC
");
$students->execute([$course_id]);
$students = $students->fetchAll();

$pageTitle = 'طلاب الدورة: ' . $course['course_name'];
require_once 'includes/header.php';
?>

<style>
.students-page {
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.stats-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.9rem;
}

.students-table {
    background: white;
    border-radius: 20px;
    overflow-x: auto;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #1e3c3f;
    color: white;
    padding: 15px;
    text-align: center;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    display: inline-block;
}

.status-active { background: #d4edda; color: #155724; }
.status-completed { background: #d1ecf1; color: #0c5460; }
.status-dropped { background: #f8d7da; color: #721c24; }

.export-btn {
    background: #28a745;
    color: white;
    padding: 10px 20px;
    border-radius: 30px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 768px) {
    th, td {
        font-size: 0.8rem;
        padding: 8px;
    }
}
</style>

<section class="students-page">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> طلاب الدورة: <?php echo htmlspecialchars($course['course_name']); ?></h1>
            <p>رمز الدورة: <?php echo $course['course_code']; ?></p>
        </div>
        <div class="stats-badge">
            <i class="fas fa-user-graduate"></i> عدد المسجلين: <?php echo count($students); ?>/<?php echo $course['max_students']; ?>
        </div>
    </div>

    <div style="margin-bottom: 20px;">
        <a href="courses.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> العودة للدورات</a>
        <a href="export_course_students.php?course_id=<?php echo $course_id; ?>" class="export-btn"><i class="fas fa-file-excel"></i> تصدير Excel</a>
    </div>

    <div class="students-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم الطالب</th>
                    <th>الهاتف</th>
                    <th>العمر</th>
                    <th>الجنس</th>
                    <th>ولي الأمر</th>
                    <th>تاريخ التسجيل</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </thead>
            <tbody>
                <?php foreach ($students as $index => $s): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                    <td dir="ltr"><?php echo $s['student_phone']; ?></td>
                    <td><?php echo $s['student_age']; ?> سنة</td>
                    <td><?php echo $s['student_gender'] == 'male' ? 'ذكر' : 'أنثى'; ?></td>
                    <td><?php echo htmlspecialchars($s['parent_name'] ?: '-'); ?></td>
                    <td><?php echo $s['enrollment_date']; ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $s['status']; ?>">
                            <?php echo $s['status'] == 'active' ? 'نشط' : ($s['status'] == 'completed' ? 'مكتمل' : 'منسحب'); ?>
                        </span>
                    </td>
                    <td>
                        <a href="course_student_details.php?id=<?php echo $s['id']; ?>" class="btn btn-info btn-sm">تفاصيل</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>