<?php
// ============================================
// ملف: check_hijri.php
// فحص التاريخ الهجري الحالي
// ============================================

require_once 'config.php';
require_once 'hijri_date.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>فحص التاريخ الهجري</title>
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #1e3c3f; color: white; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .btn { display: inline-block; background: #1e3c3f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📅 فحص التاريخ الهجري</h1>";
    
$hijri = getCurrentHijriDate();

echo "<h3>التاريخ الحالي:</h3>";
echo "<p><strong>التاريخ الهجري:</strong> " . getFullHijriDate() . "</p>";
echo "<p><strong>التاريخ الميلادي:</strong> " . date('Y-m-d') . "</p>";

echo "<h3>اختبار التواريخ:</h3>";
echo "<table>
        <thead>
            <tr><th>التاريخ الميلادي</th><th>التاريخ الهجري (API)</th><th>التاريخ الهجري (تقريبي)</th><th>المصدر</th></tr>
        </thead>
        <tbody>";

$test_dates = [
    '2026-04-01' => 'شوال',
    '2026-03-20' => 'رمضان',
    '2026-03-01' => 'رمضان',
    '2026-02-18' => 'شعبان'
];

foreach ($test_dates as $date => $expected) {
    $api_date = getHijriDateFromAPI($date);
    $approx_date = getHijriDateApproximate($date);
    $status = ($api_date['month_name'] == $expected) ? 'success' : 'warning';
    echo "<tr>
            <td>$date</td>
            <td>{$api_date['formatted']}</td>
            <td>{$approx_date['formatted']}</td>
            <td class='$status'>" . ($api_date['month_name'] == $expected ? '✅ صحيح' : '⚠️ قد يكون غير دقيق') . "</td>
          </tr>";
}

echo "</tbody></table>";

echo "<p style='margin-top: 20px;'><strong>ملاحظة:</strong> يتم جلب التاريخ من API خارجي (AlAdhan) وهو دقيق بإذن الله.</p>";
echo "<a href='refresh_hijri.php' class='btn'>تحديث التاريخ الآن</a> ";
echo "<a href='dashboard.php' class='btn'>العودة للوحة التحكم</a>";

echo "</div></body></html>";
?>