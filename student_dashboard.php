<?php
// ============================================
// ملف: student_dashboard.php - لوحة تحكم الطالب (نسخة كاملة محدثة)
// مع إحصائيات الأوراد والصلوات
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/wirds_functions.php';

if (!isStudent()) {
    redirect('login.php');
}

$pageTitle = 'لوحة تحكم الطالب';
$student_id = $_SESSION['user_id'];

// جلب معلومات الطالب
$student = $pdo->prepare("
    SELECT s.*, t.name as teacher_name 
    FROM students s 
    LEFT JOIN teachers t ON s.teacher_id = t.id 
    WHERE s.id = ?
");
$student->execute([$student_id]);
$student_info = $student->fetch();

// تحديث نقاط الطالب (من جميع المصادر)
updateTotalStudentPoints($pdo, $student_id);

// جلب بيانات النقاط الكاملة
$points_data = getStudentPointsData($pdo, $student_id);
$streak = updateDailyStreak($pdo, $student_id);

// إحصائيات الحفظ
$surahs_count = $pdo->prepare("SELECT COUNT(*) FROM student_surah_progress WHERE student_id = ? AND completed = 1");
$surahs_count->execute([$student_id]);
$surahs_count = $surahs_count->fetchColumn();

$certificates_count = $pdo->prepare("SELECT COUNT(*) FROM student_achievements WHERE student_id = ?");
$certificates_count->execute([$student_id]);
$certificates_count = $certificates_count->fetchColumn();

// حساب الأجزاء
$total_pages = getStudentUniquePages($pdo, $student_id);
$total_parts = calculatePartsFromUniquePages($total_pages);
$memorization_text = getMemorizationDescription($total_pages, $total_parts);

// إحصائيات الأوراد اليومية
$wird_stats = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT record_date) as active_days,
        SUM(points_earned) as total_points,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_days,
        MAX(streak_days) as best_streak
    FROM student_wird_records
    WHERE student_id = ?
");
$wird_stats->execute([$student_id]);
$wird_stats = $wird_stats->fetch();

// إحصائيات الصلوات
$prayer_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_prayers,
        SUM(CASE WHEN status = 'jamaa' THEN 1 ELSE 0 END) as jamaa_count,
        SUM(CASE WHEN status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(points_earned) as total_points
    FROM student_prayer_records
    WHERE student_id = ?
");
$prayer_stats->execute([$student_id]);
$prayer_stats = $prayer_stats->fetch();

// إحصائيات الصلاة على النبي
$prayer_on_prophet = $pdo->prepare("
    SELECT prayer_count FROM prayer_counts 
    WHERE user_id = ? AND user_type = 'student' AND prayer_date = CURDATE()
");
$prayer_on_prophet->execute([$student_id]);
$today_prayer_count = $prayer_on_prophet->fetchColumn() ?: 0;

// آخر 5 إنجازات
$recent_achievements = $pdo->prepare("
    SELECT * FROM points_log 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_achievements->execute([$student_id]);
$recent_achievements = $recent_achievements->fetchAll();

// الأوراد النشطة للعرض السريع
$active_wirds = getActiveWirds($pdo);
$quick_wirds = array_slice($active_wirds, 0, 3);

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الطالب - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); min-height: 100vh; }
        .dashboard { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* بطاقة الترحيب */
        .welcome-card {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid #c9a96b;
            position: relative;
            z-index: 2;
        }
        .welcome-content { flex: 1; position: relative; z-index: 2; }
        .welcome-content h2 { margin: 0 0 5px; font-size: 1.8rem; }
        .streak-badge {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            padding: 10px 25px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 2;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* بطاقة النقاط */
        .points-card {
            background: linear-gradient(135deg, #6f42c1, #9b59b6);
            color: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .points-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        .points-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        .points-main {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .points-icon { font-size: 3rem; }
        .points-number { font-size: 2.5rem; font-weight: 800; }
        .points-label { font-size: 0.9rem; opacity: 0.9; }
        .level-badge {
            background: rgba(255,255,255,0.2);
            padding: 10px 25px;
            border-radius: 30px;
            text-align: center;
        }
        .level-name { font-size: 1.3rem; font-weight: 700; }
        .progress-bar {
            height: 8px;
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ffed4e);
            border-radius: 10px;
            transition: width 0.5s;
        }
        
        /* إحصائيات سريعة */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .stat-number { font-size: 1.8rem; font-weight: 800; color: #1e3c3f; }
        .stat-label { color: #666; font-size: 0.85rem; margin-top: 5px; }
        
        /* قسم الأوراد السريع */
        .quick-wirds {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        .wirds-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .wird-mini-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            transition: 0.3s;
            text-decoration: none;
            display: block;
        }
        .wird-mini-card:hover { transform: translateY(-3px); background: #e9ecef; }
        .wird-mini-icon { font-size: 1.8rem; margin-bottom: 8px; }
        .wird-mini-name { font-weight: 700; color: #1e3c3f; }
        
        /* إحصائيات العبادات */
        .worship-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        .worship-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .worship-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        .worship-header i { font-size: 1.5rem; color: #c9a96b; }
        .worship-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            text-align: center;
        }
        .worship-stat-number { font-size: 1.5rem; font-weight: 800; }
        .worship-stat-number.prayer-jamaa { color: #28a745; }
        .worship-stat-number.prayer-on-time { color: #17a2b8; }
        .worship-stat-number.prayer-late { color: #ffc107; }
        
        /* معلومات الحفظ */
        .info-box {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .info-box i { font-size: 2rem; color: #2e7d32; }
        
        /* روابط سريعة */
        .quick-links {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .link-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            text-decoration: none;
            text-align: center;
            transition: 0.3s;
            border: 1px solid #eee;
            display: block;
        }
        .link-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); background: white; }
        .link-card i { font-size: 2rem; margin-bottom: 10px; display: block; color: #c9a96b; }
        .link-card h4 { color: #1e3c3f; margin-bottom: 5px; }
        .link-card p { color: #666; font-size: 0.75rem; }
        .link-card.prayer-link { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        .link-card.prayer-link i, .link-card.prayer-link h4, .link-card.prayer-link p { color: white; }
        
        /* آخر الإنجازات */
        .achievements-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
        }
        .achievement-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .achievement-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .achievement-icon.surah { background: #d4edda; color: #28a745; }
        .achievement-icon.wird { background: #e8f5e9; color: #2e7d32; }
        .achievement-icon.prayer { background: #fff3cd; color: #f39c12; }
        .achievement-icon.streak { background: #d1ecf1; color: #17a2b8; }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .worship-stats { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .links-grid { grid-template-columns: 1fr; }
            .welcome-card { flex-direction: column; text-align: center; }
            .points-row { flex-direction: column; text-align: center; }
            .worship-stats-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        /* إشعارات */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            direction: rtl;
        }
        .toast-message.success { border-right: 4px solid #28a745; color: #155724; }
        .toast-message.error { border-right: 4px solid #dc3545; color: #721c24; }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- بطاقة الترحيب -->
    <div class="welcome-card">
        <div class="student-avatar"><i class="fas fa-user-graduate"></i></div>
        <div class="welcome-content">
            <h2>مرحباً، <?php echo htmlspecialchars($student_info['name']); ?></h2>
            <p><i class="fas fa-chalkboard-teacher"></i> المعلم: <?php echo htmlspecialchars($student_info['teacher_name'] ?? 'غير محدد'); ?></p>
        </div>
        <div class="streak-badge">
            <i class="fas fa-fire"></i>
            <span>🔥 <?php echo $streak; ?> يوم متتالي</span>
        </div>
    </div>

    <!-- بطاقة النقاط والمستوى -->
    <div class="points-card">
        <div class="points-row">
            <div class="points-main">
                <div class="points-icon">⭐</div>
                <div>
                    <div class="points-number"><?php echo number_format($points_data['total_points']); ?></div>
                    <div class="points-label">إجمالي النقاط</div>
                </div>
            </div>
            <div class="points-main">
                <div>
                    <div class="points-number"><?php echo number_format($points_data['total_wird_points']); ?></div>
                    <div class="points-label">نقاط الأوراد</div>
                </div>
            </div>
            <div class="points-main">
                <div>
                    <div class="points-number"><?php echo number_format($points_data['total_prayer_points']); ?></div>
                    <div class="points-label">نقاط الصلوات</div>
                </div>
            </div>
            <div class="level-badge">
                <div class="level-name"><?php echo $points_data['level_icon']; ?> <?php echo $points_data['level_name']; ?></div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $points_data['progress_percent']; ?>%;"></div>
                </div>
                <div style="font-size: 0.7rem;"><?php echo $points_data['points_to_next']; ?> نقطة للمستوى التالي</div>
            </div>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='view_progress.php'">
            <div class="stat-number"><?php echo $surahs_count; ?></div>
            <div class="stat-label">سورة محفوظة</div>
        </div>
        <div class="stat-card" onclick="window.location.href='view_progress.php'">
            <div class="stat-number"><?php echo number_format($total_pages); ?></div>
            <div class="stat-label">صفحة فريدة</div>
        </div>
        <div class="stat-card" onclick="window.location.href='view_progress.php'">
            <div class="stat-number"><?php echo $total_parts >= 30 ? '🎓' : $total_parts; ?></div>
            <div class="stat-label"><?php echo $total_parts >= 30 ? 'ختم القرآن' : 'جزء'; ?></div>
        </div>
        <div class="stat-card" onclick="window.location.href='student_certificates.php'">
            <div class="stat-number"><?php echo $certificates_count; ?></div>
            <div class="stat-label">شهادة</div>
        </div>
        <div class="stat-card" id="prayerCountCard" onclick="recordPrayer()" style="cursor: pointer;">
            <div class="stat-number" id="todayPrayerCount"><?php echo $today_prayer_count; ?></div>
            <div class="stat-label">صلاة اليوم على النبي ﷺ</div>
        </div>
    </div>

    <!-- الأوراد السريعة -->
    <?php if (!empty($quick_wirds)): ?>
    <div class="quick-wirds">
        <div class="section-title">
            <i class="fas fa-praying-hands" style="color: #c9a96b;"></i>
            <h3>أورادي اليومية</h3>
            <a href="my_wirds.php" style="margin-right: auto; font-size: 0.8rem; color: #c9a96b;">عرض الكل</a>
        </div>
        <div class="wirds-mini-grid">
            <?php foreach ($quick_wirds as $wird): ?>
                <a href="my_wirds.php" class="wird-mini-card">
                    <div class="wird-mini-icon"><i class="fas <?php echo $wird['icon']; ?>" style="color: <?php echo $wird['color']; ?>;"></i></div>
                    <div class="wird-mini-name"><?php echo htmlspecialchars($wird['wird_name']); ?></div>
                    <div style="font-size: 0.7rem; color: #666;">الهدف: <?php echo number_format($wird['recommended_count']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- إحصائيات العبادات -->
    <div class="worship-stats">
        <div class="worship-card">
            <div class="worship-header">
                <i class="fas fa-star-and-crescent"></i>
                <h3>إحصائيات الأوراد</h3>
            </div>
            <div class="worship-stats-grid">
                <div>
                    <div class="worship-stat-number"><?php echo number_format($wird_stats['total_points'] ?? 0); ?></div>
                    <div class="stat-label">إجمالي النقاط</div>
                </div>
                <div>
                    <div class="worship-stat-number"><?php echo $wird_stats['active_days'] ?? 0; ?></div>
                    <div class="stat-label">أيام نشطة</div>
                </div>
                <div>
                    <div class="worship-stat-number"><?php echo $wird_stats['completed_days'] ?? 0; ?></div>
                    <div class="stat-label">أيام أكملت الهدف</div>
                </div>
            </div>
            <?php if (($wird_stats['best_streak'] ?? 0) > 0): ?>
                <div style="margin-top: 15px; text-align: center; background: #e8f5e9; padding: 8px; border-radius: 30px;">
                    🔥 أفضل سلسلة: <?php echo $wird_stats['best_streak']; ?> يوم متتالي
                </div>
            <?php endif; ?>
        </div>

        <div class="worship-card">
            <div class="worship-header">
                <i class="fas fa-mosque"></i>
                <h3>إحصائيات الصلوات</h3>
            </div>
            <div class="worship-stats-grid">
                <div>
                    <div class="worship-stat-number"><?php echo number_format($prayer_stats['total_points'] ?? 0); ?></div>
                    <div class="stat-label">نقاط الصلوات</div>
                </div>
                <div>
                    <div class="worship-stat-number prayer-jamaa"><?php echo $prayer_stats['jamaa_count'] ?? 0; ?></div>
                    <div class="stat-label">صلاة جماعة</div>
                </div>
                <div>
                    <div class="worship-stat-number prayer-on-time"><?php echo $prayer_stats['on_time_count'] ?? 0; ?></div>
                    <div class="stat-label">في الوقت</div>
                </div>
            </div>
            <?php if (($prayer_stats['total_prayers'] ?? 0) > 0): ?>
                <div style="margin-top: 15px; text-align: center; background: #e8f5e9; padding: 8px; border-radius: 30px;">
                    📿 إجمالي الصلوات المسجلة: <?php echo $prayer_stats['total_prayers']; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- معلومات الحفظ -->
    <div class="info-box">
        <i class="fas fa-chart-line"></i>
        <div><strong>تقدمك في الحفظ:</strong> <?php echo $memorization_text; ?></div>
    </div>

    <!-- روابط سريعة -->
    <div class="quick-links">
        <h3><i class="fas fa-bolt"></i> روابط سريعة</h3>
        <div class="links-grid">
            <a href="student_quran.php" class="link-card">
                <i class="fas fa-quran"></i>
                <h4>المصحف التفاعلي</h4>
                <p>اقرأ واستمع للقرآن</p>
            </a>
            <a href="my_wirds.php" class="link-card">
                <i class="fas fa-praying-hands"></i>
                <h4>أورادي اليومية</h4>
                <p>سجل أذكارك اليومية</p>
            </a>
            <a href="my_custom_wirds.php" class="link-card">
    <i class="fas fa-star-and-crescent"></i>
    <h4>أورادي الإضافية</h4>
    <p>أضف أورادك الخاصة</p>
</a>
            <a href="my_prayers.php" class="link-card">
                <i class="fas fa-mosque"></i>
                <h4>صلواتي</h4>
                <p>تسجيل الصلوات في جماعة</p>
            </a>
            <a href="#" onclick="recordPrayer(); return false;" class="link-card prayer-link">
                <i class="fas fa-star-and-crescent"></i>
                <h4>الصلاة على النبي ﷺ</h4>
                <p>سجل صلاتك اليومية</p>
            </a>
            <a href="my_plan.php" class="link-card">
                <i class="fas fa-robot"></i>
                <h4>خطتي الذكية</h4>
                <p>خطة حفظ مخصصة</p>
            </a>
            <a href="student_certificates.php" class="link-card">
                <i class="fas fa-certificate"></i>
                <h4>شهاداتي</h4>
                <p>شهادات الإنجاز</p>
            </a>
            <a href="my_badges.php" class="link-card">
                <i class="fas fa-medal"></i>
                <h4>إنجازاتي</h4>
                <p>الشارات والجوائز</p>
            </a>
            <a href="guardian_daily_memorization.php" class="link-card">
                <i class="fas fa-pen-alt"></i>
                <h4>تسجيل الحفظ اليومي</h4>
                <p>سجل حفظك اليومي</p>
            </a>
            <a href="update_phone.php" class="link-card">
                <i class="fas fa-phone-alt"></i>
                <h4>تحديث رقم الهاتف</h4>
                <p>لتلقي الإشعارات</p>
            </a>
        </div>
    </div>

    <!-- آخر الإنجازات -->
    <?php if (!empty($recent_achievements)): ?>
    <div class="achievements-card">
        <h3><i class="fas fa-history"></i> آخر الإنجازات</h3>
        <?php foreach ($recent_achievements as $ach): 
            $icon_class = '';
            if ($ach['points_type'] == 'surah') $icon_class = 'surah';
            elseif ($ach['points_type'] == 'daily_memorization') $icon_class = 'surah';
            elseif (strpos($ach['points_type'], 'prayer') !== false) $icon_class = 'prayer';
            elseif ($ach['points_type'] == 'streak_bonus') $icon_class = 'streak';
            else $icon_class = 'wird';
            
            $icon_map = [
                'surah' => ['icon' => 'fa-quran', 'bg' => 'surah'],
                'daily_memorization' => ['icon' => 'fa-book-open', 'bg' => 'surah'],
                'prayer' => ['icon' => 'fa-mosque', 'bg' => 'prayer'],
                'streak_bonus' => ['icon' => 'fa-fire', 'bg' => 'streak'],
                'default' => ['icon' => 'fa-star', 'bg' => 'wird']
            ];
            $icon_data = $icon_map[$icon_class] ?? $icon_map['default'];
        ?>
            <div class="achievement-item">
                <div class="achievement-icon <?php echo $icon_data['bg']; ?>">
                    <i class="fas <?php echo $icon_data['icon']; ?>"></i>
                </div>
                <div style="flex: 1;">
                    <div><?php echo $ach['description'] ?: 'حصلت على نقاط'; ?></div>
                    <small style="color: #999;"><?php echo date('Y-m-d H:i', strtotime($ach['created_at'])); ?></small>
                </div>
                <div style="color: #f39c12; font-weight: bold;">+<?php echo $ach['points_earned']; ?> نقطة</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// دالة تسجيل الصلاة على النبي
function recordPrayer() {
    fetch('ajax_record_prayer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=record'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const prayerCountElement = document.getElementById('todayPrayerCount');
            if (prayerCountElement) {
                prayerCountElement.textContent = data.today_count;
            }
            showToast(data.message, 'success');
        } else {
            showToast(data.error || 'حدث خطأ', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('حدث خطأ في الاتصال', 'error');
    });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

console.log('✅ لوحة تحكم الطالب جاهزة');
</script>

<?php
ob_end_flush();
require_once 'includes/footer.php';
?>