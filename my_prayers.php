<?php
// ============================================
// ملف: my_prayers.php
// صفحة تسجيل الصلوات - تصميم مميز مع أسماء الصلوات الظاهرة
// آخر تحديث: 2026-04-25
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'includes/wirds_functions.php';

if (!isStudent()) {
    redirect('login.php');
}

$pageTitle = 'صلواتي';
require_once 'includes/header.php';

$student_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$prayers = getActivePrayers($pdo);
$message = '';
$message_type = '';

// معالجة تسجيل الصلاة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_prayer'])) {
    $prayer_id = (int)$_POST['prayer_id'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    $is_jamaa = ($status == 'jamaa');
    $points = 0;
    
    $prayer = $pdo->prepare("SELECT * FROM prayers WHERE id = ?");
    $prayer->execute([$prayer_id]);
    $prayer_data = $prayer->fetch();
    
    if ($prayer_data) {
        if ($status == 'jamaa') {
            $points = $prayer_data['points_jamaa'];
        } elseif ($status == 'on_time') {
            $points = $prayer_data['points_on_time'];
        } elseif ($status == 'late') {
            $points = $prayer_data['points_late'];
        } else {
            $points = 0;
        }
    }
    
    try {
        $check = getStudentPrayerRecord($pdo, $student_id, $prayer_id, $today);
        
        if ($check) {
            $stmt = $pdo->prepare("
                UPDATE student_prayer_records SET
                    status = ?, is_jamaa = ?, points_earned = ?,
                    notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $is_jamaa, $points, $notes, $check['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO student_prayer_records 
                (student_id, prayer_id, prayer_date, status, is_jamaa, points_earned, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$student_id, $prayer_id, $today, $status, $is_jamaa, $points, $notes]);
        }
        
        updateTotalStudentPoints($pdo, $student_id);
        
        $message = "✅ تم تسجيل صلاة {$prayer_data['prayer_name']} بنجاح! +{$points} نقطة";
        $message_type = 'success';
        
    } catch (PDOException $e) {
        $message = "❌ حدث خطأ: " . $e->getMessage();
        $message_type = 'error';
    }
}

// جلب تسجيلات اليوم لكل صلاة
$records = [];
foreach ($prayers as $prayer) {
    $records[$prayer['id']] = getStudentPrayerRecord($pdo, $student_id, $prayer['id'], $today);
}

// أفضل الطلاب في الصلوات
$topPrayers = getTopStudentsByPrayers($pdo, 5);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>صلواتي - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --whatsapp: #25d366;
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
            --transition-bounce: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .prayers-page {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* ===== رأس الصفحة ===== */
        .hero-section {
            background: var(--gradient-secondary);
            border-radius: var(--radius-xl);
            padding: 35px 30px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-section h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .hero-section h1 i {
            color: var(--secondary);
            animation: starPulse 2s infinite;
        }
        
        @keyframes starPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .hero-section p {
            opacity: 0.9;
        }
        
        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: var(--radius-full);
            margin-top: 15px;
            backdrop-filter: blur(5px);
        }
        
        /* ===== إحصائيات ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        /* ===== بطاقات الصلوات ===== */
        .prayers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .prayer-card {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition-bounce);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .prayer-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }
        
        /* شريط علوي حسب الصلاة */
        .card-strip {
            height: 6px;
            background: var(--gradient-secondary);
        }
        
        .card-strip.fajr { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .card-strip.dhuhr { background: linear-gradient(90deg, #3498db, #2980b9); }
        .card-strip.asr { background: linear-gradient(90deg, #2ecc71, #27ae60); }
        .card-strip.maghrib { background: linear-gradient(90deg, #e74c3c, #c0392b); }
        .card-strip.isha { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
        
        .card-content {
            padding: 25px;
        }
        
        /* رأس البطاقة مع اسم الصلاة واضح */
        .prayer-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px dashed var(--gray-light);
        }
        
        .prayer-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--secondary);
            border: 3px solid var(--secondary);
            box-shadow: var(--shadow-md);
        }
        
        .prayer-info {
            flex: 1;
        }
        
        .prayer-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .prayer-time {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--info-light);
            padding: 4px 15px;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            color: #0c5460;
        }
        
        .points-info {
            text-align: center;
            background: var(--gray-light);
            padding: 10px;
            border-radius: var(--radius-lg);
            margin-bottom: 15px;
        }
        
        .points-info span {
            font-weight: 700;
            color: var(--secondary);
        }
        
        /* ===== خيارات الحالة ===== */
        .status-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 15px 0;
        }
        
        .status-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition-bounce);
            border: 2px solid transparent;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .status-option i {
            font-size: 1.2rem;
        }
        
        .status-option.jamaa {
            background: var(--info-light);
            color: #0c5460;
            border-color: var(--info);
        }
        
        .status-option.jamaa.selected {
            background: var(--info);
            color: white;
        }
        
        .status-option.on_time {
            background: var(--success-light);
            color: #155724;
            border-color: var(--success);
        }
        
        .status-option.on_time.selected {
            background: var(--success);
            color: white;
        }
        
        .status-option.late {
            background: var(--warning-light);
            color: #856404;
            border-color: var(--warning);
        }
        
        .status-option.late.selected {
            background: var(--warning);
            color: #212529;
        }
        
        .status-option.missed {
            background: var(--danger-light);
            color: #721c24;
            border-color: var(--danger);
        }
        
        .status-option.missed.selected {
            background: var(--danger);
            color: white;
        }
        
        .status-option input {
            display: none;
        }
        
        .current-status {
            background: #f8f9fa;
            padding: 12px;
            border-radius: var(--radius-lg);
            text-align: center;
            margin: 15px 0;
        }
        
        .points-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .btn-save {
            width: 100%;
            padding: 14px;
            background: var(--gradient-success);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition-bounce);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-gold);
        }
        
        /* ===== أفضل الطلاب ===== */
        .top-students-section {
            background: white;
            border-radius: var(--radius-xl);
            padding: 25px;
            margin-top: 20px;
            box-shadow: var(--shadow-lg);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--secondary);
            color: var(--primary);
        }
        
        .section-title i {
            font-size: 1.5rem;
            color: var(--secondary);
        }
        
        .top-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .top-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--radius-lg);
            transition: var(--transition);
        }
        
        .top-item:hover {
            transform: translateX(-5px);
            background: #e9ecef;
        }
        
        .top-rank {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: white;
        }
        
        .rank-1 { background: gold; color: #212529; }
        .rank-2 { background: silver; color: #212529; }
        .rank-3 { background: #cd7f32; }
        .rank-other { background: #6c757d; }
        
        /* ===== رسائل ===== */
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: var(--success-light);
            color: #155724;
            border-right: 5px solid var(--success);
        }
        
        .alert-error {
            background: var(--danger-light);
            color: #721c24;
            border-right: 5px solid var(--danger);
        }
        
        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .prayers-grid { grid-template-columns: 1fr; }
            .status-options { grid-template-columns: 1fr; }
            .hero-section h1 { font-size: 1.5rem; }
            .prayer-name { font-size: 1.3rem; }
            .prayer-icon { width: 55px; height: 55px; font-size: 1.5rem; }
        }
        
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .top-list { grid-template-columns: 1fr; }
            .prayer-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<div class="prayers-page">
    <!-- رأس الصفحة المميز -->
    <div class="hero-section">
        <div class="hero-content">
            <h1>
                <i class="fas fa-mosque"></i>
                صلواتي
            </h1>
            <p>سجل صلواتك اليومية في المسجد أو في جماعة واحصل على النقاط</p>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i>
                <?php echo $today; ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- بطاقات الصلوات -->
    <div class="prayers-grid">
        <?php foreach ($prayers as $prayer): 
            $record = $records[$prayer['id']] ?? null;
            $current_status = $record['status'] ?? 'missed';
            $points = $record['points_earned'] ?? 0;
            
            // تحديد لون شريط البطاقة
            $strip_class = '';
            $prayer_name_clean = str_replace(' ', '_', $prayer['prayer_name']);
            if (strpos($prayer_name_clean, 'فجر') !== false) $strip_class = 'fajr';
            elseif (strpos($prayer_name_clean, 'ظهر') !== false) $strip_class = 'dhuhr';
            elseif (strpos($prayer_name_clean, 'عصر') !== false) $strip_class = 'asr';
            elseif (strpos($prayer_name_clean, 'مغرب') !== false) $strip_class = 'maghrib';
            elseif (strpos($prayer_name_clean, 'عشاء') !== false) $strip_class = 'isha';
        ?>
            <div class="prayer-card">
                <div class="card-strip <?php echo $strip_class; ?>"></div>
                <div class="card-content">
                    <div class="prayer-header">
                        <div class="prayer-icon">
                            <i class="fas fa-mosque"></i>
                        </div>
                        <div class="prayer-info">
                            <div class="prayer-name">صلاة <?php echo $prayer['prayer_name']; ?></div>
                            <div class="prayer-time">
                                <i class="fas fa-clock"></i>
                                وقتها: <?php echo $prayer['prayer_time']; ?>
                            </div>
                        </div>
                    </div>

                    <div class="points-info">
                        <i class="fas fa-star" style="color: var(--secondary);"></i>
                        <span><?php echo $prayer['points_jamaa']; ?> نقطة</span> للجماعة |
                        <span><?php echo $prayer['points_on_time']; ?> نقطة</span> في الوقت |
                        <span><?php echo $prayer['points_late']; ?> نقطة</span> متأخر
                    </div>

                    <form method="post" class="prayer-form">
                        <input type="hidden" name="prayer_id" value="<?php echo $prayer['id']; ?>">
                        
                        <div class="status-options">
                            <label class="status-option jamaa <?php echo $current_status == 'jamaa' ? 'selected' : ''; ?>">
                                <i class="fas fa-users"></i>
                                <span>صلاة جماعة</span>
                                <input type="radio" name="status" value="jamaa" <?php echo $current_status == 'jamaa' ? 'checked' : ''; ?>>
                            </label>
                            <label class="status-option on_time <?php echo $current_status == 'on_time' ? 'selected' : ''; ?>">
                                <i class="fas fa-check-circle"></i>
                                <span>في الوقت</span>
                                <input type="radio" name="status" value="on_time" <?php echo $current_status == 'on_time' ? 'checked' : ''; ?>>
                            </label>
                            <label class="status-option late <?php echo $current_status == 'late' ? 'selected' : ''; ?>">
                                <i class="fas fa-clock"></i>
                                <span>متأخر</span>
                                <input type="radio" name="status" value="late" <?php echo $current_status == 'late' ? 'checked' : ''; ?>>
                            </label>
                            <label class="status-option missed <?php echo $current_status == 'missed' ? 'selected' : ''; ?>">
                                <i class="fas fa-times-circle"></i>
                                <span>لم أصل</span>
                                <input type="radio" name="status" value="missed" <?php echo $current_status == 'missed' ? 'checked' : ''; ?>>
                            </label>
                        </div>

                        <div class="current-status">
                            <?php if ($current_status == 'jamaa'): ?>
                                <i class="fas fa-users"></i> ✅ تم التسجيل - صلاة جماعة
                                <?php if ($points > 0): ?>
                                    <div class="points-badge" style="background: var(--info); color: white;">
                                        <i class="fas fa-star"></i> +<?php echo $points; ?> نقطة
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($current_status == 'on_time'): ?>
                                <i class="fas fa-check-circle"></i> ✅ تم التسجيل - في الوقت
                                <?php if ($points > 0): ?>
                                    <div class="points-badge" style="background: var(--success); color: white;">
                                        <i class="fas fa-star"></i> +<?php echo $points; ?> نقطة
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($current_status == 'late'): ?>
                                <i class="fas fa-clock"></i> ⏰ تم التسجيل - متأخر
                                <?php if ($points > 0): ?>
                                    <div class="points-badge" style="background: var(--warning); color: #212529;">
                                        <i class="fas fa-star"></i> +<?php echo $points; ?> نقطة
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($current_status == 'missed'): ?>
                                <i class="fas fa-times-circle"></i> ❌ لم تسجل بعد
                            <?php endif; ?>
                        </div>

                        <button type="submit" name="record_prayer" class="btn-save">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- أفضل الطلاب في الصلوات -->
    <?php if (!empty($topPrayers)): ?>
    <div class="top-students-section">
        <div class="section-title">
            <i class="fas fa-crown" style="color: gold;"></i>
            <h3>أفضل الطلاب في الصلوات</h3>
        </div>
        <div class="top-list">
            <?php foreach ($topPrayers as $index => $student): 
                $rank = $index + 1;
                $rank_class = $rank <= 3 ? $rank : 'other';
            ?>
                <div class="top-item">
                    <div class="top-rank rank-<?php echo $rank_class; ?>">
                        <?php echo $rank; ?>
                    </div>
                    <div style="flex: 1;">
                        <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                        <br><small><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></small>
                    </div>
                    <div style="text-align: left;">
                        <span style="color: var(--info); font-weight: bold;"><?php echo number_format($student['total_points']); ?></span>
                        <small>نقطة</small>
                        <br>
                        <small>جماعة: <?php echo $student['jamaa_count']; ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// تفعيل خيارات الحالة
document.querySelectorAll('.status-option').forEach(option => {
    option.addEventListener('click', function() {
        const parent = this.closest('.status-options');
        const radio = this.querySelector('input[type="radio"]');
        
        if (radio) {
            radio.checked = true;
            
            // إزالة التحديد من جميع الخيارات في نفس المجموعة
            parent.querySelectorAll('.status-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // إضافة التحديد للخيار المختار
            this.classList.add('selected');
        }
    });
});

console.log('✅ صفحة تسجيل الصلوات - التصميم المميز جاهز');
</script>

<?php require_once 'includes/footer.php'; ?>