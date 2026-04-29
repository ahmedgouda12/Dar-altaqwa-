<?php
// ============================================
// ملف: edit_teacher.php - تعديل بيانات المعلم (نسخة محسنة بالكامل)
// ============================================
ob_start();
require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تعديل بيانات المعلم';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب بيانات المعلم
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM students WHERE teacher_id = t.id) as students_count,
           (SELECT COUNT(*) FROM rings WHERE teacher_id = t.id) as rings_count
    FROM teachers t 
    WHERE t.id = ?
");
$stmt->execute([$id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    $_SESSION['error'] = "❌ المعلم غير موجود";
    header('Location: teachers.php');
    exit;
}

// أيام الأسبوع
$days_of_week = [
    1 => 'الأحد',
    2 => 'الإثنين',
    3 => 'الثلاثاء',
    4 => 'الأربعاء',
    5 => 'الخميس',
    6 => 'الجمعة',
    7 => 'السبت'
];

$teacher_work_days = explode(',', $teacher['work_days'] ?? '1,2,3,4,5,6,7');
$error = '';
$success = '';

// ============================================
// معالجة تحديث البيانات
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $gender = $_POST['gender'];
    $specialization = trim($_POST['specialization']);
    $schedule = trim($_POST['schedule']);
    $phone = trim($_POST['phone']);
    $expected_weekly_sessions = (int)$_POST['expected_weekly_sessions'];
    $can_login = isset($_POST['can_login']) ? 1 : 0;
    $can_teach_special = isset($_POST['can_teach_special']) ? 1 : 0;
    
    // معالجة أيام العمل
    $work_days = isset($_POST['work_days']) ? $_POST['work_days'] : [];
    $work_days_str = !empty($work_days) ? implode(',', $work_days) : '1,2,3,4,5,6,7';

    // التحقق من صحة البيانات
    if (empty($name)) {
        $error = '❌ الاسم مطلوب';
    } elseif (empty($email)) {
        $error = '❌ البريد الإلكتروني مطلوب';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ البريد الإلكتروني غير صالح';
    } else {
        // التحقق من عدم وجود بريد مكرر
        $check = $pdo->prepare("SELECT id FROM teachers WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            $error = '❌ البريد الإلكتروني موجود بالفعل';
        } else {
            // معالجة كلمة المرور
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = '❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                }
            } else {
                $hashed = $teacher['password'];
            }

            if (empty($error)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE teachers SET
                            name = ?,
                            email = ?,
                            password = ?,
                            gender = ?,
                            specialization = ?,
                            schedule = ?,
                            phone = ?,
                            expected_weekly_sessions = ?,
                            can_login = ?,
                            can_teach_special = ?,
                            work_days = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $email, $hashed, $gender, $specialization, 
                        $schedule, $phone, $expected_weekly_sessions, 
                        $can_login, $can_teach_special, $work_days_str, $id
                    ]);
                    
                    $_SESSION['success'] = "✅ تم تحديث بيانات المعلم بنجاح";
                    header("Location: teachers.php");
                    exit;
                    
                } catch (PDOException $e) {
                    $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
                }
            }
        }
    }
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
    --gray: #6c757d;
    --gray-light: #e9ecef;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.edit-teacher-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
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
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.page-header .stats {
    display: flex;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.stat-badge {
    background: rgba(255,255,255,0.15);
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(5px);
}

/* ===== بطاقة النموذج ===== */
.form-card {
    background: white;
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.05);
}

.form-header {
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    padding: 20px 30px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-header i {
    font-size: 1.8rem;
    color: var(--primary-dark);
}

.form-header h2 {
    margin: 0;
    color: var(--primary-dark);
    font-size: 1.3rem;
}

.form-body {
    padding: 35px;
}

/* ===== أقسام النموذج ===== */
.form-section {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    transition: all 0.3s;
    border: 1px solid var(--gray-light);
}

.form-section:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border-color: var(--secondary);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--primary);
    margin-bottom: 25px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--secondary);
}

.section-title i {
    font-size: 1.3rem;
    color: var(--secondary);
}

.section-title h3 {
    margin: 0;
    font-size: 1.1rem;
}

/* ===== الحقول ===== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.form-group label i {
    color: var(--secondary);
    margin-left: 5px;
}

.form-group label .required {
    color: var(--danger);
    margin-right: 3px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-light);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s;
    font-family: 'Cairo', sans-serif;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
}

.form-control:hover {
    border-color: var(--primary-light);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* ===== أيام العمل ===== */
.days-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 12px;
    margin-top: 10px;
}

.day-checkbox {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px;
    background: white;
    border: 2px solid var(--gray-light);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}

.day-checkbox:hover {
    border-color: var(--secondary);
    transform: translateY(-2px);
}

.day-checkbox.selected {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-color: var(--secondary);
    color: white;
}

.day-checkbox.selected i {
    color: var(--secondary);
}

.day-checkbox input {
    display: none;
}

/* ===== خيارات إضافية ===== */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: white;
    border: 2px solid var(--gray-light);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 15px;
}

.checkbox-group:hover {
    border-color: var(--secondary);
}

.checkbox-group input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--secondary);
}

.checkbox-group label {
    flex: 1;
    cursor: pointer;
    font-weight: 600;
    color: var(--primary);
    margin: 0;
}

/* ===== أزرار الإجراءات ===== */
.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    padding: 14px 25px;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 1rem;
    min-width: 150px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* ===== رسائل التنبيه ===== */
.alert {
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid var(--success);
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid var(--danger);
}

/* ===== روابط سريعة ===== */
.quick-links {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
    flex-wrap: wrap;
}

.quick-link {
    padding: 8px 20px;
    background: #f8f9fa;
    border-radius: 50px;
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.quick-link:hover {
    background: var(--secondary);
    color: white;
    transform: translateY(-2px);
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .edit-teacher-page {
        padding: 15px;
    }
    
    .form-body {
        padding: 25px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .days-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-badge {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .form-body {
        padding: 20px;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .section-title h3 {
        font-size: 1rem;
    }
    
    .days-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-links {
        flex-direction: column;
    }
    
    .quick-link {
        justify-content: center;
    }
}
</style>

<section class="edit-teacher-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            تعديل بيانات المعلم
        </h1>
        <div class="stats">
            <span class="stat-badge">
                <i class="fas fa-users"></i> <?php echo $teacher['students_count']; ?> طالب
            </span>
            <span class="stat-badge">
                <i class="fas fa-ring"></i> <?php echo $teacher['rings_count']; ?> حلقة
            </span>
            <span class="stat-badge">
                <i class="fas fa-calendar"></i> منذ <?php echo date('Y-m-d', strtotime($teacher['created_at'])); ?>
            </span>
        </div>
    </div>

    <!-- عرض رسائل الخطأ -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-header">
            <i class="fas fa-user-edit"></i>
            <h2>تعديل بيانات المعلم: <?php echo htmlspecialchars($teacher['name']); ?></h2>
        </div>
        
        <form method="post" class="form-body" id="editTeacherForm">
            <!-- ============================================ -->
            <!-- البيانات الأساسية -->
            <!-- ============================================ -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-id-card"></i>
                    <h3>البيانات الأساسية</h3>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> الاسم الكامل <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> البريد الإلكتروني <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($teacher['phone']); ?>" placeholder="مثال: 01012345678">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور الجديدة</label>
                    <input type="password" name="password" class="form-control" placeholder="اتركه فارغاً إذا لم ترد تغييره">
                    <small class="text-muted">كلمة المرور يجب أن تكون 6 أحرف على الأقل</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i> الجنس <span class="required">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="male" <?php echo $teacher['gender'] == 'male' ? 'selected' : ''; ?>>معلم</option>
                        <option value="female" <?php echo $teacher['gender'] == 'female' ? 'selected' : ''; ?>>معلمة</option>
                    </select>
                </div>
            </div>
            
            <!-- ============================================ -->
            <!-- المعلومات المهنية -->
            <!-- ============================================ -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-briefcase"></i>
                    <h3>المعلومات المهنية</h3>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> التخصص</label>
                    <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($teacher['specialization']); ?>" placeholder="مثال: القرآن والتجويد">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> المواعيد (نص حر)</label>
                    <textarea name="schedule" class="form-control" rows="3" placeholder="مثال: السبت - الاثنين - الأربعاء 4:30 عصراً"><?php echo htmlspecialchars($teacher['schedule']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-week"></i> عدد الحصص الأسبوعية (المتوقعة)</label>
                    <input type="number" name="expected_weekly_sessions" class="form-control" min="1" max="7" value="<?php echo $teacher['expected_weekly_sessions']; ?>" required>
                </div>
            </div>
            
            <!-- ============================================ -->
            <!-- أيام العمل في الأسبوع -->
            <!-- ============================================ -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>أيام العمل في الأسبوع</h3>
                </div>
                <p style="color: #666; margin-bottom: 15px; font-size: 0.85rem;">
                    <i class="fas fa-info-circle"></i> اختر الأيام التي يعمل فيها المعلم (سيتم حساب الغياب فقط في هذه الأيام)
                </p>
                
                <div class="days-grid">
                    <?php foreach ($days_of_week as $num => $day): ?>
                        <div class="day-checkbox <?php echo in_array($num, $teacher_work_days) ? 'selected' : ''; ?>" data-day="<?php echo $num; ?>">
                            <i class="fas fa-calendar-day"></i>
                            <span><?php echo $day; ?></span>
                            <input type="checkbox" name="work_days[]" value="<?php echo $num; ?>" <?php echo in_array($num, $teacher_work_days) ? 'checked' : ''; ?> style="display: none;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- ============================================ -->
            <!-- الصلاحيات والإعدادات -->
            <!-- ============================================ -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    <h3>الصلاحيات والإعدادات</h3>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="can_login" id="can_login" value="1" <?php echo $teacher['can_login'] ? 'checked' : ''; ?>>
                    <label for="can_login">
                        <i class="fas fa-sign-in-alt"></i> مسموح له بتسجيل الدخول إلى المنصة
                    </label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="can_teach_special" id="can_teach_special" value="1" <?php echo ($teacher['can_teach_special'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="can_teach_special">
                        <i class="fas fa-crown"></i> مسموح له بتدريس الطلاب الخاصين
                    </label>
                </div>
            </div>
            <!-- ============================================ -->
            <!-- أزرار الإجراءات -->
            <!-- ============================================ -->
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
                <a href="teachers.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
                <a href="?delete=<?php echo $id; ?>" class="btn btn-danger" onclick="return confirm('⚠️ هل أنت متأكد من حذف هذا المعلم؟ لا يمكن التراجع عن هذا الإجراء.')">
                    <i class="fas fa-trash"></i> حذف المعلم
                </a>
            </div>
            
            <!-- ============================================ -->
            <!-- روابط سريعة -->
            <!-- ============================================ -->
            <div class="quick-links">
                <a href="teacher_students_list.php?teacher_id=<?php echo $id; ?>" class="quick-link">
                    <i class="fas fa-users"></i> عرض طلاب هذا المعلم
                </a>
                <a href="rings.php?teacher_id=<?php echo $id; ?>" class="quick-link">
                    <i class="fas fa-ring"></i> عرض حلقات هذا المعلم
                </a>
                <a href="teacher_attendance_report.php?teacher_id=<?php echo $id; ?>" class="quick-link">
                    <i class="fas fa-calendar-check"></i> تقرير حضور المعلم
                </a>
                <a href="send_message.php?teacher_id=<?php echo $id; ?>" class="quick-link">
                    <i class="fab fa-whatsapp"></i> إرسال رسالة
                </a>
            </div>
        </form>
    </div>
</section>

<script>
// ============================================
// تفعيل اختيار أيام العمل
// ============================================
document.querySelectorAll('.day-checkbox').forEach(day => {
    day.addEventListener('click', function() {
        const checkbox = this.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            this.classList.add('selected');
        } else {
            this.classList.remove('selected');
        }
    });
});

// ============================================
// تحسين تجربة المستخدم - تأثيرات على الحقول
// ============================================
document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
        this.parentElement.style.transition = 'all 0.3s ease';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
    });
});

// ============================================
// تأكيد الحفظ قبل الإرسال
// ============================================
document.getElementById('editTeacherForm').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    if (password && password.length < 6) {
        e.preventDefault();
        alert('❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        return false;
    }
    
    if (!confirm('هل أنت متأكد من حفظ التغييرات؟')) {
        e.preventDefault();
        return false;
    }
});

// ============================================
// رسالة ترحيب في الكونسول
// ============================================
console.log('✅ صفحة تعديل المعلم جاهزة');
console.log('👨‍🏫 المعلم: <?php echo addslashes($teacher['name']); ?>');
console.log('📊 عدد الطلاب: <?php echo $teacher['students_count']; ?>');
console.log('🔄 عدد الحلقات: <?php echo $teacher['rings_count']; ?>');
</script>
ob_end_flush();
<?php require_once 'includes/footer.php'; ?>