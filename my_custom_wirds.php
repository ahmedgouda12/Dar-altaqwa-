<?php
// ============================================
// ملف: my_custom_wirds.php
// إدارة الأوراد الإضافية للطالب
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isStudent()) {
    redirect('login.php');
}

$pageTitle = 'أورادي الإضافية';
$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// معالجة إضافة ورد جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_wird'])) {
    $wird_name = trim($_POST['wird_name']);
    $wird_type = $_POST['wird_type'];
    $points_per_unit = (int)$_POST['points_per_unit'];
    $target_units = (int)$_POST['target_units'];
    $unit_type = $_POST['unit_type'];
    
    if (empty($wird_name)) {
        $message = '❌ اسم الورد مطلوب';
        $message_type = 'error';
    } elseif ($points_per_unit < 0 || $target_units < 0) {
        $message = '❌ النقاط والهدف يجب أن تكون أرقاماً موجبة';
        $message_type = 'error';
    } else {
        if (addCustomWird($pdo, $student_id, $wird_name, $wird_type, $points_per_unit, $target_units, $unit_type)) {
            $message = '✅ تم إضافة الورد بنجاح';
            $message_type = 'success';
        } else {
            $message = '❌ حدث خطأ في إضافة الورد';
            $message_type = 'error';
        }
    }
}

// معالجة حذف ورد
if (isset($_GET['delete'])) {
    $wird_id = (int)$_GET['delete'];
    if (deleteCustomWird($pdo, $wird_id, $student_id)) {
        $message = '✅ تم حذف الورد بنجاح';
        $message_type = 'success';
    } else {
        $message = '❌ حدث خطأ في حذف الورد';
        $message_type = 'error';
    }
}

// معالجة تسجيل تقدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_progress'])) {
    $wird_id = (int)$_POST['wird_id'];
    $units_completed = (int)$_POST['units_completed'];
    $notes = trim($_POST['notes'] ?? '');
    
    if ($units_completed <= 0) {
        $message = '❌ يرجى إدخال عدد أكبر من الصفر';
        $message_type = 'error';
    } else {
        if (recordCustomWirdProgress($pdo, $student_id, $wird_id, $units_completed, $notes, 'student', $student_id)) {
            $message = '✅ تم تسجيل التقدم بنجاح';
            $message_type = 'success';
        } else {
            $message = '❌ حدث خطأ في تسجيل التقدم';
            $message_type = 'error';
        }
    }
}

$custom_wirds = getStudentCustomWirds($pdo, $student_id);

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أورادي الإضافية - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --success: #28a745;
            --danger: #dc3545;
            --info: #17a2b8;
            --warning: #ffc107;
            --purple: #6f42c1;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .custom-wirds-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .page-header h1 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .page-header h1 i {
            color: var(--secondary);
        }
        
        /* نموذج إضافة ورد */
        .add-wird-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary);
            color: var(--primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .form-group label i {
            color: var(--secondary);
            margin-left: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        /* بطاقات الأوراد */
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
            border: 1px solid #eee;
        }
        
        .wird-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .wird-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .wird-name {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .wird-type {
            font-size: 0.7rem;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 30px;
        }
        
        .wird-content {
            padding: 20px;
        }
        
        .wird-stats {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #666;
        }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success), #20c997);
            border-radius: 10px;
            transition: width 0.3s;
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
            background: var(--info);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 25px;
            grid-column: 1 / -1;
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
            border-right: 5px solid var(--success);
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 5px solid var(--danger);
        }
        
        @media (max-width: 768px) {
            .wirds-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="custom-wirds-page">
    <div class="page-header">
        <h1><i class="fas fa-star-and-crescent"></i> أورادي الإضافية</h1>
        <p>أضف أورادك الخاصة وسجل تقدمك اليومي</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة ورد جديد -->
    <div class="add-wird-card">
        <div class="section-title">
            <i class="fas fa-plus-circle" style="color: var(--success);"></i>
            <h3>إضافة ورد جديد</h3>
        </div>
        
        <form method="post">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> اسم الورد</label>
                    <input type="text" name="wird_name" class="form-control" required placeholder="مثال: ورد القرآن - جزء عم">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> نوع الورد</label>
                    <select name="wird_type" class="form-control">
                        <option value="quran">📖 قرآن</option>
                        <option value="adkar">🕌 أذكار</option>
                        <option value="prayer">🕋 صلاة نافلة</option>
                        <option value="other">✨ أخرى</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-star"></i> النقاط لكل وحدة</label>
                    <input type="number" name="points_per_unit" class="form-control" value="1" min="1" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-bullseye"></i> الهدف اليومي</label>
                    <input type="number" name="target_units" class="form-control" value="1" min="1" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-ruler"></i> نوع الوحدة</label>
                    <select name="unit_type" class="form-control">
                        <option value="page">صفحة</option>
                        <option value="ayah">آية</option>
                        <option value="time">دقيقة</option>
                        <option value="count">عدد</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="add_wird" class="btn-primary">
                <i class="fas fa-save"></i> إضافة الورد
            </button>
        </form>
    </div>

    <!-- قائمة الأوراد الإضافية -->
    <div class="section-title" style="margin-bottom: 20px;">
        <i class="fas fa-list"></i>
        <h3>أورادي المضافة (<?php echo count($custom_wirds); ?>)</h3>
    </div>

    <?php if (empty($custom_wirds)): ?>
        <div class="empty-state">
            <i class="fas fa-star-and-crescent" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3>لا توجد أوراد مضافة</h3>
            <p>أضف وردك الأول الآن وابدأ في تسجيل تقدمك</p>
        </div>
    <?php else: ?>
        <div class="wirds-grid">
            <?php foreach ($custom_wirds as $wird):
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
                        <div class="wird-type"><?php echo $type_names[$wird['wird_type']] ?? $wird['wird_type']; ?></div>
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
                        
                        <?php if ($wird['today_units'] > 0): ?>
                            <div style="background: #d4edda; padding: 8px; border-radius: 10px; margin: 10px 0; text-align: center; font-size: 0.8rem;">
                                <i class="fas fa-check-circle"></i> تم اليوم: <?php echo $wird['today_units']; ?> <?php echo $wird['unit_type']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="record-form">
                            <input type="hidden" name="wird_id" value="<?php echo $wird['id']; ?>">
                            <div class="record-input">
                                <input type="number" name="units_completed" placeholder="عدد <?php echo $wird['unit_type']; ?>" min="1" required>
                                <button type="submit" name="record_progress" class="btn-record">
                                    <i class="fas fa-save"></i> سجل
                                </button>
                            </div>
                            <input type="text" name="notes" class="form-control" placeholder="ملاحظات (اختياري)" style="margin-top: 10px;">
                        </form>
                        
                        <div style="margin-top: 15px; text-align: left;">
                            <a href="?delete=<?php echo $wird['id']; ?>" class="btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الورد؟')">
                                <i class="fas fa-trash"></i> حذف
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>