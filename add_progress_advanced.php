<?php
require_once 'config.php';
if (!isTeacher() && !isAdmin()) redirect('login.php');
$pageTitle = 'تسجيل تقدم الحفظ';
require_once 'includes/header.php';

// جلب قائمة الطلاب حسب الصلاحية
if (isAdmin()) {
    $students = $pdo->query("SELECT id, name FROM students ORDER BY name")->fetchAll();
} else {
    $students = $pdo->prepare("SELECT id, name FROM students WHERE teacher_id = ? ORDER BY name");
    $students->execute([$_SESSION['user_id']]);
    $students = $students->fetchAll();
}

// جلب قائمة السور (إذا وجدت)
$surahs = $pdo->query("SELECT id, name, number FROM surahs ORDER BY number")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $surah_id = !empty($_POST['surah_id']) ? (int)$_POST['surah_id'] : null;
    $surah_name = trim($_POST['surah_name'] ?? '');
    $from_page = !empty($_POST['from_page']) ? (int)$_POST['from_page'] : null;
    $to_page = !empty($_POST['to_page']) ? (int)$_POST['to_page'] : null;
    $from_ayah = !empty($_POST['from_ayah']) ? (int)$_POST['from_ayah'] : null;
    $to_ayah = !empty($_POST['to_ayah']) ? (int)$_POST['to_ayah'] : null;
    $review_text = trim($_POST['review_text'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');

    // التحقق من وجود طالب
    if (empty($student_id)) {
        $error = 'يجب اختيار الطالب.';
    } elseif (empty($surah_id) && empty($surah_name)) {
        $error = 'يجب تحديد اسم السورة.';
    } elseif (empty($from_page) && empty($from_ayah)) {
        $error = 'يجب تحديد رقم البداية (صفحة أو آية).';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO student_memorization 
                (student_id, surah_id, surah_name, from_page, to_page, from_ayah, to_ayah, review_text, date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id,
                $surah_id,
                $surah_name ?: null,
                $from_page,
                $to_page,
                $from_ayah,
                $to_ayah,
                $review_text,
                $date
            ]);
            $success = 'تم تسجيل التقدم بنجاح.';
        } catch (PDOException $e) {
            $error = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
        }
    }
}
?>

<section class="card" style="max-width: 800px; margin: 0 auto;">
    <h2 class="card-title"><i class="fas fa-book-open"></i> تسجيل تقدم الحفظ</h2>

    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>الطالب</label>
            <select name="student_id" required>
                <option value="">-- اختر --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>السورة</label>
            <select name="surah_id" id="surahSelect">
                <option value="">-- اختر سورة (اختياري) --</option>
                <?php foreach ($surahs as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <small>أو أدخل اسم السورة يدوياً:</small>
            <input type="text" name="surah_name" placeholder="مثال: البقرة">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>من صفحة</label>
                <input type="number" name="from_page" min="1" max="604" placeholder="رقم الصفحة">
            </div>
            <div class="form-group">
                <label>إلى صفحة</label>
                <input type="number" name="to_page" min="1" max="604" placeholder="اختياري">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>من آية</label>
                <input type="number" name="from_ayah" min="1" placeholder="رقم الآية">
            </div>
            <div class="form-group">
                <label>إلى آية</label>
                <input type="number" name="to_ayah" min="1" placeholder="اختياري">
            </div>
        </div>

        <div class="form-group">
            <label>مراجعة / ملاحظات</label>
            <textarea name="review_text" rows="4" placeholder="أدخل أي ملاحظات أو مراجعة هنا..."></textarea>
        </div>

        <div class="form-group">
            <label>التاريخ</label>
            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <button type="submit" class="btn"><i class="fas fa-save"></i> حفظ التقدم</button>
        <a href="view_advanced_progress.php" class="btn">عرض التقدم</a>
    </form>
</section>

<?php require_once 'includes/footer.php'; ?>