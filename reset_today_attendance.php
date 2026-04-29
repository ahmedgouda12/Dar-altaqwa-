<?php
require_once 'config.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// التحقق من وجود رمز أمان لمنع التنفيذ العشوائي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $security_code = $_POST['security_code'] ?? '';
    
    // رمز أمان بسيط (يمكن تغييره)
    if ($security_code !== 'reset2026') {
        $error = '❌ رمز الأمان غير صحيح';
    } else {
        $today = date('Y-m-d');
        
        try {
            // حذف جميع تسجيلات اليوم للمعلمين والطلاب
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = ?");
            $stmt->execute([$today]);
            
            $count = $stmt->rowCount();
            $message = "✅ تم حذف $count تسجيل لليوم $today بنجاح.";
            
        } catch (PDOException $e) {
            $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

// جلب إحصائيات اليوم لعرضها قبل الحذف
$today = date('Y-m-d');
$stats = [
    'teachers' => 0,
    'students' => 0,
    'total' => 0
];

$stmt = $pdo->prepare("SELECT person_type, COUNT(*) as count FROM attendance WHERE date = ? GROUP BY person_type");
$stmt->execute([$today]);
$results = $stmt->fetchAll();

foreach ($results as $row) {
    if ($row['person_type'] == 'teacher') {
        $stats['teachers'] = $row['count'];
    } elseif ($row['person_type'] == 'student') {
        $stats['students'] = $row['count'];
    }
}
$stats['total'] = $stats['teachers'] + $stats['students'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين تسجيلات اليوم - دار التقوى</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            width: 100%;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        h2 {
            color: #1e3c3f;
            text-align: center;
            margin-bottom: 20px;
        }
        .warning-box {
            background: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-right: 5px solid #ffc107;
        }
        .stats-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: scale(1.02);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3e50;
        }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .code-hint {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>🔄 إعادة تعيين تسجيلات اليوم</h2>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="warning-box">
                <strong>⚠️ تحذير!</strong>
                <p>هذا الإجراء سيقوم بحذف <u>جميع</u> تسجيلات الحضور لليوم <?php echo $today; ?> بشكل نهائي.</p>
                <p>لا يمكن التراجع عن هذا الإجراء.</p>
            </div>
            
            <div class="stats-box">
                <h3 style="margin-bottom: 15px;">📊 إحصائيات اليوم</h3>
                <div class="stat-item">
                    <span>المعلمين المسجلين:</span>
                    <strong><?php echo $stats['teachers']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>الطلاب المسجلين:</span>
                    <strong><?php echo $stats['students']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>إجمالي التسجيلات:</span>
                    <strong><?php echo $stats['total']; ?></strong>
                </div>
            </div>
            
            <form method="post">
                <div class="form-group">
                    <label>🔑 رمز الأمان</label>
                    <input type="password" name="security_code" required placeholder="أدخل رمز الأمان">
                </div>
                <div class="code-hint">
                    رمز الأمان الافتراضي: <strong>reset2026</strong> (يمكنك تغييره لاحقاً)
                </div>
                
                <button type="submit" class="btn-danger" onclick="return confirm('هل أنت متأكد تماماً من حذف جميع تسجيلات اليوم؟ هذا الإجراء لا يمكن التراجع عنه!')">
                    <i class="fas fa-trash"></i> حذف جميع تسجيلات اليوم
                </button>
                
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
                </a>
            </form>
        </div>
    </div>
</body>
</html>