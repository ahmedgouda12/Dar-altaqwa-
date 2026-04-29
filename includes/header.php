<?php
// ============================================
// ملف: includes/header.php
// نسخة محدثة - مع نظام الحسابات المخزنة (Saved Accounts)
// آخر تحديث: 2026-04-27
// ============================================

// لا توجد مسافات أو أسطر قبل <?php
// التأكد من بدء الجلسة إذا لم تبدأ بعد
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('isLoggedIn')) {
    require_once 'functions.php';
}

// تضمين ملف التاريخ الهجري
$hijri_date_str = '';
if (file_exists('hijri_date.php')) {
    require_once 'hijri_date.php';
    if (function_exists('getHijriDate')) {
        $current_hijri = getHijriDate();
        $hijri_date_str = $current_hijri['formatted'] ?? '';
    }
}

// متغير الإشعارات
$unread_count = 0;

// جلب عدد الشهادات للطالب
$certificates_count = 0;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student' && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_achievements WHERE student_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $certificates_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $certificates_count = 0;
    }
}

// جلب عدد الأهداف للطلاب (للمعلم)
$goals_count = 0;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher' && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_monthly_goals g
            JOIN students s ON g.student_id = s.id
            WHERE s.teacher_id = ? 
            AND MONTH(g.created_at) = MONTH(CURDATE()) 
            AND YEAR(g.created_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $goals_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $goals_count = 0;
    }
}

// إحصائيات الدورات (للعرض في القائمة)
$active_courses_count = 0;
if (isset($pdo)) {
    try {
        $active_courses_count = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'active' AND end_date >= CURDATE()")->fetchColumn();
    } catch (PDOException $e) {
        $active_courses_count = 0;
    }
}

// ============================================
// جلب الحسابات المخزنة للمستخدم الحالي
// ============================================
$saved_accounts = [];
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && function_exists('getSavedAccounts')) {
    $saved_accounts = getSavedAccounts($pdo, $_SESSION['user_id']);
    
    // إزالة الحساب الحالي من القائمة (حتى لا يظهر مرتين)
    $saved_accounts = array_filter($saved_accounts, function($acc) {
        return !($acc['account_id'] == $_SESSION['user_id'] && $acc['account_type'] == $_SESSION['user_type']);
    });
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>دار التقوى - <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'الرئيسية'; ?></title>
    
    <!-- الخطوط الفاخرة -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Amiri:wght@400;700&family=Scheherazade+New:wght@400;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* ===== متغيرات التصميم الفاخر ===== */
        :root {
            --primary-dark: #0a2a2c;
            --primary: #1e4a4d;
            --primary-light: #2a6b6f;
            --primary-glow: #3d8b8f;
            --secondary: #d4af37;
            --secondary-light: #e6c77c;
            --secondary-glow: #ffd966;
            --accent: #c44536;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --gold: #ffd700;
            --silver: #c0c0c0;
            --bronze: #cd7f32;
            
            --gradient-primary: linear-gradient(135deg, #0a2a2c, #1e4a4d, #2a6b6f);
            --gradient-secondary: linear-gradient(135deg, #d4af37, #e6c77c, #ffd700);
            --gradient-accent: linear-gradient(135deg, #c44536, #e67e22);
            --gradient-dark: linear-gradient(135deg, #1a2634, #2c3e50);
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
            --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
            --shadow-2xl: 0 20px 40px rgba(0,0,0,0.25);
            --shadow-inner: inset 0 2px 4px rgba(0,0,0,0.05);
            --shadow-gold: 0 5px 15px rgba(212, 175, 55, 0.3);
            --shadow-gold-hover: 0 8px 25px rgba(212, 175, 55, 0.5);
            
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 20px;
            --border-radius-xl: 30px;
            --border-radius-2xl: 40px;
            --border-radius-3xl: 50px;
            --border-radius-full: 9999px;
            
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
            --transition-bounce: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #f6f9fc, #edf2f7);
            color: var(--dark);
            line-height: 1.5;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* خلفية زخرفية إسلامية متحركة */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(212, 175, 55, 0.03) 0%, transparent 30%),
                radial-gradient(circle at 90% 70%, rgba(30, 74, 77, 0.03) 0%, transparent 30%),
                repeating-linear-gradient(45deg, rgba(212, 175, 55, 0.01) 0px, rgba(212, 175, 55, 0.01) 2px, transparent 2px, transparent 10px);
            pointer-events: none;
            z-index: -1;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        body::after {
            content: "﷽";
            position: fixed;
            bottom: 20px;
            right: 20px;
            font-size: 150px;
            font-family: 'Amiri', 'Scheherazade New', serif;
            color: var(--secondary);
            opacity: 0.03;
            transform: rotate(-10deg);
            pointer-events: none;
            z-index: -1;
            animation: basmalahFloat 10s ease-in-out infinite;
        }

        @keyframes basmalahFloat {
            0%, 100% { transform: rotate(-10deg) scale(1); }
            50% { transform: rotate(-8deg) scale(1.02); }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 20px;
            position: relative;
        }

        /* ===== مؤشر التحميل الإبداعي ===== */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s, visibility 0.5s;
        }

        .page-loader.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loader-content {
            text-align: center;
            background: white;
            padding: 40px 60px;
            border-radius: var(--border-radius-3xl);
            box-shadow: var(--shadow-2xl);
            border: 2px solid var(--secondary);
            position: relative;
            overflow: hidden;
        }

        .loader-content::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            animation: loaderRotate 3s linear infinite;
        }

        @keyframes loaderRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .loader-quran {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            position: relative;
            animation: float 3s ease-in-out infinite;
        }

        .loader-quran i {
            font-size: 70px;
            color: var(--secondary);
            filter: drop-shadow(0 10px 15px rgba(212, 175, 55, 0.4));
            animation: pulse 2s ease-in-out infinite;
        }

        .loader-quran::before,
        .loader-quran::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid transparent;
            border-top-color: var(--secondary);
            border-right-color: var(--secondary-light);
            border-radius: 50%;
            animation: spin 2s linear infinite;
        }

        .loader-quran::after {
            border-top-color: var(--primary);
            border-right-color: var(--primary-light);
            animation: spin 1.5s linear infinite reverse;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
        }

        .loader-text {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            position: relative;
        }

        .loader-progress {
            width: 300px;
            height: 4px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px auto 0;
        }

        .loader-progress-bar {
            width: 0%;
            height: 100%;
            background: var(--gradient-secondary);
            animation: load 2s ease-in-out forwards;
        }

        @keyframes load {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }

        /* ===== الهيدر الفاخر ===== */
        .site-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px 30px;
            border-radius: var(--border-radius-2xl);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-2xl), 0 0 0 2px rgba(212, 175, 55, 0.3) inset;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .site-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
            z-index: 0;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .site-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gradient-secondary);
            transform: scaleX(0);
            transform-origin: right;
            animation: slideIn 1.5s ease-out forwards;
        }

        @keyframes slideIn {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }

        .logo {
            position: relative;
            z-index: 2;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .logo img {
            height: 80px;
            width: auto;
            border-radius: var(--border-radius-lg);
            background: white;
            padding: 8px;
            box-shadow: var(--shadow-2xl), 0 0 0 3px var(--secondary);
            transition: var(--transition-bounce);
        }

        .logo img:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: var(--shadow-gold-hover), 0 0 0 4px white;
        }

        .header-title {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .header-title h1 {
            font-size: 2.5rem;
            margin: 0;
            color: white;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.4);
            font-weight: 800;
            letter-spacing: 2px;
            background: linear-gradient(135deg, white, var(--secondary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: titleGlow 3s ease-in-out infinite;
        }

        @keyframes titleGlow {
            0%, 100% { filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.3)); }
            50% { filter: drop-shadow(0 0 20px rgba(212, 175, 55, 0.6)); }
        }

        .header-title p {
            margin: 10px 0 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .hijri-badge {
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(5px);
            padding: 8px 22px;
            border-radius: var(--border-radius-full);
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .hijri-badge::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 15s linear infinite;
        }

        .hijri-badge:hover {
            background: rgba(0, 0, 0, 0.35);
            transform: translateY(-3px);
            box-shadow: var(--shadow-gold);
        }

        .hijri-badge i {
            color: var(--secondary);
            animation: starTwinkle 2s ease-in-out infinite;
            position: relative;
        }

        @keyframes starTwinkle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.2); }
        }

        /* ===== قسم header-actions (الأزرار وقائمة الحسابات) ===== */
        .header-actions {
            display: flex;
            gap: 15px;
            position: relative;
            z-index: 2;
            align-items: center;
            flex-wrap: wrap;
        }

        .header-btn {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius-full);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-bounce);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .header-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
            z-index: -1;
        }

        .header-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .header-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow-gold), 0 0 0 2px white;
            background: rgba(255, 255, 255, 0.25);
        }

        /* ===== قائمة الحسابات المخزنة (Account Switcher) ===== */
        .account-switcher {
            position: relative;
            display: inline-block;
        }

        .switcher-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 8px 18px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            transition: var(--transition-normal);
        }

        .switcher-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .switcher-label {
            font-weight: 600;
        }

        .account-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-2xl);
            min-width: 280px;
            z-index: 1000;
            display: none;
            margin-top: 10px;
            overflow: hidden;
        }

        .account-menu.show {
            display: block;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .account-menu-header {
            background: var(--gradient-primary);
            color: white;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .account-menu-header i {
            color: var(--secondary);
        }

        .account-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition-normal);
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
        }

        .account-item:last-child {
            border-bottom: none;
        }

        .account-item:hover {
            background: #f8f9fa;
        }

        .account-item i {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .account-info {
            flex: 1;
        }

        .account-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--primary);
        }

        .account-type {
            font-size: 0.7rem;
            color: var(--secondary);
            font-weight: 600;
        }

        .account-identifier {
            font-size: 0.65rem;
            color: #999;
            margin-top: 2px;
            direction: ltr;
            text-align: left;
        }

        .delete-account-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 50%;
            transition: var(--transition-normal);
            opacity: 0.6;
        }

        .delete-account-btn:hover {
            opacity: 1;
            background: #f8d7da;
            transform: scale(1.1);
        }

        .account-menu-footer {
            background: #f8f9fa;
            padding: 8px 12px;
            font-size: 0.65rem;
            color: #999;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        /* ===== شريط التنقل الإبداعي ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            border-radius: var(--border-radius-2xl);
            padding: 15px 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-2xl), 0 0 0 2px var(--secondary) inset;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: sticky;
            top: 15px;
            z-index: 1000;
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: var(--transition-normal);
        }

        .navbar::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: var(--gradient-secondary);
            border-radius: calc(var(--border-radius-2xl) + 3px);
            z-index: -1;
            opacity: 0.3;
            filter: blur(8px);
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.99);
            box-shadow: var(--shadow-gold), 0 0 0 3px var(--secondary);
            padding: 12px 22px;
        }

        .navbar a, .dropdown-toggle {
            padding: 12px 20px;
            border-radius: var(--border-radius-full);
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: var(--transition-bounce);
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .navbar a::before, .dropdown-toggle::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(30, 74, 77, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
            z-index: -1;
        }

        .navbar a:hover::before, .dropdown-toggle:hover::before {
            width: 300px;
            height: 300px;
        }

        .navbar a i, .dropdown-toggle i {
            font-size: 1.2rem;
            color: var(--secondary);
            transition: var(--transition-bounce);
        }

        .navbar a:hover, .dropdown-toggle:hover {
            color: var(--primary);
            transform: translateY(-3px);
            border-color: var(--secondary);
            box-shadow: var(--shadow-gold);
        }

        .navbar a:hover i, .dropdown-toggle:hover i {
            transform: scale(1.2) rotate(5deg);
            color: var(--primary);
        }

        .navbar .active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-lg), 0 0 0 3px var(--secondary);
        }

        .navbar .active i {
            color: white;
        }

        .navbar .active:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-gold), 0 0 0 4px white;
        }

        /* ===== القائمة المنسدلة الإبداعية ===== */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            min-width: 280px;
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-2xl), 0 0 0 1px var(--secondary);
            z-index: 1000;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            transform-origin: top center;
        }

        .dropdown.active .dropdown-content {
            display: block;
            animation: dropdownFade 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes dropdownFade {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .dropdown-content::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 25px;
            width: 14px;
            height: 14px;
            background: white;
            transform: rotate(45deg);
            border-left: 1px solid var(--secondary);
            border-top: 1px solid var(--secondary);
        }

        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 18px;
            color: var(--primary-dark);
            text-decoration: none;
            border-radius: var(--border-radius-lg);
            transition: var(--transition-bounce);
            font-weight: 600;
            font-size: 0.95rem;
            border: 1px solid transparent;
            margin-bottom: 3px;
        }

        .dropdown-content a:hover {
            background: linear-gradient(135deg, rgba(30, 74, 77, 0.08), rgba(42, 107, 111, 0.08));
            transform: translateX(-8px) scale(1.02);
            border-color: var(--secondary);
            box-shadow: var(--shadow-md);
        }

        .dropdown-content a i {
            width: 24px;
            font-size: 1.3rem;
            color: var(--secondary);
            text-align: center;
        }

        .dropdown-content .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--secondary), transparent);
            margin: 10px 0;
        }

        /* ===== شارة العداد الإبداعية ===== */
        .badge {
            background: linear-gradient(135deg, var(--danger), #ff6b6b);
            color: white;
            border-radius: var(--border-radius-full);
            padding: 5px 12px;
            font-size: 0.7rem;
            min-width: 24px;
            text-align: center;
            margin-right: 8px;
            font-weight: 800;
            display: inline-block;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.5);
            animation: badgePulse 2s ease-in-out infinite;
        }

        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .badge.success {
            background: linear-gradient(135deg, var(--success), #34ce57);
        }

        .badge.warning {
            background: linear-gradient(135deg, var(--warning), #ffdb58);
            color: var(--dark);
        }

        .badge.gold {
            background: linear-gradient(135deg, #ffd700, #ffa500);
            color: var(--dark);
        }

        /* ===== زر القائمة للهاتف ===== */
        .menu-toggle {
            display: none;
            width: 100%;
            padding: 18px 20px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius-2xl);
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-2xl), 0 0 0 3px var(--secondary) inset;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition-bounce);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .menu-toggle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .menu-toggle:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: var(--shadow-gold), 0 0 0 4px white;
        }

        .menu-toggle i {
            margin-left: 12px;
            animation: menuIconBounce 2s ease-in-out infinite;
        }

        @keyframes menuIconBounce {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(90deg); }
            50% { transform: rotate(180deg); }
            75% { transform: rotate(270deg); }
        }

        /* ===== شريط التقدم العلوي ===== */
        #progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 5px;
            background: var(--gradient-secondary);
            width: 0%;
            z-index: 10001;
            transition: width 0.2s;
            box-shadow: 0 0 20px var(--secondary);
        }

        /* ===== تأثيرات إضافية ===== */
        .glow-text {
            animation: glowPulse 3s ease-in-out infinite;
        }

        @keyframes glowPulse {
            0%, 100% { text-shadow: 0 0 10px rgba(212, 175, 55, 0.3); }
            50% { text-shadow: 0 0 30px rgba(212, 175, 55, 0.7); }
        }

        /* ===== إشعارات منبثقة ===== */
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
            font-family: 'Cairo', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
            direction: rtl;
        }

        .toast-message.success {
            border-right: 4px solid #28a745;
            color: #155724;
        }

        .toast-message.error {
            border-right: 4px solid #dc3545;
            color: #721c24;
        }

        .toast-message i {
            font-size: 1.2rem;
        }

        /* ===== تحسينات متقدمة للهاتف ===== */
        @media (max-width: 1200px) {
            .header-title h1 { font-size: 2rem; }
        }

        @media (max-width: 992px) {
            .header-title h1 { font-size: 1.8rem; }
            .navbar a, .dropdown-toggle { padding: 10px 16px; font-size: 0.9rem; }
        }

        @media (max-width: 768px) {
            .site-header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }

            .logo img { height: 70px; }
            .header-title h1 { font-size: 1.5rem; }
            .header-title p { flex-direction: column; gap: 10px; }
            .hijri-badge { width: fit-content; margin: 0 auto; }
            .header-actions { width: 100%; justify-content: center; flex-wrap: wrap; }
            .header-btn { flex: 1; justify-content: center; padding: 8px 16px; font-size: 0.9rem; }
            
            .navbar {
                display: none;
                flex-direction: column;
                border-radius: var(--border-radius-xl);
                padding: 20px;
                position: static;
                margin-top: 10px;
            }
            
            .navbar.show { display: flex; animation: slideDown 0.5s ease; }
            
            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .navbar a, .dropdown-toggle { width: 100%; justify-content: center; padding: 14px; }
            .dropdown { width: 100%; }
            .dropdown-content { position: static; box-shadow: var(--shadow-lg); margin-top: 5px; width: 100%; }
            .dropdown-content::before { display: none; }
            .menu-toggle { display: block; }
            
            .account-menu {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 90%;
                max-width: 320px;
            }
        }

        @media (max-width: 480px) {
            .container { padding: 10px; }
            .header-title h1 { font-size: 1.2rem; }
            .logo img { height: 60px; }
            .header-btn { font-size: 0.8rem; padding: 6px 12px; }
            .navbar a, .dropdown-toggle { font-size: 0.85rem; }
            .loader-content { padding: 30px 30px; }
            .loader-text { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<!-- شريط التقدم العلوي -->
    <div id="progress-bar"></div>

    <!-- مؤشر تحميل الصفحة الإبداعي -->
    <div class="page-loader" id="pageLoader">
        <div class="loader-content">
            <div class="loader-quran">
                <i class="fas fa-quran"></i>
            </div>
            <div class="loader-text">دار التقوى</div>
            <div class="loader-progress">
                <div class="loader-progress-bar"></div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- الهيدر الفاخر -->
        <header class="site-header" data-aos="fade-down" data-aos-duration="1000">
            <div class="logo">
                <img src="images/logo.png" alt="دار التقوى" onerror="this.src='images/logo-placeholder.png'">
            </div>
            <div class="header-title">
                <h1 class="glow-text">دار التقوى لتحفيظ القرآن الكريم</h1>
                <p>
                    <i class="fas fa-map-marker-alt" style="color: var(--secondary);"></i>
                    منيا القمح - الشرقية
                    <?php if (!empty($hijri_date_str)): ?>
                        <span class="hijri-badge">
                            <i class="fas fa-star-and-crescent"></i> <?php echo $hijri_date_str; ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- ============================================ -->
            <!-- قسم header-actions (الأزرار وقائمة الحسابات) -->
            <!-- ============================================ -->
            <div class="header-actions">
                
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])): ?>
                    
                    <!-- ============================================ -->
                    <!-- قائمة الحسابات المخزنة (Saved Accounts) -->
                    <!-- ============================================ -->
                    <?php if (!empty($saved_accounts) && function_exists('getAccountTypeName') && function_exists('getAccountTypeIcon')): ?>
                    <div class="account-switcher">
                        <button class="switcher-btn" onclick="toggleAccountMenu()">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="switcher-label">تبديل الحساب</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="account-menu" id="accountMenu">
                            <div class="account-menu-header">
                                <i class="fas fa-users"></i>
                                <span>الحسابات المخزنة</span>
                            </div>
                            
                            <?php foreach ($saved_accounts as $account): 
                                $type_name = getAccountTypeName($account['account_type']);
                                $type_icon = getAccountTypeIcon($account['account_type']);
                            ?>
                            <div class="account-item" data-account-id="<?php echo $account['account_id']; ?>" data-account-type="<?php echo $account['account_type']; ?>">
                                <i class="fas <?php echo $type_icon; ?>"></i>
                                <div class="account-info">
                                    <div class="account-name"><?php echo htmlspecialchars($account['account_name']); ?></div>
                                    <div class="account-type"><?php echo $type_name; ?></div>
                                    <div class="account-identifier"><?php echo htmlspecialchars($account['account_identifier']); ?></div>
                                </div>
                                <button class="delete-account-btn" onclick="deleteSavedAccount(event, <?php echo $account['account_id']; ?>, '<?php echo $account['account_type']; ?>')" title="حذف من القائمة">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="account-menu-footer">
                                <small>⚠️ حذف حساب من القائمة لا يحذف الحساب الأصلي</small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- زر تغيير كلمة المرور -->
                    <a href="change_password.php" class="header-btn" title="تغيير كلمة المرور">
                        <i class="fas fa-key"></i>
                    </a>
                    
                    <!-- زر تسجيل الخروج -->
                    <a href="logout.php" class="header-btn" title="تسجيل الخروج">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </a>
                    
                <?php else: ?>
                    <!-- للمستخدم غير المسجل -->
                    <a href="login.php" class="header-btn">
                        <i class="fas fa-sign-in-alt"></i> دخول
                    </a>
                <?php endif; ?>
                
            </div>
            <!-- ============================================ -->
            <!-- نهاية قسم header-actions -->
            <!-- ============================================ -->
        </header>

        <!-- زر القائمة للهاتف -->
        <button class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i> القائمة الرئيسية
        </button>

        <!-- القائمة الرئيسية الإبداعية -->
        <nav class="navbar" id="navbar" data-aos="fade-up" data-aos-duration="800">
            <?php if (isset($_SESSION['user_type'])): ?>
                
                <?php if ($_SESSION['user_type'] == 'admin'): ?>
                    <!-- ===== قائمة الإدارة الفاخرة ===== -->
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> الرئيسية
                    </a>
                    
                    <a href="students.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> الطلاب</a>
                    <a href="teachers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> المعلمون</a>
                    <a href="rings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'rings.php' ? 'active' : ''; ?>"><i class="fas fa-ring"></i> الحلقات</a>

                    <!-- دورات -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-graduation-cap"></i> الدورات <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="courses.php"><i class="fas fa-list"></i> إدارة الدورات</a>
                            <a href="courses_list.php"><i class="fas fa-eye"></i> عرض الدورات المتاحة</a>
                            <div class="divider"></div>
                            <a href="courses.php?action=add"><i class="fas fa-plus-circle"></i> إضافة دورة جديدة</a>
                        </div>
                    </div>

                    <!-- الحضور -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-calendar-check"></i> الحضور <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="attendance_teachers.php"><i class="fas fa-chalkboard-teacher"></i> حضور معلمين</a>
                            <a href="admin_attendance.php"><i class="fas fa-calendar-alt"></i> حضور طلاب</a>
                            <a href="attendance_summary.php"><i class="fas fa-chart-bar"></i> ملخص الحضور</a>
                            <div class="divider"></div>
                            <a href="manage_holidays.php"><i class="fas fa-calendar-times"></i> إدارة الإجازات</a>
                        </div>
                    </div>

                    <!-- التقدم والحفظ -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-quran"></i> التقدم والحفظ <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="admin_memorization_report.php"><i class="fas fa-chart-pie"></i> تقرير الحفظ</a>
                            <a href="view_progress.php"><i class="fas fa-chart-line"></i> متابعة التقدم</a>
                            <a href="parts_statistics_advanced.php"><i class="fas fa-layer-group"></i> إحصائيات الأجزاء</a>
                            <div class="divider"></div>
                            <a href="manage_students_without_memorization.php"><i class="fas fa-plus-circle"></i> إضافة حفظ للطلاب</a>
                        </div>
                    </div>
                    
                    <!-- الاختبارات -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-trophy"></i> الاختبارات <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="final_exam_dashboard.php"><i class="fas fa-graduation-cap"></i> الاختبارات النهائية</a>
                            <a href="final_exam_results.php"><i class="fas fa-trophy"></i> نتائج الاختبارات</a>
                        </div>
                    </div>

                    <!-- التقارير -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-chart-line"></i> التقارير <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="admin_payments_report.php"><i class="fas fa-money-bill-wave"></i> تقرير الاشتراكات</a>
                            <a href="teacher_attendance_report.php"><i class="fas fa-calendar-check"></i> تقرير حضور المعلمين</a>
                            <a href="repeated_absences.php"><i class="fas fa-exclamation-triangle"></i> الطلاب المنقطعون</a>
                            <a href="stats.php"><i class="fas fa-chart-pie"></i> إحصائيات عامة</a>
                            <a href="annual_achievements.php"><i class="fas fa-chart-line"></i> الإنجازات السنوية</a>
                            <a href="admin_wirds_stats.php"><i class="fas fa-praying-hands"></i> إحصائيات الأوراد</a>
                        </div>
                    </div>

                    <!-- المثاليون -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-crown"></i> المثاليون <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="students_of_month.php"><i class="fas fa-edit"></i> إدارة المثاليين</a>
                            <a href="students_of_month_view.php"><i class="fas fa-eye"></i> عرض المثاليين</a>
                        </div>
                    </div>

                    <!-- الطلاب الخاصين -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-crown"></i> الطلاب الخاصين <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="special_requests.php"><i class="fas fa-list"></i> طلبات الالتحاق</a>
                            <a href="special_students.php"><i class="fas fa-users"></i> الطلاب الخاصين</a>
                            <a href="special_rings.php"><i class="fas fa-ring"></i> حلقات خاص</a>
                            <a href="special_teacher_requests.php"><i class="fas fa-tasks"></i> طلبات المعلمين</a>
                        </div>
                    </div>

                    <!-- نقل وإدارة -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-exchange-alt"></i> النقل والإدارة <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="admin_transfer_students.php"><i class="fas fa-users"></i> نقل طلاب</a>
                            <a href="transfer_ring.php"><i class="fas fa-ring"></i> نقل حلقة</a>
                            <a href="unassigned_students.php"><i class="fas fa-user-plus"></i> طلاب بلا حلقات</a>
                            <a href="check_duplicate_students.php"><i class="fas fa-clone"></i> الطلاب المكررين</a>
                        </div>
                    </div>

                    <!-- أدوات النظام -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-tools"></i> أدوات النظام <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="reset_today_attendance.php" onclick="return confirm('هل أنت متأكد؟ هذه أداة خطيرة!')">
                                <i class="fas fa-trash-alt"></i> إعادة تعيين حضور اليوم
                            </a>
                            <a href="cron_setup.php"><i class="fas fa-clock"></i> إعدادات الغياب التلقائي</a>
                            <a href="check_auto_attendance_status.php"><i class="fas fa-robot"></i> فحص الغياب التلقائي</a>
                            <a href="payment_settings.php"><i class="fas fa-cog"></i> إعدادات الدفع</a>
                            <a href="set_hijri_date.php"><i class="fas fa-calendar-alt"></i> ضبط التاريخ الهجري</a>
                        </div>
                    </div>

                    <a href="change_password.php"><i class="fas fa-key"></i> تغيير كلمة المرور</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>

                <?php elseif ($_SESSION['user_type'] == 'teacher'): ?>
                    <!-- ===== قائمة المعلم الفاخرة ===== -->
                    <a href="teacher_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'teacher_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> الرئيسية
                    </a>

                    <!-- طلابي -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-users"></i> طلابي <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="students.php"><i class="fas fa-users"></i> قائمة الطلاب</a>
                            <a href="add_student.php"><i class="fas fa-user-plus"></i> إضافة طالب</a>
                            <a href="add_multiple_students.php"><i class="fas fa-users-plus"></i> إضافة عدة طلاب</a>
                            <div class="divider"></div>
                            <a href="rings.php"><i class="fas fa-ring"></i> حلقاتي</a>
                            <a href="add_students_to_ring.php"><i class="fas fa-user-plus"></i> إضافة طلاب للحلقة</a>
                            <a href="unassigned_students.php"><i class="fas fa-user-slash"></i> طلاب بلا حلقات</a>
                        </div>
                    </div>

                    <!-- الحضور -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-calendar-check"></i> الحضور <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="attendance_teachers.php"><i class="fas fa-user-check"></i> تسجيل حضوري</a>
                            <a href="teacher_quick_absence.php"><i class="fas fa-calendar-times"></i> اعتذار سريع</a>
                            <a href="attendance_students_teacher.php"><i class="fas fa-users"></i> حضور طلابي</a>
                            <a href="teacher_attendance_by_ring.php"><i class="fas fa-ring"></i> حضور حسب الحلقة</a>
                            <a href="edit_student_attendance.php"><i class="fas fa-edit"></i> تعديل الحضور</a>
                            <a href="teacher_attendance_report.php"><i class="fas fa-calendar-alt"></i> تقرير الحضور الشهري</a>
                            <div class="divider"></div>
                            <a href="manage_holidays.php"><i class="fas fa-calendar-alt"></i> الإجازات</a>
                        </div>
                    </div>

                    <!-- التقييم والمتابعة -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-star"></i> التقييم والمتابعة <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="advanced_evaluation.php"><i class="fas fa-star"></i> التقييم اليومي المتقدم</a>
                            <a href="teacher_memorization_dashboard.php"><i class="fas fa-quran"></i> حفظ الطلاب</a>
                            <a href="teacher_monthly_goals.php"><i class="fas fa-bullseye"></i> الأهداف الشهرية</a>
                            <a href="teacher_daily_monitoring.php"><i class="fas fa-chart-line"></i> متابعة الحفظ اليومي</a>
                            <a href="add_mistake.php"><i class="fas fa-exclamation-triangle"></i> تسجيل أخطاء</a>
                        </div>
                    </div>

                    <!-- الأوراد والصلوات -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-praying-hands"></i> الأوراد والصلوات <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="teacher_wirds_monitoring.php"><i class="fas fa-chart-line"></i> متابعة أوراد طلابي</a>
                            <a href="teacher_wirds_monitoring.php?tab=prayers"><i class="fas fa-mosque"></i> متابعة صلوات طلابي</a>
                        </div>
                    </div>

                    <!-- الاختبارات -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-trophy"></i> الاختبارات <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="final_exam_dashboard.php"><i class="fas fa-graduation-cap"></i> الاختبارات النهائية</a>
                            <a href="final_exam_results.php"><i class="fas fa-trophy"></i> نتائج الاختبارات</a>
                        </div>
                    </div>

                    <!-- التقارير -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-chart-line"></i> التقارير <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="stats.php"><i class="fas fa-calendar-check"></i> إحصائيات</a>
                            <a href="repeated_absences.php"><i class="fas fa-exclamation-triangle"></i> الطلاب المنقطعون</a>
                            <a href="teacher_achievement.php"><i class="fas fa-trophy"></i> إنجازاتي</a>
                            <a href="teacher_ring_achievements.php"><i class="fas fa-ring"></i> إنجازات الحلقة</a>
                            <a href="teacher_payments.php"><i class="fas fa-money-bill-wave"></i> اشتراكات طلابي</a>
                        </div>
                    </div>

                    <!-- الطلاب الخاصين -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-crown"></i> الطلاب الخاصين <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="special_teacher_requests.php"><i class="fas fa-list"></i> طلباتي</a>
                            <a href="special_students.php"><i class="fas fa-users"></i> طلابي الخاصين</a>
                            <a href="special_rings.php"><i class="fas fa-ring"></i> حلقاتي الخاصة</a>
                        </div>
                    </div>

                    <a href="change_password.php"><i class="fas fa-key"></i> تغيير كلمة المرور</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>

                <?php elseif ($_SESSION['user_type'] == 'student'): ?>
                    <!-- ===== قائمة الطالب الفاخرة ===== -->
                    <a href="student_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> الرئيسية
                    </a>

                    <!-- العبادات -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-praying-hands"></i> العبادات <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="my_wirds.php"><i class="fas fa-star-and-crescent"></i> أورادي اليومية</a>
                            <a href="my_prayers.php"><i class="fas fa-mosque"></i> صلواتي</a>
                            <div class="divider"></div>
                            <a href="#" onclick="recordPrayer(); return false;"><i class="fas fa-microphone-alt"></i> الصلاة على النبي ﷺ</a>
                        </div>
                    </div>

                    <a href="courses_list.php"><i class="fas fa-graduation-cap"></i> الدورات المتاحة</a>
                    <a href="student_quran.php"><i class="fas fa-quran"></i> المصحف التفاعلي</a>
                    <a href="my_plan.php"><i class="fas fa-robot"></i> خطتي الذكية</a>
                    <a href="guardian_daily_memorization.php"><i class="fas fa-check-circle"></i> تسجيل حفظي</a>
                    <a href="student_monthly_goal.php"><i class="fas fa-bullseye"></i> هدفي الشهري</a>
                    <a href="rings.php"><i class="fas fa-ring"></i> حلقاتي</a>
                    <a href="student_reports.php"><i class="fas fa-file-alt"></i> تقاريري</a>
                    <a href="student_certificates.php" style="position: relative;">
                        <i class="fas fa-certificate"></i> شهاداتي
                        <?php if ($certificates_count > 0): ?>
                            <span class="badge success"><?php echo $certificates_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="my_badges.php"><i class="fas fa-medal"></i> إنجازاتي</a>
                    <a href="record_recitation.php"><i class="fas fa-microphone-alt"></i> تسجيل التلاوة</a>
                    <a href="ai_tajweed_stats.php"><i class="fas fa-chart-line"></i> تقدم التجويد</a>
                    <a href="mistake_flashcards.php"><i class="fas fa-cards"></i> مراجعة أخطائي</a>
                    <a href="update_phone.php"><i class="fas fa-phone-alt"></i> تحديث رقم الهاتف</a>

<!-- التعلم التفاعلي -->
                    <div class="dropdown">
                        <span class="dropdown-toggle" onclick="toggleDropdown(this)">
                            <i class="fas fa-brain"></i> التعلم التفاعلي <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="dropdown-content">
                            <a href="mindmap_list.php"><i class="fas fa-map"></i> الخرائط الذهنية</a>
                            <a href="interactive_quiz.php?surah=1"><i class="fas fa-question-circle"></i> الأسئلة التفاعلية</a>
                        </div>
                    </div>

                    <a href="change_password.php"><i class="fas fa-key"></i> تغيير كلمة المرور</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>

                <?php elseif ($_SESSION['user_type'] == 'guardian'): ?>
                    <!-- ===== قائمة ولي الأمر الفاخرة ===== -->
                    <a href="guardian_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guardian_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> الرئيسية
                    </a>

                    <a href="guardian_students.php"><i class="fas fa-child"></i> أبنائي</a>
                    <a href="guardian_daily_memorization.php"><i class="fas fa-pen-alt"></i> تسجيل الحفظ اليومي</a>
                    <a href="student_reports.php"><i class="fas fa-file-alt"></i> تقاريرهم</a>
                    <a href="guardian_certificates.php"><i class="fas fa-certificate"></i> شهاداتهم</a>
                    <a href="guardian_exams.php"><i class="fas fa-file-alt"></i> اختباراتهم</a>
                    <a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> المعلمون</a>
                    <a href="rings.php"><i class="fas fa-ring"></i> الحلقات</a>
                    <a href="students_of_month_view.php"><i class="fas fa-crown"></i> المثاليون</a>
                    <a href="update_phone.php"><i class="fas fa-phone-alt"></i> تحديث رقم الهاتف</a>
                    <a href="change_password.php"><i class="fas fa-key"></i> تغيير كلمة المرور</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>

                <?php endif; ?>
                
            <?php else: ?>
                <!-- قائمة الزوار (غير مسجلين) -->
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a href="courses_list.php">
                    <i class="fas fa-graduation-cap"></i> الدورات المتاحة
                </a>
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                </a>
                <a href="enroll.php">
                    <i class="fas fa-user-plus"></i> تقديم طالب جديد
                </a>
                <a href="special_enroll.php">
                    <i class="fas fa-crown"></i> تقديم طالب خاص
                </a>
            <?php endif; ?>
        </nav>

        <script>
        // إخفاء مؤشر التحميل بعد تحميل الصفحة
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loader = document.getElementById('pageLoader');
                if (loader) loader.classList.add('hidden');
            }, 800);
        });

        // شريط التقدم عند التمرير
        window.addEventListener('scroll', function() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            const progressBar = document.getElementById('progress-bar');
            if (progressBar) progressBar.style.width = scrolled + '%';
            
            const navbar = document.getElementById('navbar');
            if (navbar) {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }
        });

        // تبديل القائمة للهاتف
        function toggleMenu() {
            const navbar = document.getElementById('navbar');
            if (navbar) navbar.classList.toggle('show');
        }

        // تبديل القوائم المنسدلة
        function toggleDropdown(element) {
            const dropdown = element.closest('.dropdown');
            if (!dropdown) return;
            dropdown.classList.toggle('active');
            
            document.querySelectorAll('.dropdown').forEach(el => {
                if (el !== dropdown) {
                    el.classList.remove('active');
                }
            });
        }

        // ============================================
        // دوال قائمة الحسابات المخزنة
        // ============================================
        
        function toggleAccountMenu() {
            const menu = document.getElementById('accountMenu');
            if (menu) menu.classList.toggle('show');
        }

        // إغلاق القائمة عند النقر خارجها
        document.addEventListener('click', function(event) {
            const switcher = document.querySelector('.account-switcher');
            if (switcher && !switcher.contains(event.target)) {
                const menu = document.getElementById('accountMenu');
                if (menu) menu.classList.remove('show');
            }
        });

        // التبديل إلى حساب مخزن
        document.querySelectorAll('.account-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // منع التنفيذ إذا تم الضغط على زر الحذف
                if (e.target.closest('.delete-account-btn')) return;
                
                const accountId = this.dataset.accountId;
                const accountType = this.dataset.accountType;
                
                if (accountId && accountType) {
                    window.location.href = `switch_account.php?user_id=${accountId}&user_type=${accountType}`;
                }
            });
        });

        // حذف حساب من القائمة
        function deleteSavedAccount(event, accountId, accountType) {
            event.stopPropagation();
            
            if (confirm('هل أنت متأكد من حذف هذا الحساب من القائمة؟\nلن يتم حذف الحساب الأصلي، فقط ستتم إزالته من قائمة التبديل.')) {
                fetch('delete_saved_account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `account_id=${accountId}&account_type=${accountType}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // إزالة العنصر من القائمة
                        const item = document.querySelector(`.account-item[data-account-id="${accountId}"][data-account-type="${accountType}"]`);
                        if (item) item.remove();
                        
                        // إذا لم يبق أي حسابات، أخف القائمة
                        const remainingItems = document.querySelectorAll('.account-item:not(.account-menu-header)');
                        if (remainingItems.length === 0) {
                            const switcher = document.querySelector('.account-switcher');
                            if (switcher) switcher.remove();
                        }
                        
                        showToast('تم حذف الحساب من القائمة', 'success');
                    } else {
                        showToast(data.error || 'حدث خطأ', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('حدث خطأ في الاتصال', 'error');
                });
            }
        }

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
                    showToast(data.message, 'success');
                    const counter = document.getElementById('todayPrayerCount');
                    if (counter) counter.textContent = data.today_count;
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
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: white;
                border-radius: 12px;
                padding: 12px 20px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                border-right: 4px solid ${type === 'success' ? '#28a745' : '#dc3545'};
                font-family: 'Cairo', sans-serif;
                display: flex;
                align-items: center;
                gap: 10px;
                direction: rtl;
            `;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // إغلاق القوائم عند النقر خارجها
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown').forEach(el => {
                    el.classList.remove('active');
                });
            }
        });
        // إخفاء مؤشر التحميل
window.addEventListener('load', function() {
    setTimeout(function() {
        var loader = document.getElementById('pageLoader');
        if (loader) loader.classList.add('hidden');
    }, 500);
});

// شريط التقدم
window.addEventListener('scroll', function() {
    var winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    var height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    var scrolled = (winScroll / height) * 100;
    var progressBar = document.getElementById('progress-bar');
    if (progressBar) progressBar.style.width = scrolled + '%';
});

// إغلاق قائمة الهاتف
document.querySelectorAll('.navbar a').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            document.getElementById('navbar').classList.remove('show');
        }
    });
});

console.log('✅ نظام تبديل الحسابات جاهز');
</script>

</div>

<?php 
if (file_exists('includes/prayer_notification.php')) {
    require_once 'includes/prayer_notification.php';
    echo showPrayerNotification();
}
?>

</body>
</html>
                 
        