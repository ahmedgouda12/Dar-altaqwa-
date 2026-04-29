<?php
// ============================================
// ملف: generate_certificate_pdf.php
// توليد شهادة PDF باستخدام dompdf
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

// تضمين مكتبة dompdf
require_once __DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isTeacher() && !isAdmin() && !isStudent()) {
    die('غير مصرح بالوصول');
}

$achievement_id = isset($_GET['achievement_id']) ? (int)$_GET['achievement_id'] : 0;

if (!$achievement_id) {
    die('بيانات غير كاملة');
}

// جلب بيانات الشهادة
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        s.name as student_name,
        s.id as student_id,
        g.target_surahs,
        g.target_pages,
        g.target_ayahs,
        g.hijri_year,
        hm.name_ar as month_name,
        t.name as teacher_name,
        t.id as teacher_id
    FROM student_achievements a
    JOIN students s ON a.student_id = s.id
    JOIN student_monthly_goals g ON a.goal_id = g.id
    JOIN hijri_months hm ON g.hijri_month_id = hm.id
    LEFT JOIN teachers t ON a.approved_by = t.id
    WHERE a.id = ?
");
$stmt->execute([$achievement_id]);
$data = $stmt->fetch();

if (!$data) {
    die('لم يتم العثور على الشهادة');
}

// التحقق من الصلاحية
if (isStudent() && $data['student_id'] != $_SESSION['user_id']) {
    die('غير مصرح لك بعرض هذه الشهادة');
}

if (isTeacher() && $data['teacher_id'] != $_SESSION['user_id']) {
    die('غير مصرح لك بعرض هذه الشهادة');
}

// تكوين خيارات PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Cairo');
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);

// محتوى HTML للشهادة
$html = '
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>شهادة إنجاز - دار التقوى</title>
    <style>
        @font-face {
            font-family: "Cairo";
            font-style: normal;
            font-weight: 400;
            src: url("https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap");
        }
        
        @font-face {
            font-family: "Amiri";
            font-style: normal;
            font-weight: 400;
            src: url("https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap");
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Cairo", sans-serif;
            background: #f5f0e6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .certificate {
            width: 1100px;
            background: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            margin: 0 auto;
        }
        
        /* الخلفية المزخرفة */
        .certificate::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M30 0 L60 30 L30 60 L0 30 Z\' fill=\'%23c9a96b\' opacity=\'0.03\'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        
        /* الإطار الرئيسي */
        .border-frame {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 3px solid #c9a96b;
            border-radius: 30px;
        }
        
        /* الإطار الداخلي */
        .inner-frame {
            position: absolute;
            top: 40px;
            left: 40px;
            right: 40px;
            bottom: 40px;
            border: 2px dashed #c9a96b;
            border-radius: 20px;
        }
        
        /* الزخارف في الزوايا */
        .corner {
            position: absolute;
            width: 100px;
            height: 100px;
            border: 3px solid #c9a96b;
            opacity: 0.3;
        }
        
        .corner-top-right {
            top: 30px;
            right: 30px;
            border-left: none;
            border-bottom: none;
            border-top-right-radius: 50px;
        }
        
        .corner-top-left {
            top: 30px;
            left: 30px;
            border-right: none;
            border-bottom: none;
            border-top-left-radius: 50px;
        }
        
        .corner-bottom-right {
            bottom: 30px;
            right: 30px;
            border-left: none;
            border-top: none;
            border-bottom-right-radius: 50px;
        }
        
        .corner-bottom-left {
            bottom: 30px;
            left: 30px;
            border-right: none;
            border-top: none;
            border-bottom-left-radius: 50px;
        }
        
        /* المحتوى */
        .content {
            position: relative;
            padding: 60px 50px;
            text-align: center;
            z-index: 10;
        }
        
        .bismillah {
            font-family: "Amiri", serif;
            font-size: 50px;
            color: #1e3c3f;
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .title-main {
            color: #1e3c3f;
            font-size: 48px;
            font-weight: 700;
            margin: 10px 0 5px;
            letter-spacing: 1px;
        }
        
        .title-sub {
            color: #c9a96b;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 40px;
            border-bottom: 2px solid #c9a96b;
            display: inline-block;
            padding-bottom: 5px;
        }
        
        .certificate-text {
            font-size: 28px;
            color: #333;
            margin: 40px 0 20px;
            line-height: 2;
        }
        
        .student-name-box {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            font-size: 60px;
            font-weight: 700;
            padding: 25px 50px;
            display: inline-block;
            border-radius: 100px;
            margin: 20px 0;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border: 3px solid #c9a96b;
        }
        
        .month-name {
            font-size: 40px;
            font-weight: bold;
            color: #c9a96b;
            margin: 10px 0 30px;
        }
        
        .achievement-box {
            background: #f8f9fa;
            border: 2px solid #c9a96b;
            border-radius: 30px;
            padding: 30px;
            margin: 30px auto;
            width: 80%;
        }
        
        .achievement-title {
            color: #1e3c3f;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .achievement-details {
            display: flex;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-number {
            font-size: 50px;
            font-weight: 800;
            color: #1e3c3f;
            line-height: 1.2;
        }
        
        .detail-label {
            font-size: 20px;
            color: #666;
            margin-top: 5px;
        }
        
        .date-box {
            margin: 30px 0;
            font-size: 24px;
            color: #1e3c3f;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin: 50px 0 20px;
            padding: 0 80px;
        }
        
        .signature-item {
            text-align: center;
            width: 250px;
        }
        
        .signature-line {
            width: 100%;
            height: 2px;
            background: #1e3c3f;
            margin: 10px 0;
        }
        
        .signature-name {
            font-weight: 600;
            color: #1e3c3f;
            font-size: 18px;
        }
        
        .signature-title {
            color: #666;
            font-size: 16px;
        }
        
        .seal {
            position: absolute;
            bottom: 100px;
            left: 100px;
            width: 150px;
            height: 150px;
            border: 4px solid #c9a96b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 169, 107, 0.1);
            transform: rotate(-15deg);
            opacity: 0.8;
        }
        
        .seal-inner {
            width: 120px;
            height: 120px;
            border: 2px solid #c9a96b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Amiri", serif;
            font-size: 28px;
            color: #c9a96b;
            font-weight: bold;
            text-align: center;
        }
        
        .certificate-number {
            position: absolute;
            bottom: 30px;
            right: 50px;
            color: #999;
            font-size: 14px;
            direction: ltr;
        }
        
        .star-decoration {
            position: absolute;
            top: 50px;
            left: 50px;
            font-size: 40px;
            color: #c9a96b;
            opacity: 0.3;
        }
        
        .star-decoration2 {
            position: absolute;
            bottom: 50px;
            right: 50px;
            font-size: 50px;
            color: #c9a96b;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="border-frame"></div>
        <div class="inner-frame"></div>
        
        <div class="corner corner-top-right"></div>
        <div class="corner corner-top-left"></div>
        <div class="corner corner-bottom-right"></div>
        <div class="corner corner-bottom-left"></div>
        
        <div class="star-decoration">✧</div>
        <div class="star-decoration2">✧</div>
        
        <div class="content">
            <div class="bismillah">﷽</div>
            
            <div class="title-main">دار التقوى لتحفيظ القرآن الكريم</div>
            <div class="title-sub">منيا القمح - الشرقية</div>
            
            <div class="certificate-text">
                تمنح إدارة دار التقوى
            </div>
            
            <div class="student-name-box">
                ' . htmlspecialchars($data['student_name']) . '
            </div>
            
            <div class="certificate-text">
                هذه الشهادة؛ لإتمامه/ها الهدف الشهري لشهر
            </div>
            
            <div class="month-name">
                ' . $data['month_name'] . ' ' . $data['hijri_year'] . ' هـ
            </div>
            
            <div class="achievement-box">
                <div class="achievement-title">الإنجاز المحقق</div>
                <div class="achievement-details">
                    <div class="detail-item">
                        <div class="detail-number">' . $data['target_surahs'] . '</div>
                        <div class="detail-label">سورة</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-number">' . $data['target_pages'] . '</div>
                        <div class="detail-label">صفحة</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-number">' . $data['target_ayahs'] . '</div>
                        <div class="detail-label">آية</div>
                    </div>
                </div>
            </div>
            
            <div class="date-box">
                <i class="fas fa-calendar-alt" style="color: #c9a96b;"></i>
                تاريخ الإنجاز: ' . date('Y-m-d', strtotime($data['achieved_at'])) . '
            </div>
            
            <div class="signatures">
                <div class="signature-item">
                    <div class="signature-line"></div>
                    <div class="signature-name">' . htmlspecialchars($data['teacher_name'] ?? '........................') . '</div>
                    <div class="signature-title">معلم المادة</div>
                </div>
                <div class="signature-item">
                    <div class="signature-line"></div>
                    <div class="signature-name">........................</div>
                    <div class="signature-title">مدير الدار</div>
                </div>
            </div>
            
            <div class="seal">
                <div class="seal-inner">
                    دار<br>التقوى
                </div>
            </div>
            
            <div class="certificate-number">
                رقم الشهادة: ' . $data['certificate_number'] . '
            </div>
        </div>
    </div>
</body>
</html>
';

// تحميل HTML إلى dompdf
$dompdf->loadHtml($html);

// تعيين حجم الصفحة (A4 landscape)
$dompdf->setPaper('A4', 'landscape');

// Render PDF
$dompdf->render();

// إرسال PDF إلى المتصفح للتحميل
$dompdf->stream("certificate_" . $data['certificate_number'] . ".pdf", array("Attachment" => 1));

// اختيارياً: حفظ نسخة على السيرفر
// file_put_contents('certificates/' . $data['certificate_number'] . '.pdf', $dompdf->output());
exit;
?>