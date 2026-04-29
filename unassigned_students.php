<?php
// ============================================
// ملف: unassigned_students.php
// عرض الطلاب غير المرتبطين بحلقات وإضافتهم إلى حلقات
// آخر تحديث: 2026-04-07
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الطلاب غير المرتبطين بحلقات';
require_once 'includes/header.php';

$message = '';
$message_type = '';

// ============================================
// جلب الطلاب غير المرتبطين بحلقات
// ============================================
$unassigned_students = $pdo->query("
    SELECT s.id, s.name, s.category, s.level, s.parent_phone, 
           t.id as teacher_id, t.name as teacher_name,
           (SELECT COUNT(*) FROM ring_students WHERE student_id = s.id) as rings_count
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.id NOT IN (SELECT DISTINCT student_id FROM ring_students)
    ORDER BY s.name
")->fetchAll();

// ============================================
// جلب جميع الحلقات لكل معلم
// ============================================
$rings_by_teacher = [];
$all_rings = $pdo->query("
    SELECT r.id, r.name, r.teacher_id, t.name as teacher_name,
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
    FROM rings r
    LEFT JOIN teachers t ON r.teacher_id = t.id
    ORDER BY t.name, r.name
")->fetchAll();

foreach ($all_rings as $ring) {
    $rings_by_teacher[$ring['teacher_id']][] = $ring;
}

// ============================================
// معالجة إضافة طالب إلى حلقة
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_ring'])) {
    $student_id = (int)$_POST['student_id'];
    $ring_id = (int)$_POST['ring_id'];
    
    // التحقق من عدم وجود الطالب مسبقاً في الحلقة
    $check = $pdo->prepare("SELECT id FROM ring_students WHERE ring_id = ? AND student_id = ?");
    $check->execute([$ring_id, $student_id]);
    
    if ($check->fetch()) {
        $message = "⚠️ هذا الطالب مسجل بالفعل في هذه الحلقة";
        $message_type = 'warning';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
            $stmt->execute([$ring_id, $student_id]);
            
            // تحديث عدد الطلاب في الحلقة
            $update_count = $pdo->prepare("
                UPDATE rings SET student_count = (SELECT COUNT(*) FROM ring_students WHERE ring_id = ?)
                WHERE id = ?
            ");
            $update_count->execute([$ring_id, $ring_id]);
            
            $message = "✅ تم إضافة الطالب إلى الحلقة بنجاح";
            $message_type = 'success';
            
            // تحديث القائمة
            $unassigned_students = $pdo->query("
                SELECT s.id, s.name, s.category, s.level, s.parent_phone, 
                       t.id as teacher_id, t.name as teacher_name,
                       (SELECT COUNT(*) FROM ring_students WHERE student_id = s.id) as rings_count
                FROM students s
                LEFT JOIN teachers t ON s.teacher_id = t.id
                WHERE s.id NOT IN (SELECT DISTINCT student_id FROM ring_students)
                ORDER BY s.name
            ")->fetchAll();
            
        } catch (PDOException $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ============================================
// معالجة إضافة عدة طلاب إلى حلقة دفعة واحدة
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_multiple_to_ring'])) {
    $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];
    $ring_id = (int)$_POST['ring_id'];
    $added = 0;
    $skipped = 0;
    
    foreach ($selected_students as $student_id) {
        $check = $pdo->prepare("SELECT id FROM ring_students WHERE ring_id = ? AND student_id = ?");
        $check->execute([$ring_id, $student_id]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO ring_students (ring_id, student_id) VALUES (?, ?)");
            $stmt->execute([$ring_id, $student_id]);
            $added++;
        } else {
            $skipped++;
        }
    }
    
    if ($added > 0) {
        // تحديث عدد الطلاب في الحلقة
        $update_count = $pdo->prepare("
            UPDATE rings SET student_count = (SELECT COUNT(*) FROM ring_students WHERE ring_id = ?)
            WHERE id = ?
        ");
        $update_count->execute([$ring_id, $ring_id]);
        
        $message = "✅ تم إضافة $added طالب إلى الحلقة" . ($skipped > 0 ? " (تخطي $skipped مضاف مسبقاً)" : "");
        $message_type = 'success';
        
        // تحديث القائمة
        $unassigned_students = $pdo->query("
            SELECT s.id, s.name, s.category, s.level, s.parent_phone, 
                   t.id as teacher_id, t.name as teacher_name,
                   (SELECT COUNT(*) FROM ring_students WHERE student_id = s.id) as rings_count
            FROM students s
            LEFT JOIN teachers t ON s.teacher_id = t.id
            WHERE s.id NOT IN (SELECT DISTINCT student_id FROM ring_students)
            ORDER BY s.name
        ")->fetchAll();
        
    } else {
        $message = "⚠️ لم يتم إضافة أي طالب (جميعهم مضافون مسبقاً)";
        $message_type = 'warning';
    }
}
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.unassigned-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 25px 30px;
    border-radius: 25px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.6rem;
}

.stats-badge {
    background: rgba(255,255,255,0.15);
    padding: 10px 25px;
    border-radius: 50px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ===== أزرار الإجراءات ===== */
.action-bar {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-select {
    padding: 10px 20px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.9rem;
    background: white;
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 10px 20px;
    padding-right: 40px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.9rem;
    width: 250px;
}

.search-box i {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.bulk-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn {
    padding: 8px 20px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: #212529;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-sm {
    padding: 5px 15px;
    font-size: 0.8rem;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== قائمة الطلاب ===== */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

.student-card {
    background: white;
    border-radius: 20px;
    padding: 0;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s;
    border: 1px solid #eee;
    position: relative;
    overflow: hidden;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.student-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--warning), var(--danger));
}

.card-checkbox {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 22px;
    height: 22px;
    cursor: pointer;
    z-index: 2;
}

.card-content {
    padding: 20px;
}

.student-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.student-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: #666;
}

.student-meta i {
    color: var(--secondary);
}

.student-details {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
}

.detail-row {
    display: flex;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px dashed #e9ecef;
    font-size: 0.85rem;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-icon {
    width: 30px;
    color: var(--secondary);
}

.detail-label {
    font-weight: 600;
    color: #2c3e50;
    min-width: 80px;
}

.ring-select {
    width: 100%;
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 0.85rem;
    margin-top: 10px;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 25px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

.alert {
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid var(--success);
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-right: 5px solid var(--warning);
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid var(--danger);
}

@media (max-width: 768px) {
    .unassigned-page { padding: 15px; }
    .students-grid { grid-template-columns: 1fr; }
    .action-bar { flex-direction: column; }
    .search-box input { width: 100%; }
    .bulk-actions { width: 100%; justify-content: center; }
    .filter-group { width: 100%; }
    .filter-select { flex: 1; }
}
</style>

<section class="unassigned-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-users-slash"></i>
            الطلاب غير المرتبطين بحلقات
        </h1>
        <div class="stats-badge">
            <i class="fas fa-user-graduate"></i>
            عدد الطلاب: <?php echo count($unassigned_students); ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($unassigned_students)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color: var(--success);"></i>
            <h3>جميع الطلاب مرتبطون بحلقات</h3>
            <p>لا يوجد طلاب غير مرتبطين بأي حلقة</p>
            <a href="students.php" class="btn btn-primary">العودة للطلاب</a>
        </div>
    <?php else: ?>
        
        <!-- شريط الإجراءات -->
        <div class="action-bar">
            <div class="filter-group">
                <select id="teacherFilter" class="filter-select" onchange="filterByTeacher()">
                    <option value="all">-- جميع المعلمين --</option>
                    <?php 
                    $teachers_list = [];
                    foreach ($unassigned_students as $s) {
                        if ($s['teacher_id'] && !isset($teachers_list[$s['teacher_id']])) {
                            $teachers_list[$s['teacher_id']] = $s['teacher_name'];
                        }
                    }
                    foreach ($teachers_list as $id => $name):
                    ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="bulkRingSelect" class="filter-select">
                    <option value="">-- اختر حلقة للإضافة الجماعية --</option>
                    <?php foreach ($all_rings as $ring): ?>
                        <option value="<?php echo $ring['id']; ?>">
                            <?php echo htmlspecialchars($ring['teacher_name'] . ' - ' . $ring['name']); ?>
                            (<?php echo $ring['students_count']; ?> طالب)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="ابحث عن طالب...">
            </div>
            
            <div class="bulk-actions">
                <button class="btn btn-info btn-sm" onclick="toggleSelectAll()">
                    <i class="fas fa-check-double"></i> تحديد الكل
                </button>
                <button class="btn btn-success btn-sm" onclick="addSelectedToRing()">
                    <i class="fas fa-layer-group"></i> إضافة المحددين إلى الحلقة
                </button>
            </div>
        </div>

        <!-- قائمة الطلاب -->
        <div class="students-grid" id="studentsGrid">
            <?php foreach ($unassigned_students as $student): 
                $teacher_rings = $rings_by_teacher[$student['teacher_id']] ?? [];
            ?>
                <div class="student-card" data-teacher="<?php echo $student['teacher_id']; ?>" data-name="<?php echo strtolower($student['name']); ?>">
                    <input type="checkbox" class="card-checkbox" value="<?php echo $student['id']; ?>">
                    <div class="card-content">
                        <div class="student-header">
                            <div class="student-avatar">
                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="student-info">
                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                <div class="student-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo $student['category'] == 'boy' ? 'أولاد' : ($student['category'] == 'girl' ? 'بنات' : ($student['category'] == 'child' ? 'أطفال' : 'نساء')); ?></span>
                                    <span><i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="student-details">
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <div class="detail-label">المعلم:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></div>
                            </div>
                            <?php if (!empty($student['parent_phone'])): ?>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-phone"></i></div>
                                <div class="detail-label">ولي الأمر:</div>
                                <div class="detail-value" dir="ltr"><?php echo $student['parent_phone']; ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($teacher_rings)): ?>
                        <form method="post" class="add-form">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <select name="ring_id" class="ring-select" required>
                                <option value="">-- اختر حلقة --</option>
                                <?php foreach ($teacher_rings as $ring): ?>
                                    <option value="<?php echo $ring['id']; ?>">
                                        <?php echo htmlspecialchars($ring['name']); ?> (<?php echo $ring['students_count']; ?> طالب)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="add_to_ring" class="btn btn-primary btn-sm" style="width: 100%; margin-top: 10px;">
                                <i class="fas fa-plus-circle"></i> إضافة إلى الحلقة
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert-warning" style="padding: 10px; border-radius: 10px; text-align: center; font-size: 0.8rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                            لا توجد حلقات لهذا المعلم
                            <a href="add_ring.php" style="display: block; margin-top: 5px; color: var(--primary);">إضافة حلقة</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- نموذج الإضافة الجماعية (مخفي) -->
        <form method="post" id="bulkForm" style="display: none;">
            <input type="hidden" name="add_multiple_to_ring" value="1">
            <input type="hidden" name="ring_id" id="bulkRingId">
            <div id="bulkStudents"></div>
        </form>
        
    <?php endif; ?>
</section>

<script>
// فلترة حسب المعلم
function filterByTeacher() {
    const teacherId = document.getElementById('teacherFilter').value;
    const cards = document.querySelectorAll('.student-card');
    
    cards.forEach(card => {
        const cardTeacher = card.getAttribute('data-teacher');
        if (teacherId === 'all' || cardTeacher === teacherId) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// البحث
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.student-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name') || '';
        if (name.includes(searchTerm) || searchTerm === '') {
            card.style.display = 'block';
        } else {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// تحديد الكل
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.card-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

// إضافة المحددين إلى حلقة
function addSelectedToRing() {
    const ringId = document.getElementById('bulkRingSelect').value;
    if (!ringId) {
        alert('⚠️ يرجى اختيار حلقة أولاً');
        return;
    }
    
    const selectedStudents = [];
    document.querySelectorAll('.card-checkbox:checked').forEach(cb => {
        selectedStudents.push(cb.value);
    });
    
    if (selectedStudents.length === 0) {
        alert('⚠️ يرجى اختيار طالب واحد على الأقل');
        return;
    }
    
    if (confirm(`هل أنت متأكد من إضافة ${selectedStudents.length} طالب إلى الحلقة المحددة؟`)) {
        const bulkForm = document.getElementById('bulkForm');
        const bulkStudents = document.getElementById('bulkStudents');
        
        // تفريغ وتعبئة الطلاب المحددين
        bulkStudents.innerHTML = '';
        selectedStudents.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_students[]';
            input.value = id;
            bulkStudents.appendChild(input);
        });
        
        document.getElementById('bulkRingId').value = ringId;
        bulkForm.submit();
    }
}

console.log('✅ صفحة الطلاب غير المرتبطين بحلقات جاهزة');
</script>

<?php require_once 'includes/footer.php'; ?>