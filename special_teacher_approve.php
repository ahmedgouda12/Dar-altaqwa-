<?php
// ============================================
// ملف: special_teacher_approve.php
// صفحة للمعلم للموافقة على الطالب الخاص أو رفضه عبر الرابط
// آخر تحديث: 2026-03-31
// ============================================

require_once 'config.php';
require_once 'functions.php';

// دالة آمنة لعرض النصوص
function safeHtml($text) {
    if ($text === null) {
        return '';
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// التحقق من وجود الطلب
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$request_id || !in_array($action, ['approve', 'reject'])) {
    die('طلب غير صالح');
}

// جلب معلومات الطلب
$stmt = $pdo->prepare("
    SELECT r.*, t.name as teacher_name, t.phone as teacher_phone, t.id as teacher_id
    FROM special_enrollment_requests r
    LEFT JOIN teachers t ON r.assigned_to_teacher_id = t.id
    WHERE r.id = ? AND r.status = 'assigned_to_teacher'
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    die('الطلب غير موجود أو تمت معالجته مسبقاً');
}

$teacher_id = $request['assigned_to_teacher_id'];
$student_name = $request['student_name'];
$teacher_name = $request['teacher_name'];

// ============================================
// معالجة الموافقة
// ============================================
if ($action == 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_time = $_POST['schedule_time'] ?? null;
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        // تحديد الفئة
        $category = $request['student_category'];
        if (empty($category)) {
            if ($request['student_age'] <= 10) {
                $category = 'child';
            } elseif ($request['student_gender'] == 'male' && $request['student_age'] >= 11) {
                $category = 'boy';
            } elseif ($request['student_gender'] == 'female' && $request['student_age'] >= 19) {
                $category = 'woman';
            } elseif ($request['student_gender'] == 'female' && $request['student_age'] >= 11) {
                $category = 'girl';
            } else {
                $category = 'child';
            }
        }
        
        // إنشاء اسم مستخدم
        $username = 'student_' . time() . '_' . $request['id'];
        $plain_password = '123456';
        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
        
        // رقم التواصل
        $contact_phone = $request['parent_phone'];
        if ($request['student_age'] >= 18 && !empty($request['student_phone'])) {
            $contact_phone = $request['student_phone'];
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
            $request['student_name'],
            $category,
            $request['birth_date'],
            $teacher_id,
            $contact_phone,
            $request['previous_quran_level'] ?: 'مبتدئ',
            $username,
            $hashed_password,
            $teacher_id,
            $request['id']
        ]);
        
        $student_id = $pdo->lastInsertId();
        
        // تحديث حالة الطلب
        $update = $pdo->prepare("
            UPDATE special_enrollment_requests 
            SET status = 'approved', 
                teacher_approved = 1,
                teacher_approved_at = NOW(),
                student_id = ?
            WHERE id = ?
        ");
        $update->execute([$student_id, $request_id]);
        
        // حذف الإشعارات
        $pdo->prepare("DELETE FROM special_teacher_notifications WHERE request_id = ?")->execute([$request_id]);
        
        $pdo->commit();
        
        // إرسال رسالة تأكيد لولي الأمر عبر واتساب
        $whatsapp_url = '';
        if (!empty($contact_phone)) {
            $phone = formatWhatsAppNumber($contact_phone);
            if ($phone) {
                $type_name = $request['preferred_ring_type_id'] == 1 ? 'حضوري' : 'أونلاين';
                $formatted_time = $schedule_time ? date('h:i A', strtotime($schedule_time)) : 'سيتم تحديده لاحقاً';
                
                $message = "السلام عليكم ورحمة الله وبركاته\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "📋 *تم قبول طلب الالتحاق الخاص*\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "👤 *الطالب/ة:* {$request['student_name']}\n";
                $message .= "👨‍🏫 *المعلم:* {$teacher_name}\n";
                $message .= "📱 *نوع الدراسة:* {$type_name}\n";
                $message .= "🕐 *الموعد:* {$formatted_time}\n";
                
                if ($meeting_link && $request['preferred_ring_type_id'] == 2) {
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
                
                $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);
            }
        }
        
        $success_message = "✅ تم قبول الطالب بنجاح!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "❌ خطأ: " . $e->getMessage();
    }
}

// ============================================
// معالجة الرفض
// ============================================
if ($action == 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    
    try {
        // تحديث حالة الطلب
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
        
        // حذف الإشعارات
        $pdo->prepare("DELETE FROM special_teacher_notifications WHERE request_id = ?")->execute([$request_id]);
        
        $success_message = "✅ تم رفض الطلب. سيتم إعادته للإدارة لإعادة التوزيع.";
        
    } catch (Exception $e) {
        $error_message = "❌ خطأ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action == 'approve' ? 'قبول' : 'رفض'; ?> الطالب الخاص - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 550px;
            width: 100%;
            background: white;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header p {
            margin-top: 8px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .student-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        .student-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e3c3f;
            margin-top: 10px;
        }
        .info-details {
            margin-top: 10px;
            color: #666;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e3c3f;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #c9a96b;
            box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 40px;
            border: none;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-right: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 4px solid #dc3545;
        }
        .info-box {
            background: #e7f3ff;
            border-radius: 12px;
            padding: 12px;
            margin: 15px 0;
            font-size: 0.9rem;
            color: #0c5460;
        }
        .whatsapp-link {
            display: inline-block;
            margin-top: 15px;
            background: #25d366;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #1e3c3f;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .content { padding: 20px; }
            .student-name { font-size: 1.2rem; }
            .header h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <?php if ($action == 'approve'): ?>
                <i class="fas fa-check-circle"></i> قبول الطالب الخاص
            <?php else: ?>
                <i class="fas fa-times-circle"></i> رفض الطالب الخاص
            <?php endif; ?>
        </h1>
        <p>الطلب رقم: <?php echo safeHtml($request['request_number']); ?></p>
    </div>
    
    <div class="content">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
            <?php if ($action == 'approve' && isset($whatsapp_url) && $whatsapp_url): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>تم إنشاء حساب للطالب:</strong><br>
                    اسم المستخدم: <code>student_...</code><br>
                    كلمة المرور: <code>123456</code><br>
                    <strong>تم إرسال تفاصيل الحساب لولي الأمر عبر واتساب.</strong>
                </div>
                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="whatsapp-link">
                    <i class="fab fa-whatsapp"></i> فتح واتساب (إرسال لولي الأمر)
                </a>
            <?php endif; ?>
            <a href="teacher_dashboard.php" class="back-link">← العودة إلى لوحة التحكم</a>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
            <a href="teacher_dashboard.php" class="back-link">← العودة إلى لوحة التحكم</a>
        <?php else: ?>
            <div class="student-info">
                <i class="fas fa-user-graduate" style="font-size: 3rem; color: #c9a96b;"></i>
                <div class="student-name"><?php echo safeHtml($student_name); ?></div>
                <div class="info-details">
                    <p>العمر: <?php echo $request['student_age']; ?> سنة</p>
                    <p>ولي الأمر: <?php echo safeHtml($request['parent_name']); ?></p>
                    <p>رقم الهاتف: <span dir="ltr"><?php echo safeHtml($request['parent_phone']); ?></span></p>
                    <?php if ($request['total_memorized_surahs'] > 0 || $request['total_memorized_parts'] > 0): ?>
                        <p>المحفوظات: 
                            <?php if ($request['total_memorized_surahs'] > 0): ?>
                                <?php echo $request['total_memorized_surahs']; ?> سورة
                            <?php endif; ?>
                            <?php if ($request['total_memorized_parts'] > 0): ?>
                                <?php echo $request['total_memorized_parts']; ?> جزء
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($action == 'approve'): ?>
                <form method="post">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> الموعد المناسب (اختياري)</label>
                        <input type="time" name="schedule_time" class="form-control" id="scheduleTime">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-video"></i> رابط الاجتماع (للطلاب الأونلاين)</label>
                        <input type="text" name="meeting_link" class="form-control" id="meetingLink" placeholder="https://meet.google.com/...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> ملاحظات إضافية</label>
                        <textarea name="additional_notes" class="form-control" rows="3" id="additionalNotes"></textarea>
                    </div>
                    <div class="info-box" style="background: #fff3cd;">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> بعد قبول الطالب، سيتم إنشاء حساب له وإرسال تفاصيل الحساب لولي الأمر عبر واتساب.
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> تأكيد القبول
                    </button>
                </form>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> سبب الرفض (اختياري)</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="اكتب سبب الرفض..."></textarea>
                    </div>
                    <div class="info-box" style="background: #fff3cd;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تنبيه:</strong> بعد الرفض، سيتم إعادة الطلب للإدارة لإعادة توزيعه على معلم آخر.
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> تأكيد الرفض
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>