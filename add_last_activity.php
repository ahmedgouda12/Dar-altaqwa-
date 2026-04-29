<?php
// ============================================
// ملف: add_last_activity.php
// إضافة أعمدة last_activity للجداول
// آخر تحديث: 2026-04-02
// ============================================

require_once 'config.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>إضافة أعمدة last_activity</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e3c3f;
            border-bottom: 3px solid #c9a96b;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .success {
            color: #155724;
            background: #d4edda;
            padding: 15px;
            border-radius: 12px;
            margin: 12px 0;
            border-right: 5px solid #28a745;
        }
        .error {
            color: #721c24;
            background: #f8d7da;
            padding: 15px;
            border-radius: 12px;
            margin: 12px 0;
            border-right: 5px solid #dc3545;
        }
        .info {
            color: #0c5460;
            background: #d1ecf1;
            padding: 15px;
            border-radius: 12px;
            margin: 12px 0;
            border-right: 5px solid #17a2b8;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            margin-top: 25px;
            transition: 0.3s;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .stats {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c3f;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>
        <i class='fas fa-database'></i>
        إضافة أعمدة last_activity
    </h1>";

$tables = ['admins', 'teachers', 'students', 'guardians'];
$success_count = 0;
$error_count = 0;

foreach ($tables as $table) {
    echo "<h3>📋 جدول: {$table}</h3>";
    try {
        $check = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'last_activity'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN last_activity DATETIME NULL");
            $pdo->exec("ALTER TABLE {$table} ADD INDEX idx_last_activity (last_activity)");
            echo "<div class='success'>✅ تم إضافة عمود last_activity في جدول {$table}</div>";
            $success_count++;
        } else {
            echo "<div class='info'>ℹ️ عمود last_activity موجود بالفعل في جدول {$table}</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>❌ خطأ في جدول {$table}: " . $e->getMessage() . "</div>";
        $error_count++;
    }
}

echo "<div class='stats'>
        <div class='stats-number'>{$success_count}</div>
        <div>تمت بنجاح</div>
        <div style='margin-top: 10px;'>{$error_count} خطأ</div>
     </div>";

echo "<div style='display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px;'>";
echo "<a href='dashboard.php' class='btn'><i class='fas fa-home'></i> الذهاب للوحة التحكم</a>";
echo "<a href='login.php' class='btn'><i class='fas fa-sign-in-alt'></i> الذهاب لتسجيل الدخول</a>";
echo "</div>";

echo "</div></body></html>";
?>