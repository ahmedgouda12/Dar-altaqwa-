<?php
// ============================================
// ملف: guardian_full_worship.php
// لوحة تحكم ولي الأمر - جميع العبادات
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'includes/wirds_functions.php';

if (!isGuardian()) {
    redirect('guardian_login.php');
}

$pageTitle = 'لوحة العبادات - أبنائي';
$guardian_id = $_SESSION['user_id'];
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'wirds';
$message = '';
$message_type = '';

// جلب جميع أبناء ولي الأمر
$children = $pdo->prepare("
    SELECT s.id, s.name, s.level, s.category, t.name as teacher_name
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.guardian_id = ?
    ORDER BY s.name
");
$children->execute([$guardian_id]);
$children = $children->fetchAll();

$student_name = '';
if ($selected_student > 0) {
    $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND guardian_id = ?");
    $stmt->execute([$selected_student, $guardian_id]);
    $student_name = $stmt->fetchColumn();
}

// ============================================
// جلب الأوراد الأساسية
// ============================================
$basic_wirds = getActiveWirds($pdo);

// جلب الصلوات الأساسية
$prayers = getActivePrayers($pdo);

// جلب الأوراد الإضافية للطالب
$custom_wirds = [];
if ($selected_student > 0) {
    $custom_wirds = getStudentCustomWirds($pdo, $selected_student);
}

// ============================================
// جلب تسجيلات اليوم للطالب
// ============================================
$today = date('Y-m-d');
$wird_records = [];
$prayer_records = [];

if ($selected_student > 0) {
    foreach ($basic_wirds as $wird) {
        $wird_records[$wird['id']] = getStudentWirdRecord($pdo, $selected_student, $wird['id'], $today);
    }
    foreach ($prayers as $prayer) {
        $prayer_records[$prayer['id']] = getStudentPrayerRecord($pdo, $selected_student, $prayer['id'], $today);
    }
}

// ============================================
// معالجة إضافة ورد إضافي للطالب
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_custom_wird']) && $selected_student > 0) {
    $wird_name = trim($_POST['wird_name']);
    $wird_type = $_POST['wird_type'];
    $points_per_unit = (int)$_POST['points_per_unit'];
    $target_units = (int)$_POST['target_units'];
    $unit_type = $_POST['unit_type'];
    
    if (empty($wird_name)) {
        $message = '❌ اسم الورد مطلوب';
        $message_type = 'error';
    } elseif ($points_per_unit <= 0 || $target_units <= 0) {
        $message = '❌ النقاط والهدف يجب أن تكون أرقاماً موجبة';
        $message_type = 'error';
    } else {
        if (addCustomWird($pdo, $selected_student, $wird_name, $wird_type, $points_per_unit, $target_units, $unit_type)) {
            $message = '✅ تم إضافة الورد بنجاح للطالب';
            $message_type = 'success';
            $custom_wirds = getStudentCustomWirds($pdo, $selected_student);
        } else {
            $message = '❌ حدث خطأ في إضافة الورد';
            $message_type = 'error';
        }
    }
}

// ============================================
// معالجة تسجيل تقدم في ورد إضافي
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_custom_wird']) && $selected_student > 0) {
    $wird_id = (int)$_POST['wird_id'];
    $units_completed = (int)$_POST['units_completed'];
    $notes = trim($_POST['notes'] ?? '');
    
    if ($units_completed <= 0) {
        $message = '❌ يرجى إدخال عدد أكبر من الصفر';
        $message_type = 'error';
    } else {
        if (recordCustomWirdProgress($pdo, $selected_student, $wird_id, $units_completed, $notes, 'guardian', $guardian_id)) {
            $message = '✅ تم تسجيل التقدم بنجاح';
            $message_type = 'success';
            $custom_wirds = getStudentCustomWirds($pdo, $selected_student);
        } else {
            $message = '❌ حدث خطأ في تسجيل التقدم';
            $message_type = 'error';
        }
    }
}

// ============================================
// معالجة تسجيل تقدم في ورد أساسي
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_basic_wird']) && $selected_student > 0) {
    $wird_id = (int)$_POST['wird_id'];
    $count = (int)$_POST['count'];
    $notes = trim($_POST['notes'] ?? '');
    
    if (updateStudentWird($pdo, $selected_student, $wird_id, $count, $notes)) {
        $message = '✅ تم تسجيل الورد بنجاح';
        $message_type = 'success';
        $wird_records[$wird_id] = getStudentWirdRecord($pdo, $selected_student, $wird_id, $today);
    } else {
        $message = '❌ حدث خطأ في تسجيل الورد';
        $message_type = 'error';
    }
}

// ============================================
// معالجة تسجيل صلاة
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_prayer']) && $selected_student > 0) {
    $prayer_id = (int)$_POST['prayer_id'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    $is_jamaa = ($status == 'jamaa');
    $points = 0;
    
    $prayer = $pdo->prepare("SELECT * FROM prayers WHERE id = ?");
    $prayer->execute([$prayer_id]);
    $prayer_data = $prayer->fetch();
    
    if ($prayer_data) {
        if ($status == 'jamaa') $points = $prayer_data['points_jamaa'];
        elseif ($status == 'on_time') $points = $prayer_data['points_on_time'];
        elseif ($status == 'late') $points = $prayer_data['points_late'];
    }
    
    try {
        $check = getStudentPrayerRecord($pdo, $selected_student, $prayer_id, $today);
        
        if ($check) {
            $stmt = $pdo->prepare("
                UPDATE student_prayer_records SET
                    status = ?, is_jamaa = ?, points_earned = ?,
                    notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $is_jamaa, $points, $notes, $check['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO student_prayer_records 
                (student_id, prayer_id, prayer_date, status, is_jamaa, points_earned, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$selected_student, $prayer_id, $today, $status, $is_jamaa, $points, $notes]);
        }
        
        updateTotalStudentPoints($pdo, $selected_student);
        $message = "✅ تم تسجيل صلاة {$prayer_data['prayer_name']} بنجاح! +{$points} نقطة";
        $message_type = 'success';
        $prayer_records[$prayer_id] = getStudentPrayerRecord($pdo, $selected_student, $prayer_id, $today);
        
    } catch (PDOException $e) {
        $message = "❌ حدث خطأ: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================
// معالجة حذف ورد إضافي
// ============================================
if (isset($_GET['delete_custom_wird']) && $selected_student > 0) {
    $wird_id = (int)$_GET['delete_custom_wird'];
    if (deleteCustomWird($pdo, $wird_id, $selected_student)) {
        $message = '✅ تم حذف الورد بنجاح';
        $message_type = 'success';
        $custom_wirds = getStudentCustomWirds($pdo, $selected_student);
    } else {
        $message = '❌ حدث خطأ في حذف الورد';
        $message_type = 'error';
    }
}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة العبادات - أبنائي | دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
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
            --purple: #6f42c1;
            --whatsapp: #25d366;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .guardian-worship {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* رأس الصفحة */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        /* قائمة الأبناء */
        .children-section {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .child-card {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            border: 2px solid transparent;
        }
        
        .child-card:hover {
            transform: translateY(-3px);
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .child-card.active {
            border-color: var(--secondary);
            background: linear-gradient(135deg, #fff8e7, #fff3d6);
        }
        
        .child-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 10px;
            border: 2px solid var(--secondary);
        }
        
        .child-name {
            font-weight: 700;
            color: var(--primary);
        }
        
        .child-level {
            font-size: 0.7rem;
            color: #666;
        }
        
        /* التبويبات */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 28px;
            border-radius: 50px;
            background: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        
        .tab-btn.active i {
            color: var(--secondary);
        }
        
        /* بطاقات الأوراد */
        .wirds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .wird-card, .prayer-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
            border: 1px solid #eee;
        }
        
        .wird-card:hover, .prayer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .wird-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .wird-name {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .wird-content, .prayer-content {
            padding: 20px;
        }
        
        .counter-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 15px 0;
        }
        
        .counter-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .counter-btn.minus { background: var(--danger); color: white; }
        .counter-btn.plus { background: var(--success); color: white; }
        .counter-value { font-size: 1.8rem; font-weight: 700; min-width: 80px; text-align: center; }
        
        .progress-bar {
            height: 6px;
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
        
        /* خيارات الصلاة */
        .prayer-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        
        .prayer-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 30px;
            cursor: pointer;
            transition: 0.3s;
            border: 2px solid transparent;
            font-weight: 600;
        }
        
        .prayer-option.jamaa { background: #d1ecf1; color: #0c5460; border-color: #17a2b8; }
        .prayer-option.jamaa.selected { background: #17a2b8; color: white; }
        .prayer-option.on_time { background: #d4edda; color: #155724; border-color: #28a745; }
        .prayer-option.on_time.selected { background: #28a745; color: white; }
        .prayer-option.late { background: #fff3cd; color: #856404; border-color: #ffc107; }
        .prayer-option.late.selected { background: #ffc107; color: #212529; }
        .prayer-option.missed { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        .prayer-option.missed.selected { background: #dc3545; color: white; }
        
        .btn-save {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        
        /* نموذج إضافة ورد */
        .add-wird-form {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #d4edda; color: #155724; border-right: 5px solid var(--success); }
        .alert-error { background: #f8d7da; color: #721c24; border-right: 5px solid var(--danger); }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 25px;
        }
        
        @media (max-width: 768px) {
            .wirds-grid { grid-template-columns: 1fr; }
            .children-grid { grid-template-columns: 1fr; }
            .prayer-options { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="guardian-worship">
    <div class="page-header">
        <h1><i class="fas fa-praying-hands"></i> لوحة العبادات - أبنائي</h1>
        <p>سجل لأبنائك الأوراد والصلوات والأذكار اليومية</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- قائمة الأبناء -->
    <div class="children-section">
        <h3><i class="fas fa-users"></i> أبنائي</h3>
        <div class="children-grid">
            <?php foreach ($children as $child): ?>
                <div class="child-card <?php echo $selected_student == $child['id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='?student_id=<?php echo $child['id']; ?>&tab=<?php echo $active_tab; ?>'">
                    <div class="child-avatar">
                        <?php echo htmlspecialchars(mb_substr($child['name'], 0, 1, 'UTF-8')); ?>
                    </div>
                    <div class="child-name"><?php echo htmlspecialchars($child['name']); ?></div>
                    <div class="child-level"><?php echo htmlspecialchars($child['level'] ?? 'مبتدئ'); ?></div>
                    <div style="font-size: 0.7rem; color: #666;">المعلم: <?php echo htmlspecialchars($child['teacher_name'] ?? '-'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($selected_student > 0 && $student_name): ?>
        
        <!-- تبويبات العبادات -->
        <div class="tabs">
            <button class="tab-btn <?php echo $active_tab == 'basic_wirds' ? 'active' : ''; ?>" onclick="window.location.href='?student_id=<?php echo $selected_student; ?>&tab=basic_wirds'">
                <i class="fas fa-star-and-crescent"></i> الأوراد الأساسية
            </button>
            <button class="tab-btn <?php echo $active_tab == 'prayers' ? 'active' : ''; ?>" onclick="window.location.href='?student_id=<?php echo $selected_student; ?>&tab=prayers'">
                <i class="fas fa-mosque"></i> الصلوات
            </button>
            <button class="tab-btn <?php echo $active_tab == 'custom_wirds' ? 'active' : ''; ?>" onclick="window.location.href='?student_id=<?php echo $selected_student; ?>&tab=custom_wirds'">
                <i class="fas fa-plus-circle"></i> أوراد إضافية
            </button>
        </div>

        <!-- ============================================ -->
        <!-- تبويب الأوراد الأساسية -->
        <!-- ============================================ -->
        <div id="tab-basic_wirds" style="display: <?php echo $active_tab == 'basic_wirds' ? 'block' : 'none'; ?>">
            <h3 style="margin-bottom: 15px;">
                <i class="fas fa-star-and-crescent"></i> 
                أوراد <?php echo htmlspecialchars($student_name); ?> الأساسية
            </h3>
            
            <?php if (empty($basic_wirds)): ?>
                <div class="empty-state">
                    <i class="fas fa-star-and-crescent"></i>
                    <h3>لا توجد أوراد أساسية</h3>
                </div>
            <?php else: ?>
                <div class="wirds-grid">
                    <?php foreach ($basic_wirds as $wird):
                        $record = $wird_records[$wird['id']] ?? null;
                        $current_count = $record['current_count'] ?? 0;
                        $target = $wird['recommended_count'];
                        $percentage = $target > 0 ? min(100, round(($current_count / $target) * 100)) : 0;
                    ?>
                        <div class="wird-card">
                            <div class="wird-header">
                                <div class="wird-name">
                                    <i class="fas <?php echo $wird['icon']; ?>" style="color: var(--secondary);"></i>
                                    <?php echo htmlspecialchars($wird['wird_name']); ?>
                                </div>
                                <div class="wird-points"><?php echo $wird['points_per_unit']; ?> نقطة/مرة</div>
                            </div>
                            <div class="wird-content">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                                
                                <form method="post">
                                    <input type="hidden" name="wird_id" value="<?php echo $wird['id']; ?>">
                                    <div class="counter-container">
                                        <button type="button" class="counter-btn minus" onclick="updateCounter(this, -1, <?php echo $wird['id']; ?>)">−</button>
                                        <span class="counter-value" id="counter_<?php echo $wird['id']; ?>"><?php echo number_format($current_count); ?></span>
                                        <button type="button" class="counter-btn plus" onclick="updateCounter(this, 1, <?php echo $wird['id']; ?>)">+</button>
                                    </div>
                                    <input type="hidden" name="count" id="count_<?php echo $wird['id']; ?>" value="<?php echo $current_count; ?>">
                                    <button type="submit" name="record_basic_wird" class="btn-save">
                                        <i class="fas fa-save"></i> حفظ التقدم
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- تبويب الصلوات -->
        <!-- ============================================ -->
        <div id="tab-prayers" style="display: <?php echo $active_tab == 'prayers' ? 'block' : 'none'; ?>">
            <h3 style="margin-bottom: 15px;">
                <i class="fas fa-mosque"></i> 
                صلوات <?php echo htmlspecialchars($student_name); ?>
            </h3>
            
            <?php if (empty($prayers)): ?>
                <div class="empty-state">
                    <i class="fas fa-mosque"></i>
                    <h3>لا توجد صلوات مسجلة</h3>
                </div>
            <?php else: ?>
                <div class="wirds-grid">
                    <?php foreach ($prayers as $prayer):
                        $record = $prayer_records[$prayer['id']] ?? null;
                        $current_status = $record['status'] ?? 'missed';
                        $points = $record['points_earned'] ?? 0;
                    ?>
                        <div class="prayer-card">
                            <div class="wird-header">
                                <div class="wird-name">
                                    <i class="fas fa-clock"></i>
                                    صلاة <?php echo $prayer['prayer_name']; ?>
                                </div>
                                <div class="wird-points">
                                    <?php echo $prayer['points_jamaa']; ?> نقطة (جماعة)
                                </div>
                            </div>
                            <div class="prayer-content">
                                <form method="post">
                                    <input type="hidden" name="prayer_id" value="<?php echo $prayer['id']; ?>">
                                    
                                    <div class="prayer-options">
                                        <label class="prayer-option jamaa <?php echo $current_status == 'jamaa' ? 'selected' : ''; ?>">
                                            <i class="fas fa-users"></i> صلاة جماعة
                                            <input type="radio" name="status" value="jamaa" <?php echo $current_status == 'jamaa' ? 'checked' : ''; ?> style="display: none;">
                                        </label>
                                        <label class="prayer-option on_time <?php echo $current_status == 'on_time' ? 'selected' : ''; ?>">
                                            <i class="fas fa-check-circle"></i> في الوقت
                                            <input type="radio" name="status" value="on_time" <?php echo $current_status == 'on_time' ? 'checked' : ''; ?> style="display: none;">
                                        </label>
                                        <label class="prayer-option late <?php echo $current_status == 'late' ? 'selected' : ''; ?>">
                                            <i class="fas fa-clock"></i> متأخر
                                            <input type="radio" name="status" value="late" <?php echo $current_status == 'late' ? 'checked' : ''; ?> style="display: none;">
                                        </label>
                                        <label class="prayer-option missed <?php echo $current_status == 'missed' ? 'selected' : ''; ?>">
                                            <i class="fas fa-times-circle"></i> لم يصل
                                            <input type="radio" name="status" value="missed" <?php echo $current_status == 'missed' ? 'checked' : ''; ?> style="display: none;">
                                        </label>
                                    </div>
                                    
                                    <button type="submit" name="record_prayer" class="btn-save">
                                        <i class="fas fa-save"></i> حفظ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- تبويب الأوراد الإضافية -->
        <!-- ============================================ -->
        <div id="tab-custom_wirds" style="display: <?php echo $active_tab == 'custom_wirds' ? 'block' : 'none'; ?>">
            <h3 style="margin-bottom: 15px;">
                <i class="fas fa-plus-circle"></i> 
                أوراد إضافية لـ <?php echo htmlspecialchars($student_name); ?>
            </h3>
            
            <!-- نموذج إضافة ورد جديد -->
            <div class="add-wird-form">
                <h4><i class="fas fa-plus-circle"></i> إضافة ورد جديد للطالب</h4>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label>اسم الورد</label>
                            <input type="text" name="wird_name" class="form-control" required placeholder="مثال: ورد قرآن - جزء عم">
                        </div>
                        <div class="form-group">
                            <label>نوع الورد</label>
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
                            <label>النقاط لكل وحدة</label>
                            <input type="number" name="points_per_unit" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>الهدف اليومي</label>
                            <input type="number" name="target_units" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>نوع الوحدة</label>
                            <select name="unit_type" class="form-control">
                                <option value="page">صفحة</option>
                                <option value="ayah">آية</option>
                                <option value="time">دقيقة</option>
                                <option value="count">عدد</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="add_custom_wird" class="btn-save" style="background: var(--primary);">
                        <i class="fas fa-save"></i> إضافة الورد للطالب
                    </button>
                </form>
            </div>
            
            <!-- قائمة الأوراد الإضافية -->
            <?php if (empty($custom_wirds)): ?>
                <div class="empty-state">
                    <i class="fas fa-star-and-crescent"></i>
                    <h3>لا توجد أوراد إضافية</h3>
                    <p>أضف ورداً جديداً للطالب باستخدام النموذج أعلاه</p>
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
                                <div class="wird-points"><?php echo $wird['points_per_unit']; ?> نقطة/<?php echo $wird['unit_type']; ?></div>
                            </div>
                            <div class="wird-content">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                                </div>
                                <div style="text-align: center; font-size: 0.8rem;">
                                    الإنجاز: <?php echo $total_units; ?>/<?php echo $target; ?> <?php echo $wird['unit_type']; ?>
                                </div>
                                
                                <form method="post">
                                    <input type="hidden" name="wird_id" value="<?php echo $wird['id']; ?>">
                                    <div class="counter-container">
                                        <button type="button" class="counter-btn minus" onclick="updateCustomCounter(this, -1, <?php echo $wird['id']; ?>)">−</button>
                                        <span class="counter-value" id="custom_counter_<?php echo $wird['id']; ?>">0</span>
                                        <button type="button" class="counter-btn plus" onclick="updateCustomCounter(this, 1, <?php echo $wird['id']; ?>)">+</button>
                                    </div>
                                    <input type="hidden" name="units_completed" id="custom_units_<?php echo $wird['id']; ?>" value="0">
                                    <button type="submit" name="record_custom_wird" class="btn-save">
                                        <i class="fas fa-save"></i> تسجيل التقدم
                                    </button>
                                </form>
                                
                                <div style="margin-top: 10px; text-align: left;">
                                    <a href="?student_id=<?php echo $selected_student; ?>&tab=custom_wirds&delete_custom_wird=<?php echo $wird['id']; ?>" 
                                       class="btn-save" style="background: var(--danger); text-decoration: none; display: inline-block; padding: 5px 15px; font-size: 0.8rem;"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا الورد؟')">
                                        <i class="fas fa-trash"></i> حذف
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif (empty($children)): ?>
        <div class="empty-state">
            <i class="fas fa-child"></i>
            <h3>لا يوجد أبناء مسجلين</h3>
            <p>لم يتم ربط أي طالب بحسابك بعد. يرجى التواصل مع الإدارة.</p>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-hand-point-left"></i>
            <h3>اختر أحد أبنائك من القائمة</h3>
            <p>يرجى اختيار طالب لتسجيل عباداته</p>
        </div>
    <?php endif; ?>
</div>

<script>
// تحديث عداد الورد الأساسي
function updateCounter(btn, change, wirdId) {
    const counterSpan = document.getElementById('counter_' + wirdId);
    const counterInput = document.getElementById('count_' + wirdId);
    let current = parseInt(counterSpan.innerText.replace(/,/g, '')) || 0;
    let newValue = current + change;
    if (newValue < 0) newValue = 0;
    counterSpan.innerText = newValue.toLocaleString();
    counterInput.value = newValue;
}

// تحديث عداد الورد الإضافي
function updateCustomCounter(btn, change, wirdId) {
    const counterSpan = document.getElementById('custom_counter_' + wirdId);
    const counterInput = document.getElementById('custom_units_' + wirdId);
    let current = parseInt(counterSpan.innerText) || 0;
    let newValue = current + change;
    if (newValue < 0) newValue = 0;
    counterSpan.innerText = newValue;
    counterInput.value = newValue;
}

// تفعيل خيارات الصلاة
document.querySelectorAll('.prayer-option').forEach(option => {
    option.addEventListener('click', function() {
        const parent = this.closest('.prayer-options');
        const radio = this.querySelector('input[type="radio"]');
        
        if (radio) {
            radio.checked = true;
            parent.querySelectorAll('.prayer-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
        }
    });
});

console.log('✅ لوحة العبادات لولي الأمر جاهزة');
</script>

<?php require_once 'includes/footer.php'; ?>