<?php
// ============================================
// ملف: add_student.php - إضافة طالب بتصميم مبتكر
// آخر تحديث: 2026-03-17
// ============================================
ob_start();
require_once 'config.php';
if (!isAdmin() && !isTeacher()) redirect('login.php');
$pageTitle = 'إضافة طالب جديد - تصميم مبتكر';
require_once 'includes/header.php';

// جلب قائمة المعلمين
$teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();

// جلب قائمة الحلقات حسب الصلاحية
if (isAdmin()) {
    $rings = $pdo->query("SELECT id, name FROM rings ORDER BY name")->fetchAll();
} else {
    $teacher_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, name FROM rings WHERE teacher_id = ? ORDER BY name");
    $stmt->execute([$teacher_id]);
    $rings = $stmt->fetchAll();
}

// جلب أولياء الأمور
$guardians = $pdo->query("SELECT id, name, phone FROM guardians ORDER BY name")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $birth_date = $_POST['birth_date'] ?: null;
    $level = trim($_POST['level']);
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $ring_id = !empty($_POST['ring_id']) ? (int)$_POST['ring_id'] : null;
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $guardian_id = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;
    $student_username = trim($_POST['student_username'] ?? '');
    $student_password = $_POST['student_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $error = 'اسم الطالب مطلوب';
    } elseif (!empty($student_username) && strlen($student_password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif (!empty($student_username) && $student_password !== $confirm_password) {
        $error = 'كلمتا المرور غير متطابقتين';
    } else {
        // التحقق من عدم تكرار اسم المستخدم
        if (!empty($student_username)) {
            $check = $pdo->prepare("SELECT id FROM students WHERE username = ?");
            $check->execute([$student_username]);
            if ($check->fetch()) {
                $error = 'اسم المستخدم موجود بالفعل';
            }
        }

        if (empty($error)) {
            // معالجة guardian_id
            if ($guardian_id) {
                $final_guardian_id = $guardian_id;
            } elseif (!empty($parent_phone)) {
                $stmt = $pdo->prepare("SELECT id FROM guardians WHERE phone = ?");
                $stmt->execute([$parent_phone]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $final_guardian_id = $existing['id'];
                } else {
                    $temp_name = "ولي أمر " . $name;
                    $temp_pass = password_hash('123456', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO guardians (name, phone, password) VALUES (?, ?, ?)");
                    $stmt->execute([$temp_name, $parent_phone, $temp_pass]);
                    $final_guardian_id = $pdo->lastInsertId();
                }
            } else {
                $final_guardian_id = null;
            }

            $hashed_password = !empty($student_password) ? password_hash($student_password, PASSWORD_DEFAULT) : null;

            // إدراج الطالب
            $stmt = $pdo->prepare("
                INSERT INTO students (name, category, birth_date, level, teacher_id, parent_phone, guardian_id, username, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $category, $birth_date, $level, $teacher_id, $parent_phone ?: null, $final_guardian_id, $student_username ?: null, $hashed_password]);
            $student_id = $pdo->lastInsertId();

            // إذا تم اختيار حلقة، أضف الطالب إليها
            if ($ring_id) {
                $checkRing = $pdo->prepare("SELECT id FROM ring_students WHERE ring_id = ? AND student_id = ?");
                $checkRing->execute([$ring_id, $student_id]);
                if (!$checkRing->fetch()) {
                    $stmtRing = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
                    $stmtRing->execute([$ring_id, $student_id]);
                }
            }

            $_SESSION['success_message'] = '✅ تم إضافة الطالب بنجاح';
            header('Location: students.php');
            exit;
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

.add-student-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: var(--gradient-primary);
    color: white;
    padding: 25px 30px;
    border-radius: var(--border-radius-xl);
    margin-bottom: 30px;
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

.page-header i {
    font-size: 3rem;
    color: var(--secondary);
    position: relative;
    z-index: 2;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.page-header h1 {
    margin: 0;
    font-size: 2rem;
    position: relative;
    z-index: 2;
    flex: 1;
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

/* ===== خيارات الحلقات ===== */
.ring-options {
    background: #e8f5e9;
    padding: 15px;
    border-radius: var(--border-radius-lg);
    margin-bottom: 15px;
    border-right: 4px solid var(--success);
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

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-xl);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2a5f5a, #1e3c3f);
}

/* ===== رسائل ===== */
.alert {
    padding: 15px 20px;
    border-radius: var(--border-radius-lg);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-right: 5px solid;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right-color: var(--danger);
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right-color: var(--success);
}

/* ===== معلومات مساعدة ===== */
.help-text {
    background: #e7f3ff;
    padding: 15px;
    border-radius: var(--border-radius-lg);
    margin: 15px 0;
    color: #0c5460;
    border-right: 4px solid var(--info);
    font-size: 0.9rem;
}

.help-text i {
    color: var(--info);
    margin-left: 8px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .form-row {
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
}
</style>

<section class="add-student-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <i class="fas fa-user-graduate"></i>
        <h1>إضافة طالب جديد</h1>
    </div>

    <!-- رسائل الخطأ/النجاح -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج الإضافة -->
    <form method="post" class="form-card" id="studentForm">
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
                <input type="text" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-tag"></i>
                        الفئة
                    </label>
                    <select name="category" class="form-control" required>
                        <option value="boy" <?php echo (isset($_POST['category']) && $_POST['category'] == 'boy') ? 'selected' : ''; ?>>أولاد</option>
                        <option value="girl" <?php echo (isset($_POST['category']) && $_POST['category'] == 'girl') ? 'selected' : ''; ?>>بنات</option>
                        <option value="child" <?php echo (isset($_POST['category']) && $_POST['category'] == 'child') ? 'selected' : ''; ?>>أطفال</option>
                        <option value="woman" <?php echo (isset($_POST['category']) && $_POST['category'] == 'woman') ? 'selected' : ''; ?>>نساء</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-birthday-cake"></i>
                        تاريخ الميلاد
                    </label>
                    <input type="date" name="birth_date" class="form-control" value="<?php echo isset($_POST['birth_date']) ? $_POST['birth_date'] : ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-level-up-alt"></i>
                        المستوى
                    </label>
                    <input type="text" name="level" class="form-control" value="<?php echo isset($_POST['level']) ? htmlspecialchars($_POST['level']) : 'مبتدئ'; ?>">
                </div>

                <?php if (isAdmin()): ?>
<!-- للمدير: يظهر جميع المعلمين -->
<div class="form-group">
    <label>
        <i class="fas fa-chalkboard-teacher"></i>
        المعلم المشرف
    </label>
    <select name="teacher_id" class="form-control">
        <option value="">-- بدون معلم --</option>
        <?php foreach ($teachers as $t): ?>
            <option value="<?php echo $t['id']; ?>" <?php echo (isset($_POST['teacher_id']) && $_POST['teacher_id'] == $t['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($t['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<?php else: ?>
<!-- للمعلم: يظهر اسمه فقط (مخفي) -->
<input type="hidden" name="teacher_id" value="<?php echo $_SESSION['user_id']; ?>">
<div class="form-group">
    <label>
        <i class="fas fa-chalkboard-teacher"></i>
        المعلم المشرف
    </label>
    <input type="text" class="form-control" value="<?php echo $_SESSION['user_name']; ?>" readonly disabled style="background: #f0f0f0;">
</div>
<?php endif; ?>

            <?php if (!empty($rings)): ?>
            <div class="ring-options">
                <div class="form-group">
                    <label>
                        <i class="fas fa-ring"></i>
                        إضافة إلى حلقة (اختياري)
                    </label>
                    <select name="ring_id" class="form-control">
                        <option value="">-- بدون حلقة --</option>
                        <?php foreach ($rings as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo (isset($_POST['ring_id']) && $_POST['ring_id'] == $r['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ربط ولي الأمر -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-user-tie"></i>
                <h3>ربط ولي أمر (اختياري)</h3>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-users"></i>
                    اختر ولي أمر موجود
                </label>
                <select name="guardian_id" class="form-control">
                    <option value="">-- اختر --</option>
                    <?php foreach ($guardians as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo (isset($_POST['guardian_id']) && $_POST['guardian_id'] == $g['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['name'] . ' - ' . $g['phone']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-phone"></i>
                    أو أدخل رقم هاتف ولي الأمر
                </label>
                <input type="text" name="parent_phone" class="form-control" placeholder="مثال: 01012345678" value="<?php echo isset($_POST['parent_phone']) ? htmlspecialchars($_POST['parent_phone']) : ''; ?>">
            </div>
        </div>

        <!-- إنشاء حساب للطالب -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-lock"></i>
                <h3>إنشاء حساب دخول للطالب (اختياري)</h3>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user-circle"></i>
                        اسم المستخدم
                    </label>
                    <input type="text" name="student_username" class="form-control" placeholder="اختياري" value="<?php echo isset($_POST['student_username']) ? htmlspecialchars($_POST['student_username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i>
                        كلمة المرور
                    </label>
                    <input type="password" name="student_password" class="form-control" placeholder="6 أحرف على الأقل">
                </div>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-check-circle"></i>
                    تأكيد كلمة المرور
                </label>
                <input type="password" name="confirm_password" class="form-control">
            </div>

            <div class="help-text">
                <i class="fas fa-info-circle"></i>
                إذا تركت هذه الحقول فارغة، لن يتمكن الطالب من تسجيل الدخول.
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="action-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                حفظ الطالب
            </button>
            <a href="students.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                إلغاء
            </a>
        </div>
    </form>
</section>

<script>
// تحسينات للنموذج
document.addEventListener('DOMContentLoaded', function() {
    // التحقق من تطابق كلمة المرور
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
</script>

<?php require_once 'includes/footer.php'; ?>