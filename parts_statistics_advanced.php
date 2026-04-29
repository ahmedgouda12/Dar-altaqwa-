<?php
// ============================================
// ملف: parts_statistics_advanced.php
// إحصائيات الأجزاء المتقدمة (بدقة)
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'إحصائيات الأجزاء المتقدمة';
require_once 'includes/header.php';

// تحديث إحصائيات جميع الطلاب
$all_students = $pdo->query("SELECT id FROM students")->fetchAll();
foreach ($all_students as $student) {
    updateStudentPartsStats($pdo, $student['id']);
}

// إحصائيات عامة
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$students_with_memorization = $pdo->query("SELECT COUNT(*) FROM student_parts_stats WHERE total_parts > 0")->fetchColumn();
$completed_quran = $pdo->query("SELECT COUNT(*) FROM student_parts_stats WHERE total_parts >= 30")->fetchColumn();
$total_pages = $pdo->query("SELECT COALESCE(SUM(total_pages), 0) FROM student_parts_stats")->fetchColumn();
$avg_parts = $pdo->query("SELECT COALESCE(AVG(total_parts), 0) FROM student_parts_stats")->fetchColumn();

// توزيع الأجزاء (دقيق)
$precise_distribution = $pdo->query("
    SELECT 
        FLOOR(total_parts) as part_number,
        COUNT(*) as count,
        AVG((total_parts - FLOOR(total_parts)) * 100) as avg_progress,
        SUM(total_pages) as total_pages_in_range
    FROM student_parts_stats
    WHERE total_parts > 0
    GROUP BY FLOOR(total_parts)
    ORDER BY part_number
")->fetchAll();

// أفضل 10 طلاب حسب الأجزاء
$top_students = $pdo->query("
    SELECT s.id, s.name, s.category, sps.total_parts, sps.total_pages, 
           sps.full_parts, sps.current_part_progress, sps.description
    FROM students s
    JOIN student_parts_stats sps ON s.id = sps.student_id
    ORDER BY sps.total_parts DESC, sps.total_pages DESC
    LIMIT 10
")->fetchAll();

// الطلاب الذين هم في منتصف جزء (تقدم بين 1% و 99%)
$in_progress_students = $pdo->query("
    SELECT s.id, s.name, s.category, sps.total_parts, sps.total_pages,
           sps.current_part_number, sps.current_part_progress
    FROM students s
    JOIN student_parts_stats sps ON s.id = sps.student_id
    WHERE sps.current_part_progress > 0 AND sps.current_part_progress < 100
    ORDER BY sps.current_part_progress DESC
    LIMIT 20
")->fetchAll();
?>

<style>
.parts-advanced {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e3c3f;
}

.stat-label {
    color: #666;
    font-size: 0.85rem;
}

.parts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.part-card {
    background: white;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.part-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.part-number {
    font-size: 1.3rem;
    font-weight: 800;
    color: #1e3c3f;
}

.part-count {
    font-size: 0.75rem;
    color: #666;
}

.part-progress {
    font-size: 0.7rem;
    color: #f39c12;
    margin-top: 5px;
}

.progress-bar-small {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}

.progress-fill-small {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 2px;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #c9a96b;
}

.students-table {
    background: white;
    border-radius: 15px;
    overflow-x: auto;
    padding: 15px;
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
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-completed { background: #d4edda; color: #155724; }
.badge-progress { background: #fff3cd; color: #856404; }
.badge-start { background: #e9ecef; color: #6c757d; }

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .parts-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<section class="parts-advanced">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> إحصائيات الأجزاء المتقدمة</h1>
        <p>حساب دقيق للأجزاء المحفوظة (كل 20 صفحة = جزء واحد)</p>
    </div>

    <!-- إحصائيات عامة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_students; ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #28a745;"><?php echo $students_with_memorization; ?></div>
            <div class="stat-label">لديهم حفظ</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #f39c12;"><?php echo number_format($avg_parts, 2); ?></div>
            <div class="stat-label">متوسط الأجزاء</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #17a2b8;"><?php echo number_format($total_pages); ?></div>
            <div class="stat-label">إجمالي الصفحات</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #dc3545;"><?php echo $total_students - $students_with_memorization; ?></div>
            <div class="stat-label">لم يبدأوا بعد</div>
        </div>
    </div>

    <!-- توزيع الأجزاء -->
    <div class="section-title">
        <i class="fas fa-chart-bar"></i>
        <h3>توزيع الطلاب حسب الأجزاء المحفوظة</h3>
    </div>
    
    <div class="parts-grid">
        <?php 
        $distribution = array_fill(0, 31, ['count' => 0, 'progress' => 0]);
        foreach ($precise_distribution as $p) {
            $distribution[$p['part_number']] = [
                'count' => $p['count'],
                'progress' => round($p['avg_progress'], 1)
            ];
        }
        
        for ($i = 1; $i <= 30; $i++): 
            $count = $distribution[$i]['count'];
            $progress = $distribution[$i]['progress'];
            $percentage = $total_students > 0 ? round(($count / $total_students) * 100) : 0;
        ?>
            <div class="part-card">
                <div class="part-number">الجزء <?php echo $i; ?></div>
                <div class="part-count"><?php echo $count; ?> طالب</div>
                <?php if ($progress > 0 && $progress < 100): ?>
                    <div class="part-progress">متوسط التقدم: <?php echo $progress; ?>%</div>
                <?php endif; ?>
                <div class="progress-bar-small">
                    <div class="progress-fill-small" style="width: <?php echo $percentage; ?>%;"></div>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- أفضل 10 طلاب -->
    <div class="section-title">
        <i class="fas fa-crown" style="color: gold;"></i>
        <h3>أفضل 10 طلاب حفظاً</h3>
    </div>
    
    <div class="students-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>الفئة</th>
                    <th>الأجزاء</th>
                    <th>الصفحات</th>
                    <th>التفاصيل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_students as $index => $student): ?>
                    <tr>
                        <td><strong><?php echo $index + 1; ?></strong></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td>
                            <?php 
                            $cat_name = match($student['category']) {
                                'boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء', default => $student['category']
                            };
                            echo $cat_name;
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($student['total_parts'] >= 30) {
                                echo '<span class="badge badge-completed">🎓 30 جزء (ختم)</span>';
                            } else {
                                echo number_format($student['total_parts'], 2) . ' جزء';
                            }
                            ?>
                        </td>
                        <td><?php echo number_format($student['total_pages']); ?> / 604</td>
                        <td style="font-size: 0.85rem; color: #666;"><?php echo $student['description']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- الطلاب في منتصف الأجزاء -->
    <?php if (!empty($in_progress_students)): ?>
    <div class="section-title">
        <i class="fas fa-hourglass-half"></i>
        <h3>الطلاب الذين هم في منتصف جزء</h3>
    </div>
    
    <div class="students-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطالب</th>
                    <th>الفئة</th>
                    <th>الجزء الحالي</th>
                    <th>نسبة الإنجاز</th>
                    <th>الصفحات المحفوظة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($in_progress_students as $index => $student): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td>
                            <?php 
                            $cat_name = match($student['category']) {
                                'boy' => 'أولاد', 'girl' => 'بنات', 'child' => 'أطفال', 'woman' => 'نساء', default => $student['category']
                            };
                            echo $cat_name;
                            ?>
                        </td>
                        <td>الجزء <?php echo $student['current_part_number']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="color: #f39c12;"><?php echo $student['current_part_progress']; ?>%</span>
                                <div class="progress-bar-small" style="flex: 1;">
                                    <div class="progress-fill-small" style="width: <?php echo $student['current_part_progress']; ?>%;"></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo number_format($student['total_pages']); ?> صفحة</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- معلومات عن طريقة الحساب -->
    <div style="margin-top: 30px; background: #e7f3ff; border-radius: 15px; padding: 20px;">
        <h4 style="color: #0c5460; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-info-circle"></i> طريقة حساب الأجزاء بدقة
        </h4>
        <ul style="margin-top: 10px; margin-right: 25px; color: #0c5460;">
            <li><strong>📖 1 جزء = 20 صفحة</strong> من المصحف الشريف</li>
            <li><strong>📊 القرآن الكريم كاملاً = 604 صفحة = 30.2 جزء</strong> (نعتبر 30 جزء كاملاً)</li>
            <li><strong>📈 يتم حساب الأجزاء بدقة</strong> بناءً على الصفحات الفريدة المحفوظة</li>
            <li><strong>🎯 الطالب الذي حفظ 35 صفحة = 1.75 جزء</strong> (أكمل جزء كامل + 75% من الجزء الثاني)</li>
            <li><strong>✅ لا يعتبر الطالب أنه أتم جزءاً</strong> إلا إذا حفظ الـ 20 صفحة كاملة منه</li>
        </ul>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>