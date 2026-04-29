<?php
// ============================================
// ملف: ring_scattered_settings.php
// إعدادات حلقة المتفرقين - نسخة محسنة
// آخر تحديث: 2026-04-04
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'إعدادات حلقة المتفرقين';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;

// جلب معلومات الحلقة
$ring = $pdo->prepare("
    SELECT r.*, t.name as teacher_name,
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
    FROM rings r
    LEFT JOIN teachers t ON r.teacher_id = t.id
    WHERE r.id = ? AND r.teacher_id = ?
");
$ring->execute([$ring_id, $teacher_id]);
$ring = $ring->fetch();

if (!$ring) {
    echo '<div class="alert alert-error">الحلقة غير موجودة</div>';
    require_once 'includes/footer.php';
    exit;
}

// جلب طلاب الحلقة
$students = $pdo->prepare("
    SELECT s.id, s.name, s.level, s.parent_phone
    FROM ring_students rs
    JOIN students s ON rs.student_id = s.id
    WHERE rs.ring_id = ?
    ORDER BY s.name
");
$students->execute([$ring_id]);
$students = $students->fetchAll();

// جلب الإعدادات السابقة
$saved_times = [];
$saved_order = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $is_scattered = isset($_POST['is_scattered']) ? 1 : 0;
    $scattered_start_time = $_POST['scattered_start_time'];
    $scattered_end_time = $_POST['scattered_end_time'];
    $distribution_type = $_POST['distribution_type'];
    $auto_start = isset($_POST['auto_start']) ? 1 : 0;
    $reminder_minutes = (int)$_POST['reminder_minutes'];
    $warning_minutes = (int)$_POST['warning_minutes'];
    
    // جمع أوقات الطلاب
    $student_times = [];
    foreach ($students as $student) {
        $time_key = 'time_' . $student['id'];
        if (isset($_POST[$time_key]) && is_numeric($_POST[$time_key])) {
            $student_times[$student['id']] = (int)$_POST[$time_key];
        }
    }
    
    // جمع ترتيب الطلاب
    $student_order = [];
    if (isset($_POST['student_order']) && is_array($_POST['student_order'])) {
        foreach ($_POST['student_order'] as $order => $student_id) {
            $student_order[(int)$student_id] = (int)$order + 1;
        }
    }
    
    if ($is_scattered) {
        $start = strtotime($scattered_start_time);
        $end = strtotime($scattered_end_time);
        $total_minutes = ($end - $start) / 60;
        
        if ($total_minutes <= 0) {
            $error = "❌ وقت النهاية يجب أن يكون بعد وقت البداية";
        } elseif (empty($students)) {
            $error = "❌ لا يوجد طلاب في هذه الحلقة. أضف طلاباً أولاً";
        } else {
            try {
                $pdo->beginTransaction();
                
                // تحديث إعدادات الحلقة
                $stmt = $pdo->prepare("
                    UPDATE rings SET 
                        is_scattered = ?,
                        scattered_start_time = ?,
                        scattered_end_time = ?,
                        scattered_total_minutes = ?,
                        scattered_auto_timer = ?
                    WHERE id = ?
                ");
                $stmt->execute([$is_scattered, $scattered_start_time, $scattered_end_time, $total_minutes, $auto_start, $ring_id]);
                
                // حفظ الإعدادات في الجلسة
                $_SESSION['scattered_times_' . $ring_id] = $student_times;
                $_SESSION['scattered_order_' . $ring_id] = $student_order;
                $_SESSION['scattered_warning_' . $ring_id] = $warning_minutes;
                
                $pdo->commit();
                $success = "✅ تم حفظ إعدادات الحلقة بنجاح";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "❌ خطأ: " . $e->getMessage();
            }
        }
    } else {
        $pdo->prepare("UPDATE rings SET is_scattered = 0, scattered_auto_timer = 0 WHERE id = ?")->execute([$ring_id]);
        $success = "✅ تم إلغاء تفعيل إعدادات حلقة المتفرقين";
    }
}

// تحميل الإعدادات المحفوظة
$saved_times = $_SESSION['scattered_times_' . $ring_id] ?? [];
$saved_order = $_SESSION['scattered_order_' . $ring_id] ?? [];
$warning_minutes = $_SESSION['scattered_warning_' . $ring_id] ?? 2;

// ترتيب الطلاب حسب الترتيب المحفوظ
usort($students, function($a, $b) use ($saved_order) {
    $order_a = $saved_order[$a['id']] ?? 999;
    $order_b = $saved_order[$b['id']] ?? 999;
    if ($order_a == $order_b) {
        return strcmp($a['name'], $b['name']);
    }
    return $order_a - $order_b;
});

$total_custom_time = array_sum($saved_times);
$distribution_type_default = !empty($saved_times) ? 'custom' : 'equal';
?>

<style>
.settings-page {
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
    text-align: center;
}

.ring-info {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.ring-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #1e3c3f;
}

.form-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
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

.distribution-buttons {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}

.dist-btn {
    flex: 1;
    padding: 12px;
    text-align: center;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    cursor: pointer;
    transition: 0.3s;
}

.dist-btn.selected {
    background: #1e3c3f;
    color: white;
    border-color: #c9a96b;
}

.students-table-container {
    overflow-x: auto;
    margin: 20px 0;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 15px;
    overflow: hidden;
}

.students-table th,
.students-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.students-table th {
    background: #f8f9fa;
    font-weight: 700;
    color: #1e3c3f;
}

.duration-input {
    width: 100px;
    padding: 10px;
    text-align: center;
    border: 2px solid #e9ecef;
    border-radius: 10px;
}

.drag-handle {
    cursor: move;
    color: #c9a96b;
    font-size: 1.2rem;
}

.time-preview {
    background: #e8f5e9;
    border-radius: 15px;
    padding: 15px;
    margin-top: 20px;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #c8e6c9;
}

.preview-item:last-child {
    border-bottom: none;
}

.preview-order {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #1e3c3f;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #28a745;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.btn {
    width: 100%;
    padding: 14px;
    border-radius: 40px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    margin-top: 20px;
    font-size: 1.1rem;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.alert {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.total-time-warning {
    background: #fff3cd;
    padding: 12px;
    border-radius: 10px;
    margin: 10px 0;
    color: #856404;
    display: none;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .students-table th,
    .students-table td {
        padding: 10px;
        font-size: 0.85rem;
    }
    .duration-input {
        width: 70px;
    }
}
</style>

<section class="settings-page">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> إعدادات حلقة المتفرقين</h1>
        <p>تحديد أوقات الطلاب وترتيبهم في الجلسة</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="ring-info">
        <div class="ring-name"><i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?></div>
        <div class="ring-stats"><i class="fas fa-users"></i> <?php echo count($students); ?> طالب</div>
    </div>

    <form method="post" class="form-card" id="settingsForm">
        <div class="switch-label" style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            <label class="switch">
                <input type="checkbox" name="is_scattered" value="1" <?php echo $ring['is_scattered'] ? 'checked' : ''; ?> onchange="toggleScattered()">
                <span class="slider"></span>
            </label>
            <span><strong>تفعيل حلقة متفرقين</strong> (تحديد أوقات الطلاب)</span>
        </div>

        <div id="scatteredSettings" style="display: <?php echo $ring['is_scattered'] ? 'block' : 'none'; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> وقت بدء الحلقة</label>
                    <input type="time" name="scattered_start_time" class="form-control" value="<?php echo $ring['scattered_start_time'] ?? '14:00'; ?>" required id="startTime" onchange="updatePreview()">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> وقت انتهاء الحلقة</label>
                    <input type="time" name="scattered_end_time" class="form-control" value="<?php echo $ring['scattered_end_time'] ?? '15:00'; ?>" required id="endTime" onchange="updatePreview()">
                </div>
            </div>

            <div class="distribution-buttons">
                <div class="dist-btn <?php echo $distribution_type_default == 'equal' ? 'selected' : ''; ?>" onclick="setDistribution('equal')">
                    <i class="fas fa-balance-scale"></i> توزيع متساوي
                </div>
                <div class="dist-btn <?php echo $distribution_type_default == 'custom' ? 'selected' : ''; ?>" onclick="setDistribution('custom')">
                    <i class="fas fa-sliders-h"></i> تحديد أوقات يدوية
                </div>
            </div>
            <input type="hidden" name="distribution_type" id="distribution_type" value="<?php echo $distribution_type_default; ?>">

            <div id="customDistribution" style="display: <?php echo $distribution_type_default == 'custom' ? 'block' : 'none'; ?>">
                <div class="students-table-container">
                    <table class="students-table" id="studentsTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><i class="fas fa-arrows-alt"></i> ترتيب</th>
                                <th style="width: 50px;">#</th>
                                <th>اسم الطالب</th>
                                <th style="width: 120px;">الوقت (دقائق)</th>
                                <th style="width: 150px;">الوقت المتوقع</th>
                            </thead>
                        <tbody id="studentsTableBody">
                            <?php foreach ($students as $index => $student): 
                                $default_duration = $saved_times[$student['id']] ?? 15;
                                $order = $saved_order[$student['id']] ?? ($index + 1);
                            ?>
                            <tr data-student-id="<?php echo $student['id']; ?>" data-order="<?php echo $order; ?>">
                                <td class="drag-handle"><i class="fas fa-grip-vertical"></i><input type="hidden" name="student_order[]" value="<?php echo $student['id']; ?>" class="order-input-hidden"></td>
                                <td class="order-display"><?php echo $order; ?></td>
                                <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><input type="number" name="time_<?php echo $student['id']; ?>" class="duration-input" value="<?php echo $default_duration; ?>" min="1" max="60" step="1" onchange="updatePreview()"> <span>دقيقة</span></td>
                                <td class="time-slot-preview">-</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="equalDistributionInfo" style="display: <?php echo $distribution_type_default == 'equal' ? 'block' : 'none'; ?>">
                <div class="info-box" style="background: #e8f5e9; padding: 15px; border-radius: 12px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>التوزيع المتساوي:</strong>
                    <p>سيتم تقسيم وقت الحلقة بالتساوي بين جميع الطلاب</p>
                </div>
            </div>

            <div class="form-row" style="margin-top: 20px;">
                <div class="form-group">
                    <label><i class="fas fa-bell"></i> وقت التحذير (دقائق قبل النهاية)</label>
                    <input type="number" name="warning_minutes" class="form-control" value="<?php echo $warning_minutes; ?>" min="1" max="10">
                    <small>سيتم إصدار صوت تنبيه قبل انتهاء الوقت بهذا المقدار</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-bell"></i> وقت التذكير (دقائق قبل البدء)</label>
                    <input type="number" name="reminder_minutes" class="form-control" value="5" min="1" max="30">
                    <small>سيتم إرسال تذكير واتساب قبل بدء الحلقة</small>
                </div>
            </div>

            <div class="switch-label" style="margin: 20px 0;">
                <label class="switch">
                    <input type="checkbox" name="auto_start" value="1" checked>
                    <span class="slider"></span>
                </label>
                <span>تفعيل المؤقت التلقائي (يبدأ تلقائياً عند بدء وقت الحلقة)</span>
            </div>

            <div class="time-preview" id="timePreview">
                <h4><i class="fas fa-chart-line"></i> جدول توزيع الوقت النهائي</h4>
                <div id="previewContent"></div>
            </div>

            <div id="totalTimeWarning" class="total-time-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="warningMessage"></span>
            </div>
             <!-- إعدادات المؤقت التلقائي -->
<div class="form-section" style="margin-top: 20px;">
    <div class="section-title">
        <i class="fas fa-clock"></i>
        <h3>إعدادات المؤقت التلقائي</h3>
    </div>
    
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="auto_timer_enabled" value="1" <?php echo ($ring['scattered_auto_timer'] ?? 1) ? 'checked' : ''; ?>>
            <i class="fas fa-play-circle"></i> تفعيل المؤقت التلقائي (يبدأ تلقائياً عند وقت الحلقة)
        </label>
    </div>
    
    <div class="form-group">
        <label><i class="fas fa-bell"></i> وقت التذكير قبل البدء (دقائق)</label>
        <input type="number" name="reminder_minutes" class="form-control" value="5" min="0" max="30">
        <small>سيتم إصدار صوت تنبيه قبل بدء الجلسة</small>
    </div>
    
    <div class="form-group">
        <label><i class="fas fa-volume-up"></i> تنبيه صوتي عند انتهاء الوقت</label>
        <div class="radio-group">
            <label class="radio-option"><input type="radio" name="sound_alert" value="on" checked> تشغيل</label>
            <label class="radio-option"><input type="radio" name="sound_alert" value="off"> إيقاف</label>
        </div>
    </div>
</div>
            <button type="submit" name="save_settings" class="btn">
                <i class="fas fa-save"></i> حفظ إعدادات أوقات الطلاب
            </button>
        </div>
    </form>
</section>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
let currentDistribution = '<?php echo $distribution_type_default; ?>';

function toggleScattered() {
    const isChecked = document.querySelector('input[name="is_scattered"]').checked;
    document.getElementById('scatteredSettings').style.display = isChecked ? 'block' : 'none';
}

function setDistribution(type) {
    currentDistribution = type;
    document.querySelectorAll('.dist-btn').forEach(btn => btn.classList.remove('selected'));
    if (type === 'equal') {
        document.querySelector('.dist-btn:first-child').classList.add('selected');
        document.getElementById('customDistribution').style.display = 'none';
        document.getElementById('equalDistributionInfo').style.display = 'block';
    } else {
        document.querySelector('.dist-btn:last-child').classList.add('selected');
        document.getElementById('customDistribution').style.display = 'block';
        document.getElementById('equalDistributionInfo').style.display = 'none';
    }
    document.getElementById('distribution_type').value = type;
    updatePreview();
}

function updatePreview() {
    let startTime = document.getElementById('startTime').value;
    let endTime = document.getElementById('endTime').value;
    let students = [];
    let previewHtml = '';
    let totalCustomTime = 0;
    
    if (currentDistribution === 'equal') {
        <?php foreach ($students as $student): ?>
            students.push({ id: <?php echo $student['id']; ?>, name: "<?php echo addslashes($student['name']); ?>", duration: 0 });
        <?php endforeach; ?>
    } else {
        document.querySelectorAll('#studentsTableBody tr').forEach(row => {
            let studentId = row.dataset.studentId;
            let name = row.querySelector('.student-name').innerText;
            let duration = parseInt(row.querySelector('.duration-input').value) || 0;
            students.push({ id: studentId, name: name, duration: duration });
            totalCustomTime += duration;
        });
    }
    
    if (students.length === 0) {
        previewHtml = '<p style="color: #666;">لا يوجد طلاب في هذه الحلقة</p>';
    } else if (!startTime || !endTime) {
        previewHtml = '<p style="color: #666;">الرجاء تحديد وقت البداية والنهاية</p>';
    } else {
        let start = new Date(`2000-01-01T${startTime}`);
        let end = new Date(`2000-01-01T${endTime}`);
        let totalAvailable = (end - start) / 60000;
        
        const warningDiv = document.getElementById('totalTimeWarning');
        const warningMsg = document.getElementById('warningMessage');
        
        if (currentDistribution === 'equal') {
            let equalDuration = Math.floor(totalAvailable / students.length);
            let remaining = totalAvailable - (equalDuration * students.length);
            let currentTime = start;
            
            previewHtml = `<div style="margin-bottom: 15px; padding: 10px; background: #e8f5e9; border-radius: 10px;">
                <strong>المدة الإجمالية:</strong> ${Math.round(totalAvailable)} دقيقة | 
                <strong>عدد الطلاب:</strong> ${students.length} |
                <strong>مدة كل طالب:</strong> ${equalDuration} دقيقة
                ${remaining > 0 ? `(+${remaining} دقيقة للطالب الأول)` : ''}
            </div>`;
            
            students.forEach((student, index) => {
                let duration = equalDuration + (index === 0 ? remaining : 0);
                let slotEnd = new Date(currentTime.getTime() + duration * 60000);
                previewHtml += `
                    <div class="preview-item">
                        <div class="preview-item">
                        <div class="preview-order">${index + 1}</div>
                        <div class="preview-name">${student.name}</div>
                        <div class="preview-time">${duration} دقيقة</div>
                        <div class="preview-slot">${currentTime.toLocaleTimeString('ar-EG', {hour:'2-digit', minute:'2-digit'})} - ${slotEnd.toLocaleTimeString('ar-EG', {hour:'2-digit', minute:'2-digit'})}</div>
                    </div>
                `;
                currentTime = slotEnd;
            });
            warningDiv.style.display = 'none';
        } else {
            if (totalCustomTime > totalAvailable) {
                warningDiv.style.display = 'block';
                warningMsg.innerHTML = `⚠️ إجمالي الوقت المطلوب (${totalCustomTime} دقيقة) يتجاوز الوقت المتاح (${Math.round(totalAvailable)} دقيقة) بفارق ${totalCustomTime - Math.round(totalAvailable)} دقيقة. يرجى تعديل الأوقات.`;
                previewHtml = `<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 10px;">
                    ⚠️ إجمالي الوقت المطلوب (${totalCustomTime} دقيقة) يتجاوز الوقت المتاح (${Math.round(totalAvailable)} دقيقة)
                </div>`;
            } else {
                warningDiv.style.display = 'none';
                let currentTime = start;
                previewHtml = `<div style="margin-bottom: 15px; padding: 10px; background: #e8f5e9; border-radius: 10px;">
                    <strong>المدة الإجمالية:</strong> ${Math.round(totalAvailable)} دقيقة | 
                    <strong>الوقت المطلوب:</strong> ${totalCustomTime} دقيقة |
                    <strong>الوقت المتبقي:</strong> ${Math.round(totalAvailable - totalCustomTime)} دقيقة
                </div>`;
                
                students.forEach((student, index) => {
                    let duration = student.duration;
                    let slotEnd = new Date(currentTime.getTime() + duration * 60000);
                    previewHtml += `
                        <div class="preview-item">
                            <div class="preview-order">${index + 1}</div>
                            <div class="preview-name">${student.name}</div>
                            <div class="preview-time">${duration} دقيقة</div>
                            <div class="preview-slot">${currentTime.toLocaleTimeString('ar-EG', {hour:'2-digit', minute:'2-digit'})} - ${slotEnd.toLocaleTimeString('ar-EG', {hour:'2-digit', minute:'2-digit'})}</div>
                        </div>
                    `;
                    currentTime = slotEnd;
                });
            }
        }
    }
    
    document.getElementById('previewContent').innerHTML = previewHtml;
    
    if (currentDistribution === 'custom') {
        let start = new Date(`2000-01-01T${document.getElementById('startTime').value}`);
        let currentTime = start;
        document.querySelectorAll('#studentsTableBody tr').forEach((row, index) => {
            let duration = parseInt(row.querySelector('.duration-input').value) || 0;
            let slotEnd = new Date(currentTime.getTime() + duration * 60000);
            row.querySelector('.time-slot-preview').innerHTML = `${currentTime.toLocaleTimeString('ar-EG', {hour:'2-digit', minute:'2-digit'})} - ${slotEnd.toLocaleTimeString('ar-EG', {hour:'2-digit', minute:'2-digit'})}`;
            currentTime = slotEnd;
        });
        
        document.querySelectorAll('#studentsTableBody tr').forEach((row, index) => {
            row.querySelector('.order-display').innerHTML = index + 1;
            row.querySelector('.order-input-hidden').value = index + 1;
        });
    }
}

const tbody = document.getElementById('studentsTableBody');
if (tbody) {
    new Sortable(tbody, {
        handle: '.drag-handle',
        animation: 300,
        onEnd: function() { updatePreview(); }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    toggleScattered();
});
</script>

<?php require_once 'includes/footer.php'; ?>