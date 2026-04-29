<?php
// ============================================
// ملف: add_parts_tables.php
// إضافة جداول نظام الأجزاء المحفوظة
// مع معالجة مشكلة الحد الأقصى للجداول
// ============================================

require_once 'config.php';

// منع التنفيذ المباشر بدون تأكيد
$force = isset($_GET['force']) && $_GET['force'] === 'yes';

if (!$force) {
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <title>تحذير - إضافة جداول جديدة</title>
        <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 40px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .warning-box { max-width: 600px; background: white; border-radius: 20px; padding: 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-top: 5px solid #ffc107; }
            .warning-box h1 { color: #856404; margin-bottom: 15px; }
            .warning-box p { color: #666; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; margin: 5px; }
            .btn-danger { background: #dc3545; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
            .btn-warning { background: #ffc107; color: #212529; }
            .table-list { background: #f8f9fa; border-radius: 10px; padding: 15px; margin: 20px 0; text-align: right; max-height: 300px; overflow-y: auto; }
            .table-list ul { margin: 0 20px; }
            .table-list li { padding: 5px 0; }
        </style>
    </head>
    <body>
    <div class='warning-box'>
        <h1>⚠️ تحذير!</h1>
        <p>أنت على وشك إضافة جداول جديدة إلى قاعدة البيانات.</p>
        <p>سيتم إضافة الجداول التالية:</p>
        <div class='table-list'>
            <ul>
                <li><strong>quran_parts</strong> - تعريف أجزاء القرآن (30 جزءاً)</li>
                <li><strong>student_parts_stats</strong> - إحصائيات الأجزاء المحفوظة للطلاب</li>
            </ul>
        </div>";
    
    // عرض عدد الجداول الحالية
    $current_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>📊 عدد الجداول الحالية: <strong>" . count($current_tables) . "</strong></p>";
    
    if (count($current_tables) >= 100) {
        echo "<p style='color: #dc3545;'><strong>⚠️ تنبيه:</strong> لديك " . count($current_tables) . " جدول. بعض الاستضافات المجانية لديها حد أقصى (100-150 جدول).</p>";
        echo "<p>إذا واجهتك مشكلة، يمكنك <strong>حذف الجداول غير الضرورية</strong> أولاً.</p>";
    }
    
    echo "<div style='margin-top: 25px;'>
            <a href='?force=yes' class='btn btn-warning' onclick='return confirm(\"هل أنت متأكد من إضافة الجداول؟\")'>✅ تأكيد الإضافة</a>
            <a href='dashboard.php' class='btn btn-secondary'>❌ إلغاء</a>
          </div>
          <p style='margin-top: 20px; font-size: 0.8rem; color: #999;'>يمكنك إضافة المعامل <code>?force=yes</code> لتجاوز هذه الشاشة</p>
    </div>
    </body>
    </html>";
    exit;
}

// ============================================
// بدء عملية الإضافة
// ============================================

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>إضافة جداول نظام الأجزاء</title>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        h1 { color: #1e3c3f; border-bottom: 3px solid #c9a96b; padding-bottom: 10px; margin-bottom: 25px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 10px; margin: 10px 0; border-right: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 10px; margin: 10px 0; border-right: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 10px; margin: 10px 0; border-right: 4px solid #17a2b8; }
        .btn { display: inline-block; background: #1e3c3f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📊 إضافة جداول نظام الأجزاء المحفوظة</h1>";

// ============================================
// 1. التحقق من وجود الجداول
// ============================================

echo "<h3>🔍 فحص الجداول الموجودة...</h3>";

$existing_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<p>📋 عدد الجداول الحالية: <strong>" . count($existing_tables) . "</strong></p>";

// ============================================
// 2. إنشاء جدول quran_parts (تقسيم الأجزاء)
// ============================================

echo "<h3>📖 1. إنشاء جدول تعريف الأجزاء (quran_parts)</h3>";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quran_parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_number INT NOT NULL UNIQUE,
            start_page INT NOT NULL,
            end_page INT NOT NULL,
            total_pages INT NOT NULL,
            start_surah INT,
            end_surah INT,
            description VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_part (part_number),
            INDEX idx_page (start_page, end_page)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول quran_parts بنجاح</div>";
    
    // التحقق من وجود بيانات
    $check = $pdo->query("SELECT COUNT(*) FROM quran_parts")->fetchColumn();
    if ($check == 0) {
        echo "<div class='info'>📝 جاري إدخال بيانات الأجزاء...</div>";
        
        // إدخال بيانات الأجزاء الـ 30
        $parts_data = [
            [1, 1, 22, 22, 1, 2, 'الفاتحة وأول البقرة'],
            [2, 22, 42, 21, 2, 2, 'البقرة'],
            [3, 42, 62, 21, 2, 3, 'آخر البقرة وأول آل عمران'],
            [4, 62, 82, 21, 3, 4, 'آخر آل عمران وأول النساء'],
            [5, 82, 102, 21, 4, 4, 'النساء'],
            [6, 102, 122, 21, 4, 5, 'آخر النساء وأول المائدة'],
            [7, 122, 142, 21, 5, 6, 'آخر المائدة وأول الأنعام'],
            [8, 142, 162, 21, 6, 7, 'الأنعام'],
            [9, 162, 182, 21, 7, 8, 'آخر الأنعام وأول الأعراف'],
            [10, 182, 202, 21, 8, 9, 'الأنفال والتوبة'],
            [11, 202, 222, 21, 9, 10, 'التوبة ويونس'],
            [12, 222, 242, 21, 11, 12, 'هود ويوسف'],
            [13, 242, 262, 21, 13, 14, 'الرعد وإبراهيم'],
            [14, 262, 282, 21, 15, 16, 'الحجر والنحل'],
            [15, 282, 302, 21, 17, 18, 'الإسراء والكهف'],
            [16, 302, 322, 21, 19, 20, 'مريم وطه'],
            [17, 322, 342, 21, 21, 22, 'الأنبياء والحج'],
            [18, 342, 362, 21, 23, 24, 'المؤمنون والنور'],
            [19, 362, 382, 21, 25, 26, 'الفرقان والشعراء'],
            [20, 382, 402, 21, 27, 28, 'النمل والقصص'],
            [21, 402, 422, 21, 29, 30, 'العنكبوت والروم'],
            [22, 422, 442, 21, 31, 33, 'لقمان والسجدة والأحزاب'],
            [23, 442, 462, 21, 34, 36, 'سبأ وفاطر ويس'],
            [24, 462, 482, 21, 37, 39, 'الصافات وص والزمر'],
            [25, 482, 502, 21, 41, 43, 'فصلت والشورى والزخرف'],
            [26, 502, 522, 21, 44, 46, 'الدخان والجاثية والأحقاف'],
            [27, 522, 542, 21, 51, 57, 'الذاريات إلى الحديد'],
            [28, 542, 562, 21, 58, 66, 'المجادلة إلى التحريم'],
            [29, 562, 582, 21, 67, 77, 'الملك إلى المرسلات'],
            [30, 582, 604, 23, 78, 114, 'النبأ إلى الناس']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO quran_parts 
            (part_number, start_page, end_page, total_pages, start_surah, end_surah, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($parts_data as $part) {
            $stmt->execute($part);
        }
        echo "<div class='success'>✅ تم إدخال بيانات 30 جزءاً بنجاح</div>";
    } else {
        echo "<div class='info'>ℹ️ جدول quran_parts يحتوي بالفعل على بيانات</div>";
    }
    
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1142) {
        echo "<div class='error'>❌ ليس لديك صلاحية إنشاء جدول. يرجى التحقق من صلاحيات قاعدة البيانات.</div>";
    } else {
        echo "<div class='error'>❌ خطأ في إنشاء جدول quran_parts: " . $e->getMessage() . "</div>";
    }
}

// ============================================
// 3. إنشاء جدول student_parts_stats
// ============================================

echo "<h3>📊 2. إنشاء جدول إحصائيات الأجزاء (student_parts_stats)</h3>";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_parts_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            total_parts INT DEFAULT 0,
            parts_list TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_parts (total_parts),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<div class='success'>✅ تم إنشاء جدول student_parts_stats بنجاح</div>";
    
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1215) {
        // مشكلة المفتاح الخارجي - نحاول بدون FOREIGN KEY
        echo "<div class='info'>⚠️ محاولة إنشاء الجدول بدون مفتاح خارجي...</div>";
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_parts_stats (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id INT NOT NULL UNIQUE,
                    total_parts INT DEFAULT 0,
                    parts_list TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_student (student_id),
                    INDEX idx_parts (total_parts)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "<div class='success'>✅ تم إنشاء جدول student_parts_stats بنجاح (بدون مفتاح خارجي)</div>";
        } catch (PDOException $e2) {
            echo "<div class='error'>❌ خطأ: " . $e2->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error'>❌ خطأ في إنشاء جدول student_parts_stats: " . $e->getMessage() . "</div>";
    }
}

// ============================================
// 4. تحديث إحصائيات الطلاب الحاليين
// ============================================

echo "<h3>🔄 3. تحديث إحصائيات الطلاب الحاليين</h3>";

try {
    // التحقق من وجود دالة updateStudentPartsStats
    if (!function_exists('updateStudentPartsStats')) {
        // إضافة الدالة مؤقتاً
        function updateStudentPartsStatsTemp($pdo, $student_id) {
            // تحديث بسيط - عدد السور المحفوظة تقريباً
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_surah_progress WHERE student_id = ? AND completed = 1");
            $stmt->execute([$student_id]);
            $surahs_count = $stmt->fetchColumn();
            
            // تقريب: كل 20 سورة = جزء واحد
            $total_parts = ceil($surahs_count / 20);
            
            $check = $pdo->prepare("SELECT id FROM student_parts_stats WHERE student_id = ?");
            $check->execute([$student_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE student_parts_stats SET total_parts = ? WHERE student_id = ?");
                $stmt->execute([$total_parts, $student_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO student_parts_stats (student_id, total_parts) VALUES (?, ?)");
                $stmt->execute([$student_id, $total_parts]);
            }
            return $total_parts;
        }
    }
    
    $students = $pdo->query("SELECT id, name FROM students")->fetchAll();
    $updated = 0;
    
    foreach ($students as $student) {
        if (function_exists('updateStudentPartsStats')) {
            $result = updateStudentPartsStats($pdo, $student['id']);
        } else {
            $result = updateStudentPartsStatsTemp($pdo, $student['id']);
        }
        if ($result !== false) $updated++;
    }
    
    echo "<div class='success'>✅ تم تحديث إحصائيات $updated طالب</div>";
    
} catch (PDOException $e) {
    echo "<div class='info'>ℹ️ لم يتم تحديث إحصائيات الطلاب تلقائياً: " . $e->getMessage() . "</div>";
}

// ============================================
// 5. عرض الإحصائيات النهائية
// ============================================

echo "<h3>📈 4. الإحصائيات النهائية</h3>";

try {
    $final_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'>📋 عدد الجداول بعد الإضافة: <strong>" . count($final_tables) . "</strong></div>";
    
    $parts_count = $pdo->query("SELECT COUNT(*) FROM quran_parts")->fetchColumn();
    echo "<div class='info'>📖 عدد الأجزاء المسجلة: <strong>$parts_count</strong> جزء</div>";
    
    $stats_count = $pdo->query("SELECT COUNT(*) FROM student_parts_stats")->fetchColumn();
    echo "<div class='info'>👨‍🎓 عدد الطلاب الذين لديهم إحصائيات: <strong>$stats_count</strong></div>";
    
} catch (PDOException $e) {
    echo "<div class='info'>ℹ️ لا يمكن عرض الإحصائيات</div>";
}

// ============================================
// 6. روابط الصفحات
// ============================================

echo "<hr>";
echo "<h2>✅ اكتملت عملية إضافة الجداول!</h2>";
echo "<div style='display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px;'>";
echo "<a href='parts_statistics.php' class='btn'><i class='fas fa-chart-pie'></i> عرض إحصائيات الأجزاء</a>";
echo "<a href='dashboard.php' class='btn'><i class='fas fa-home'></i> العودة للوحة التحكم</a>";
echo "<a href='parts_statistics.php?update_stats=1' class='btn btn-success'><i class='fas fa-sync-alt'></i> تحديث الإحصائيات</a>";
echo "</div>";

// ============================================
// 7. في حالة فشل الإضافة بسبب الحد الأقصى
// ============================================

if (count($existing_tables) >= 100 && (!isset($final_tables) || count($final_tables) == count($existing_tables))) {
    echo "<div class='error' style='margin-top: 20px;'>
            <strong>⚠️ لم تتم إضافة الجداول بسبب الوصول إلى الحد الأقصى!</strong><br>
            لديك حاليًا " . count($existing_tables) . " جدول. بعض الاستضافات المجانية لديها حد أقصى (100-150 جدول).<br><br>
            <strong>الحلول المقترحة:</strong>
            <ul style='margin-top: 10px; margin-right: 20px;'>
                <li>حذف الجداول غير الضرورية (مثل جداول النسخ الاحتياطي)</li>
                <li>الترقية إلى خطة استضافة مدفوعة</li>
                <li>استخدام قاعدة بيانات إضافية على نفس الخادم (إذا كان متاحاً)</li>
            </ul>
          </div>";
}

echo "</div></body></html>";
?>