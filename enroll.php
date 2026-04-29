<?php
// ============================================
// ملف: enroll.php - تقديم طالب جديد (محدث)
// ============================================

require_once 'config.php';
require_once 'check_duplicate.php';

$pageTitle = 'تقديم طالب جديد - دار التقوى';
$error = '';
$success = '';
$warning = '';
$duplicate_warning = '';
$similar_students = [];

// دوال مساعدة
function determineCategory($gender, $age) {
    if ($age >= 3 && $age <= 10) return 'child';
    if ($gender == 'female') {
        if ($age >= 11 && $age <= 18) return 'girl';
        if ($age >= 19) return 'woman';
    }
    if ($gender == 'male' && $age >= 11) return 'boy';
    return null;
}

function generateRequestNumber() {
    return 'REQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
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

function checkExistingRequest($pdo, $name, $phone) {
    $stmt = $pdo->prepare("
        SELECT id, status, created_at, request_number 
        FROM enrollment_requests 
        WHERE student_name = ? AND parent_phone = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$name, $phone]);
    return $stmt->fetch();
}

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_enrollment'])) {
    $student_name = trim($_POST['student_name'] ?? '');
    $student_gender = $_POST['student_gender'] ?? '';
    $birth_date = $_POST['birth_date'] ?? null;
    $student_age = null;
    $age_input = $_POST['age_input'] ?? '';
    $student_phone = trim($_POST['student_phone'] ?? '');
    
    // حساب العمر
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
    } elseif (!preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $main_contact_phone))) {
        $error = '❌ رقم الهاتف غير صالح';
    } elseif ($student_age >= 18 && empty($student_phone)) {
        $error = '❌ رقم هاتف الطالب مطلوب (لأن عمره 18 سنة أو أكثر)';
    } elseif ($has_memorized && empty($mem_surahs) && empty($mem_parts)) {
        $error = '❌ يرجى اختيار السور أو الأجزاء المحفوظة على الأقل، أو اختر "لا يوجد محفوظات"';
    } else {
        $existingRequest = checkExistingRequest($pdo, $student_name, $parent_phone);
        
        if ($existingRequest && $existingRequest['status'] != 'rejected') {
            $statusText = [
                'pending' => 'قيد الانتظار',
                'approved_by_teacher' => 'موافقة معلم',
                'assigned' => 'تم التوزيع'
            ];
            $status = $statusText[$existingRequest['status']] ?? $existingRequest['status'];
            $error = "⚠️ يوجد طلب سابق بنفس الاسم ورقم الهاتف!<br>";
            $error .= "رقم الطلب: {$existingRequest['request_number']}<br>";
            $error .= "الحالة: {$status}<br>";
            $error .= "يرجى انتظار الرد على طلبك السابق أو <a href='my_requests.php'>عرض طلباتي</a>";
        } else {
            $category = determineCategory($student_gender, $student_age);
            if (!$category) {
                $error = '❌ العمر غير مناسب للتسجيل في الدار';
            } else {
                $result = proceedWithEnrollment($pdo, [
                    'student_name' => $student_name,
                    'student_gender' => $student_gender,
                    'student_age' => $student_age,
                    'birth_date' => $birth_date,
                    'student_phone' => $student_phone,
                    'category' => $category,
                    'parent_name' => $parent_name,
                    'parent_phone' => $parent_phone,
                    'main_contact_phone' => $main_contact_phone,
                    'parent_email' => $parent_email,
                    'address' => $address,
                    'previous_quran_level' => $previous_quran_level,
                    'preferred_time' => $preferred_time,
                    'notes' => $notes,
                    'mem_surahs_json' => $mem_surahs_json,
                    'mem_parts_json' => $mem_parts_json,
                    'total_parts' => $total_parts,
                    'total_surahs' => $total_surahs
                ], $pdo);
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
}

function proceedWithEnrollment($pdo, $data) {
    try {
        $request_number = generateRequestNumber();
        $edit_token = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("
            INSERT INTO enrollment_requests 
            (request_number, student_name, student_gender, student_category, birth_date, student_age, 
             parent_name, parent_phone, student_phone, contact_phone, parent_email, address, 
             previous_quran_level, preferred_time, notes, status, edit_token,
             memorized_surahs, memorized_parts, total_memorized_parts, total_memorized_surahs,
             name_soundex, parent_name_soundex)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $request_number, 
            $data['student_name'], 
            $data['student_gender'], 
            $data['category'], 
            $data['birth_date'], 
            $data['student_age'],
            $data['parent_name'], 
            $data['parent_phone'],
            $data['student_phone'] ?? null,
            $data['main_contact_phone'],
            $data['parent_email'], 
            $data['address'], 
            $data['previous_quran_level'], 
            $data['preferred_time'], 
            $data['notes'],
            $edit_token,
            $data['mem_surahs_json'],
            $data['mem_parts_json'],
            $data['total_parts'],
            $data['total_surahs'],
            soundex($data['student_name']),
            soundex($data['parent_name'])
        ]);
        
        $request_id = $pdo->lastInsertId();
        
        // تسجيل في سجل التغييرات
        $log = $pdo->prepare("INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes) 
                              VALUES (?, NULL, 'pending', ?, 'system', ?)");
        $log->execute([$request_id, 0, "تم إنشاء طلب جديد - رقم الطلب: {$request_number}"]);
        
        // إشعار للمعلمين
        $teacherQuery = $pdo->prepare("
            SELECT id FROM teachers 
            WHERE can_login = 1 
            AND ((gender = 'male' AND ? = 'boy') OR (gender = 'female' AND ? IN ('girl', 'child', 'woman')))
        ");
        $teacherQuery->execute([$data['category'], $data['category']]);
        $teachers = $teacherQuery->fetchAll();
        
        foreach ($teachers as $teacher) {
            $notify = $pdo->prepare("INSERT INTO enrollment_notifications (teacher_id, request_id) VALUES (?, ?)");
            $notify->execute([$teacher['id'], $request_id]);
        }
        
        $edit_link = "edit_enrollment.php?id={$request_id}&token={$edit_token}";
        
        $message = "✅ تم تقديم طلب الالتحاق بنجاح.<br>";
        $message .= "📋 رقم الطلب: <strong>{$request_number}</strong><br>";
        $message .= "📖 عدد الأجزاء المحفوظة: <strong>{$data['total_parts']}</strong> جزء<br>";
        $message .= "📚 عدد السور المحفوظة: <strong>{$data['total_surahs']}</strong> سورة<br>";
        $message .= "⏳ سيتم التواصل معكم خلال 48 ساعة.<br><br>";
        $message .= "<div style='background: #e8f5e9; padding: 15px; border-radius: 12px; margin: 15px 0; border-right: 4px solid #28a745;'>";
        $message .= "<i class='fas fa-edit' style='color: #28a745;'></i> ";
        $message .= "<strong>🔗 رابط تعديل الطلب:</strong><br>";
        $message .= "<a href='{$edit_link}' target='_blank' style='color: #28a745; text-decoration: underline; word-break: break-all;'>";
        $message .= "اضغط هنا لتعديل الطلب</a>";
        $message .= "<br><small>⚠️ يمكنك استخدام هذا الرابط لتعديل بيانات الطلب خلال 3 أيام فقط</small>";
        $message .= "</div>";
        
        return ['success' => true, 'message' => $message];
        
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            return ['success' => false, 'error' => "⚠️ يوجد طلب سابق بنفس الاسم ورقم الهاتف. يرجى الانتظار حتى يتم الرد."];
        } else {
            return ['success' => false, 'error' => "❌ حدث خطأ: " . $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>دار التقوى - تقديم طالب جديد</title>
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
            --gray: #6c757d;
            --gray-light: #e9ecef;
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
        
        .enroll-body { padding: 35px; }
        
        .top-actions {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .btn-view-requests {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-view-requests:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
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
            border: 2px solid var(--gray-light);
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
            border: 2px solid var(--gray-light);
            border-radius: 50px;
            transition: 0.3s;
        }
        
        .radio-option:hover {
            border-color: var(--secondary);
            background: #fff;
        }
        
        .age-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
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
            border-radius: 50px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 600;
        }
        
        .toggle-option.selected {
            background: var(--primary);
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
            transition: 0.3s;
        }
        
        .selection-option.selected {
            border-color: var(--secondary);
            background: #fff8e7;
        }
        
        .auto-select-box {
            background: #e8f5e9;
            padding: 10px 15px;
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        }
        
        .checkbox-item:hover { background: #e9ecef; }
        .checkbox-item input { width: 18px; height: 18px; cursor: pointer; }
        .checkbox-item label { flex: 1; cursor: pointer; margin: 0; font-size: 0.9rem; }
        
        .memorization-summary {
            background: #d1ecf1;
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            color: #0c5460;
            font-weight: 600;
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
        
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid var(--success); }
        .alert-error { background: #f8d7da; color: #721c24; border-right: 5px solid var(--danger); }
        .alert-warning { background: #fff3cd; color: #856404; border-right: 5px solid var(--warning); }
        
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
            .form-row, .age-group { grid-template-columns: 1fr; }
            .surahs-grid, .parts-grid { grid-template-columns: repeat(2, 1fr); }
            .selection-type { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="enroll-container">
    <div class="enroll-card">
        <div class="enroll-header">
            <h1><i class="fas fa-user-plus"></i> تقديم طالب جديد</h1>
            <p>دار التقوى لتحفيظ القرآن الكريم - منيا القمح - الشرقية</p>
        </div>
        
        <div class="enroll-body">
            <div class="top-actions">
                <a href="my_requests.php" class="btn-view-requests">
                    <i class="fas fa-list-alt"></i> عرض طلباتي السابقة
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php else: ?>
            
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
                            <small>لأن عمر الطالب 18 سنة أو أكثر، سيتم استخدام هذا الرقم للتواصل</small>
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
                
                <!-- المحفوظات -->
                <div class="form-section memorization-section">
                    <div class="section-title"><i class="fas fa-quran"></i><h3>المحفوظات <span class="required">*</span></h3></div>
                    
                    <div class="memorization-toggle" id="memorizationToggle">
                        <div class="toggle-option selected" data-value="yes">يوجد محفوظات</div>
                        <div class="toggle-option" data-value="no">لا يوجد محفوظات</div>
                    </div>
                    <input type="hidden" name="has_memorized" id="hasMemorized" value="yes">
                    
                    <div id="memorizationDetails">
                        <div class="selection-type" id="selectionType">
                            <div class="selection-option selected" data-type="both">
                                <i class="fas fa-check-double"></i> السور والأجزاء
                            </div>
                            <div class="selection-option" data-type="surahs_only">
                                <i class="fas fa-book-open"></i> سور فقط
                            </div>
                            <div class="selection-option" data-type="parts_only">
                                <i class="fas fa-layer-group"></i> أجزاء فقط
                            </div>
                        </div>
                        <input type="hidden" name="selection_type" id="selectionTypeValue" value="both">
                        
                        <div class="auto-select-box" id="autoSelectBox" style="display: none;">
                            <input type="checkbox" name="auto_select_surahs" id="autoSelectSurahs" value="1">
                            <label for="autoSelectSurahs">
                                <i class="fas fa-magic"></i> تحديد السور تلقائياً من الأجزاء المختارة
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-book-open"></i> السور المحفوظة</label>
                            <div class="surahs-grid" id="surahsGrid">
                                <?php for ($i = 1; $i <= 114; $i++): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="memorized_surahs[]" value="<?php echo $i; ?>" id="surah_<?php echo $i; ?>">
                                    <label for="surah_<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></label>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <small>يمكنك اختيار أكثر من سورة (يمكن اختيار سورة واحدة فقط)</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> الأجزاء المحفوظة</label>
                            <div class="parts-grid" id="partsGrid">
                                <?php for ($i = 1; $i <= 30; $i++): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="memorized_parts[]" value="<?php echo $i; ?>" id="part_<?php echo $i; ?>">
                                    <label for="part_<?php echo $i; ?>">الجزء <?php echo $i; ?></label>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <small>يمكنك اختيار أكثر من جزء (يمكن اختيار جزء واحد فقط)</small>
                        </div>
                    </div>
                    
                    <div id="noMemorizationMessage" style="display: none; text-align: center; padding: 30px;">
                        <i class="fas fa-info-circle" style="font-size: 3rem; color: var(--secondary);"></i>
                        <p style="margin-top: 15px;">سيتم اعتبار الطالب مبتدئاً دون أي محفوظات سابقة.</p>
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
                        <input type="text" name="previous_quran_level" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> الوقت المفضل للحضور</label>
                        <input type="time" name="preferred_time" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> تقديم الطلب
                </button>
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
    } else if (age !== null && age < 18) {
        studentPhoneGroup.style.display = 'none';
        document.getElementById('studentPhone').required = false;
        parentPhone.required = true;
    } else {
        studentPhoneGroup.style.display = 'none';
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
        // لا نجعل الحقول مطلوبة، يمكن اختيار سورة واحدة فقط
        document.querySelectorAll('input[name="memorized_surahs[]"], input[name="memorized_parts[]"]').forEach(input => {
            input.required = false;
        });
    } else {
        detailsDiv.style.display = 'none';
        noMemDiv.style.display = 'block';
        hasMemorized.value = 'no';
        document.querySelectorAll('input[name="memorized_surahs[]"], input[name="memorized_parts[]"]').forEach(input => {
            input.required = false;
            input.checked = false;
        });
        updateMemorizationSummary();
    }
}

function toggleSelectionType(type) {
    const surahsGrid = document.getElementById('surahsGrid');
    const partsGrid = document.getElementById('partsGrid');
    const autoSelectBox = document.getElementById('autoSelectBox');
    const selectionTypeValue = document.getElementById('selectionTypeValue');
    
    selectionTypeValue.value = type;
    
    if (type === 'surahs_only') {
        surahsGrid.style.display = 'grid';
        partsGrid.style.display = 'none';
        autoSelectBox.style.display = 'none';
        document.querySelectorAll('input[name="memorized_parts[]"]').forEach(input => {
            input.checked = false;
        });
    } else if (type === 'parts_only') {
        surahsGrid.style.display = 'none';
        partsGrid.style.display = 'grid';
        autoSelectBox.style.display = 'block';
        document.querySelectorAll('input[name="memorized_surahs[]"]').forEach(input => {
            input.checked = false;
        });
    } else {
        surahsGrid.style.display = 'grid';
        partsGrid.style.display = 'grid';
        autoSelectBox.style.display = 'none';
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
toggleMemorizationMode('yes');
toggleSelectionType('both');

// التحقق من صحة النموذج
document.getElementById('enrollForm').addEventListener('submit', function(e) {
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
        
        // لا نتحقق من عدد السور، يمكن اختيار سورة واحدة فقط
        // نتحقق فقط من وجود اختيار واحد على الأقل
        if (selectedSurahs === 0 && selectedParts === 0) {
            e.preventDefault();
            alert('❌ يرجى اختيار سورة واحدة على الأقل أو جزء واحد على الأقل، أو اختر "لا يوجد محفوظات"');
            return false;
        }
    }
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التقديم...';
});
</script>
</body>
</html>