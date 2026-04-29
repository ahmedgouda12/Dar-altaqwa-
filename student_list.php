<?php
require_once 'config.php';
if (!isAdmin()) {
    redirect('login.php');
}
$pageTitle = 'قائمة الطلاب وبيانات الدخول';
require_once 'includes/header.php';

$students = $pdo->query("
    SELECT s.id, s.name, s.parent_phone, s.username, s.level, t.name as teacher_name
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    ORDER BY s.name
")->fetchAll();
?>
<style>
    .student-list {
        max-width: 1200px;
        margin: 0 auto;
    }
    .toolbar {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    th {
        background: #063b5e;
        color: white;
        padding: 12px;
        font-size: 0.95rem;
    }
    td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        text-align: center;
    }
    tr:hover {
        background-color: #f8f9fa;
    }
    .default-pass {
        background: #fff3cd;
        color: #856404;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-block;
    }
    .note {
        margin-top: 20px;
        padding: 15px;
        background: #d4edda;
        color: #155724;
        border-radius: 10px;
        border-right: 5px solid #28a745;
    }
    @media print {
        .no-print, .toolbar, .site-header, .footer { display: none; }
        body { background: white; }
        table { box-shadow: none; }
        th { background: #063b5e; color: white; }
    }
    @media (max-width: 768px) {
        table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<section class="student-list">
    <h2 class="card-title"><i class="fas fa-list"></i> قائمة الطلاب وبيانات الدخول</h2>

    <div class="toolbar no-print">
        <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> طباعة القائمة</button>
        <a href="export_students_csv.php" class="btn" style="background: #28a745;"><i class="fas fa-file-csv"></i> تصدير Excel</a>
        <a href="create_accounts.php" class="btn" style="background: #17a2b8;">إنشاء حسابات للطلاب</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم الطالب</th>
                <th>ولي الأمر (رقم الهاتف)</th>
                <th>المعلم</th>
                <th>المستوى</th>
                <th>اسم المستخدم</th>
                <th>كلمة المرور المؤقتة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $index => $s): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td dir="ltr"><?= !empty($s['parent_phone']) ? htmlspecialchars($s['parent_phone']) : '<span style="color:#999;">لا يوجد</span>' ?></td>
                <td><?= htmlspecialchars($s['teacher_name'] ?? 'غير محدد') ?></td>
                <td><?= htmlspecialchars($s['level'] ?? 'مبتدئ') ?></td>
                <td>
                    <?php if ($s['username']): ?>
                        <strong><?= htmlspecialchars($s['username']) ?></strong>
                    <?php else: ?>
                        <span style="color:#999;">لم يُنشأ</span>
                    <?php endif; ?>
                </td>
                <td><span class="default-pass">123456</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="note no-print">
        <i class="fas fa-info-circle"></i>
        <strong>ملاحظات مهمة:</strong>
        <ul style="margin: 10px 0 0 20px;">
            <li>كلمة المرور المؤقتة موحدة لجميع الطلاب <strong>123456</strong>، يمكن تغييرها بعد أول تسجيل دخول.</li>
            <li>الطلاب الذين ليس لديهم اسم مستخدم بعد، اضغط على "إنشاء حسابات للطلاب" أولاً.</li>
            <li>يمكن طباعة هذه القائمة أو تصديرها كملف Excel لتوزيعها على الطلاب.</li>
        </ul>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>