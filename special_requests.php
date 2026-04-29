<?php
// ============================================
// ملف: special_requests.php
// إدارة طلبات الطلاب الخاصين - تصميم عصري بالكامل
// آخر تحديث: 2026-03-31
// ============================================
ob_start();
require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الطلاب الخاصين - طلبات الالتحاق';
require_once 'includes/header.php';

// دالة آمنة لعرض النصوص
function safeHtml($text) {
    if ($text === null) return '';
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// دالة إرسال واتساب للمعلم
function sendWhatsAppToTeacher($teacher_phone, $teacher_name, $student_name, $request_id) {
    if (empty($teacher_phone)) return false;
    $phone = formatWhatsAppNumber($teacher_phone);
    if (!$phone) return false;
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $approve_link = $base_url . "/special_teacher_approve.php?request_id=" . $request_id . "&action=approve";
    $reject_link = $base_url . "/special_teacher_approve.php?request_id=" . $request_id . "&action=reject";
    $message = "السلام عليكم ورحمة الله وبركاته\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📋 *طلب طالب خاص جديد*\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nالسلام عليكم أستاذ/ة *{$teacher_name}*\n\nتم توزيع طالب خاص إليك:\n┌─────────────────────────────────\n│ 👤 *اسم الطالب:* {$student_name}\n│ 📅 *تاريخ الطلب:* " . date('Y-m-d') . "\n└─────────────────────────────────\n\n📌 *يرجى الضغط على الرابط المناسب:*\n\n✅ *للموافقة:*\n   {$approve_link}\n\n❌ *للرفض:*\n   {$reject_link}\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nدار التقوى لتحفيظ القرآن الكريم";
    return "https://wa.me/{$phone}?text=" . urlencode($message);
}

// جلب المعلمين
$teachers = $pdo->query("
    SELECT id, name, gender, can_teach_special, can_login, phone 
    FROM teachers 
    WHERE can_login = 1 AND can_teach_special = 1 
    ORDER BY name
")->fetchAll();

// جلب الطلبات حسب حالتها
$pending_requests = $pdo->query("
    SELECT r.*, st.name_ar as type_name
    FROM special_enrollment_requests r
    LEFT JOIN special_student_types st ON r.preferred_ring_type_id = st.id
    WHERE r.status = 'pending' 
    AND (r.teacher_approved IS NULL OR r.teacher_approved = 0)
    AND (r.assigned_to_teacher_id IS NULL OR r.assigned_to_teacher_id = 0)
    ORDER BY r.created_at DESC
")->fetchAll();

$assigned_requests = $pdo->query("
    SELECT r.*, st.name_ar as type_name, t.name as teacher_name, t.phone as teacher_phone
    FROM special_enrollment_requests r
    LEFT JOIN special_student_types st ON r.preferred_ring_type_id = st.id
    LEFT JOIN teachers t ON r.assigned_to_teacher_id = t.id
    WHERE r.status = 'assigned_to_teacher' 
    AND r.teacher_approved IS NULL
    ORDER BY r.created_at DESC
")->fetchAll();

$rejected_requests = $pdo->query("
    SELECT r.*, st.name_ar as type_name, t.name as teacher_name
    FROM special_enrollment_requests r
    LEFT JOIN special_student_types st ON r.preferred_ring_type_id = st.id
    LEFT JOIN teachers t ON r.assigned_to_teacher_id = t.id
    WHERE r.teacher_approved = 0 
    AND r.status = 'pending'
    AND (r.assigned_to_teacher_id IS NULL OR r.assigned_to_teacher_id = 0)
    ORDER BY r.teacher_approved_at DESC
")->fetchAll();

$approved_requests = $pdo->query("
    SELECT r.*, st.name_ar as type_name, t.name as teacher_name, s.name as student_name
    FROM special_enrollment_requests r
    LEFT JOIN special_student_types st ON r.preferred_ring_type_id = st.id
    LEFT JOIN teachers t ON r.assigned_to_teacher_id = t.id
    LEFT JOIN students s ON r.student_id = s.id
    WHERE r.status = 'approved'
    ORDER BY r.teacher_approved_at DESC
    LIMIT 20
")->fetchAll();

// معالجة توزيع الطلب
if (isset($_GET['assign']) && isset($_GET['teacher_id'])) {
    $request_id = (int)$_GET['assign'];
    $teacher_id = (int)$_GET['teacher_id'];
    $send_whatsapp = isset($_GET['send_whatsapp']) ? (bool)$_GET['send_whatsapp'] : true;
    try {
        $check = $pdo->prepare("SELECT * FROM special_enrollment_requests WHERE id = ? AND (status = 'pending' OR (teacher_approved = 0 AND assigned_to_teacher_id IS NULL))");
        $check->execute([$request_id]);
        $req = $check->fetch();
        if (!$req) throw new Exception("الطلب غير موجود أو تمت معالجته مسبقاً");
        $teacher_stmt = $pdo->prepare("SELECT name, phone FROM teachers WHERE id = ?");
        $teacher_stmt->execute([$teacher_id]);
        $teacher = $teacher_stmt->fetch();
        if (!$teacher) throw new Exception("المعلم غير موجود");
        $update = $pdo->prepare("UPDATE special_enrollment_requests SET status = 'assigned_to_teacher', assigned_to_teacher_id = ?, teacher_approved = NULL, teacher_notes = NULL, teacher_approved_at = NULL, updated_at = NOW() WHERE id = ?");
        $update->execute([$teacher_id, $request_id]);
        $pdo->exec("CREATE TABLE IF NOT EXISTS special_teacher_notifications (id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT NOT NULL, request_id INT NOT NULL, is_read TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_teacher (teacher_id), INDEX idx_request (request_id))");
        $notify = $pdo->prepare("INSERT INTO special_teacher_notifications (teacher_id, request_id) VALUES (?, ?)");
        $notify->execute([$teacher_id, $request_id]);
        $whatsapp_url = '';
        if ($send_whatsapp && !empty($teacher['phone'])) {
            $whatsapp_url = sendWhatsAppToTeacher($teacher['phone'], $teacher['name'], $req['student_name'], $request_id);
            if ($whatsapp_url) {
                $_SESSION['whatsapp_sent'] = true;
                $_SESSION['whatsapp_url'] = $whatsapp_url;
                $_SESSION['whatsapp_teacher'] = $teacher['name'];
            }
        }
        $_SESSION['success'] = "✅ تم توزيع الطلب على المعلم {$teacher['name']} بنجاح" . ($whatsapp_url ? " وتم إرسال إشعار واتساب" : "");
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: special_requests.php");
    exit;
}

// معالجة حذف الطلب
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';
    try {
        $req_stmt = $pdo->prepare("SELECT * FROM special_enrollment_requests WHERE id = ?");
        $req_stmt->execute([$request_id]);
        $req = $req_stmt->fetch();
        if (!$req) throw new Exception("الطلب غير موجود");
        $pdo->exec("CREATE TABLE IF NOT EXISTS special_rejected_requests_archive (id INT AUTO_INCREMENT PRIMARY KEY, request_number VARCHAR(50), student_name VARCHAR(255), parent_phone VARCHAR(20), rejection_reason TEXT, rejected_by VARCHAR(100), rejected_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $archive = $pdo->prepare("INSERT INTO special_rejected_requests_archive (request_number, student_name, parent_phone, rejection_reason, rejected_by, rejected_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $archive->execute([$req['request_number'] ?? '', $req['student_name'] ?? '', $req['parent_phone'] ?? '', $reason, $_SESSION['user_name'] ?? 'admin']);
        $pdo->prepare("DELETE FROM special_teacher_notifications WHERE request_id = ?")->execute([$request_id]);
        $pdo->prepare("DELETE FROM special_enrollment_requests WHERE id = ?")->execute([$request_id]);
        $_SESSION['success'] = "✅ تم حذف الطلب نهائياً بنجاح";
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: special_requests.php");
    exit;
}

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
$whatsapp_sent = $_SESSION['whatsapp_sent'] ?? false;
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
$whatsapp_teacher = $_SESSION['whatsapp_teacher'] ?? '';
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['whatsapp_sent'], $_SESSION['whatsapp_url'], $_SESSION['whatsapp_teacher']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>الطلاب الخاصين - طلبات الالتحاق | دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1e3c3f;
            --primary-dark: #0a2a2c;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --secondary-light: #dbb87c;
            --success: #28a745;
            --success-light: #d4edda;
            --danger: #dc3545;
            --danger-light: #f8d7da;
            --warning: #ffc107;
            --warning-light: #fff3cd;
            --info: #17a2b8;
            --info-light: #d1ecf1;
            --whatsapp: #25d366;
            --whatsapp-light: #e8f5e9;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --white: #ffffff;
            --gradient-primary: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            --gradient-secondary: linear-gradient(135deg, #c9a96b, #dbb87c);
            --gradient-success: linear-gradient(135deg, #28a745, #20c997);
            --gradient-danger: linear-gradient(135deg, #dc3545, #c82333);
            --gradient-warning: linear-gradient(135deg, #ffc107, #e0a800);
            --gradient-info: linear-gradient(135deg, #17a2b8, #138496);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
            --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 20px;
            --border-radius-xl: 30px;
            --border-radius-full: 9999px;
            --transition: 0.3s ease;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        /* الحاوية الرئيسية */
        .special-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* ===== رأس الصفحة العصري ===== */
        .hero-section {
            background: var(--gradient-primary);
            border-radius: var(--border-radius-xl);
            padding: 35px 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .hero-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .hero-title i {
            font-size: 3rem;
            color: var(--secondary);
            animation: crownFloat 3s ease-in-out infinite;
        }

        @keyframes crownFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .hero-title h1 {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin: 0;
        }

        .hero-title p {
            color: rgba(255,255,255,0.8);
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .hero-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .stat-pill {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: var(--border-radius-full);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: var(--transition);
        }

        .stat-pill:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        .stat-pill i {
            color: var(--secondary);
        }

        .hero-actions {
            display: flex;
            gap: 12px;
        }

        .hero-btn {
            background: rgba(255,255,255,0.15);
            padding: 10px 24px;
            border-radius: var(--border-radius-full);
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .hero-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* ===== تبويبات الحالة ===== */
        .status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .tab-btn {
            padding: 12px 28px;
            border-radius: var(--border-radius-full);
            background: white;
            border: none;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
            color: var(--dark);
        }

        .tab-btn i {
            font-size: 1.1rem;
        }

        .tab-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .tab-btn.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .tab-btn.active i {
            color: var(--secondary);
        }

        /* ===== أقسام المحتوى ===== */
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== بطاقات الطلبات ===== */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        .request-card {
            background: white;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }

        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        /* شريط علوي ملون حسب الحالة */
        .card-strip {
            height: 6px;
        }

        .card-strip.pending { background: linear-gradient(90deg, var(--warning), #ffdb58); }
        .card-strip.assigned { background: linear-gradient(90deg, var(--info), #138496); }
        .card-strip.rejected { background: linear-gradient(90deg, var(--danger), #ff6b6b); }
        .card-strip.approved { background: linear-gradient(90deg, var(--success), #20c997); }

        .card-content {
            padding: 20px;
        }

        /* رأس البطاقة */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .request-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: var(--border-radius-full);
            font-size: 0.7rem;
            font-weight: 700;
        }

        .request-badge.pending { background: var(--warning-light); color: #856404; }
        .request-badge.assigned { background: var(--info-light); color: #0c5460; }
        .request-badge.rejected { background: var(--danger-light); color: #721c24; }
        .request-badge.approved { background: var(--success-light); color: #155724; }

        .request-number {
            font-size: 0.7rem;
            color: var(--gray);
            background: var(--light);
            padding: 4px 10px;
            border-radius: var(--border-radius-full);
        }

        /* معلومات الطالب */
        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .student-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--secondary);
        }

        .student-details {
            flex: 1;
        }

        .student-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .student-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--gray);
        }

        .student-meta i {
            margin-left: 3px;
            color: var(--secondary);
        }

        /* تفاصيل الطلب */
        .request-details {
            background: var(--light);
            border-radius: var(--border-radius-lg);
            padding: 15px;
            margin: 15px 0;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed var(--gray-light);
            font-size: 0.85rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row i {
            width: 24px;
            color: var(--secondary);
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray);
            min-width: 85px;
        }

        .detail-value {
            color: var(--dark);
            flex: 1;
        }

        /* المحفوظات */
        .memorization-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 12px 0;
        }

        .mem-badge {
            background: var(--secondary-light);
            color: var(--primary-dark);
            padding: 4px 12px;
            border-radius: var(--border-radius-full);
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* معلومات المعلم */
        .teacher-card {
            background: var(--info-light);
            border-radius: var(--border-radius-lg);
            padding: 12px;
            margin: 12px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: #0c5460;
        }

        .teacher-card i {
            font-size: 1.2rem;
        }

        .rejection-note {
            background: var(--danger-light);
            border-radius: var(--border-radius-lg);
            padding: 12px;
            margin: 12px 0;
            color: #721c24;
            font-size: 0.85rem;
            border-right: 3px solid var(--danger);
        }

        /* أزرار الإجراءات */
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            padding: 10px 12px;
            border-radius: var(--border-radius-full);
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }

        .action-btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .action-btn-warning {
            background: var(--warning);
            color: #212529;
        }

        .action-btn-danger {
            background: var(--danger);
            color: white;
        }

        .action-btn-info {
            background: var(--info);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            box-shadow: var(--shadow-sm);
        }

        /* ===== نافذة منبثقة ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-window {
            background: white;
            border-radius: var(--border-radius-xl);
            width: 100%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlide 0.3s ease;
            box-shadow: var(--shadow-xl);
        }

        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 2px solid var(--secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .teacher-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius-lg);
            margin: 15px 0;
        }

        .teacher-option {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-light);
            cursor: pointer;
            transition: var(--transition);
        }

        .teacher-option:hover {
            background: var(--light);
        }

        .teacher-option.selected {
            background: var(--success-light);
            border-right: 4px solid var(--success);
        }

        .teacher-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .teacher-info {
            flex: 1;
        }

        .teacher-name {
            font-weight: 700;
            color: var(--primary);
        }

        .teacher-phone {
            font-size: 0.7rem;
            color: var(--gray);
            direction: ltr;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--light);
            padding: 12px;
            border-radius: var(--border-radius-lg);
            margin: 15px 0;
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

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-lg);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        /* ===== حالة فارغة ===== */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: var(--border-radius-xl);
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-light);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--gray);
        }

        /* ===== رسائل ===== */
        .alert-message {
            padding: 15px 20px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }

        .alert-success {
            background: var(--success-light);
            color: #155724;
            border-right: 4px solid var(--success);
        }

        .alert-error {
            background: var(--danger-light);
            color: #721c24;
            border-right: 4px solid var(--danger);
        }

        .alert-whatsapp {
            background: var(--whatsapp-light);
            color: #155724;
            border-right: 4px solid var(--whatsapp);
        }

        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 768px) {
            .special-container { padding: 15px; }
            .hero-content { flex-direction: column; text-align: center; }
            .hero-title { flex-direction: column; }
            .hero-stats { justify-content: center; }
            .hero-actions { width: 100%; justify-content: center; }
            .hero-btn { flex: 1; justify-content: center; }
            .requests-grid { grid-template-columns: 1fr; }
            .status-tabs { flex-direction: column; }
            .tab-btn { width: 100%; justify-content: center; }
            .card-actions { flex-direction: column; }
            .modal-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="special-container">
    <!-- رأس الصفحة العصري -->
    <div class="hero-section">
        <div class="hero-content">
            <div class="hero-title">
                <i class="fas fa-crown"></i>
                <div>
                    <h1>الطلاب الخاصين</h1>
                    <p>إدارة طلبات الالتحاق بالبرنامج الخاص</p>
                </div>
            </div>
            <div class="hero-stats">
                <div class="stat-pill"><i class="fas fa-clock"></i> <?php echo count($pending_requests); ?> قيد الانتظار</div>
                <div class="stat-pill"><i class="fas fa-user-clock"></i> <?php echo count($assigned_requests); ?> معلمين</div>
                <div class="stat-pill"><i class="fas fa-times-circle"></i> <?php echo count($rejected_requests); ?> مرفوضة</div>
                <div class="stat-pill"><i class="fas fa-check-circle"></i> <?php echo count($approved_requests); ?> مقبولة</div>
            </div>
            <div class="hero-actions">
                <a href="special_enroll.php" class="hero-btn" target="_blank"><i class="fas fa-external-link-alt"></i> رابط التقديم</a>
                <a href="special_students.php" class="hero-btn"><i class="fas fa-users"></i> الطلاب الخاصين</a>
            </div>
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if ($success_message): ?>
        <div class="alert-message alert-success"><i class="fas fa-check-circle"></i> <?php echo safeHtml($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-message alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo safeHtml($error_message); ?></div>
    <?php endif; ?>
    <?php if ($whatsapp_sent && $whatsapp_url): ?>
        <div class="alert-message alert-whatsapp">
            <i class="fab fa-whatsapp" style="font-size:1.5rem; color:#25d366;"></i>
            <div style="flex:1;"><strong>✅ تم إرسال إشعار واتساب للمعلم <?php echo safeHtml($whatsapp_teacher); ?></strong></div>
            <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="action-btn action-btn-primary" style="background:#25d366; padding:8px 20px;">فتح واتساب</a>
        </div>
    <?php endif; ?>

    <!-- تبويبات الحالة -->
    <div class="status-tabs">
        <button class="tab-btn active" data-tab="pending"><i class="fas fa-clock"></i> قيد الانتظار <span class="badge"><?php echo count($pending_requests); ?></span></button>
        <button class="tab-btn" data-tab="assigned"><i class="fas fa-user-clock"></i> معلمين <span><?php echo count($assigned_requests); ?></span></button>
        <button class="tab-btn" data-tab="rejected"><i class="fas fa-times-circle"></i> مرفوضة <span><?php echo count($rejected_requests); ?></span></button>
        <button class="tab-btn" data-tab="approved"><i class="fas fa-check-circle"></i> مقبولة <span><?php echo count($approved_requests); ?></span></button>
    </div>

    <!-- محتوى التبويب: قيد الانتظار -->
    <div id="tab-pending" class="tab-content active">
        <?php if (empty($pending_requests)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><h3>لا توجد طلبات جديدة</h3><p>جميع الطلبات تمت معالجتها</p></div>
        <?php else: ?>
            <div class="requests-grid">
                <?php foreach ($pending_requests as $req): ?>
                    <div class="request-card">
                        <div class="card-strip pending"></div>
                        <div class="card-content">
                            <div class="card-header">
                                <span class="request-badge pending"><i class="fas fa-clock"></i> قيد الانتظار</span>
                                <span class="request-number"><i class="fas fa-qrcode"></i> <?php echo safeHtml($req['request_number']); ?></span>
                            </div>
                            <div class="student-info">
                                <div class="student-avatar"><?php echo mb_substr($req['student_name'], 0, 1, 'UTF-8'); ?></div>
                                <div class="student-details">
                                    <div class="student-name"><?php echo safeHtml($req['student_name']); ?></div>
                                    <div class="student-meta">
                                        <span><i class="fas fa-venus-mars"></i> <?php echo $req['student_gender'] == 'male' ? 'ذكر' : 'أنثى'; ?></span>
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo $req['student_age']; ?> سنة</span>
                                    </div>
                                </div>
                            </div>
                            <div class="request-details">
                                <div class="detail-row"><i class="fas fa-user-tie"></i><span class="detail-label">ولي الأمر:</span><span class="detail-value"><?php echo safeHtml($req['parent_name']); ?></span></div>
                                <div class="detail-row"><i class="fas fa-phone"></i><span class="detail-label">رقم الهاتف:</span><span class="detail-value" dir="ltr"><?php echo safeHtml($req['parent_phone']); ?></span></div>
                                <div class="detail-row"><i class="fas fa-ring"></i><span class="detail-label">نوع الدراسة:</span><span class="detail-value"><?php echo safeHtml($req['type_name'] ?? ($req['preferred_ring_type_id'] == 1 ? 'حضوري' : 'أونلاين')); ?></span></div>
                            </div>
                            <div class="memorization-badges">
                                <i class="fas fa-quran" style="color:var(--secondary);"></i>
                                <?php if (($req['total_memorized_surahs'] ?? 0) > 0): ?><span class="mem-badge"><?php echo safeHtml($req['total_memorized_surahs']); ?> سورة</span><?php endif; ?>
                                <?php if (($req['total_memorized_parts'] ?? 0) > 0): ?><span class="mem-badge"><?php echo safeHtml($req['total_memorized_parts']); ?> جزء</span><?php endif; ?>
                                <?php if (($req['total_memorized_surahs'] ?? 0) == 0 && ($req['total_memorized_parts'] ?? 0) == 0): ?><span class="mem-badge">مبتدئ</span><?php endif; ?>
                            </div>
                            <?php if (!empty($req['notes'])): ?>
                                <div style="background:var(--warning-light); padding:10px; border-radius:var(--border-radius-lg); font-size:0.85rem;"><i class="fas fa-sticky-note"></i> <?php echo nl2br(safeHtml($req['notes'])); ?></div>
                            <?php endif; ?>
                            <div class="card-actions">
                                <button class="action-btn action-btn-primary" onclick="openAssignModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')"><i class="fas fa-user-plus"></i> توزيع على معلم</button>
                                <button class="action-btn action-btn-danger" onclick="openDeleteModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')"><i class="fas fa-trash-alt"></i> حذف</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- محتوى التبويب: معلمين (موزعة) -->
    <div id="tab-assigned" class="tab-content">
        <?php if (empty($assigned_requests)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><h3>لا توجد طلبات موزعة</h3><p>جميع الطلبات قيد الانتظار أو تمت معالجتها</p></div>
        <?php else: ?>
            <div class="requests-grid">
                <?php foreach ($assigned_requests as $req): ?>
                    <div class="request-card">
                        <div class="card-strip assigned"></div>
                        <div class="card-content">
                            <div class="card-header">
                                <span class="request-badge assigned"><i class="fas fa-user-clock"></i> بانتظار رد المعلم</span>
                                <span class="request-number"><i class="fas fa-qrcode"></i> <?php echo safeHtml($req['request_number']); ?></span>
                            </div>
                            <div class="student-info">
                                <div class="student-avatar"><?php echo mb_substr($req['student_name'], 0, 1, 'UTF-8'); ?></div>
                                <div class="student-details">
                                    <div class="student-name"><?php echo safeHtml($req['student_name']); ?></div>
                                    <div class="student-meta"><span><i class="fas fa-calendar-alt"></i> <?php echo $req['student_age']; ?> سنة</span></div>
                                </div>
                            </div>
                            <div class="teacher-card">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <div><strong>المعلم:</strong> <?php echo safeHtml($req['teacher_name'] ?? 'غير محدد'); ?></div>
                                <?php if (!empty($req['teacher_phone'])): ?><div><i class="fas fa-phone"></i> <?php echo safeHtml($req['teacher_phone']); ?></div><?php endif; ?>
                            </div>
                            <div class="request-details">
                                <div class="detail-row"><i class="fas fa-user-tie"></i><span class="detail-label">ولي الأمر:</span><span class="detail-value"><?php echo safeHtml($req['parent_name']); ?></span></div>
                                <div class="detail-row"><i class="fas fa-phone"></i><span class="detail-label">رقم الهاتف:</span><span class="detail-value" dir="ltr"><?php echo safeHtml($req['parent_phone']); ?></span></div>
                            </div>
                            <div class="card-actions">
                                <button class="action-btn action-btn-warning" onclick="openReassignModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')"><i class="fas fa-exchange-alt"></i> إعادة توزيع</button>
                                <button class="action-btn action-btn-danger" onclick="openDeleteModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')"><i class="fas fa-trash-alt"></i> حذف</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- محتوى التبويب: مرفوضة -->
    <div id="tab-rejected" class="tab-content">
        <?php if (empty($rejected_requests)): ?>
            <div class="empty-state"><i class="fas fa-smile"></i><h3>لا توجد طلبات مرفوضة</h3><p>جميع الطلبات تمت معالجتها بنجاح</p></div>
        <?php else: ?>
            <div class="requests-grid">
                <?php foreach ($rejected_requests as $req): ?>
                    <div class="request-card">
                        <div class="card-strip rejected"></div>
                        <div class="card-content">
                            <div class="card-header">
                                <span class="request-badge rejected"><i class="fas fa-times-circle"></i> مرفوض من المعلم</span>
                                <span class="request-number"><i class="fas fa-qrcode"></i> <?php echo safeHtml($req['request_number']); ?></span>
                            </div>
                            <div class="student-info">
                                <div class="student-avatar"><?php echo mb_substr($req['student_name'], 0, 1, 'UTF-8'); ?></div>
                                <div class="student-details">
                                    <div class="student-name"><?php echo safeHtml($req['student_name']); ?></div>
                                    <div class="student-meta"><span><i class="fas fa-calendar-alt"></i> <?php echo $req['student_age']; ?> سنة</span></div>
                                </div>
                            </div>
                            <?php if (!empty($req['teacher_notes'])): ?>
                                <div class="rejection-note"><i class="fas fa-comment"></i> <strong>سبب الرفض:</strong> <?php echo safeHtml($req['teacher_notes']); ?></div>
                            <?php endif; ?>
                            <div class="request-details">
                                <div class="detail-row"><i class="fas fa-user-tie"></i><span class="detail-label">ولي الأمر:</span><span class="detail-value"><?php echo safeHtml($req['parent_name']); ?></span></div>
                                <div class="detail-row"><i class="fas fa-phone"></i><span class="detail-label">رقم الهاتف:</span><span class="detail-value" dir="ltr"><?php echo safeHtml($req['parent_phone']); ?></span></div>
                            </div>
                            <div class="card-actions">
                                <button class="action-btn action-btn-primary" onclick="openAssignModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')"><i class="fas fa-user-plus"></i> إعادة التوزيع</button>
                                <button class="action-btn action-btn-danger" onclick="openDeleteModal(<?php echo $req['id']; ?>, '<?php echo addslashes(safeHtml($req['student_name'])); ?>')"><i class="fas fa-trash-alt"></i> حذف</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- محتوى التبويب: مقبولة -->
    <div id="tab-approved" class="tab-content">
        <?php if (empty($approved_requests)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><h3>لا توجد طلبات مقبولة بعد</h3><p>سيتم عرض الطلبات التي تم قبولها هنا</p></div>
        <?php else: ?>
            <div class="requests-grid">
                <?php foreach ($approved_requests as $req): ?>
                    <div class="request-card">
                        <div class="card-strip approved"></div>
                        <div class="card-content">
                            <div class="card-header">
                                <span class="request-badge approved"><i class="fas fa-check-circle"></i> مقبول - طالب خاص</span>
                                <span class="request-number"><i class="fas fa-qrcode"></i> <?php echo safeHtml($req['request_number']); ?></span>
                            </div>
                            <div class="student-info">
                                <div class="student-avatar"><?php echo mb_substr($req['student_name'], 0, 1, 'UTF-8'); ?></div>
                                <div class="student-details">
                                    <div class="student-name"><?php echo safeHtml($req['student_name']); ?></div>
                                    <div class="student-meta"><span><i class="fas fa-calendar-alt"></i> <?php echo $req['student_age']; ?> سنة</span></div>
                                </div>
                            </div>
                            <div class="teacher-card">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <div><strong>المعلم:</strong> <?php echo safeHtml($req['teacher_name'] ?? 'غير محدد'); ?></div>
                            </div>
                            <div class="memorization-badges" style="background:var(--success-light); padding:8px; border-radius:var(--border-radius-lg);">
                                <i class="fas fa-check-circle" style="color:var(--success);"></i> تم إنشاء حساب للطالب
                            </div>
                            <div class="request-details">
                                <div class="detail-row"><i class="fas fa-phone"></i><span class="detail-label">رقم الهاتف:</span><span class="detail-value" dir="ltr"><?php echo safeHtml($req['parent_phone']); ?></span></div>
                                <div class="detail-row"><i class="fas fa-calendar-check"></i><span class="detail-label">تاريخ القبول:</span><span class="detail-value"><?php echo date('Y-m-d', strtotime($req['teacher_approved_at'])); ?></span></div>
                            </div>
                            <div class="card-actions">
                                <a href="view_progress.php?student_id=<?php echo $req['student_id']; ?>" class="action-btn action-btn-info"><i class="fas fa-chart-line"></i> عرض التقدم</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- نافذة توزيع الطلب -->
<!-- ============================================ -->
<div id="assignModal" class="modal-overlay">
    <div class="modal-window">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> توزيع الطلب على معلم</h3>
            <button class="modal-close" onclick="closeAssignModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="assignStudentName" style="background:var(--light); padding:15px; border-radius:var(--border-radius-lg); margin-bottom:20px; text-align:center; font-weight:bold;"></div>
            <div class="teacher-list" id="teacherList">
                <?php if (empty($teachers)): ?>
                    <div style="padding:20px; text-align:center;"><i class="fas fa-exclamation-triangle"></i> لا يوجد معلمين متاحين</div>
                <?php else: ?>
                    <?php foreach ($teachers as $t): ?>
                        <div class="teacher-option" data-teacher-id="<?php echo $t['id']; ?>" onclick="selectTeacher(this)">
                            <div class="teacher-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                            <div class="teacher-info">
                                <div class="teacher-name"><?php echo safeHtml($t['name']); ?></div>
                                <div class="teacher-phone"><?php echo safeHtml($t['phone'] ?? 'لا يوجد هاتف'); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="sendWhatsappAssign" checked>
                <label for="sendWhatsappAssign"><i class="fab fa-whatsapp"></i> إرسال إشعار واتساب للمعلم (رابط للموافقة/الرفض)</label>
            </div>
            <input type="hidden" id="assignRequestId" value="">
            <input type="hidden" id="selectedTeacherId" value="">
            <div class="modal-actions">
                <button class="action-btn action-btn-warning" onclick="closeAssignModal()">إلغاء</button>
                <button class="action-btn action-btn-primary" onclick="submitAssign()">تأكيد التوزيع</button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إعادة توزيع الطلب -->
<div id="reassignModal" class="modal-overlay">
    <div class="modal-window">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt"></i> إعادة توزيع الطلب</h3>
            <button class="modal-close" onclick="closeReassignModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="reassignStudentName" style="background:var(--light); padding:15px; border-radius:var(--border-radius-lg); margin-bottom:20px; text-align:center; font-weight:bold;"></div>
            <div class="teacher-list" id="reassignTeacherList">
                <?php foreach ($teachers as $t): ?>
                    <div class="teacher-option" data-teacher-id="<?php echo $t['id']; ?>" onclick="selectReassignTeacher(this)">
                        <div class="teacher-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="teacher-info">
                            <div class="teacher-name"><?php echo safeHtml($t['name']); ?></div>
                            <div class="teacher-phone"><?php echo safeHtml($t['phone'] ?? 'لا يوجد هاتف'); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="sendWhatsappReassign" checked>
                <label for="sendWhatsappReassign"><i class="fab fa-whatsapp"></i> إرسال إشعار واتساب للمعلم الجديد</label>
            </div>
            <input type="hidden" id="reassignRequestId" value="">
            <input type="hidden" id="selectedReassignTeacherId" value="">
            <div class="modal-actions">
                <button class="action-btn action-btn-warning" onclick="closeReassignModal()">إلغاء</button>
                <button class="action-btn action-btn-primary" onclick="submitReassign()">تأكيد إعادة التوزيع</button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة حذف الطلب -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-window">
        <div class="modal-header">
            <h3><i class="fas fa-trash-alt" style="color:var(--danger);"></i> حذف الطلب نهائياً</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="deleteStudentName" style="background:var(--light); padding:15px; border-radius:var(--border-radius-lg); margin-bottom:20px; text-align:center; font-weight:bold;"></div>
            <div class="alert-message" style="background:var(--danger-light); color:#721c24; margin-bottom:15px;"><i class="fas fa-exclamation-triangle"></i> <strong>تحذير!</strong> هذا الإجراء لا يمكن التراجع عنه.</div>
            <div class="form-group">
                <label><i class="fas fa-comment"></i> سبب الحذف (اختياري)</label>
                <textarea id="deleteReason" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-actions">
                <button class="action-btn action-btn-warning" onclick="closeDeleteModal()">إلغاء</button>
                <button class="action-btn action-btn-danger" onclick="submitDelete()">تأكيد الحذف</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRequestId = null;

// التبويبات
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tabId = btn.getAttribute('data-tab');
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(`tab-${tabId}`).classList.add('active');
    });
});

// دوال توزيع الطلب
function openAssignModal(requestId, studentName) {
    currentRequestId = requestId;
    document.getElementById('assignRequestId').value = requestId;
    document.getElementById('assignStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('assignModal').classList.add('active');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.remove('active');
    document.getElementById('selectedTeacherId').value = '';
    document.querySelectorAll('#teacherList .teacher-option').forEach(opt => opt.classList.remove('selected'));
}

function selectTeacher(element) {
    document.querySelectorAll('#teacherList .teacher-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('selectedTeacherId').value = element.dataset.teacherId;
}

function submitAssign() {
    let teacherId = document.getElementById('selectedTeacherId').value;
    let requestId = document.getElementById('assignRequestId').value;
    let sendWhatsapp = document.getElementById('sendWhatsappAssign').checked ? 1 : 0;
    if (!teacherId) { alert('⚠️ الرجاء اختيار معلم'); return; }
    window.location.href = 'special_requests.php?assign=' + requestId + '&teacher_id=' + teacherId + '&send_whatsapp=' + sendWhatsapp;
}
// دوال إعادة التوزيع
function openReassignModal(requestId, studentName) {
    currentRequestId = requestId;
    document.getElementById('reassignRequestId').value = requestId;
    document.getElementById('reassignStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('reassignModal').classList.add('active');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.remove('active');
    document.getElementById('selectedReassignTeacherId').value = '';
    document.querySelectorAll('#reassignTeacherList .teacher-option').forEach(opt => opt.classList.remove('selected'));
}

function selectReassignTeacher(element) {
    document.querySelectorAll('#reassignTeacherList .teacher-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('selectedReassignTeacherId').value = element.dataset.teacherId;
}

function submitReassign() {
    let teacherId = document.getElementById('selectedReassignTeacherId').value;
    let requestId = document.getElementById('reassignRequestId').value;
    let sendWhatsapp = document.getElementById('sendWhatsappReassign').checked ? 1 : 0;
    if (!teacherId) { alert('⚠️ الرجاء اختيار معلم'); return; }
    window.location.href = 'special_requests.php?assign=' + requestId + '&teacher_id=' + teacherId + '&send_whatsapp=' + sendWhatsapp;
}

// دوال الحذف
function openDeleteModal(requestId, studentName) {
    currentRequestId = requestId;
    document.getElementById('deleteStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

function submitDelete() {
    let requestId = currentRequestId;
    let reason = document.getElementById('deleteReason').value;
    let url = 'special_requests.php?delete=1&id=' + requestId;
    if (reason) url += '&reason=' + encodeURIComponent(reason);
    if (confirm('⚠️ هل أنت متأكد من حذف هذا الطلب نهائياً؟ لا يمكن استعادته.')) window.location.href = url;
}

// إغلاق النوافذ بالنقر خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeAssignModal();
        closeReassignModal();
        closeDeleteModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>