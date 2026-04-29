<?php
// ============================================
// ملف: get_teacher_rings.php
// جلب حلقات المعلم - نسخة مبسطة ومضمونة
// ============================================

// إخفاء جميع الأخطاء
error_reporting(0);
ini_set('display_errors', 0);

// تضمين ملف الإعدادات
require_once 'config.php';

// التأكد من عدم وجود أي مخرجات قبل JSON
ob_clean();

// تعيين رأس JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// الحصول على ID المعلم
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// التحقق من صحة ID
if ($teacher_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معلم غير صحيح', 'rings' => []]);
    exit;
}

try {
    // جلب حلقات المعلم
    $stmt = $pdo->prepare("
        SELECT id, name, location, description 
        FROM rings 
        WHERE teacher_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$teacher_id]);
    $rings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات
    $result = [];
    foreach ($rings as $ring) {
        $result[] = [
            'id' => $ring['id'],
            'name' => $ring['name'],
            'location' => $ring['location'] ?? '',
            'students_count' => 0,
            'schedule_text' => 'مواعيد الحلقة محددة في الإعدادات'
        ];
    }
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'rings' => $result,
        'count' => count($result)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'rings' => []
    ]);
}
?>