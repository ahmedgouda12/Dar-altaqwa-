<?php
// ============================================
// ملف: guardian_daily_memorization.php
// تسجيل الحفظ اليومي - تصميم مميز وعصري
// آخر تحديث: 2026-04-25
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isGuardian() && !isStudent() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تسجيل الحفظ اليومي - تصميم مميز';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$today = date('Y-m-d');
$today_day = date('w');

$is_friday = ($today_day == 5);
$current_month = date('F Y');

// جلب الطلاب
if (isGuardian()) {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.category,
               (SELECT COUNT(*) FROM daily_memorization_records 
                WHERE student_id = s.id AND record_date = ?) as recorded_today,
               (SELECT COUNT(*) FROM daily_memorization_records 
                WHERE student_id = s.id AND MONTH(record_date) = MONTH(CURDATE())) as monthly_records
        FROM students s
        WHERE s.guardian_id = ?
        ORDER BY s.name
    ");
    $students->execute([$today, $user_id]);
} elseif (isTeacher()) {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.category,
               (SELECT COUNT(*) FROM daily_memorization_records 
                WHERE student_id = s.id AND record_date = ?) as recorded_today,
               (SELECT COUNT(*) FROM daily_memorization_records 
                WHERE student_id = s.id AND MONTH(record_date) = MONTH(CURDATE())) as monthly_records
        FROM students s
        WHERE s.teacher_id = ?
        ORDER BY s.name
    ");
    $students->execute([$today, $user_id]);
} else {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.level, s.category,
               (SELECT COUNT(*) FROM daily_memorization_records 
                WHERE student_id = s.id AND record_date = ?) as recorded_today,
               (SELECT COUNT(*) FROM daily_memorization_records 
                WHERE student_id = s.id AND MONTH(record_date) = MONTH(CURDATE())) as monthly_records
        FROM students s
        WHERE s.id = ?
    ");
    $students->execute([$today, $user_id]);
}
$students = $students->fetchAll();

// معالجة تسجيل الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_memorization'])) {
    $student_id = (int)$_POST['student_id'];
    $new_memorization = isset($_POST['new_memorization']) ? 1 : 0;
    $recent_review = isset($_POST['recent_review']) ? 1 : 0;
    $old_review = isset($_POST['old_review']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');
    $memorization_details = trim($_POST['memorization_details'] ?? '');
    
    if ($is_friday) {
        $_SESSION['error'] = "⚠️ يوم الجمعة إجازة رسمية";
        header("Location: guardian_daily_memorization.php");
        exit;
    }
    
    $check = $pdo->prepare("SELECT id FROM daily_memorization_records WHERE student_id = ? AND record_date = ?");
    $check->execute([$student_id, $today]);
    
    if ($check->fetch()) {
        $_SESSION['error'] = "⚠️ تم تسجيل الحفظ اليوم بالفعل";
        header("Location: guardian_daily_memorization.php");
        exit;
    }
    
    $points_earned = ($new_memorization ? 10 : 0) + ($recent_review ? 10 : 0) + ($old_review ? 10 : 0);
    $quality_rating = 'good';
    if ($new_memorization && $recent_review && $old_review) {
        $quality_rating = 'excellent';
        $points_earned += 5;
    } elseif ($new_memorization || $recent_review || $old_review) {
        $quality_rating = 'good';
    } else {
        $quality_rating = 'average';
    }
    
    try {
        $pdo->beginTransaction();
        
        $memorized_surahs_text = '';
        if ($new_memorization) $memorized_surahs_text .= "حفظ جديد ";
        if ($recent_review) $memorized_surahs_text .= "مراجعة قريبة ";
        if ($old_review) $memorized_surahs_text .= "مراجعة بعيدة ";
        
        $stmt = $pdo->prepare("
            INSERT INTO daily_memorization_records 
            (student_id, record_date, memorized_ayahs, memorized_pages, 
             memorized_surahs, review_ayahs, quality_rating, notes, recorded_by, recorded_by_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $memorized_ayahs = $new_memorization ? 10 : 0;
        $review_ayahs = ($recent_review ? 10 : 0) + ($old_review ? 10 : 0);
        
        $stmt->execute([
            $student_id, $today, $memorized_ayahs, 0,
            trim($memorized_surahs_text) . ($memorization_details ? " - $memorization_details" : ""),
            $review_ayahs, $quality_rating, $notes,
            $_SESSION['user_id'], $user_type
        ]);
        
        if (function_exists('addPointsLog')) {
            addPointsLog($pdo, $student_id, $points_earned, 'daily_memorization', 
                "تسجيل حفظ يومي: " . ($new_memorization ? "حفظ جديد " : "") . 
                ($recent_review ? "مراجعة قريبة " : "") . 
                ($old_review ? "مراجعة بعيدة " : "")
            );
        }
        
        if (function_exists('updateStudentPoints')) {
            updateStudentPoints($pdo, $student_id);
        }
        
        if (function_exists('updateDailyStreak')) {
            $new_streak = updateDailyStreak($pdo, $student_id);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "✅ تم تسجيل الحفظ اليومي! +{$points_earned} نقطة";
        if (isset($new_streak) && $new_streak > 0) {
            $_SESSION['success'] .= " 🔥 السلسلة الحالية: {$new_streak} يوم";
        }
        
        header("Location: guardian_daily_memorization.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
        header("Location: guardian_daily_memorization.php");
        exit;
    }
}

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>تسجيل الحفظ اليومي - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --primary-dark: #0a2a2c;
            --secondary: #c9a96b;
            --secondary-light: #dbb87c;
            --success: #28a745;
            --success-light: #d4edda;
            --danger: #dc3545;
            --danger-light: #f8d7da;
            --warning: #ffc107;
            --warning-light: #fff3cd;
            --info: #17a2b8;
            --info-light: #d1ecf1;
            --purple: #6f42c1;
            --purple-light: #e9d8fd;
            --orange: #fd7e14;
            --orange-light: #fff3e0;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --dark: #2c3e50;
            
            --gradient-primary: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            --gradient-secondary: linear-gradient(135deg, #c9a96b, #dbb87c);
            --gradient-success: linear-gradient(135deg, #28a745, #20c997);
            --gradient-info: linear-gradient(135deg, #17a2b8, #138496);
            --gradient-warning: linear-gradient(135deg, #ffc107, #e0a800);
            --gradient-purple: linear-gradient(135deg, #6f42c1, #9b59b6);
            --gradient-orange: linear-gradient(135deg, #fd7e14, #ffc107);
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
            --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
            --shadow-2xl: 0 20px 40px rgba(0,0,0,0.25);
            --shadow-gold: 0 5px 15px rgba(201,169,107,0.3);
            
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
        
        /* ===== الحاوية الرئيسية ===== */
        .daily-page {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* ===== رأس الصفحة ===== */
        .hero-section {
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            padding: 35px 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            text-align: center;
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
            font-size: 1rem;
        }
        
        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: var(--radius-full);
            margin-top: 15px;
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
        }
        
        /* ===== إحصائيات سريعة ===== */
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
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-secondary);
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
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        /* ===== تنبيه الجمعة ===== */
        .friday-alert {
            background: linear-gradient(135deg, var(--warning-light), #ffe69c);
            border-radius: var(--radius-xl);
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-right: 5px solid var(--warning);
            box-shadow: var(--shadow-md);
        }
        
        .friday-alert i {
            font-size: 2.5rem;
            color: var(--warning);
        }
        
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
        
        /* ===== بطاقات الطلاب ===== */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 25px;
        }
        
        .student-card {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition-bounce);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .student-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }
        
        /* شريط علوي حسب الفئة */
        .card-strip {
            height: 6px;
            background: var(--gradient-secondary);
        }
        
        .card-strip.boy { background: linear-gradient(90deg, #3498db, #2980b9); }
        .card-strip.girl { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
        .card-strip.child { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .card-strip.woman { background: linear-gradient(90deg, #e84342, #c0392b); }
        
        /* محتوى البطاقة */
        .card-content {
            padding: 25px;
        }
        
        .student-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px dashed var(--gray-light);
        }
        
        .student-avatar {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: bold;
            border: 3px solid var(--secondary);
            box-shadow: var(--shadow-md);
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .student-level {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--info-light);
            padding: 3px 12px;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            color: #0c5460;
        }
        
        .recorded-badge {
            background: var(--success-light);
            color: #155724;
            padding: 8px 16px;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .streak-badge {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 5px 12px;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* ===== خيارات الحفظ الثلاثة ===== */
        .options-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 20px 0;
        }
        
        .option-card {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: var(--radius-xl);
            border: 2px solid transparent;
            transition: var(--transition-bounce);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .option-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, rgba(201,169,107,0.1), transparent);
            transition: width 0.4s;
        }
        
        .option-card:hover::before {
            width: 100%;
        }
        
        .option-card:hover {
            transform: translateX(-8px);
            border-color: var(--secondary-light);
        }
        
        .option-card.selected {
            border-color: var(--success);
            background: linear-gradient(135deg, #f0fff4, #e8f5e9);
        }
        
        .option-icon {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            transition: var(--transition);
        }
        
        .option-card.selected .option-icon {
            transform: scale(1.1);
        }
        
        .option-icon.new { background: var(--gradient-success); color: white; }
        .option-icon.recent { background: var(--gradient-info); color: white; }
        .option-icon.old { background: var(--gradient-orange); color: white; }
        
        .option-content {
            flex: 1;
        }
        
        .option-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .option-desc {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .option-points {
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 8px 18px;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .option-card.selected .option-points {
            background: var(--success);
            color: white;
        }
        
        .option-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--success);
            margin-right: 5px;
        }
        
        /* ===== تفاصيل الحفظ ===== */
        .details-input {
            margin: 15px 0;
            padding: 15px;
            background: var(--info-light);
            border-radius: var(--radius-lg);
            display: none;
        }
        
        .details-input.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .details-input label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0c5460;
        }
        
        .details-input input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
        }
        
        /* ===== ملخص النقاط ===== */
        .points-summary {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border-radius: var(--radius-xl);
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .points-summary::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,215,0,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .points-summary span {
            font-size: 2rem;
            font-weight: 800;
            color: var(--secondary);
        }
        
        .bonus-message {
            font-size: 0.8rem;
            color: var(--warning);
            margin-top: 5px;
        }
        
        /* ===== حقل الملاحظات ===== */
        .notes-section {
            margin: 20px 0;
        }
        
        .notes-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .notes-section textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            resize: vertical;
            font-family: 'Cairo', sans-serif;
        }
        
        /* ===== زر الحفظ ===== */
        .btn-save {
            width: 100%;
            padding: 16px;
            background: var(--gradient-success);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-bounce);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-gold);
            filter: brightness(1.05);
        }
        
        /* ===== حالة عدم وجود طلاب ===== */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-lg);
        }
        
        .empty-state i {
            font-size: 5rem;
            color: var(--gray-light);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .students-grid { grid-template-columns: 1fr; }
            .option-card { flex-wrap: wrap; }
            .option-points { width: 100%; text-align: center; margin-top: 10px; }
            .hero-section h1 { font-size: 1.5rem; }
            .student-header { flex-direction: column; text-align: center; }
        }
        
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .option-icon { width: 50px; height: 50px; font-size: 1.3rem; }
            .option-title { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

<div class="daily-page">
    <!-- رأس الصفحة المميز -->
    <div class="hero-section">
        <div class="hero-content">
            <h1>
                <i class="fas fa-pen-alt"></i>
                سجل حفظك اليومي
            </h1>
            <p>اختر ما أنجزته اليوم من الحفظ والمراجعة واحصل على النقاط</p>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i>
                <?php echo $today; ?>
            </div>
        </div>
    </div>

    <!-- تنبيه الجمعة -->
    <?php if ($is_friday): ?>
        <div class="friday-alert">
            <i class="fas fa-mosque"></i>
            <div>
                <strong>📅 يوم الجمعة إجازة</strong><br>
                لا يمكن تسجيل الحفظ في يوم الجمعة. نتمنى لكم يومًا مباركًا.
            </div>
        </div>
    <?php endif; ?>

    <!-- رسائل التنبيه -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <?php if (!empty($students)): 
        $total_students = count($students);
        $recorded_today_count = count(array_filter($students, fn($s) => $s['recorded_today'] > 0));
    ?>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--gradient-primary);"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--gradient-success);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number" style="color: var(--success);"><?php echo $recorded_today_count; ?></div>
                <div class="stat-label">سجلوا اليوم</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--gradient-warning);"><i class="fas fa-clock"></i></div>
                <div class="stat-number" style="color: var(--warning);"><?php echo $total_students - $recorded_today_count; ?></div>
                <div class="stat-label">لم يسجلوا بعد</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--gradient-purple);"><i class="fas fa-star"></i></div>
                <div class="stat-number"><?php echo array_sum(array_column($students, 'monthly_records')); ?></div>
                <div class="stat-label">تسجيلات هذا الشهر</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-child"></i>
            <h3>لا يوجد أبناء مسجلين</h3>
            <p>لم تقم بإضافة أي أبناء بعد. يرجى التواصل مع الإدارة.</p>
        </div>
    <?php else: ?>
        <!-- قائمة الطلاب -->
        <div class="students-grid">
            <?php foreach ($students as $student): 
                $recorded_today = $student['recorded_today'] > 0;
                $cat_class = $student['category'] ?? 'boy';
                $avatar_initial = mb_substr($student['name'], 0, 1, 'UTF-8');
                $cat_names = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
            ?>
                <div class="student-card">
                    <div class="card-strip <?php echo $cat_class; ?>"></div>
                    <div class="card-content">
                        <div class="student-header">
                            <div class="student-avatar"><?php echo $avatar_initial; ?></div>
                            <div class="student-info">
                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                <div class="student-level">
                                    <i class="fas fa-level-up-alt"></i>
                                    <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?>
                                </div>
                            </div>
                            <?php if ($recorded_today): ?>
                                <div class="recorded-badge">
                                    <i class="fas fa-check-circle"></i> تم التسجيل
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($recorded_today): ?>
                            <div style="background: var(--success-light); padding: 20px; border-radius: var(--radius-lg); text-align: center;">
                                <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                                <p style="margin-top: 10px; font-weight: 600;">✓ تم تسجيل حفظ اليوم</p>
                                <p style="font-size: 0.8rem; margin-top: 5px;">جزاكم الله خيراً على متابعتكم</p>
                            </div>
                        <?php elseif (!$is_friday): ?>
                            <form method="post">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                
                                <div class="options-container">
                                    <!-- الحفظ الجديد -->
                                    <div class="option-card" onclick="toggleOption(this, 'new')" data-option="new">
                                        <div class="option-icon new"><i class="fas fa-book-open"></i></div>
                                        <div class="option-content">
                                            <div class="option-title">📖 الحفظ الجديد</div>
                                            <div class="option-desc">حفظ سورة أو جزء جديد لم تكن تحفظه من قبل</div>
                                        </div>
                                        <div class="option-points">+10 نقاط</div>
                                        <input type="checkbox" name="new_memorization" value="1" class="option-checkbox" onclick="event.stopPropagation()">
                                    </div>

                                    <!-- المراجعة القريبة -->
                                    <div class="option-card" onclick="toggleOption(this, 'recent')" data-option="recent">
                                        <div class="option-icon recent"><i class="fas fa-history"></i></div>
                                        <div class="option-content">
                                            <div class="option-title">🔄 المراجعة القريبة</div>
                                            <div class="option-desc">مراجعة ما تم حفظه منذ أقل من شهر</div>
                                        </div>
                                        <div class="option-points">+10 نقاط</div>
                                        <input type="checkbox" name="recent_review" value="1" class="option-checkbox" onclick="event.stopPropagation()">
                                    </div>

                                    <!-- المراجعة البعيدة -->
                                    <div class="option-card" onclick="toggleOption(this, 'old')" data-option="old">
                                        <div class="option-icon old"><i class="fas fa-archive"></i></div>
                                        <div class="option-content">
                                            <div class="option-title">📚 المراجعة البعيدة</div>
                                            <div class="option-desc">مراجعة ما تم حفظه منذ أكثر من شهر</div>
                                        </div>
                                        <div class="option-points">+10 نقاط</div>
                                        <input type="checkbox" name="old_review" value="1" class="option-checkbox" onclick="event.stopPropagation()">
                                    </div>
                                </div>

                                <!-- حقل تفاصيل الحفظ -->
                                <div class="details-input" id="detailsInput">
                                    <label><i class="fas fa-info-circle"></i> تفاصيل الحفظ (اختياري)</label>
                                    <input type="text" name="memorization_details" placeholder="مثال: سورة الملك آية 1-10">
                                </div>

                                <div class="points-summary">
                                    النقاط المتوقعة: <span id="totalPoints">0</span> نقطة
                                    <div class="bonus-message" id="bonusMessage" style="display: none;">
                                        🎁 +5 نقاط إضافية عند إكمال الأنواع الثلاثة!
                                    </div>
                                </div>

                                <div class="notes-section">
                                    <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                                    <textarea name="notes" rows="2" placeholder="أي ملاحظات عن حفظ الطالب..."></textarea>
                                </div>

                                <button type="submit" name="save_memorization" class="btn-save">
                                    <i class="fas fa-save"></i> تسجيل الحفظ
                                </button>
                            </form>
                        <?php else: ?>
                            <div style="background: var(--warning-light); padding: 20px; border-radius: var(--radius-lg); text-align: center;">
                                <i class="fas fa-mosque" style="font-size: 2rem; color: var(--warning);"></i>
                                <p style="margin-top: 10px; font-weight: 600;">يوم الجمعة إجازة</p>
                                <p style="font-size: 0.8rem;">يمكنك تسجيل الحفظ من السبت إلى الخميس</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// تحديث النقاط عند تغيير الاختيارات
function updatePoints() {
    let totalPoints = 0;
    let checkedCount = 0;
    let hasNew = false, hasRecent = false, hasOld = false;
    
    document.querySelectorAll('.option-checkbox').forEach(checkbox => {
        if (checkbox.checked) {
            totalPoints += 10;
            checkedCount++;
            
            // تحديد النوع
            const card = checkbox.closest('.option-card');
            if (card && card.dataset.option === 'new') hasNew = true;
            if (card && card.dataset.option === 'recent') hasRecent = true;
            if (card && card.dataset.option === 'old') hasOld = true;
        }
    });
    
    // مكافأة إضافية إذا تم اختيار الأقسام الثلاثة
    const bonusMsg = document.getElementById('bonusMessage');
    const detailsInput = document.getElementById('detailsInput');
    
    if (checkedCount === 3) {
        totalPoints += 5;
        bonusMsg.style.display = 'block';
    } else {
        bonusMsg.style.display = 'none';
    }
    
    // إظهار حقل التفاصيل إذا تم اختيار أي شيء
    if (checkedCount > 0) {
        detailsInput.classList.add('show');
    } else {
        detailsInput.classList.remove('show');
    }
    
    document.getElementById('totalPoints').innerText = totalPoints;
}

// تبديل اختيار الخانة عند النقر على البطاقة
function toggleOption(card, option) {
    const checkbox = card.querySelector('.option-checkbox');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        if (checkbox.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
        updatePoints();
    }
}

// إضافة مستمع للأحداث على صناديق الاختيار
document.querySelectorAll('.option-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const card = this.closest('.option-card');
        if (this.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
        updatePoints();
    });
});

// تهيئة النقاط عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updatePoints();
});
</script>

<?php require_once 'includes/footer.php'; ?>