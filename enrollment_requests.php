<?php
// ============================================
// ملف: enrollment_requests.php - إدارة طلبات الالتحاق (للمسؤول)
// مع عرض تفاصيل المحفوظات ومنع التكرار وإضافة اختيار الحلقة
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'check_duplicate.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إدارة طلبات الالتحاق';
require_once 'includes/header.php';

$filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================
// دوال مساعدة
// ============================================

function getTeacherName($pdo, $id) {
    $stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ?: 'غير معروف';
}

function getCategoryNameAr($category) {
    $map = ['boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء'];
    return $map[$category] ?? $category;
}

function getStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'badge-warning', 'text' => 'قيد الانتظار', 'icon' => 'fa-clock'],
        'approved_by_teacher' => ['class' => 'badge-info', 'text' => 'موافقة معلم', 'icon' => 'fa-check-circle'],
        'assigned' => ['class' => 'badge-success', 'text' => 'تم التوزيع', 'icon' => 'fa-user-check'],
        'rejected' => ['class' => 'badge-danger', 'text' => 'مرفوض', 'icon' => 'fa-times-circle']
    ];
    $b = $badges[$status] ?? $badges['pending'];
    return '<span class="badge ' . $b['class'] . '"><i class="fas ' . $b['icon'] . '"></i> ' . $b['text'] . '</span>';
}

function determineContactPhone($request) {
    if ($request['student_age'] >= 18 && !empty($request['student_phone'])) {
        return $request['student_phone'];
    }
    return $request['parent_phone'];
}

/**
 * الحصول على المحفوظات بشكل آمن
 */
function getMemorizedData($data, $key) {
    if (!isset($data[$key]) || empty($data[$key])) {
        return [];
    }
    $decoded = json_decode($data[$key], true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * جلب معلومات الحلقة للمعلم
 */
function getTeacherRingInfo($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               (SELECT GROUP_CONCAT(
                   CONCAT(
                       CASE rs.day_of_week
                           WHEN 1 THEN 'الأحد'
                           WHEN 2 THEN 'الإثنين'
                           WHEN 3 THEN 'الثلاثاء'
                           WHEN 4 THEN 'الأربعاء'
                           WHEN 5 THEN 'الخميس'
                           WHEN 6 THEN 'الجمعة'
                           WHEN 7 THEN 'السبت'
                       END,
                       ' (', TIME_FORMAT(rs.start_time, '%H:%i'), ')'
                   ) ORDER BY rs.day_of_week SEPARATOR '، '
               ) FROM ring_schedules WHERE ring_id = r.id) as schedule
        FROM rings r
        WHERE r.teacher_id = ?
        GROUP BY r.id
        ORDER BY r.id ASC
        LIMIT 1
    ");
    $stmt->execute([$teacher_id]);
    return $stmt->fetch();
}

// جلب المعلمين
$teachers = $pdo->query("SELECT id, name, gender FROM teachers WHERE can_login = 1 ORDER BY name")->fetchAll();

// ============================================
// معالجة توزيع طالب على معلم (مع منع التكرار وإضافة الحلقة)
// ============================================
if (isset($_GET['assign']) && isset($_GET['teacher_id'])) {
    $request_id = (int)$_GET['assign'];
    $teacher_id = (int)$_GET['teacher_id'];
    $ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;
    $send_whatsapp = isset($_GET['send_whatsapp']) ? (bool)$_GET['send_whatsapp'] : true;
    
    try {
        $pdo->beginTransaction();
        
        // 1. التحقق من وجود الطلب وحالته مع قفل
        $reqStmt = $pdo->prepare("SELECT * FROM enrollment_requests WHERE id = ? FOR UPDATE");
        $reqStmt->execute([$request_id]);
        $req = $reqStmt->fetch();
        
        if (!$req) throw new Exception("الطلب غير موجود");
        
        // 2. التحقق من عدم توزيع الطالب مسبقاً
        if ($req['status'] == 'assigned') {
            throw new Exception("❌ هذا الطالب تم توزيعه مسبقاً على معلم!");
        }
        
        // 3. التحقق من عدم وجود نفس الطالب في جدول students
        $checkStudent = $pdo->prepare("
            SELECT id, name, teacher_id, parent_phone 
            FROM students 
            WHERE name = ? AND parent_phone = ?
        ");
        $checkStudent->execute([$req['student_name'], $req['parent_phone']]);
        $existingStudent = $checkStudent->fetch();
        
        if ($existingStudent) {
            throw new Exception("❌ الطالب {$req['student_name']} مسجل بالفعل في النظام! (معلم: " . getTeacherName($pdo, $existingStudent['teacher_id']) . ")");
        }
        
        // 4. جلب معلومات الحلقة إذا تم اختيارها
        $ringInfo = null;
        if ($ring_id > 0) {
            $ringStmt = $pdo->prepare("
                SELECT r.*, 
                       GROUP_CONCAT(
                           CONCAT(
                               CASE rs.day_of_week
                                   WHEN 1 THEN 'الأحد'
                                   WHEN 2 THEN 'الإثنين'
                                   WHEN 3 THEN 'الثلاثاء'
                                   WHEN 4 THEN 'الأربعاء'
                                   WHEN 5 THEN 'الخميس'
                                   WHEN 6 THEN 'الجمعة'
                                   WHEN 7 THEN 'السبت'
                               END,
                               ' (', TIME_FORMAT(rs.start_time, '%H:%i'), ')'
                           ) ORDER BY rs.day_of_week SEPARATOR '، '
                       ) as schedule
                FROM rings r
                LEFT JOIN ring_schedules rs ON r.id = rs.ring_id
                WHERE r.id = ?
                GROUP BY r.id
            ");
            $ringStmt->execute([$ring_id]);
            $ringInfo = $ringStmt->fetch();
        }
        
        // 5. تحديث حالة الطلب
        $update = $pdo->prepare("
            UPDATE enrollment_requests 
            SET status = 'assigned', 
                assigned_teacher_id = ?, 
                assigned_at = NOW() 
            WHERE id = ? AND status != 'assigned'
        ");
        $update->execute([$teacher_id, $request_id]);
        
        if ($update->rowCount() == 0) {
            throw new Exception("❌ فشل تحديث حالة الطلب - ربما تم توزيعه مسبقاً");
        }
        
        // 6. تحديد رقم التواصل
        $contact_phone = determineContactPhone($req);
        
        // 7. جلب المحفوظات بشكل آمن
        $mem_surahs = getMemorizedData($req, 'memorized_surahs');
        $mem_parts = getMemorizedData($req, 'memorized_parts');
        $total_parts = count($mem_parts);
        $total_surahs = count($mem_surahs);
        
        // 8. إنشاء حساب للطالب
        $username = 'student_' . time() . '_' . $req['id'];
        $password = password_hash('123456', PASSWORD_DEFAULT);
        $phone_source = ($req['student_age'] >= 18) ? 'self' : 'parent';
        
        $insertStudent = $pdo->prepare("
            INSERT INTO students 
            (name, category, birth_date, teacher_id, parent_phone, level, 
             username, password, enrollment_request_id, enrollment_date, phone_source,
             memorized_surahs, memorized_parts, total_memorized_parts, total_memorized_surahs)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        
        $insertStudent->execute([
            $req['student_name'],
            $req['student_category'],
            $req['birth_date'],
            $teacher_id,
            $contact_phone,
            $req['previous_quran_level'] ?: 'مبتدئ',
            $username,
            $password,
            $req['id'],
            $phone_source,
            json_encode($mem_surahs),
            json_encode($mem_parts),
            $total_parts,
            $total_surahs
        ]);
        
        $student_id = $pdo->lastInsertId();
        
        // 9. إذا تم اختيار حلقة، أضف الطالب إليها
        if ($ring_id > 0) {
            $checkRing = $pdo->prepare("SELECT id FROM ring_students WHERE ring_id = ? AND student_id = ?");
            $checkRing->execute([$ring_id, $student_id]);
            if (!$checkRing->fetch()) {
                $stmtRing = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
                $stmtRing->execute([$ring_id, $student_id]);
            }
        }
        
        // 10. تسجيل في السجل
        $log = $pdo->prepare("
            INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, ?, 'assigned', ?, 'admin', ?)
        ");
        $log->execute([
            $request_id,
            $req['status'],
            $_SESSION['user_id'],
            "تم توزيع الطالب على المعلم: " . getTeacherName($pdo, $teacher_id) . 
            ($ringInfo ? " | الحلقة: " . $ringInfo['name'] : "") .
            " | المحفوظات: {$total_parts} جزء, {$total_surahs} سورة"
        ]);
        
        // 11. إرسال إشعار واتساب مع معلومات الحلقة والمحفوظات
        if ($send_whatsapp && !empty($contact_phone)) {
            $phone = formatWhatsAppNumber($contact_phone);
            if ($phone) {
                $recipient_type = ($phone_source == 'self') ? 'الطالب' : 'ولي الأمر';
                $teacher_name = getTeacherName($pdo, $teacher_id);
                
                $message = "السلام عليكم ورحمة الله وبركاته\n";
                $message .= "تم قبول طلب الالتحاق الخاص {$recipient_type} بالطالب/ة: *" . $req['student_name'] . "*\n";
                $message .= "الفئة: " . getCategoryNameAr($req['student_category']) . "\n";
                
                if ($total_parts > 0 || $total_surahs > 0) {
                    $message .= "📖 *المحفوظات السابقة:*\n";
                    $message .= "┌─────────────────────\n";
                    if ($total_parts > 0) $message .= "│ 📚 الأجزاء: {$total_parts} جزء\n";
                    if ($total_surahs > 0) $message .= "│ 📖 السور: {$total_surahs} سورة\n";
                    $message .= "└─────────────────────\n\n";
                }
                
                $message .= "تم توزيع الطالب على المعلم: *" . $teacher_name . "*\n\n";
                
                if ($ringInfo) {
                    $message .= "📚 *معلومات الحلقة:*\n";
                    $message .= "┌─────────────────────\n";
                    $message .= "│ 🏷️ اسم الحلقة: " . $ringInfo['name'] . "\n";
                    if (!empty($ringInfo['schedule'])) {
                        $message .= "│ 🕐 مواعيد الحلقة:\n";
                        $schedules = explode('،', $ringInfo['schedule']);
                        foreach ($schedules as $sch) {
                            $message .= "│    • " . trim($sch) . "\n";
                        }
                    }
                    if (!empty($ringInfo['location'])) {
                        $message .= "│ 📍 المكان: " . $ringInfo['location'] . "\n";
                    }
                    $message .= "└─────────────────────\n\n";
                }
                
                $message .= "يمكنكم الآن متابعة تقدم الطالب عبر تطبيق دار التقوى.\n";
                $message .= "🔑 *بيانات الدخول:*\n";
                $message .= "┌─────────────────────\n";
                $message .= "│ 👤 اسم المستخدم: {$username}\n";
                $message .= "│ 🔒 كلمة المرور المؤقتة: 123456\n";
                $message .= "└─────────────────────\n\n";
                $message .= "📌 *نوصي بتغيير كلمة المرور بعد أول دخول.*\n\n";
                $message .= "مع تمنياتنا بالتوفيق.\n";
                $message .= "دار التقوى لتحفيظ القرآن الكريم";
                
                $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);
                $_SESSION['whatsapp_url'] = $whatsapp_url;
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "✅ تم توزيع الطالب بنجاح وإنشاء حسابه" . ($ringInfo ? " (الحلقة: {$ringInfo['name']})" : "");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: enrollment_requests.php?status=" . $filter);
    exit;
}

// ============================================
// معالجة تحديث الفئة يدوياً
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $request_id = (int)$_POST['request_id'];
    $new_category = $_POST['category'];
    $old_category = '';

    $stmt = $pdo->prepare("SELECT student_category FROM enrollment_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $old_category = $stmt->fetchColumn();

    if ($old_category && $old_category != $new_category) {
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare("UPDATE enrollment_requests SET student_category = ? WHERE id = ?");
            $update->execute([$new_category, $request_id]);
            
            $log = $pdo->prepare("
                INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes)
                VALUES (?, ?, ?, ?, 'admin', ?)
            ");
            $log->execute([
                $request_id, $old_category, $new_category, $_SESSION['user_id'],
                "تم تغيير الفئة من {$old_category} إلى {$new_category}"
            ]);
            $pdo->commit();
            $_SESSION['success'] = "✅ تم تحديث الفئة بنجاح";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "❌ خطأ في تحديث الفئة: " . $e->getMessage();
        }
    }
    header("Location: enrollment_requests.php?status=" . $filter);
    exit;
}

// ============================================
// معالجة رفض الطلب
// ============================================
if (isset($_GET['reject'])) {
    $request_id = (int)$_GET['reject'];
    $reason = isset($_GET['reason']) ? $_GET['reason'] : '';
    try {
        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE enrollment_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $update->execute([$reason, $request_id]);
        
        $log = $pdo->prepare("
            INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, ?, 'rejected', ?, 'admin', ?)
        ");
        $log->execute([$request_id, $filter, $_SESSION['user_id'], $reason ?: "تم رفض الطلب"]);
        $pdo->commit();
        $_SESSION['success'] = "✅ تم رفض الطلب";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: enrollment_requests.php?status=" . $filter);
    exit;
}

// ============================================
// معالجة إعادة تفعيل الطلب
// ============================================
if (isset($_GET['reactivate'])) {
    $request_id = (int)$_GET['reactivate'];
    try {
        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE enrollment_requests SET status = 'pending', rejection_reason = NULL WHERE id = ?");
        $update->execute([$request_id]);
        
        $log = $pdo->prepare("
            INSERT INTO enrollment_logs (request_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, 'rejected', 'pending', ?, 'admin', 'تم إعادة تفعيل الطلب')
        ");
        $log->execute([$request_id, $_SESSION['user_id']]);
        $pdo->commit();
        $_SESSION['success'] = "✅ تم إعادة تفعيل الطلب";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: enrollment_requests.php?status=pending");
    exit;
}

// ============================================
// إصلاح التوزيع المكرر
// ============================================
if (isset($_GET['fix_duplicate_assignment'])) {
    $student_name = isset($_GET['name']) ? $_GET['name'] : '';
    $student_phone = isset($_GET['phone']) ? $_GET['phone'] : '';
    
    if (empty($student_name) || empty($student_phone)) {
        $_SESSION['warning'] = "⚠️ لا توجد بيانات كافية للإصلاح";
    } else {
        $duplicates = $pdo->prepare("
            SELECT * FROM students 
            WHERE name = ? AND parent_phone = ?
            ORDER BY id
        ");
        $duplicates->execute([$student_name, $student_phone]);
        $students_list = $duplicates->fetchAll();
        
        if (count($students_list) > 1) {
            $keepId = $students_list[0]['id'];
            $toDelete = array_slice($students_list, 1);
            $deleted_count = 0;
            
            foreach ($toDelete as $student) {
                $pdo->prepare("UPDATE student_surah_progress SET student_id = ? WHERE student_id = ?")->execute([$keepId, $student['id']]);
                $pdo->prepare("UPDATE attendance SET person_id = ? WHERE person_type='student' AND person_id = ?")->execute([$keepId, $student['id']]);
                $pdo->prepare("UPDATE student_evaluations SET student_id = ? WHERE student_id = ?")->execute([$keepId, $student['id']]);
                $pdo->prepare("UPDATE student_achievements SET student_id = ? WHERE student_id = ?")->execute([$keepId, $student['id']]);
                $pdo->prepare("UPDATE student_monthly_goals SET student_id = ? WHERE student_id = ?")->execute([$keepId, $student['id']]);
                $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$student['id']]);
                $deleted_count++;
            }
            
            $_SESSION['success'] = "✅ تم إصلاح التوزيع المكرر. تم الاحتفاظ بالطالب وحذف $deleted_count سجل مكرر.";
        } else {
            $_SESSION['warning'] = "⚠️ لا توجد سجلات مكررة لهذا الطالب";
        }
    }
    
    header("Location: enrollment_requests.php?status=" . $filter);
    exit;
}

// ============================================
// جلب الطلبات
// ============================================
$query = "
    SELECT 
        r.*,
        t.name as teacher_name,
        at.name as assigned_teacher_name,
        (SELECT COUNT(*) FROM enrollment_logs WHERE request_id = r.id) as logs_count
    FROM enrollment_requests r
    LEFT JOIN teachers t ON r.preferred_teacher_id = t.id
    LEFT JOIN teachers at ON r.assigned_teacher_id = at.id
    WHERE 1=1
";
$params = [];

if ($filter != 'all') {
    $query .= " AND r.status = ?";
    $params[] = $filter;
}
if (!empty($search)) {
    $query .= " AND (r.student_name LIKE ? OR r.parent_name LIKE ? OR r.parent_phone LIKE ? OR r.request_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY 
    CASE r.status 
        WHEN 'pending' THEN 1 
        WHEN 'approved_by_teacher' THEN 2 
        WHEN 'assigned' THEN 3 
        WHEN 'rejected' THEN 4 
    END, 
    r.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// إحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM enrollment_requests")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'approved_by_teacher'")->fetchColumn(),
    'assigned' => $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'assigned'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'rejected'")->fetchColumn()
];

// جلب الطلاب المكررين
$duplicateStudents = $pdo->query("
    SELECT name, parent_phone, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM students
    GROUP BY name, parent_phone
    HAVING COUNT(*) > 1
")->fetchAll();

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
$warning_message = $_SESSION['warning'] ?? '';
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['warning'], $_SESSION['whatsapp_url']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>دار التقوى - <?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --primary-dark: #0a2a2c;
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
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --dark: #2c3e50;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --shadow-hover: 0 15px 40px rgba(0,0,0,0.15);
            --radius: 20px;
            --radius-sm: 12px;
            --radius-full: 9999px;
            --transition: 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            color: #2c3e50;
        }
        
        .enrollment-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ===== رأس الصفحة ===== */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
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
        
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 2;
        }
        
        .page-header h1 i {
            color: var(--secondary);
        }
        
        .header-link {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(5px);
            padding: 10px 25px;
            border-radius: 50px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 2;
        }
        
        .header-link:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-3px);
        }
        
        /* ===== بطاقات الإحصائيات ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--secondary-light));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        /* ===== شريط الفلترة ===== */
        .filter-bar {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 8px 20px;
            border-radius: 50px;
            background: #f8f9fa;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--secondary);
        }
        
        .filter-tab:hover:not(.active) {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            padding: 10px 20px;
            border: 2px solid var(--gray-light);
            border-radius: 50px;
            font-size: 0.9rem;
            width: 250px;
            transition: var(--transition);
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        .search-box button {
            padding: 10px 25px;
            background: var(--secondary);
            border: none;
            border-radius: 50px;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .search-box button:hover {
            background: var(--secondary-light);
            transform: translateY(-2px);
        }
        
        /* ===== شبكة الطلبات ===== */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 25px;
        }
        
        /* ===== بطاقة الطلب ===== */
        .request-card {
            background: white;
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .request-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .student-avatar {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: bold;
            border: 3px solid var(--secondary);
        }
        
        .student-details h3 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .student-details p {
            margin: 5px 0 0;
            color: var(--gray);
            font-size: 0.85rem;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .request-number {
            font-size: 0.7rem;
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 20px;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .contact-badge {
            background: #e7f3ff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
            margin-top: 5px;
        }
        
        /* ===== تنسيقات المحفوظات ===== */
        .memorized-section {
            background: #f8f9fa;
            border-radius: var(--radius-sm);
            padding: 12px;
            margin: 10px 0;
            border-right: 3px solid var(--secondary);
            position: relative;
        }
        
        .memorized-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .memorized-header i {
            color: var(--secondary);
        }
        
        .memorized-badge {
            display: inline-block;
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 5px;
            cursor: help;
        }
        
        .memorized-tooltip {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid var(--secondary);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            font-size: 0.8rem;
            z-index: 100;
            box-shadow: var(--shadow);
            max-width: 250px;
            bottom: 100%;
            right: 0;
            margin-bottom: 5px;
        }
        
        .memorized-section:hover .memorized-tooltip {
            display: block;
        }
        
        /* ===== الشارات ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-warning { background: var(--warning-light); color: #856404; }
        .badge-info { background: var(--info-light); color: #0c5460; }
        .badge-success { background: var(--success-light); color: #155724; }
        .badge-danger { background: var(--danger-light); color: #721c24; }
        
        /* ===== تفاصيل الطلب ===== */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--radius-sm);
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        
        .detail-item i {
            width: 25px;
            color: var(--secondary);
        }
        
        .detail-item .label {
            font-weight: 600;
            color: var(--gray);
        }
        
        .detail-item .value {
            color: var(--primary);
        }
        
        .notes-box {
            background: #f8f9fa;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin: 10px 0;
            border-right: 4px solid var(--secondary);
            font-size: 0.85rem;
        }
        
        /* ===== أزرار الإجراءات ===== */
        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .btn-info { background: var(--info); color: white; }
        .btn-warning { background: var(--warning); color: #212529; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
        
        /* ===== نافذة تحرير الفئة ===== */
        .category-selector {
            display: inline-flex;
            gap: 5px;
            align-items: center;
            margin-top: 5px;
        }
        
        .category-selector select {
            padding: 4px 8px;
            border-radius: 20px;
            border: 1px solid var(--gray-light);
            font-size: 0.8rem;
        }
        
        .category-selector button {
            background: var(--success);
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.7rem;
        }
        
        /* ===== رسائل التنبيه ===== */
        .alert {
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-light); color: #155724; }
        .alert-error { background: var(--danger-light); color: #721c24; }
        .alert-warning { background: var(--warning-light); color: #856404; }
        
        /* ===== إشعار واتساب ===== */
        .whatsapp-notice {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #25d366;
            color: white;
            padding: 15px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow);
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .whatsapp-notice a {
            color: white;
            font-weight: bold;
            text-decoration: none;
            background: rgba(0,0,0,0.2);
            padding: 5px 15px;
            border-radius: 30px;
        }
        
        /* ===== حالة فارغة ===== */
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: var(--radius);
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: var(--gray-light);
            margin-bottom: 20px;
        }
        
        /* ===== النوافذ المنبثقة ===== */
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
            border-radius: var(--radius);
            padding: 30px;
            width: 90%;
            max-width: 550px;
            animation: modalPop 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        @keyframes modalPop {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary);
        }
        
        .modal-header h3 {
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }
        
        .close-btn:hover {
            color: var(--danger);
            transform: rotate(90deg);
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
        
        .form-group label .required {
            color: var(--danger);
            margin-right: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .text-muted {
            color: var(--gray);
            font-size: 0.8rem;
        }
        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .requests-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .search-box input { width: 100%; }
            .details-grid { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.4rem; }
            .modal-content { padding: 20px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .student-avatar { width: 50px; height: 50px; font-size: 1.3rem; }
            .student-details h3 { font-size: 1rem; }
        }
    </style>
</head>
<body>
<div class="enrollment-page">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> طلبات الالتحاق</h1>
        <a href="enroll.php" class="header-link" target="_blank">
            <i class="fas fa-external-link-alt"></i> رابط التقديم
        </a>
    </div>

    <?php if ($whatsapp_url): ?>
    <div class="whatsapp-notice" id="whatsappNotice">
        <i class="fab fa-whatsapp fa-2x"></i>
        <div>تم إنشاء حساب الطالب. هل تريد إرسال إشعار واتساب؟</div>
        <a href="<?php echo $whatsapp_url; ?>" target="_blank">إرسال الآن</a>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:white; cursor:pointer;">✕</button>
    </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if ($warning_message): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $warning_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($duplicateStudents)): ?>
    <div class="alert alert-warning" style="background: #f8d7da; color: #721c24;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>⚠️ تنبيه: يوجد طلاب مكررون في النظام!</strong>
        <ul style="margin-top: 10px;">
            <?php foreach ($duplicateStudents as $dup): ?>
            <li>
                <?php echo htmlspecialchars($dup['name']); ?> (هاتف: <?php echo $dup['parent_phone']; ?>) - 
                مكرر <?php echo $dup['count']; ?> مرة
                <a href="?fix_duplicate_assignment=1&name=<?php echo urlencode($dup['name']); ?>&phone=<?php echo urlencode($dup['parent_phone']); ?>&status=<?php echo $filter; ?>" 
                   class="btn btn-sm btn-warning" 
                   onclick="return confirm('إصلاح التكرار؟ سيتم الاحتفاظ بسجل واحد وحذف الباقي')">
                    <i class="fas fa-magic"></i> إصلاح
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-label">إجمالي الطلبات</div></div>
        <div class="stat-card"><div class="stat-number" style="color: var(--warning);"><?php echo $stats['pending']; ?></div><div class="stat-label">قيد الانتظار</div></div>
        <div class="stat-card"><div class="stat-number" style="color: var(--info);"><?php echo $stats['approved']; ?></div><div class="stat-label">موافقة معلم</div></div>
        <div class="stat-card"><div class="stat-number" style="color: var(--success);"><?php echo $stats['assigned']; ?></div><div class="stat-label">تم التوزيع</div></div>
        <div class="stat-card"><div class="stat-number" style="color: var(--danger);"><?php echo $stats['rejected']; ?></div><div class="stat-label">مرفوض</div></div>
    </div>

    <div class="filter-bar">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">الكل</a>
            <a href="?status=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">قيد الانتظار</a>
            <a href="?status=approved_by_teacher" class="filter-tab <?php echo $filter == 'approved_by_teacher' ? 'active' : ''; ?>">موافقة معلم</a>
            <a href="?status=assigned" class="filter-tab <?php echo $filter == 'assigned' ? 'active' : ''; ?>">تم التوزيع</a>
            <a href="?status=rejected" class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>">مرفوض</a>
        </div>
        <div class="search-box">
            <form method="get">
                <input type="hidden" name="status" value="<?php echo $filter; ?>">
                <input type="text" name="search" placeholder="بحث بالاسم أو رقم الهاتف..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> بحث</button>
            </form>
        </div>
    </div>

    <?php if (empty($requests)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><h3>لا توجد طلبات</h3></div>
    <?php else: ?>
        <div class="requests-grid">
            <?php foreach ($requests as $req): 
                $contact_phone = determineContactPhone($req);
                $contact_source = ($req['student_age'] >= 18) ? 'self' : 'parent';
                $mem_surahs = getMemorizedData($req, 'memorized_surahs');
                $mem_parts = getMemorizedData($req, 'memorized_parts');
                $has_memorized = (!empty($mem_surahs) || !empty($mem_parts));
                $total_parts = count($mem_parts);
                $total_surahs = count($mem_surahs);
            ?>
                <div class="request-card">
                    <div class="card-header">
                        <div class="student-info">
                            <div class="student-avatar"><?php echo mb_substr($req['student_name'], 0, 1, 'UTF-8'); ?></div>
                            <div class="student-details">
                                <h3><?php echo htmlspecialchars($req['student_name']); ?></h3>
                                <p>
                                    <?php echo $req['student_gender'] == 'male' ? 'ذكر' : 'أنثى'; ?> | العمر: <?php echo $req['student_age']; ?> سنة
                                    <span class="badge badge-info"><?php echo getCategoryNameAr($req['student_category']); ?></span>
                                </p>
                                <div class="contact-badge">
                                    <i class="fas fa-phone"></i> التواصل: <?php echo $contact_phone; ?>
                                    <small>(<?php echo $contact_source == 'self' ? 'رقم الطالب' : 'رقم ولي الأمر'; ?>)</small>
                                </div>
                                <div class="request-number">رقم الطلب: <?php echo $req['request_number'] ?? '-'; ?></div>
                            </div>
                        </div>
                        <div><?php echo getStatusBadge($req['status']); ?></div>
                    </div>

                    <?php if ($has_memorized): ?>
                    <div class="memorized-section">
                        <div class="memorized-header">
                            <i class="fas fa-quran"></i>
                            <span>المحفوظات السابقة</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php if (!empty($mem_parts)): ?>
                                <span class="memorized-badge" title="الأجزاء المحفوظة: <?php echo implode('، ', $mem_parts); ?>">
                                    <?php echo $total_parts; ?> جزء
                                </span>
                                <span class="memorized-tooltip">
                                    الأجزاء: <?php echo implode('، ', array_slice($mem_parts, 0, 10)); ?>
                                    <?php if ($total_parts > 10) echo ' ... و' . ($total_parts - 10) . ' أخرى'; ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($mem_surahs)): ?>
                                <span class="memorized-badge" title="السور المحفوظة: <?php echo implode('، ', array_map('getSurahName', array_slice($mem_surahs, 0, 10))); ?>">
                                    <?php echo $total_surahs; ?> سورة
                                </span>
                                <span class="memorized-tooltip">
                                    السور: 
                                    <?php 
                                    $surah_names = array_slice($mem_surahs, 0, 8);
                                    $names = [];
                                    foreach ($surah_names as $num) {
                                        $names[] = getSurahName($num);
                                    }
                                    echo implode('، ', $names);
                                    if ($total_surahs > 8) echo ' ... و' . ($total_surahs - 8) . ' أخرى';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="memorized-section" style="border-right-color: var(--gray);">
                        <div class="memorized-header">
                            <i class="fas fa-quran"></i>
                            <span>المحفوظات</span>
                        </div>
                        <span class="text-muted">لا يوجد محفوظات سابقة (مبتدئ)</span>
                    </div>
                    <?php endif; ?>

                    <div class="details-grid">
                        <div class="detail-item"><i class="fas fa-user-tie"></i><span class="label">ولي الأمر:</span><span class="value"><?php echo htmlspecialchars($req['parent_name']); ?></span></div>
                        <div class="detail-item"><i class="fas fa-phone"></i><span class="label">هاتف ولي الأمر:</span><span class="value" dir="ltr"><?php echo htmlspecialchars($req['parent_phone']); ?></span></div>
                        <?php if ($req['student_phone']): ?>
                        <div class="detail-item"><i class="fas fa-mobile-alt"></i><span class="label">هاتف الطالب:</span><span class="value" dir="ltr"><?php echo htmlspecialchars($req['student_phone']); ?></span></div>
                        <?php endif; ?>
                        <?php if ($req['previous_quran_level']): ?>
                        <div class="detail-item"><i class="fas fa-level-up-alt"></i><span class="label">المستوى:</span><span class="value"><?php echo htmlspecialchars($req['previous_quran_level']); ?></span></div>
                        <?php endif; ?>
                        <?php if ($req['preferred_time']): ?>
                        <div class="detail-item"><i class="fas fa-clock"></i><span class="label">الوقت المفضل:</span><span class="value"><?php echo $req['preferred_time']; ?></span></div>
                        <?php endif; ?>
                        <?php if ($req['assigned_teacher_name']): ?>
                        <div class="detail-item"><i class="fas fa-user-check"></i><span class="label">المعلم المسند:</span><span class="value"><?php echo htmlspecialchars($req['assigned_teacher_name']); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($req['notes']): ?>
                    <div class="notes-box"><i class="fas fa-sticky-note"></i> <?php echo nl2br(htmlspecialchars($req['notes'])); ?></div>
                    <?php endif; ?>

                    <div class="request-actions">
                        <?php if ($req['status'] == 'pending'): ?>
                            <a href="edit_enrollment.php?id=<?php echo $req['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i> تعديل الطلب
                            </a>
                            <button class="btn btn-success btn-sm" onclick="openApproveModal(<?php echo $req['id']; ?>, '<?php echo addslashes($req['student_name']); ?>')">
                                <i class="fas fa-check"></i> موافقة مبدئية
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?php echo $req['id']; ?>, '<?php echo addslashes($req['student_name']); ?>')">
                                <i class="fas fa-times"></i> رفض
                            </button>
                        <?php elseif ($req['status'] == 'approved_by_teacher'): ?>
                            <button class="btn btn-primary btn-sm" onclick="openAssignModal(<?php echo $req['id']; ?>, '<?php echo addslashes($req['student_name']); ?>')">
                                <i class="fas fa-user-plus"></i> توزيع على معلم
                            </button>
                        <?php elseif ($req['status'] == 'assigned'): ?>
                            <?php 
                                $stmt = $pdo->prepare("SELECT id FROM students WHERE enrollment_request_id = ?");
                                $stmt->execute([$req['id']]);
                                $student_id = $stmt->fetchColumn();
                            ?>
                            <a href="view_progress.php?student_id=<?php echo $student_id; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> عرض الطالب
                            </a>
                        <?php elseif ($req['status'] == 'rejected'): ?>
                            <a href="?reactivate=<?php echo $req['id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('هل تريد إعادة تفعيل هذا الطلب؟')">
                                <i class="fas fa-redo"></i> إعادة تفعيل
                            </a>
                        <?php endif; ?>
                        <a href="?status=<?php echo $filter; ?>&view_logs=<?php echo $req['id']; ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-history"></i> سجل التغييرات
                        </a>
                    </div>

                    <!-- نموذج تحرير الفئة (مخفي) -->
                    <div id="categoryForm_<?php echo $req['id']; ?>" style="display: none; margin-top: 15px;">
                        <form method="post" class="category-selector">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <select name="category">
                                <option value="boy" <?php echo $req['student_category'] == 'boy' ? 'selected' : ''; ?>>أولاد</option>
                                <option value="girl" <?php echo $req['student_category'] == 'girl' ? 'selected' : ''; ?>>بنات</option>
                                <option value="child" <?php echo $req['student_category'] == 'child' ? 'selected' : ''; ?>>أطفال</option>
                                <option value="woman" <?php echo $req['student_category'] == 'woman' ? 'selected' : ''; ?>>نساء</option>
                            </select>
                            <button type="submit" name="update_category">حفظ</button>
                            <button type="button" onclick="hideCategoryEdit(<?php echo $req['id']; ?>)">إلغاء</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<!-- ============================================ -->
<!-- النوافذ المنبثقة -->
<!-- ============================================ -->

<!-- نافذة الموافقة المبدئية -->
<div class="modal" id="approveModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> موافقة مبدئية</h3>
            <button class="close-btn" onclick="closeModal('approveModal')">&times;</button>
        </div>
        <form method="post" action="approve_enrollment.php">
            <input type="hidden" name="request_id" id="approveRequestId">
            <div id="approveStudentName" style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 12px; text-align: center; font-weight: bold; color: #1e3c3f;"></div>
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات إضافية..."></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" onclick="closeModal('approveModal')" style="flex:1; background:#6c757d; color:white;">إلغاء</button>
                <button type="submit" name="approve" class="btn btn-success" style="flex:2;">تأكيد الموافقة</button>
            </div>
        </form>
    </div>
</div>

<!-- نافذة رفض الطلب -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color: #dc3545;"></i> رفض الطلب</h3>
            <button class="close-btn" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="get">
            <input type="hidden" name="reject" id="rejectRequestId">
            <input type="hidden" name="status" value="<?php echo $filter; ?>">
            <div id="rejectStudentName" style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 12px; text-align: center; font-weight: bold; color: #1e3c3f;"></div>
            <div class="form-group">
                <label><i class="fas fa-ban"></i> سبب الرفض (اختياري)</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="سبب الرفض..."></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" onclick="closeModal('rejectModal')" style="flex:1; background:#6c757d; color:white;">إلغاء</button>
                <button type="submit" class="btn btn-danger" style="flex:2;">تأكيد الرفض</button>
            </div>
        </form>
    </div>
</div>

<!-- نافذة توزيع الطالب (محدثة) -->
<div class="modal" id="assignModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> توزيع الطالب على معلم</h3>
            <button class="close-btn" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <form method="get" id="assignForm">
            <input type="hidden" name="assign" id="assignRequestId">
            <input type="hidden" name="status" value="<?php echo $filter; ?>">
            
            <div id="assignStudentName" style="margin-bottom: 20px; padding: 12px; background: linear-gradient(135deg, #f8f9fa, #fff); border-radius: 12px; text-align: center; font-weight: bold; color: #1e3c3f; border-right: 4px solid #c9a96b;">
                <i class="fas fa-user-graduate"></i> <span id="assignStudentNameText"></span>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-chalkboard-teacher"></i> اختر المعلم <span class="required">*</span></label>
                <select name="teacher_id" id="assignTeacherId" class="form-control" required onchange="loadTeacherRings(this.value)">
                    <option value="">-- اختر معلم --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?> (<?php echo $t['gender'] == 'male' ? 'معلم' : 'معلمة'; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- حقل اختيار الحلقة مع تحميل متقدم -->
            <div class="form-group" id="ringSelectContainer" style="display: none;">
                <label><i class="fas fa-ring"></i> اختر الحلقة (اختياري)</label>
                <select name="ring_id" id="ringSelect" class="form-control" onchange="updateRingInfo(this)">
                    <option value="">-- بدون حلقة --</option>
                </select>
                <div id="ringLoading" style="display: none; margin-top: 5px;">
                    <i class="fas fa-spinner fa-spin"></i> جاري تحميل الحلقات...
                </div>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> سيتم إضافة الطالب إلى الحلقة المختارة وسيتم إرسال معلومات الحلقة في الإشعار
                </small>
            </div>
            
            <!-- عرض معلومات الحلقة المختارة -->
            <div id="ringInfoDisplay" style="display: none; background: #e8f5e9; padding: 15px; border-radius: 12px; margin-top: 10px; border-right: 4px solid #28a745;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <i class="fas fa-ring" style="color: #28a745;"></i>
                    <strong style="color: #155724;">معلومات الحلقة:</strong>
                </div>
                <div id="ringInfoContent" style="font-size: 0.9rem; color: #155724; line-height: 1.6;"></div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="send_whatsapp" value="1" checked>
                    <i class="fab fa-whatsapp"></i> إرسال إشعار واتساب لولي الأمر
                </label>
            </div>
            
            <div id="whatsappPreview" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 12px; margin-top: 10px; border-right: 4px solid #25d366;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <i class="fab fa-whatsapp" style="color: #25d366; font-size: 1.2rem;"></i>
                    <strong>معاينة رسالة واتساب:</strong>
                </div>
                <div id="whatsappPreviewContent" style="font-size: 0.85rem; color: #333; max-height: 200px; overflow-y: auto; white-space: pre-wrap; background: white; padding: 12px; border-radius: 8px; font-family: monospace;"></div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn" onclick="closeModal('assignModal')" style="flex:1; background:#6c757d; color:white; padding: 12px;">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" class="btn btn-primary" style="flex:2; padding: 12px;">
                    <i class="fas fa-check-circle"></i> تأكيد التوزيع
                </button>
            </div>
        </form>
    </div>
</div>
<script>
// ============================================
// دوال تحرير الفئة
// ============================================
function showCategoryEdit(requestId) {
    document.getElementById('categoryForm_' + requestId).style.display = 'block';
}
function hideCategoryEdit(requestId) {
    document.getElementById('categoryForm_' + requestId).style.display = 'none';
}

// ============================================
// دوال النوافذ المنبثقة الأساسية
// ============================================
function openApproveModal(id, name) {
    document.getElementById('approveRequestId').value = id;
    document.getElementById('approveStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + name;
    document.getElementById('approveModal').classList.add('show');
}

function openRejectModal(id, name) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('rejectStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + name;
    document.getElementById('rejectModal').classList.add('show');
}

function openAssignModal(id, name) {
    document.getElementById('assignRequestId').value = id;
    document.getElementById('assignStudentNameText').innerHTML = name;
    document.getElementById('assignStudentName').style.display = 'block';
    document.getElementById('assignModal').classList.add('show');
    
    // إعادة تعيين الحقول
    document.getElementById('assignTeacherId').value = '';
    document.getElementById('ringSelectContainer').style.display = 'none';
    document.getElementById('ringInfoPreview').style.display = 'none';
    document.getElementById('whatsappPreview').style.display = 'none';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// ============================================
// دوال تحميل حلقات المعلم ومعاينة رسالة واتساب
// ============================================
// ============================================
// دوال تحميل حلقات المعلم ومعاينة رسالة واتساب (محسنة)
// ============================================

// متغير لتخزين بيانات الحلقات
let ringsData = {};

function loadTeacherRings(teacherId) {
    if (!teacherId) {
        document.getElementById('ringSelectContainer').style.display = 'none';
        document.getElementById('ringInfoDisplay').style.display = 'none';
        document.getElementById('whatsappPreview').style.display = 'none';
        return;
    }
    
    const ringSelect = document.getElementById('ringSelect');
    const ringLoading = document.getElementById('ringLoading');
    const ringSelectContainer = document.getElementById('ringSelectContainer');
    
    // إظهار مؤشر التحميل
    ringLoading.style.display = 'block';
    ringSelectContainer.style.display = 'block';
    ringSelect.innerHTML = '<option value="">-- جاري التحميل --</option>';
    ringSelect.disabled = true;
    
    fetch(`get_teacher_rings.php?teacher_id=${teacherId}`)
        .then(response => response.json())
        .then(data => {
            ringLoading.style.display = 'none';
            ringSelect.disabled = false;
            
            if (data.error) {
                console.error('Error:', data.error);
                ringSelect.innerHTML = '<option value="">-- لا توجد حلقات --</option>';
                return;
            }
            
            const rings = data.rings || [];
            ringsData[teacherId] = rings;
            
            if (rings.length > 0) {
                let options = '<option value="">-- بدون حلقة --</option>';
                rings.forEach(ring => {
                    let schedulePreview = ring.schedule_text ? ` (${ring.schedule_text.substring(0, 50)}${ring.schedule_text.length > 50 ? '...' : ''})` : '';
                    options += `<option value="${ring.id}" data-name="${ring.name}" data-schedule="${ring.schedule_text || ''}" data-location="${ring.location || ''}">${ring.name}${schedulePreview}</option>`;
                });
                ringSelect.innerHTML = options;
                ringSelectContainer.style.display = 'block';
            } else {
                ringSelect.innerHTML = '<option value="">-- لا توجد حلقات لهذا المعلم --</option>';
                ringSelectContainer.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ringLoading.style.display = 'none';
            ringSelect.disabled = false;
            ringSelect.innerHTML = '<option value="">-- حدث خطأ في التحميل --</option>';
            ringSelectContainer.style.display = 'block';
        });
}

function updateRingInfo(select) {
    const selectedOption = select.options[select.selectedIndex];
    const ringInfoDisplay = document.getElementById('ringInfoDisplay');
    const ringInfoContent = document.getElementById('ringInfoContent');
    
    if (select.value && selectedOption.value) {
        const ringName = selectedOption.getAttribute('data-name') || selectedOption.text.split('(')[0];
        const schedule = selectedOption.getAttribute('data-schedule') || '';
        const location = selectedOption.getAttribute('data-location') || '';
        
        let html = `<strong><i class="fas fa-ring"></i> ${ringName}</strong><br>`;
        
        if (schedule) {
            html += `<div style="margin-top: 8px;"><i class="fas fa-clock"></i> <strong>مواعيد الحلقة:</strong><br>`;
            const scheduleLines = schedule.split('،');
            scheduleLines.forEach(line => {
                html += `&nbsp;&nbsp;• ${line.trim()}<br>`;
            });
            html += `</div>`;
        } else {
            html += `<div style="margin-top: 8px;"><i class="fas fa-clock"></i> <strong>المواعيد:</strong> لم يتم تحديد مواعيد بعد</div>`;
        }
        
        if (location) {
            html += `<div style="margin-top: 8px;"><i class="fas fa-map-marker-alt"></i> <strong>المكان:</strong> ${location}</div>`;
        }
        
        ringInfoContent.innerHTML = html;
        ringInfoDisplay.style.display = 'block';
        
        // تحديث معاينة واتساب بعد اختيار الحلقة
        updateWhatsAppPreview();
    } else {
        ringInfoDisplay.style.display = 'none';
        updateWhatsAppPreview();
    }
}

function updateWhatsAppPreview() {
    const teacherSelect = document.getElementById('assignTeacherId');
    const ringSelect = document.getElementById('ringSelect');
    const studentName = document.getElementById('assignStudentNameText').innerText;
    const previewDiv = document.getElementById('whatsappPreview');
    const previewContent = document.getElementById('whatsappPreviewContent');
    
    if (!teacherSelect.value) {
        previewDiv.style.display = 'none';
        return;
    }
    
    const teacherName = teacherSelect.options[teacherSelect.selectedIndex].text;
    const teacherId = teacherSelect.value;
    
    let message = `السلام عليكم ورحمة الله وبركاته\n`;
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    message += `📋 *تم قبول طلب الالتحاق*\n`;
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    message += `👤 *الطالب/ة:* ${studentName}\n`;
    message += `👨‍🏫 *المعلم:* ${teacherName}\n\n`;
    
    // إذا تم اختيار حلقة
    if (ringSelect.value) {
        const selectedOption = ringSelect.options[ringSelect.selectedIndex];
        const ringName = selectedOption.getAttribute('data-name') || selectedOption.text.split('(')[0];
        const schedule = selectedOption.getAttribute('data-schedule') || '';
        const location = selectedOption.getAttribute('data-location') || '';
        
        message += `📚 *معلومات الحلقة:*\n`;
        message += `┌─────────────────────────────────\n`;
        message += `│ 🏷️ *اسم الحلقة:* ${ringName}\n`;
        
        if (schedule) {
            message += `│ \n`;
            message += `│ 🕐 *مواعيد الحلقة:*\n`;
            const scheduleLines = schedule.split('،');
            scheduleLines.forEach(line => {
                message += `│    • ${line.trim()}\n`;
            });
        } else {
            message += `│ 🕐 *المواعيد:* سيتم تحديدها لاحقاً\n`;
        }
        
        if (location) {
            message += `│ \n`;
            message += `│ 📍 *المكان:* ${location}\n`;
        }
        message += `└─────────────────────────────────\n\n`;
    }
    
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    message += `📱 يمكنكم متابعة تقدم الطالب عبر تطبيق دار التقوى\n`;
    message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    message += `جزاكم الله خيراً على متابعتكم.\n`;
    message += `دار التقوى لتحفيظ القرآن الكريم`;
    
    previewContent.innerHTML = message.replace(/\n/g, '<br>');
    previewDiv.style.display = 'block';
}

// إضافة مستمع لتحديث معاينة واتساب عند تغيير المعلم
document.getElementById('assignTeacherId')?.addEventListener('change', function() {
    // إعادة تعيين الحلقة عند تغيير المعلم
    const ringSelect = document.getElementById('ringSelect');
    if (ringSelect) ringSelect.value = '';
    document.getElementById('ringInfoDisplay').style.display = 'none';
    updateWhatsAppPreview();
});

// إضافة مستمع لتحديث معاينة واتساب عند تغيير الحلقة
document.getElementById('ringSelect')?.addEventListener('change', updateWhatsAppPreview);
</script>

<?php
ob_end_flush();
require_once 'includes/footer.php';
?>