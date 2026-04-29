<?php
// ============================================
// ملف: certificate_view.php - نسخة محدثة
// آخر تحديث: 2026-03-15
// ============================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isset($_SESSION['user_id'])) {
    die('غير مصرح بالوصول');
}

$achievement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$achievement_id) {
    die('معرف الشهادة مطلوب');
}

try {
    // استعلام محدث - بدون الأعمدة القديمة
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.student_id,
            a.goal_id,
            a.teacher_id,
            a.achieved_at,
            a.certificate_number,
            a.approved_by,
            s.name as student_name,
            s.level as student_level,
            g.from_surah,
            g.from_ayah,
            g.to_surah,
            g.to_ayah,
            g.hijri_year,
            g.hijri_month_id,
            t.name as teacher_name
        FROM student_achievements a
        INNER JOIN students s ON a.student_id = s.id
        LEFT JOIN student_monthly_goals g ON a.goal_id = g.id
        LEFT JOIN teachers t ON a.approved_by = t.id
        WHERE a.id = ?
    ");
    $stmt->execute([$achievement_id]);
    $data = $stmt->fetch();

    if (!$data) {
        die('لم يتم العثور على الشهادة');
    }

    // التحقق من الصلاحية
    $is_teacher = ($_SESSION['user_type'] == 'teacher' && $data['teacher_id'] == $_SESSION['user_id']);
    $is_admin = ($_SESSION['user_type'] == 'admin');
    $is_student = ($_SESSION['user_type'] == 'student' && $data['student_id'] == $_SESSION['user_id']);

    if (!$is_teacher && !$is_admin && !$is_student) {
        die('غير مصرح لك بمشاهدة هذه الشهادة');
    }

    // جلب اسم الشهر الهجري
    $month_name = '';
    if (!empty($data['hijri_month_id'])) {
        $month_stmt = $pdo->prepare("SELECT name_ar FROM hijri_months WHERE id = ?");
        $month_stmt->execute([$data['hijri_month_id']]);
        $month_name = $month_stmt->fetchColumn();
    }

    // التاريخ الهجري
    $hijri_date = ($month_name ? $month_name . ' ' : '') . ($data['hijri_year'] ?? '') . ' هـ';
    $gregorian_date = date('Y/m/d', strtotime($data['achieved_at']));
    
    // تكوين نص نطاق الحفظ
    $from_name = getSurahName($data['from_surah']);
    $to_name = getSurahName($data['to_surah']);
    $range_text = "من ";
    if ($data['from_ayah']) $range_text .= "الآية {$data['from_ayah']} ";
    $range_text .= "من سورة {$from_name} إلى ";
    if ($data['to_ayah']) $range_text .= "الآية {$data['to_ayah']} ";
    $range_text .= "من سورة {$to_name}";
    
    // جلب اسم المدير من الإعدادات
    $director_name = 'أ. وليد هاشم';
    try {
        $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
        if ($settings) $director_name = $settings['director_name'] ?? $director_name;
    } catch (PDOException $e) {
        // تجاهل الخطأ
    }
    
} catch (PDOException $e) {
    die('خطأ في قاعدة البيانات: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شهادة إنجاز - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .certificate-wrapper {
            max-width: 1000px;
            width: 100%;
            background: #f9f1e0;
            padding: 20px;
            border-radius: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .certificate {
            background: white;
            border: 15px solid #c9a96b;
            border-radius: 30px;
            padding: 30px;
            position: relative;
        }
        .certificate::before {
            content: "﷽";
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 80px;
            font-family: 'Amiri', serif;
            color: #c9a96b;
            opacity: 0.1;
        }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px dashed #c9a96b; padding-bottom: 20px; }
        .logo-text { font-size: 2rem; font-weight: 800; color: #1e3c3f; }
        .address { color: #666; margin-top: 5px; }
        .content { text-align: center; margin: 30px 0; }
        .student-name {
            font-size: 3rem; font-weight: 800; color: white;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            display: inline-block; padding: 15px 40px; border-radius: 80px;
            margin: 20px 0; border: 4px solid #c9a96b;
        }
        .range-box {
            background: #f8f9fa; border: 3px solid #c9a96b; border-radius: 60px;
            padding: 20px; max-width: 80%; margin: 20px auto;
        }
        .range-text { font-size: 1.5rem; font-weight: 700; color: #1e3c3f; }
        .date-box {
            display: flex; justify-content: center; gap: 20px; margin: 20px 0;
        }
        .date-item {
            background: #f8f9fa; padding: 8px 20px; border-radius: 50px;
            border: 2px solid #c9a96b;
        }
        .signatures {
            display: flex; justify-content: space-around; margin: 40px 0 20px;
        }
        .signature-line { width: 200px; height: 2px; background: #1e3c3f; margin: 10px 0; }
        .btn { padding: 12px 30px; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; margin: 10px; }
        .btn-print { background: #1e3c3f; color: white; }
        @media print { .btn { display: none; } }
    </style>
</head>
<body>
    <div class="certificate-wrapper">
        <div class="certificate">
            <div class="header">
                <div class="logo-text">دار التقوى لتحفيظ القرآن الكريم</div>
                <div class="address">منيا القمح - الشرقية</div>
            </div>
            <div class="content">
                <div style="font-size: 1.3rem;">تـشهد إدارة دار التقوى</div>
                <div class="student-name"><?php echo htmlspecialchars($data['student_name']); ?></div>
                <div style="font-size: 1.3rem;">على إكمال الهدف الشهري</div>
                <div class="range-box">
                    <div class="range-text"><?php echo $range_text; ?></div>
                </div>
                <div class="date-box">
                    <div class="date-item"><?php echo $gregorian_date; ?> م</div>
                    <div class="date-item"><?php echo $hijri_date; ?></div>
                </div>
            </div>
            <div class="signatures">
                <div style="text-align: center;">
                    <div class="signature-line"></div>
                    <div><?php echo htmlspecialchars($data['teacher_name'] ?? '........................'); ?></div>
                    <div>معلم المادة</div>
                </div>
                <div style="text-align: center;">
                    <div class="signature-line"></div>
                    <div><?php echo $director_name; ?></div>
                    <div>مدير دار التقوى</div>
                </div>
            </div>
            <div style="text-align: left; color: #999; font-size: 12px;"><?php echo $data['certificate_number']; ?></div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> طباعة الشهادة</button>
            <button onclick="window.history.back()" class="btn" style="background: #6c757d; color: white;">عودة</button>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</body>
</html>
<?php exit; ?>