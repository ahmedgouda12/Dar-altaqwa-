<?php
// ============================================
// ملف: add_multiple_students.php
// إضافة عدة طلاب دفعة واحدة - نسخة الجدول الديناميكي
// آخر تحديث: 2026-04-23
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'إضافة عدة طلاب دفعة واحدة';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$is_admin = isAdmin();

// جلب قائمة المعلمين للإدارة
if ($is_admin) {
    $teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
}

// جلب قائمة الحلقات حسب الصلاحية
if ($is_admin) {
    $rings = $pdo->query("SELECT id, name FROM rings ORDER BY name")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM rings WHERE teacher_id = ? ORDER BY name");
    $stmt->execute([$teacher_id]);
    $rings = $stmt->fetchAll();
}

$error = '';
$success = '';
$imported_count = 0;
$errors_list = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_students'])) {
    $names = $_POST['student_name'] ?? [];
    $categories = $_POST['category'] ?? [];
    $phones = $_POST['parent_phone'] ?? [];
    $levels = $_POST['level'] ?? [];
    
    $default_teacher_id = (int)($_POST['default_teacher_id'] ?? 0);
    $default_ring_id = (int)($_POST['default_ring_id'] ?? 0);
    $create_accounts = isset($_POST['create_accounts']);
    $default_password = $_POST['default_password'] ?? '123456';
    
    $imported_count = 0;
    $errors_list = [];
    
    foreach ($names as $index => $name) {
        $name = trim($name);
        if (empty($name)) continue;
        
        $category = $categories[$index] ?? 'boy';
        $parent_phone = trim($phones[$index] ?? '');
        $level = trim($levels[$index] ?? 'مبتدئ');
        
        // التحقق من صحة الفئة
        if (!in_array($category, ['boy', 'girl', 'child', 'woman'])) {
            $errors_list[] = "الطالب {$name}: الفئة غير صحيحة";
            continue;
        }
        
        if (empty($name)) {
            $errors_list[] = "السطر " . ($index + 1) . ": اسم الطالب مطلوب";
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            
            // تحديد المعلم
            $final_teacher_id = $default_teacher_id;
            if (isTeacher()) {
                $final_teacher_id = $teacher_id;
            }
            
            // تنظيف رقم الهاتف
            $parent_phone = preg_replace('/[^0-9]/', '', $parent_phone);
            if (!empty($parent_phone) && strlen($parent_phone) == 10 && substr($parent_phone, 0, 1) != '0') {
                $parent_phone = '0' . $parent_phone;
            }
            if (!empty($parent_phone) && strlen($parent_phone) == 12 && substr($parent_phone, 0, 2) == '20') {
                $parent_phone = '0' . substr($parent_phone, 2);
            }
            if (!empty($parent_phone) && strlen($parent_phone) < 10) {
                $parent_phone = '';
            }
            
            // إنشاء اسم مستخدم إذا مطلوب
            $username = null;
            $password = null;
            if ($create_accounts) {
                $username = 'student_' . time() . '_' . $imported_count . '_' . rand(100, 999);
                $password = password_hash($default_password, PASSWORD_DEFAULT);
            }
            
            // إدراج الطالب
            $stmt = $pdo->prepare("
                INSERT INTO students 
                (name, category, level, teacher_id, parent_phone, username, password, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name, 
                $category, 
                $level, 
                $final_teacher_id ?: null, 
                $parent_phone ?: null,
                $username,
                $password
            ]);
            
            $student_id = $pdo->lastInsertId();
            
            // إضافة للحلقة إذا وجدت
            if ($default_ring_id > 0) {
                $checkRing = $pdo->prepare("SELECT id FROM ring_students WHERE ring_id = ? AND student_id = ?");
                $checkRing->execute([$default_ring_id, $student_id]);
                if (!$checkRing->fetch()) {
                    $stmtRing = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
                    $stmtRing->execute([$default_ring_id, $student_id]);
                }
            }
            
            $pdo->commit();
            $imported_count++;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors_list[] = "الطالب {$name}: " . $e->getMessage();
        }
    }
    
    if ($imported_count > 0) {
        $success = "✅ تم إضافة $imported_count طالب بنجاح";
        if (!empty($errors_list)) {
            $success .= " (مع بعض الأخطاء)";
        }
    } elseif (empty($errors_list)) {
        $error = "⚠️ لم يتم إضافة أي طالب. يرجى إدخال بيانات الطلاب.";
    } else {
        $error = "❌ لم يتم إضافة أي طالب. يرجى مراجعة الأخطاء.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>إضافة عدة طلاب دفعة واحدة - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --secondary: #c9a96b;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --gray: #6c757d;
            --gray-light: #e9ecef;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .multiple-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* ===== رأس الصفحة ===== */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .page-header h1 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.8rem;
            position: relative;
            z-index: 2;
        }

        .page-header h1 i {
            color: var(--secondary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .header-stats {
            background: rgba(255,255,255,0.15);
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 1rem;
            position: relative;
            z-index: 2;
        }

        /* ===== بطاقة الإعدادات ===== */
        .settings-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .form-group label i {
            color: var(--secondary);
            margin-left: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .checkbox-label input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* ===== جدول الطلاب الديناميكي ===== */
        .students-table-wrapper {
            background: white;
            border-radius: 25px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .students-table th {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 15px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .students-table td {
            padding: 12px;
            border-bottom: 1px solid var(--gray-light);
            vertical-align: middle;
        }

        .students-table tr:last-child td {
            border-bottom: none;
        }

        .student-input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--gray-light);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .student-input:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .category-select {
            width: 100%;
            padding: 10px 8px;
            border: 2px solid var(--gray-light);
            border-radius: 10px;
            font-size: 0.85rem;
            background: white;
        }

        .remove-row-btn {
            background: var(--danger);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .remove-row-btn:hover {
            transform: scale(1.1);
            background: #c82333;
        }

        .add-row-btn {
            background: var(--info);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            transition: all 0.3s;
        }

        .add-row-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        /* ===== أزرار الإجراءات ===== */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            padding: 14px 25px;
            border-radius: 50px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        /* ===== رسائل ===== */
        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-right: 5px solid var(--success);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-right: 5px solid var(--danger);
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-right: 5px solid var(--warning);
        }

        .errors-list {
            margin-top: 15px;
            padding: 15px;
            background: #f8d7da;
            border-radius: 12px;
            color: #721c24;
        }

        .error-item {
            padding: 5px 0;
            font-size: 0.85rem;
            border-bottom: 1px solid #f5c6cb;
        }

        .error-item:last-child {
            border-bottom: none;
        }

        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 768px) {
            .multiple-page { padding: 15px; }
            .settings-grid { grid-template-columns: 1fr; }
            .students-table th, .students-table td { padding: 8px; }
            .student-input, .category-select { font-size: 0.8rem; padding: 8px; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

<section class="multiple-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-users-plus"></i>
            إضافة عدة طلاب دفعة واحدة
        </h1>
        <div class="header-stats">
            <i class="fas fa-table"></i>
            طريقة سهلة - املأ البيانات في الجدول
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors_list)): ?>
        <div class="errors-list">
            <strong><i class="fas fa-list"></i> الأخطاء:</strong>
            <?php foreach ($errors_list as $err): ?>
                <div class="error-item">⚠️ <?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج الإضافة -->
    <form method="post" id="studentsForm">
        <!-- الإعدادات العامة -->
        <div class="settings-card">
            <h3 style="margin-bottom: 20px; color: var(--primary);">
                <i class="fas fa-cog"></i> الإعدادات العامة
            </h3>
            <div class="settings-grid">
                <?php if ($is_admin): ?>
                <div class="form-group">
                    <label><i class="fas fa-chalkboard-teacher"></i> المعلم الافتراضي</label>
                    <select name="default_teacher_id" class="form-control">
                        <option value="0">-- بدون معلم --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (!empty($rings)): ?>
                <div class="form-group">
                    <label><i class="fas fa-ring"></i> الحلقة الافتراضية (اختياري)</label>
                    <select name="default_ring_id" class="form-control">
                        <option value="0">-- بدون حلقة --</option>
                        <?php foreach ($rings as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> إنشاء حسابات دخول</label>
                    <div class="checkbox-label">
                        <input type="checkbox" name="create_accounts" id="create_accounts" value="1">
                        <span>إنشاء اسم مستخدم وكلمة مرور لكل طالب</span>
                    </div>
                </div>

                <div class="form-group" id="passwordField" style="display: none;">
                    <label><i class="fas fa-key"></i> كلمة المرور الافتراضية</label>
                    <input type="text" name="default_password" class="form-control" value="123456">
                </div>
            </div>
        </div>

        <!-- جدول الطلاب -->
        <div class="students-table-wrapper">
            <h3 style="margin-bottom: 20px; color: var(--primary);">
                <i class="fas fa-table-list"></i> بيانات الطلاب
                <small style="color: var(--gray); font-size: 0.8rem;">(الحقول المطلوبة: الاسم والفئة)</small>
            </h3>
            
            <div style="overflow-x: auto;">
                <table class="students-table" id="studentsTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>اسم الطالب <span style="color: var(--danger);">*</span></th>
                            <th style="width: 100px;">الفئة <span style="color: var(--danger);">*</span></th>
                            <th style="width: 130px;">رقم الهاتف</th>
                            <th style="width: 100px;">المستوى</th>
                            <th style="width: 50px;"></th>
                        </thead>
                        </thead>
                    <tbody id="tableBody">
                        <tr class="student-row" data-row="0">
                            <td class="row-number">1</td>
                            <td><input type="text" name="student_name[]" class="student-input" placeholder="مثال: أحمد محمد"></td>
                            <td>
                                <select name="category[]" class="category-select">
                                    <option value="boy">أولاد</option>
                                    <option value="girl">بنات</option>
                                    <option value="child">أطفال</option>
                                    <option value="woman">نساء</option>
                                </select>
                            </td>
                            <td><input type="tel" name="parent_phone[]" class="student-input" placeholder="01012345678" dir="ltr"></td>
                            <td><input type="text" name="level[]" class="student-input" placeholder="مبتدئ"></td>
                            <td style="text-align: center;">
                                <button type="button" class="remove-row-btn" onclick="removeRow(this)" style="display: none;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="button" class="add-row-btn" onclick="addNewRow()">
                <i class="fas fa-plus-circle"></i> إضافة طالب آخر
            </button>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="action-buttons">
            <button type="submit" name="submit_students" class="btn btn-primary">
                <i class="fas fa-save"></i> إضافة الطلاب
            </button>
            <a href="students.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> إلغاء
            </a>
        </div>
    </form>

    <!-- معلومات مساعدة -->
    <div style="margin-top: 25px; background: #e7f3ff; border-radius: 20px; padding: 20px;">
        <h4 style="color: #0c5460; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-info-circle"></i> طريقة الاستخدام
        </h4>
        <ul style="margin-top: 10px; margin-right: 25px; color: #0c5460;">
            <li>📝 <strong>املأ البيانات في الجدول:</strong> كل صف يمثل طالباً واحداً</li>
            <li>⭐ <strong>الحقول المطلوبة:</strong> اسم الطالب والفئة (أولاد/بنات/أطفال/نساء)</li>
            <li>📱 <strong>رقم الهاتف:</strong> اختياري، يمكن تركه فارغاً</li>
            <li>📚 <strong>المستوى:</strong> اختياري، القيمة الافتراضية "مبتدئ"</li>
            <li>➕ <strong>إضافة المزيد:</strong> اضغط على زر "إضافة طالب آخر" لإضافة صف جديد</li>
            <li>🗑️ <strong>حذف صف:</strong> يمكن حذف أي صف (عدا الصف الأول) بالضغط على زر الحذف</li>
            <li>🔑 <strong>حسابات دخول:</strong> إذا اخترت "إنشاء حسابات"، سيتم إنشاء اسم مستخدم لكل طالب وكلمة مرور موحدة</li>
        </ul>
    </div>
</section>

<script>
let rowCounter = 1;

// إظهار/إخفاء حقل كلمة المرور
document.getElementById('create_accounts')?.addEventListener('change', function() {
    document.getElementById('passwordField').style.display = this.checked ? 'block' : 'none';
});

// إضافة صف جديد
function addNewRow() {
    const tbody = document.getElementById('tableBody');
    const newRow = document.createElement('tr');
    newRow.className = 'student-row';
    newRow.setAttribute('data-row', rowCounter);
    
    newRow.innerHTML = `
        <td class="row-number">${rowCounter + 1}</td>
        <td><input type="text" name="student_name[]" class="student-input" placeholder="مثال: أحمد محمد"></td>
        <td>
            <select name="category[]" class="category-select">
                <option value="boy">أولاد</option>
                <option value="girl">بنات</option>
                <option value="child">أطفال</option>
                <option value="woman">نساء</option>
            </select>
        </td>
        <td><input type="tel" name="parent_phone[]" class="student-input" placeholder="01012345678" dir="ltr"></td>
        <td><input type="text" name="level[]" class="student-input" placeholder="مبتدئ"></td>
        <td style="text-align: center;">
            <button type="button" class="remove-row-btn" onclick="removeRow(this)">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    rowCounter++;
    updateRowNumbers();
}

// حذف صف
function removeRow(button) {
    const row = button.closest('tr');
    if (row && document.querySelectorAll('.student-row').length > 1) {
        row.remove();
        updateRowNumbers();
    }
}

// تحديث أرقام الصفوف
function updateRowNumbers() {
    const rows = document.querySelectorAll('.student-row');
    rows.forEach((row, index) => {
        const rowNumberCell = row.querySelector('.row-number');
        if (rowNumberCell) {
            rowNumberCell.textContent = index + 1;
        }
        // إظهار زر الحذف لجميع الصفوف عدا الأول
        const removeBtn = row.querySelector('.remove-row-btn');
        if (removeBtn) {
            removeBtn.style.display = index === 0 ? 'none' : 'inline-flex';
        }
    });
}

// التحقق من صحة النموذج قبل الإرسال
document.getElementById('studentsForm')?.addEventListener('submit', function(e) {
    let hasData = false;
    const nameInputs = document.querySelectorAll('input[name="student_name[]"]');
    
    nameInputs.forEach(input => {
        if (input.value.trim() !== '') {
            hasData = true;
        }
    });
    
    if (!hasData) {
        e.preventDefault();
        alert('⚠️ الرجاء إدخال بيانات طالب واحد على الأقل');
        return false;
    }
    
    // تأكيد الإضافة
    return confirm('هل أنت متأكد من إضافة هؤلاء الطلاب؟');
});

console.log('✅ صفحة إضافة عدة طلاب - النسخة المبسطة جاهزة');
</script>

<?php require_once 'includes/footer.php'; ?>