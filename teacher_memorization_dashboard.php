<?php
// ============================================
// ملف: teacher_memorization_dashboard.php - لوحة تحكم حفظ الطلاب للمعلم
// مع إمكانية إضافة سورة واحدة أو عدة سور لكل طالب
// ============================================

ob_start();
require_once 'config.php';

if (!isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'لوحة تحكم حفظ الطلاب';
$teacher_id = $_SESSION['user_id'];

// تحديث إحصائيات جميع طلاب المعلم
$my_students = $pdo->prepare("SELECT id FROM students WHERE teacher_id = ?");
$my_students->execute([$teacher_id]);
foreach ($my_students->fetchAll() as $student) {
    updateStudentPartsStats($pdo, $student['id']);
}

// جلب الطلاب مع إحصائياتهم
$students = $pdo->prepare("
    SELECT 
        s.id, s.name, s.level,
        COUNT(sp.id) as surahs_count,
        MAX(sp.completed_at) as last_memorized,
        sps.total_parts,
        sps.total_pages
    FROM students s
    LEFT JOIN student_surah_progress sp ON s.id = sp.student_id AND sp.completed = 1
    LEFT JOIN student_parts_stats sps ON s.id = sps.student_id
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY sps.total_parts DESC, sps.total_pages DESC
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// إحصائيات سريعة
$total_students = count($students);
$total_surahs = array_sum(array_column($students, 'surahs_count'));
$total_pages = array_sum(array_column($students, 'total_pages'));
$completed_quran = count(array_filter($students, fn($s) => ($s['total_parts'] ?? 0) >= 30));

// قائمة السور للاختيار
$surah_list = [];
for ($i = 1; $i <= 114; $i++) {
    $surah_list[$i] = $i . '. ' . getSurahName($i);
}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دار التقوى - <?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .page-header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 25px 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .stat-number { font-size: 2rem; font-weight: 800; color: #1e3c3f; }
        .students-table {
            background: white;
            border-radius: 25px;
            overflow-x: auto;
            padding: 20px;
        }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: #1e3c3f; color: white; padding: 15px; text-align: center; }
        td { padding: 12px; text-align: center; border-bottom: 1px solid #eee; }
        .badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-gold { background: linear-gradient(135deg, gold, #ffd700); color: #1e3c3f; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 8px 15px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
        }
        .action-btn.view { background: #17a2b8; }
        .action-btn.add { background: #28a745; }
        .action-btn.add-multiple { background: #c9a96b; color: #1e3c3f; }
        .action-btn.delete { background: #dc3545; }
        .alert { padding: 15px; border-radius: 15px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        /* النوافذ المنبثقة */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 25px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c9a96b;
        }
        .modal-header h3 { color: #1e3c3f; }
        .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #999;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e3c3f; }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
        }
        .surahs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 12px;
        }
        .surah-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
        }
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .btn-modal { flex: 1; padding: 12px; border-radius: 30px; border: none; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #1e3c3f; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; text-align: center; }
            .action-buttons { flex-direction: column; align-items: center; }
            .action-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-quran"></i> لوحة تحكم حفظ الطلاب</h1>
        <div class="stats-badge">إجمالي السور المحفوظة: <?php echo $total_surahs; ?></div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo $total_students; ?></div><div>إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $total_surahs; ?></div><div>سور محفوظة</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo number_format($total_pages); ?></div><div>صفحة فريدة</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#28a745;"><?php echo $completed_quran; ?></div><div>خاتمين للقرآن</div></div>
    </div>

    <div class="students-table">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-list"></i> قائمة الطلاب وتقدمهم</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>السور</th>
                    <th>الصفحات</th>
                    <th>الأجزاء</th>
                    <th>آخر إضافة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $index => $student): 
                    $parts = $student['total_parts'] ?? 0;
                    $pages = $student['total_pages'] ?? 0;
                    $surahs = $student['surahs_count'] ?? 0;
                    
                    if ($parts >= 30) {
                        $badge_class = 'badge-gold';
                        $badge_text = '🎓 ختم القرآن';
                    } elseif ($parts >= 20) {
                        $badge_class = 'badge-success';
                        $badge_text = $parts . ' جزء';
                    } elseif ($parts >= 10) {
                        $badge_class = 'badge-info';
                        $badge_text = $parts . ' جزء';
                    } elseif ($parts >= 1) {
                        $badge_class = 'badge-warning';
                        $badge_text = $parts . ' جزء';
                    } else {
                        $badge_class = 'badge-danger';
                        $badge_text = 'مبتدئ';
                    }
                ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td style="text-align: right;">
                            <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($student['level'] ?? 'مبتدئ'); ?></small>
                        </td>
                        <td><?php echo $surahs; ?> / 114</span></td>
                        <td><?php echo number_format($pages); ?> / 604</span></td>
                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span></td>
                        <td><?php echo $student['last_memorized'] ? date('Y-m-d', strtotime($student['last_memorized'])) : '-'; ?></td>
                        <td class="action-buttons">
                            <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="action-btn view" title="عرض التقدم">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                            <button class="action-btn add" title="إضافة سورة" onclick="openAddSurahModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
                                <i class="fas fa-plus"></i> إضافة سورة
                            </button>
                            <button class="action-btn add-multiple" title="إضافة عدة سور" onclick="openAddMultipleModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
                                <i class="fas fa-layer-group"></i> إضافة عدة
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- نافذة إضافة سورة واحدة -->
<div class="modal" id="addSurahModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> إضافة سورة محفوظة</h3>
            <button class="close-modal" onclick="closeAddSurahModal()">&times;</button>
        </div>
        <form method="post" action="add_surah.php">
            <input type="hidden" name="student_id" id="singleStudentId">
            <div id="singleStudentName" style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-book-open"></i> اختر السورة</label>
                <select name="surah_number" class="form-control" required>
                    <option value="">-- اختر السورة --</option>
                    <?php for ($i = 1; $i <= 114; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?>. <?php echo getSurahName($i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-modal btn-secondary" onclick="closeAddSurahModal()">إلغاء</button>
                <button type="submit" name="add_surah" class="btn-modal btn-primary">إضافة السورة</button>
            </div>
        </form>
    </div>
</div>

<!-- نافذة إضافة عدة سور -->
<div class="modal" id="addMultipleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> إضافة عدة سور دفعة واحدة</h3>
            <button class="close-modal" onclick="closeAddMultipleModal()">&times;</button>
        </div>
        <form method="post" action="add_multiple_surahs.php">
            <input type="hidden" name="student_id" id="multipleStudentId">
            <div id="multipleStudentName" style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: bold;"></div>
            
            <div class="form-group">
                <label><i class="fas fa-quran"></i> اختر السور</label>
                <div class="surahs-grid" id="surahsGrid">
                    <?php for ($i = 1; $i <= 114; $i++): ?>
                        <label class="surah-checkbox">
                            <input type="checkbox" name="surahs[]" value="<?php echo $i; ?>">
                            <span><?php echo $i; ?>. <?php echo getSurahName($i); ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <small>يمكنك اختيار أكثر من سورة (Ctrl + نقرة متعددة)</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-modal btn-secondary" onclick="selectAllSurahs()">تحديد الكل</button>
                <button type="button" class="btn-modal btn-secondary" onclick="deselectAllSurahs()">إلغاء التحديد</button>
                <button type="submit" name="add_multiple" class="btn-modal btn-primary">إضافة السور</button>
            </div>
        </form>
    </div>
</div>

<script>
// نافذة إضافة سورة واحدة
function openAddSurahModal(studentId, studentName) {
    document.getElementById('singleStudentId').value = studentId;
    document.getElementById('singleStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('addSurahModal').classList.add('show');
}

function closeAddSurahModal() {
    document.getElementById('addSurahModal').classList.remove('show');
}

// نافذة إضافة عدة سور
function openAddMultipleModal(studentId, studentName) {
    document.getElementById('multipleStudentId').value = studentId;
    document.getElementById('multipleStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('addMultipleModal').classList.add('show');
}

function closeAddMultipleModal() {
    document.getElementById('addMultipleModal').classList.remove('show');
}

// تحديد الكل في السور
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

// إغلاق النوافذ بالنقر خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>

</body>
</html>

<?php require_once 'includes/footer.php'; ?>