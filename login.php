<?php
// ============================================
// ملف: login.php - تسجيل الدخول (نسخة محدثة)
// مع حفظ الحسابات تلقائياً لاستخدامها في تبديل الحسابات
// آخر تحديث: 2026-04-28
// ============================================

require_once 'config.php';

// إذا كان المستخدم مسجلاً بالفعل
if (isLoggedIn()) {
    if (isAdmin()) redirect('dashboard.php');
    elseif (isTeacher()) redirect('teacher_dashboard.php');
    elseif (isGuardian()) redirect('guardian_dashboard.php');
    elseif (isStudent()) redirect('student_dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? 'admin';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember']) ? true : false;

    // ============================================
    // تسجيل دخول الإدارة
    // ============================================
    if ($login_type === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $display_name = $user['name'] ?? $user['username'];
            loginUser($user['id'], 'admin', $display_name, $remember_me);
            
            // حفظ الحساب في قائمة الحسابات المخزنة
            if (function_exists('saveAccountForUser')) {
                saveAccountForUser($pdo, $user['id'], $user['id'], 'admin', $display_name, $username);
            }
            
            redirect('dashboard.php');
        } else {
            $error = 'بيانات دخول الإدارة غير صحيحة';
        }
    } 
    // ============================================
    // تسجيل دخول المعلم
    // ============================================
    elseif ($login_type === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ? AND can_login = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            loginUser($user['id'], 'teacher', $user['name'], $remember_me);
            
            // حفظ الحساب في قائمة الحسابات المخزنة
            if (function_exists('saveAccountForUser')) {
                saveAccountForUser($pdo, $user['id'], $user['id'], 'teacher', $user['name'], $username);
            }
            
            redirect('teacher_dashboard.php');
        } else {
            $error = 'بيانات دخول المعلم غير صحيحة';
        }
    } 
    // ============================================
    // تسجيل دخول ولي الأمر (يدعم رقم الهاتف)
    // ============================================
    elseif ($login_type === 'guardian') {
        // تنظيف رقم الهاتف (إزالة أي أحرف غير رقمية)
        $clean_phone = preg_replace('/[^0-9]/', '', $username);
        
        // البحث عن ولي الأمر برقم الهاتف أو اسم المستخدم
        $stmt = $pdo->prepare("
            SELECT * FROM guardians 
            WHERE phone = ? OR username = ?
        ");
        $stmt->execute([$clean_phone, $username]);
        $user = $stmt->fetch();
        
        // إذا لم يوجد ولي أمر، نحاول إنشاء حساب تلقائي
        if (!$user && !empty($clean_phone) && strlen($clean_phone) >= 10) {
            // إنشاء حساب ولي أمر جديد تلقائياً
            $guardian_name = "ولي أمر (رقم: " . substr($clean_phone, -6) . ")";
            $guardian_username = 'guardian_' . time() . '_' . rand(100, 999);
            $hashed_password = password_hash($clean_phone, PASSWORD_DEFAULT);
            
            $insert = $pdo->prepare("
                INSERT INTO guardians (name, phone, username, password, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $insert->execute([$guardian_name, $clean_phone, $guardian_username, $hashed_password]);
            $guardian_id = $pdo->lastInsertId();
            
            // ربط أي طالب بنفس رقم الهاتف بهذا الحساب
            $pdo->prepare("
                UPDATE students SET guardian_id = ? 
                WHERE parent_phone = ? AND (guardian_id IS NULL OR guardian_id = 0)
            ")->execute([$guardian_id, $clean_phone]);
            
            // جلب المستخدم الجديد
            $stmt = $pdo->prepare("SELECT * FROM guardians WHERE id = ?");
            $stmt->execute([$guardian_id]);
            $user = $stmt->fetch();
        }
        
        if ($user && password_verify($password, $user['password'])) {
            loginUser($user['id'], 'guardian', $user['name'], $remember_me);
            
            // حفظ الحساب في قائمة الحسابات المخزنة
            if (function_exists('saveAccountForUser')) {
                $identifier = $user['phone'] ?? $username;
                saveAccountForUser($pdo, $user['id'], $user['id'], 'guardian', $user['name'], $identifier);
            }
            
            redirect('guardian_dashboard.php');
        } else {
            $error = 'بيانات دخول ولي الأمر غير صحيحة';
        }
    } 
    // ============================================
    // تسجيل دخول الطالب
    // ============================================
    elseif ($login_type === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            loginUser($user['id'], 'student', $user['name'], $remember_me);
            
            // حفظ الحساب في قائمة الحسابات المخزنة
            if (function_exists('saveAccountForUser')) {
                saveAccountForUser($pdo, $user['id'], $user['id'], 'student', $user['name'], $username);
            }
            
            redirect('student_dashboard.php');
        } else {
            $error = 'بيانات دخول الطالب غير صحيحة';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دار التقوى - تسجيل الدخول</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #0a2a2c;
            --primary-dark: #051a1c;
            --primary-light: #1e4a4d;
            --secondary: #d4af37;
            --secondary-light: #e6c77c;
            --secondary-dark: #b38b40;
            --success: #2ecc71;
            --danger: #e74c3c;
            --white: #ffffff;
            --gray: #7f8c8d;
            --gray-light: #ecf0f1;
            --dark: #2c3e50;
            
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 5px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 35px rgba(0,0,0,0.12);
            --shadow-gold: 0 5px 20px rgba(212, 175, 55, 0.15);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 28px;
            --radius-full: 999px;
        }

        body {
            font-family: 'Cairo', sans-serif;
            min-height: 100vh;
            background: linear-gradient(145deg, #1a472a 0%, #0d2818 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: radial-gradient(circle at 20% 30%, rgba(212, 175, 55, 0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-wrap: wrap;
            background: var(--white);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .info-side {
            flex: 1.2;
            background: linear-gradient(145deg, var(--primary), var(--primary-dark));
            padding: 50px 40px;
            color: var(--white);
            position: relative;
            min-width: 280px;
        }

        .info-side::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.05"><path fill="white" d="M20,20 L80,20 L80,80 L20,80 Z"/><circle cx="50" cy="50" r="15"/></svg>');
            background-size: 60px;
            pointer-events: none;
        }

        .logo-area {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            border: 2px solid var(--secondary);
        }

        .logo-area h2 {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .logo-area p {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .features {
            margin-top: 50px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .feature-icon {
            width: 45px;
            height: 45px;
            background: rgba(212, 175, 55, 0.15);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--secondary);
        }

        .feature-text h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .feature-text p {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .form-side {
            flex: 1;
            padding: 50px 45px;
            background: var(--white);
            min-width: 320px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .form-header h3 {
            font-size: 1.6rem;
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .tabs {
            display: flex;
            gap: 12px;
            background: var(--gray-light);
            padding: 10px;
            border-radius: var(--radius-lg);
            margin-bottom: 35px;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 15px 8px;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
        }

        .tab i {
            font-size: 1.6rem;
            display: block;
            margin-bottom: 8px;
            color: var(--gray);
        }

        .tab span {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray);
            display: block;
        }

        .tab.active {
            background: linear-gradient(145deg, var(--primary), var(--primary-light));
            box-shadow: var(--shadow-sm);
        }

        .tab.active i {
            color: var(--secondary);
        }

        .tab.active span {
            color: var(--white);
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.85rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1rem;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 45px 14px 18px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: all 0.2s;
            font-family: 'Cairo', sans-serif;
            background: var(--white);
        }

        .input-wrapper input:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: var(--shadow-gold);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            color: var(--gray);
        }

        .checkbox input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--secondary);
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: var(--secondary);
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(145deg, var(--primary), var(--primary-light));
            color: var(--white);
            border: none;
            padding: 15px;
            border-radius: var(--radius-full);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .login-btn:hover {
            background: linear-gradient(145deg, var(--primary-light), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-gold);
        }

        .register-links {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }

        .register-links span {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .register-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            margin: 0 5px;
            transition: color 0.2s;
        }

        .register-links a:hover {
            color: var(--secondary);
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-error {
            background: #fef3f2;
            color: var(--danger);
            border-right: 3px solid var(--danger);
        }

        .alert-success {
            background: #e8f8f0;
            color: var(--success);
            border-right: 3px solid var(--success);
        }

        .info-note {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 0.75rem;
            color: #0c5460;
            text-align: center;
        }

        @media (max-width: 900px) {
            .info-side {
                display: none;
            }
            .form-side {
                flex: 1;
                min-width: 320px;
            }
        }

        @media (max-width: 550px) {
            .form-side {
                padding: 30px 25px;
            }
            .tabs {
                gap: 8px;
                padding: 8px;
            }
            .tab {
                padding: 10px 5px;
            }
            .tab i {
                font-size: 1.3rem;
                margin-bottom: 5px;
            }
            .tab span {
                font-size: 0.7rem;
            }
            .form-header h3 {
                font-size: 1.4rem;
            }
            .login-btn {
                padding: 12px;
                font-size: 1rem;
            }
        }

        @media (max-width: 400px) {
            .tabs {
                gap: 5px;
                padding: 5px;
            }
            .tab {
                padding: 8px 3px;
            }
            .tab i {
                font-size: 1.1rem;
            }
            .tab span {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="info-side">
            <div class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-quran"></i>
                </div>
                <h2>دار التقوى</h2>
                <p>لتحفيظ القرآن الكريم</p>
            </div>
            
            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="feature-text">
                        <h4>معلمون متخصصون</h4>
                        <p>أفضل المعلمين في تحفيظ القرآن</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-text">
                        <h4>متابعة دقيقة</h4>
                        <p>نظام متابعة متكامل للطلاب</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="feature-text">
                        <h4>شهادات معتمدة</h4>
                        <p>شهادات إنجاز لكل طالب</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="feature-text">
                        <h4>تطبيق متكامل</h4>
                        <p>متابعة عبر الهاتف أينما كنت</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-side">
            <div class="form-header">
                <h3>مرحباً بك</h3>
                <p>سجل دخولك للوصول إلى لوحة التحكم</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="tabs" id="tabs">
                <div class="tab active" data-type="admin">
                    <i class="fas fa-user-tie"></i>
                    <span>إدارة</span>
                </div>
                <div class="tab" data-type="teacher">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>معلم</span>
                </div>
                <div class="tab" data-type="guardian">
                    <i class="fas fa-user-friends"></i>
                    <span>ولي أمر</span>
                </div>
                <div class="tab" data-type="student">
                    <i class="fas fa-user-graduate"></i>
                    <span>طالب</span>
                </div>
            </div>

            <form method="post" id="loginForm">
                <input type="hidden" name="login_type" id="login_type" value="admin">

                <div class="input-group">
                    <label class="input-label" id="usernameLabel">اسم المستخدم</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" placeholder="أدخل اسم المستخدم" required autocomplete="username">
                    </div>
                </div>

                <div class="input-group">
                    <label class="input-label">كلمة المرور</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                    </div>
                </div>

                <div id="guardianNote" class="info-note" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    يمكنك تسجيل الدخول برقم هاتفك وكلمة المرور (نفس الرقم)
                </div>

                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember" id="remember">
                        <span>تذكرني</span>
                    </label>
                    <a href="#" class="forgot-link">نسيت كلمة المرور؟</a>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    دخول
                    <i class="fas fa-arrow-left"></i>
                </button>
            </form>

            <div class="register-links">
                <span>ليس لديك حساب؟</span>
                <a href="enroll.php">تقديم طالب جديد</a>
                <span>|</span>
                <a href="special_enroll.php">تقديم طالب خاص</a>
            </div>
        </div>
    </div>

    <script>
        // تبديل التبويبات
        const tabs = document.querySelectorAll('.tab');
        const loginType = document.getElementById('login_type');
        const usernameLabel = document.getElementById('usernameLabel');
        const usernameInput = document.getElementById('username');
        const guardianNote = document.getElementById('guardianNote');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const type = this.dataset.type;
                loginType.value = type;

                if (type === 'admin') {
                    usernameLabel.innerHTML = 'اسم المستخدم';
                    usernameInput.placeholder = 'admin';
                    guardianNote.style.display = 'none';
                } else if (type === 'teacher') {
                    usernameLabel.innerHTML = 'البريد الإلكتروني';
                    usernameInput.placeholder = 'teacher@example.com';
                    guardianNote.style.display = 'none';
                } else if (type === 'guardian') {
                    usernameLabel.innerHTML = 'رقم الهاتف أو اسم المستخدم';
                    usernameInput.placeholder = '01012345678';
                    guardianNote.style.display = 'block';
                } else if (type === 'student') {
                    usernameLabel.innerHTML = 'اسم المستخدم';
                    usernameInput.placeholder = 'student123';
                    guardianNote.style.display = 'none';
                }
            });
        });

        console.log('%c✨ دار التقوى لتحفيظ القرآن الكريم ✨', 'color: #d4af37; font-size: 14px; font-weight: bold;');
        console.log('%c✅ نظام حفظ الحسابات مفعل - سيتم حفظ أي حساب تسجل به تلقائياً', 'color: #28a745; font-size: 12px;');
    </script>
</body>
</html>
             