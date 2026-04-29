<?php
// ============================================
// ملف: add_ring.php - إضافة حلقة جديدة (تصميم عصري)
// ============================================

ob_start(); // منع مشكلة headers
require_once 'config.php';
if (!isAdmin() && !isTeacher()) redirect('login.php');
$pageTitle = 'إضافة حلقة جديدة';
require_once 'includes/header.php';

// جلب قائمة المعلمين (للإدارة فقط)
if (isAdmin()) {
    $teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
}

$error = '';
$success = '';

// أيام الأسبوع مع أسمائها
$days_of_week = [
    1 => ['name' => 'الأحد', 'short' => 'أحد'],
    2 => ['name' => 'الإثنين', 'short' => 'إثنين'],
    3 => ['name' => 'الثلاثاء', 'short' => 'ثلاثاء'],
    4 => ['name' => 'الأربعاء', 'short' => 'أربعاء'],
    5 => ['name' => 'الخميس', 'short' => 'خميس'],
    6 => ['name' => 'الجمعة', 'short' => 'جمعة'],
    7 => ['name' => 'السبت', 'short' => 'سبت']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location'] ?? '');

    if (isAdmin()) {
        $teacher_id = (int)$_POST['teacher_id'];
    } else {
        $teacher_id = $_SESSION['user_id'];
    }

    // معالجة المواعيد - تحقق من وجود أيام ومواعيد
    $days = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : [];
    $times = isset($_POST['start_time']) ? $_POST['start_time'] : [];

    // تصفية الأيام التي لها مواعيد صالحة
    $valid_schedules = [];
    for ($i = 0; $i < count($days); $i++) {
        if (!empty($days[$i]) && !empty($times[$i])) {
            $valid_schedules[] = [
                'day' => (int)$days[$i],
                'time' => $times[$i]
            ];
        }
    }

    if (empty($name)) {
        $error = 'اسم الحلقة مطلوب.';
    } elseif (empty($valid_schedules)) {
        $error = 'يجب تحديد يوم واحد على الأقل مع تحديد الوقت.';
    } else {
        try {
            $pdo->beginTransaction();

            // إدراج الحلقة
            $stmt = $pdo->prepare("INSERT INTO rings (name, description, teacher_id, location) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $teacher_id, $location]);
            $ring_id = $pdo->lastInsertId();

            // إدراج المواعيد
            $stmt2 = $pdo->prepare("INSERT INTO ring_schedules (ring_id, day_of_week, start_time) VALUES (?, ?, ?)");
            foreach ($valid_schedules as $schedule) {
                $stmt2->execute([$ring_id, $schedule['day'], $schedule['time']]);
            }

            $pdo->commit();
            $success = 'تم إضافة الحلقة بنجاح.';
            
            // إعادة التوجيه بعد ثانيتين
            header("refresh:2; url=rings.php?msg=added");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'خطأ: ' . $e->getMessage();
        }
    }
}
?>

<style>
/* ===== تصميم عصري لصفحة إضافة حلقة ===== */
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
    
    --gradient-primary: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    --gradient-secondary: linear-gradient(135deg, #c9a96b, #dbb87c);
    --gradient-success: linear-gradient(135deg, #28a745, #20c997);
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 8px rgba(0,0,0,0.1);
    --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
    --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 20px;
    --radius-xl: 30px;
    --radius-2xl: 40px;
    --radius-full: 9999px;
    
    --transition: 0.3s ease;
}

.add-ring-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: var(--gradient-primary);
    color: white;
    padding: 30px;
    border-radius: var(--radius-xl);
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
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
    justify-content: center;
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

.page-header p {
    position: relative;
    z-index: 2;
    opacity: 0.9;
    margin-top: 10px;
}

/* ===== بطاقة النموذج ===== */
.form-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 35px;
    box-shadow: var(--shadow-lg);
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
    background: var(--gradient-secondary);
}

/* ===== أقسام النموذج ===== */
.form-section {
    background: #f8f9fa;
    border-radius: var(--radius-lg);
    padding: 25px;
    margin-bottom: 25px;
    transition: var(--transition);
}

.form-section:hover {
    box-shadow: var(--shadow-sm);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--secondary);
}

.section-title i {
    color: var(--secondary);
    font-size: 1.3rem;
}

.section-title h3 {
    margin: 0;
    font-size: 1.2rem;
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
    padding: 14px 18px;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-lg);
    font-size: 1rem;
    transition: var(--transition);
    font-family: 'Cairo', sans-serif;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* ===== أيام ومواعيد الحلقة ===== */
.schedules-container {
    margin-top: 15px;
}

.schedule-row {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    align-items: center;
    background: white;
    padding: 12px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-light);
    transition: var(--transition);
}

.schedule-row:hover {
    border-color: var(--secondary);
    box-shadow: var(--shadow-sm);
}

.schedule-day {
    flex: 2;
    padding: 12px 15px;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-md);
    font-size: 1rem;
    background: white;
    cursor: pointer;
}

.schedule-day:focus {
    outline: none;
    border-color: var(--secondary);
}

.schedule-time {
    flex: 1;
    padding: 12px 15px;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-md);
    font-size: 1rem;
    background: white;
}

.schedule-time:focus {
    outline: none;
    border-color: var(--secondary);
}

.remove-day-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--danger);
    color: white;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.remove-day-btn:hover {
    background: #c82333;
    transform: scale(1.05);
}

.add-day-btn {
    background: var(--info);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: var(--radius-full);
    cursor: pointer;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
    margin-top: 10px;
}

.add-day-btn:hover {
    background: #138496;
    transform: translateY(-2px);
}

/* ===== ملخص المواعيد ===== */
.schedule-summary {
    background: var(--info-light);
    border-radius: var(--radius-lg);
    padding: 15px;
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-right: 4px solid var(--info);
    font-size: 0.9rem;
}

.schedule-summary i {
    color: var(--info);
    font-size: 1.2rem;
}

.schedule-summary span {
    color: #0c5460;
}

/* ===== أزرار الإجراءات ===== */
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
    border-radius: var(--radius-full);
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 1rem;
    min-width: 150px;
}

.btn-primary {
    background: var(--gradient-success);
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

/* ===== رسائل التنبيه ===== */
.alert {
    padding: 15px 20px;
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: fadeIn 0.5s ease;
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

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .add-ring-page {
        padding: 15px;
    }
    
    .form-card {
        padding: 25px;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .schedule-row {
        flex-wrap: wrap;
    }
    
    .schedule-day, .schedule-time {
        flex: 1 1 100%;
    }
    
    .remove-day-btn {
        position: absolute;
        top: 10px;
        left: 10px;
        width: 30px;
        height: 30px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .page-header h1 {
        font-size: 1.4rem;
    }
}
</style>

<section class="add-ring-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-plus-circle"></i>
            إضافة حلقة جديدة
        </h1>
        <p>أدخل معلومات الحلقة وأيام انعقادها مع تحديد المواعيد</p>
    </div>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="ringForm">
            <!-- معلومات الحلقة -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    <h3>معلومات الحلقة</h3>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> اسم الحلقة <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           placeholder="مثال: حلقة الفجر" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <?php if (isAdmin()): ?>
                    <div class="form-group">
                        <label><i class="fas fa-chalkboard-teacher"></i> المعلم المشرف <span style="color: var(--danger);">*</span></label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">-- اختر المعلم --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="teacher_id" value="<?php echo $_SESSION['user_id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> المكان (اختياري)</label>
                    <input type="text" name="location" class="form-control" 
                           placeholder="مثال: القاعة الرئيسية - الدور الأول"
                           value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> وصف الحلقة (اختياري)</label>
                    <textarea name="description" class="form-control" rows="3" 
                              placeholder="أي معلومات إضافية عن الحلقة..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
            </div>

            <!-- أيام ومواعيد الحلقة -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>أيام ومواعيد الحلقة</h3>
                </div>
                
                <div id="schedules-container" class="schedules-container">
                    <!-- صف افتراضي واحد -->
                    <div class="schedule-row" id="schedule-0">
                        <select name="day_of_week[]" class="schedule-day" required>
                            <option value="">-- اختر اليوم --</option>
                            <?php foreach ($days_of_week as $num => $day): ?>
                                <option value="<?php echo $num; ?>"><?php echo $day['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="time" name="start_time[]" class="schedule-time" placeholder="الوقت" required>
                        <button type="button" class="remove-day-btn" onclick="removeScheduleRow(this)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>

                <button type="button" class="add-day-btn" onclick="addScheduleRow()">
                    <i class="fas fa-plus-circle"></i> إضافة يوم آخر
                </button>
                
                <div class="schedule-summary" id="scheduleSummary">
                    <i class="fas fa-info-circle"></i>
                    <span>لم يتم إضافة أي مواعيد بعد</span>
                </div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    إضافة الحلقة
                </button>
                <a href="rings.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</section>

<script>
let scheduleCounter = 1;

function addScheduleRow() {
    const container = document.getElementById('schedules-container');
    const index = scheduleCounter++;
    const newRow = document.createElement('div');
    newRow.className = 'schedule-row';
    newRow.id = `schedule-${index}`;
    newRow.innerHTML = `
        <select name="day_of_week[]" class="schedule-day" required>
            <option value="">-- اختر اليوم --</option>
            <?php foreach ($days_of_week as $num => $day): ?>
                <option value="<?php echo $num; ?>"><?php echo $day['name']; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" name="start_time[]" class="schedule-time" placeholder="الوقت" required>
        <button type="button" class="remove-day-btn" onclick="removeScheduleRow(this)">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(newRow);
    updateSummary();
}

function removeScheduleRow(button) {
    const row = button.closest('.schedule-row');
    const container = document.getElementById('schedules-container');
    if (container.children.length > 1) {
        row.remove();
    } else {
        // إذا كان الصف الوحيد، قم بتفريغ القيم بدلاً من حذفه
        const select = row.querySelector('.schedule-day');
        const timeInput = row.querySelector('.schedule-time');
        if (select) select.value = '';
        if (timeInput) timeInput.value = '';
    }
    updateSummary();
}

function updateSummary() {
    const rows = document.querySelectorAll('.schedule-row');
    let scheduleText = [];
    
    rows.forEach(row => {
        const select = row.querySelector('.schedule-day');
        const timeInput = row.querySelector('.schedule-time');
        const dayName = select?.options[select.selectedIndex]?.text;
        const time = timeInput?.value;
        
        if (dayName && time && dayName !== '-- اختر اليوم --') {
            scheduleText.push(`${dayName} (${time})`);
        }
    });
    
    const summarySpan = document.querySelector('#scheduleSummary span');
    if (scheduleText.length > 0) {
        summarySpan.innerHTML = `المواعيد المحددة: ${scheduleText.join(' - ')}`;
        summarySpan.style.color = '#155724';
        document.querySelector('#scheduleSummary i').className = 'fas fa-check-circle';
    } else {
        summarySpan.innerHTML = 'لم يتم إضافة أي مواعيد بعد';
        summarySpan.style.color = '#0c5460';
        document.querySelector('#scheduleSummary i').className = 'fas fa-info-circle';
    }
}

// إضافة مستمع لتحديث الملخص عند تغيير أي حقل
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('schedule-day') || e.target.classList.contains('schedule-time')) {
        updateSummary();
    }
});

// التحقق من صحة النموذج قبل الإرسال
document.getElementById('ringForm').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.schedule-row');
    let hasValidSchedule = false;
    
    rows.forEach(row => {
        const select = row.querySelector('.schedule-day');
        const timeInput = row.querySelector('.schedule-time');
        const dayValue = select?.value;
        const timeValue = timeInput?.value;
        
        if (dayValue && timeValue) {
            hasValidSchedule = true;
        }
    });
    
    if (!hasValidSchedule) {
        e.preventDefault();
        alert('⚠️ يرجى تحديد يوم واحد على الأقل مع تحديد الوقت');
        return false;
    }
    
    // التحقق من عدم تكرار الأيام
    const selectedDays = [];
    rows.forEach(row => {
        const select = row.querySelector('.schedule-day');
        const dayValue = select?.value;
        if (dayValue) {
            if (selectedDays.includes(dayValue)) {
                e.preventDefault();
                alert('⚠️ لا يمكن تحديد نفس اليوم أكثر من مرة');
                return false;
            }
            selectedDays.push(dayValue);
        }
    });
});

// استعادة البيانات إذا كان هناك خطأ
<?php if (isset($_POST['day_of_week']) && is_array($_POST['day_of_week'])): ?>
    window.addEventListener('load', function() {
        // إزالة الصفوف الافتراضية
        const container = document.getElementById('schedules-container');
        container.innerHTML = '';
        scheduleCounter = 0;
        
        <?php foreach ($_POST['day_of_week'] as $index => $day): ?>
            <?php if (isset($_POST['start_time'][$index]) && !empty($_POST['start_time'][$index])): ?>
                const newRow = document.createElement('div');
                newRow.className = 'schedule-row';
                newRow.id = `schedule-${scheduleCounter++}`;
                newRow.innerHTML = `
                    <select name="day_of_week[]" class="schedule-day" required>
                        <option value="">-- اختر اليوم --</option>
                        <?php foreach ($days_of_week as $num => $dayInfo): ?>
                            <option value="<?php echo $num; ?>" <?php echo $day == $num ? 'selected' : ''; ?>><?php echo $dayInfo['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="time" name="start_time[]" class="schedule-time" value="<?php echo $_POST['start_time'][$index]; ?>" required>
                    <button type="button" class="remove-day-btn" onclick="removeScheduleRow(this)">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
                container.appendChild(newRow);
            <?php endif; ?>
        <?php endforeach; ?>
        
        // إذا لم يتم إضافة أي صف، أضف صفاً افتراضياً
        if (container.children.length === 0) {
            addScheduleRow();
        }
        
        updateSummary();
    });
<?php else: ?>
    // تهيئة: إضافة صف افتراضي واحد
    window.addEventListener('load', function() {
        updateSummary();
    });
<?php endif; ?>
</script>

<?php
ob_end_flush();
require_once 'includes/footer.php';
?>