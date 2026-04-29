<?php
// ============================================
// ملف: courses.php - إدارة الدورات (للمسؤول)
// ===========================================
ob_start( );

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إدارة الدورات';
require_once 'includes/header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب قائمة المعلمين
$teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();

// ============================================
// معالجة إضافة/تعديل دورة
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_course']) || isset($_POST['edit_course'])) {
        $course_name = trim($_POST['course_name']);
        $course_code = trim($_POST['course_code']);
        $course_description = trim($_POST['course_description']);
        $course_type = $_POST['course_type'];
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $max_students = (int)$_POST['max_students'];
        $days_of_week = isset($_POST['days_of_week']) ? implode(',', $_POST['days_of_week']) : '';
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = trim($_POST['location']);
        $price = (float)$_POST['price'];
        $requirements = trim($_POST['requirements']);
        $syllabus = trim($_POST['syllabus']);
        $status = $_POST['status'];

        if (empty($course_name)) {
            $error = "❌ اسم الدورة مطلوب";
        } elseif (empty($course_code)) {
            $error = "❌ رمز الدورة مطلوب";
        } elseif (empty($start_date) || empty($end_date)) {
            $error = "❌ تاريخ البداية والنهاية مطلوب";
        } else {
            try {
                if (isset($_POST['add_course'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO courses 
                        (course_code, course_name, course_description, course_type, teacher_id, 
                         start_date, end_date, max_students, days_of_week, start_time, end_time, 
                         location, price, requirements, syllabus, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $course_code, $course_name, $course_description, $course_type, $teacher_id,
                        $start_date, $end_date, $max_students, $days_of_week, $start_time, $end_time,
                        $location, $price, $requirements, $syllabus, $status, $_SESSION['user_id']
                    ]);
                    $_SESSION['success'] = "✅ تم إضافة الدورة بنجاح";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE courses SET
                            course_code = ?, course_name = ?, course_description = ?, course_type = ?,
                            teacher_id = ?, start_date = ?, end_date = ?, max_students = ?,
                            days_of_week = ?, start_time = ?, end_time = ?, location = ?,
                            price = ?, requirements = ?, syllabus = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $course_code, $course_name, $course_description, $course_type, $teacher_id,
                        $start_date, $end_date, $max_students, $days_of_week, $start_time, $end_time,
                        $location, $price, $requirements, $syllabus, $status, $course_id
                    ]);
                    $_SESSION['success'] = "✅ تم تحديث الدورة بنجاح";
                }
                header("Location: courses.php");
                exit;
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = "❌ رمز الدورة موجود بالفعل";
                } else {
                    $error = "❌ خطأ: " . $e->getMessage();
                }
            }
        }
    }
    
    // معالجة حذف دورة
    if (isset($_POST['delete_course'])) {
        $course_id = (int)$_POST['course_id'];
        $check = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?");
        $check->execute([$course_id]);
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "❌ لا يمكن حذف الدورة لأن بها طلاب مسجلين";
        } else {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $_SESSION['success'] = "✅ تم حذف الدورة بنجاح";
        }
        header("Location: courses.php");
        exit;
    }
}

// ============================================
// جلب قائمة الدورات
// ============================================
$courses = $pdo->query("
    SELECT c.*, t.name as teacher_name,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as enrolled_count
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    ORDER BY c.start_date DESC
")->fetchAll();

// جلب بيانات دورة للتعديل
$edit_course = null;
if ($action == 'edit' && $course_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $edit_course = $stmt->fetch();
}

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
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
}

.courses-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
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
    color: var(--primary);
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.course-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.course-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
}

.course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.course-code {
    background: var(--secondary);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
}

.course-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-completed { background: #d1ecf1; color: #0c5460; }

.course-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 10px;
}

.course-details {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
    border-bottom: 1px dashed #dee2e6;
    font-size: 0.9rem;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row i {
    width: 25px;
    color: var(--secondary);
}

.course-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 15px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
    transition: 0.3s;
}

.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-info { background: var(--info); color: white; }
.btn-warning { background: var(--warning); color: #212529; }

.form-container {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 800px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--primary);
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.days-checkbox {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.day-checkbox {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #f8f9fa;
    padding: 8px 15px;
    border-radius: 30px;
    cursor: pointer;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .courses-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="courses-page">
    <div class="page-header">
        <h1><i class="fas fa-graduation-cap"></i> إدارة الدورات</h1>
        <a href="?action=add" class="btn" style="background: var(--secondary); color: var(--primary);">
            <i class="fas fa-plus-circle"></i> دورة جديدة
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($action == 'add' || ($action == 'edit' && $edit_course)): ?>
        <!-- نموذج إضافة/تعديل دورة -->
        <div class="form-container">
            <h2 style="color: var(--primary); margin-bottom: 25px;">
                <?php echo $action == 'add' ? '➕ إضافة دورة جديدة' : '✏️ تعديل الدورة'; ?>
            </h2>
            
            <form method="post">
                <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="course_id" value="<?php echo $edit_course['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> رمز الدورة *</label>
                        <input type="text" name="course_code" class="form-control" required value="<?php echo $edit_course['course_code'] ?? ''; ?>" placeholder="مثال: TJD-001">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> اسم الدورة *</label>
                        <input type="text" name="course_name" class="form-control" required value="<?php echo $edit_course['course_name'] ?? ''; ?>" placeholder="مثال: دورة التجويد المتقدم">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> وصف الدورة</label>
                    <textarea name="course_description" class="form-control" rows="3"><?php echo $edit_course['course_description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> نوع الدورة</label>
                        <select name="course_type" class="form-control">
                            <option value="tajweed" <?php echo (isset($edit_course) && $edit_course['course_type'] == 'tajweed') ? 'selected' : ''; ?>>تجويد</option>
                            <option value="tafsir" <?php echo (isset($edit_course) && $edit_course['course_type'] == 'tafsir') ? 'selected' : ''; ?>>تفسير</option>
                            <option value="qiraat" <?php echo (isset($edit_course) && $edit_course['course_type'] == 'qiraat') ? 'selected' : ''; ?>>قراءات</option>
                            <option value="arabic" <?php echo (isset($edit_course) && $edit_course['course_type'] == 'arabic') ? 'selected' : ''; ?>>لغة عربية</option>
                            <option value="memorization" <?php echo (isset($edit_course) && $edit_course['course_type'] == 'memorization') ? 'selected' : ''; ?>>تحفيظ</option>
                            <option value="other" <?php echo (isset($edit_course) && $edit_course['course_type'] == 'other') ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-chalkboard-teacher"></i> المعلم</label>
                        <select name="teacher_id" class="form-control">
                            <option value="">-- بدون معلم --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo (isset($edit_course) && $edit_course['teacher_id'] == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-start"></i> تاريخ البداية *</label>
                        <input type="date" name="start_date" class="form-control" required value="<?php echo $edit_course['start_date'] ?? date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-end"></i> تاريخ النهاية *</label>
                        <input type="date" name="end_date" class="form-control" required value="<?php echo $edit_course['end_date'] ?? date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> الحد الأقصى للطلاب</label>
                        <input type="number" name="max_students" class="form-control" value="<?php echo $edit_course['max_students'] ?? 20; ?>" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> الرسوم (ج.م)</label>
                        <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $edit_course['price'] ?? 0; ?>" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-week"></i> أيام الأسبوع</label>
                    <div class="days-checkbox">
                        <?php 
                        $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                        $selected_days = isset($edit_course) ? explode(',', $edit_course['days_of_week']) : [];
                        for ($i = 1; $i <= 7; $i++): 
                        ?>
                        <label class="day-checkbox">
                            <input type="checkbox" name="days_of_week[]" value="<?php echo $i; ?>" <?php echo in_array($i, $selected_days) ? 'checked' : ''; ?>>
                            <?php echo $days[$i-1]; ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> وقت البداية</label>
                        <input type="time" name="start_time" class="form-control" value="<?php echo $edit_course['start_time'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> وقت النهاية</label>
                        <input type="time" name="end_time" class="form-control" value="<?php echo $edit_course['end_time'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-location-dot"></i> المكان</label>
                    <input type="text" name="location" class="form-control" value="<?php echo $edit_course['location'] ?? ''; ?>" placeholder="مثال: القاعة الرئيسية">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-clipboard-list"></i> المتطلبات السابقة</label>
                    <textarea name="requirements" class="form-control" rows="3"><?php echo $edit_course['requirements'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-book"></i> منهج الدورة</label>
                    <textarea name="syllabus" class="form-control" rows="4"><?php echo $edit_course['syllabus'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-circle"></i> حالة الدورة</label>
                        <select name="status" class="form-control">
                            <option value="active" <?php echo (isset($edit_course) && $edit_course['status'] == 'active') ? 'selected' : ''; ?>>نشطة</option>
                            <option value="inactive" <?php echo (isset($edit_course) && $edit_course['status'] == 'inactive') ? 'selected' : ''; ?>>غير نشطة</option>
                            <option value="completed" <?php echo (isset($edit_course) && $edit_course['status'] == 'completed') ? 'selected' : ''; ?>>منتهية</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 25px;">
                    <button type="submit" name="<?php echo $action == 'add' ? 'add_course' : 'edit_course'; ?>" class="btn btn-primary" style="flex:2;">
                        <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'إضافة الدورة' : 'حفظ التغييرات'; ?>
                    </button>
                    <a href="courses.php" class="btn btn-danger" style="flex:1; text-align: center;">إلغاء</a>
                </div>
            </form>
  <?php else: ?>
        <!-- عرض قائمة الدورات -->
        <?php
        $active_courses = array_filter($courses, fn($c) => $c['status'] == 'active');
        $total_enrolled = array_sum(array_column($courses, 'enrolled_count'));
        ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo count($courses); ?></div><div class="stat-label">إجمالي الدورات</div></div>
            <div class="stat-card"><div class="stat-number" style="color: var(--success);"><?php echo count($active_courses); ?></div><div class="stat-label">دورات نشطة</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_enrolled; ?></div><div class="stat-label">إجمالي المسجلين</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo round($total_enrolled / max(1, count($courses)), 1); ?></div><div class="stat-label">متوسط لكل دورة</div></div>
        </div>
        
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>لا توجد دورات</h3>
                <p>قم بإضافة أول دورة الآن</p>
                <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> إضافة دورة</a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): 
                    $remaining = $course['max_students'] - $course['enrolled_count'];
                    $status_class = $course['status'] == 'active' ? 'status-active' : ($course['status'] == 'inactive' ? 'status-inactive' : 'status-completed');
                ?>
                    <div class="course-card">
                        <div class="course-header">
                            <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                            <span class="course-status <?php echo $status_class; ?>">
                                <?php echo $course['status'] == 'active' ? 'نشطة' : ($course['status'] == 'inactive' ? 'غير نشطة' : 'منتهية'); ?>
                            </span>
                        </div>
                        
                        <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                        <p style="color: #666; font-size: 0.9rem; margin-bottom: 10px;"><?php echo nl2br(htmlspecialchars(substr($course['course_description'], 0, 100))); ?></p>
                        
                        <div class="course-details">
                            <div class="detail-row"><i class="fas fa-chalkboard-teacher"></i> <span>المعلم: <?php echo $course['teacher_name'] ?? 'غير محدد'; ?></span></div>
                            <div class="detail-row"><i class="fas fa-calendar-alt"></i> <span>من <?php echo $course['start_date']; ?> إلى <?php echo $course['end_date']; ?></span></div>
                            <div class="detail-row"><i class="fas fa-users"></i> <span>المسجلون: <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> (متبقي: <?php echo $remaining; ?>)</span></div>
                            <?php if ($course['price'] > 0): ?>
                            <div class="detail-row"><i class="fas fa-money-bill-wave"></i> <span>الرسوم: <?php echo number_format($course['price'], 2); ?> ج.م</span></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-actions">
                            <a href="course_students.php?id=<?php echo $course['id']; ?>" class="btn btn-info">
                                <i class="fas fa-users"></i> الطلاب (<?php echo $course['enrolled_count']; ?>)
                            </a>
                            <a href="?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" name="delete_course" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الدورة؟')">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>