<?php
// ============================================
// ملف: add_teacher.php - إضافة معلم/معلمة (نسخة مصححة)
// ============================================

ob_start();
require_once 'config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إضافة معلم/معلمة';
$error = '';
$success = '';

// أيام الأسبوع للعرض
$days_of_week = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $specialization = trim($_POST['specialization'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $expected_weekly_sessions = (int)($_POST['expected_weekly_sessions'] ?? 3);
    
    // معالجة أيام العمل
    $work_days = isset($_POST['work_days']) ? $_POST['work_days'] : [];
    $work_days_str = !empty($work_days) ? implode(',', $work_days) : '1,2,3,4,5,6,7';

    // التحقق من المدخلات
    if (empty($name)) {
        $error = '❌ الاسم مطلوب';
    } elseif (empty($email)) {
        $error = '❌ البريد الإلكتروني مطلوب';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ البريد الإلكتروني غير صالح';
    } elseif (empty($password) || strlen($password) < 6) {
        $error = '❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif (empty($gender)) {
        $error = '❌ الجنس مطلوب';
    } else {
        // التحقق من عدم وجود بريد مكرر
        $check = $pdo->prepare("SELECT id FROM teachers WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = '❌ البريد الإلكتروني موجود بالفعل';
        } else {
            // تشفير كلمة المرور
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            // إدراج المعلم
            $stmt = $pdo->prepare("
                INSERT INTO teachers 
                (name, email, password, gender, specialization, schedule, phone, expected_weekly_sessions, can_login, work_days) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$name, $email, $hashed, $gender, $specialization, $schedule, $phone, $expected_weekly_sessions, $work_days_str]);
            
            $_SESSION['success'] = '✅ تم إضافة المعلم بنجاح';
            header('Location: teachers.php');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>

<style>
.add-teacher-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    text-align: center;
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
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.page-header h1 i {
    color: #c9a96b;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.form-card {
    background: white;
    border-radius: 30px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.05);
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
    background: linear-gradient(90deg, #c9a96b, #1e3c3f);
}

.form-section {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    transition: all 0.3s;
}

.form-section:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border-color: #c9a96b;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #1e3c3f;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #c9a96b;
}

.section-title i {
    color: #c9a96b;
    font-size: 1.3rem;
}

.section-title h3 {
    margin: 0;
    font-size: 1.2rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
    font-size: 0.95rem;
}

.form-group label i {
    color: #c9a96b;
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s;
    font-family: 'Cairo', sans-serif;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #c9a96b;
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
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

.gender-options {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.gender-option {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.gender-option.selected {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    border-color: #c9a96b;
    color: white;
}

.gender-option.selected i {
    color: white;
}

.gender-option input {
    display: none;
}

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
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.day-checkbox:hover {
    border-color: #c9a96b;
}

.day-checkbox.selected {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    border-color: #c9a96b;
    color: white;
}

.day-checkbox.selected i {
    color: #c9a96b;
}

.day-checkbox input {
    display: none;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
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
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
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

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

@media (max-width: 768px) {
    .add-teacher-page {
        padding: 15px;
    }
    
    .form-card {
        padding: 25px;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .days-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .gender-options {
        flex-direction: column;
    }
    
    .days-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="add-teacher-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-user-plus"></i>
            إضافة معلم/معلمة
        </h1>
        <p>أدخل بيانات المعلم لإنشاء حساب دخول للمنصة</p>
    </div>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="addTeacherForm">
            <!-- البيانات الأساسية -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-id-card"></i>
                    <h3>البيانات الأساسية</h3>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> الاسم الكامل <span style="color: #dc3545;">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> البريد الإلكتروني <span style="color: #dc3545;">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور <span style="color: #dc3545;">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                    <small class="text-muted">يجب أن تكون 6 أحرف على الأقل</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i> الجنس <span style="color: #dc3545;">*</span></label>
                    <div class="gender-options" id="genderOptions">
                        <div class="gender-option" data-gender="male">
                            <i class="fas fa-male"></i>
                            <span>معلم</span>
                            <input type="radio" name="gender" value="male" required>
                        </div>
                        <div class="gender-option" data-gender="female">
                            <i class="fas fa-female"></i>
                            <span>معلمة</span>
                            <input type="radio" name="gender" value="female" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- المعلومات المهنية -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-briefcase"></i>
                    <h3>المعلومات المهنية</h3>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> التخصص</label>
                    <input type="text" name="specialization" class="form-control" value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> المواعيد (نص حر)</label>
                    <textarea name="schedule" class="form-control" rows="2"><?php echo isset($_POST['schedule']) ? htmlspecialchars($_POST['schedule']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar-week"></i> عدد الحصص الأسبوعية (المتوقعة)</label>
                    <input type="number" name="expected_weekly_sessions" class="form-control" min="1" max="7" value="<?php echo isset($_POST['expected_weekly_sessions']) ? (int)$_POST['expected_weekly_sessions'] : 3; ?>" required>
                </div>
            </div>

            <!-- أيام العمل -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>أيام العمل في الأسبوع</h3>
                </div>
                <p style="color: #666; margin-bottom: 15px; font-size: 0.85rem;">
                    <i class="fas fa-info-circle"></i> اختر الأيام التي يعمل فيها المعلم (سيتم حساب الغياب فقط في هذه الأيام)
                </p>
                
                <div class="days-grid" id="daysGrid">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <div class="day-checkbox" data-day="<?php echo $i; ?>">
                            <i class="fas fa-calendar-day"></i>
                            <span><?php echo $days_of_week[$i-1]; ?></span>
                            <input type="checkbox" name="work_days[]" value="<?php echo $i; ?>" style="display: none;" <?php echo (isset($_POST['work_days']) && in_array($i, $_POST['work_days'])) ? 'checked' : ''; ?>>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    إضافة المعلم
                </button>
                <a href="teachers.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</section>

<script>
// تفعيل اختيار الجنس
document.querySelectorAll('.gender-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.gender-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        const radio = this.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    });
});

// تفعيل اختيار أيام العمل
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

// استعادة البيانات إذا كان هناك خطأ
<?php if (isset($_POST['gender'])): ?>
    window.addEventListener('load', function() {
        const gender = '<?php echo $_POST['gender']; ?>';
        const genderOption = document.querySelector(`.gender-option[data-gender="${gender}"]`);
        if (genderOption) {
            genderOption.click();
        }
    });
<?php endif; ?>

<?php if (isset($_POST['work_days']) && is_array($_POST['work_days'])): ?>
    window.addEventListener('load', function() {
        <?php foreach ($_POST['work_days'] as $day): ?>
            const dayCheck = document.querySelector(`.day-checkbox[data-day="${<?php echo $day; ?>}"]`);
            if (dayCheck) {
                dayCheck.click();
            }
        <?php endforeach; ?>
    });
<?php else: ?>
    // تحديد جميع الأيام افتراضياً
    window.addEventListener('load', function() {
        document.querySelectorAll('.day-checkbox').forEach(day => {
            day.click();
        });
    });
<?php endif; ?>

// التحقق من صحة النموذج قبل الإرسال
document.getElementById('addTeacherForm').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    if (password && password.length < 6) {
        e.preventDefault();
        alert('❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        return false;
    }
    
    const genderSelected = document.querySelector('input[name="gender"]:checked');
    if (!genderSelected) {
        e.preventDefault();
        alert('❌ يرجى اختيار الجنس');
        return false;
    }
});

console.log('✅ صفحة إضافة معلم جاهزة');
</script>

<?php require_once 'includes/footer.php'; ?>