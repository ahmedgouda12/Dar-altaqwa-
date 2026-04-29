<?php
require_once 'config.php';

// فقط للمسؤولين (حماية بسيطة)
if (!isAdmin()) {
    die('غير مصرح بالوصول');
}

echo "<h1>إنشاء حسابات للطلاب وأولياء الأمور</h1>";

// 1. التأكد من وجود الأعمدة في جدول students
try {
    $check = $pdo->query("SHOW COLUMNS FROM students LIKE 'username'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN username VARCHAR(50) UNIQUE AFTER id");
        $pdo->exec("ALTER TABLE students ADD COLUMN password VARCHAR(255) AFTER username");
        echo "<p style='color:green;'>✅ تم إضافة أعمدة اسم المستخدم وكلمة المرور في جدول الطلاب.</p>";
    } else {
        echo "<p>✅ أعمدة اسم المستخدم وكلمة المرور موجودة بالفعل في جدول الطلاب.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ في جدول students: " . $e->getMessage() . "</p>";
}

// 2. التأكد من وجود الأعمدة في جدول guardians
try {
    $check = $pdo->query("SHOW COLUMNS FROM guardians LIKE 'username'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE guardians ADD COLUMN username VARCHAR(50) UNIQUE AFTER id");
        $pdo->exec("ALTER TABLE guardians ADD COLUMN password VARCHAR(255) AFTER username");
        echo "<p style='color:green;'>✅ تم إضافة أعمدة اسم المستخدم وكلمة المرور في جدول أولياء الأمور.</p>";
    } else {
        echo "<p>✅ أعمدة اسم المستخدم وكلمة المرور موجودة بالفعل في جدول أولياء الأمور.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ في جدول guardians: " . $e->getMessage() . "</p>";
}

// 3. إنشاء حسابات للطلاب
echo "<h2>الطلاب</h2>";
$students = $pdo->query("SELECT id, name FROM students WHERE username IS NULL")->fetchAll();
if (count($students) > 0) {
    $default_password = '123456';
    $hashed = password_hash($default_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE students SET username = ?, password = ? WHERE id = ?");
    $count = 0;
    foreach ($students as $student) {
        $username = 'student_' . $student['id'];
        try {
            $stmt->execute([$username, $hashed, $student['id']]);
            $count++;
        } catch (PDOException $e) {
            echo "<p style='color:orange;'>⚠️ فشل تحديث الطالب {$student['name']} (ID: {$student['id']}): " . $e->getMessage() . "</p>";
        }
    }
    echo "<p style='color:green;'>✅ تم إنشاء $count حساب للطلاب. كلمة المرور الافتراضية: <strong>$default_password</strong></p>";
} else {
    echo "<p>✅ جميع الطلاب لديهم حسابات بالفعل.</p>";
}

// 4. إنشاء حسابات لأولياء الأمور
echo "<h2>أولياء الأمور</h2>";
$guardians = $pdo->query("SELECT id, name FROM guardians WHERE username IS NULL")->fetchAll();
if (count($guardians) > 0) {
    $default_password = '123456';
    $hashed = password_hash($default_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE guardians SET username = ?, password = ? WHERE id = ?");
    $count = 0;
    foreach ($guardians as $guardian) {
        $username = 'guardian_' . $guardian['id'];
        try {
            $stmt->execute([$username, $hashed, $guardian['id']]);
            $count++;
        } catch (PDOException $e) {
            echo "<p style='color:orange;'>⚠️ فشل تحديث ولي الأمر {$guardian['name']} (ID: {$guardian['id']}): " . $e->getMessage() . "</p>";
        }
    }
    echo "<p style='color:green;'>✅ تم إنشاء $count حساب لأولياء الأمور. كلمة المرور الافتراضية: <strong>$default_password</strong></p>";
} else {
    echo "<p>✅ جميع أولياء الأمور لديهم حسابات بالفعل.</p>";
}

echo "<hr>";
echo "<h3>معلومات الدخول الافتراضية</h3>";
echo "<ul>";
echo "<li><strong>الطلاب:</strong> اسم المستخدم = student_[رقم الطالب] , كلمة المرور = 123456</li>";
echo "<li><strong>أولياء الأمور:</strong> اسم المستخدم = guardian_[رقم ولي الأمر] , كلمة المرور = 123456</li>";
echo "</ul>";
echo "<p>⚠️ يرجى حذف هذا الملف بعد التأكد من نجاح العملية.</p>";
echo "<p><a href='login.php' class='btn'>العودة لتسجيل الدخول</a></p>";
?>