<?php
// ============================================
// ملف: my_requests.php - عرض طلبات الطالب
// ============================================

ob_start();
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'طلباتي - دار التقوى';
$error = '';
$success = '';

// التحقق من الجلسة (يمكن للطالب استخدام رقم الهاتف مؤقتاً أو تسجيل الدخول)
$student_phone = $_SESSION['student_phone'] ?? '';
$student_account_id = $_SESSION['student_account_id'] ?? 0;

// إذا لم يكن هناك جلسة، نعرض نموذج إدخال رقم الهاتف
if (empty($student_phone) && empty($student_account_id) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>عرض طلباتي - دار التقوى</title>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
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
                max-width: 450px;
                width: 100%;
                background: white;
                border-radius: 30px;
                padding: 30px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
            }
            .container h2 {
                color: #1e3c3f;
                margin-bottom: 20px;
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
                padding: 12px;
                border: 2px solid #e9ecef;
                border-radius: 12px;
                font-size: 1rem;
            }
            .btn {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
                color: white;
                border: none;
                border-radius: 50px;
                font-size: 1rem;
                font-weight: bold;
                cursor: pointer;
            }
            .btn-secondary {
                background: #c9a96b;
                margin-top: 10px;
            }
            .alert {
                padding: 12px;
                border-radius: 12px;
                margin-bottom: 20px;
                background: #f8d7da;
                color: #721c24;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <h2><i class="fas fa-search"></i> عرض طلباتي</h2>
        <p>أدخل رقم هاتف ولي الأمر المرتبط بالطلبات</p>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert">❌ رقم الهاتف غير صحيح أو لا توجد طلبات</div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                <input type="tel" name="phone" class="form-control" required placeholder="01012345678" dir="ltr">
            </div>
            <button type="submit" class="btn"><i class="fas fa-list-alt"></i> عرض طلباتي</button>
            <a href="enroll.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none; margin-top: 10px;">
                <i class="fas fa-arrow-right"></i> العودة للتقديم
            </a>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// معالجة إدخال رقم الهاتف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'])) {
    $phone = trim($_POST['phone']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_requests WHERE parent_phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['temp_phone'] = $phone;
        header("Location: my_requests.php");
        exit;
    } else {
        header("Location: my_requests.php?error=1");
        exit;
    }
}

// جلب رقم الهاتف من الجلسة
$search_phone = $_SESSION['student_phone'] ?? $_SESSION['temp_phone'] ?? '';

// جلب جميع طلبات الطالب
$requests = $pdo->prepare("
    SELECT r.*, 
           CASE r.status 
               WHEN 'pending' THEN 'قيد الانتظار'
               WHEN 'approved_by_teacher' THEN 'موافقة معلم'
               WHEN 'assigned' THEN 'تم التوزيع'
               WHEN 'rejected' THEN 'مرفوض'
           END as status_text,
           CASE r.status 
               WHEN 'pending' THEN 'warning'
               WHEN 'approved_by_teacher' THEN 'info'
               WHEN 'assigned' THEN 'success'
               WHEN 'rejected' THEN 'danger'
           END as status_class
    FROM enrollment_requests r
    WHERE r.parent_phone = ? OR r.account_id = ?
    ORDER BY r.created_at DESC
");
$requests->execute([$search_phone, $student_account_id]);
$requests = $requests->fetchAll();

// معالجة حذف طلب
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $request_id = (int)$_GET['delete'];
    $token = $_GET['token'];
    
    $check = $pdo->prepare("SELECT id, edit_token, status FROM enrollment_requests WHERE id = ? AND (parent_phone = ? OR account_id = ?)");
    $check->execute([$request_id, $search_phone, $student_account_id]);
    $req = $check->fetch();
    
    if ($req && $req['edit_token'] === $token && $req['status'] == 'pending') {
        $pdo->prepare("DELETE FROM enrollment_requests WHERE id = ?")->execute([$request_id]);
        $success = "✅ تم حذف الطلب بنجاح";
        header("Location: my_requests.php?success=deleted");
        exit;
    }
}

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
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
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --radius: 20px;
            --radius-sm: 12px;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .requests-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: var(--radius);
            margin-bottom: 30px;
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
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            position: relative;
            z-index: 2;
        }
        
        .page-header p {
            position: relative;
            z-index: 2;
            opacity: 0.9;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--primary);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .request-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
            position: relative;
            border-top: 5px solid;
        }
        
        .request-card.status-pending { border-top-color: var(--warning); }
        .request-card.status-approved_by_teacher { border-top-color: var(--info); }
        .request-card.status-assigned { border-top-color: var(--success); }
        .request-card.status-rejected { border-top-color: var(--danger); }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        
        .request-number {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .student-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .request-date {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved_by_teacher { background: #d1ecf1; color: #0c5460; }
        .status-assigned { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .request-details {
            background: #f8f9fa;
            border-radius: var(--radius-sm);
            padding: 12px;
            margin: 15px 0;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            font-size: 0.9rem;
        }
        
        .detail-row i {
            width: 25px;
            color: var(--secondary);
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: var(--radius);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-light);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .requests-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="requests-container">
    <div class="page-header">
        <h1><i class="fas fa-list-alt"></i> طلباتي</h1>
        <p>عرض جميع طلبات الالتحاق السابقة وحالتها</p>
    </div>
    
    <div class="action-buttons">
        <a href="enroll.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> تقديم طلب جديد
        </a>
        <a href="student_login.php" class="btn btn-outline">
            <i class="fas fa-user-circle"></i> تسجيل الدخول لحفظ الطلبات
        </a>
        <?php if ($search_phone): ?>
        <a href="?logout=1" class="btn btn-warning">
            <i class="fas fa-sign-out-alt"></i> تغيير رقم الهاتف
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($success_message == 'deleted'): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> تم حذف الطلب بنجاح
        </div>
    <?php endif; ?>
    
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>لا توجد طلبات</h3>
            <p>لم تقم بتقديم أي طلب التحاق بعد</p>
            <a href="enroll.php" class="btn btn-primary" style="display: inline-block; margin-top: 15px;">
                <i class="fas fa-plus-circle"></i> تقديم طلب جديد
            </a>
        </div>
    <?php else: ?>
        <div class="requests-grid">
            <?php foreach ($requests as $req): 
                $mem_surahs = json_decode($req['memorized_surahs'], true) ?: [];
                $mem_parts = json_decode($req['memorized_parts'], true) ?: [];
                $total_parts = count($mem_parts);
                $total_surahs = count($mem_surahs);
                $can_edit = ($req['status'] == 'pending');
                $edit_link = "edit_enrollment.php?id={$req['id']}&token={$req['edit_token']}";
            ?>
                <div class="request-card status-<?php echo $req['status']; ?>">
                    <div class="request-number">
                        <i class="fas fa-qrcode"></i> رقم الطلب: <?php echo $req['request_number']; ?>
                    </div>
                    <div class="student-name">
                        <?php echo htmlspecialchars($req['student_name']); ?>
                    </div>
                    <div class="request-date">
                        <i class="fas fa-calendar-alt"></i> تاريخ التقديم: <?php echo date('Y-m-d', strtotime($req['created_at'])); ?>
                    </div>
                    <div class="status-badge status-<?php echo $req['status']; ?>">
                        <i class="fas <?php 
                            echo $req['status'] == 'pending' ? 'fa-clock' : 
                                ($req['status'] == 'approved_by_teacher' ? 'fa-check-circle' : 
                                ($req['status'] == 'assigned' ? 'fa-user-check' : 'fa-times-circle')); 
                        ?>"></i>
                        <?php echo $req['status_text']; ?>
                    </div>
                    
                    <div class="request-details">
                        <div class="detail-row">
                            <i class="fas fa-venus-mars"></i>
                            <span><?php echo $req['student_gender'] == 'male' ? 'ذكر' : 'أنثى'; ?> | العمر: <?php echo $req['student_age']; ?> سنة</span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-quran"></i>
                            <span>المحفوظات: <?php echo $total_parts; ?> جزء، <?php echo $total_surahs; ?> سورة</span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-user-tie"></i>
                            <span>ولي الأمر: <?php echo htmlspecialchars($req['parent_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-phone"></i>
                            <span dir="ltr"><?php echo htmlspecialchars($req['parent_phone']); ?></span>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <?php if ($can_edit): ?>
                            <a href="<?php echo $edit_link; ?>" class="btn btn-warning" style="flex:1; justify-content: center;">
                                <i class="fas fa-edit"></i> تعديل الطلب
                            </a>
                            <a href="?delete=<?php echo $req['id']; ?>&token=<?php echo $req['edit_token']; ?>" 
                               class="btn btn-outline" style="flex:1; justify-content: center; border-color: var(--danger); color: var(--danger);"
                               onclick="return confirm('هل أنت متأكد من حذف هذا الطلب؟ لا يمكن التراجع.')">
                                <i class="fas fa-trash"></i> حذف
                            </a>
                        <?php elseif ($req['status'] == 'assigned'): ?>
                            <a href="view_progress.php?student_id=<?php echo $req['id']; ?>" class="btn btn-success" style="flex:1; justify-content: center;">
                                <i class="fas fa-chart-line"></i> متابعة التقدم
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline" style="flex:1; justify-content: center; opacity: 0.6; cursor: not-allowed;" disabled>
                                <i class="fas fa-lock"></i> غير قابل للتعديل
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>