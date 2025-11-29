<?php

require_once 'db-functions.php';

$conn = getDbConnection();

$student_id = intval($_GET['student_id'] ?? 0);
$term = intval($_GET['term'] ?? 1);
$marks_type = $_GET['type'] ?? 'midterm';

if (!$student_id) {
    die("Error: Student ID is required. Please access this page from the marks report.");
}

// Get complete student data with marks
$stmt = $conn->prepare("
    SELECT s.id, s.first_name, s.last_name, s.student_number, s.class, s.birth_year,
           COALESCE(m.math, 0) as math, COALESCE(m.srs, 0) as srs, 
           COALESCE(m.eng, 0) as eng, COALESCE(m.set_subject, 0) as set_subject, 
           COALESCE(m.kiny, 0) as kiny, COALESCE(m.art, 0) as art, 
           COALESCE(m.franc, 0) as franc, COALESCE(m.pes, 0) as pes, 
           COALESCE(m.religion, 0) as religion, COALESCE(m.kisw, 0) as kisw,
           COALESCE(m.total, 0) as total, COALESCE(m.average, 0) as average,
           COALESCE(m.grade, '-') as grade, COALESCE(m.rank, 0) as rank, 
           COALESCE(m.teacher_comment, '') as teacher_comment
    FROM students s
    LEFT JOIN marks m ON s.id = m.student_id AND m.term = ? AND m.marks_type = ?
    WHERE s.id = ?
");

$stmt->bind_param("iss", $term, $marks_type, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Error: Student not found. Please check the student ID.");
}

// Get max marks for class
$max_stmt = $conn->prepare("SELECT * FROM class_max_marks WHERE class = ?");
$max_stmt->bind_param("s", $student['class']);
$max_stmt->execute();
$max_result = $max_stmt->get_result();
$max_marks = $max_result->fetch_assoc() ?: [];

$subjects_data = [
    'math' => 'Mathematics',
    'eng' => 'English',
    'kiny' => 'Kinyarwanda',
    'set_subject' => 'Science & Technology',
    'srs' => 'Social Studies',
    'kisw' => 'Kiswahili',
    'franc' => 'French',
    'art' => 'Creative Arts',
    'pes' => 'Physical Education',
    'religion' => 'Religion'
];

$has_marks = $student['average'] > 0;
$active_subjects = [];
$total_maximum_marks = 0;

foreach ($subjects_data as $key => $display_name) {
    if (isset($max_marks[$key]) && $max_marks[$key] > 0) {
        $active_subjects[$key] = $display_name;
        $total_maximum_marks += $max_marks[$key];
    }
}

$marks = [
    'total' => (float)$student['total'],
    'average' => (float)$student['average'],
    'grade' => $student['grade'],
    'teacher_comment' => $student['teacher_comment']
];

foreach ($subjects_data as $key => $display_name) {
    $marks[$key] = (float)$student[$key];
}

// Get student rank and total students in class
$rank_stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE TRIM(class) = ?");
$rank_stmt->bind_param("s", $student['class']);
$rank_stmt->execute();
$rank_result = $rank_stmt->get_result();
$rank_data = $rank_result->fetch_assoc();
$total_students_in_class = $rank_data['count'] ?? 0;

$student_rank = $student['rank'] ?? '-';

$performance_color = '#1a4b8c';
$performance_message = 'Keep up the good work!';

if ($marks['average'] >= 80) {
    $performance_color = '#28a745';
    $performance_message = 'Excellent performance! You are excelling in your studies.';
} elseif ($marks['average'] >= 60) {
    $performance_color = '#20c997';
    $performance_message = 'Good performance. Continue with your hard work.';
} elseif ($marks['average'] >= 40) {
    $performance_color = '#ffc107';
    $performance_message = 'Average performance. More effort is needed to improve.';
} else {
    $performance_color = '#dc3545';
    $performance_message = 'Below average. Please focus more on your studies.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="logo.jpg" type="image/x-icon">
<title>Report Card - <?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e6f2ff 100%);
    padding: 20px;
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.report-card {
    border: 3px solid #1a4b8c;
    padding: 30px;
    margin-bottom: 20px;
    border-radius: 8px;
    background: linear-gradient(to bottom, #ffffff 0%, #f9fbfd 100%);
    page-break-inside: avoid;
    position: relative;
}

.rwanda-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 3px solid #1a4b8c;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.desktop-logo {
    max-width: 60px;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.school-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a4b8c;
    letter-spacing: 0.5px;
}

.header-center {
    text-align: center;
    flex: 1;
}

.report-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a4b8c;
    margin-bottom: 8px;
}

.student-name-header {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.rank-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    color: white;
    font-weight: 700;
    font-size: 12px;
    margin-left: 10px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.rank-badge.gold { background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); color: #333; }
.rank-badge.silver { background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%); color: #333; }
.rank-badge.bronze { background: linear-gradient(135deg, #cd7f32 0%, #e8a652 100%); }
.rank-badge.other { background: linear-gradient(135deg, #1a4b8c 0%, #2c5aa0 100%); }

.student-info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    overflow: hidden;
}

.student-info-table th {
    background: #e6f2ff;
    color: #1a4b8c;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    padding: 12px;
    border-bottom: 2px solid #1a4b8c;
}

.student-info-table td {
    text-align: left;
    font-size: 13px;
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.student-info-table tr:last-child td {
    border-bottom: none;
}

.subject-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    overflow: hidden;
    font-size: 12px;
}

.subject-table th {
    background: linear-gradient(135deg, #1a4b8c 0%, #2c5aa0 100%);
    color: white;
    padding: 12px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #0d3a6b;
}

.subject-table td {
    padding: 12px;
    border: 1px solid #e0e0e0;
    text-align: center;
}

.subject-table td:first-child {
    text-align: left;
    font-weight: 600;
    color: #1a4b8c;
}

.subject-table tbody tr:hover {
    background: #f5f9ff;
    transition: background 0.3s ease;
}

.subject-table tbody tr:nth-child(even) {
    background: #f9fbfd;
}

.subject-table tr.total-row {
    background: #e6f2ff;
    border-top: 3px solid #1a4b8c;
    font-weight: 700;
}

.grade-badge {
    padding: 6px 12px;
    border-radius: 20px;
    color: white;
    font-weight: 700;
    font-size: 11px;
    display: inline-block;
    min-width: 40px;
    text-align: center;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.grade-aplus, .grade-a { background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); }
.grade-bplus, .grade-b { background: linear-gradient(135deg, #20c997 0%, #2dd4bf 100%); }
.grade-cplus, .grade-c { background: linear-gradient(135deg, #ffc107 0%, #ffdb58 100%); color: #333; }
.grade-d { background: linear-gradient(135deg, #fd7e14 0%, #ff9838 100%); }
.grade-e { background: linear-gradient(135deg, #dc3545 0%, #e74c5c 100%); }
.grade-none { background: #6c757d; }

.final-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    overflow: hidden;
    background: linear-gradient(to bottom, #f0f8ff 0%, #ffffff 100%);
}

.final-summary-table th {
    background: linear-gradient(135deg, #1a4b8c 0%, #2c5aa0 100%);
    color: white;
    font-weight: 700;
    font-size: 12px;
    padding: 12px;
    text-align: center;
    border: 1px solid #0d3a6b;
}

.final-summary-table td {
    padding: 12px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #e0e0e0;
}

.performance-summary {
    margin: 20px 0;
    padding: 20px;
    border-left: 5px solid #1a4b8c;
    background: #f0f8ff;
    border-radius: 6px;
    border: 2px solid #e6f2ff;
}

.performance-summary h4 {
    margin: 0 0 10px 0;
    color: #1a4b8c;
    font-size: 14px;
    font-weight: 700;
}

.performance-summary p {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
}

.teacher-comments {
    margin: 20px 0;
    padding: 20px;
    background: linear-gradient(135deg, #fff9e6 0%, #fffaf0 100%);
    border-left: 5px solid #ff9800;
    border-radius: 6px;
    border: 2px solid #ffe0b2;
}

.teacher-comments h4 {
    margin: 0 0 10px 0;
    color: #ff6f00;
    font-size: 14px;
    font-weight: 700;
}

.teacher-comments p {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    font-style: italic;
    color: #555;
}

.signatures {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px dashed #ccc;
}

.signatures table {
    width: 100%;
    border-collapse: collapse;
}

.signatures td {
    text-align: center;
    padding: 20px 10px 0;
    border: none;
}

.signature-line {
    border-top: 2px solid #000;
    width: 150px;
    margin: 0 auto 5px;
}

.signature-label {
    font-size: 11px;
    color: #666;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 25px;
    justify-content: center;
}

button {
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-print {
    background: linear-gradient(135deg, #1a4b8c 0%, #2c5aa0 100%);
    color: white;
}

.btn-print:hover {
    background: linear-gradient(135deg, #0d3a6b 0%, #1a4b8c 100%);
}

.btn-back {
    background: linear-gradient(135deg, #6c757d 0%, #7a8288 100%);
    color: white;
}

.btn-back:hover {
    background: linear-gradient(135deg, #5a6268 0%, #6c757d 100%);
}

.btn-home {
    background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
    color: white;
}

.btn-home:hover {
    background: linear-gradient(135deg, #218838 0%, #28a745 100%);
}

.no-marks-warning {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 6px;
    margin: 20px 0;
    border: 2px solid #f5c6cb;
    font-size: 13px;
}

@media print {
    body {
        background: white;
        margin: 0;
        padding: 0;
    }
    .container {
        box-shadow: none;
        padding: 0;
        margin: 0;
        max-width: 100%;
        border: none;
    }
    .report-card {
        box-shadow: none;
        page-break-inside: avoid;
        margin: 0;
        padding: 20px;
    }
    .action-buttons {
        display: none;
    }
    * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

@media screen and (max-width: 768px) {
    .container {
        padding: 15px;
    }
    .report-card {
        padding: 15px;
    }
    .rwanda-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    .header-left {
        justify-content: center;
        width: 100%;
    }
    button {
        padding: 10px 15px;
        font-size: 12px;
        width: 100%;
        max-width: 200px;
    }
}
</style>
</head>
<body>
<div class="container">
<div class="report-card" data-has-marks="<?= $has_marks ? '1' : '0' ?>">
    
    <div class="rwanda-header">
        <div class="header-left">
            <img src="logo.jpg" alt="School Logo" class="desktop-logo">
            <div>
                <div class="school-title">MEJECRES SCHOOL</div>
                <div style="font-size: 11px; color: #666;">Kigali, Rwanda</div>
            </div>
        </div>
        
        <div class="header-center">
            <div class="report-title">Student Report Card</div>
            <div class="student-name-header">
                <?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?>
                <?php if($has_marks && ($student_rank != '-' && $student_rank > 0)): 
                    $rankClass = 'other';
                    if ($student_rank == 1) $rankClass = 'gold';
                    elseif ($student_rank == 2) $rankClass = 'silver';
                    elseif ($student_rank == 3) $rankClass = 'bronze';
                ?>
                <span class="rank-badge <?= $rankClass ?>">Rank <?= $student_rank ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <table class="student-info-table">
        <tr>
            <th>Admission No:</th>
            <td><?= htmlspecialchars($student['student_number']) ?></td>
            <th>Class:</th>
            <td><?= htmlspecialchars($student['class']) ?></td>
        </tr>
        <tr>
            <th>Student Name:</th>
            <td colspan="3"><?= htmlspecialchars(strtoupper($student['first_name'].' '.$student['last_name'])) ?></td>
        </tr>
    </table>

    <?php if($has_marks): ?>
    
    <table class="subject-table">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Max Marks</th>
                <th>Obtained</th>
                <th>Percentage</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($active_subjects as $subject => $display_name): ?>
            <?php 
            $marks_obtained = $marks[$subject] ?? 0;
            $max_mark = $max_marks[$subject] ?? 0;
            $percentage = $max_mark > 0 ? round(($marks_obtained / $max_mark) * 100, 1) : 0;
            $grade = getGrade($percentage);
            $grade_class = 'grade-' . strtolower(str_replace('+', 'plus', $grade));
            $remark = '';
            if($grade == 'A+' || $grade == 'A') $remark = 'Excellent';
            elseif($grade == 'B+' || $grade == 'B') $remark = 'Very Good';
            elseif($grade == 'C+' || $grade == 'C') $remark = 'Good';
            elseif($grade == 'D') $remark = 'Fair';
            elseif($grade == 'E') $remark = 'Needs Improvement';
            ?>
            <tr>
                <td><?= htmlspecialchars($display_name) ?></td>
                <td><?= htmlspecialchars($max_mark) ?></td>
                <td><?= htmlspecialchars($marks_obtained) ?></td>
                <td><?= $percentage ?>%</td>
                <td><span class="grade-badge <?= $grade_class ?>"><?= htmlspecialchars($grade) ?></span></td>
                <td><?= htmlspecialchars($remark) ?></td>
            </tr>
        <?php endforeach; ?>
        
        <tr class="total-row">
            <td>TOTAL</td>
            <td><?= htmlspecialchars($total_maximum_marks) ?></td>
            <td><?= number_format($marks['total'], 1) ?></td>
            <td><?= number_format($marks['average'], 1) ?>%</td>
            <td><span class="grade-badge <?= 'grade-' . strtolower(str_replace('+', 'plus', $marks['grade'])) ?>"><?= htmlspecialchars($marks['grade']) ?></span></td>
            <td>OVERALL</td>
        </tr>
        </tbody>
    </table>

    <table class="final-summary-table">
        <thead>
            <tr>
                <th>Total Obtained</th>
                <th>Total Maximum</th>
                <th>Average %</th>
                <th>Grade</th>
                <th>Class Rank</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= number_format($marks['total'], 1) ?></td>
                <td><?= htmlspecialchars($total_maximum_marks) ?></td>
                <td><?= number_format($marks['average'], 1) ?>%</td>
                <td><span class="grade-badge <?= 'grade-' . strtolower(str_replace('+', 'plus', $marks['grade'])) ?>"><?= htmlspecialchars($marks['grade']) ?></span></td>
                <td><?php if($student_rank && $student_rank != '-' && $student_rank > 0): ?>
                    <?= htmlspecialchars($student_rank) ?> / <?= htmlspecialchars($total_students_in_class) ?>
                <?php else: ?>
                    -
                <?php endif; ?></td>
            </tr>
        </tbody>
    </table>

    <div class="performance-summary">
        <h4>Performance Summary</h4>
        <p style="color: <?= $performance_color ?>;"><?= htmlspecialchars($performance_message) ?></p>
    </div>

    <div class="teacher-comments">
        <h4>Teacher's Comments</h4>
        <p><?= !empty($marks['teacher_comment']) ? htmlspecialchars($marks['teacher_comment']) : 'No comments provided.' ?></p>
    </div>

    <div class="signatures">
        <table>
            <tr>
                <td style="width: 33%;">
                    <div class="signature-line"></div>
                    <div class="signature-label">Class Teacher</div>
                </td>
                <td style="width: 33%;">
                    <div class="signature-line"></div>
                    <div class="signature-label">Head Teacher</div>
                </td>
                <td style="width: 34%;">
                    <div class="signature-label">Date: <?= date('d/m/Y') ?></div>
                </td>
            </tr>
        </table>
    </div>

    <?php else: ?>
    <div class="no-marks-warning">
        <strong>No Marks Entered</strong>
        <p style="margin: 5px 0 0 0;">Please enter marks in the system before viewing this report.</p>
    </div>
    <?php endif; ?>

    <div class="action-buttons">
        <button class="btn-print" onclick="window.print()">Print Report</button>
        <button class="btn-back" onclick="window.location='report.php'">Back to Reports</button>
        <button class="btn-home" onclick="window.location='teachers.php'">Home</button>
    </div>
</div>
</div>

<script>
</script>
</body>
</html>
