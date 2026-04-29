<?php
// ============================================
// teacher_ring_achievements.php - نسخة مصححة
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// التحقق من أن المستخدم معلم
if (!isTeacher()) {
    echo "❌ هذه الصفحة مخصصة للمعلمين فقط";
    exit;
}

$pageTitle = 'إنجازات حلقة التحفيظ';
$teacher_id = $_SESSION['user_id'];
$current_hijri_year = date('Y'); // مؤقتاً نستخدم السنة الميلادية

// جلب حلقات المعلم
try {
    $rings = $pdo->prepare("
        SELECT r.*, 
               (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
        FROM rings r
        WHERE r.teacher_id = ?
        ORDER BY r.name
    ");
    $rings->execute([$teacher_id]);
    $rings = $rings->fetchAll();
} catch (PDOException $e) {
    echo "خطأ في جلب الحلقات: " . $e->getMessage();
    $rings = [];
}

// معالجة حفظ الإنجازات
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_achievement'])) {
    $ring_id = (int)$_POST['ring_id'];
    $start_surah = (int)$_POST['start_surah'];
    $end_surah = (int)$_POST['end_surah'];
    $total_memorized_surahs = (int)$_POST['total_memorized_surahs'];
    $students_completed_quran = (int)$_POST['students_completed_quran'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ring_achievements 
            (ring_id, teacher_id, hijri_year, start_surah, end_surah, 
             total_memorized_surahs, students_completed_quran, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')
            ON DUPLICATE KEY UPDATE
            start_surah = VALUES(start_surah),
            end_surah = VALUES(end_surah),
            total_memorized_surahs = VALUES(total_memorized_surahs),
            students_completed_quran = VALUES(students_completed_quran),
            updated_at = NOW()
        ");
        $stmt->execute([$ring_id, $teacher_id, $current_hijri_year, $start_surah, $end_surah, $total_memorized_surahs, $students_completed_quran]);
        $message = '<div style="background:#d4edda; color:#155724; padding:10px; border-radius:10px; margin-bottom:15px;">✅ تم حفظ الإنجازات بنجاح</div>';
    } catch (PDOException $e) {
        $message = '<div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:10px; margin-bottom:15px;">❌ خطأ: ' . $e->getMessage() . '</div>';
    }
}

require_once 'includes/header.php';
?>

<style>
    .achievements-container {
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
    .rings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }
    .ring-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border: 1px solid #eee;
    }
    .ring-header {
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 12px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ring-name {
        font-weight: bold;
        color: #1e3c3f;
        font-size: 1.1rem;
    }
    .ring-students {
        background: #c9a96b;
        color: #1e3c3f;
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.8rem;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #1e3c3f;
        font-size: 0.9rem;
    }
    .form-control {
        width: 100%;
        padding: 10px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 0.9rem;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .btn-save {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border: none;
        border-radius: 30px;
        font-weight: bold;
        cursor: pointer;
        margin-top: 15px;
    }
    .btn-save:hover {
        transform: translateY(-2px);
    }
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 20px;
    }
    @media (max-width: 768px) {
        .rings-grid {
            grid-template-columns: 1fr;
        }
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="achievements-container">
    <div class="page-header">
        <h1><i class="fas fa-trophy"></i> إنجازات حلقة التحفيظ</h1>
        <p>سجل محفوظات حلقاتك خلال دورة التحفيظ - العام <?php echo $current_hijri_year; ?></p>
    </div>

    <?php echo $message; ?>

    <?php if (empty($rings)): ?>
        <div class="empty-state">
            <i class="fas fa-ring" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3 style="margin-top: 15px;">لا توجد حلقات</h3>
            <p>لم تقم بإضافة أي حلقة بعد</p>
            <a href="add_ring.php" class="btn" style="background: #1e3c3f; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: inline-block;">إضافة حلقة</a>
        </div>
    <?php else: ?>
        <div class="rings-grid">
            <?php foreach ($rings as $ring): ?>
                <?php
                // جلب الإنجازات السابقة إن وجدت
                $achievement = $pdo->prepare("
                    SELECT * FROM ring_achievements 
                    WHERE ring_id = ? AND hijri_year = ?
                ");
                $achievement->execute([$ring['id'], $current_hijri_year]);
                $prev = $achievement->fetch();
                ?>
                <div class="ring-card">
                    <div class="ring-header">
                        <div class="ring-name">
                            <i class="fas fa-ring"></i> <?php echo htmlspecialchars($ring['name']); ?>
                        </div>
                        <div class="ring-students">
                            <i class="fas fa-users"></i> <?php echo $ring['students_count']; ?> طالب
                        </div>
                    </div>
                    
                    <form method="post">
                        <input type="hidden" name="ring_id" value="<?php echo $ring['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-play-circle"></i> بداية الدورة (من سورة)</label>
                                <select name="start_surah" class="form-control" required>
                                    <option value="">اختر السورة</option>
                                    <?php for ($i = 1; $i <= 114; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($prev && $prev['start_surah'] == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>. <?php echo getSurahName($i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-stop-circle"></i> نهاية الدورة (إلى سورة)</label>
                                <select name="end_surah" class="form-control" required>
                                    <option value="">اختر السورة</option>
                                    <?php for ($i = 1; $i <= 114; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($prev && $prev['end_surah'] == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>. <?php echo getSurahName($i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-quran"></i> عدد السور المحفوظة</label>
                                <input type="number" name="total_memorized_surahs" class="form-control" value="<?php echo $prev['total_memorized_surahs'] ?? 0; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-crown"></i> عدد الطلاب الخاتمين</label>
                                <input type="number" name="students_completed_quran" class="form-control" value="<?php echo $prev['students_completed_quran'] ?? 0; ?>" min="0">
                            </div>
                        </div>
                        
                        <button type="submit" name="save_achievement" class="btn-save">
                            <i class="fas fa-save"></i> حفظ الإنجازات
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>