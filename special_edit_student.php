<?php
// ============================================
// ملف: special_edit_student.php
// تعديل بيانات الطالب الخاص
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تعديل بيانات الطالب الخاص';
require_once 'includes/header.php';

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$student = $pdo->prepare("
    SELECT s.*, t.name as teacher_name
    FROM special_students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.id = ?
");
$student->execute([$student_id]);
$student = $student->fetch();

if (!$student) {
    echo '<div class="alert alert-error">الطالب غير موجود</div>';
    require_once 'includes/footer.php';
    exit;
}

$teachers = $pdo->query("SELECT id, name, gender FROM teachers WHERE can_login = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name']);
    $parent_phone = trim($_POST['parent_phone']);
    $schedule_time = $_POST['schedule_time'];
    $meeting_link = trim($_POST['meeting_link']);
    $level = trim($_POST['level']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status'];
    $teacher_id = (int)$_POST['teacher_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE special_students SET 
                student_name = ?, parent_phone = ?, schedule_time = ?, 
                meeting_link = ?, level = ?, notes = ?, status = ?, teacher_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$student_name, $parent_phone, $schedule_time, $meeting_link, $level, $notes, $status, $teacher_id, $student_id]);
        
        $_SESSION['success'] = "✅ تم تحديث بيانات الطالب بنجاح";
        header("Location: special_students.php");
        exit;
        
    } catch (PDOException $e) {
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<style>
.edit-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.btn {
    width: 100%;
    padding: 12px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="edit-page">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> تعديل بيانات الطالب الخاص</h1>
        <p><?php echo htmlspecialchars($student['student_name']); ?></p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <div class="form-row">
            <div class="form-group"><label><i class="fas fa-user"></i> اسم الطالب</label><input type="text" name="student_name" class="form-control" value="<?php echo htmlspecialchars($student['student_name']); ?>" required></div>
            <div class="form-group"><label><i class="fas fa-phone"></i> رقم ولي الأمر</label><input type="tel" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_phone']); ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label><i class="fas fa-clock"></i> الموعد</label><input type="time" name="schedule_time" class="form-control" value="<?php echo $student['schedule_time']; ?>"></div>
            <div class="form-group"><label><i class="fas fa-chalkboard-teacher"></i> المعلم</label><select name="teacher_id" class="form-control" required><?php foreach ($teachers as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $student['teacher_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-group"><label><i class="fas fa-video"></i> رابط الاجتماع</label><input type="text" name="meeting_link" class="form-control" value="<?php echo htmlspecialchars($student['meeting_link']); ?>" placeholder="meet.google.com/..."></div>
        <div class="form-row">
            <div class="form-group"><label><i class="fas fa-level-up-alt"></i> المستوى</label><input type="text" name="level" class="form-control" value="<?php echo htmlspecialchars($student['level']); ?>"></div>
            <div class="form-group"><label><i class="fas fa-circle"></i> الحالة</label><select name="status" class="form-control"><option value="active" <?php echo $student['status'] == 'active' ? 'selected' : ''; ?>>نشط</option><option value="inactive" <?php echo $student['status'] == 'inactive' ? 'selected' : ''; ?>>غير نشط</option><option value="completed" <?php echo $student['status'] == 'completed' ? 'selected' : ''; ?>>مكتمل</option></select></div>
        </div>
        <div class="form-group"><label><i class="fas fa-sticky-note"></i> ملاحظات</label><textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($student['notes']); ?></textarea></div>
        <button type="submit" class="btn">حفظ التغييرات</button>
    </form>
</section>

<?php require_once 'includes/footer.php'; ?>