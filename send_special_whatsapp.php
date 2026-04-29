<?php
// ============================================
// ملف: send_special_whatsapp.php
// إرسال إشعارات واتساب للطلاب الخاصين
// ============================================

function sendSpecialWhatsApp($pdo, $student_id, $ring_id, $custom_message = null) {
    // جلب معلومات الطالب
    $student = $pdo->prepare("
        SELECT s.*, r.name as ring_name, r.start_time, r.end_time, r.days_of_week, r.location, r.meeting_link,
               t.name as teacher_name, t.phone as teacher_phone
        FROM special_students s
        JOIN special_rings r ON s.ring_id = r.id
        JOIN teachers t ON s.teacher_id = t.id
        WHERE s.id = ?
    ");
    $student->execute([$student_id]);
    $data = $student->fetch();
    
    if (!$data) {
        return ['success' => false, 'message' => 'الطالب غير موجود'];
    }
    
    // تحديد رقم الهاتف (ولي الأمر أو الطالب)
    $phone = $data['parent_phone'];
    if (empty($phone)) {
        return ['success' => false, 'message' => 'لا يوجد رقم هاتف مسجل'];
    }
    
    // تنسيق الرقم
    $formatted_phone = formatWhatsAppNumber($phone);
    if (!$formatted_phone) {
        return ['success' => false, 'message' => 'رقم الهاتف غير صالح'];
    }
    
    // أيام الأسبوع
    $days_map = [
        1 => 'الأحد', 2 => 'الإثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء',
        5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'
    ];
    
    $days_text = '';
    if ($data['days_of_week']) {
        $days_array = explode(',', $data['days_of_week']);
        $days_list = [];
        foreach ($days_array as $d) {
            if (isset($days_map[$d])) {
                $days_list[] = $days_map[$d];
            }
        }
        $days_text = implode(' - ', $days_list);
    }
    
    // إنشاء الرسالة
    if ($custom_message) {
        $message = $custom_message;
    } else {
        $message = "السلام عليكم ورحمة الله وبركاته\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📋 *تأكيد موعد الحلقة الخاصة*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "👤 *الطالب/ة:* {$data['student_name']}\n";
        $message .= "👨‍🏫 *المعلم:* {$data['teacher_name']}\n\n";
        $message .= "📚 *معلومات الحلقة:*\n";
        $message .= "┌─────────────────────────────────\n";
        $message .= "│ 🏷️ *اسم الحلقة:* {$data['ring_name']}\n";
        
        if ($days_text) {
            $message .= "│ \n";
            $message .= "│ 📅 *أيام الانعقاد:*\n";
            $message .= "│    • {$days_text}\n";
        }
        
        if ($data['start_time'] && $data['end_time']) {
            $start = date('h:i A', strtotime($data['start_time']));
            $end = date('h:i A', strtotime($data['end_time']));
            $message .= "│ \n";
            $message .= "│ 🕐 *الموعد:* {$start} - {$end}\n";
        }
        
        if ($data['location']) {
            $message .= "│ \n";
            $message .= "│ 📍 *المكان:* {$data['location']}\n";
        }
        
        if ($data['meeting_link']) {
            $message .= "│ \n";
            $message .= "│ 💻 *رابط الاجتماع:*\n";
            $message .= "│    {$data['meeting_link']}\n";
        }
        
        $message .= "└─────────────────────────────────\n\n";
        $message .= "📝 *ملاحظات:* يرجى الحضور في الموعد المحدد.\n";
        $message .= "للتواصل مع المعلم: {$data['teacher_phone']}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "دار التقوى لتحفيظ القرآن الكريم";
    }
    
    // إنشاء رابط واتساب
    $whatsapp_url = "https://wa.me/{$formatted_phone}?text=" . urlencode($message);
    
    // تسجيل في السجل
    $log = $pdo->prepare("
        INSERT INTO special_whatsapp_logs (student_id, ring_id, phone_number, message, status, sent_at)
        VALUES (?, ?, ?, ?, 'sent', NOW())
    ");
    $log->execute([$student_id, $ring_id, $phone, $message]);
    
    return [
        'success' => true,
        'url' => $whatsapp_url,
        'phone' => $formatted_phone,
        'message' => $message
    ];
}

// إذا تم استدعاء الملف مباشرة مع GET
if (basename($_SERVER['PHP_SELF']) == 'send_special_whatsapp.php' && isset($_GET['student_id'])) {
    require_once 'config.php';
    require_once 'functions.php';
    
    if (!isTeacher() && !isAdmin()) {
        die('غير مصرح');
    }
    
    $student_id = (int)$_GET['student_id'];
    $ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;
    
    $result = sendSpecialWhatsApp($pdo, $student_id, $ring_id);
    
    if ($result['success']) {
        header("Location: " . $result['url']);
        exit;
    } else {
        echo "<div class='alert alert-error'>" . $result['message'] . "</div>";
        echo "<a href='javascript:history.back()'>العودة</a>";
    }
}
?>