<?php
// ============================================
// : check_student_absences.php
//       
//    
//  : 2026-04-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

// ============================================
//       (  )
// ============================================
function getStudentConsecutiveAbsences($pdo, $student_id) {
    //      
    $leave_period = null;
    $stmt = $pdo->prepare("
        SELECT leave_start_date, leave_end_date 
        FROM students 
        WHERE id = ? AND on_leave = 1
    ");
    $stmt->execute([$student_id]);
    $leave = $stmt->fetch();
    
    if ($leave) {
        $leave_period = [
            'start' => $leave['leave_start_date'],
            'end' => $leave['leave_end_date']
        ];
    }
    
    //      30 
    $stmt = $pdo->prepare("
        SELECT date, status, is_excused 
        FROM attendance 
        WHERE person_type = 'student' 
        AND person_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY date DESC
    ");
    $stmt->execute([$student_id]);
    $records = $stmt->fetchAll();
    
    $consecutive_absences = 0;
    $last_absence_date = null;
    $current_streak = 0;
    $total_absences = 0;
    
    foreach ($records as $record) {
        $date = $record['date'];
        
        //    (     )
        if ($leave_period && $date >= $leave_period['start'] && $date <= $leave_period['end']) {
            continue; //    
        }
        
        //    
        if (isHoliday($pdo, $date, 'students')) {
            continue; //    
        }
        
        //        
        if (!isRingDayForStudent($pdo, $student_id, $date)) {
            continue; //      
        }
        
        //       
        $is_unexcused_absence = ($record['status'] == 'absent' && !$record['is_excused']);
        
        if ($is_unexcused_absence) {
            $total_absences++;
            
            if ($current_streak == 0) {
                $current_streak = 1;
                $last_absence_date = $date;
            } else {
                //    
                $last_date = new DateTime($last_absence_date);
                $current_date = new DateTime($date);
                $diff = $last_date->diff($current_date)->days;
                
                //       ()
                if ($diff <= 1) {
                    $current_streak++;
                    $last_absence_date = $date;
                } else {
                    //           
                    //      
                    break;
                }
            }
        } else {
            //        
            if ($record['status'] == 'present' || $record['status'] == 'late') {
                break;
            }
        }
    }
    
    $consecutive_absences = $current_streak;
    
    //     ( )
    $last_attendance_stmt = $pdo->prepare("
        SELECT date FROM attendance 
        WHERE person_type = 'student' AND person_id = ? 
        AND (status = 'present' OR status = 'late')
        AND date NOT BETWEEN ? AND ?
        ORDER BY date DESC LIMIT 1
    ");
    $last_attendance_stmt->execute([$student_id, $leave_period['start'] ?? '1900-01-01', $leave_period['end'] ?? '1900-01-01']);
    $last_attendance = $last_attendance_stmt->fetchColumn();
    
    return [
        'consecutive_absences' => $consecutive_absences,
        'total_absences' => $total_absences,
        'last_absence_date' => $last_absence_date,
        'last_attendance_date' => $last_attendance,
        'is_disconnected' => ($consecutive_absences >= 2),
        'on_leave' => ($leave_period !== null),
        'leave_start' => $leave_period['start'] ?? null,
        'leave_end' => $leave_period['end'] ?? null
    ];
}

// ============================================
//     (     )
// ============================================
function getDisconnectedStudents($pdo, $teacher_id = null) {
    $sql = "
        SELECT 
            s.id,
            s.name,
            s.parent_phone,
            s.level,
            s.category,
            s.on_leave,
            s.leave_start_date,
            s.leave_end_date,
            t.name as teacher_name,
            t.id as teacher_id
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($teacher_id) {
        $sql .= " AND s.teacher_id = :teacher_id";
        $params[':teacher_id'] = $teacher_id;
    }
    
    $sql .= " ORDER BY s.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    $disconnected = [];
    
    foreach ($students as $student) {
        //           
        if ($student['on_leave'] == 1) {
            continue;
        }
        
        $absences = getStudentConsecutiveAbsences($pdo, $student['id']);
        
        //  :     
        if ($absences['consecutive_absences'] >= 2) {
            $student['consecutive_absences'] = $absences['consecutive_absences'];
            $student['total_absences'] = $absences['total_absences'];
            $student['last_absence_date'] = $absences['last_absence_date'];
            $student['last_attendance_date'] = $absences['last_attendance_date'];
            
            //   
            if ($student['consecutive_absences'] >= 6) {
                $student['status_class'] = 'critical';
                $student['status_text'] = '  ';
                $student['status_icon'] = 'fa-skull-crossbones';
            } elseif ($student['consecutive_absences'] >= 4) {
                $student['status_class'] = 'warning';
                $student['status_text'] = ' ';
                $student['status_icon'] = 'fa-exclamation-triangle';
            } else {
                $student['status_class'] = 'notice';
                $student['status_text'] = ' ';
                $student['status_icon'] = 'fa-bell';
            }
            
            $disconnected[] = $student;
        }
    }
    
    //     
    usort($disconnected, function($a, $b) {
        return $b['consecutive_absences'] - $a['consecutive_absences'];
    });
    
    return $disconnected;
}

// ============================================
//        
// ============================================
function markStudentAttendanceAndResetStreak($pdo, $student_id, $status = 'present', $notes = '') {
    $today = date('Y-m-d');
    $day_of_week = date('w', strtotime($today)) + 1;
    
    //       
    $check_leave = $pdo->prepare("SELECT on_leave FROM students WHERE id = ?");
    $check_leave->execute([$student_id]);
    $on_leave = $check_leave->fetchColumn();
    
    if ($on_leave) {
        return false;
    }
    
    //        
    if (!isRingDayForStudent($pdo, $student_id, $today)) {
        return false;
    }
    
    //      
    $check = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
    $check->execute([$student_id, $today]);
    
    if ($check->fetch()) {
        //   
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET status = ?, notes = ?, is_excused = 0
            WHERE person_type='student' AND person_id=? AND date=?
        ");
        $stmt->execute([$status, $notes, $student_id, $today]);
    } else {
        //   
        $stmt = $pdo->prepare("
            INSERT INTO attendance (person_type, person_id, date, status, notes) 
            VALUES ('student', ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $today, $status, $notes]);
    }
    
    return true;
}

// ============================================
//         
// ============================================
function recalculateAbsencesAfterLeave($pdo, $student_id) {
    //   
    $stmt = $pdo->prepare("
        SELECT leave_start_date, leave_end_date 
        FROM students 
        WHERE id = ? AND on_leave = 1
    ");
    $stmt->execute([$student_id]);
    $leave = $stmt->fetch();
    
    if (!$leave) {
        return false;
    }
    
    //          
    $delete = $pdo->prepare("
        DELETE FROM attendance 
        WHERE person_type = 'student' 
        AND person_id = ? 
        AND date BETWEEN ? AND ?
        AND auto_generated = 1
    ");
    $delete->execute([$student_id, $leave['leave_start_date'], $leave['leave_end_date']]);
    
    return true;
}

// ============================================
//      ()
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'check_student_absences.php' && isset($_GET['debug'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>    </h2>";
    
    $students = getDisconnectedStudents($pdo);
    
    echo "<h3>  : " . count($students) . "</h3>";
    foreach ($students as $student) {
        echo "<p><strong>{$student['name']}</strong> -  : {$student['consecutive_absences']}  - {$student['status_text']}</p>";
    }
    
    exit;
}

//      (AJAX)
if (basename($_SERVER['PHP_SELF']) == 'check_student_absences.php' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
    $students = getDisconnectedStudents($pdo, $teacher_id);
    
    echo json_encode([
        'success' => true,
        'count' => count($students),
        'students' => $students,
        'teacher_id' => $teacher_id,
        'query_date' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>