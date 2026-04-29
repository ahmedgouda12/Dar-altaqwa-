<?php
// ============================================
// ملف: test_hijri_functions.php
// اختبار دوال التاريخ الهجري
// ============================================

require_once 'config.php';
require_once 'hijri_date.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>اختبار دوال التاريخ الهجري</title>
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #1e3c3f; color: white; }
        .btn { display: inline-block; background: #1e3c3f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📅 اختبار دوال التاريخ الهجري</h1>";

// قائمة الدوال المطلوب اختبارها
$functions = [
    'getCurrentHijriDate' => 'الحصول على التاريخ الهجري الحالي',
    'getCurrentHijriYear' => 'الحصول على السنة الهجرية الحالية',
    'getHijriYear' => 'الحصول على السنة الهجرية (مختصر)',
    'getHijriMonth' => 'الحصول على الشهر الهجري',
    'getHijriMonthName' => 'الحصول على اسم الشهر الهجري',
    'getHijriDay' => 'الحصول على اليوم الهجري',
    'getHijriDateFormatted' => 'الحصول على التاريخ الهجري منسق',
    'getFullHijriDate' => 'الحصول على التاريخ الهجري كامل مع اليوم',
    'testHijriAPI' => 'اختبار الاتصال بـ API'
];

echo "<h3>نتائج الاختبار:</h3>";
echo "<table>
        <thead>
            <tr><th>الدالة</th><th>الوصف</th><th>النتيجة</th><th>القيمة</th> </tr>
        </thead>
        <tbody>";

foreach ($functions as $func => $desc) {
    echo "<tr>";
    echo "<td><code>$func()</code></td>";
    echo "<td>$desc</td>";
    
    if (function_exists($func)) {
        try {
            $result = $func();
            echo "<td class='success'>✅ موجودة</td>";
            echo "<td>" . (is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : $result) . "</td>";
        } catch (Exception $e) {
            echo "<td class='error'>❌ خطأ</td>";
            echo "<td>" . $e->getMessage() . "</td>";
        }
    } else {
        echo "<td class='error'>❌ غير موجودة</td>";
        echo "<td>-</td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";

echo "<h3>التاريخ الحالي:</h3>";
$hijri = getCurrentHijriDate();
echo "<p><strong>التاريخ الهجري:</strong> " . getFullHijriDate() . "</p>";
echo "<p><strong>التاريخ الميلادي:</strong> " . date('Y-m-d') . "</p>";
echo "<p><strong>السنة الهجرية:</strong> " . getCurrentHijriYear() . " هـ</p>";
echo "<p><strong>الشهر:</strong> " . getHijriMonthName() . " (" . getHijriMonth() . ")</p>";
echo "<p><strong>اليوم:</strong> " . getHijriDay() . "</p>";

echo "<a href='refresh_hijri.php' class='btn'>تحديث التاريخ</a> ";
echo "<a href='annual_report.php' class='btn'>الذهاب للتقرير السنوي</a> ";
echo "<a href='dashboard.php' class='btn'>العودة للوحة التحكم</a>";

echo "</div></body></html>";
?>