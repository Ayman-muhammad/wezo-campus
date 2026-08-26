<?php
/**
 * WEZO CAMPUS HUB - Export Timetable
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Helpers.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Helpers;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

// Get export format
$format = $_GET['format'] ?? 'pdf';
$week = intval($_GET['week'] ?? date('W'));
$year = intval($_GET['year'] ?? date('Y'));

// Calculate week dates
$weekStart = new DateTime();
$weekStart->setISODate($year, $week);
$weekStart->setTime(0, 0, 0);

$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');
$weekEnd->setTime(23, 59, 59);

// Get timetable data
$timetable = $db->fetchAll("
    SELECT tt.*, 
           c.code as course_code, c.name as course_name,
           u.first_name, u.last_name,
           r.name as room_name, r.building
    FROM timetable_entries tt
    LEFT JOIN courses c ON tt.course_id = c.id
    LEFT JOIN users u ON tt.lecturer_id = u.id
    LEFT JOIN rooms r ON tt.room_id = r.id
    WHERE tt.user_id = ? 
    AND tt.status = 'active'
    AND tt.start_time >= ? AND tt.start_time <= ?
    ORDER BY tt.day_of_week, tt.start_time
", [$user['id'], $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);

// Get user info
$userInfo = $db->fetch("
    SELECT u.*, c.name as campus_name
    FROM users u
    LEFT JOIN campuses c ON u.campus_id = c.id
    WHERE u.id = ?
", [$user['id']]);

// Get assignments
$assignments = $db->fetchAll("
    SELECT a.*, c.code as course_code
    FROM assignments a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.user_id = ? 
    AND a.due_date >= ? AND a.due_date <= ?
    ORDER BY a.due_date ASC
", [$user['id'], $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);

// Group timetable by day
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$timetableByDay = [];

foreach ($days as $dayIndex => $dayName) {
    $timetableByDay[$dayName] = array_filter($timetable, function($entry) use ($dayIndex) {
        return $entry['day_of_week'] == $dayIndex;
    });
}

// Handle different export formats
switch ($format) {
    case 'pdf':
        exportPDF();
        break;
    case 'excel':
        exportExcel();
        break;
    case 'csv':
        exportCSV();
        break;
    case 'ical':
        exportICal();
        break;
    case 'image':
        exportImage();
        break;
    default:
        Session::setFlash('error', 'Invalid export format');
        header('Location: index.php');
        exit;
}

function exportPDF() {
    global $userInfo, $timetableByDay, $week, $year, $weekStart, $weekEnd, $assignments;
    
    // Create PDF using TCPDF or similar
    // For now, we'll output HTML that can be printed as PDF
    
    header('Content-Type: text/html; charset=utf-8');
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Timetable Export - Week <?= $week ?>, <?= $year ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #333; margin-bottom: 5px; }
            .header p { color: #666; }
            .student-info { margin-bottom: 20px; }
            .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .info-item { background: #f5f5f5; padding: 10px; border-radius: 5px; }
            .timetable { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .timetable th { background: #007bff; color: white; padding: 12px; text-align: left; }
            .timetable td { padding: 10px; border: 1px solid #ddd; }
            .timetable tr:nth-child(even) { background: #f9f9f9; }
            .course-block { margin: 5px 0; padding: 8px; border-radius: 4px; color: white; }
            .assignments { margin-top: 30px; }
            .assignment-item { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 5px 0; border-radius: 4px; }
            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 0.9em; }
            @media print {
                .no-print { display: none; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Print Timetable
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                Close
            </button>
        </div>
        
        <div class="header">
            <h1>Academic Timetable</h1>
            <p>Week <?= $week ?> (<?= $weekStart->format('M j') ?> - <?= $weekEnd->format('M j, Y') ?>)</p>
        </div>
        
        <div class="student-info">
            <div class="info-grid">
                <div class="info-item">
                    <strong>Student:</strong><br>
                    <?= htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']) ?>
                </div>
                <div class="info-item">
                    <strong>Student ID:</strong><br>
                    <?= htmlspecialchars($userInfo['student_id'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <strong>Campus:</strong><br>
                    <?= htmlspecialchars($userInfo['campus_name'] ?? 'N/A') ?>
                </div>
            </div>
        </div>
        
        <table class="timetable">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Course</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Lecturer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timetableByDay as $dayName => $entries): ?>
                    <?php if (empty($entries)) continue; ?>
                    <?php foreach ($entries as $index => $entry): ?>
                        <tr>
                            <?php if ($index === 0): ?>
                                <td rowspan="<?= count($entries) ?>" style="vertical-align: top; font-weight: bold;">
                                    <?= $dayName ?><br>
                                    <small><?= date('M j', strtotime($weekStart->format('Y-m-d') . " +" . (array_search($dayName, $days) ?: 0) . " days")) ?></small>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?= date('g:i A', strtotime($entry['start_time'])) ?> -<br>
                                <?= date('g:i A', strtotime($entry['end_time'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($entry['course_code']) ?></strong><br>
                                <small><?= htmlspecialchars($entry['course_name']) ?></small>
                            </td>
                            <td><?= ucfirst($entry['entry_type']) ?></td>
                            <td>
                                <?php if ($entry['room_name']): ?>
                                    <?= htmlspecialchars($entry['room_name']) ?>
                                    <?php if ($entry['building']): ?>
                                        <br><small><?= htmlspecialchars($entry['building']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($entry['first_name']): ?>
                                    <?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (!empty($assignments)): ?>
            <div class="assignments">
                <h3>Upcoming Assignments</h3>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-item">
                        <strong><?= htmlspecialchars($assignment['title']) ?></strong><br>
                        <small>
                            Course: <?= htmlspecialchars($assignment['course_code']) ?> • 
                            Due: <?= date('M j, Y', strtotime($assignment['due_date'])) ?> • 
                            Priority: <?= ucfirst($assignment['priority']) ?>
                        </small>
                        <?php if ($assignment['description']): ?>
                            <p style="margin: 5px 0 0 0; font-size: 0.9em;">
                                <?= htmlspecialchars($assignment['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Generated on <?= date('F j, Y \a\t g:i A') ?> • WEZO CAMPUS HUB</p>
            <p>This timetable is subject to change. Please check regularly for updates.</p>
        </div>
        
        <script>
        window.onload = function() {
            // Auto-print if requested
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1') {
                window.print();
            }
        };
        </script>
    </body>
    </html>
    <?php
    exit;
}

function exportExcel() {
    global $userInfo, $timetable, $week, $year, $weekStart, $weekEnd, $assignments;
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="timetable_week_' . $week . '_' . $year . '.xls"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fwrite($output, "WEZO CAMPUS HUB - Academic Timetable\n");
    fwrite($output, "Week " . $week . " (" . $weekStart->format('M j') . " - " . $weekEnd->format('M j, Y') . ")\n");
    fwrite($output, "Student: " . $userInfo['first_name'] . " " . $userInfo['last_name'] . "\n");
    fwrite($output, "Student ID: " . ($userInfo['student_id'] ?? 'N/A') . "\n");
    fwrite($output, "Campus: " . ($userInfo['campus_name'] ?? 'N/A') . "\n\n");
    
    // Timetable headers
    fwrite($output, "Day\tDate\tStart Time\tEnd Time\tCourse Code\tCourse Name\tType\tLocation\tLecturer\tNotes\n");
    
    // Timetable data
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($timetable as $entry) {
        $dayName = $days[$entry['day_of_week']];
        $dayDate = date('M j', strtotime($weekStart->format('Y-m-d') . " +" . $entry['day_of_week'] . " days"));
        
        $row = [
            $dayName,
            $dayDate,
            date('g:i A', strtotime($entry['start_time'])),
            date('g:i A', strtotime($entry['end_time'])),
            $entry['course_code'],
            $entry['course_name'],
            ucfirst($entry['entry_type']),
            $entry['room_name'] ?: 'N/A',
            $entry['first_name'] ? $entry['first_name'] . ' ' . $entry['last_name'] : 'N/A',
            $entry['notes'] ?: ''
        ];
        
        fwrite($output, implode("\t", $row) . "\n");
    }
    
    // Add assignments if any
    if (!empty($assignments)) {
        fwrite($output, "\n\nUPCOMING ASSIGNMENTS\n");
        fwrite($output, "Course\tTitle\tDue Date\tPriority\tStatus\tDescription\n");
        
        foreach ($assignments as $assignment) {
            $row = [
                $assignment['course_code'],
                $assignment['title'],
                date('M j, Y', strtotime($assignment['due_date'])),
                ucfirst($assignment['priority']),
                ucfirst($assignment['status']),
                $assignment['description'] ?: ''
            ];
            
            fwrite($output, implode("\t", $row) . "\n");
        }
    }
    
    fwrite($output, "\n\nGenerated on " . date('F j, Y \a\t g:i A') . " by WEZO CAMPUS HUB");
    
    fclose($output);
    exit;
}

function exportCSV() {
    global $userInfo, $timetable, $week, $year, $weekStart, $weekEnd;
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="timetable_week_' . $week . '_' . $year . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['WEZO CAMPUS HUB - Academic Timetable']);
    fputcsv($output, ['Week ' . $week . ' (' . $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y') . ')']);
    fputcsv($output, ['Student: ' . $userInfo['first_name'] . ' ' . $userInfo['last_name']]);
    fputcsv($output, ['Student ID: ' . ($userInfo['student_id'] ?? 'N/A')]);
    fputcsv($output, ['Campus: ' . ($userInfo['campus_name'] ?? 'N/A')]);
    fputcsv($output, []); // Empty row
    
    // Timetable headers
    fputcsv($output, ['Day', 'Date', 'Start Time', 'End Time', 'Course Code', 'Course Name', 'Type', 'Location', 'Lecturer', 'Notes']);
    
    // Timetable data
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($timetable as $entry) {
        $dayName = $days[$entry['day_of_week']];
        $dayDate = date('M j', strtotime($weekStart->format('Y-m-d') . " +" . $entry['day_of_week'] . " days"));
        
        fputcsv($output, [
            $dayName,
            $dayDate,
            date('g:i A', strtotime($entry['start_time'])),
            date('g:i A', strtotime($entry['end_time'])),
            $entry['course_code'],
            $entry['course_name'],
            ucfirst($entry['entry_type']),
            $entry['room_name'] ?: 'N/A',
            $entry['first_name'] ? $entry['first_name'] . ' ' . $entry['last_name'] : 'N/A',
            $entry['notes'] ?: ''
        ]);
    }
    
    fclose($output);
    exit;
}

function exportICal() {
    global $userInfo, $timetable, $week, $year;
    
    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename="timetable_week_' . $week . '_' . $year . '.ics"');
    
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//WEZO CAMPUS HUB//Timetable//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:PUBLISH\r\n";
    echo "X-WR-CALNAME:Academic Timetable\r\n";
    echo "X-WR-TIMEZONE:Africa/Nairobi\r\n";
    echo "X-WR-CALDESC:Timetable for " . $userInfo['first_name'] . " " . $userInfo['last_name'] . "\r\n";
    
    foreach ($timetable as $entry) {
        $startTime = new DateTime($entry['start_time']);
        $endTime = new DateTime($entry['end_time']);
        
        // Generate unique ID
        $uid = md5($entry['id'] . $entry['start_time']) . '@wezocampushub.com';
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $uid . "\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:" . $startTime->format('Ymd\THis') . "\r\n";
        echo "DTEND:" . $endTime->format('Ymd\THis') . "\r\n";
        echo "SUMMARY:" . $entry['course_code'] . " - " . $entry['course_name'] . "\r\n";
        echo "DESCRIPTION:" . ($entry['notes'] ?: 'Class session') . "\r\n";
        
        if ($entry['room_name']) {
            echo "LOCATION:" . $entry['room_name'] . ($entry['building'] ? ", " . $entry['building'] : "") . "\r\n";
        }
        
        if ($entry['lecturer_id']) {
            echo "ORGANIZER;CN=\"" . $entry['first_name'] . " " . $entry['last_name'] . "\":mailto:" . ($entry['email'] ?? 'noreply@wezocampushub.com') . "\r\n";
        }
        
        echo "RRULE:FREQ=WEEKLY;UNTIL=" . date('Ymd\T235959\Z', strtotime('+3 months')) . "\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "SEQUENCE:0\r\n";
        echo "END:VEVENT\r\n";
    }
    
    echo "END:VCALENDAR\r\n";
    exit;
}

function exportImage() {
    // This would generate an image of the timetable
    // For now, redirect to printable version
    header('Location: export.php?format=pdf&autoprint=1');
    exit;
}