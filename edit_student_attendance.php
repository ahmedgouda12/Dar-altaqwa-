<?php
// ============================================
// ملف: edit_student_attendance.php
// تعديل حضور الطلاب - تصميم متجاوب بالكامل مع الهاتف
// آخر تحديث: 2026-04-20
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'تعديل حضور الطلاب';
require_once 'includes/header.php';

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$message = '';
$message_type = '';

// جلب طلاب المعلم
$students = $pdo->prepare("
    SELECT s.id, s.name, s.category, s.level, s.parent_phone
    FROM students s 
    WHERE s.teacher_id = ?
    ORDER BY s.name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// جلب تسجيلات الحضور لليوم
$attendance_today = [];
if (!empty($students)) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT a.*, s.name as student_name, s.category 
            FROM attendance a
            JOIN students s ON a.person_id = s.id
            WHERE a.person_type = 'student' AND a.date = ? AND a.person_id IN ($placeholders)
            ORDER BY s.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today, ...$ids]);
    $attendance_today = $stmt->fetchAll();
}

// معالجة تعديل الحضور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attendance'])) {
    $attendance_id = (int)$_POST['attendance_id'];
    $new_status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    $is_excused = isset($_POST['is_excused']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET status = ?, notes = ?, is_excused = ? 
            WHERE id = ? AND person_type = 'student'
        ");
        $stmt->execute([$new_status, $notes, $is_excused, $attendance_id]);
        
        $message = "✅ تم تحديث حالة الحضور بنجاح";
        $message_type = 'success';
        
        // إعادة تحميل البيانات
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today, ...$ids]);
        $attendance_today = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $message_type = 'error';
    }
}

// إحصائيات سريعة
$present_count = count(array_filter($attendance_today, fn($a) => $a['status'] == 'present' && $a['is_excused'] == 0));
$absent_count = count(array_filter($attendance_today, fn($a) => $a['status'] == 'absent' && $a['is_excused'] == 0));
$late_count = count(array_filter($attendance_today, fn($a) => $a['status'] == 'late'));
$excused_count = count(array_filter($attendance_today, fn($a) => $a['is_excused'] == 1));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>تعديل حضور الطلاب - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ===== المتغيرات والتنسيقات الأساسية ===== */
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
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
            --whatsapp: #25d366;
            
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-full: 9999px;
            
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 16px;
        }
        
        /* ===== الحاوية الرئيسية ===== */
        .edit-page {
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* ===== رأس الصفحة ===== */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 24px 20px;
            border-radius: var(--radius-xl);
            margin-bottom: 20px;
            text-align: center;
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
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            z-index: 2;
        }
        
        .page-header h1 i {
            color: var(--secondary);
        }
        
        .date-badge {
            background: rgba(255,255,255,0.15);
            padding: 6px 16px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(5px);
        }
        
        /* ===== رسائل التنبيه ===== */
        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        /* ===== بطاقات الإحصائيات (شبكة مرنة) ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid #eee;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
        }
        
        .stat-icon.present { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.absent { background: linear-gradient(135deg, #dc3545, #c82333); }
        .stat-icon.late { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .stat-icon.excused { background: linear-gradient(135deg, #17a2b8, #138496); }
        
        .stat-content {
            flex: 1;
            min-width: 0;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.7rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* ===== قائمة الطلاب (بطاقات بدلاً من جدول) ===== */
        .students-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .student-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 16px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid #eee;
            animation: slideUp 0.3s ease;
            animation-fill-mode: both;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .student-card:active {
            transform: scale(0.99);
        }
        
        /* صفوف مرنة داخل البطاقة */
        .student-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: white;
            flex-shrink: 0;
        }
        
        .student-info {
            flex: 1;
            min-width: 120px;
        }
        
        .student-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        .student-details {
            font-size: 0.7rem;
            color: var(--gray);
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .student-details i {
            color: var(--secondary);
        }
        
        /* شارة الحالة */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-present {
            background: var(--success-light);
            color: #155724;
        }
        
        .status-absent {
            background: var(--danger-light);
            color: #721c24;
        }
        
        .status-late {
            background: var(--warning-light);
            color: #856404;
        }
        
        .status-excused {
            background: var(--info-light);
            color: #0c5460;
        }
        
        /* وقت التسجيل */
        .record-time {
            font-size: 0.7rem;
            color: var(--gray);
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            white-space: nowrap;
        }
        
        /* زر التعديل */
        .edit-btn {
            background: var(--warning);
            color: #212529;
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-full);
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        .edit-btn:active {
            transform: scale(0.95);
        }
        
        /* حالة عدم وجود بيانات */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: var(--radius-xl);
            margin-bottom: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 12px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 16px;
        }
        
        /* ===== النافذة المنبثقة (محسنة للهاتف) ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            align-items: flex-end;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            width: 100%;
            max-width: 500px;
            animation: slideUp 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .close-modal:active {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .student-name-box {
            background: var(--gray-light);
            padding: 14px;
            border-radius: var(--radius-md);
            text-align: center;
            margin-bottom: 20px;
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            word-break: break-word;
        }
        
        /* خيارات الحالة (شبكة مرنة) */
        .status-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .status-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 8px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.9rem;
            background: white;
        }
        
        .status-option:active {
            transform: scale(0.97);
        }
        
        .status-option.selected {
            border-color: var(--secondary);
            background: var(--secondary);
            color: white;
        }
        
        .status-option input {
            display: none;
        }
        
        /* خيار المعذور */
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 14px;
            background: var(--info-light);
            border-radius: var(--radius-md);
            color: #0c5460;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .checkbox-label:active {
            opacity: 0.8;
        }
        
        .checkbox-label input {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--info);
        }
        
        /* حقول النموذج */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .form-group label i {
            color: var(--secondary);
            margin-left: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: var(--transition);
            font-family: 'Cairo', sans-serif;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        
        /* أزرار النافذة */
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn {
            flex: 1;
            padding: 14px;
            border-radius: var(--radius-full);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn:active {
            transform: scale(0.97);
        }
        
        /* زر العودة */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gray);
            color: white;
            padding: 12px 20px;
            border-radius: var(--radius-full);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 16px;
            transition: var(--transition);
        }
        
        .back-btn:active {
            transform: scale(0.97);
        }
        
        /* ===== تحسينات إضافية للهاتف ===== */
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            .stats-grid {
                gap: 10px;
            }
            
            .stat-icon {
            	 width: 44px;
                height: 44px;
                font-size: 1.1rem;
            }
            
            .stat-number {
                font-size: 1.3rem;
            }
            
            .student-avatar {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            
            .student-name {
                font-size: 0.9rem;
            }
            
            .status-options {
                grid-template-columns: 1fr;
            }
            
            .modal-actions {
                flex-direction: column;
            }
            
            .modal-header h3 {
                font-size: 1rem;
            }
        }
        
        /* للشاشات الصغيرة جداً */
        @media (max-width: 360px) {
            .student-row {
                flex-direction: column;
                text-align: center;
            }
            
            .student-info {
                text-align: center;
            }
            
            .student-details {
                justify-content: center;
            }
            
            .edit-btn {
                width: 100%;
                justify-content: center;
            }
            
            .record-time {
                width: 100%;
                text-align: center;
            }
        }
        
        /* تحسين اللمس */
        button, 
        .stat-card,
        .student-card,
        .status-option,
        .checkbox-label,
        .back-btn,
        .edit-btn {
            cursor: pointer;
            touch-action: manipulation;
        }
        
        /* إخفاء شريط التمرير الزائد */
        .modal-content::-webkit-scrollbar {
            width: 4px;
        }
        
        .modal-content::-webkit-scrollbar-track {
            background: var(--gray-light);
        }
        
        .modal-content::-webkit-scrollbar-thumb {
            background: var(--secondary);
            border-radius: 4px;
        }
    </style>
</head>
<body>

<section class="edit-page">
    <!-- زر العودة -->
    <a href="teacher_dashboard.php" class="back-btn">
        <i class="fas fa-arrow-right"></i>
        العودة
    </a>
    
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            تعديل حضور الطلاب
        </h1>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?php echo $today; ?>
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card" onclick="filterByStatus('present')">
            <div class="stat-icon present"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $present_count; ?></div>
                <div class="stat-label">✅ حاضر</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterByStatus('absent')">
            <div class="stat-icon absent"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $absent_count; ?></div>
                <div class="stat-label">❌ غائب</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterByStatus('late')">
            <div class="stat-icon late"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $late_count; ?></div>
                <div class="stat-label">⏰ متأخر</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterByStatus('excused')">
            <div class="stat-icon excused"><i class="fas fa-calendar-times"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $excused_count; ?></div>
                <div class="stat-label">📋 معتذر</div>
            </div>
        </div>
    </div>

    <!-- قائمة الطلاب -->
    <?php if (empty($attendance_today)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>لا توجد سجلات حضور اليوم</h3>
            <p>لم يتم تسجيل حضور أي طالب لهذا اليوم بعد</p>
            <a href="attendance_students_teacher.php" class="edit-btn" style="background: var(--primary); color: white; text-decoration: none;">
                <i class="fas fa-plus-circle"></i> تسجيل حضور جديد
            </a>
        </div>
    <?php else: ?>
        <div class="students-list" id="studentsList">
            <?php foreach ($attendance_today as $index => $att):
                $status_class = '';
                $status_text = '';
                if ($att['is_excused'] == 1) {
                    $status_class = 'excused';
                    $status_text = 'معتذر';
                } elseif ($att['status'] == 'present') {
                    $status_class = 'present';
                    $status_text = 'حاضر';
                } elseif ($att['status'] == 'absent') {
                    $status_class = 'absent';
                    $status_text = 'غائب';
                } elseif ($att['status'] == 'late') {
                    $status_class = 'late';
                    $status_text = 'متأخر';
                }
                
                $avatar_color = match($att['category']) {
                    'boy' => '#3498db',
                    'girl' => '#9b59b6',
                    'child' => '#f39c12',
                    'woman' => '#e84342',
                    default => '#1e3c3f'
                };
            ?>
                <div class="student-card" data-status="<?php echo $status_class; ?>" style="animation-delay: <?php echo $index * 0.05; ?>s">
                    <div class="student-row">
                        <div class="student-avatar" style="background: <?php echo $avatar_color; ?>;">
                            <?php echo mb_substr($att['student_name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div class="student-info">
                            <div class="student-name"><?php echo htmlspecialchars($att['student_name']); ?></div>
                            <div class="student-details">
                                <span><i class="fas fa-tag"></i> <?php echo match($att['category']) {
                                    'boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء', default => ''
                                }; ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $status_class; ?>">
                                <?php if ($status_class == 'present'): ?>
                                    <i class="fas fa-check-circle"></i> <?php echo $status_text; ?>
                                <?php elseif ($status_class == 'absent'): ?>
                                    <i class="fas fa-times-circle"></i> <?php echo $status_text; ?>
                                <?php elseif ($status_class == 'late'): ?>
                                    <i class="fas fa-clock"></i> <?php echo $status_text; ?>
                                <?php elseif ($status_class == 'excused'): ?>
                                    <i class="fas fa-calendar-times"></i> <?php echo $status_text; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="record-time">
                            <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($att['created_at'])); ?>
                        </div>
                        <button class="edit-btn" onclick="openEditModal(<?php echo $att['id']; ?>, '<?php echo addslashes($att['student_name']); ?>', '<?php echo $att['status']; ?>', <?php echo $att['is_excused']; ?>, '<?php echo addslashes($att['notes'] ?? ''); ?>')">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- نافذة تعديل الحضور -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> تعديل حالة الحضور</h3>
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post" id="editForm">
            <input type="hidden" name="attendance_id" id="attendanceId">
            <div class="modal-body">
                <div class="student-name-box" id="studentNameDisplay"></div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> حالة الحضور</label>
                    <div class="status-options" id="statusOptions">
                        <div class="status-option" data-status="present" onclick="selectStatus('present')">
                            <i class="fas fa-check-circle"></i> حاضر
                            <input type="radio" name="status" value="present">
                        </div>
                        <div class="status-option" data-status="absent" onclick="selectStatus('absent')">
                            <i class="fas fa-times-circle"></i> غائب
                            <input type="radio" name="status" value="absent">
                        </div>
                        <div class="status-option" data-status="late" onclick="selectStatus('late')">
                            <i class="fas fa-clock"></i> متأخر
                            <input type="radio" name="status" value="late">
                        </div>
                    </div>
                </div>
                
                <div class="checkbox-label">
                    <input type="checkbox" name="is_excused" id="isExcused" value="1" onchange="toggleExcused(this.checked)">
                    <i class="fas fa-calendar-times"></i>
                    <span>غياب معذور (لا يحسب في الغياب التلقائي)</span>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                    <textarea name="notes" id="attendanceNotes" class="form-control" rows="3" placeholder="أدخل ملاحظاتك هنا..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">إلغاء</button>
                    <button type="submit" name="edit_attendance" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentStatus = '';

// فلترة حسب الحالة
function filterByStatus(status) {
    const cards = document.querySelectorAll('.student-card');
    let hasVisible = false;
    
    cards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
            hasVisible = true;
        } else {
            card.style.display = 'none';
        }
    });
    
    // تحديث النمط النشط للإحصائيات
    document.querySelectorAll('.stat-card').forEach(card => {
        card.style.opacity = '0.7';
    });
    event.currentTarget.style.opacity = '1';
    
    // عرض رسالة إذا لم تكن هناك نتائج
    const noResultsMsg = document.getElementById('noResultsMsg');
    if (!hasVisible) {
        if (!noResultsMsg) {
            const msg = document.createElement('div');
            msg.id = 'noResultsMsg';
            msg.className = 'empty-state';
            msg.innerHTML = '<i class="fas fa-filter"></i><h3>لا توجد نتائج</h3><p>لا يوجد طلاب بهذه الحالة</p>';
            document.querySelector('.students-list').appendChild(msg);
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}

// فتح نافذة التعديل
function openEditModal(id, name, status, isExcused, notes) {
    document.getElementById('attendanceId').value = id;
    document.getElementById('studentNameDisplay').innerHTML = '<i class="fas fa-user-graduate"></i> ' + name;
    document.getElementById('attendanceNotes').value = notes;
    
    // تحديد الحالة
    currentStatus = status;
    document.querySelectorAll('.status-option').forEach(opt => {
        opt.classList.remove('selected');
        if (opt.dataset.status === status) {
            opt.classList.add('selected');
            opt.querySelector('input').checked = true;
        }
    });
    
    // تحديد خانة المعذور
    const excusedCheckbox = document.getElementById('isExcused');
    if (excusedCheckbox) {
        excusedCheckbox.checked = isExcused == 1;
        toggleExcused(isExcused == 1);
    }
    
    document.getElementById('editModal').classList.add('show');
    // منع تمرير الخلفية
    document.body.style.overflow = 'hidden';
}

// إغلاق النافذة
function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.body.style.overflow = '';
}

// اختيار الحالة
function selectStatus(status) {
    currentStatus = status;
    document.querySelectorAll('.status-option').forEach(opt => {
        opt.classList.remove('selected');
        if (opt.dataset.status === status) {
            opt.classList.add('selected');
            opt.querySelector('input').checked = true;
        }
    });
}

// تفعيل/إلغاء خيار المعذور
function toggleExcused(checked) {
    const statusOptions = document.getElementById('statusOptions');
    if (checked) {
        statusOptions.style.opacity = '0.5';
        statusOptions.style.pointerEvents = 'none';
        // إضافة حقل مخفي
        let hiddenInput = document.getElementById('excusedStatus');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'status';
            hiddenInput.value = 'absent';
            hiddenInput.id = 'excusedStatus';
            document.getElementById('editForm').appendChild(hiddenInput);
        }
    } else {
        statusOptions.style.opacity = '1';
        statusOptions.style.pointerEvents = 'auto';
        let hiddenInput = document.getElementById('excusedStatus');
        if (hiddenInput) hiddenInput.remove();
    }
}

// إغلاق النافذة بالنقر خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeEditModal();
    }
}

// تأثير ظهور تدريجي للبطاقات
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.student-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
    
    console.log('✅ صفحة تعديل حضور الطلاب - نسخة متجاوبة مع الهاتف');
});
</script>

<?php require_once 'includes/footer.php'; ?>