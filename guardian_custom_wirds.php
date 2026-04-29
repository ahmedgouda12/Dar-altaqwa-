<?php
// ============================================
// ملف: guardian_custom_wirds.php
// ولي الأمر يسجل العبادات لأبنائه
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isGuardian()) {
    redirect('guardian_login.php');
}

$pageTitle = 'تسجيل العبادات للأبناء';
$guardian_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// جلب أبناء ولي الأمر
$children = getGuardianChildren($pdo, $guardian_id);

// جلب الأوراد الإضافية للطالب المختار
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0);
$student_wirds = [];
$student_name = '';

if ($selected_student > 0) {
    $student_wirds = getStudentCustomWirds($pdo, $selected_student);
    $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $stmt->execute([$selected_student]);
    $student_name = $stmt->fetchColumn();
}

// معالجة تسجيل التقدم من ولي الأمر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_progress'])) {
    $student_id = (int)$_POST['student_id'];
    $wird_id = (int)$_POST['wird_id'];
    $units_completed = (int)$_POST['units_completed'];
    $notes = trim($_POST['notes'] ?? '');
    
    // التحقق من أن الطالب يتبع ولي الأمر
    $check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND guardian_id = ?");
    $check->execute([$student_id, $guardian_id]);
    
    if (!$check->fetch()) {
        $message = '❌ هذا الطالب ليس من أبنائك';
        $message_type = 'error';
    } elseif ($units_completed <= 0) {
        $message = '❌ يرجى إدخال عدد أكبر من الصفر';
        $message_type = 'error';
    } else {
        if (recordCustomWirdProgress($pdo, $student_id, $wird_id, $units_completed, $notes, 'guardian', $guardian_id)) {
            $message = '✅ تم تسجيل التقدم بنجاح';
            $message_type = 'success';
            // تحديث القائمة
            $student_wirds = getStudentCustomWirds($pdo, $selected_student);
        } else {
            $message = '❌ حدث خطأ في تسجيل التقدم';
            $message_type = 'error';
        }
    }
}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل العبادات للأبناء - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .guardian-wirds-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .page-header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .child-card {
            background: white;
            border-radius: 20px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            border: 2px solid transparent;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .child-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .child-card.active {
            border-color: #c9a96b;
            background: #fff8e7;
        }
        .child-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 10px;
        }
        .child-name {
            font-weight: 700;
            color: #1e3c3f;
        }
        .wirds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        .wird-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .wird-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .wird-header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 15px 20px;
        }
        .wird-name {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .wird-content {
            padding: 20px;
        }
        .wird-stats {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        .progress-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 10px;
        }
        .record-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e9ecef;
        }
        .record-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .record-input input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
        }
        .btn-record {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
        }
        .alert {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-right: 5px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 5px solid #dc3545;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 25px;
        }
        @media (max-width: 768px) {
            .wirds-grid { grid-template-columns: 1fr; }
            .children-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="guardian-wirds-page">
    <div class="page-header">
        <h1><i class="fas fa-hands-helping"></i> تسجيل العبادات لأبنائي</h1>
        <p>سجل لأبنائك أورادهم الإضافية واحصلوا على النقاط</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- قائمة الأبناء -->
    <div class="children-grid">
        <?php foreach ($children as $child): ?>
            <div class="child-card <?php echo $selected_student == $child['id'] ? 'active' : ''; ?>" 
                 onclick="window.location.href='?student_id=<?php echo $child['id']; ?>'">
                <div class="child-avatar">
                    <?php echo htmlspecialchars(mb_substr($child['name'], 0, 1, 'UTF-8')); ?>
                </div>
                <div class="child-name"><?php echo htmlspecialchars($child['name']); ?></div>
                <div style="font-size: 0.7rem; color: #666;">المعلم: <?php echo htmlspecialchars($child['teacher_name'] ?? 'غير محدد'); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($selected_student > 0 && empty($student_wirds)): ?>
        <div class="empty-state">
            <i class="fas fa-star-and-crescent" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3>لا توجد أوراد مضافة للطالب <?php echo htmlspecialchars($student_name); ?></h3>
            <p>يمكن للطالب إضافة أوراده الخاصة من حسابه، أو يمكنك تسجيل العبادات اليومية المباشرة</p>
            <div style="margin-top: 20px;">
                <a href="guardian_daily_memorization.php?student_id=<?php echo $selected_student; ?>" class="btn btn-primary" style="background: #1e3c3f; color: white; padding: 10px 25px; text-decoration: none; border-radius: 30px;">
                    <i class="fas fa-pen-alt"></i> تسجيل حفظ يومي
                </a>
            </div>
        </div>
    <?php elseif ($selected_student > 0): ?>
        <div style="margin-bottom: 20px;">
            <h3 style="color: #1e3c3f;">
                <i class="fas fa-user-graduate"></i> 
                أوراد <?php echo htmlspecialchars($student_name); ?> الإضافية
            </h3>
        </div>
        
        <div class="wirds-grid">
            <?php foreach ($student_wirds as $wird):
                $total_units = $wird['total_units'] ?? 0;
                $target = $wird['target_units'];
                $progress = $target > 0 ? min(100, round(($total_units / $target) * 100)) : 0;
                $type_names = [
                    'quran' => '📖 قرآن',
                    'adkar' => '🕌 أذكار',
                    'prayer' => '🕋 صلاة نافلة',
                    'other' => '✨ أخرى'
                ];
            ?>
                <div class="wird-card">
                    <div class="wird-header">
                        <div class="wird-name">
                            <i class="fas <?php echo $wird['wird_type'] == 'quran' ? 'fa-quran' : ($wird['wird_type'] == 'adkar' ? 'fa-praying-hands' : 'fa-star'); ?>"></i>
                            <?php echo htmlspecialchars($wird['wird_name']); ?>
                        </div>
                    </div>
                    
                    <div class="wird-content">
                        <div class="wird-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($wird['points_per_unit']); ?></div>
                                <div class="stat-label">نقطة/وحدة</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $wird['target_units']; ?></div>
                                <div class="stat-label">الهدف اليومي</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($wird['total_points'] ?? 0); ?></div>
                                <div class="stat-label">إجمالي النقاط</div>
                            </div>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <div style="text-align: center; font-size: 0.7rem; margin-top: 5px;">
                            تقدم: <?php echo $total_units; ?>/<?php echo $target; ?> <?php echo $wird['unit_type']; ?>
                        </div>
                        
                        <form method="post" class="record-form">
                            <input type="hidden" name="student_id" value="<?php echo $selected_student; ?>">
                            <input type="hidden" name="wird_id" value="<?php echo $wird['id']; ?>">
                            <div class="record-input">
                                <input type="number" name="units_completed" placeholder="عدد <?php echo $wird['unit_type']; ?>" min="1" required>
                                <button type="submit" name="record_progress" class="btn-record">
                                    <i class="fas fa-save"></i> سجل
                                </button>
                            </div>
                            <input type="text" name="notes" class="form-control" placeholder="ملاحظات (اختياري)" style="margin-top: 10px; width: 100%; padding: 8px;">
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($children)): ?>
        <div class="empty-state">
            <i class="fas fa-child" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3>لا يوجد أبناء مسجلين</h3>
            <p>لم يتم ربط أي طالب بحسابك بعد. يرجى التواصل مع الإدارة.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>