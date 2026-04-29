<?php
// ============================================
// ملف: transfer_ring.php
// نقل حلقة بين المعلمين مع إشعارات واتساب
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'نقل حلقة';
require_once 'includes/header.php';

$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : (isset($_POST['ring_id']) ? (int)$_POST['ring_id'] : 0);
$error = '';
$ring = null;
$available_teachers = [];

// ============================================
// جلب معلومات الحلقة
// ============================================
if ($ring_id > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as current_teacher_name, t.gender as current_teacher_gender, t.phone as current_teacher_phone
        FROM rings r
        LEFT JOIN teachers t ON r.teacher_id = t.id
        WHERE r.id = ?
    ");
    $stmt->execute([$ring_id]);
    $ring = $stmt->fetch();
    
    if (!$ring) {
        $error = "❌ الحلقة غير موجودة";
    } else {
        $canTransfer = isAdmin() || (isTeacher() && $ring['teacher_id'] == $_SESSION['user_id']);
        if (!$canTransfer) {
            $error = "❌ لا تملك صلاحية نقل هذه الحلقة";
        } else {
            $current_gender = $ring['current_teacher_gender'];
            $teachers_query = "SELECT id, name, gender, phone FROM teachers WHERE can_login = 1 AND gender = ? AND id != ? ORDER BY name";
            $stmt = $pdo->prepare($teachers_query);
            $stmt->execute([$current_gender, $ring['teacher_id'] ?: 0]);
            $available_teachers = $stmt->fetchAll();
        }
    }
}

// ============================================
// جلب طلاب الحلقة
// ============================================
$ring_students = [];
if ($ring_id > 0 && !$error) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.parent_phone
        FROM ring_students rs
        JOIN students s ON rs.student_id = s.id
        WHERE rs.ring_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$ring_id]);
    $ring_students = $stmt->fetchAll();
}

// ============================================
// دالة تنسيق رقم الهاتف
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
// دالة إنشاء جدول السجل
// ============================================
function ensureRingTransferLogsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ring_transfer_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ring_id INT NOT NULL,
            ring_name VARCHAR(255) NOT NULL,
            old_teacher_id INT NOT NULL,
            new_teacher_id INT NOT NULL,
            students_count INT DEFAULT 0,
            transfer_students TINYINT DEFAULT 1,
            transfer_reason TEXT,
            transferred_by INT NOT NULL,
            transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// ============================================
// معالجة نقل الحلقة
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_ring']) && $ring) {
    $new_teacher_id = (int)$_POST['new_teacher_id'];
    $transfer_students = isset($_POST['transfer_students']);
    $transfer_reason = trim($_POST['transfer_reason'] ?? '');
    
    $new_teacher = $pdo->prepare("SELECT id, name, gender, phone FROM teachers WHERE id = ? AND can_login = 1");
    $new_teacher->execute([$new_teacher_id]);
    $new_teacher_data = $new_teacher->fetch();
    
    if (!$new_teacher_data) {
        $error = "❌ المعلم المحدد غير موجود";
    } elseif ($ring['current_teacher_gender'] != $new_teacher_data['gender']) {
        $error = "❌ لا يمكن نقل الحلقة إلى معلم من جنس مختلف";
    } else {
        try {
            ensureRingTransferLogsTable($pdo);
            
            $pdo->beginTransaction();
            
            // تحديث معلم الحلقة
            $update = $pdo->prepare("UPDATE rings SET teacher_id = ? WHERE id = ?");
            $update->execute([$new_teacher_id, $ring_id]);
            
            // نقل الطلاب مع الحلقة
            $transferred_students = [];
            if ($transfer_students && !empty($ring_students)) {
                foreach ($ring_students as $student) {
                    $pdo->prepare("UPDATE students SET teacher_id = ? WHERE id = ?")->execute([$new_teacher_id, $student['id']]);
                    $transferred_students[] = $student;
                }
            }
            
            // تسجيل النقل
            $log = $pdo->prepare("
                INSERT INTO ring_transfer_logs 
                (ring_id, ring_name, old_teacher_id, new_teacher_id, students_count, transfer_students, transfer_reason, transferred_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $log->execute([
                $ring_id, 
                $ring['name'], 
                $ring['teacher_id'], 
                $new_teacher_id,
                count($ring_students), 
                $transfer_students ? 1 : 0, 
                $transfer_reason, 
                $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            
            // تخزين معلومات النقل في الجلسة
            $_SESSION['ring_transfer_completed'] = true;
            $_SESSION['ring_transfer_name'] = $ring['name'];
            $_SESSION['ring_transfer_new_teacher_name'] = $new_teacher_data['name'];
            $_SESSION['ring_transfer_new_teacher_phone'] = $new_teacher_data['phone'];
            $_SESSION['ring_transfer_students'] = $transferred_students;
            $_SESSION['ring_transfer_reason'] = $transfer_reason;
            $_SESSION['ring_transfer_old_teacher_name'] = $ring['current_teacher_name'];
            
            $_SESSION['success'] = "✅ تم نقل الحلقة بنجاح إلى المعلم {$new_teacher_data['name']}";
            header("Location: rings.php");
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}

$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
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
        .ring-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; border-right: 4px solid var(--secondary); }
        .ring-icon { width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 3px solid var(--secondary); }
        .ring-info h3 { color: var(--primary); font-size: 1.3rem; margin-bottom: 5px; }
        .ring-info p { color: #666; font-size: 0.9rem; display: flex; gap: 10px; flex-wrap: wrap; }
        .students-list { background: #e8f5e9; padding: 10px; border-radius: 10px; margin-top: 10px; max-height: 200px; overflow-y: auto; }
        .student-item { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #c8e6c9; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--primary); }
        .form-group label i { color: var(--secondary); margin-left: 5px; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 12px; margin-bottom: 15px; }
        .action-buttons { display: flex; gap: 15px; margin-top: 25px; }
        .btn { flex: 1; padding: 12px; border-radius: 40px; border: none; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid var(--success); }
        .alert-error { background: #f8d7da; color: #721c24; border-right: 5px solid var(--danger); }
        @media (max-width: 768px) { .ring-card { flex-direction: column; text-align: center; } .action-buttons { flex-direction: column; } }
    </style>
</head>
<body>
<div class="transfer-container">
    <div class="transfer-card">
        <div class="transfer-header">
            <h1><i class="fas fa-exchange-alt"></i> نقل حلقة</h1>
            <p>نقل الحلقة إلى معلم آخر مع إمكانية نقل الطلاب معها</p>
        </div>
        
        <div class="transfer-body">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($ring): ?>
            <div class="ring-card">
                <div class="ring-icon"><i class="fas fa-ring"></i></div>
                <div class="ring-info">
                    <h3><?php echo htmlspecialchars($ring['name']); ?></h3>
                    <p><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($ring['current_teacher_name'] ?? 'غير محدد'); ?> | <i class="fas fa-users"></i> <?php echo count($ring_students); ?> طالب</p>
                    <?php if (!empty($ring_students)): ?>
                        <div class="students-list">
                            <strong><i class="fas fa-list"></i> طلاب الحلقة:</strong>
                            <?php foreach ($ring_students as $s): ?>
                                <div class="student-item">
                                    <span><?php echo htmlspecialchars($s['name']); ?></span>
                                    <span><?php echo $s['parent_phone'] ?: 'لا يوجد هاتف'; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="ring_id" value="<?php echo $ring_id; ?>">
                <input type="hidden" name="transfer_ring" value="1">

                <div class="form-group">
                    <label><i class="fas fa-chalkboard-teacher"></i> اختر المعلم الجديد</label>
                    <select name="new_teacher_id" class="form-control" required>
                        <option value="">-- اختر معلم --</option>
                        <?php foreach ($available_teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?> (<?php echo $t['phone'] ?: 'لا يوجد هاتف'; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="checkbox-label">
                    <input type="checkbox" name="transfer_students" value="1" checked>
                    <i class="fas fa-users"></i> نقل الطلاب مع الحلقة
                </div>

                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> سبب النقل</label>
                    <textarea name="transfer_reason" class="form-control" rows="2"></textarea>
                </div>

                <div class="action-buttons">
                    <a href="rings.php" class="btn btn-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('هل أنت متأكد من نقل هذه الحلقة؟')">تأكيد النقل</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>