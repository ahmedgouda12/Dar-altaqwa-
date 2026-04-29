<?php
// ============================================
// ملف: index.php - الصفحة الرئيسية
// ============================================

require_once 'config.php';

// إذا كان المستخدم مسجلاً، نوجهه للوحة المناسبة
if (isLoggedIn()) {
    if (isAdmin()) redirect('dashboard.php');
    if (isTeacher()) redirect('teacher_dashboard.php');
    if (isStudent()) redirect('student_dashboard.php');
    if (isGuardian()) redirect('guardian_dashboard.php');
}

$pageTitle = 'الرئيسية';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دار التقوى - تحفيظ القرآن الكريم</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #1a472a, #0d2818);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: "﷽";
            position: fixed;
            bottom: 20px;
            right: 20px;
            font-size: 100px;
            font-family: 'Amiri', serif;
            color: rgba(255,255,255,0.05);
            pointer-events: none;
        }
        .hero {
            background: rgba(255,255,255,0.98);
            border-radius: 40px;
            padding: 50px;
            text-align: center;
            max-width: 900px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #c9a96b, #1e3c3f, #c9a96b);
        }
        .logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            border: 3px solid #c9a96b;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .logo i { font-size: 50px; color: #c9a96b; }
        h1 {
            color: #1e3c3f;
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 800;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            font-size: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            box-shadow: 0 5px 15px rgba(30,60,63,0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #c9a96b, #dbb87c);
            color: #1e3c3f;
            box-shadow: 0 5px 15px rgba(201,169,107,0.3);
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 20px;
        }
        .feature {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 20px;
            transition: 0.3s;
        }
        .feature:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .feature i {
            font-size: 2.5rem;
            color: #c9a96b;
            margin-bottom: 15px;
        }
        .feature h3 {
            color: #1e3c3f;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        .feature p {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        @media (max-width: 768px) {
            .hero { padding: 30px; }
            h1 { font-size: 1.6rem; }
            .features { grid-template-columns: 1fr; }
            .btn-group { flex-direction: column; }
            .btn { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="logo">
            <i class="fas fa-quran"></i>
        </div>
        <h1>دار التقوى لتحفيظ القرآن الكريم</h1>
        <p class="subtitle">منيا القمح - الشرقية</p>
        
        <div class="btn-group">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </a>
            <a href="enroll.php" class="btn btn-secondary">
                <i class="fas fa-user-plus"></i> تقديم طالب جديد
            </a>
        </div>
        
        <div class="features">
            <div class="feature">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>معلمون متخصصون</h3>
                <p>نخبة من المعلمين والمعلمات في تحفيظ القرآن وتجويده</p>
            </div>
            <div class="feature">
                <i class="fas fa-chart-line"></i>
                <h3>متابعة دقيقة</h3>
                <p>نظام متابعة إلكتروني متكامل لأولياء الأمور</p>
            </div>
        </div>
    </div>
</body>
</html>