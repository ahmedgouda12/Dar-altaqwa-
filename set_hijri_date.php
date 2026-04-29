<?php
// ============================================
// ملف: set_hijri_date.php
// تعيين التاريخ الهجري يدوياً (للمسؤول فقط)
// ============================================

require_once 'config.php';
require_once 'hijri_date.php';

if (!isAdmin()) {
    redirect('login.php');
}

$message = '';
$error = '';

// معالجة تعيين التاريخ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_date'])) {
    $year = (int)$_POST['hijri_year'];
    $month = (int)$_POST['hijri_month'];
    $day = (int)$_POST['hijri_day'];
    
    if ($year > 1400 && $year < 1500 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 30) {
        setManualHijriDate($year, $month, $day);
        $message = "✅ تم تعيين التاريخ الهجري إلى: {$day} {$month} {$year} هـ";
    } else {
        $error = "❌ قيم غير صحيحة. تأكد من السنة (1400-1500)، الشهر (1-12)، اليوم (1-30)";
    }
}

$current = getCurrentHijriDate();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعيين التاريخ الهجري يدوياً</title>
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
            max-width: 550px;
            width: 100%;
            background: white;
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e3c3f;
            text-align: center;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .current-date {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        .current-date .label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        .current-date .date {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 8px;
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
        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-secondary {
            background: #6c757d;
            margin-top: 15px;
        }
        .alert {
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-right: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 4px solid #dc3545;
        }
        .info-box {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #0c5460;
        }
        hr {
            margin: 20px 0;
            border-color: #e9ecef;
        }
        @media (max-width: 480px) {
            .container { padding: 25px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-calendar-alt"></i> تعيين التاريخ الهجري</h1>
    
    <div class="current-date">
        <div class="label">التاريخ الحالي في النظام</div>
        <div class="date"><?php echo getFullHijriDate(); ?></div>
        <div style="margin-top: 5px; font-size: 0.8rem;"><?php echo date('Y-m-d'); ?> ميلادي</div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>اليوم</label>
                <input type="number" name="hijri_day" class="form-control" value="<?php echo $current['day']; ?>" min="1" max="30" required>
            </div>
            <div class="form-group">
                <label>الشهر</label>
                <select name="hijri_month" class="form-control" required>
                    <option value="1" <?php echo $current['month'] == 1 ? 'selected' : ''; ?>>محرم</option>
                    <option value="2" <?php echo $current['month'] == 2 ? 'selected' : ''; ?>>صفر</option>
                    <option value="3" <?php echo $current['month'] == 3 ? 'selected' : ''; ?>>ربيع الأول</option>
                    <option value="4" <?php echo $current['month'] == 4 ? 'selected' : ''; ?>>ربيع الآخر</option>
                    <option value="5" <?php echo $current['month'] == 5 ? 'selected' : ''; ?>>جمادى الأولى</option>
                    <option value="6" <?php echo $current['month'] == 6 ? 'selected' : ''; ?>>جمادى الآخرة</option>
                    <option value="7" <?php echo $current['month'] == 7 ? 'selected' : ''; ?>>رجب</option>
                    <option value="8" <?php echo $current['month'] == 8 ? 'selected' : ''; ?>>شعبان</option>
                    <option value="9" <?php echo $current['month'] == 9 ? 'selected' : ''; ?>>رمضان</option>
                    <option value="10" <?php echo $current['month'] == 10 ? 'selected' : ''; ?>>شوال</option>
                    <option value="11" <?php echo $current['month'] == 11 ? 'selected' : ''; ?>>ذو القعدة</option>
                    <option value="12" <?php echo $current['month'] == 12 ? 'selected' : ''; ?>>ذو الحجة</option>
                </select>
            </div>
            <div class="form-group">
                <label>السنة</label>
                <input type="number" name="hijri_year" class="form-control" value="<?php echo $current['year']; ?>" min="1400" max="1500" required>
            </div>
        </div>
        
        <button type="submit" name="set_date" class="btn"><i class="fas fa-save"></i> تعيين التاريخ</button>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> العودة للرئيسية</a>
    </form>
    
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <strong>ملاحظة:</strong> يمكنك تعديل التاريخ الهجري يدوياً إذا كان غير دقيق. 
        التاريخ الحالي الصحيح هو: <strong>13 شوال 1447 هـ</strong> (الموافق 1 أبريل 2026)
    </div>
    
    <hr>
    
    <div style="text-align: center; font-size: 0.8rem; color: #666;">
        <a href="refresh_hijri.php" style="color: #1e3c3f;">تحديث من API</a> | 
        <a href="fix_hijri_display.php" style="color: #1e3c3f;">اختبار التاريخ</a>
    </div>
</div>
</body>
</html>