<?php
// ============================================
// ملف: student_timer_settings.php
// إعدادات المؤقت الزمني للطلاب
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'إعدادات المؤقت الزمني للطلاب';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];

// ============================================
// معالجة حفظ الإعدادات
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $student_id = (int)$_POST['student_id'];
    $time_per_ayah = (int)$_POST['time_per_ayah'];
    $time_per_page = (int)$_POST['time_per_page'];
    $time_per_surah = (int)$_POST['time_per_surah'];
    $base_time = (int)$_POST['base_time'];
    $max_time = (int)$_POST['max_time'];
    $warning_time = (int)$_POST['warning_time'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO student_timer_settings 
            (student_id, teacher_id, time_per_ayah, time_per_page, time_per_surah, base_time, max_time, warning_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            time_per_ayah = VALUES(time_per_ayah),
            time_per_page = VALUES(time_per_page),
            time_per_surah = VALUES(time_per_surah),
            base_time = VALUES(base_time),
            max_time = VALUES(max_time),
            warning_time = VALUES(warning_time),
            updated_at = NOW()
        ");
        $stmt->execute([
            $student_id, $teacher_id, $time_per_ayah, $time_per_page, 
            $time_per_surah, $base_time, $max_time, $warning_time
        ]);
        
        $_SESSION['success'] = "✅ تم حفظ إعدادات المؤقت للطالب بنجاح";
        header("Location: student_timer_settings.php?student_id=$student_id");
        exit;
        
    } catch (PDOException $e) {
        $error = "❌ خطأ في حفظ الإعدادات: " . $e->getMessage();
    }
}

// ============================================
// جلب قائمة الطلاب
// ============================================
$students = $pdo->prepare("
    SELECT s.id, s.name, s.level,
           (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_surahs,
           ts.time_per_ayah, ts.time_per_page, ts.time_per_surah, ts.base_time, ts.max_time, ts.warning_time
    FROM students s
    LEFT JOIN student_timer_settings ts ON s.id = ts.student_id
    WHERE s.teacher_id = ?
    ORDER BY s.name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student_settings = null;

if ($selected_student > 0) {
    foreach ($students as $s) {
        if ($s['id'] == $selected_student) {
            $student_settings = $s;
            break;
        }
    }
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
    --gray: #6c757d;
    --gray-light: #e9ecef;
}

.settings-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.student-selector {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.student-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.student-card-select {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
}

.student-card-select:hover {
    background: #e9ecef;
    transform: translateY(-3px);
}

.student-card-select.active {
    border-color: var(--secondary);
    background: #fff3cd;
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.2rem;
    font-weight: bold;
}

.settings-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--secondary);
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

.form-group label i {
    color: var(--secondary);
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--gray-light);
    border-radius: 12px;
    font-size: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.info-box {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 15px;
    margin: 20px 0;
    border-right: 4px solid var(--info);
}

.btn {
    padding: 12px 25px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    width: 100%;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .student-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<section class="settings-page">
    <div class="page-header">
        <h1><i class="fas fa-hourglass-half"></i> إعدادات المؤقت الزمني للطلاب</h1>
        <p>حدد الوقت المناسب لكل طالب حسب كمية الحفظ</p>
    </div>

    <?php if ($success_message): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- اختيار الطالب -->
    <div class="student-selector">
        <h3><i class="fas fa-user-graduate"></i> اختر الطالب</h3>
        <div class="student-grid">
            <?php foreach ($students as $s): ?>
                <div class="student-card-select <?php echo $selected_student == $s['id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='?student_id=<?php echo $s['id']; ?>'">
                    <div class="student-avatar">
                        <?php echo mb_substr($s['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($s['name']); ?></div>
                    <div style="font-size: 0.8rem; color: #666;"><?php echo $s['memorized_surahs']; ?> سورة</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($selected_student > 0 && $student_settings): ?>
        <!-- إعدادات المؤقت -->
        <div class="settings-card">
            <div class="section-title">
                <i class="fas fa-hourglass-half"></i>
                <h3>إعدادات المؤقت لـ: <?php echo htmlspecialchars($student_settings['name']); ?></h3>
            </div>

            <form method="post">
                <input type="hidden" name="student_id" value="<?php echo $selected_student; ?>">
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>طريقة حساب الوقت:</strong>
                    <p class="mt-1">الوقت = (عدد الآيات × الوقت لكل آية) + الوقت الأساسي</p>
                    <p class="mt-1">الحد الأقصى للجلسة: <?php echo isset($student_settings['max_time']) ? round($student_settings['max_time'] / 60) : 15; ?> دقيقة</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> الوقت الأساسي (ثانية)</label>
                        <input type="number" name="base_time" class="form-control" 
                               value="<?php echo $student_settings['base_time'] ?? 60; ?>" min="0" max="300" required>
                        <small>يضاف كوقت افتتاحي للجلسة</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-end"></i> الحد الأقصى (ثانية)</label>
                        <input type="number" name="max_time" class="form-control" 
                               value="<?php echo $student_settings['max_time'] ?? 900; ?>" min="60" max="3600" required>
                        <small>أقصى مدة للجلسة (دقائق: <?php echo round(($student_settings['max_time'] ?? 900) / 60); ?>)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-quran"></i> وقت كل آية (ثانية)</label>
                        <input type="number" name="time_per_ayah" class="form-control" 
                               value="<?php echo $student_settings['time_per_ayah'] ?? 30; ?>" min="10" max="120" required>
                        <small>الوقت المقدر لكل آية</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> وقت كل صفحة (ثانية)</label>
                        <input type="number" name="time_per_page" class="form-control" 
                               value="<?php echo $student_settings['time_per_page'] ?? 60; ?>" min="20" max="180" required>
                        <small>الوقت المقدر لكل صفحة (حوالي 15 آية)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-book-open"></i> وقت كل سورة (ثانية)</label>
                        <input type="number" name="time_per_surah" class="form-control" 
                               value="<?php echo $student_settings['time_per_surah'] ?? 180; ?>" min="60" max="600" required>
                        <small>الوقت المقدر للسور القصيرة</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-bell"></i> وقت التحذير (ثانية)</label>
                        <input type="number" name="warning_time" class="form-control" 
                               value="<?php echo $student_settings['warning_time'] ?? 60; ?>" min="10" max="300" required>
                        <small>تنبيه قبل انتهاء الوقت</small>
                    </div>
                </div>

                <button type="submit" name="save_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
            </form>

            <!-- معاينة الوقت حسب كمية الحفظ -->
            <div class="info-box" style="margin-top: 20px; background: #e8f5e9;">
                <i class="fas fa-chart-line"></i>
                <strong>معاينة الوقت المقدر حسب كمية الحفظ:</strong>
                <div style="margin-top: 10px;">
                    <div>لحفظ <strong>10 آيات</strong>: 
                        <span id="preview10"><?php echo round(($student_settings['time_per_ayah'] ?? 30) * 10 + ($student_settings['base_time'] ?? 60)); ?></span> ثانية 
                        (<?php echo round((($student_settings['time_per_ayah'] ?? 30) * 10 + ($student_settings['base_time'] ?? 60)) / 60, 1); ?> دقيقة)
                    </div>
                    <div>لحفظ <strong>20 آية</strong>: 
                        <span id="preview20"><?php echo round(($student_settings['time_per_ayah'] ?? 30) * 20 + ($student_settings['base_time'] ?? 60)); ?></span> ثانية 
                        (<?php echo round((($student_settings['time_per_ayah'] ?? 30) * 20 + ($student_settings['base_time'] ?? 60)) / 60, 1); ?> دقيقة)
                    </div>
                    <div>لحفظ <strong>30 آية</strong> (صفحة واحدة): 
                        <span id="preview30"><?php echo round(($student_settings['time_per_page'] ?? 60) + ($student_settings['base_time'] ?? 60)); ?></span> ثانية 
                        (<?php echo round((($student_settings['time_per_page'] ?? 60) + ($student_settings['base_time'] ?? 60)) / 60, 1); ?> دقيقة)
                    </div>
                </div>
            </div>
        </div>

        <script>
        // تحديث المعاينة ديناميكياً
        const timePerAyah = document.querySelector('input[name="time_per_ayah"]');
        const timePerPage = document.querySelector('input[name="time_per_page"]');
        const baseTime = document.querySelector('input[name="base_time"]');
        
        function updatePreview() {
            let base = parseInt(baseTime.value) || 60;
            let perAyah = parseInt(timePerAyah.value) || 30;
            let perPage = parseInt(timePerPage.value) || 60;
            
            document.getElementById('preview10').innerText = perAyah * 10 + base;
            document.getElementById('preview20').innerText = perAyah * 20 + base;
            document.getElementById('preview30').innerText = perPage + base;
        }
        
        timePerAyah.addEventListener('input', updatePreview);
        timePerPage.addEventListener('input', updatePreview);
        baseTime.addEventListener('input', updatePreview);
        </script>

    <?php elseif ($selected_student > 0 && !$student_settings): ?>
        <div class="settings-card">
            <div class="section-title">
                <i class="fas fa-hourglass-half"></i>
                <h3>إعدادات المؤقت لـ: الطالب</h3>
            </div>
            <form method="post">
                <input type="hidden" name="student_id" value="<?php echo $selected_student; ?>">
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>الإعدادات الافتراضية:</strong>
                    <p>سيتم استخدام الإعدادات الافتراضية: 30 ثانية لكل آية، 60 ثانية للصفحة، 60 ثانية وقت أساسي</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الوقت الأساسي (ثانية)</label>
                        <input type="number" name="base_time" class="form-control" value="60" min="0" max="300" required>
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى (ثانية)</label>
                        <input type="number" name="max_time" class="form-control" value="900" min="60" max="3600" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>وقت كل آية (ثانية)</label>
                        <input type="number" name="time_per_ayah" class="form-control" value="30" min="10" max="120" required>
                    </div>
                    <div class="form-group">
                        <label>وقت كل صفحة (ثانية)</label>
                        <input type="number" name="time_per_page" class="form-control" value="60" min="20" max="180" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>وقت كل سورة (ثانية)</label>
                        <input type="number" name="time_per_surah" class="form-control" value="180" min="60" max="600" required>
                    </div>
                    <div class="form-group">
                        <label>وقت التحذير (ثانية)</label>
                        <input type="number" name="warning_time" class="form-control" value="60" min="10" max="300" required>
                    </div>
                </div>
                
                <button type="submit" name="save_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>