<?php
// ============================================
// ملف: export_achievement_pdf.php
// تصدير تقرير الإنجازات إلى PDF
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

// تضمين مكتبة dompdf
require_once __DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isLoggedIn()) {
    die('غير مصرح');
}

$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

// جلب بيانات التقرير
if (isTeacher()) {
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as teacher_name
        FROM teacher_achievement_reports r
        JOIN teachers t ON r.teacher_id = t.id
        WHERE r.id = ? AND r.teacher_id = ?
    ");
    $stmt->execute([$report_id, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as teacher_name
        FROM teacher_achievement_reports r
        JOIN teachers t ON r.teacher_id = t.id
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
}

$report = $stmt->fetch();

if (!$report) {
    die('التقرير غير موجود');
}

// جلب أفضل الطلاب
$top_students = json_decode($report['top_students'], true);

// إعداد خيارات PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Cairo');

$dompdf = new Dompdf($options);

// محتوى HTML للشهادة
$html = '
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الإنجازات - ' . htmlspecialchars($report['report_title']) . '</title>
    <style>
        @font-face {
            font-family: "Cairo";
            font-style: normal;
            font-weight: 400;
            src: url("https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap");
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Cairo", sans-serif;
            background: white;
            padding: 40px;
        }
        
        .certificate {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            position: relative;
            border: 2px solid #c9a96b;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #c9a96b;
            padding-bottom: 20px;
        }
        
        .logo-text {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c3f;
        }
        
        .address {
            color: #666;
            margin-top: 5px;
        }
        
        .report-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e3c3f;
            text-align: center;
            margin: 30px 0;
        }
        
        .report-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            background: #f8f9fa;
            padding: 8px 20px;
            border-radius: 30px;
            color: #1e3c3f;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c3f;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e3c3f;
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        
        .content-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        
        .top-students-list {
            margin-top: 20px;
        }
        
        .top-student-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .top-student-rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .rank-1 { background: gold; color: #212529; }
        .rank-2 { background: silver; color: #212529; }
        .rank-3 { background: #cd7f32; }
        .rank-other { background: #6c757d; }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #666;
            font-size: 0.9rem;
        }
        
        @media print {
            body { padding: 0; }
            .certificate { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="logo-text">دار التقوى لتحفيظ القرآن الكريم</div>
            <div class="address">منيا القمح - الشرقية</div>
        </div>
        
        <div class="report-title">
            ' . htmlspecialchars($report['report_title']) . '
        </div>
        
        <div class="report-meta">
            <div class="meta-item"><i class="fas fa-calendar-alt"></i> السنة الهجرية: ' . $report['hijri_year'] . ' هـ - الشهر ' . $report['hijri_month'] . '</div>
            <div class="meta-item"><i class="fas fa-chalkboard-teacher"></i> المعلم: ' . htmlspecialchars($report['teacher_name']) . '</div>
            <div class="meta-item"><i class="fas fa-clock"></i> تاريخ الإعداد: ' . date('Y-m-d', strtotime($report['created_at'])) . '</div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">' . $report['total_students'] . '</div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $report['new_memorized_surahs'] . '</div>
                <div class="stat-label">سور جديدة</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $report['completed_quran_count'] . '</div>
                <div class="stat-label">خاتمين للقرآن</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . ($report['status'] == 'approved' ? 'معتمد' : ($report['status'] == 'submitted' ? 'قيد المراجعة' : 'مسودة')) . '</div>
                <div class="stat-label">حالة التقرير</div>
            </div>
        </div>';
        
        if (!empty($top_students)) {
            $html .= '
        <div class="section-title"><i class="fas fa-crown"></i> أفضل الطلاب حفظاً</div>
        <div class="top-students-list">';
            foreach ($top_students as $index => $student) {
                $rank = $index + 1;
                $rank_class = $rank <= 3 ? $rank : 'other';
                $html .= '
            <div class="top-student-item">
                <div class="top-student-rank rank-' . $rank_class . '">' . $rank . '</div>
                <div style="flex: 1;"><strong>' . htmlspecialchars($student['name']) . '</strong></div>
                <div><span class="stat-tag">' . $student['surahs'] . ' سورة</span></div>
            </div>';
            }
            $html .= '
        </div>';
        }
        
        $html .= '
        <div class="section-title"><i class="fas fa-align-left"></i> تفاصيل الإنجازات</div>
        <div class="content-box">
            ' . nl2br(htmlspecialchars($report['report_content'])) . '
        </div>';
        
        if (!empty($report['notes'])) {
            $html .= '
        <div class="section-title"><i class="fas fa-sticky-note"></i> ملاحظات</div>
        <div class="content-box" style="background: #fff3cd;">
            ' . nl2br(htmlspecialchars($report['notes'])) . '
        </div>';
        }
        
        $html .= '
        <div class="footer">
            <div>دار التقوى لتحفيظ القرآن الكريم - منيا القمح - الشرقية</div>
            <div style="margin-top: 10px;">تم إنشاء هذا التقرير بواسطة نظام دار التقوى الإلكتروني</div>
        </div>
    </div>
</body>
</html>';

// تحميل HTML إلى dompdf
$dompdf->loadHtml($html);

// تعيين حجم الصفحة
$dompdf->setPaper('A4', 'portrait');

// Render PDF
$dompdf->render();

// إرسال PDF للمتصفح
$filename = 'تقرير_إنجازات_' . $report['report_title'] . '.pdf';
$dompdf->stream($filename, array("Attachment" => 0));
exit;
?>