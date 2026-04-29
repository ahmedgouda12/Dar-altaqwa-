<?php
require_once 'config.php';
if (!isTeacher()) redirect('login.php');
$pageTitle = 'تسجيل تقدم طالب';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$students = $pdo->prepare("SELECT id, name FROM students WHERE teacher_id = ?");
$students->execute([$teacher_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $surah = $_POST['surah'];
    $from_ayah = $_POST['from_ayah'] ?: null;
    $to_ayah = $_POST['to_ayah'] ?: null;
    $review = $_POST['review'];
    
    $stmt = $pdo->prepare("INSERT INTO student_progress (student_id, date, surah, from_ayah, to_ayah, review) VALUES (?, CURDATE(), ?, ?, ?, ?)");
    $stmt->execute([$student_id, $surah, $from_ayah, $to_ayah, $review]);
    $success = "تم تسجيل التقدم";
}
?>
<!-- نموذج الإدخال (بسيط) -->
<form method="post">
    <select name="student_id" required>
        <?php foreach($students as $s): ?>
        <option value="<?=$s['id']?>"><?=$s['name']?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="surah" placeholder="اسم السورة" required>
    <input type="number" name="from_ayah" placeholder="من آية">
    <input type="number" name="to_ayah" placeholder="إلى آية">
    <textarea name="review" placeholder="ملاحظات"></textarea>
    <button type="submit">حفظ</button>
</form>