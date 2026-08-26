<?php
/**
 * WEZO CAMPUS HUB - Timetable Planner
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

// Get current week
$currentWeek = intval($_GET['week'] ?? date('W'));
$currentYear = intval($_GET['year'] ?? date('Y'));
$view = $_GET['view'] ?? 'week'; // week, day, month, list

// Calculate week start and end
$weekStart = new DateTime();
$weekStart->setISODate($currentYear, $currentWeek);
$weekStart->setTime(0, 0, 0);

$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');
$weekEnd->setTime(23, 59, 59);

// Get user's timetable
$timetable = $db->fetchAll("
    SELECT tt.*, 
           c.code as course_code, c.name as course_name, c.color as course_color,
           u.first_name, u.last_name, u.username as lecturer_username,
           r.name as room_name, r.building as building_name
    FROM timetable_entries tt
    LEFT JOIN courses c ON tt.course_id = c.id
    LEFT JOIN users u ON tt.lecturer_id = u.id
    LEFT JOIN rooms r ON tt.room_id = r.id
    WHERE tt.user_id = ? 
    AND tt.status = 'active'
    AND tt.start_time >= ? AND tt.start_time <= ?
    ORDER BY tt.day_of_week, tt.start_time
", [$user['id'], $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);

// Group by day
$timetableByDay = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

foreach ($days as $dayIndex => $dayName) {
    $timetableByDay[$dayIndex] = array_filter($timetable, function($entry) use ($dayIndex) {
        return $entry['day_of_week'] == $dayIndex;
    });
}

// Get upcoming assignments
$upcomingAssignments = $db->fetchAll("
    SELECT a.*, c.code as course_code, c.name as course_name
    FROM assignments a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.user_id = ? 
    AND a.due_date >= CURDATE()
    AND a.status IN ('pending', 'in_progress')
    ORDER BY a.due_date ASC
    LIMIT 5
", [$user['id']]);

// Get courses for adding entries
$courses = $db->fetchAll("
    SELECT c.* FROM courses c
    WHERE c.id IN (
        SELECT course_id FROM student_courses 
        WHERE user_id = ? AND status = 'active'
    )
    ORDER BY c.code
", [$user['id']]);

// Handle timetable actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_entry'])) {
        $entryData = [
            'user_id' => $user['id'],
            'course_id' => intval($_POST['course_id']),
            'day_of_week' => intval($_POST['day_of_week']),
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'lecturer_id' => intval($_POST['lecturer_id'] ?? 0),
            'room_id' => intval($_POST['room_id'] ?? 0),
            'entry_type' => $_POST['entry_type'],
            'recurring' => isset($_POST['recurring']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? ''),
            'color' => $_POST['color'] ?? '#007bff',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $entryId = $db->insert('timetable_entries', $entryData);
        
        if ($entryId) {
            Session::setFlash('success', 'Timetable entry added successfully');
            header('Location: index.php');
            exit;
        } else {
            Session::setFlash('error', 'Failed to add timetable entry');
        }
    } elseif (isset($_POST['delete_entry'])) {
        $entryId = intval($_POST['entry_id']);
        
        // Verify ownership
        $entry = $db->fetch("SELECT user_id FROM timetable_entries WHERE id = ?", [$entryId]);
        
        if ($entry && $entry['user_id'] == $user['id']) {
            $db->query("UPDATE timetable_entries SET status = 'deleted' WHERE id = ?", [$entryId]);
            Session::setFlash('success', 'Entry deleted');
            header('Location: index.php');
            exit;
        }
    } elseif (isset($_POST['add_assignment'])) {
        $assignmentData = [
            'user_id' => $user['id'],
            'course_id' => intval($_POST['course_id']),
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'due_date' => $_POST['due_date'],
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $assignmentId = $db->insert('assignments', $assignmentData);
        
        if ($assignmentId) {
            Session::setFlash('success', 'Assignment added to tracker');
        } else {
            Session::setFlash('error', 'Failed to add assignment');
        }
    }
}

$pageTitle = "Timetable Planner";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Timetable Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Timetable Planner</h1>
                    <p class="text-muted mb-0">
                        Week <?= $currentWeek ?>, <?= $currentYear ?> • 
                        <?= $weekStart->format('M j') ?> - <?= $weekEnd->format('M j, Y') ?>
                    </p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                                <i class="fas fa-calendar-plus"></i> Timetable Entry
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                <i class="fas fa-tasks"></i> Assignment
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="import.php">
                                <i class="fas fa-file-import"></i> Import Timetable
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="export.php">
                                <i class="fas fa-file-export"></i> Export Timetable
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- View Controls -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar"></i>
                                </span>
                                <select class="form-select" id="viewSelect">
                                    <option value="week" <?= $view == 'week' ? 'selected' : '' ?>>Week View</option>
                                    <option value="day" <?= $view == 'day' ? 'selected' : '' ?>>Day View</option>
                                    <option value="month" <?= $view == 'month' ? 'selected' : '' ?>>Month View</option>
                                    <option value="list" <?= $view == 'list' ? 'selected' : '' ?>>List View</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="input-group">
                                <button class="btn btn-outline-secondary" onclick="previousWeek()">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <input type="week" class="form-control text-center" 
                                       id="weekPicker" 
                                       value="<?= $currentYear ?>-W<?= str_pad($currentWeek, 2, '0', STR_PAD_LEFT) ?>">
                                <button class="btn btn-outline-secondary" onclick="nextWeek()">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <button class="btn btn-outline-primary" onclick="goToToday()">
                                    <i class="fas fa-calendar-day"></i> Today
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary w-100" onclick="printTimetable()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button class="btn btn-outline-success w-100" onclick="shareTimetable()">
                                    <i class="fas fa-share"></i> Share
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Week View -->
            <?php if ($view === 'week'): ?>
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <div class="timetable-week">
                            <!-- Time Header -->
                            <div class="timetable-header">
                                <div class="timetable-time-col"></div>
                                <?php for ($i = 0; $i < 7; $i++): 
                                    $dayDate = clone $weekStart;
                                    $dayDate->modify("+$i days");
                                ?>
                                    <div class="timetable-day-col <?= $dayDate->format('Y-m-d') == date('Y-m-d') ? 'today' : '' ?>">
                                        <div class="day-header">
                                            <div class="day-name"><?= $days[$i] ?></div>
                                            <div class="day-date"><?= $dayDate->format('M j') ?></div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- Time Slots (8 AM to 8 PM) -->
                            <?php for ($hour = 8; $hour <= 20; $hour++): ?>
                                <div class="timetable-row">
                                    <div class="timetable-time-col">
                                        <div class="time-slot">
                                            <?= str_pad($hour, 2, '0', STR_PAD_LEFT) ?>:00
                                        </div>
                                    </div>
                                    
                                    <?php for ($day = 0; $day < 7; $day++): ?>
                                        <div class="timetable-day-col <?= $day == date('w') - 1 ? 'today' : '' ?>">
                                            <div class="time-cell" data-hour="<?= $hour ?>" data-day="<?= $day ?>">
                                                <?php 
                                                // Find entries for this day and hour
                                                $currentEntries = array_filter($timetableByDay[$day] ?? [], function($entry) use ($hour) {
                                                    $entryHour = (int)date('G', strtotime($entry['start_time']));
                                                    return $entryHour == $hour;
                                                });
                                                
                                                foreach ($currentEntries as $entry): 
                                                    $duration = (strtotime($entry['end_time']) - strtotime($entry['start_time'])) / 3600;
                                                    $top = (date('i', strtotime($entry['start_time'])) / 60) * 100;
                                                ?>
                                                    <div class="timetable-entry" 
                                                         style="background: <?= $entry['color'] ?>; height: calc(<?= $duration ?> * 60px); top: <?= $top ?>%;"
                                                         data-entry-id="<?= $entry['id'] ?>">
                                                        <div class="entry-content">
                                                            <div class="entry-title">
                                                                <strong><?= htmlspecialchars($entry['course_code']) ?></strong>
                                                            </div>
                                                            <div class="entry-details">
                                                                <?= date('g:i', strtotime($entry['start_time'])) ?> - 
                                                                <?= date('g:i A', strtotime($entry['end_time'])) ?>
                                                            </div>
                                                            <?php if ($entry['room_name']): ?>
                                                                <div class="entry-location">
                                                                    <i class="fas fa-map-marker-alt"></i> 
                                                                    <?= htmlspecialchars($entry['room_name']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="entry-actions">
                                                            <button class="btn btn-sm btn-light" onclick="editEntry(<?= $entry['id'] ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($view === 'list'): ?>
                <!-- List View -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Weekly Schedule (List View)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (empty($timetable)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No classes scheduled</h5>
                                    <p class="text-muted">Add your first class to get started</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                                        <i class="fas fa-plus"></i> Add Class
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($timetable as $entry): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1 me-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge me-2" style="background: <?= $entry['color'] ?>; color: white;">
                                                        <?= $days[$entry['day_of_week']] ?>
                                                    </span>
                                                    <span class="badge bg-secondary">
                                                        <?= date('g:i A', strtotime($entry['start_time'])) ?> - 
                                                        <?= date('g:i A', strtotime($entry['end_time'])) ?>
                                                    </span>
                                                </div>
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($entry['course_code']) ?>: 
                                                    <?= htmlspecialchars($entry['course_name']) ?>
                                                </h6>
                                                <div class="text-muted small">
                                                    <?php if ($entry['lecturer_username']): ?>
                                                        <i class="fas fa-chalkboard-teacher"></i> 
                                                        @<?= htmlspecialchars($entry['lecturer_username']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($entry['room_name']): ?>
                                                        <span class="ms-2">
                                                            <i class="fas fa-map-marker-alt"></i> 
                                                            <?= htmlspecialchars($entry['room_name']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($entry['notes']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-sticky-note"></i> 
                                                            <?= htmlspecialchars($entry['notes']) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="editEntry(<?= $entry['id'] ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                                                <button type="submit" name="delete_entry" class="dropdown-item text-danger" 
                                                                        onclick="return confirm('Delete this entry?')">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item" href="#">
                                                                <i class="fas fa-share"></i> Share
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Upcoming Assignments -->
            <?php if (!empty($upcomingAssignments)): ?>
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Upcoming Assignments</h5>
                        <a href="assignments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($upcomingAssignments as $assignment): 
                                $daysLeft = floor((strtotime($assignment['due_date']) - time()) / (60 * 60 * 24));
                                $priorityClass = '';
                                switch ($assignment['priority']) {
                                    case 'high': $priorityClass = 'danger'; break;
                                    case 'medium': $priorityClass = 'warning'; break;
                                    case 'low': $priorityClass = 'info'; break;
                                }
                            ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-<?= $priorityClass ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title mb-1"><?= htmlspecialchars($assignment['title']) ?></h6>
                                                    <p class="card-text small text-muted mb-2">
                                                        <?= htmlspecialchars($assignment['course_code']) ?> • 
                                                        Due: <?= date('M j, Y', strtotime($assignment['due_date'])) ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-<?= $priorityClass ?>">
                                                    <?= $daysLeft > 0 ? "$daysLeft days left" : 'Due today' ?>
                                                </span>
                                            </div>
                                            <?php if ($assignment['description']): ?>
                                                <p class="card-text small"><?= htmlspecialchars(substr($assignment['description'], 0, 100)) ?>...</p>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar bg-<?= $priorityClass ?>" 
                                                         style="width: <?= min(100, max(0, 100 - ($daysLeft * 10))) ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="row mt-4">
                <div class="col-md-3 col-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= count($timetable) ?></h2>
                            <p class="text-muted mb-0">Weekly Classes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= count($upcomingAssignments) ?></h2>
                            <p class="text-muted mb-0">Upcoming Assignments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= count($courses) ?></h2>
                            <p class="text-muted mb-0">Registered Courses</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h2 class="mb-0">
                                <?= array_sum(array_column($timetable, function($entry) {
                                    return (strtotime($entry['end_time']) - strtotime($entry['start_time'])) / 3600;
                                })) ?>
                            </h2>
                            <p class="text-muted mb-0">Weekly Hours</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Entry Modal -->
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Timetable Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course *</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" 
                                            data-color="<?= $course['color'] ?>">
                                        <?= htmlspecialchars($course['code']) ?> - <?= htmlspecialchars($course['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Entry Type *</label>
                            <select name="entry_type" class="form-select" required>
                                <option value="lecture">Lecture</option>
                                <option value="tutorial">Tutorial</option>
                                <option value="lab">Lab Session</option>
                                <option value="study">Study Time</option>
                                <option value="break">Break</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Day *</label>
                            <select name="day_of_week" class="form-select" required>
                                <?php foreach ($days as $index => $day): ?>
                                    <option value="<?= $index ?>"><?= $day ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control form-control-color" 
                                   value="#007bff" title="Choose color">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Recurring</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="recurring" class="form-check-input" id="recurring">
                                <label class="form-check-label" for="recurring">
                                    Repeat weekly
                                </label>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_entry" class="btn btn-primary">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>">
                                    <?= htmlspecialchars($course['code']) ?> - <?= htmlspecialchars($course['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-control" required 
                               min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_assignment" class="btn btn-primary">Add Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Week navigation
const weekPicker = document.getElementById('weekPicker');
const viewSelect = document.getElementById('viewSelect');

function previousWeek() {
    const [year, week] = weekPicker.value.split('-W');
    let newWeek = parseInt(week) - 1;
    let newYear = parseInt(year);
    
    if (newWeek < 1) {
        newWeek = 52;
        newYear--;
    }
    
    navigateToWeek(newYear, newWeek);
}

function nextWeek() {
    const [year, week] = weekPicker.value.split('-W');
    let newWeek = parseInt(week) + 1;
    let newYear = parseInt(year);
    
    if (newWeek > 52) {
        newWeek = 1;
        newYear++;
    }
    
    navigateToWeek(newYear, newWeek);
}

function goToToday() {
    const today = new Date();
    const year = today.getFullYear();
    const week = getWeekNumber(today);
    
    navigateToWeek(year, week);
}

function navigateToWeek(year, week) {
    const params = new URLSearchParams(window.location.search);
    params.set('year', year);
    params.set('week', week);
    window.location.href = 'index.php?' + params.toString();
}

function getWeekNumber(date) {
    const firstDayOfYear = new Date(date.getFullYear(), 0, 1);
    const pastDaysOfYear = (date - firstDayOfYear) / 86400000;
    return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
}

// Event listeners
weekPicker.addEventListener('change', function() {
    const [year, week] = this.value.split('-W');
    navigateToWeek(year, parseInt(week));
});

viewSelect.addEventListener('change', function() {
    const params = new URLSearchParams(window.location.search);
    params.set('view', this.value);
    window.location.href = 'index.php?' + params.toString();
});

// Print timetable
function printTimetable() {
    window.print();
}

// Share timetable
function shareTimetable() {
    if (navigator.share) {
        navigator.share({
            title: 'My Timetable - Week <?= $currentWeek ?>',
            text: 'Check out my timetable for week <?= $currentWeek ?> on WEZO CAMPUS HUB',
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Timetable link copied to clipboard!');
    }
}

// Edit entry
function editEntry(entryId) {
    window.location.href = 'edit.php?id=' + entryId;
}

// Auto-color selection
document.querySelector('select[name="course_id"]').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const color = selectedOption.getAttribute('data-color');
    if (color) {
        document.querySelector('input[name="color"]').value = color;
    }
});
</script>

<style>
.timetable-week {
    display: flex;
    flex-direction: column;
    min-height: 600px;
}

.timetable-header {
    display: flex;
    border-bottom: 2px solid #dee2e6;
    background: #f8f9fa;
}

.timetable-time-col {
    width: 80px;
    min-width: 80px;
    flex-shrink: 0;
    border-right: 1px solid #dee2e6;
}

.timetable-day-col {
    flex: 1;
    min-width: 0;
    border-right: 1px solid #dee2e6;
}

.timetable-day-col:last-child {
    border-right: none;
}

.timetable-day-col.today {
    background-color: rgba(13, 110, 253, 0.05);
}

.day-header {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
}

.day-name {
    font-weight: bold;
    font-size: 0.9em;
}

.day-date {
    font-size: 0.8em;
    color: #6c757d;
}

.timetable-row {
    display: flex;
    border-bottom: 1px solid #dee2e6;
    min-height: 60px;
}

.time-slot {
    padding: 10px;
    text-align: center;
    font-size: 0.8em;
    color: #6c757d;
    border-right: 1px solid #dee2e6;
    height: 60px;
}

.time-cell {
    position: relative;
    height: 60px;
    border-right: 1px solid #dee2e6;
}

.time-cell:last-child {
    border-right: none;
}

.timetable-entry {
    position: absolute;
    left: 2px;
    right: 2px;
    border-radius: 4px;
    padding: 5px;
    color: white;
    font-size: 0.75em;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s;
    z-index: 1;
}

.timetable-entry:hover {
    transform: scale(1.02);
    z-index: 2;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.entry-content {
    overflow: hidden;
}

.entry-title {
    font-weight: bold;
    margin-bottom: 2px;
}

.entry-details {
    font-size: 0.85em;
    opacity: 0.9;
}

.entry-location {
    font-size: 0.8em;
    margin-top: 2px;
}

.entry-actions {
    position: absolute;
    top: 2px;
    right: 2px;
    opacity: 0;
    transition: opacity 0.2s;
}

.timetable-entry:hover .entry-actions {
    opacity: 1;
}

@media print {
    .col-md-3, .modal, .dropdown, .btn, .card-header .btn {
        display: none !important;
    }
    .col-md-9 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    .timetable-entry {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: #fff !important;
    }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>