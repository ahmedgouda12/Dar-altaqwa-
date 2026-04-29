<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');
$pageTitle = 'الطلاب المثاليون';
require_once 'includes/header.php';

$month_year = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// تحديد الفئات المسموح رؤيتها حسب نوع المستخدم
$allowed_categories = [];

if (isAdmin()) {
    // الإدارة ترى الكل
    $allowed_categories = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
} elseif (isTeacher()) {
    // جلب جنس المعلم من قاعدة البيانات
    $stmt = $pdo->prepare("SELECT gender FROM teachers WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $teacher_gender = $stmt->fetchColumn();

    if ($teacher_gender == 'male') {
        // المعلم (رجل) يرى أولاد فقط
        $allowed_categories = ['boy' => 'أولاد'];
    } else {
        // المعلمة (امرأة) ترى بنات + أطفال + نساء
        $allowed_categories = [
            'girl' => 'بنات',
            'child' => 'أطفال',
            'woman' => 'نساء'
        ];
    }
} else {
    // الأدوار الأخرى (ولي أمر، طالب) يمكنها رؤية الكل أو ما يناسبها - سنسمح لهم برؤية الكل
    $allowed_categories = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
}

// دالة لترجمة الرتبة
function getRankName($rank) {
    switch($rank) {
        case 'first': return 'الأول';
        case 'second': return 'الثاني';
        case 'third': return 'الثالث';
        default: return $rank;
    }
}
?>

<section class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
        <h2 class="card-title"><i class="fas fa-crown"></i> الطلاب المثاليون</h2>
        <form method="get">
            <input type="month" name="month" value="<?php echo $month_year; ?>">
            <button type="submit" class="btn">عرض</button>
        </form>
    </div>

    <?php
    $has_any = false;

    foreach ($allowed_categories as $cat_key => $cat_name):
        // جلب المثاليين لهذه الفئة
        $stmt = $pdo->prepare("
            SELECT som.*, s.name 
            FROM students_of_month som
            JOIN students s ON som.student_id = s.id
            WHERE som.month_year = ? AND som.category = ?
            ORDER BY FIELD(som.rank, 'first', 'second', 'third')
        ");
        $stmt->execute([$month_year, $cat_key]);
        $winners = $stmt->fetchAll();

        if (!empty($winners)):
            $has_any = true;
    ?>
            <div class="card" style="margin-bottom: 20px; border-right: 5px solid <?php
                if ($cat_key == 'boy') echo '#3498db';
                elseif ($cat_key == 'girl') echo '#9b59b6';
                elseif ($cat_key == 'child') echo '#f39c12';
                else echo '#e7d4f0';
            ?>;">
                <h3 style="color: #1e3c3f;">🏆 فئة <?php echo $cat_name; ?></h3>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; margin-top: 15px;">
                    <?php foreach ($winners as $winner): ?>
                        <div style="flex: 1; min-width: 200px; text-align: center; padding: 20px; border-radius: 15px; background: <?php 
                            if($winner['rank']=='first') echo 'linear-gradient(135deg, #f9e5b7, #f5d79c)';
                            elseif($winner['rank']=='second') echo 'linear-gradient(135deg, #eaeaea, #d5d5d5)';
                            else echo 'linear-gradient(135deg, #f0ddd4, #e5c7b8)';
                        ?>;">
                            <div style="font-size: 4rem;">
                                <?php if($winner['rank']=='first'): ?>🥇
                                <?php elseif($winner['rank']=='second'): ?>🥈
                                <?php else: ?>🥉
                                <?php endif; ?>
                            </div>
                            <h3 style="margin: 10px 0;"><?php echo htmlspecialchars($winner['name']); ?></h3>
                            <p style="margin: 5px 0;">
                                <strong><?php echo getRankName($winner['rank']); ?></strong><br>
                                <?php if (!empty($winner['attendance_rate'])): ?>
                                    حضور: <?php echo $winner['attendance_rate']; ?>%<br>
                                <?php endif; ?>
                                <?php if (!empty($winner['level_achievement'])): ?>
                                    <small><?php echo $winner['level_achievement']; ?></small>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!$has_any): ?>
        <div style="text-align: center; padding: 50px;">
            <i class="fas fa-crown" style="font-size: 60px; color: #95a5a6;"></i>
            <p style="margin-top: 20px;">لا يوجد طلاب مثاليون لهذا الشهر بعد.</p>
            <?php if (isAdmin()): ?>
                <a href="calculate_stars.php" class="btn">حساب المثاليين الآن</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>