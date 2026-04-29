<?php
// ============================================
// ملف: course_register.php - تسجيل طالب في دورة
// ============================================

ob_start();
require_once 'config.php';

$pageTitle = 'تسجيل في دورة - دار التقوى';
$error = '';
$success = '';
$course = null;

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($course_id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, t.name as teacher_name
        FROM courses c
        LEFT JOIN teachers t ON c.teacher_id = t.id
        WHERE c.id = ? AND c.status = 'active'
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $error = "❌ الدورة غير متاحة للتسجيل حالياً";
    } else {
        // التحقق من المقاعد المتاحة
        $enrolled = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?");
        $enrolled->execute([$course_id]);
        $current_enrolled = $enrolled->fetchColumn();
        $remaining = $course['max_students'] - $current_enrolled;
        
        if ($remaining <= 0) {
            $error = "❌ عذراً، اكتمل العدد المطلوب لهذه الدورة";
            $course = null;
        }
    }
}

// معالجة تسجيل الطالب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course_id > 0 && $course) {
    $student_name = trim($_POST['student_name'] ?? '');
    $student_phone = trim($_POST['student_phone'] ?? '');
    $student_email = trim($_POST['student_email'] ?? '');
    $student_age = (int)($_POST['student_age'] ?? 0);
    $student_gender = $_POST['student_gender'] ?? '';
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $previous_experience = trim($_POST['previous_experience'] ?? '');
    $motivation = trim($_POST['motivation'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // التحقق من المدخلات
    if (empty($student_name)) {
        $error = '❌ اسم الطالب مطلوب';
    } elseif (empty($student_phone)) {
        $error = '❌ رقم الهاتف مطلوب';
    } elseif ($student_age < 6) {
        $error = '❌ العمر يجب أن يكون 6 سنوات على الأقل';
    } elseif (empty($student_gender)) {
        $error = '❌ الجنس مطلوب';
    } else {
        try {
            // التحقق من عدم تكرار التسجيل
            $check = $pdo->prepare("
                SELECT COUNT(*) FROM course_enrollments 
                WHERE course_id = ? AND student_phone = ?
            ");
            $check->execute([$course_id, $student_phone]);
            if ($check->fetchColumn() > 0) {
                $error = "❌ هذا الرقم مسجل بالفعل في هذه الدورة";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO course_enrollments 
                    (course_id, student_name, student_phone, student_email, student_age, student_gender,
                     parent_name, parent_phone, address, previous_experience, motivation, notes, enrollment_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
                ");
                $stmt->execute([
                    $course_id, $student_name, $student_phone, $student_email, $student_age, $student_gender,
                    $parent_name, $parent_phone, $address, $previous_experience, $motivation, $notes
                ]);
                
                $success = "✅ تم تسجيلك في الدورة بنجاح. سيتم التواصل معك قريباً.";
            }
        } catch (PDOException $e) {
            $error = "❌ خطأ في التسجيل: " . $e->getMessage();
        }
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --success: #28a745;
            --danger: #dc3545;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .register-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .register-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .course-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .course-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .course-header .course-code {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 30px;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .register-body {
            padding: 30px;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .form-group label i {
            color: var(--secondary);
            margin-left: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40,167,69,0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-right: 5px solid var(--success);
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 5px solid var(--danger);
        }
        
        .course-info {
            background: #e7f3ff;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 8px 15px;
            border-radius: 30px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .register-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="register-container">
    <div class="register-card">
        <div class="course-header">
            <span class="course-code"><?php echo $course ? htmlspecialchars($course['course_code']) : 'دورة'; ?></span>
            <h1><?php echo $course ? htmlspecialchars($course['course_name']) : 'تسجيل في دورة'; ?></h1>
            <p>دار التقوى لتحفيظ القرآن الكريم</p>
        </div>
        
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="courses_list.php" class="btn-submit" style="background: var(--primary); text-decoration: none;">عرض جميع الدورات</a>
                </div>
            <?php elseif ($course): ?>
            
            <!-- معلومات الدورة -->
            <div class="course-info">
                <div class="info-item"><i class="fas fa-chalkboard-teacher"></i> المعلم: <?php echo $course['teacher_name'] ?? 'سيتم تحديده'; ?></div>
                <div class="info-item"><i class="fas fa-calendar-alt"></i> من <?php echo $course['start_date']; ?> إلى <?php echo $course['end_date']; ?></div>
                <?php
                $enrolled = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?");
                $enrolled->execute([$course['id']]);
                $current_enrolled = $enrolled->fetchColumn();
                $remaining = $course['max_students'] - $current_enrolled;
                ?>
                <div class="info-item"><i class="fas fa-users"></i> المتبقي: <?php echo $remaining; ?> مقعد</div>
                <?php if ($course['price'] > 0): ?>
                <div class="info-item"><i class="fas fa-money-bill-wave"></i> الرسوم: <?php echo number_format($course['price'], 2); ?> ج.م</div>
                <?php else: ?>
                <div class="info-item"><i class="fas fa-clock"></i> الرسوم: لم يتم التحديد بعد</div>
                <?php endif; ?>
            </div>
            
            <form method="post">
                <!-- بيانات الطالب -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-user-graduate"></i><h3>بيانات الطالب</h3></div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> اسم الطالب كاملاً *</label>
                        <input type="text" name="student_name" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> رقم الهاتف *</label>
                            <input type="tel" name="student_phone" class="form-control" required placeholder="01012345678">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                            <input type="email" name="student_email" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> العمر *</label>
                            <input type="number" name="student_age" class="form-control" required min="6" max="100">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> الجنس *</label>
                            <div class="radio-group">
                                <label class="radio-option"><input type="radio" name="student_gender" value="male" required> ذكر</label>
                                <label class="radio-option"><input type="radio" name="student_gender" value="female" required> أنثى</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- بيانات ولي الأمر -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-user-tie"></i><h3>بيانات ولي الأمر</h3></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> اسم ولي الأمر</label>
                            <input type="text" name="parent_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> رقم ولي الأمر</label>
                            <input type="tel" name="parent_phone" class="form-control" placeholder="للتواصل">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> العنوان</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                </div>
                
                <!-- معلومات إضافية -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-info-circle"></i><h3>معلومات إضافية</h3></div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-quran"></i> الخبرة السابقة</label>
                        <textarea name="previous_experience" class="form-control" rows="2" placeholder="هل سبق لك دراسة القرآن أو التجويد؟"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> الدافع للتسجيل</label>
                        <textarea name="motivation" class="form-control" rows="2" placeholder="ما الذي يحفزك للتسجيل في هذه الدورة؟"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit"><i class="fas fa-check-circle"></i> تسجيل في الدورة</button>
            </form>
            
            <?php else: ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> الدورة غير متاحة للتسجيل حالياً</div>
                <div style="text-align: center;">
                    <a href="courses_list.php" class="btn-submit" style="background: var(--primary); text-decoration: none;">عرض الدورات المتاحة</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>