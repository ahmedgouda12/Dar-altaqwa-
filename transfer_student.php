<?php
// ============================================
// ملف: transfer_student.php
// نقل الطالب بين المعلمين - النسخة النهائية
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'نقل طالب';

// جلب معرف الطالب من الرابط
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0);
$selected_teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : (isset($_POST['new_teacher_id']) ? (int)$_POST['new_teacher_id'] : 0);

$error = '';
$success_message_display = '';
$student = null;
$available_teachers = [];
$selected_teacher_rings = [];
$current_ring = null;
$transfer_data = null;

// ============================================
// جلب معلومات الطالب
// ============================================
if ($student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as current_teacher_name, t.gender as current_teacher_gender, t.phone as current_teacher_phone
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $error = "❌ الطالب غير موجود";
    } else {
        if (isTeacher() && $student['teacher_id'] != $_SESSION['user_id']) {
            $error = "❌ لا يمكنك نقل طالب ليس من طلابك";
        } else {
            $current_gender = $student['current_teacher_gender'] ?: (($student['category'] == 'boy') ? 'male' : 'female');
            $teachers_query = "SELECT id, name, gender, phone FROM teachers WHERE can_login = 1 AND gender = ? AND id != ? ORDER BY name";
            $stmt = $pdo->prepare($teachers_query);
            $stmt->execute([$current_gender, $student['teacher_id'] ?: 0]);
            $available_teachers = $stmt->fetchAll();
            
            $ring_stmt = $pdo->prepare("
                SELECT r.id, r.name
                FROM ring_students rs
                JOIN rings r ON rs.ring_id = r.id
                WHERE rs.student_id = ?
                LIMIT 1
            ");
            $ring_stmt->execute([$student_id]);
            $current_ring = $ring_stmt->fetch();
        }
    }
}

// ============================================
// جلب حلقات المعلم المختار
// ============================================
if ($selected_teacher_id > 0) {
    $rings_stmt = $pdo->prepare("SELECT id, name FROM rings WHERE teacher_id = ? ORDER BY name");
    $rings_stmt->execute([$selected_teacher_id]);
    $selected_teacher_rings = $rings_stmt->fetchAll();
}

// ============================================
// دالة تنسيق رقم الواتساب
// ============================================
function formatPhoneForWhatsApp($phone) {
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '20' . substr($phone, 1);
    } elseif (strlen($phone) == 10) {
        $phone = '20' . $phone;
    }
    return $phone;
}

// ============================================
// معالجة تأكيد النقل (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_transfer']) && $student) {
    $new_teacher_id = (int)$_POST['new_teacher_id'];
    $transfer_with_ring = isset($_POST['transfer_with_ring']);
    $new_ring_id = (int)($_POST['new_ring_id'] ?? 0);
    $transfer_reason = trim($_POST['transfer_reason'] ?? '');
    
    if ($new_teacher_id <= 0) {
        $error = "❌ يرجى اختيار المعلم الجديد";
    } else {
        $new_teacher = $pdo->prepare("SELECT id, name, gender, phone FROM teachers WHERE id = ? AND can_login = 1");
        $new_teacher->execute([$new_teacher_id]);
        $new_teacher_data = $new_teacher->fetch();
        
        if (!$new_teacher_data) {
            $error = "❌ المعلم المحدد غير موجود";
        } elseif (!isAdmin() && $new_teacher_data['gender'] != $student['current_teacher_gender']) {
            $error = "❌ لا يمكن نقل الطالب إلى معلم من جنس مختلف";
        } else {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS transfer_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        student_id INT NOT NULL,
                        old_teacher_id INT NOT NULL,
                        new_teacher_id INT NOT NULL,
                        old_ring_id INT NULL,
                        new_ring_id INT NULL,
                        transfer_reason TEXT,
                        transferred_by INT NOT NULL,
                        transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                $pdo->beginTransaction();
                
                $update = $pdo->prepare("UPDATE students SET teacher_id = ? WHERE id = ?");
                $update->execute([$new_teacher_id, $student_id]);
                
                if ($transfer_with_ring && $new_ring_id > 0) {
                    $pdo->prepare("DELETE FROM ring_students WHERE student_id = ?")->execute([$student_id]);
                    $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)")->execute([$new_ring_id, $student_id]);
                } elseif (!$transfer_with_ring && $current_ring) {
                    $pdo->prepare("DELETE FROM ring_students WHERE student_id = ?")->execute([$student_id]);
                }
                
                $log = $pdo->prepare("
                    INSERT INTO transfer_logs 
                    (student_id, old_teacher_id, new_teacher_id, old_ring_id, new_ring_id, transfer_reason, transferred_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $log->execute([
                    $student_id,
                    $student['teacher_id'] ?: 0,
                    $new_teacher_id,
                    $current_ring['id'] ?? null,
                    ($transfer_with_ring && $new_ring_id > 0) ? $new_ring_id : null,
                    $transfer_reason,
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                
                // تخزين بيانات النقل لعرضها في نفس الصفحة
                $transfer_data = [
                    'success' => true,
                    'student_name' => $student['name'],
                    'new_teacher_name' => $new_teacher_data['name'],
                    'new_teacher_phone' => $new_teacher_data['phone'],
                    'parent_phone' => $student['parent_phone'],
                    'reason' => $transfer_reason,
                    'old_teacher_name' => $student['current_teacher_name'],
                    'transfer_with_ring' => $transfer_with_ring,
                    'new_ring_name' => $new_ring_id > 0 ? getRingName($pdo, $new_ring_id) : ''
                ];
                
                $success_message_display = "✅ تم نقل الطالب بنجاح إلى المعلم {$new_teacher_data['name']}";
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "❌ خطأ: " . $e->getMessage();
            }
        }
    }
}

// دالة مساعدة لجلب اسم الحلقة
function getRingName($pdo, $ring_id) {
    $stmt = $pdo->prepare("SELECT name FROM rings WHERE id = ?");
    $stmt->execute([$ring_id]);
    return $stmt->fetchColumn();
}

ob_clean();
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --primary: #1e3c3f; --primary-light: #2a5f5a; --secondary: #c9a96b; --success: #28a745; --danger: #dc3545; --whatsapp: #25d366; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 40px 20px; }
        .transfer-container { max-width: 800px; margin: 0 auto; }
        .transfer-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .transfer-header { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 30px; text-align: center; }
        .transfer-header h1 { font-size: 1.8rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .transfer-header h1 i { color: var(--secondary); }
        .transfer-body { padding: 30px; }
        .student-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; border-right: 4px solid var(--secondary); }
        .student-avatar { width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; border: 3px solid var(--secondary); }
        .student-info h3 { color: var(--primary); font-size: 1.3rem; margin-bottom: 5px; }
        .student-info p { color: #666; font-size: 0.9rem; display: flex; gap: 10px; flex-wrap: wrap; }
        .current-ring { background: #e8f5e9; padding: 8px 15px; border-radius: 10px; margin-top: 10px; border-right: 3px solid var(--success); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--primary); }
        .form-group label i { color: var(--secondary); margin-left: 5px; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 12px; margin-bottom: 15px; }
        .rings-list { margin-top: 10px; border: 1px solid #e9ecef; border-radius: 12px; max-height: 200px; overflow-y: auto; }
        .ring-option { display: flex; align-items: center; gap: 10px; padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; }
        .ring-option:hover { background: #f8f9fa; }
        .ring-option.selected { background: #e8f5e9; border-right: 3px solid var(--success); }
        .ring-option input { margin: 0; width: 18px; height: 18px; }
        .action-buttons { display: flex; gap: 15px; margin-top: 25px; }
        .btn { flex: 1; padding: 12px; border-radius: 40px; border: none; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-whatsapp { background: var(--whatsapp); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid var(--success); }
        .alert-error { background: #f8d7da; color: #721c24; border-right: 5px solid var(--danger); }
        .alert-whatsapp { background: #e8f5e9; border-right: 5px solid var(--whatsapp); padding: 20px; border-radius: 15px; margin-bottom: 20px; }
        .whatsapp-buttons { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px; }
        .whatsapp-link { background: var(--whatsapp); color: white; padding: 12px 25px; border-radius: 40px; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; font-weight: 700; transition: 0.3s; }
        .whatsapp-link:hover { transform: translateY(-3px); filter: brightness(1.05); }
        .info-note { background: #e7f3ff; padding: 12px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #0c5460; }
        @media (max-width: 768px) { 
            .student-card { flex-direction: column; text-align: center; } 
            .action-buttons { flex-direction: column; } 
            .whatsapp-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="transfer-container">
    <div class="transfer-card">
        <div class="transfer-header">
            <h1><i class="fas fa-exchange-alt"></i> نقل طالب</h1>
            <p>نقل الطالب إلى معلم آخر مع إمكانية نقله إلى حلقة جديدة</p>
        </div>
        
        <div class="transfer-body">
            <!-- رسالة الخطأ -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- رسالة النجاح -->
            <?php if ($success_message_display): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo $success_message_display; ?>
                </div>
            <?php endif; ?>

            <!-- أزرار واتساب بعد نجاح النقل -->
            <?php if ($transfer_data && $transfer_data['success']): ?>
            <div class="alert-whatsapp">
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                    <i class="fab fa-whatsapp" style="font-size: 2rem; color: var(--whatsapp);"></i>
                    <div style="flex: 1;">
                        <strong>✅ تم نقل الطالب "<?php echo htmlspecialchars($transfer_data['student_name']); ?>" بنجاح!</strong>
                        <br>يمكنك الآن إرسال إشعارات واتساب:
                    </div>
                </div>
                <div class="whatsapp-buttons">
                    <?php 
                    $teacher_phone = formatPhoneForWhatsApp($transfer_data['new_teacher_phone']);
                    if ($teacher_phone):
                        $msg_teacher = "السلام عليكم ورحمة الله وبركاته\n";
                        $msg_teacher .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $msg_teacher .= "📋 *إشعار نقل طالب إليك*\n";
                        $msg_teacher .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $msg_teacher .= "👤 *الطالب/ة:* {$transfer_data['student_name']}\n";
                        $msg_teacher .= "👨‍🏫 *المعلم السابق:* {$transfer_data['old_teacher_name']}\n";
                        if ($transfer_data['transfer_with_ring'] && $transfer_data['new_ring_name']) {
                            $msg_teacher .= "🔄 *الحلقة الجديدة:* {$transfer_data['new_ring_name']}\n";
                        }
                        if ($transfer_data['reason']) {
                            $msg_teacher .= "📝 *سبب النقل:* {$transfer_data['reason']}\n";
                        }
                        $msg_teacher .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $msg_teacher .= "دار التقوى لتحفيظ القرآن الكريم";
                        $whatsapp_url_teacher = "https://wa.me/{$teacher_phone}?text=" . urlencode($msg_teacher);
                    ?>
                        <a href="<?php echo $whatsapp_url_teacher; ?>" target="_blank" class="whatsapp-link">
                            <i class="fab fa-whatsapp"></i> 📱 إشعار للمعلم الجديد (<?php echo htmlspecialchars($transfer_data['new_teacher_name']); ?>)
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $parent_phone = formatPhoneForWhatsApp($transfer_data['parent_phone']);
                    if ($parent_phone):
                        $msg_parent = "السلام عليكم ورحمة الله وبركاته\n";
                        $msg_parent .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $msg_parent .= "📋 *إشعار نقل طالب*\n";
                        $msg_parent .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $msg_parent .= "👤 *الطالب/ة:* {$transfer_data['student_name']}\n";
                        $msg_parent .= "👨‍🏫 *المعلم الجديد:* {$transfer_data['new_teacher_name']}\n";
                        if ($transfer_data['transfer_with_ring'] && $transfer_data['new_ring_name']) {
                            $msg_parent .= "🔄 *الحلقة الجديدة:* {$transfer_data['new_ring_name']}\n";
                        }
                        if ($transfer_data['reason']) {
                            $msg_parent .= "📝 *سبب النقل:* {$transfer_data['reason']}\n";
                        }
                        $msg_parent .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $msg_parent .= "دار التقوى لتحفيظ القرآن الكريم";
                        $whatsapp_url_parent = "https://wa.me/{$parent_phone}?text=" . urlencode($msg_parent);
                    ?>
                        <a href="<?php echo $whatsapp_url_parent; ?>" target="_blank" class="whatsapp-link">
                            <i class="fab fa-whatsapp"></i> 📱 إشعار لولي الأمر
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($student && !$transfer_data): ?>
            <div class="student-card">
                <div class="student-avatar"><?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?></div>
                <div class="student-info">
                    <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                    <p><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student['current_teacher_name'] ?? 'غير محدد'); ?> | <i class="fas fa-phone"></i> <?php echo $student['parent_phone'] ?: 'لا يوجد'; ?></p>
                    <?php if ($current_ring): ?>
                        <div class="current-ring"><i class="fas fa-ring"></i> الحلقة الحالية: <?php echo htmlspecialchars($current_ring['name']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-note"><i class="fas fa-info-circle"></i> اختر المعلم الجديد أولاً لعرض حلقاته.</div>

            <!-- نموذج اختيار المعلم (GET) -->
            <form method="get" id="selectTeacherForm">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                <div class="form-group">
                    <label><i class="fas fa-chalkboard-teacher"></i> اختر المعلم الجديد</label>
                    <select name="teacher_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- اختر معلم --</option>
                        <?php foreach ($available_teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($selected_teacher_id == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?> (<?php echo $t['phone'] ?: 'لا يوجد هاتف'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selected_teacher_id > 0): ?>
            <!-- نموذج تأكيد النقل (POST) -->
            <form method="post" id="transferForm">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                <input type="hidden" name="new_teacher_id" value="<?php echo $selected_teacher_id; ?>">
                <input type="hidden" name="confirm_transfer" value="1">
                
                <div class="checkbox-label">
                    <input type="checkbox" name="transfer_with_ring" id="transferWithRing" value="1">
                    <i class="fas fa-ring"></i> نقل الطالب مع حلقة
                </div>

                <div id="ringGroup" style="display: none; margin-top: 15px;">
                    <label><i class="fas fa-ring"></i> اختر الحلقة الجديدة</label>
                    <div class="rings-list" id="ringsList">
                        <?php if (empty($selected_teacher_rings)): ?>
                            <div class="info-note" style="margin:0; background:#fff3cd; color:#856404;">
                                <i class="fas fa-exclamation-triangle"></i> لا توجد حلقات لهذا المعلم
                            </div>
                        <?php else: ?>
                            <?php foreach ($selected_teacher_rings as $index => $ring): ?>
                                <div class="ring-option" onclick="selectRing(this, <?php echo $ring['id']; ?>)">
                                    <input type="radio" name="new_ring_id" value="<?php echo $ring['id']; ?>" id="ring_<?php echo $ring['id']; ?>">
                                    <label for="ring_<?php echo $ring['id']; ?>" style="flex:1; cursor:pointer;"><?php echo htmlspecialchars($ring['name']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> سبب النقل (اختياري)</label>
                    <textarea name="transfer_reason" class="form-control" rows="2"></textarea>
                </div>

                <div class="action-buttons">
                    <a href="<?php echo isTeacher() ? 'teacher_dashboard.php' : 'students.php'; ?>" class="btn btn-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('هل أنت متأكد من نقل هذا الطالب؟')">تأكيد النقل</button>
                </div>
            </form>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// إظهار/إخفاء حقل الحلقة
const transferWithRing = document.getElementById('transferWithRing');
const ringGroup = document.getElementById('ringGroup');

if (transferWithRing && ringGroup) {
    transferWithRing.addEventListener('change', function() {
        ringGroup.style.display = this.checked ? 'block' : 'none';
    });
}

// تحديد حلقة
function selectRing(element, ringId) {
    document.querySelectorAll('.ring-option').forEach(opt => {
        opt.classList.remove('selected');
        const radio = opt.querySelector('input[type="radio"]');
        if (radio) radio.checked = false;
    });
    element.classList.add('selected');
    const radio = element.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
}
</script>

<?php require_once 'includes/footer.php'; ?>
      