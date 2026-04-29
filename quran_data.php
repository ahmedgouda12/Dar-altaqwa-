<?php
/**
 * بيانات القرآن الكريم - السور والآيات
 */

$surahs = [
    1 => ['name' => 'الفاتحة', 'ayas' => 7, 'type' => 'مكية'],
    2 => ['name' => 'البقرة', 'ayas' => 286, 'type' => 'مدنية'],
    3 => ['name' => 'آل عمران', 'ayas' => 200, 'type' => 'مدنية'],
    4 => ['name' => 'النساء', 'ayas' => 176, 'type' => 'مدنية'],
    5 => ['name' => 'المائدة', 'ayas' => 120, 'type' => 'مدنية'],
    6 => ['name' => 'الأنعام', 'ayas' => 165, 'type' => 'مكية'],
    7 => ['name' => 'الأعراف', 'ayas' => 206, 'type' => 'مكية'],
    8 => ['name' => 'الأنفال', 'ayas' => 75, 'type' => 'مدنية'],
    9 => ['name' => 'التوبة', 'ayas' => 129, 'type' => 'مدنية'],
    10 => ['name' => 'يونس', 'ayas' => 109, 'type' => 'مكية'],
    11 => ['name' => 'هود', 'ayas' => 123, 'type' => 'مكية'],
    12 => ['name' => 'يوسف', 'ayas' => 111, 'type' => 'مكية'],
    13 => ['name' => 'الرعد', 'ayas' => 43, 'type' => 'مدنية'],
    14 => ['name' => 'إبراهيم', 'ayas' => 52, 'type' => 'مكية'],
    15 => ['name' => 'الحجر', 'ayas' => 99, 'type' => 'مكية'],
    16 => ['name' => 'النحل', 'ayas' => 128, 'type' => 'مكية'],
    17 => ['name' => 'الإسراء', 'ayas' => 111, 'type' => 'مكية'],
    18 => ['name' => 'الكهف', 'ayas' => 110, 'type' => 'مكية'],
    19 => ['name' => 'مريم', 'ayas' => 98, 'type' => 'مكية'],
    20 => ['name' => 'طه', 'ayas' => 135, 'type' => 'مكية'],
    // ... يمكنك إضافة باقي السور لاحقاً
];

// دالة لجلب اسم السورة
function getSurahName($surah_number) {
    global $surahs;
    return isset($surahs[$surah_number]['name']) ? $surahs[$surah_number]['name'] : 'غير معروف';
}

// دالة لجلب عدد آيات السورة
function getSurahAyas($surah_number) {
    global $surahs;
    return isset($surahs[$surah_number]['ayas']) ? $surahs[$surah_number]['ayas'] : 0;
}
?>