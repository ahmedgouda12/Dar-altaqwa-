<?php
// ============================================
// ملف: edit_enrollment.php - تعديل طلب الالتحاق
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'تعديل طلب الالتحاق - دار التقوى';
$error = '';
$success = '';
$request = null;
$is_admin = false;

// التحقق من الصلاحية
$token = isset($_GET['token']) ? $_GET['token'] : '';
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (isAdmin()) {
    $is_admin = true;
    $stmt = $pdo->prepare("SELECT * FROM enrollment_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
} elseif (!empty($token) && $request_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM enrollment_requests WHERE id = ? AND edit_token = ? AND status = 'pending'");
    $stmt->execute([$request_id, $token]);
    $request = $stmt->fetch();
} else {
    $error = "❌ غير مصرح بتعديل هذا الطلب";
}

function determineCategory($gender, $age) {
    if ($age >= 3 && $age <= 10) return 'child';
    if ($gender == 'female') {
        if ($age >= 11 && $age <= 18) return 'girl';
        if ($age >= 19) return 'woman';
    }
    if ($gender == 'male' && $age >= 11) return 'boy';
    return null;
}

function getSurahsFromParts($parts) {
    $part_to_surahs = [
        1 => [1, 2], 2 => [2, 3], 3 => [3, 4], 4 => [4, 5], 5 => [5, 6],
        6 => [6, 7], 7 => [7, 8], 8 => [8, 9], 9 => [9, 10], 10 => [10, 11],
        11 => [11, 12], 12 => [12, 13], 13 => [13, 14], 14 => [14, 15], 15 => [15, 16],
        16 => [16, 17], 17 => [17, 18], 18 => [18, 19], 19 => [19, 20], 20 => [20, 21],
        21 => [21, 22], 22 => [22, 23], 23 => [23, 24], 24 => [24, 25], 25 => [25, 26],
        26 => [26, 27], 27 => [27, 28], 28 => [28, 29], 29 => [29, 30], 30 => [30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114]
    ];
    
    $all_surahs = [];
    foreach ($parts as $part) {
        if (isset($part_to_surahs[$part])) {
            $all_surahs = array_merge($all_surahs, $part_to_surahs[$part]);
        }
    }
    return array_unique($all_surahs);
}

// معالجة تحديث الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request) {
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
    $previous_quran_level = trim($_POST['previous_quran_level'] ?? '');
    $preferred_time = trim($_POST['preferred_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $selection_type = $_POST['selection_type'] ?? 'both';
    $mem_surahs = isset($_POST['memorized_surahs']) ? array_map('intval', $_POST['memorized_surahs']) : [];
    $mem_parts = isset($_POST['memorized_parts']) ? array_map('intval', $_POST['memorized_parts']) : [];
    
    if ($selection_type == 'parts_only' && !empty($mem_parts)) {
        $mem_surahs = getSurahsFromParts($mem_parts);
    }
    if ($selection_type == 'surahs_only') {
        $mem_parts = [];
    }
    
    $has_memorized = isset($_POST['has_memorized']) ? ($_POST['has_memorized'] == 'yes') : (!empty($mem_surahs) || !empty($mem_parts));
    
    if (!$has_memorized) {
        $mem_surahs = [];
        $mem_parts = [];
    }
    
    $mem_surahs_json = json_encode($mem_surahs);
    $mem_parts_json = json_encode($mem_parts);
    $total_parts = count($mem_parts);
    $total_surahs = count($mem_surahs);
    
    $main_contact_phone = ($student_age >= 18 && !empty($student_phone)) ? $student_phone : $parent_phone;
    $category = determineCategory($student_gender, $student_age);
    
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
    } elseif ($student_age >= 18 && empty($student_phone)) {
        $error = '❌ رقم هاتف الطالب مطلوب';
    } elseif ($has_memorized && empty($mem_surahs) && empty($mem_parts)) {
        $error = '❌ يرجى اختيار السور أو الأجزاء المحفوظة على الأقل، أو اختر "لا يوجد محفوظات"';
    } elseif (!$category) {
        $error = '❌ العمر غير مناسب للتسجيل';
    } else {
        try {
            $pdo->beginTransaction();
            
            $update = $pdo->prepare("
                UPDATE enrollment_requests SET
                    student_name = ?,
                    student_gender = ?,
                    student_category = ?,
                    birth_date = ?,
                    student_age = ?,
                    parent_name = ?,
                    parent_phone = ?,
                    student_phone = ?,
                    contact_phone = ?,
                    parent_email = ?,
                    address = ?,
                    previous_quran_level = ?,
                    preferred_time = ?,
                    notes = ?,
                    memorized_surahs = ?,
                    memorized_parts = ?,
                    total_memorized_parts = ?,
                    total_memorized_surahs = ?,
                    name_soundex = SOUNDEX(?),
                    parent_name_soundex = SOUNDEX(?),
                    edit_count = edit_count + 1,
                    last_edit_at = NOW()
                WHERE id = ?
            ");
            
            $update->execute([
                $student_name,
                $student_gender,
                $category,
                $birth_date,
                $student_age,
                $parent_name,
                $parent_phone,
                $student_phone,
                $main_contact_phone,
                $parent_email,
                $address,
                $previous_quran_level,
                $preferred_time,
                $notes,
                $mem_surahs_json,
                $mem_parts_json,
                $total_parts,
                $total_surahs,
                $student_name,
                $parent_name,
                $request['id']
            ]);
            
            $log = $pdo->prepare("
                INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $log->execute([
                $request['id'],
                $request['status'],
                $request['status'],
                $is_admin ? $_SESSION['user_id'] : 0,
                $is_admin ? 'admin' : 'student',
                "تم تعديل الطلب" . ($is_admin ? " بواسطة الإدارة" : "") . 
                " | المحفوظات: {$total_parts} جزء, {$total_surahs} سورة"
            ]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "✅ تم تحديث الطلب بنجاح";
            header("Location: " . ($is_admin ? "enrollment_requests.php" : "my_requests.php"));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>تعديل طلب الالتحاق - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --success: #28a745;
            --success-light: #d4edda;
            --danger: #dc3545;
            --danger-light: #f8d7da;
            --warning: #ffc107;
            --warning-light: #fff3cd;
            --info: #17a2b8;
            --info-light: #d1ecf1;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --radius: 20px;
            --radius-sm: 12px;
            --radius-full: 50px;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --transition: 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .edit-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* بطاقة التعديل */
        .edit-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        /* رأس الصفحة */
        .edit-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .edit-header::before {
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
        
        .edit-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .edit-header h1 i {
            color: var(--secondary);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .edit-header p {
            position: relative;
            z-index: 2;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .edit-body {
            padding: 35px;
        }
        
        /* أقسام النموذج */
        .form-section {
            background: #f8f9fa;
            border-radius: var(--radius);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
        }
        
        .form-section:hover {
            box-shadow: var(--shadow-sm);
            border-color: var(--secondary);
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
        
        .section-title i {
            color: var(--secondary);
            font-size: 1.5rem;
        }
        
        .section-title h3 {
            font-size: 1.2rem;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.95rem;
        }
        
        .form-group label i {
            color: var(--secondary);
            margin-left: 5px;
        }
        
        .form-group label .required {
            color: var(--danger);
            margin-right: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            font-family: 'Cairo', sans-serif;
            background: white;
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
        
        /* خيارات الجنس */
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px 25px;
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-full);
            transition: var(--transition);
        }
        
        .radio-option:hover {
            border-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .radio-option.selected {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-color: var(--secondary);
            color: white;
        }
        
        /* قسم المحفوظات */
        .memorization-section {
            background: linear-gradient(135deg, #fff8e7, #fff3d6);
            border: 2px solid var(--secondary);
        }
        
        .memorization-toggle {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .toggle-option {
            flex: 1;
            text-align: center;
            padding: 12px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .toggle-option.selected {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-color: var(--secondary);
        }
        
        .selection-type {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .selection-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .selection-option.selected {
            border-color: var(--secondary);
            background: #fff8e7;
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
            border: 1px solid var(--gray-light);
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .checkbox-item:hover {
            background: #e9ecef;
            transform: translateX(-3px);
        }
        
        .checkbox-item input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            flex: 1;
            cursor: pointer;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .memorization-summary {
            background: var(--info-light);
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            color: #0c5460;
            font-weight: 600;
        }
        
        /* أزرار الإجراءات */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
        }
        
        .btn-cancel {
            background: #6c757d;
            margin-top: 0;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* رسائل التنبيه */
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error {
            background: var(--danger-light);
            color: #721c24;
            border-right: 5px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.3rem;
        }
        
        /* معلومات إضافية */
        .info-note {
            background: var(--warning-light);
            border-radius: var(--radius-sm);
            padding: 12px 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #856404;
            font-size: 0.85rem;
            border-right: 3px solid var(--warning);
        }
        
        @media (max-width: 768px) {
            .edit-body { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .surahs-grid, .parts-grid { grid-template-columns: repeat(2, 1fr); }
            .selection-type { flex-direction: column; }
            .action-buttons { flex-direction: column; }
            .edit-header h1 { font-size: 1.4rem; }
        }
        
        @media (max-width: 480px) {
            .surahs-grid, .parts-grid { grid-template-columns: 1fr; }
            .radio-option { width: 100%; justify-content: center; }
            .toggle-option { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="edit-container">
    <div class="edit-card">
        <div class="edit-header">
            <h1><i class="fas fa-edit"></i> تعديل طلب الالتحاق</h1>
            <p>رقم الطلب: <?php echo $request ? htmlspecialchars($request['request_number']) : 'غير متاح'; ?></p>
            <?php if ($request && $request['edit_count'] > 0): ?>
                <p style="font-size: 0.8rem; margin-top: 5px; opacity: 0.8;">
                    <i class="fas fa-history"></i> تم تعديل الطلب <?php echo $request['edit_count']; ?> مرة سابقاً
                </p>
            <?php endif; ?>
        </div>
        
        <div class="edit-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$request && !$error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> الطلب غير موجود أو تمت معالجته بالفعل
                </div>
                <div class="action-buttons">
                    <a href="enroll.php" class="btn-submit btn-cancel" style="text-align: center; text-decoration: none;">
                        <i class="fas fa-plus-circle"></i> تقديم طلب جديد
                    </a>
                </div>
            <?php elseif ($request): 
                $mem_surahs = json_decode($request['memorized_surahs'], true) ?: [];
                $mem_parts = json_decode($request['memorized_parts'], true) ?: [];
                $has_memorized = !empty($mem_surahs) || !empty($mem_parts);
                $selection_type_default = (!empty($mem_surahs) && !empty($mem_parts)) ? 'both' : (empty($mem_parts) ? 'surahs_only' : 'parts_only');
            ?>
                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <span>يمكنك تعديل بيانات الطلب خلال 3 أيام فقط من تاريخ التقديم. بعد ذلك سيتم توزيع الطلب ولا يمكن تعديله.</span>
                </div>
                
                <form method="post" id="editForm">
                    <!-- بيانات الطالب -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-graduate"></i>
                            <h3>بيانات الطالب</h3>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> اسم الطالب كاملاً <span class="required">*</span></label>
                            <input type="text" name="student_name" class="form-control" required value="<?php echo htmlspecialchars($request['student_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> الجنس <span class="required">*</span></label>
                            <div class="radio-group">
                                <label class="radio-option <?php echo $request['student_gender'] == 'male' ? 'selected' : ''; ?>">
                                    <input type="radio" name="student_gender" value="male" <?php echo $request['student_gender'] == 'male' ? 'checked' : ''; ?>> ذكر
                                </label>
                                <label class="radio-option <?php echo $request['student_gender'] == 'female' ? 'selected' : ''; ?>">
                                    <input type="radio" name="student_gender" value="female" <?php echo $request['student_gender'] == 'female' ? 'checked' : ''; ?>> أنثى
                                </label>
                            </div>
                        </div>
                        
                        <div class="age-group">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> تاريخ الميلاد</label>
                                <input type="date" name="birth_date" class="form-control" id="birthDate" value="<?php echo $request['birth_date']; ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> أو أدخل العمر (بالسنوات)</label>
                                <input type="number" name="age_input" class="form-control" id="ageInput" min="3" max="100" placeholder="مثال: 12" value="<?php echo $request['student_age']; ?>">
                            </div>
                        </div>
                        
                        <div id="studentPhoneGroup" style="<?php echo ($request['student_age'] >= 18) ? 'display:block' : 'display:none'; ?>">
                            <div class="form-group">
                                <label><i class="fas fa-mobile-alt"></i> رقم هاتف الطالب <span class="required">*</span></label>
                                <input type="tel" name="student_phone" class="form-control" id="studentPhone" value="<?php echo htmlspecialchars($request['student_phone']); ?>" placeholder="01012345678" dir="ltr">
                                <small>لأن عمر الطالب 18 سنة أو أكثر</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- بيانات ولي الأمر -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-tie"></i>
                            <h3>بيانات ولي الأمر</h3>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> اسم ولي الأمر <span class="required">*</span></label>
                                <input type="text" name="parent_name" class="form-control" required value="<?php echo htmlspecialchars($request['parent_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> رقم هاتف ولي الأمر <span class="required">*</span></label>
                                <input type="tel" name="parent_phone" class="form-control" id="parentPhone" required value="<?php echo htmlspecialchars($request['parent_phone']); ?>" placeholder="01012345678" dir="ltr">
                                <small>للطلاب أقل من 18 سنة</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                                <input type="email" name="parent_email" class="form-control" value="<?php echo htmlspecialchars($request['parent_email']); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> العنوان</label>
                                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($request['address']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- المحفوظات -->
                    <div class="form-section memorization-section">
                        <div class="section-title">
                            <i class="fas fa-quran"></i>
                            <h3>المحفوظات <span class="required">*</span></h3>
                        </div>
                        
                        <div class="memorization-toggle" id="memorizationToggle">
                            <div class="toggle-option <?php echo $has_memorized ? 'selected' : ''; ?>" data-value="yes">يوجد محفوظات</div>
                            <div class="toggle-option <?php echo !$has_memorized ? 'selected' : ''; ?>" data-value="no">لا يوجد محفوظات</div>
                        </div>
                        <input type="hidden" name="has_memorized" id="hasMemorized" value="<?php echo $has_memorized ? 'yes' : 'no'; ?>">
                        
                        <div id="memorizationDetails" style="display: <?php echo $has_memorized ? 'block' : 'none'; ?>">
                            <div class="selection-type" id="selectionType">
                                <div class="selection-option <?php echo $selection_type_default == 'surahs_only' ? 'selected' : ''; ?>" data-type="surahs_only">سور فقط</div>
                                <div class="selection-option <?php echo $selection_type_default == 'parts_only' ? 'selected' : ''; ?>" data-type="parts_only">أجزاء فقط</div>
                                <div class="selection-option <?php echo $selection_type_default == 'both' ? 'selected' : ''; ?>" data-type="both">السور والأجزاء</div>
                            </div>
                            <input type="hidden" name="selection_type" id="selectionTypeValue" value="<?php echo $selection_type_default; ?>">
                            
                            <div class="form-group">
                                <label><i class="fas fa-book-open"></i> السور المحفوظة</label>
                                <div class="surahs-grid" id="surahsGrid">
                                    <?php for ($i = 1; $i <= 114; $i++): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="memorized_surahs[]" value="<?php echo $i; ?>" id="surah_<?php echo $i; ?>" <?php echo in_array($i, $mem_surahs) ? 'checked' : ''; ?>>
                                        <label for="surah_<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></label>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <small>يمكنك اختيار سورة واحدة أو أكثر (اختياري)</small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-layer-group"></i> الأجزاء المحفوظة</label>
                                <div class="parts-grid" id="partsGrid">
                                    <?php for ($i = 1; $i <= 30; $i++): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="memorized_parts[]" value="<?php echo $i; ?>" id="part_<?php echo $i; ?>" <?php echo in_array($i, $mem_parts) ? 'checked' : ''; ?>>
                                        <label for="part_<?php echo $i; ?>">الجزء <?php echo $i; ?></label>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <small>يمكنك اختيار جزء واحد أو أكثر (اختياري)</small>
                            </div>
                        </div>
                        
                        <div id="noMemorizationMessage" style="display: <?php echo $has_memorized ? 'none' : 'block'; ?>; text-align: center; padding: 20px;">
                            <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--secondary);"></i>
                            <p style="margin-top: 10px;">سيتم اعتبار الطالب مبتدئاً دون أي محفوظات سابقة.</p>
                        </div>
                        
                        <div class="memorization-summary" id="memorizationSummary">
                            <i class="fas fa-chart-line"></i>
                            تم اختيار <span id="selectedSurahsCount"><?php echo count($mem_surahs); ?></span> سورة و <span id="selectedPartsCount"><?php echo count($mem_parts); ?></span> جزء
                        </div>
                    </div>
                    
                    <!-- معلومات إضافية -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-info-circle"></i>
                            <h3>معلومات إضافية</h3>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-level-up-alt"></i> المستوى السابق</label>
                            <input type="text" name="previous_quran_level" class="form-control" value="<?php echo htmlspecialchars($request['previous_quran_level']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> الوقت المفضل للحضور</label>
                            <input type="time" name="preferred_time" class="form-control" value="<?php echo $request['preferred_time']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label>
                            <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($request['notes']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <a href="<?php echo $is_admin ? 'enrollment_requests.php' : 'my_requests.php'; ?>" class="btn-submit btn-cancel" style="text-align: center; text-decoration: none;">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
    } else {
        studentPhoneGroup.style.display = 'none';
        document.getElementById('studentPhone').required = false;
        parentPhone.required = true;
    }
}

function updateMemorizationSummary() {
    const selectedSurahs = document.querySelectorAll('input[name="memorized_surahs[]"]:checked').length;
    const selectedParts = document.querySelectorAll('input[name="memorized_parts[]"]:checked').length;
    document.getElementById('selectedSurahsCount').textContent = selectedSurahs;
    document.getElementById('selectedPartsCount').textContent = selectedParts;
}

function toggleMemorizationMode(value) {
    const detailsDiv = document.getElementById('memorizationDetails');
    const noMemDiv = document.getElementById('noMemorizationMessage');
    const hasMemorized = document.getElementById('hasMemorized');
    
    if (value === 'yes') {
        detailsDiv.style.display = 'block';
        noMemDiv.style.display = 'none';
        hasMemorized.value = 'yes';
    } else {
        detailsDiv.style.display = 'none';
        noMemDiv.style.display = 'block';
        hasMemorized.value = 'no';
        document.querySelectorAll('input[name="memorized_surahs[]"], input[name="memorized_parts[]"]').forEach(input => {
            input.checked = false;
        });
        updateMemorizationSummary();
    }
}

function toggleSelectionType(type) {
    const surahsGrid = document.getElementById('surahsGrid');
    const partsGrid = document.getElementById('partsGrid');
    const selectionTypeValue = document.getElementById('selectionTypeValue');
    
    selectionTypeValue.value = type;
    
    if (type === 'surahs_only') {
        surahsGrid.style.display = 'grid';
        partsGrid.style.display = 'none';
    } else if (type === 'parts_only') {
        surahsGrid.style.display = 'none';
        partsGrid.style.display = 'grid';
    } else {
        surahsGrid.style.display = 'grid';
        partsGrid.style.display = 'grid';
    }
    
    updateMemorizationSummary();
}

document.getElementById('birthDate').addEventListener('change', updateContactFields);
document.getElementById('ageInput').addEventListener('input', updateContactFields);
document.querySelectorAll('input[name="memorized_surahs[]"], input[name="memorized_parts[]"]').forEach(input => {
    input.addEventListener('change', updateMemorizationSummary);
});

document.querySelectorAll('.toggle-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.toggle-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        toggleMemorizationMode(this.dataset.value);
    });
});

document.querySelectorAll('.selection-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.selection-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        toggleSelectionType(this.dataset.type);
    });
});

updateContactFields();
updateMemorizationSummary();
toggleMemorizationMode('<?php echo $has_memorized ? 'yes' : 'no'; ?>');
toggleSelectionType('<?php echo $selection_type_default; ?>');

document.getElementById('editForm').addEventListener('submit', function(e) {
    const hasMemorized = document.getElementById('hasMemorized').value;
    const selectionType = document.getElementById('selectionTypeValue').value;
    
    if (hasMemorized === 'yes') {
        let selectedSurahs = 0, selectedParts = 0;
        
        if (selectionType !== 'parts_only') {
            selectedSurahs = document.querySelectorAll('input[name="memorized_surahs[]"]:checked').length;
        }
        if (selectionType !== 'surahs_only') {
            selectedParts = document.querySelectorAll('input[name="memorized_parts[]"]:checked').length;
        }
        
        if (selectedSurahs === 0 && selectedParts === 0) {
            e.preventDefault();
            alert('❌ يرجى اختيار سورة واحدة على الأقل أو جزء واحد على الأقل، أو اختر "لا يوجد محفوظات"');
            return false;
        }
    }
});
</script>
</body>
</html>