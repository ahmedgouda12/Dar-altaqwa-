<?php
// ============================================
// ملف: special_enroll.php
// تقديم طالب خاص - مع خيار تحديد المحفوظات (سور/أجزاء/كلاهما)
// ============================================

require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'تقديم طالب خاص - دار التقوى';
$error = '';
$success = '';

// جلب أنواع الحلقات الخاصة
$types = $pdo->query("SELECT * FROM special_student_types WHERE is_active = 1")->fetchAll();

function generateRequestNumber() {
    return 'SP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function getSurahNameForSpecial($number) {
    $surahs = [
        1 => 'الفاتحة', 2 => 'البقرة', 3 => 'آل عمران', 4 => 'النساء',
        5 => 'المائدة', 6 => 'الأنعام', 7 => 'الأعراف', 8 => 'الأنفال',
        9 => 'التوبة', 10 => 'يونس', 11 => 'هود', 12 => 'يوسف',
        13 => 'الرعد', 14 => 'إبراهيم', 15 => 'الحجر', 16 => 'النحل',
        17 => 'الإسراء', 18 => 'الكهف', 19 => 'مريم', 20 => 'طه',
        21 => 'الأنبياء', 22 => 'الحج', 23 => 'المؤمنون', 24 => 'النور',
        25 => 'الفرقان', 26 => 'الشعراء', 27 => 'النمل', 28 => 'القصص',
        29 => 'العنكبوت', 30 => 'الروم', 31 => 'لقمان', 32 => 'السجدة',
        33 => 'الأحزاب', 34 => 'سبأ', 35 => 'فاطر', 36 => 'يس',
        37 => 'الصافات', 38 => 'ص', 39 => 'الزمر', 40 => 'غافر',
        41 => 'فصلت', 42 => 'الشورى', 43 => 'الزخرف', 44 => 'الدخان',
        45 => 'الجاثية', 46 => 'الأحقاف', 47 => 'محمد', 48 => 'الفتح',
        49 => 'الحجرات', 50 => 'ق', 51 => 'الذاريات', 52 => 'الطور',
        53 => 'النجم', 54 => 'القمر', 55 => 'الرحمن', 56 => 'الواقعة',
        57 => 'الحديد', 58 => 'المجادلة', 59 => 'الحشر', 60 => 'الممتحنة',
        61 => 'الصف', 62 => 'الجمعة', 63 => 'المنافقون', 64 => 'التغابن',
        65 => 'الطلاق', 66 => 'التحريم', 67 => 'الملك', 68 => 'القلم',
        69 => 'الحاقة', 70 => 'المعارج', 71 => 'نوح', 72 => 'الجن',
        73 => 'المزمل', 74 => 'المدثر', 75 => 'القيامة', 76 => 'الإنسان',
        77 => 'المرسلات', 78 => 'النبأ', 79 => 'النازعات', 80 => 'عبس',
        81 => 'التكوير', 82 => 'الانفطار', 83 => 'المطففين', 84 => 'الانشقاق',
        85 => 'البروج', 86 => 'الطارق', 87 => 'الأعلى', 88 => 'الغاشية',
        89 => 'الفجر', 90 => 'البلد', 91 => 'الشمس', 92 => 'الليل',
        93 => 'الضحى', 94 => 'الشرح', 95 => 'التين', 96 => 'العلق',
        97 => 'القدر', 98 => 'البينة', 99 => 'الزلزلة', 100 => 'العاديات',
        101 => 'القارعة', 102 => 'التكاثر', 103 => 'العصر', 104 => 'الهمزة',
        105 => 'الفيل', 106 => 'قريش', 107 => 'الماعون', 108 => 'الكوثر',
        109 => 'الكافرون', 110 => 'النصر', 111 => 'المسد', 112 => 'الإخلاص',
        113 => 'الفلق', 114 => 'الناس'
    ];
    return $surahs[$number] ?? 'غير معروف';
}

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_enrollment'])) {
    $student_name = trim($_POST['student_name'] ?? '');
    $student_gender = $_POST['student_gender'] ?? '';
    $birth_date = $_POST['birth_date'] ?? null;
    $student_age = null;
    $age_input = $_POST['age_input'] ?? '';
    $student_phone = trim($_POST['student_phone'] ?? '');
    
    if (!empty($birth_date)) {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $student_age = $today->diff($birth)->y;
    } elseif (!empty($age_input) && is_numeric($age_input)) {
        $student_age = (int)$age_input;
    }
    
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $preferred_ring_type_id = (int)($_POST['preferred_ring_type_id'] ?? 0);
    $preferred_time = trim($_POST['preferred_time'] ?? '');
    $previous_quran_level = trim($_POST['previous_quran_level'] ?? '');
    $special_needs = trim($_POST['special_needs'] ?? '');
    $motivation = trim($_POST['motivation'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // نوع المحفوظات
    $memorization_type = $_POST['memorization_type'] ?? 'both';
    
    // السور والأجزاء المحفوظة
    $mem_surahs = [];
    $mem_parts = [];
    
    if ($memorization_type == 'surahs' || $memorization_type == 'both') {
        $mem_surahs = isset($_POST['memorized_surahs']) ? array_map('intval', $_POST['memorized_surahs']) : [];
    }
    if ($memorization_type == 'parts' || $memorization_type == 'both') {
        $mem_parts = isset($_POST['memorized_parts']) ? array_map('intval', $_POST['memorized_parts']) : [];
    }
    
    $mem_surahs_json = json_encode($mem_surahs);
    $mem_parts_json = json_encode($mem_parts);
    $total_surahs = count($mem_surahs);
    $total_parts = count($mem_parts);
    
    $main_contact_phone = ($student_age >= 18 && !empty($student_phone)) ? $student_phone : $parent_phone;
    
    // التحقق من المدخلات
    if (empty($student_name)) {
        $error = '❌ اسم الطالب مطلوب';
    } elseif (empty($student_gender)) {
        $error = '❌ الجنس مطلوب';
    } elseif (empty($student_age) || $student_age < 3) {
        $error = '❌ العمر غير صالح (يجب أن يكون 3 سنوات أو أكثر)';
    } elseif (empty($parent_name)) {
        $error = '❌ اسم ولي الأمر مطلوب';
    } elseif (empty($main_contact_phone)) {
        $error = '❌ رقم التواصل مطلوب';
    } elseif ($preferred_ring_type_id == 0) {
        $error = '❌ يرجى اختيار نوع الحلقة (حضوري أو أونلاين)';
    } else {
        try {
            $request_number = generateRequestNumber();
            $edit_token = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("
                INSERT INTO special_enrollment_requests 
                (request_number, student_name, student_gender, birth_date, student_age, student_phone,
                 parent_name, parent_phone, parent_email, address, preferred_ring_type_id,
                 preferred_time, previous_quran_level, special_needs, motivation,
                 memorized_surahs, memorized_parts, memorization_type, total_memorized_surahs, total_memorized_parts,
                 notes, edit_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $request_number, $student_name, $student_gender, $birth_date, $student_age, $student_phone,
                $parent_name, $parent_phone, $parent_email, $address, $preferred_ring_type_id,
                $preferred_time, $previous_quran_level, $special_needs, $motivation,
                $mem_surahs_json, $mem_parts_json, $memorization_type, $total_surahs, $total_parts,
                $notes, $edit_token
            ]);
            
            $request_id = $pdo->lastInsertId();
            
            $edit_link = "special_edit_enrollment.php?id={$request_id}&token={$edit_token}";
            
            $success = "
                <div style='text-align: center;'>
                    <i class='fas fa-check-circle' style='font-size: 3rem; color: #28a745;'></i>
                    <h3>✅ تم تقديم طلب الالتحاق بنجاح</h3>
                    <p>رقم الطلب: <strong>{$request_number}</strong></p>
                    <p>عدد السور المحفوظة: <strong>{$total_surahs}</strong> سورة</p>
                    <p>عدد الأجزاء المحفوظة: <strong>{$total_parts}</strong> جزء</p>
                    <p>سيتم التواصل معكم خلال 48 ساعة لتأكيد القبول وتحديد المواعيد.</p>
                    <div style='background: #e8f5e9; padding: 15px; border-radius: 12px; margin: 15px 0;'>
                        <i class='fas fa-edit'></i>
                        <strong>رابط تعديل الطلب:</strong><br>
                        <a href='{$edit_link}' target='_blank' style='color: #28a745; word-break: break-all;'>
                            {$edit_link}
                        </a>
                        <br><small>⚠️ يمكنك استخدام هذا الرابط لتعديل بيانات الطلب خلال 3 أيام</small>
                    </div>
                    <a href='index.php' class='btn btn-primary'>العودة للرئيسية</a>
                </div>
            ";
            
        } catch (PDOException $e) {
            $error = "❌ حدث خطأ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>دار التقوى - تقديم طالب خاص</title>
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
            --radius: 20px;
            --radius-sm: 12px;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .enroll-container { max-width: 1000px; margin: 0 auto; }
        
        .enroll-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .enroll-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .enroll-header::before {
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
        
        .enroll-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .enroll-header h1 i { color: var(--secondary); }
        
        .special-badge {
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .enroll-body { padding: 35px; }
        
        .form-section {
            background: #f8f9fa;
            border-radius: var(--radius);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--secondary);
        }
        
        .form-group { margin-bottom: 20px; }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .form-group label i { color: var(--secondary); margin-left: 5px; }
        .form-group label .required { color: var(--danger); margin-right: 3px; }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: 0.3s;
            font-family: 'Cairo', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .radio-group {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px 25px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            transition: 0.3s;
        }
        
        .radio-option:hover {
            border-color: var(--secondary);
            background: #fff;
        }
        
        .type-selector {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .type-option {
            flex: 1;
            text-align: center;
            padding: 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: var(--radius);
            cursor: pointer;
            transition: 0.3s;
        }
        
        .type-option.selected {
            border-color: var(--secondary);
            background: linear-gradient(135deg, #fff8e7, #fff3d6);
        }
        
        .type-option i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        /* تنسيق اختيار نوع المحفوظات */
        .memorization-type-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .mem-type-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: 0.3s;
        }
        
        .mem-type-option.selected {
            border-color: var(--secondary);
            background: #fff8e7;
        }
        
        .mem-type-option i {
            font-size: 1.5rem;
            margin-bottom: 5px;
            display: block;
        }
        
        .surahs-grid, .parts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: white;
            border-radius: var(--radius-sm);
            border: 1px solid #e9ecef;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        
        .checkbox-item:hover { background: #e9ecef; }
        .checkbox-item input { width: 18px; height: 18px; cursor: pointer; }
        .checkbox-item label { flex: 1; cursor: pointer; margin: 0; font-size: 0.9rem; }
        
        .memorization-summary {
            background: #e7f3ff;
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-top: 15px;
            text-align: center;
            color: #0c5460;
        }
        
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40,167,69,0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 5px solid var(--danger);
        }
        
        .info-box {
            background: #e7f3ff;
            border-radius: var(--radius);
            padding: 20px;
            margin: 25px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            border-right: 6px solid var(--info);
        }
        
        @media (max-width: 768px) {
            .enroll-body { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .type-selector { flex-direction: column; }
            .memorization-type-selector { flex-direction: column; }
            .surahs-grid, .parts-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="enroll-container">
    <div class="enroll-card">
        <div class="enroll-header">
            <h1><i class="fas fa-crown"></i> تقديم طالب خاص</h1>
            <p>برنامج متخصص للطلاب المتميزين - حضوري أو أونلاين</p>
            <div class="special-badge">
                <i class="fas fa-star"></i> نظام خاص بالدار
            </div>
        </div>
        
        <div class="enroll-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <?php echo $success; ?>
            <?php else: ?>
            
            <div class="info-box">
                <i class="fas fa-info-circle" style="font-size: 2rem;"></i>
                <div>
                    <strong>ما هو الطالب الخاص؟</strong>
                    <p>برنامج مخصص للطلاب المتميزين الذين يحتاجون متابعة فردية. يمكنك اختيار التعليم الحضوري في الدار أو التعليم عن بُعد (أونلاين).</p>
                </div>
            </div>
            
            <form method="post" id="enrollForm">
                <input type="hidden" name="submit_enrollment" value="1">
                
                <!-- بيانات الطالب -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-user-graduate"></i><h3>بيانات الطالب</h3></div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> اسم الطالب كاملاً <span class="required">*</span></label>
                        <input type="text" name="student_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> الجنس <span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-option"><input type="radio" name="student_gender" value="male" required> ذكر</label>
                            <label class="radio-option"><input type="radio" name="student_gender" value="female" required> أنثى</label>
                        </div>
                    </div>
                    
                    <div class="age-group">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> تاريخ الميلاد</label>
                            <input type="date" name="birth_date" class="form-control" id="birthDate">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> أو أدخل العمر (بالسنوات)</label>
                            <input type="number" name="age_input" class="form-control" id="ageInput" min="3" max="100" placeholder="مثال: 12">
                        </div>
                    </div>
                    
                    <div id="studentPhoneGroup" style="display: none;">
                        <div class="form-group">
                            <label><i class="fas fa-mobile-alt"></i> رقم هاتف الطالب <span class="required">*</span></label>
                            <input type="tel" name="student_phone" class="form-control" id="studentPhone" placeholder="01012345678" dir="ltr">
                            <small>لأن عمر الطالب 18 سنة أو أكثر</small>
                        </div>
                    </div>
                </div>
                
                <!-- بيانات ولي الأمر -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-user-tie"></i><h3>بيانات ولي الأمر</h3></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> اسم ولي الأمر <span class="required">*</span></label>
                            <input type="text" name="parent_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> رقم هاتف ولي الأمر <span class="required">*</span></label>
                            <input type="tel" name="parent_phone" class="form-control" id="parentPhone" required placeholder="01012345678" dir="ltr">
                            <small>للطلاب أقل من 18 سنة</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                            <input type="email" name="parent_email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> العنوان</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                    </div>
                </div>
                
                <!-- اختيار نوع الحلقة -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-ring"></i><h3>نوع الحلقة المفضل</h3></div>
                    
                    <div class="type-selector">
                        <?php foreach ($types as $type): ?>
                            <div class="type-option" data-type="<?php echo $type['id']; ?>">
                                <i class="fas <?php echo $type['icon']; ?>"></i>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($type['name_ar']); ?></div>
                                <small><?php echo htmlspecialchars($type['description']); ?></small>
                                <input type="radio" name="preferred_ring_type_id" value="<?php echo $type['id']; ?>" style="display: none;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> الوقت المفضل للحضور</label>
                        <input type="time" name="preferred_time" class="form-control">
                    </div>
                </div>
                
                <!-- المحفوظات مع خيار اختيار النوع -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-quran"></i><h3>المحفوظات السابقة</h3></div>
                    
                    <!-- اختيار نوع المحفوظات -->
                    <div class="memorization-type-selector" id="memorizationTypeSelector">
                        <div class="mem-type-option selected" data-type="both">
                            <i class="fas fa-check-double"></i>
                            <div>السور والأجزاء</div>
                        </div>
                        <div class="mem-type-option" data-type="surahs">
                            <i class="fas fa-book-open"></i>
                            <div>سور فقط</div>
                        </div>
                        <div class="mem-type-option" data-type="parts">
                            <i class="fas fa-layer-group"></i>
                            <div>أجزاء فقط</div>
                        </div>
                    </div>
                    <input type="hidden" name="memorization_type" id="memorization_type" value="both">
                    
                    <!-- السور المحفوظة -->
                    <div id="surahsSection">
                        <div class="form-group">
                            <label><i class="fas fa-book-open"></i> السور المحفوظة</label>
                            <div class="surahs-grid">
                                <?php for ($i = 1; $i <= 114; $i++): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="memorized_surahs[]" value="<?php echo $i; ?>" id="surah_<?php echo $i; ?>">
                                    <label for="surah_<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahNameForSpecial($i); ?></label>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <small>يمكنك اختيار أكثر من سورة</small>
                        </div>
                    </div>
                    
                    <!-- الأجزاء المحفوظة -->
                    <div id="partsSection">
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> الأجزاء المحفوظة</label>
                            <div class="parts-grid">
                                <?php for ($i = 1; $i <= 30; $i++): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="memorized_parts[]" value="<?php echo $i; ?>" id="part_<?php echo $i; ?>">
                                    <label for="part_<?php echo $i; ?>">الجزء <?php echo $i; ?></label>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <small>يمكنك اختيار أكثر من جزء</small>
                        </div>
                    </div>
                    
                    <div class="memorization-summary" id="memorizationSummary">
                        <i class="fas fa-chart-line"></i>
                        تم اختيار <span id="selectedSurahsCount">0</span> سورة و <span id="selectedPartsCount">0</span> جزء
                    </div>
                </div>
                
                <!-- معلومات إضافية -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-info-circle"></i><h3>معلومات إضافية</h3></div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-level-up-alt"></i> المستوى السابق</label>
                        <input type="text" name="previous_quran_level" class="form-control" placeholder="مثال: حفظ 5 أجزاء">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-heart"></i> احتياجات خاصة (إن وجدت)</label>
                        <textarea name="special_needs" class="form-control" rows="2" placeholder="أي احتياجات خاصة للطالب..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> الدافع للتسجيل</label>
                        <textarea name="motivation" class="form-control" rows="2" placeholder="ما الذي يحفزك للتسجيل في البرنامج الخاص؟"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> تقديم الطلب
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// حساب العمر من تاريخ الميلاد
function calculateAgeFromBirth(birthDateStr) {
    if (!birthDateStr) return null;
    const birth = new Date(birthDateStr);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age;
}

function updateContactFields() {
    let age = null;
    const birthDate = document.getElementById('birthDate').value;
    const ageInput = document.getElementById('ageInput').value;
    const studentPhoneGroup = document.getElementById('studentPhoneGroup');
    const parentPhone = document.getElementById('parentPhone');
    
    if (birthDate) age = calculateAgeFromBirth(birthDate);
    else if (ageInput && ageInput > 0) age = parseInt(ageInput);
    
    if (age !== null && age >= 18) {
        studentPhoneGroup.style.display = 'block';
        document.getElementById('studentPhone').required = true;
        parentPhone.required = false;
    } else if (age !== null && age < 18) {
        studentPhoneGroup.style.display = 'none';
        document.getElementById('studentPhone').required = false;
        parentPhone.required = true;
    } else {
        studentPhoneGroup.style.display = 'none';
        parentPhone.required = true;
    }
}

// تحديث ملخص المحفوظات
function updateMemorizationSummary() {
    const memType = document.getElementById('memorization_type').value;
    let selectedSurahs = 0;
    let selectedParts = 0;
    
    if (memType === 'surahs' || memType === 'both') {
        selectedSurahs = document.querySelectorAll('input[name="memorized_surahs[]"]:checked').length;
    }
    if (memType === 'parts' || memType === 'both') {
        selectedParts = document.querySelectorAll('input[name="memorized_parts[]"]:checked').length;
    }
    
    document.getElementById('selectedSurahsCount').innerText = selectedSurahs;
    document.getElementById('selectedPartsCount').innerText = selectedParts;
}

// تغيير نوع المحفوظات
function changeMemorizationType(type) {
    document.getElementById('memorization_type').value = type;
    
    // تحديث التبويبات
    document.querySelectorAll('.mem-type-option').forEach(opt => {
        opt.classList.remove('selected');
        if (opt.dataset.type === type) {
            opt.classList.add('selected');
        }
    });
    
    // إظهار/إخفاء الأقسام
    const surahsSection = document.getElementById('surahsSection');
    const partsSection = document.getElementById('partsSection');
    
    if (type === 'surahs') {
        surahsSection.style.display = 'block';
        partsSection.style.display = 'none';
        // إلغاء تحديد الأجزاء
        document.querySelectorAll('input[name="memorized_parts[]"]').forEach(cb => cb.checked = false);
    } else if (type === 'parts') {
        surahsSection.style.display = 'none';
        partsSection.style.display = 'block';
        // إلغاء تحديد السور
        document.querySelectorAll('input[name="memorized_surahs[]"]').forEach(cb => cb.checked = false);
    } else {
        surahsSection.style.display = 'block';
        partsSection.style.display = 'block';
    }
    
    updateMemorizationSummary();
}

// اختيار نوع الحلقة
document.querySelectorAll('.type-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        const radio = this.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    });
});

// اختيار نوع المحفوظات
document.querySelectorAll('.mem-type-option').forEach(option => {
    option.addEventListener('click', function() {
        changeMemorizationType(this.dataset.type);
    });
});

// تحديث عند تغيير الاختيارات
document.querySelectorAll('input[name="memorized_surahs[]"], input[name="memorized_parts[]"]').forEach(input => {
    input.addEventListener('change', updateMemorizationSummary);
});

document.getElementById('birthDate').addEventListener('change', updateContactFields);
document.getElementById('ageInput').addEventListener('input', updateContactFields);

// تهيئة
updateContactFields();
updateMemorizationSummary();
changeMemorizationType('both');
</script>
</body>
</html>
              