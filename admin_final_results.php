<?php
require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'نتائج الاختبارات النهائية';
require_once 'includes/header.php';

// جلب جميع الطلاب الذين أكملوا الاختبارات النهائية
$query = "
    SELECT 
        s.id,
        s.name,
        s.category,
        t.name as teacher_name,
        fse.total_parts,
        fse.final_average,
        fse.end_date,
        (SELECT COUNT(*) FROM memorization_sessions WHERE student_exam_id = fse.id) as sessions_done
    FROM students s
    JOIN final_student_exams fse ON s.id = fse.student_id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE fse.status = 'completed'
    ORDER BY fse.total_parts DESC, fse.final_average DESC
";
$results = $pdo->query($query)->fetchAll();

// تجميع النتائج حسب عدد الأجزاء
$grouped = [];
foreach ($results as $row) {
    $parts = $row['total_parts'];
    if (!isset($grouped[$parts])) {
        $grouped[$parts] = [];
    }
    $grouped[$parts][] = $row;
}

// ترتيب المجموعات تنازلياً (من الأكبر للأصغر)
krsort($grouped);

// تصنيفات الفئات
$categories = [
    'boy' => 'أولاد',
    'girl' => 'بنات',
    'child' => 'أطفال',
    'woman' => 'نساء'
];

// إحصائيات عامة
$total_students = count($results);
$total_30 = count(array_filter($results, fn($r) => $r['total_parts'] >= 30));
$avg_score = $total_students > 0 ? array_sum(array_column($results, 'final_average')) / $total_students : 0;
?>

<!-- إضافة مكتبة html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
/* ===== التصميم العام ===== */
.results-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

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
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

.page-header h1 {
    margin: 0;
    font-size: 2.2rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: #c9a96b;
}

.export-buttons {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 12px 28px;
    border-radius: 60px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: white;
    font-size: 1rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-pdf {
    background: #dc3545;
}
.btn-print {
    background: #6c757d;
}
.btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2.8rem;
    font-weight: 800;
    color: #1e3c3f;
    line-height: 1.2;
}

.stat-label {
    color: #666;
    font-size: 1rem;
    font-weight: 600;
    margin-top: 8px;
}

.parts-group {
    background: white;
    border-radius: 30px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border-right: 10px solid;
    transition: 0.3s;
}

.parts-group:hover {
    transform: translateX(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
}

.parts-group-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    flex-wrap: wrap;
}

.parts-badge {
    background: #1e3c3f;
    color: white;
    padding: 10px 35px;
    border-radius: 60px;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: 1px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.parts-count {
    background: #28a745;
    color: white;
    padding: 8px 25px;
    border-radius: 60px;
    font-weight: bold;
    font-size: 1.2rem;
}

.podium {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 25px;
    margin: 40px 0;
    flex-wrap: wrap;
}

.podium-item {
    text-align: center;
    padding: 25px;
    border-radius: 20px;
    background: white;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    min-width: 240px;
    transition: 0.3s;
}

.podium-1 {
    background: linear-gradient(145deg, #ffffff, #fff3d1);
    border: 4px solid gold;
    transform: scale(1.08);
    box-shadow: 0 20px 40px rgba(255,215,0,0.3);
}
.podium-2 {
    border: 4px solid silver;
}
.podium-3 {
    border: 4px solid #cd7f32;
}

.podium-rank {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 10px;
}
.podium-rank-1 { color: gold; text-shadow: 0 2px 5px rgba(255,215,0,0.5); }
.podium-rank-2 { color: silver; }
.podium-rank-3 { color: #cd7f32; }

.podium-name {
    font-size: 1.4rem;
    font-weight: 800;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.podium-score {
    font-size: 1.8rem;
    color: #28a745;
    font-weight: 800;
}

.category-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 40px;
    font-size: 0.9rem;
    font-weight: 700;
    color: white;
    margin-top: 8px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
}
.category-boy { background: #3498db; }
.category-girl { background: #9b59b6; }
.category-child { background: #f39c12; }
.category-woman { background: #6f42c1; }

.ranking-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 25px;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.ranking-table th {
    background: #1e3c3f;
    color: white;
    padding: 15px;
    text-align: center;
    font-weight: 700;
    font-size: 1rem;
}

.ranking-table td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
    font-weight: 500;
}

.ranking-table tr:hover {
    background: #f8f9fa;
}

.rank-1 { background: rgba(255,215,0,0.15); }
.rank-2 { background: rgba(192,192,192,0.15); }
.rank-3 { background: rgba(205,127,50,0.15); }

.empty-state {
    text-align: center;
    padding: 80px;
    background: white;
    border-radius: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 80px;
    color: #dee2e6;
    margin-bottom: 20px;
}

@media print {
    .no-print, .export-buttons, .navbar, footer, .site-header {
        display: none !important;
    }
    body { background: white; }
    .results-page { padding: 10px; }
    .page-header { background: #1e3c3f; color: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .parts-group { break-inside: avoid; border-right: 8px solid; }
    .podium-1 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

@media (max-width: 768px) {
    .podium {
        flex-direction: column;
        align-items: center;
    }
    .podium-item { width: 100%; }
    .podium-1 { transform: scale(1); }
}
</style>

<section class="results-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-trophy"></i>
            نتائج الاختبارات النهائية
        </h1>
        <div class="export-buttons no-print">
            <button class="btn btn-pdf" onclick="generatePDF()">
                <i class="fas fa-file-pdf"></i> تصدير PDF
            </button>
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>
    </div>

    <?php if (empty($results)): ?>
        <div class="empty-state">
            <i class="fas fa-graduation-cap"></i>
            <h2>لا توجد نتائج بعد</h2>
            <p>لم يكمل أي طالب الاختبارات النهائية حتى الآن</p>
        </div>
    <?php else: ?>
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">إجمالي المختبرين</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_30; ?></div>
                <div class="stat-label">أتموا القرآن كاملاً</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round($avg_score, 1); ?>%</div>
                <div class="stat-label">متوسط الدرجات</div>
            </div>
        </div>

        <div id="results-container">
            <?php foreach ($grouped as $parts => $students): 
                // ترتيب الطلاب حسب الدرجة
                usort($students, fn($a, $b) => $b['final_average'] <=> $a['final_average']);
                $top3 = array_slice($students, 0, 3);
                $others = array_slice($students, 3);
            ?>
                <div class="parts-group" style="border-right-color: <?php 
                    echo $parts >= 30 ? '#28a745' : ($parts >= 20 ? '#17a2b8' : ($parts >= 10 ? '#ffc107' : '#dc3545')); 
                ?>;">
                    <div class="parts-group-header">
                        <div class="parts-badge"><?php echo $parts; ?> أجزاء</div>
                        <div class="parts-count"><?php echo count($students); ?> طالب</div>
                    </div>

                    <!-- منصة التتويج (أول 3) -->
                    <?php if (!empty($top3)): ?>
                        <div class="podium">
                            <?php foreach ($top3 as $index => $student): 
                                $rank = $index + 1;
                            ?>
                                <div class="podium-item podium-<?php echo $rank; ?>">
                                    <div class="podium-rank podium-rank-<?php echo $rank; ?>">
                                        <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                    </div>
                                    <div class="podium-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <div class="podium-score"><?php echo round($student['final_average'], 2); ?>%</div>
                                    <div>
                                        <span class="category-badge category-<?php echo $student['category']; ?>">
                                            <?php echo $categories[$student['category']] ?? $student['category']; ?>
                                        </span>
                                    </div>
                                    <?php if ($student['teacher_name']): ?>
                                        <div style="font-size:0.9rem; color:#666; margin-top:8px;">
                                            <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($student['teacher_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- باقي المراكز (جدول) -->
                    <?php if (!empty($others)): ?>
                        <table class="ranking-table">
                            <thead>
                                <tr>
                                    <th>المركز</th>
                                    <th>اسم الطالب</th>
                                    <th>الفئة</th>
                                    <th>المعلم</th>
                                    <th>المعدل</th>
                                    <th>تاريخ الانتهاء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($others as $idx => $student): 
                                    $rank = $idx + 4; // يبدأ من المركز الرابع
                                ?>
                                    <tr class="rank-<?php echo $rank <= 10 ? $rank : ''; ?>">
                                        <td><strong>#<?php echo $rank; ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td>
                                            <span class="category-badge category-<?php echo $student['category']; ?>">
                                                <?php echo $categories[$student['category']] ?? $student['category']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['teacher_name'] ?? '-'); ?></td>
                                        <td style="color:#28a745; font-weight:bold;"><?php echo round($student['final_average'], 2); ?>%</td>
                                        <td><?php echo $student['end_date']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- قالب PDF مخفي خاص بالمعلمين -->
<div id="pdf-teachers-template" style="display: none;">
    <?php
    // جلب جميع المعلمين الذين لديهم طلاب مكتملون
    $teachers_query = "
        SELECT DISTINCT t.id, t.name
        FROM teachers t
        JOIN students s ON s.teacher_id = t.id
        JOIN final_student_exams fse ON s.id = fse.student_id
        WHERE fse.status = 'completed'
        ORDER BY t.name
    ";
    $teachers = $pdo->query($teachers_query)->fetchAll();

    foreach ($teachers as $teacher):
        // جلب طلاب هذا المعلم
        $students_query = "
            SELECT 
                s.id,
                s.name,
                s.category,
                fse.total_parts,
                fse.final_average,
                fse.end_date
            FROM students s
            JOIN final_student_exams fse ON s.id = fse.student_id
            WHERE s.teacher_id = ? AND fse.status = 'completed'
            ORDER BY fse.total_parts DESC, fse.final_average DESC
        ";
        $stmt = $pdo->prepare($students_query);
        $stmt->execute([$teacher['id']]);
        $teacher_students = $stmt->fetchAll();

        // تجميع طلاب هذا المعلم حسب عدد الأجزاء
        $teacher_grouped = [];
        foreach ($teacher_students as $row) {
            $parts = $row['total_parts'];
            $teacher_grouped[$parts][] = $row;
        }
        krsort($teacher_grouped);
    ?>
        <div class="pdf-teacher-section" style="margin-bottom: 40px; page-break-after: always;">
            <h2 style="color: #1e3c3f; border-bottom: 3px solid #c9a96b; padding-bottom: 10px;">
                <i class="fas fa-chalkboard-teacher"></i> معلم: <?php echo htmlspecialchars($teacher['name']); ?>
            </h2>
            
            <?php foreach ($teacher_grouped as $parts => $students): 
                // ترتيب حسب الدرجة
                usort($students, fn($a, $b) => $b['final_average'] <=> $a['final_average']);
                $top3 = array_slice($students, 0, 3);
                $others = array_slice($students, 3);
            ?>
                <div class="pdf-parts-group" style="margin: 20px 0; border-right: 6px solid <?php 
                    echo $parts >= 30 ? '#28a745' : ($parts >= 20 ? '#17a2b8' : ($parts >= 10 ? '#ffc107' : '#dc3545')); 
                ?>; background: #f8f9fa; padding: 15px; border-radius: 10px;">
                    
                    <h3 style="color: #1e3c3f; margin-top: 0;"><?php echo $parts; ?> أجزاء</h3>
                    
                    <!-- منصة التتويج -->
                    <?php if (!empty($top3)): ?>
                        <div style="display: flex; gap: 15px; justify-content: center; margin: 20px 0;">
                            <?php foreach ($top3 as $index => $student): 
                                $rank = $index + 1;
                                $rankColor = $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : '#cd7f32');
                            ?>
                                <div style="text-align: center; padding: 15px; background: white; border-radius: 10px; border: 2px solid <?php echo $rankColor; ?>; min-width: 180px;">
                                    <div style="font-size: 2rem;"><?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?></div>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <div style="color: #28a745; font-size: 1.3rem;"><?php echo round($student['final_average'], 2); ?>%</div>
                                    <div style="font-size: 0.8rem;"><?php echo $categories[$student['category']] ?? $student['category']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- باقي المراكز -->
                    <?php if (!empty($others)): ?>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                            <thead>
                                <tr style="background: #1e3c3f; color: white;">
                                    <th style="padding: 8px;">المركز</th>
                                    <th style="padding: 8px;">الطالب</th>
                                    <th style="padding: 8px;">الفئة</th>
                                    <th style="padding: 8px;">المعدل</th>
                                    <th style="padding: 8px;">تاريخ الانتهاء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($others as $idx => $student): 
                                    $rank = $idx + 4;
                                ?>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px; text-align: center;">#<?php echo $rank; ?></td>
                                        <td style="padding: 8px;"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td style="padding: 8px; text-align: center;">
                                            <span style="background: <?php 
                                                echo $student['category'] == 'boy' ? '#3498db' : ($student['category'] == 'girl' ? '#9b59b6' : ($student['category'] == 'child' ? '#f39c12' : '#6f42c1')); 
                                            ?>; color: white; padding: 3px 10px; border-radius: 20px;">
                                                <?php echo $categories[$student['category']] ?? $student['category']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px; text-align: center; color: #28a745; font-weight: bold;">
                                            <?php echo round($student['final_average'], 2); ?>%
                                        </td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $student['end_date']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
<script>
// دالة إنشاء PDF (للتقرير العام)
function generatePDF() {
    const element = document.getElementById('results-container');
    const header = document.querySelector('.page-header').cloneNode(true);
    if (header.querySelector('.export-buttons')) {
        header.querySelector('.export-buttons').remove();
    }
    
    const wrapper = document.createElement('div');
    wrapper.appendChild(header.cloneNode(true));
    wrapper.appendChild(element.cloneNode(true));
    
    const opt = {
        margin:        [0.5, 0.5, 0.5, 0.5],
        filename:     'نتائج_الاختبارات_النهائية.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, letterRendering: true, useCORS: true },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' },
        pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    html2pdf().set(opt).from(wrapper).save();
}

// دالة إنشاء PDF حسب المعلمين (يمكن استدعاؤها من زر منفصل إذا أردت)
function generateTeachersPDF() {
    const pdfContent = document.getElementById('pdf-teachers-template').cloneNode(true);
    pdfContent.style.display = 'block';
    
    const header = document.createElement('div');
    header.innerHTML = `
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #1e3c3f;">نتائج الاختبارات النهائية - حسب المعلمين</h1>
            <p style="color: #666;">تاريخ التقرير: ${new Date().toLocaleDateString('ar-EG')}</p>
        </div>
    `;
    pdfContent.insertBefore(header, pdfContent.firstChild);
    
    const opt = {
        margin:        [0.5, 0.5, 0.5, 0.5],
        filename:     'نتائج_حسب_المعلمين.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, letterRendering: true, useCORS: true },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' },
        pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    html2pdf().set(opt).from(pdfContent).save();
}

// التحقق من تحميل المكتبة
document.addEventListener('DOMContentLoaded', function() {
    if (typeof html2pdf === 'undefined') {
        console.error('⚠️ مكتبة html2pdf لم يتم تحميلها!');
    } else {
        console.log('✅ مكتبة html2pdf جاهزة');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>