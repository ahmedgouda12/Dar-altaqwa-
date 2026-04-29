<?php
// ============================================
// ملف: hijri_date.php - نظام التاريخ الهجري المتقدم
// آخر تحديث: 2026-04-01
// ============================================

/**
 * التحقق من وجود جلسة نشطة
 */
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * الحصول على التاريخ الهجري من API (محسن)
 */
function getHijriDateFromAPI($date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    // استخدام API مختلف وأكثر دقة
    $apis = [
        "https://api.aladhan.com/v1/gToH/{$date}",
        "https://api.islamicfinder.org/v1/hijri/gregorian?date={$date}",
        "https://www.islamicfinder.org/hijri-calendar/gregorian/{$date}"
    ];
    
    foreach ($apis as $api_url) {
        // محاولة جلب البيانات باستخدام cURL
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['data']['hijri'])) {
                    return parseHijriData($data['data']['hijri']);
                }
            }
        }
        
        // محاولة باستخدام file_get_contents
        if (ini_get('allow_url_fopen')) {
            $response = @file_get_contents($api_url);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['data']['hijri'])) {
                    return parseHijriData($data['data']['hijri']);
                }
            }
        }
    }
    
    // إذا فشل API، استخدم الحساب اليدوي المحدد
    return getManualHijriDate($date);
}

/**
 * حساب يدوي للتاريخ الهجري (يمكن تعديله حسب الحاجة)
 */
function getManualHijriDate($date = null) {
    if (!$date) $date = date('Y-m-d');
    
    $hijri_months = [
        1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
        5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
        9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة'
    ];
    
    // جدول التحويل اليدوي للتواريخ الهامة
    $manual_conversion = [
        '2026-04-01' => ['year' => 1447, 'month' => 10, 'day' => 13], // 13 شوال 1447
        '2026-04-02' => ['year' => 1447, 'month' => 10, 'day' => 14],
        '2026-04-03' => ['year' => 1447, 'month' => 10, 'day' => 15],
        '2026-04-04' => ['year' => 1447, 'month' => 10, 'day' => 16],
        '2026-04-05' => ['year' => 1447, 'month' => 10, 'day' => 17],
        '2026-04-06' => ['year' => 1447, 'month' => 10, 'day' => 18],
        '2026-04-07' => ['year' => 1447, 'month' => 10, 'day' => 19],
        '2026-04-08' => ['year' => 1447, 'month' => 10, 'day' => 20],
        '2026-04-09' => ['year' => 1447, 'month' => 10, 'day' => 21],
        '2026-04-10' => ['year' => 1447, 'month' => 10, 'day' => 22],
    ];
    
    // إذا كان التاريخ في الجدول، استخدمه
    if (isset($manual_conversion[$date])) {
        $h = $manual_conversion[$date];
        return [
            'year' => $h['year'],
            'month' => $h['month'],
            'day' => $h['day'],
            'month_name' => $hijri_months[$h['month']],
            'formatted' => $h['day'] . ' ' . $hijri_months[$h['month']] . ' ' . $h['year'] . ' هـ',
            'weekday' => ''
        ];
    }
    
    // حساب تقريبي
    $parts = explode('-', $date);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    
    // بداية التقويم الهجري (محرم 1, 1 هـ = 16 يوليو 622 م)
    $base_year = 622;
    $base_month = 7;
    $base_day = 16;
    
    // حساب الفرق بالأيام
    $current_jd = gregoriantojd($month, $day, $year);
    $base_jd = gregoriantojd($base_month, $base_day, $base_year);
    $diff_days = $current_jd - $base_jd;
    
    // السنة الهجرية = عدد الأيام / 354.367 (متوسط طول السنة الهجرية)
    $hijri_year = floor($diff_days / 354.367) + 1;
    $remaining_days = $diff_days % 354.367;
    
    // حساب الشهر
    $month_days = [30, 29, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29];
    $hijri_month = 1;
    $temp_days = $remaining_days;
    
    foreach ($month_days as $md) {
        if ($temp_days < $md) break;
        $temp_days -= $md;
        $hijri_month++;
    }
    
    $hijri_day = floor($temp_days) + 1;
    
    // التأكد من صحة القيم
    if ($hijri_month > 12) {
        $hijri_month = 1;
        $hijri_year++;
    }
    if ($hijri_day > 30) $hijri_day = 30;
    if ($hijri_day < 1) $hijri_day = 1;
    
    return [
        'year' => $hijri_year,
        'month' => $hijri_month,
        'day' => $hijri_day,
        'month_name' => $hijri_months[$hijri_month] ?? 'شوال',
        'formatted' => $hijri_day . ' ' . ($hijri_months[$hijri_month] ?? 'شوال') . ' ' . $hijri_year . ' هـ',
        'weekday' => ''
    ];
}

/**
 * تحليل بيانات API
 */
function parseHijriData($hijri) {
    $hijri_months = [
        1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
        5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
        9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة'
    ];
    
    preg_match('/(\d+)/', $hijri['day'], $day_match);
    preg_match('/(\d+)/', $hijri['month']['number'], $month_match);
    preg_match('/(\d+)/', $hijri['year'], $year_match);
    
    $day = $day_match[1] ?? 1;
    $month = $month_match[1] ?? 1;
    $year = $year_match[1] ?? 1447;
    
    return [
        'year' => (int)$year,
        'month' => (int)$month,
        'day' => (int)$day,
        'month_name' => $hijri_months[$month] ?? 'شوال',
        'formatted' => $day . ' ' . ($hijri_months[$month] ?? 'شوال') . ' ' . $year . ' هـ',
        'weekday' => $hijri['weekday']['ar'] ?? ''
    ];
}

/**
 * الحصول على التاريخ الهجري الحالي
 */
function getCurrentHijriDate() {
    ensureSession();
    $today = date('Y-m-d');
    
    // إذا كان التاريخ المخزن قديماً أو غير موجود، قم بتحديثه
    if (!isset($_SESSION['hijri_date']) || 
        !isset($_SESSION['hijri_last_check']) || 
        $_SESSION['hijri_last_check'] != $today) {
        
        $_SESSION['hijri_date'] = getManualHijriDate($today);
        $_SESSION['hijri_last_check'] = $today;
        $_SESSION['hijri_updated_at'] = date('Y-m-d H:i:s');
    }
    
    return $_SESSION['hijri_date'];
}

/**
 * تحديث التاريخ الهجري يدوياً
 */
function refreshHijriDate() {
    ensureSession();
    $today = date('Y-m-d');
    $_SESSION['hijri_date'] = getManualHijriDate($today);
    $_SESSION['hijri_last_check'] = $today;
    $_SESSION['hijri_updated_at'] = date('Y-m-d H:i:s');
    $_SESSION['hijri_manual_update'] = true;
    return $_SESSION['hijri_date'];
}

/**
 * تعيين التاريخ الهجري يدوياً (للتعديل)
 */
function setManualHijriDate($year, $month, $day) {
    ensureSession();
    $hijri_months = [
        1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
        5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
        9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة'
    ];
    
    $_SESSION['hijri_date'] = [
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'month_name' => $hijri_months[$month] ?? 'شوال',
        'formatted' => $day . ' ' . ($hijri_months[$month] ?? 'شوال') . ' ' . $year . ' هـ',
        'weekday' => ''
    ];
    $_SESSION['hijri_last_check'] = date('Y-m-d');
    $_SESSION['hijri_updated_at'] = date('Y-m-d H:i:s');
    $_SESSION['hijri_manual_update'] = true;
    
    return $_SESSION['hijri_date'];
}

/**
 * الحصول على السنة الهجرية الحالية
 */
function getCurrentHijriYear() {
    $hijri = getCurrentHijriDate();
    return $hijri['year'];
}

function getHijriYear() {
    return getCurrentHijriYear();
}

function getHijriMonth() {
    $hijri = getCurrentHijriDate();
    return $hijri['month'];
}

function getHijriMonthName() {
    $hijri = getCurrentHijriDate();
    return $hijri['month_name'];
}

function getHijriDay() {
    $hijri = getCurrentHijriDate();
    return $hijri['day'];
}

function getHijriDateFormatted() {
    $hijri = getCurrentHijriDate();
    return $hijri['formatted'];
}

function getFullHijriDate() {
    $hijri = getCurrentHijriDate();
    $day_names = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $today_weekday = $day_names[date('w')];
    return $today_weekday . '، ' . $hijri['formatted'];
}

function getHijriInfo() {
    $hijri = getCurrentHijriDate();
    return [
        'hijri' => $hijri,
        'gregorian' => date('Y-m-d'),
        'updated_at' => $_SESSION['hijri_updated_at'] ?? null,
        'is_manual' => $_SESSION['hijri_manual_update'] ?? false
    ];
}
?>