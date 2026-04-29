<?php
// ============================================
// ملف: ring_bulk_memorization.php
// الحفظ الجماعي للحلقة - صفحة منفصلة
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$ring_id = isset($_GET['ring_id']) ? (int)$_GET['ring_id'] : 0;

// جلب معلومات الحلقة
$ring = $pdo->prepare("
    SELECT r.*, t.name as teacher_name,
           (SELECT COUNT(*) FROM ring_students WHERE ring_id = r.id) as students_count
    FROM rings r
    LEFT JOIN teachers t ON r.teacher_id = t.id
    WHERE r.id = ?
");
$ring->execute([$ring_id]);
$ring_info = $ring->fetch();

if (!$ring_info) {
    $_SESSION['error'] = "❌ الحلقة غير موجودة";
    header("Location: rings.php");
    exit;
}

// التحقق من الصلاحية
if (isTeacher() && $ring_info['teacher_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "❌ لا تملك صلاحية الوصول لهذه الحلقة";
    header("Location: rings.php");
    exit;
}

$pageTitle = 'حفظ جماعي - ' . $ring_info['name'];
require_once 'includes/header.php';

// ============================================
// معالجة الحفظ الجماعي
// ============================================
$result_message = '';
$result_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_memorization'])) {
    $bulk_type = $_POST['bulk_type'];
    $notes = trim($_POST['notes'] ?? '');
    $result = null;
    
    try {
        if ($bulk_type == 'single_surah') {
            $surah_number = (int)$_POST['surah_number'];
            if ($surah_number >= 1 && $surah_number <= 114) {
                $result = addBulkMemorizationToRing($pdo, $ring_id, $surah_number, null, null, null, null, $notes);
            } else {
                throw new Exception("رقم سورة غير صحيح");
            }
        } 
        elseif ($bulk_type == 'multiple_surahs') {
            $surahs = isset($_POST['surahs']) ? array_map('intval', $_POST['surahs']) : [];
            if (!empty($surahs)) {
                $result = addMultipleBulkMemorizationToRing($pdo, $ring_id, $surahs, $notes);
            } else {
                throw new Exception("لم يتم اختيار أي سورة");
            }
        } 
        elseif ($bulk_type == 'by_pages') {
            $from_page = (int)$_POST['from_page'];
            $to_page = (int)$_POST['to_page'];
            if ($from_page >= 1 && $to_page <= 604 && $from_page <= $to_page) {
                $result = addBulkMemorizationByPages($pdo, $ring_id, $from_page, $to_page, $notes);
            } else {
                throw new Exception("نطاق الصفحات غير صحيح");
            }
        }
        
        if ($result && $result['success']) {
            $result_message = $result['message'] ?? "✅ تمت إضافة الحفظ لجميع طلاب الحلقة بنجاح";
            $result_type = 'success';
        } else {
            $result_message = $result['message'] ?? "❌ حدث خطأ في إضافة الحفظ";
            $result_type = 'error';
        }
        
    } catch (Exception $e) {
        $result_message = "❌ " . $e->getMessage();
        $result_type = 'error';
    }
}

// جلب السور المحفوظة سابقاً لهذه الحلقة (سجل الإضافات الجماعية)
$bulk_history = $pdo->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM students s 
            JOIN ring_students rs ON s.id = rs.student_id 
            WHERE rs.ring_id = b.ring_id) as students_count
    FROM ring_memorization_bulk b
    WHERE b.ring_id = ?
    ORDER BY b.added_at DESC
    LIMIT 20
");
$bulk_history->execute([$ring_id]);
$bulk_history = $bulk_history->fetchAll();
?>

<style>
.bulk-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 25px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.5rem;
}

.ring-info {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.ring-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #1e3c3f;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ring-stats {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: bold;
}

.bulk-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.bulk-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.bulk-card h3 {
    color: #1e3c3f;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid #c9a96b;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
}

.surahs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    background: #f8f9fa;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 5px;
    border-radius: 8px;
    transition: 0.3s;
}

.checkbox-item:hover {
    background: #e9ecef;
}

.checkbox-item input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.btn {
    padding: 12px 25px;
    border-radius: 30px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-sm {
    padding: 6px 15px;
    width: auto;
}

.btn-outline {
    background: transparent;
    border: 2px solid #c9a96b;
    color: #1e3c3f;
}

.btn-outline:hover {
    background: #c9a96b;
    color: white;
}

.alert {
    padding: 15px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}

.history-table {
    background: white;
    border-radius: 20px;
    padding: 20px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #1e3c3f;
    color: white;
    padding: 12px;
    text-align: center;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

@media (max-width: 768px) {
    .bulk-options {
        grid-template-columns: 1fr;
    }
    .surahs-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<section class="bulk-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-layer-group"></i>
            حفظ جماعي للحلقة
        </h1>
        <a href="rings.php" class="btn-outline" style="text-decoration: none; padding: 10px 20px;">
            <i class="fas fa-arrow-right"></i> العودة للحلقات
        </a>
    </div>

    <div class="ring-info">
        <div class="ring-name">
            <i class="fas fa-ring"></i>
            <?php echo htmlspecialchars($ring_info['name']); ?>
        </div>
        <div class="ring-stats">
            <i class="fas fa-users"></i> <?php echo $ring_info['students_count']; ?> طالب
        </div>
    </div>

    <?php if ($result_message): ?>
        <div class="alert alert-<?php echo $result_type; ?>">
            <i class="fas <?php echo $result_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $result_message; ?>
        </div>
    <?php endif; ?>

    <div class="bulk-options">
        <!-- بطاقة 1: إضافة سورة واحدة -->
        <div class="bulk-card">
            <h3><i class="fas fa-book-open"></i> إضافة سورة واحدة</h3>
            <form method="post">
                <input type="hidden" name="bulk_type" value="single_surah">
                <div class="form-group">
                    <label>اختر السورة</label>
                    <select name="surah_number" class="form-control" required>
                        <option value="">-- اختر --</option>
                        <?php for ($i = 1; $i <= 114; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ملاحظات (اختياري)</label>
                    <input type="text" name="notes" class="form-control" placeholder="مثال: تم حفظ السورة">
                </div>
                <button type="submit" name="bulk_memorization" class="btn btn-success">
                    <i class="fas fa-save"></i> إضافة لجميع الطلاب
                </button>
            </form>
        </div>

        <!-- بطاقة 2: إضافة عدة سور -->
        <div class="bulk-card">
            <h3><i class="fas fa-layer-group"></i> إضافة عدة سور</h3>
            <form method="post" id="multipleSurahsForm">
                <input type="hidden" name="bulk_type" value="multiple_surahs">
                <div class="form-group">
                    <label>اختر السور</label>
                    <div class="surahs-grid" id="surahsGrid">
                        <?php for ($i = 1; $i <= 114; $i++): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="surahs[]" value="<?php echo $i; ?>">
                                <span><?php echo $i; ?>. <?php echo getSurahName($i); ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>ملاحظات</label>
                    <input type="text" name="notes" class="form-control" placeholder="مثال: تم حفظ هذه السور">
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <button type="button" class="btn-sm btn-outline" onclick="selectAllSurahs()">تحديد الكل</button>
                    <button type="button" class="btn-sm btn-outline" onclick="deselectAllSurahs()">إلغاء التحديد</button>
                </div>
                <button type="submit" name="bulk_memorization" class="btn btn-success">
                    <i class="fas fa-save"></i> إضافة السور المحددة
                </button>
            </form>
        </div>

        <!-- بطاقة 3: إضافة حسب الصفحات -->
        <div class="bulk-card">
            <h3><i class="fas fa-file-alt"></i> إضافة حسب الصفحات</h3>
            <form method="post">
                <input type="hidden" name="bulk_type" value="by_pages">
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>من صفحة</label>
                        <input type="number" name="from_page" class="form-control" min="1" max="604" required>
                    </div>
                    <div class="form-group">
                        <label>إلى صفحة</label>
                        <input type="number" name="to_page" class="form-control" min="1" max="604" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>ملاحظات</label>
                    <input type="text" name="notes" class="form-control" placeholder="مثال: تم حفظ هذه الصفحات">
                </div>
                <button type="submit" name="bulk_memorization" class="btn btn-success">
                    <i class="fas fa-save"></i> إضافة الصفحات لجميع الطلاب
                </button>
            </form>
        </div>
    </div>

    <!-- سجل الإضافات السابقة -->
    <?php if (!empty($bulk_history)): ?>
    <div class="history-table">
        <h3 style="margin-bottom: 15px;">
            <i class="fas fa-history"></i> آخر الإضافات الجماعية
        </h3>
        <table>
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>السورة</th>
                    <th>نطاق الصفحات</th>
                    <th>عدد الطلاب</th>
                    <th>الملاحظات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bulk_history as $item): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($item['added_at'])); ?></td>
                        <td><?php echo getSurahName($item['surah_number']); ?></td>
                        <td>
                            <?php if ($item['from_page'] && $item['to_page']): ?>
                                صفحة <?php echo $item['from_page']; ?> - <?php echo $item['to_page']; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['students_count']; ?> طالب</span></td>
                        <td><?php echo htmlspecialchars($item['notes'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="action-buttons">
        <a href="rings.php" class="btn-secondary" style="text-decoration: none; text-align: center;">
            <i class="fas fa-arrow-right"></i> العودة للحلقات
        </a>
        <a href="ring_details.php?id=<?php echo $ring_id; ?>" class="btn-outline" style="text-decoration: none; text-align: center;">
            <i class="fas fa-info-circle"></i> تفاصيل الحلقة
        </a>
    </div>
</section>

<script>
function selectAllSurahs() {
    document.querySelectorAll('#surahsGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllSurahs() {
    document.querySelectorAll('#surahsGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>