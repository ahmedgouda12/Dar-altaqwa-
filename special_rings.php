<?php
// ============================================
// ملف: special_rings.php
// إدارة حلقات الطلاب الخاصين
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'حلقات الطلاب الخاصين';
require_once 'includes/header.php';

$is_admin = isAdmin();
$teacher_id = isTeacher() ? $_SESSION['user_id'] : 0;

// جلب أنواع الحلقات
$types = $pdo->query("SELECT * FROM special_student_types WHERE is_active = 1")->fetchAll();

// جلب المعلمين
$teachers = $pdo->query("SELECT id, name, gender FROM teachers WHERE can_login = 1 ORDER BY name")->fetchAll();

// معالجة إضافة حلقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ring'])) {
    $name = trim($_POST['name']);
    $type_id = (int)$_POST['type_id'];
    $teacher_id_selected = (int)$_POST['teacher_id'];
    $description = trim($_POST['description']);
    $max_students = (int)$_POST['max_students'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $days_of_week = isset($_POST['days_of_week']) ? implode(',', $_POST['days_of_week']) : '';
    $location = trim($_POST['location']);
    $meeting_link = trim($_POST['meeting_link']);
    
    if (empty($name)) {
        $error = "❌ اسم الحلقة مطلوب";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO special_rings 
                (name, type_id, teacher_id, description, max_students, start_time, end_time, days_of_week, location, meeting_link)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $type_id, $teacher_id_selected, $description, $max_students,
                $start_time, $end_time, $days_of_week, $location, $meeting_link
            ]);
            $_SESSION['success'] = "✅ تم إضافة الحلقة بنجاح";
            header("Location: special_rings.php");
            exit;
        } catch (PDOException $e) {
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}

// معالجة حذف حلقة
if (isset($_GET['delete'])) {
    $ring_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM special_rings WHERE id = ?");
    $stmt->execute([$ring_id]);
    $_SESSION['success'] = "✅ تم حذف الحلقة بنجاح";
    header("Location: special_rings.php");
    exit;
}

// جلب الحلقات
if ($is_admin) {
    $rings = $pdo->query("
        SELECT sr.*, t.name as teacher_name, st.name_ar as type_name,
               (SELECT COUNT(*) FROM special_students WHERE ring_id = sr.id) as current_students
        FROM special_rings sr
        JOIN teachers t ON sr.teacher_id = t.id
        JOIN special_student_types st ON sr.type_id = st.id
        ORDER BY sr.name
    ")->fetchAll();
} else {
    $rings = $pdo->prepare("
        SELECT sr.*, t.name as teacher_name, st.name_ar as type_name,
               (SELECT COUNT(*) FROM special_students WHERE ring_id = sr.id) as current_students
        FROM special_rings sr
        JOIN teachers t ON sr.teacher_id = t.id
        JOIN special_student_types st ON sr.type_id = st.id
        WHERE sr.teacher_id = ?
        ORDER BY sr.name
    ");
    $rings->execute([$teacher_id]);
    $rings = $rings->fetchAll();
}

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$days_of_week_ar = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
?>

<style>
.special-rings-page {
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

.btn-add {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 10px 25px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.rings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.ring-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    position: relative;
    border-top: 5px solid;
}

.ring-card.online { border-top-color: #17a2b8; }
.ring-card.onsite { border-top-color: #28a745; }

.ring-type-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.type-online { background: #d1ecf1; color: #0c5460; }
.type-onsite { background: #d4edda; color: #155724; }

.ring-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e3c3f;
    margin-bottom: 15px;
    margin-top: 10px;
}

.ring-details {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px;
    margin: 15px 0;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 0.9rem;
}

.detail-row i {
    width: 25px;
    color: #c9a96b;
}

.students-count {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.ring-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn {
    flex: 1;
    padding: 8px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 0.85rem;
}

.btn-primary { background: #1e3c3f; color: white; }
.btn-danger { background: #dc3545; color: white; }

.form-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.days-checkbox {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.day-checkbox {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="special-rings-page">
    <div class="page-header">
        <h1><i class="fas fa-crown"></i> حلقات الطلاب الخاصين</h1>
        <button class="btn-add" onclick="document.getElementById('addForm').style.display='block'">
            <i class="fas fa-plus-circle"></i> إضافة حلقة
        </button>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- نموذج إضافة حلقة -->
    <div id="addForm" style="display: none;">
        <div class="form-card">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-plus-circle"></i> إضافة حلقة جديدة</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>اسم الحلقة *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>نوع الحلقة *</label>
                        <select name="type_id" class="form-control" required>
                            <option value="">-- اختر --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name_ar']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>المعلم المشرف *</label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">-- اختر --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى للطلاب</label>
                        <input type="number" name="max_students" class="form-control" value="1" min="1" max="10">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>وقت البداية</label>
                        <input type="time" name="start_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>وقت النهاية</label>
                        <input type="time" name="end_time" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>أيام الأسبوع</label>
                    <div class="days-checkbox">
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <label class="day-checkbox">
                                <input type="checkbox" name="days_of_week[]" value="<?php echo $i; ?>">
                                <?php echo $days_of_week_ar[$i-1]; ?>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>المكان (للحضوري)</label>
                        <input type="text" name="location" class="form-control" placeholder="مثال: القاعة الرئيسية">
                    </div>
                    <div class="form-group">
                        <label>رابط الاجتماع (للأونلاين)</label>
                        <input type="url" name="meeting_link" class="form-control" placeholder="https://meet.google.com/...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>وصف الحلقة</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_ring" class="btn btn-primary">إضافة الحلقة</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('addForm').style.display='none'">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($rings)): ?>
        <div class="empty-state">
            <i class="fas fa-ring"></i>
            <h3>لا توجد حلقات</h3>
            <p>قم بإضافة أول حلقة للطلاب الخاصين</p>
        </div>
    <?php else: ?>
        <div class="rings-grid">
            <?php foreach ($rings as $ring): 
                $type_class = $ring['type_name'] == 'أونلاين (خاص)' ? 'online' : 'onsite';
            ?>
                <div class="ring-card <?php echo $type_class; ?>">
                    <div class="ring-type-badge type-<?php echo $type_class; ?>">
                        <i class="fas <?php echo $ring['type_name'] == 'أونلاين (خاص)' ? 'fa-laptop' : 'fa-building'; ?>"></i>
                        <?php echo $ring['type_name']; ?>
                    </div>
                    <div class="ring-name"><?php echo htmlspecialchars($ring['name']); ?></div>
                    <div class="ring-details">
                        <div class="detail-row">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>المعلم: <?php echo htmlspecialchars($ring['teacher_name']); ?></span>
                        </div>
                        <?php if ($ring['start_time']): ?>
                        <div class="detail-row">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('h:i A', strtotime($ring['start_time'])); ?> - <?php echo date('h:i A', strtotime($ring['end_time'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($ring['location']): ?>
                        <div class="detail-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($ring['location']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($ring['meeting_link']): ?>
                        <div class="detail-row">
                            <i class="fas fa-video"></i>
                            <a href="<?php echo $ring['meeting_link']; ?>" target="_blank">رابط الاجتماع</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="students-count">
                        <span><i class="fas fa-users"></i> <?php echo $ring['current_students']; ?>/<?php echo $ring['max_students']; ?> طالب</span>
                        <?php if ($ring['current_students'] < $ring['max_students']): ?>
                            <span style="color: #28a745;">متاح</span>
                        <?php else: ?>
                            <span style="color: #dc3545;">مكتمل</span>
                        <?php endif; ?>
                    </div>
                    <div class="ring-actions">
                        <a href="special_ring_students.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-users"></i> الطلاب
                        </a>
                        <a href="?delete=<?php echo $ring['id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                            <i class="fas fa-trash"></i> حذف
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>