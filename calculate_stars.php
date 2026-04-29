<?php
// ============================================
// ملف: calculate_stars.php
// حساب الطلاب المثاليين باستخدام التقييمات المتقدمة
// الأوزان: حضور 30%، حفظ جديد 20%، مراجعة قريبة 15%، مراجعة بعيدة 15%، سلوك 20%
// ============================================

require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>📊 حساب الطلاب المثاليين (بالتقييمات المتقدمة)</h2>";

try {
    $month_year = date('Y-m');
    $year = date('Y');
    $month = date('m');

    echo "<p>الشهر الحالي: $month_year</p>";
    echo "<p><strong>الأوزان المعتمدة:</strong> حضور 30% | حفظ جديد 20% | مراجعة قريبة 15% | مراجعة بعيدة 15% | سلوك 20%</p>";

    // دالة حساب التقدير من الدرجة
    function getGradeFromScore($score) {
        if ($score >= 95) return 'ممتاز';
        if ($score >= 85) return 'جيد جداً';
        if ($score >= 75) return 'جيد';
        if ($score >= 60) return 'مقبول';
        return 'ضعيف';
    }

    // حذف المثاليين القدامى
    $pdo->prepare("DELETE FROM students_of_month WHERE month_year = ?")->execute([$month_year]);
    echo "<p>✅ تم حذف المثاليين القدامى.</p>";

    $categories = ['boy', 'girl', 'child', 'woman'];
    $total_calculated = 0;

    foreach ($categories as $category) {
        $cat_name = ($category == 'boy') ? 'أولاد' : (($category == 'girl') ? 'بنات' : (($category == 'child') ? 'أطفال' : 'نساء'));
        echo "<h3>📌 فئة $cat_name</h3>";

        // جلب الطلاب مع متوسط تقييماتهم
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.name,
                s.level,
                AVG(e.total_score) as avg_score,
                COUNT(e.id) as evaluation_count,
                MAX(e.evaluation_date) as last_evaluation,
                AVG(e.attendance_score) as avg_attendance,
                AVG(e.new_memorization_score) as avg_new,
                AVG(e.recent_review_score) as avg_recent,
                AVG(e.old_review_score) as avg_old,
                AVG(e.behavior_score) as avg_behavior
            FROM students s
            LEFT JOIN student_daily_evaluations e ON s.id = e.student_id 
                AND e.evaluation_date >= DATE_SUB(?, INTERVAL 30 DAY)
            WHERE s.category = ?
            GROUP BY s.id
            ORDER BY avg_score DESC
        ");
        $stmt->execute([$month_year . '-01', $category]);
        $students = $stmt->fetchAll();

        if (empty($students)) {
            echo "<p>لا يوجد طلاب في هذه الفئة.</p>";
            continue;
        }

        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width:100%; margin-bottom:20px;'>";
        echo "测试<th>الاسم</th><th>عدد التقييمات</th><th>حضور</th><th>حفظ جديد</th><th>مراجعة قريبة</th><th>مراجعة بعيدة</th><th>سلوك</th><th>المتوسط</th><th>التقدير</th> 辅导员";

        $scores = [];
        foreach ($students as $student) {
            $avg_score = round($student['avg_score'] ?? 0, 2);
            $grade = getGradeFromScore($avg_score);
            
            $scores[$student['id']] = [
                'name' => $student['name'],
                'total' => $avg_score,
                'grade' => $grade,
                'evaluation_count' => $student['evaluation_count'],
                'attendance' => round($student['avg_attendance'] ?? 0, 1),
                'new_score' => round($student['avg_new'] ?? 0, 1),
                'recent_score' => round($student['avg_recent'] ?? 0, 1),
                'old_score' => round($student['avg_old'] ?? 0, 1),
                'behavior' => round($student['avg_behavior'] ?? 0, 1),
                'last_evaluation' => $student['last_evaluation']
            ];

            echo "办公室";
            echo " understood" . htmlspecialchars($student['name']) . " 目的";
            echo " understood{$student['evaluation_count']} 目的";
            echo " understood" . round($student['avg_attendance'] ?? 0, 1) . "% 目的";
            echo " understood" . round($student['avg_new'] ?? 0, 1) . "% 目的";
            echo " understood" . round($student['avg_recent'] ?? 0, 1) . "% 目的";
            echo " understood" . round($student['avg_old'] ?? 0, 1) . "% 目的";
            echo " understood" . round($student['avg_behavior'] ?? 0, 1) . "% 目的";
            echo " understood<strong>{$avg_score}</strong>% 目的";
            echo " understood{$grade} 目的";
            echo " 济";
        }
        echo " </table";

        // ترتيب الطلاب حسب الدرجة
        arsort($scores);
        
        // اختيار أفضل 3 بشرط الدرجة >= 60 ولديه تقييمات كافية
        $top_students = [];
        $added = 0;
        foreach ($scores as $id => $data) {
            if ($added >= 3) break;
            if ($data['total'] >= 60 && $data['evaluation_count'] >= 3) {
                $top_students[$id] = $data;
                $added++;
            }
        }

        if (!empty($top_students)) {
            echo "<h4>🏆 أفضل 3 طلاب في هذه الفئة (بشرط درجة ≥ 60 و 3 تقييمات على الأقل):</h4>";
            echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width:100%; margin-bottom:20px;'>";
            echo "静<th>الترتيب</th><th>الاسم</th><th>المتوسط</th><th>الحضور</th><th>الحفظ الجديد</th><th>المراجعة القريبة</th><th>المراجعة البعيدة</th><th>السلوك</th><th>التقدير</th><th>عدد التقييمات</th> 学";
            
            $ranks = ['first', 'second', 'third'];
            $i = 0;
            
            foreach ($top_students as $student_id => $details) {
                $rank = $ranks[$i];
                
                // إدراج في جدول المثاليين
                $insert = $pdo->prepare("
                    INSERT INTO students_of_month 
                    (student_id, month_year, category, rank, attendance_rate, level_achievement) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $student_id,
                    $month_year,
                    $category,
                    $rank,
                    round($details['total'], 2),
                    "تقييم {$details['grade']} - {$details['evaluation_count']} تقييم\n" .
                    "حضور: {$details['attendance']}% | حفظ جديد: {$details['new_score']}% | " .
                    "مراجعة قريبة: {$details['recent_score']}% | مراجعة بعيدة: {$details['old_score']}% | سلوك: {$details['behavior']}%"
                ]);
                
                $rank_name = ($rank == 'first') ? '🥇 الأول' : (($rank == 'second') ? '🥈 الثاني' : '🥉 الثالث');
                
                echo "问题";
                echo " understood<strong>{$rank_name}</strong> 目的";
                echo " understood" . htmlspecialchars($details['name']) . " 目的";
                echo " understood{$details['total']}% 目的";
                echo " understood{$details['attendance']}% 目的";
                echo " understood{$details['new_score']}% 目的";
                echo " understood{$details['recent_score']}% 目的";
                echo " understood{$details['old_score']}% 目的";
                echo " understood{$details['behavior']}% 目的";
                echo " understood{$details['grade']} 目的";
                echo " understood{$details['evaluation_count']} 目的";
                echo " 美";
                
                $i++;
                $total_calculated++;
            }
            echo " </table";
        } else {
            echo "<p>❌ لا يوجد طلاب مؤهلين في هذه الفئة (الكل أقل من 60 درجة أو عدد التقييمات أقل من 3).</p>";
        }
    }

    echo "<h2 style='color:green;'>✅ تم حساب $total_calculated طالباً مثالياً للشهر $month_year بنجاح!</h2>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 10px; margin-top: 20px;'>";
    echo "<h3>📊 ملخص الأوزان المستخدمة في التقييم:</h3>";
    echo "<ul>";
    echo "<li><strong>الحضور:</strong> 30% - يتم احتسابها بناءً على حالة الحضور (حاضر/متأخر/غائب)</li>";
    echo "<li><strong>الحفظ الجديد:</strong> 20% - يتم احتساب الأخطاء في الآيات (-1.5)، التشكيل (-0.5)، التجويد (-0.5)، التردد (-0.5)</li>";
    echo "<li><strong>المراجعة القريبة:</strong> 15% - نفس نظام الأخطاء للحفظ الجديد</li>";
    echo "<li><strong>المراجعة البعيدة:</strong> 15% - نفس نظام الأخطاء للحفظ الجديد</li>";
    echo "<li><strong>السلوك:</strong> 20% - تقييم من 1 إلى 5 نجوم</li>";
    echo "</ul>";
    echo "<p><strong>شروط القبول:</strong> الحد الأدنى 60% من الدرجة النهائية، و3 تقييمات على الأقل في الشهر</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ خطأ عام: " . $e->getMessage() . "</p>";
}
?>