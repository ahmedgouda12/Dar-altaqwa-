<?php
// ============================================
// ملف: special_teacher_requests.php - نسخة محسنة
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'الطلاب الخاصين - المعلم';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];

// جلب معلومات المعلم
$teacher = $pdo->prepare("SELECT name, phone, can_teach_special FROM teachers WHERE id = ?");
$teacher->execute([$teacher_id]);
$teacher_data = $teacher->fetch();

function safeHtml($text) {
    return $text === null ? '' : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ============================================
// 1. جلب الطلبات الموزعة على المعلم والتي لم يرد عليها بعد
//    (مع إصلاح الحالة إذا كانت خاطئة)
// ============================================

// أولاً: جلب الطلبات التي تم توزيعها على هذا المعلم (assigned_to_teacher_id = ?)
// ولكن قد تكون حالتها خطأ (مثل 'pending' أو NULL)
$pending_requests = $pdo->prepare("
    SELECT r.*, st.name_ar as type_name
    FROM special_enrollment_requests r
    LEFT JOIN special_student_types st ON r.preferred_ring_type_id = st.id
    WHERE r.assigned_to_teacher_id = ? 
    AND r.teacher_approved IS NULL
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$pending_requests->execute([$teacher_id]);
$pending_requests = $pending_requests->fetchAll();

// إصلاح الحالة للطلبات التي تم جلبها ولكن حالتها ليست 'assigned_to_teacher'
$fixed_count = 0;
foreach ($pending_requests as &$req) {
    if ($req['status'] !== 'assigned_to_teacher') {
        $fix = $pdo->prepare("UPDATE special_enrollment_requests SET status = 'assigned_to_teacher' WHERE id = ?");
        $fix->execute([$req['id']]);
        $fixed_count++;
        $req['status'] = 'assigned_to_teacher'; // تحديث في المصفوفة للعرض
    }
}
if ($fixed_count > 0) {
    // يمكن تسجيل ذلك في سجل الأخطاء (اختياري)
    error_log("تم إصلاح حالة $fixed_count طلب للمعلم $teacher_id");
}

// ============================================
// 2. جلب الطلاب الخاصين المقبولين
// ============================================
$my_special_students = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM student_surah_progress WHERE student_id = s.id AND completed = 1) as memorized_count
    FROM students s
    WHERE s.teacher_id = ? AND s.is_special = 1
    ORDER BY s.name
");
$my_special_students->execute([$teacher_id]);
$my_special_students = $my_special_students->fetchAll();

// ============================================
// 3. جلب الطلبات المرفوضة (للسجل فقط)
// ============================================
$rejected_requests = $pdo->prepare("
    SELECT r.*, st.name_ar as type_name
    FROM special_enrollment_requests r
    LEFT JOIN special_student_types st ON r.preferred_ring_type_id = st.id
    WHERE r.assigned_to_teacher_id = ? 
    AND r.teacher_approved = 0
    ORDER BY r.teacher_approved_at DESC
    LIMIT 10
");
$rejected_requests->execute([$teacher_id]);
$rejected_requests = $rejected_requests->fetchAll();

// ... باقي الكود (قبول الطالب، رفض الطالب، HTML) كما هو ...

// ============================================
// معالجة قبول الطالب
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_student'])) {
    $request_id = (int)$_POST['request_id'];
    $schedule_time = $_POST['schedule_time'] ?? null;
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    $send_whatsapp = isset($_POST['send_whatsapp']) ? true : false;
    
    try {
        $pdo->beginTransaction();
        
        // جلب معلومات الطلب
        $request = $pdo->prepare("
            SELECT * FROM special_enrollment_requests 
            WHERE id = ? AND assigned_to_teacher_id = ? AND teacher_approved IS NULL
        ");
        $request->execute([$request_id, $teacher_id]);
        $req = $request->fetch();
        
        if (!$req) {
            throw new Exception("الطلب غير موجود أو تمت معالجته مسبقاً");
        }
        
        // التحقق من عدم وجود الطالب مسبقاً
        $check_exists = $pdo->prepare("
            SELECT id FROM students 
            WHERE name = ? AND parent_phone = ?
        ");
        $check_exists->execute([$req['student_name'], $req['parent_phone']]);
        if ($check_exists->fetch()) {
            throw new Exception("الطالب موجود بالفعل في النظام");
        }
        
        // تحديد الفئة
        $category = $req['student_category'];
        if (empty($category)) {
            if ($req['student_age'] <= 10) {
                $category = 'child';
            } elseif ($req['student_gender'] == 'male' && $req['student_age'] >= 11) {
                $category = 'boy';
            } elseif ($req['student_gender'] == 'female' && $req['student_age'] >= 19) {
                $category = 'woman';
            } elseif ($req['student_gender'] == 'female' && $req['student_age'] >= 11) {
                $category = 'girl';
            } else {
                $category = 'child';
            }
        }
        
        // إنشاء حساب الطالب
        $username = 'student_' . time() . '_' . $req['id'];
        $plain_password = '123456';
        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
        
        // رقم التواصل
        $contact_phone = $req['parent_phone'];
        if ($req['student_age'] >= 18 && !empty($req['student_phone'])) {
            $contact_phone = $req['student_phone'];
        }
        
        // إدراج الطالب في جدول students
        $insert = $pdo->prepare("
            INSERT INTO students 
            (name, category, birth_date, teacher_id, parent_phone, level, 
             username, password, is_special, special_approved_by, special_approved_at,
             enrollment_request_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
        ");
        
        $insert->execute([
            $req['student_name'],
            $category,
            $req['birth_date'],
            $teacher_id,
            $contact_phone,
            $req['previous_quran_level'] ?: 'مبتدئ',
            $username,
            $hashed_password,
            $teacher_id,
            $req['id']
        ]);
        
        $student_id = $pdo->lastInsertId();
        
        // تحديث حالة الطلب إلى approved
        $update = $pdo->prepare("
            UPDATE special_enrollment_requests 
            SET status = 'approved', 
                teacher_approved = 1,
                teacher_approved_at = NOW(),
                student_id = ?
            WHERE id = ? AND assigned_to_teacher_id = ?
        ");
        $update->execute([$student_id, $request_id, $teacher_id]);
        
        // حذف الإشعارات
        $pdo->prepare("DELETE FROM special_teacher_notifications WHERE request_id = ? AND teacher_id = ?")
            ->execute([$request_id, $teacher_id]);
        
        $pdo->commit();
        
        // إرسال رسالة واتساب لولي الأمر
        if ($send_whatsapp && !empty($contact_phone)) {
            $type_name = $req['preferred_ring_type_id'] == 1 ? 'حضوري' : 'أونلاين';
            $formatted_time = $schedule_time ? date('h:i A', strtotime($schedule_time)) : 'سيتم تحديده لاحقاً';
            
            $message = "السلام عليكم ورحمة الله وبركاته\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📋 *تم قبول طلب الالتحاق الخاص*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "👤 *الطالب/ة:* {$req['student_name']}\n";
            $message .= "👨‍🏫 *المعلم:* {$teacher_data['name']}\n";
            $message .= "📱 *نوع الدراسة:* {$type_name}\n";
            $message .= "🕐 *الموعد:* {$formatted_time}\n";
            
            if ($meeting_link && $req['preferred_ring_type_id'] == 2) {
                $message .= "💻 *رابط الاجتماع:*\n";
                $message .= "   {$meeting_link}\n";
            }
            
            if ($additional_notes) {
                $message .= "\n📝 *ملاحظات:*\n";
                $message .= "   {$additional_notes}\n";
            }
            
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🔑 *بيانات الدخول:*\n";
            $message .= "┌─────────────────────────────────\n";
            $message .= "│ 👤 اسم المستخدم: {$username}\n";
            $message .= "│ 🔒 كلمة المرور: {$plain_password}\n";
            $message .= "└─────────────────────────────────\n\n";
            $message .= "🌐 رابط الدخول: " . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "دار التقوى لتحفيظ القرآن الكريم";
            
            $formatted_phone = formatWhatsAppNumber($contact_phone);
            if ($formatted_phone) {
                $_SESSION['whatsapp_url'] = "https://wa.me/{$formatted_phone}?text=" . urlencode($message);
            }
        }
        
        $_SESSION['success'] = "✅ تم قبول الطالب وترقيته إلى طالب خاص\n👤 اسم المستخدم: {$username}\n🔒 كلمة المرور: {$plain_password}";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
    }
    
    header("Location: special_teacher_requests.php");
    exit;
}

// ============================================
// معالجة رفض الطالب (يعاد إلى الإدارة)
// ============================================
if (isset($_GET['reject'])) {
    $request_id = (int)$_GET['reject'];           // <-- هنا التصحيح
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';
    
    try {
        // التحقق من وجود الطلب وأنه لا يزال موزعاً على هذا المعلم
        $check = $pdo->prepare("
            SELECT id FROM special_enrollment_requests 
            WHERE id = ? AND assigned_to_teacher_id = ? AND teacher_approved IS NULL
        ");
        $check->execute([$request_id, $teacher_id]);
        if (!$check->fetch()) {
            throw new Exception("الطلب غير موجود أو تمت معالجته مسبقاً");
        }
        
        // تحديث الطلب: إعادة تعيين المعلم، وضع علامة مرفوض، الحالة pending
        $update = $pdo->prepare("
            UPDATE special_enrollment_requests 
            SET 
                teacher_approved = 0,
                teacher_notes = ?,
                teacher_approved_at = NOW(),
                status = 'pending',
                assigned_to_teacher_id = NULL
            WHERE id = ? AND assigned_to_teacher_id = ?
        ");
        $update->execute([$reason, $request_id, $teacher_id]);
        
        // التأكد من أن التحديث تم
        if ($update->rowCount() == 0) {
            throw new Exception("فشل تحديث الطلب، ربما تم تعديله من قبل");
        }
        
        // حذف الإشعارات المرتبطة
        $pdo->prepare("DELETE FROM special_teacher_notifications WHERE request_id = ? AND teacher_id = ?")
            ->execute([$request_id, $teacher_id]);
        
        $_SESSION['success'] = "✅ تم رفض الطلب. سيتم إعادته للإدارة لإعادة التوزيع.";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ خطأ في رفض الطلب: " . $e->getMessage();
    }
    
    header("Location: special_teacher_requests.php");
    exit;
}

// تحديث الإشعارات
try {
    $pdo->prepare("UPDATE special_teacher_notifications SET is_read = 1 WHERE teacher_id = ? AND is_read = 0")
        ->execute([$teacher_id]);
} catch (PDOException $e) {
    // تجاهل
}

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['whatsapp_url']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلاب الخاصين - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* رأس الصفحة */
        .page-header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        .stats-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
            display: flex;
            gap: 20px;
        }
        
        /* الرسائل */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border-right: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-right: 4px solid #dc3545; }
        .alert-whatsapp { background: #e8f5e9; color: #155724; border-right: 4px solid #25d366; }
        
        /* أقسام الصفحة */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        
        /* بطاقات الطلاب المقبولين */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .student-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-right: 5px solid #f39c12;
            position: relative;
        }
        .special-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .student-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e3c3f;
            margin-bottom: 10px;
            margin-top: 10px;
        }
        .student-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            margin: 12px 0;
        }
        .info-row {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 100px; font-weight: 600; }
        .login-details {
            background: #fff3cd;
            padding: 10px;
            border-radius: 10px;
            margin: 10px 0;
            font-size: 0.85rem;
        }
        .login-details code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 5px;
            font-family: monospace;
        }
        
        /* بطاقات الطلبات الجديدة */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .request-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 5px solid #ffc107;
            position: relative;
        }
        .new-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ffc107;
            color: #212529;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .request-number {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 10px;
            margin-top: 10px;
        }
        .request-student-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e3c3f;
            margin-bottom: 15px;
        }
        .request-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            margin: 12px 0;
        }
        .memorization-badge {
            background: #c9a96b;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin: 2px;
        }
        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* أزرار */
        .btn {
            flex: 1;
            padding: 10px;
            border-radius: 30px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: 0.3s;
        }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-primary { background: #1e3c3f; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
        }
        .empty-state i { font-size: 4rem; color: #dee2e6; margin-bottom: 15px; }
        
        /* النوافذ المنبثقة */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 25px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #c9a96b;
        }
        .modal-header h3 { margin: 0; color: #1e3c3f; display: flex; align-items: center; gap: 10px; }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #999;
            transition: 0.3s;
        }
        .close-btn:hover { color: #dc3545; transform: rotate(90deg); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e3c3f; }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
        }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 12px;
        }
        .info-box {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .modal-actions { display: flex; gap: 15px; margin-top: 25px; }
        
        @media (max-width: 768px) {
            .students-grid, .requests-grid { grid-template-columns: 1fr; }
            .request-actions { flex-direction: column; }
            .modal-actions { flex-direction: column; }
            .page-header { flex-direction: column; text-align: center; }
            .stats-badge { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-crown"></i> الطلاب الخاصين</h1>
        <div class="stats-badge">
            <span><i class="fas fa-clock"></i> <?php echo count($pending_requests); ?> طلب جديد</span>
            <span><i class="fas fa-users"></i> <?php echo count($my_special_students); ?> طالب مقبول</span>
        </div>
    </div>
    
    <?php if ($whatsapp_url): ?>
        <div class="alert alert-whatsapp">
            <i class="fab fa-whatsapp" style="font-size: 1.5rem;"></i>
            <div style="flex:1;"><strong>✅ تم قبول الطالب!</strong> يمكنك إرسال الإشعار لولي الأمر</div>
            <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn" style="background: #25d366; color: white;">إرسال</a>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo nl2br($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <!-- ============================================ -->
    <!-- الطلاب الخاصين المقبولين -->
    <!-- ============================================ -->
    <?php if (!empty($my_special_students)): ?>
        <div class="section-title">
            <i class="fas fa-check-circle" style="color: #28a745;"></i>
            <h3>طلابي الخاصين (<?php echo count($my_special_students); ?>)</h3>
            <a href="students.php?special=special" style="background: #28a745; color: white; padding: 5px 15px; border-radius: 20px; text-decoration: none; font-size: 0.8rem;">عرض الكل</a>
        </div>
        
        <div class="students-grid">
            <?php foreach ($my_special_students as $student): ?>
                <div class="student-card">
                    <div class="special-badge"><i class="fas fa-crown"></i> طالب خاص</div>
                    <div class="student-name">
                        <i class="fas fa-user-graduate"></i> <?php echo safeHtml($student['name']); ?>
                    </div>
                    <div class="student-info">
                        <div class="info-row">
                            <div class="info-label">رقم ولي الأمر:</div>
                            <div dir="ltr"><?php echo safeHtml($student['parent_phone'] ?? 'لا يوجد'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">المستوى:</div>
                            <div><?php echo safeHtml($student['level'] ?? 'مبتدئ'); ?></div>
                        </div>
                    </div>
                    <div class="login-details">
                        <i class="fas fa-key"></i> <strong>بيانات الدخول:</strong><br>
                        <code><?php echo safeHtml($student['username']); ?></code> :اسم المستخدم<br>
                        <code>123456</code> :كلمة المرور
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- ============================================ -->
    <!-- طلبات جديدة بانتظار الرد -->
    <!-- ============================================ -->
    <?php if (!empty($pending_requests)): ?>
        <div class="section-title">
            <i class="fas fa-clock"></i>
            <h3>طلبات جديدة بانتظار ردك (<?php echo count($pending_requests); ?>)</h3>
        </div>
        
        <div class="requests-grid">
            <?php foreach ($pending_requests as $req): ?>
                <div class="request-card">
                    <div class="new-badge"><i class="fas fa-bell"></i> جديد</div>
                    <div class="request-number">
                        <i class="fas fa-qrcode"></i> <?php echo safeHtml($req['request_number']); ?>
                    </div>
                    <div class="request-student-name">
                        <?php echo safeHtml($req['student_name']); ?>
                    </div>
                    <div class="request-details">
                        <div class="info-row">
                            <div class="info-label">العمر:</div>
                            <div><?php echo safeHtml($req['student_age']); ?> سنة</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">ولي الأمر:</div>
                            <div><?php echo safeHtml($req['parent_name']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">الهاتف:</div>
                            <div dir="ltr"><?php echo safeHtml($req['parent_phone']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">المحفوظات:</div>
                            <div>
                                <?php if (($req['total_memorized_surahs'] ?? 0) > 0): ?>
                                    <span class="memorization-badge"><?php echo safeHtml($req['total_memorized_surahs']); ?> سورة</span>
                                <?php endif; ?>
                                <?php if (($req['total_memorized_parts'] ?? 0) > 0): ?>
                                    <span class="memorization-badge"><?php echo safeHtml($req['total_memorized_parts']); ?> جزء</span>
                                <?php endif; ?>
                                <?php if (($req['total_memorized_surahs'] ?? 0) == 0 && ($req['total_memorized_parts'] ?? 0) == 0): ?>
                                    <span class="memorization-badge">مبتدئ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="request-actions">
                        <button class="btn btn-success" onclick="openAcceptModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>', <?php echo $req['preferred_ring_type_id']; ?>)">
                            <i class="fas fa-check"></i> قبول
                        </button>
                        <button class="btn btn-danger" onclick="openRejectModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')">
                            <i class="fas fa-times"></i> رفض
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($pending_requests) && empty($my_special_students)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>لا توجد طلبات جديدة</h3>
            <p>سيتم إشعارك عند توزيع طلب جديد عليك</p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- نافذة قبول الطالب -->
<!-- ============================================ -->
<div id="acceptModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> قبول الطالب</h3>
            <button class="close-btn" onclick="closeAcceptModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="request_id" id="acceptRequestId">
            <div id="acceptStudentName" style="background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-clock"></i> الموعد المناسب</label>
                <input type="time" name="schedule_time" class="form-control">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-video"></i> رابط الاجتماع (للأونلاين)</label>
                <input type="text" name="meeting_link" class="form-control" placeholder="meet.google.com/...">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label>
                <textarea name="additional_notes" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="checkbox-label">
                <input type="checkbox" name="send_whatsapp" value="1" checked>
                <i class="fab fa-whatsapp"></i> إرسال إشعار واتساب لولي الأمر
            </div>
            
            <div class="info-box" style="background: #fff3cd; margin-top: 15px;">
                <i class="fas fa-info-circle"></i>
                <strong>معلومات الحساب:</strong><br>
                اسم المستخدم: <code>student_...</code><br>
                كلمة المرور: <code>123456</code><br>
                <strong>سيتم ترقية الطالب تلقائياً إلى طالب خاص</strong>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAcceptModal()">إلغاء</button>
                <button type="submit" name="accept_student" class="btn btn-primary">تأكيد القبول</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- نافذة رفض الطالب -->
<!-- ============================================ -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color: #dc3545;"></i> رفض الطالب</h3>
            <button class="close-btn" onclick="closeRejectModal()">&times;</button>
        </div>
        <div id="rejectStudentName" style="background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
        
        <div class="alert" style="background: #fff3cd; color: #856404; margin-bottom: 15px;">
            <i class="fas fa-exclamation-triangle"></i>
            سيتم إعادة هذا الطلب إلى الإدارة لإعادة توزيعه على معلم آخر.
        </div>
        
        <form method="get">
            <input type="hidden" name="reject" id="rejectRequestId">
            <div class="form-group">
                <label><i class="fas fa-comment"></i> سبب الرفض (اختياري)</label>
                <textarea name="reason" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">إلغاء</button>
                <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAcceptModal(id, name, type) {
    document.getElementById('acceptRequestId').value = id;
    document.getElementById('acceptStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + name;
    document.getElementById('acceptModal').classList.add('show');
}

function closeAcceptModal() {
    document.getElementById('acceptModal').classList.remove('show');
}

function openRejectModal(id, name) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('rejectStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + name;
    document.getElementById('rejectModal').classList.add('show');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeAcceptModal();
        closeRejectModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>