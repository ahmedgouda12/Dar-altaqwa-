<?php
// ============================================
// ملف: record_memorization.php - تسجيل الحفظ اليومي
// آخر تحديث: 2026-03-14
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isTeacher() && !isAdmin()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'تسجيل الحفظ اليومي';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$is_admin = isAdmin();

$current_hijri = getHijriDate();
$hijri_year = $current_hijri['year'];
$hijri_month = $current_hijri['month'];

// ============================================
// معالجة تسجيل الحفظ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_memorization'])) {
    $student_id = (int)$_POST['student_id'];
    $goal_id = !empty($_POST['goal_id']) ? (int)$_POST['goal_id'] : null;
    $surah_number = (int)$_POST['surah_number'];
    $from_ayah = !empty($_POST['from_ayah']) ? (int)$_POST['from_ayah'] : null;
    $to_ayah = !empty($_POST['to_ayah']) ? (int)$_POST['to_ayah'] : null;
    $memorized_date = $_POST['memorized_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    
    if (isTeacher()) {
        $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
        $check->execute([$student_id, $teacher_id]);
        if (!$check->fetch()) {
            $_SESSION['error'] = "❌ هذا الطالب ليس من طلابك";
            header("Location: record_memorization.php");
            exit;
        }
    }
    
    // حساب عدد الآيات المحفوظة
    $ayahs_count = 0;
    if ($from_ayah && $to_ayah) {
        $ayahs_count = $to_ayah - $from_ayah + 1;
    }
    
    try {
        $pdo->beginTransaction();
        
        // تسجيل الحفظ اليومي
        $stmt = $pdo->prepare("
            INSERT INTO daily_memorization 
            (student_id, teacher_id, goal_id, surah_number, from_ayah, to_ayah, memorized_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $teacher_id, $goal_id, $surah_number, $from_ayah, $to_ayah, $memorized_date, $notes]);
        
        // إذا كان هناك هدف مرتبط، قم بتحديث تقدم الهدف
        if ($goal_id) {
            $goal = $pdo->prepare("SELECT * FROM student_monthly_goals WHERE id = ? AND student_id = ?");
            $goal->execute([$goal_id, $student_id]);
            $goal_data = $goal->fetch();
            
            if ($goal_data) {
                $update = $pdo->prepare("
                    UPDATE student_monthly_goals 
                    SET memorized_ayahs = memorized_ayahs + ?,
                        current_surah = ?,
                        current_ayah = ?
                    WHERE id = ?
                ");
                $update->execute([$ayahs_count, $surah_number, $to_ayah, $goal_id]);
                
                // التحقق مما إذا تم إكمال الهدف
                $updated = $pdo->prepare("SELECT memorized_ayahs, total_ayahs FROM student_monthly_goals WHERE id = ?");
                $updated->execute([$goal_id]);
                $progress = $updated->fetch();
                
                if ($progress && $progress['memorized_ayahs'] >= $progress['total_ayahs']) {
                    $pdo->prepare("UPDATE student_monthly_goals SET completed = 1, completed_at = NOW() WHERE id = ?")->execute([$goal_id]);
                    $_SESSION['success'] = "✅ تم تسجيل الحفظ واكتمل الهدف الشهري!";
                } else {
                    $_SESSION['success'] = "✅ تم تسجيل الحفظ اليومي. التقدم: {$progress['memorized_ayahs']}/{$progress['total_ayahs']}";
                }
            }
        } else {
            $_SESSION['success'] = "✅ تم تسجيل الحفظ اليومي";
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: record_memorization.php?student_id=$student_id");
    exit;
}
// بعد حفظ التسجيل بنجاح
if (function_exists('updateStudentPoints')) {
    updateStudentPoints($pdo, $student_id);
    updateDailyStreak($pdo, $student_id);
    addPointsLog($pdo, $student_id, 3, 'daily_memorization', 'تسجيل حفظ يومي');
}
// جلب قائمة الطلاب
if (isTeacher()) {
    $students = $pdo->prepare("SELECT s.id, s.name, s.level FROM students s WHERE s.teacher_id = ? ORDER BY s.name");
    $students->execute([$teacher_id]);
    $students = $students->fetchAll();
} else {
    $students = $pdo->query("SELECT id, name, level FROM students ORDER BY name")->fetchAll();
}

$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student_goals = [];
$student_name = '';
$recent_records = [];

// جلب آخر 20 تسجيل للطالب المحدد
if ($selected_student > 0) {
    // جلب اسم الطالب
    $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $stmt->execute([$selected_student]);
    $student_name = $stmt->fetchColumn();
    
    // جلب أهداف الطالب للشهر الحالي
    $goals = $pdo->prepare("
        SELECT * FROM student_monthly_goals 
        WHERE student_id = ? AND hijri_year = ? AND hijri_month_id = ?
        ORDER BY id DESC
    ");
    $goals->execute([$selected_student, $hijri_year, $hijri_month]);
    $student_goals = $goals->fetchAll();
    
    // جلب آخر التسجيلات - بدون استخدام دالة SQL
    $records = $pdo->prepare("
        SELECT *
        FROM daily_memorization
        WHERE student_id = ?
        ORDER BY memorized_date DESC, id DESC
        LIMIT 20
    ");
    $records->execute([$selected_student]);
    $recent_records = $records->fetchAll();
    
    // إضافة أسماء السور باستخدام دالة PHP
    foreach ($recent_records as &$record) {
        $record['surah_name'] = getSurahName($record['surah_number']);
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
    --info: #17a2b8;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
.record-page { max-width: 800px; margin: 0 auto; padding: 10px; }

.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 20px;
    border-radius: 25px;
    margin-bottom: 20px;
    text-align: center;
}
.page-header h1 { font-size: 1.5rem; }
.page-header h1 i { color: var(--secondary); }

.student-selector {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.student-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 15px;
}
.student-item {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
    border: 2px solid transparent;
}
.student-item:hover { background: #e9ecef; }
.student-item.active { border-color: var(--secondary); background: #fff3cd; }

.record-form {
    background: white;
    border-radius: 25px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}
.form-title {
    color: var(--primary);
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--secondary);
}

.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--primary); }
.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    font-size: 1rem;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 15px;
    font-weight: 600;
    cursor: pointer;
    background: var(--primary);
    color: white;
    font-size: 1rem;
}

.progress-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
    border-right: 4px solid var(--secondary);
}
.progress-bar {
    height: 10px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 8px 0;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #20c997);
    border-radius: 10px;
}

.records-list {
    background: white;
    border-radius: 20px;
    padding: 15px;
}
.record-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-bottom: 1px solid #eee;
}
.record-date {
    background: var(--primary);
    color: white;
    padding: 5px 10px;
    border-radius: 10px;
    font-size: 0.8rem;
    min-width: 80px;
    text-align: center;
}
.record-details { flex: 1; }
.record-surah { font-weight: 600; color: var(--primary); }

@media (max-width: 480px) {
    .form-row { grid-template-columns: 1fr; }
    .student-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<section class="record-page">
    <div class="page-header">
        <h1><i class="fas fa-pen-alt"></i> تسجيل الحفظ اليومي</h1>
    </div>

    <?php if ($success_message): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 15px; margin-bottom: 15px;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 15px; margin-bottom: 15px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="student-selector">
        <h3 style="color: var(--primary); margin-bottom: 15px;">اختر الطالب</h3>
        <div class="student-grid">
            <?php foreach ($students as $s): ?>
                <div class="student-item <?php echo $selected_student == $s['id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='?student_id=<?php echo $s['id']; ?>'">
                    <i class="fas fa-user-circle"></i>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($s['name']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($selected_student > 0): ?>
        <form method="post" class="record-form">
            <h3 class="form-title"><i class="fas fa-save"></i> تسجيل حفظ جديد لـ <?php echo htmlspecialchars($student_name); ?></h3>
            <input type="hidden" name="student_id" value="<?php echo $selected_student; ?>">
            
            <div class="form-group">
                <label>ربط بهدف شهري</label>
                <select name="goal_id" class="form-control">
                    <option value="">-- بدون ربط --</option>
                    <?php foreach ($student_goals as $g): 
                        $range = "من سورة " . getSurahName($g['from_surah']);
                        if ($g['from_ayah']) $range .= " آية {$g['from_ayah']}";
                        $range .= " إلى سورة " . getSurahName($g['to_surah']);
                        if ($g['to_ayah']) $range .= " آية {$g['to_ayah']}";
                        $progress = $g['total_ayahs'] ? round(($g['memorized_ayahs'] / $g['total_ayahs']) * 100) : 0;
                    ?>
                        <option value="<?php echo $g['id']; ?>">
                            <?php echo $range; ?> (<?php echo $g['memorized_ayahs']; ?>/<?php echo $g['total_ayahs']; ?> - <?php echo $progress; ?>%)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>السورة</label>
                    <select name="surah_number" class="form-control" required>
                        <option value="">اختر</option>
                        <?php for ($i = 1; $i <= 114; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>التاريخ</label>
                    <input type="date" name="memorized_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>من آية</label>
                    <input type="number" name="from_ayah" class="form-control" min="1" placeholder="اختياري">
                </div>
                <div class="form-group">
                    <label>إلى آية</label>
                    <input type="number" name="to_ayah" class="form-control" min="1" placeholder="اختياري">
                </div>
            </div>
            
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <button type="submit" name="save_memorization" class="btn"><i class="fas fa-save"></i> تسجيل الحفظ</button>
        </form>

        <?php if (!empty($student_goals)): ?>
            <div style="margin: 20px 0;">
                <h3 style="color: var(--primary); margin-bottom: 15px;">تقدم الأهداف</h3>
                <?php foreach ($student_goals as $goal): 
                    $progress = $goal['total_ayahs'] ? round(($goal['memorized_ayahs'] / $goal['total_ayahs']) * 100) : 0;
                ?>
                    <div class="progress-card">
                        <div style="display: flex; justify-content: space-between;">
                            <strong>هدف الشهر</strong>
                            <span><?php echo $goal['memorized_ayahs']; ?>/<?php echo $goal['total_ayahs']; ?> آية</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div></div>
                        <?php if ($goal['completed']): ?>
                            <div style="color: var(--success); margin-top: 5px;"><i class="fas fa-check-circle"></i> اكتمل الهدف</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($recent_records)): ?>
            <div class="records-list">
                <h3 style="color: var(--primary); margin-bottom: 15px;">آخر التسجيلات</h3>
                <?php foreach ($recent_records as $rec): ?>
                    <div class="record-item">
                        <div class="record-date"><?php echo date('Y-m-d', strtotime($rec['memorized_date'])); ?></div>
                        <div class="record-details">
                            <div class="record-surah"><?php echo getSurahName($rec['surah_number']); ?></div>
                            <?php if ($rec['from_ayah'] && $rec['to_ayah']): ?>
                                <div>الآيات <?php echo $rec['from_ayah']; ?> - <?php echo $rec['to_ayah']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php ob_end_flush(); require_once 'includes/footer.php'; ?>