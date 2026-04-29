<?php
// ============================================
// ملف: edit_ring.php - تعديل حلقة (تصميم عصري)
// ============================================

require_once 'config.php';
if (!isAdmin() && !isTeacher()) redirect('login.php');
$pageTitle = 'تعديل حلقة';
require_once 'includes/header.php';

$ring_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب الحلقة
if (isAdmin()) {
    $stmt = $pdo->prepare("SELECT * FROM rings WHERE id = ?");
    $stmt->execute([$ring_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM rings WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$ring_id, $_SESSION['user_id']]);
}
$ring = $stmt->fetch();

if (!$ring) {
    echo '<div class="alert alert-error">الحلقة غير موجودة أو لا تملك صلاحية تعديلها.</div>';
    require_once 'includes/footer.php';
    exit;
}

// جلب مواعيد الحلقة الحالية
$schedules = $pdo->prepare("SELECT * FROM ring_schedules WHERE ring_id = ? ORDER BY day_of_week");
$schedules->execute([$ring_id]);
$schedules = $schedules->fetchAll();

// جلب قائمة المعلمين
if (isAdmin()) {
    $teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location'] ?? '');
    
    if (isAdmin()) {
        $teacher_id = (int)$_POST['teacher_id'];
    } else {
        $teacher_id = $ring['teacher_id'];
    }

    $days = $_POST['day_of_week'] ?? [];
    $times = $_POST['start_time'] ?? [];

    if (empty($name)) {
        $error = 'اسم الحلقة مطلوب.';
    } elseif (empty($days)) {
        $error = 'يجب تحديد يوم واحد على الأقل.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE rings SET name=?, description=?, teacher_id=?, location=? WHERE id=?");
            $stmt->execute([$name, $description, $teacher_id, $location, $ring_id]);

            $pdo->prepare("DELETE FROM ring_schedules WHERE ring_id = ?")->execute([$ring_id]);

            $stmt2 = $pdo->prepare("INSERT INTO ring_schedules (ring_id, day_of_week, start_time) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($days); $i++) {
                if (!empty($days[$i])) {
                    $time = $times[$i] ?? null;
                    $stmt2->execute([$ring_id, $days[$i], $time]);
                }
            }

            $pdo->commit();
            $success = 'تم تحديث الحلقة بنجاح.';

            $schedules = $pdo->prepare("SELECT * FROM ring_schedules WHERE ring_id = ? ORDER BY day_of_week");
            $schedules->execute([$ring_id]);
            $schedules = $schedules->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'خطأ: ' . $e->getMessage();
        }
    }
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
?>

<style>
/* ===== تصميم عصري لصفحة تعديل حلقة ===== */
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --primary-dark: #0a2a2c;
    --secondary: #c9a96b;
    --secondary-light: #dbb87c;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --gray: #6c757d;
    --gray-light: #e9ecef;
    --dark: #2c3e50;
    --light: #f8f9fa;
    
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

.edit-ring-page {
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
    margin-top: 5px;
    font-size: 0.9rem;
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

/* ===== جدول المواعيد ===== */
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
    padding: 10px 12px;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    background: white;
}

.schedule-time {
    flex: 1;
    padding: 10px 12px;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
}

.schedule-delete-btn {
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
}

.schedule-delete-btn:hover {
    background: #c82333;
    transform: scale(1.05);
}

.add-day-btn {
    background: var(--info);
    color: white;
    border: none;
    padding: 10px 20px;
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
    .edit-ring-page {
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

@media (max-width: 480px) {
    .section-title h3 {
        font-size: 1rem;
    }
}
</style>

<section class="edit-ring-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            تعديل الحلقة
        </h1>
        <p><?php echo htmlspecialchars($ring['name']); ?></p>
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

        <form method="post">
            <!-- معلومات الحلقة -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    <h3>معلومات الحلقة</h3>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> اسم الحلقة <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($ring['name']); ?>">
                </div>

                <?php if (isAdmin()): ?>
                    <div class="form-group">
                        <label><i class="fas fa-chalkboard-teacher"></i> المعلم المشرف <span style="color: var(--danger);">*</span></label>
                        <select name="teacher_id" class="form-control" required>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $ring['teacher_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> المكان (اختياري)</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($ring['location'] ?? ''); ?>" placeholder="مثال: القاعة الرئيسية">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> الوصف</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($ring['description']); ?></textarea>
                </div>
            </div>

            <!-- أيام الحلقة -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>أيام ومواعيد الحلقة</h3>
                </div>
                
                <div id="schedules-container" class="schedules-container">
                    <?php if (!empty($schedules)): ?>
                        <?php foreach ($schedules as $index => $sch): ?>
                            <div class="schedule-row" id="schedule-<?php echo $index; ?>">
                                <select name="day_of_week[]" class="schedule-day" required>
                                    <?php foreach ($days_of_week as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $sch['day_of_week'] == $num ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="time" name="start_time[]" class="schedule-time" value="<?php echo htmlspecialchars($sch['start_time'] ?? ''); ?>" required>
                                <?php if ($index > 0): ?>
                                    <button type="button" class="schedule-delete-btn" onclick="this.closest('.schedule-row').remove()">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="schedule-row" id="schedule-0">
                            <select name="day_of_week[]" class="schedule-day" required>
                                <?php foreach ($days_of_week as $num => $name): ?>
                                    <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="time" name="start_time[]" class="schedule-time" required>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="add-day-btn" onclick="addScheduleDay()">
                    <i class="fas fa-plus-circle"></i> إضافة يوم آخر
                </button>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    حفظ التغييرات
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
let scheduleCounter = <?php echo count($schedules); ?>;

function addScheduleDay() {
    const container = document.getElementById('schedules-container');
    const index = scheduleCounter++;
    const newRow = document.createElement('div');
    newRow.className = 'schedule-row';
    newRow.id = `schedule-${index}`;
    newRow.innerHTML = `
        <select name="day_of_week[]" class="schedule-day" required>
            <?php foreach ($days_of_week as $num => $name): ?>
                <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" name="start_time[]" class="schedule-time" required>
        <button type="button" class="schedule-delete-btn" onclick="this.closest('.schedule-row').remove()">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(newRow);
}
</script>

<?php require_once 'includes/footer.php'; ?>