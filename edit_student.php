<?php
// ============================================
// ملف: edit_student.php
// تعديل بيانات الطالب - تصميم احترافي
// آخر تحديث: 2026-03-18
// ============================================

require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');
$pageTitle = 'تعديل بيانات طالب';
require_once 'includes/header.php';

// التحقق من وجود معرف الطالب
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($student_id <= 0) {
    echo '<p class="card">معرف الطالب غير صحيح.</p>';
    require_once 'includes/footer.php';
    exit;
}

// جلب بيانات الطالب الحالية
$stmt = $pdo->prepare("
    SELECT s.*, t.name as teacher_name 
    FROM students s 
    LEFT JOIN teachers t ON s.teacher_id = t.id 
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    echo '<p class="card">الطالب غير موجود.</p>';
    require_once 'includes/footer.php';
    exit;
}

// جلب قائمة المعلمين
$teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();

// جلب قائمة أولياء الأمور
$guardians = $pdo->query("SELECT id, name, phone FROM guardians ORDER BY name")->fetchAll();

// جلب إحصائيات سريعة للطالب
// جلب إحصائيات سريعة للطالب
$stats = [];

// عدد السور المحفوظة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_surah_progress WHERE student_id = ? AND completed = 1");
$stmt->execute([$student_id]);
$stats['total_memorized'] = $stmt->fetchColumn() ?: 0;

// عدد الشهادات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_achievements WHERE student_id = ?");
$stmt->execute([$student_id]);
$stats['total_certificates'] = $stmt->fetchColumn() ?: 0;

// عدد الأهداف الشهرية
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_monthly_goals WHERE student_id = ?");
$stmt->execute([$student_id]);
$stats['total_goals'] = $stmt->fetchColumn() ?: 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $birth_date = $_POST['birth_date'] ?: null;
    $level = trim($_POST['level']);
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $guardian_id = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;
    
    // بيانات الدخول
    $student_username = trim($_POST['student_username'] ?? '');
    $student_password = $_POST['student_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $error = 'اسم الطالب مطلوب';
    } elseif (!empty($student_username) && $student_username !== $student['username'] && !empty($student_username)) {
        $check = $pdo->prepare("SELECT id FROM students WHERE username = ? AND id != ?");
        $check->execute([$student_username, $student_id]);
        if ($check->fetch()) {
            $error = 'اسم المستخدم هذا موجود بالفعل';
        }
    } elseif (!empty($student_password) && strlen($student_password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif (!empty($student_password) && $student_password !== $confirm_password) {
        $error = 'كلمتا المرور غير متطابقتين';
    }

    if (empty($error)) {
        $hashed_password = $student['password'];
        if (!empty($student_password)) {
            $hashed_password = password_hash($student_password, PASSWORD_DEFAULT);
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE students SET
                    name = ?,
                    category = ?,
                    birth_date = ?,
                    level = ?,
                    teacher_id = ?,
                    parent_phone = ?,
                    guardian_id = ?,
                    username = ?,
                    password = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $category, $birth_date, $level, $teacher_id,
                $parent_phone ?: null, $guardian_id,
                $student_username ?: null, $hashed_password,
                $student_id
            ]);
            $success = '✅ تم تحديث بيانات الطالب بنجاح';
            
            // إعادة جلب البيانات المحدثة
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = 'حدث خطأ أثناء التحديث: ' . $e->getMessage();
        }
    }
}
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --secondary-light: #dbb87c;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --dark: #2c3e50;
    --light: #f8f9fa;
    
    --gradient-primary: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    --gradient-secondary: linear-gradient(135deg, #c9a96b, #dbb87c);
    --gradient-success: linear-gradient(135deg, #28a745, #20c997);
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 8px rgba(0,0,0,0.1);
    --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
    --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
    
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 20px;
    --border-radius-xl: 30px;
    --border-radius-full: 9999px;
    
    --transition: 0.3s ease;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.edit-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: var(--gradient-primary);
    color: white;
    padding: 25px 30px;
    border-radius: var(--border-radius-xl);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-xl);
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

.student-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    border: 3px solid var(--secondary);
    box-shadow: var(--shadow-lg);
    position: relative;
    z-index: 2;
}

.header-content {
    flex: 1;
    position: relative;
    z-index: 2;
}

.header-content h1 {
    margin: 0 0 5px;
    font-size: 2rem;
}

.header-content p {
    opacity: 0.9;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 20px;
    text-align: center;
    box-shadow: var(--shadow-md);
    border: 1px solid #eee;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.5rem;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== بطاقة النموذج ===== */
.form-card {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 30px;
    box-shadow: var(--shadow-xl);
    border: 1px solid #eee;
    position: relative;
    overflow: hidden;
}

.form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: var(--gradient-secondary);
}

/* ===== أقسام النموذج ===== */
.form-section {
    background: #f8f9fa;
    border-radius: var(--border-radius-lg);
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid #e9ecef;
    transition: var(--transition);
}

.form-section:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--secondary);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 1.2rem;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--secondary);
}

.section-title i {
    color: var(--secondary);
    font-size: 1.4rem;
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
    font-size: 0.95rem;
}

.form-group label i {
    color: var(--secondary);
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius-lg);
    font-size: 1rem;
    transition: var(--transition);
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* ===== صورة الفئة ===== */
.category-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: var(--border-radius-full);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    margin-right: 10px;
}

.category-badge.boy { background: #3498db; }
.category-badge.girl { background: #9b59b6; }
.category-badge.child { background: #f39c12; }
.category-badge.woman { background: #e84342; }

/* ===== معلومات مساعدة ===== */
.info-box {
    background: #e7f3ff;
    border-right: 5px solid var(--info);
    padding: 15px;
    border-radius: var(--border-radius-lg);
    margin: 15px 0;
    color: #0c5460;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-box i {
    font-size: 1.5rem;
    color: var(--info);
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
    padding: 15px 25px;
    border: none;
    border-radius: var(--border-radius-full);
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 1rem;
    min-width: 150px;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
}

.btn-success {
    background: var(--gradient-success);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-xl);
}

/* ===== رسائل ===== */
.alert {
    padding: 15px 20px;
    border-radius: var(--border-radius-lg);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
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
    margin-top: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.quick-link {
    padding: 10px 20px;
    background: #f8f9fa;
    border-radius: var(--border-radius-full);
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.quick-link:hover {
    background: var(--secondary);
    color: white;
    transform: translateY(-2px);
}

.quick-link i {
    color: var(--secondary);
    transition: var(--transition);
}

.quick-link:hover i {
    color: white;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
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
    
    .quick-links {
        flex-direction: column;
    }
    
    .quick-link {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .form-card {
        padding: 20px;
    }
    
    .section-title {
        font-size: 1rem;
    }
}
</style>

<section class="edit-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <div class="student-avatar-large">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="header-content">
            <h1>تعديل بيانات الطالب</h1>
            <p>
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($student['name']); ?>
                <span class="category-badge <?php echo $student['category']; ?>">
                    <?php 
                    $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
                    echo $cat_names[$student['category']] ?? $student['category'];
                    ?>
                </span>
            </p>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">
                <i class="fas fa-quran"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_memorized']; ?></div>
            <div class="stat-label">سور محفوظة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0; color: #f57c00;">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_certificates']; ?></div>
            <div class="stat-label">شهادات</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9; color: #2e7d32;">
                <i class="fas fa-bullseye"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_goals']; ?></div>
            <div class="stat-label">أهداف شهرية</div>
        </div>
    </div>

    <!-- رسائل النجاح/الخطأ -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج التعديل -->
    <form method="post" class="form-card" id="editForm">
        <!-- البيانات الأساسية -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-id-card"></i>
                <h3>البيانات الأساسية</h3>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-user"></i>
                    الاسم الكامل <span style="color: var(--danger);">*</span>
                </label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-tag"></i>
                        الفئة
                    </label>
                    <select name="category" class="form-control" required>
                        <option value="boy" <?php echo $student['category'] == 'boy' ? 'selected' : ''; ?>>أولاد</option>
                        <option value="girl" <?php echo $student['category'] == 'girl' ? 'selected' : ''; ?>>بنات</option>
                        <option value="child" <?php echo $student['category'] == 'child' ? 'selected' : ''; ?>>أطفال</option>
                        <option value="woman" <?php echo $student['category'] == 'woman' ? 'selected' : ''; ?>>نساء</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-birthday-cake"></i>
                        تاريخ الميلاد
                    </label>
                    <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($student['birth_date']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-level-up-alt"></i>
                        المستوى
                    </label>
                    <input type="text" name="level" class="form-control" value="<?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?>">
                </div>

                <?php if (isAdmin()): ?>
<div class="form-group">
    <label>
        <i class="fas fa-chalkboard-teacher"></i>
        المعلم المشرف
    </label>
    <select name="teacher_id" class="form-control">
        <option value="">-- بدون معلم --</option>
        <?php foreach ($teachers as $t): ?>
            <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $student['teacher_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($t['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<?php else: ?>
<input type="hidden" name="teacher_id" value="<?php echo $student['teacher_id']; ?>">
<div class="form-group">
    <label>
        <i class="fas fa-chalkboard-teacher"></i>
        المعلم المشرف
    </label>
    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['teacher_name'] ?? $_SESSION['user_name']); ?>" readonly disabled style="background: #f0f0f0;">
</div>
<?php endif; ?>

        <!-- ربط ولي الأمر -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-user-tie"></i>
                <h3>ربط ولي أمر</h3>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-users"></i>
                    اختر ولي أمر موجود
                </label>
                <select name="guardian_id" class="form-control">
                    <option value="">-- اختر من القائمة --</option>
                    <?php foreach ($guardians as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo $g['id'] == ($student['guardian_id'] ?? 0) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['name'] . ' - ' . $g['phone']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-phone"></i>
                    أو رقم هاتف ولي الأمر
                </label>
                <input type="text" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>" placeholder="مثال: 01012345678">
            </div>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>سيتم استخدام هذا الرقم لإرسال تنبيهات واتساب عند الضرورة.</span>
            </div>
        </div>

        <!-- بيانات الدخول -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-lock"></i>
                <h3>بيانات الدخول</h3>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-user-circle"></i>
                    اسم المستخدم الحالي
                </label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['username'] ?? 'لا يوجد'); ?>" readonly disabled style="background: #f0f0f0;">
    </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user-circle"></i>
                        اسم المستخدم الجديد
                    </label>
                    <input type="text" name="student_username" class="form-control" value="<?php echo htmlspecialchars($student['username'] ?? ''); ?>" placeholder="اتركه فارغاً إذا لم ترد التغيير">
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i>
                        كلمة المرور الجديدة
                    </label>
                    <input type="password" name="student_password" class="form-control" placeholder="6 أحرف على الأقل">
                </div>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-check-circle"></i>
                    تأكيد كلمة المرور
                </label>
                <input type="password" name="confirm_password" class="form-control" placeholder="أعد كتابة كلمة المرور">
            </div>

            <div class="info-box" style="background: #fff3cd; border-right-color: var(--warning); color: #856404;">
                <i class="fas fa-exclamation-triangle"></i>
                <span>اترك حقول كلمة المرور فارغة إذا لم ترد تغييرها.</span>
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="action-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                حفظ التغييرات
            </button>
            <a href="students.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                إلغاء
            </a>
        </div>

        <!-- روابط سريعة -->
        <div class="quick-links">
            <a href="view_progress.php?student_id=<?php echo $student_id; ?>" class="quick-link">
                <i class="fas fa-chart-line"></i>
                عرض التقدم
            </a>
            <a href="student_monthly_goal.php?student_id=<?php echo $student_id; ?>" class="quick-link">
                <i class="fas fa-bullseye"></i>
                الأهداف الشهرية
            </a>
            <a href="student_certificates.php?student_id=<?php echo $student_id; ?>" class="quick-link">
                <i class="fas fa-certificate"></i>
                الشهادات
            </a>
            <?php if (!empty($student['parent_phone'])): ?>
                <a href="send_report.php?student_id=<?php echo $student_id; ?>" class="quick-link">
                    <i class="fab fa-whatsapp"></i>
                    إرسال تقرير
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>

<script>
// التحقق من تطابق كلمة المرور
document.addEventListener('DOMContentLoaded', function() {
    const password = document.querySelector('input[name="student_password"]');
    const confirm = document.querySelector('input[name="confirm_password"]');
    
    if (password && confirm) {
        confirm.addEventListener('keyup', function() {
            if (password.value !== this.value) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#28a745';
            }
        });
    }
});

// تأكيد قبل الحفظ
document.getElementById('editForm').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="student_password"]').value;
    const confirm = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('❌ كلمتا المرور غير متطابقتين');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>