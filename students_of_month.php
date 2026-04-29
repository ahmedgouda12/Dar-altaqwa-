<?php
require_once 'config.php';
if (!isAdmin()) redirect('login.php');
$pageTitle = 'إدارة الطلاب المثاليين';
require_once 'includes/header.php';

$current_month = date('Y-m');
$error = '';
$success = '';

// تعريف الفئات
$categories = [
    'boy' => 'أولاد',
    'girl' => 'بنات',
    'child' => 'أطفال',
    'woman' => 'نساء'
];

// جلب الطلاب مع نسب الحضور (مقسمة حسب الفئة)
$students_by_category = [];
foreach ($categories as $cat_key => $cat_name) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.level,
               (SELECT COUNT(*) FROM attendance WHERE person_type='student' AND person_id=s.id AND MONTH(date)=MONTH(CURDATE()) AND status='present') as present_count,
               (SELECT COUNT(*) FROM attendance WHERE person_type='student' AND person_id=s.id AND MONTH(date)=MONTH(CURDATE())) as total_days
        FROM students s
        WHERE s.category = ?
        ORDER BY s.name
    ");
    $stmt->execute([$cat_key]);
    $students_by_category[$cat_key] = $stmt->fetchAll();
}

// جلب المثاليين الحاليين لهذا الشهر
$current_winners = [];
foreach ($categories as $cat_key => $cat_name) {
    $stmt = $pdo->prepare("SELECT * FROM students_of_month WHERE month_year = ? AND category = ?");
    $stmt->execute([$current_month, $cat_key]);
    $current_winners[$cat_key] = [];
    foreach ($stmt->fetchAll() as $winner) {
        $current_winners[$cat_key][$winner['rank']] = $winner['student_id'];
    }
}

// معالجة حفظ المثاليين
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month_year = $_POST['month_year'] ?? $current_month;
    
    // حذف القديم لهذا الشهر (لجميع الفئات)
    $pdo->prepare("DELETE FROM students_of_month WHERE month_year = ?")->execute([$month_year]);
    
    // إضافة الجدد لكل فئة
    foreach ($categories as $cat_key => $cat_name) {
        $first = isset($_POST[$cat_key . '_first']) ? (int)$_POST[$cat_key . '_first'] : 0;
        $second = isset($_POST[$cat_key . '_second']) ? (int)$_POST[$cat_key . '_second'] : 0;
        $third = isset($_POST[$cat_key . '_third']) ? (int)$_POST[$cat_key . '_third'] : 0;
        
        if ($first) {
            $stmt = $pdo->prepare("INSERT INTO students_of_month (student_id, month_year, category, rank, awarded_by) VALUES (?, ?, ?, 'first', ?)");
            $stmt->execute([$first, $month_year, $cat_key, $_SESSION['user_id']]);
        }
        if ($second) {
            $stmt = $pdo->prepare("INSERT INTO students_of_month (student_id, month_year, category, rank, awarded_by) VALUES (?, ?, ?, 'second', ?)");
            $stmt->execute([$second, $month_year, $cat_key, $_SESSION['user_id']]);
        }
        if ($third) {
            $stmt = $pdo->prepare("INSERT INTO students_of_month (student_id, month_year, category, rank, awarded_by) VALUES (?, ?, ?, 'third', ?)");
            $stmt->execute([$third, $month_year, $cat_key, $_SESSION['user_id']]);
        }
    }
    
    $success = "تم تحديث قائمة الطلاب المثاليين لجميع الفئات بنجاح.";
    
    // تحديث المتغيرات لعرض الجدد
    foreach ($categories as $cat_key => $cat_name) {
        $stmt = $pdo->prepare("SELECT * FROM students_of_month WHERE month_year = ? AND category = ?");
        $stmt->execute([$month_year, $cat_key]);
        $current_winners[$cat_key] = [];
        foreach ($stmt->fetchAll() as $winner) {
            $current_winners[$cat_key][$winner['rank']] = $winner['student_id'];
        }
    }
}
?>

<section class="card">
    <h2 class="card-title"><i class="fas fa-crown"></i> تحديد الطلاب المثاليين للشهر (حسب الفئة)</h2>

    <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>الشهر والسنة</label>
            <input type="month" name="month_year" value="<?php echo $current_month; ?>" required>
        </div>

        <?php foreach ($categories as $cat_key => $cat_name): ?>
            <div style="margin-bottom: 30px; padding: 20px; border: 2px solid #e0e0e0; border-radius: 15px;">
                <h3 style="color: #1e3c3f; margin-bottom: 15px;">🏆 فئة <?php echo $cat_name; ?></h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <!-- الأول -->
                    <div style="border-right: 4px solid gold; padding: 10px;">
                        <label style="font-weight: bold; color: gold;">🥇 الأول</label>
                        <select name="<?php echo $cat_key; ?>_first">
                            <option value="">-- اختر --</option>
                            <?php foreach ($students_by_category[$cat_key] as $s): 
                                $attendance_percent = $s['total_days'] > 0 ? round(($s['present_count'] / $s['total_days']) * 100) : 0;
                            ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo (isset($current_winners[$cat_key]['first']) && $current_winners[$cat_key]['first'] == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> - مستوى: <?php echo $s['level']; ?> (حضور <?php echo $attendance_percent; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- الثاني -->
                    <div style="border-right: 4px solid silver; padding: 10px;">
                        <label style="font-weight: bold; color: silver;">🥈 الثاني</label>
                        <select name="<?php echo $cat_key; ?>_second">
                            <option value="">-- اختر --</option>
                            <?php foreach ($students_by_category[$cat_key] as $s): 
                                $attendance_percent = $s['total_days'] > 0 ? round(($s['present_count'] / $s['total_days']) * 100) : 0;
                            ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo (isset($current_winners[$cat_key]['second']) && $current_winners[$cat_key]['second'] == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> - مستوى: <?php echo $s['level']; ?> (حضور <?php echo $attendance_percent; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- الثالث -->
                    <div style="border-right: 4px solid #cd7f32; padding: 10px;">
                        <label style="font-weight: bold; color: #cd7f32;">🥉 الثالث</label>
                        <select name="<?php echo $cat_key; ?>_third">
                            <option value="">-- اختر --</option>
                            <?php foreach ($students_by_category[$cat_key] as $s): 
                                $attendance_percent = $s['total_days'] > 0 ? round(($s['present_count'] / $s['total_days']) * 100) : 0;
                            ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo (isset($current_winners[$cat_key]['third']) && $current_winners[$cat_key]['third'] == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> - مستوى: <?php echo $s['level']; ?> (حضور <?php echo $attendance_percent; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn"><i class="fas fa-save"></i> حفظ المثاليين لجميع الفئات</button>
        <a href="dashboard.php" class="btn">إلغاء</a>
    </form>
</section>

<?php require_once 'includes/footer.php'; ?>