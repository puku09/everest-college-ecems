<?php
session_start();
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/SecurityHelper.php';

$isLoggedIn = SecurityHelper::isLoggedIn();
$u_id = $_SESSION['u_id'] ?? 0;

// Fetch departments
$dept_list = [];
$dept_result = $conn->query("SELECT * FROM departments ORDER BY dept_name ASC");
if ($dept_result) {
    while($row = $dept_result->fetch_assoc()) {
        $dept_list[] = $row;
    }
}

// Filters
$f_dept = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
$f_cat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;

$filter_query = "";
if ($f_dept) $filter_query .= " AND e.dept_id = $f_dept";
if ($f_cat) $filter_query .= " AND e.category_id = $f_cat";

// Upcoming events
$upcoming_sql = "SELECT e.*, 
                 d.dept_name,
                 c.category_name,
                 v.venue_name,
                 r_check.reg_id as is_registered,
                 (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.event_id AND r2.reg_status = 'confirmed') as current_participants
                 FROM events e 
                 LEFT JOIN departments d ON e.dept_id = d.dept_id
                 LEFT JOIN categories c ON e.category_id = c.category_id
                 LEFT JOIN venues v ON e.venue_id = v.venue_id
                 LEFT JOIN registrations r_check ON e.event_id = r_check.event_id AND r_check.u_id = $u_id
                 WHERE e.is_published = 1 AND e.event_date >= CURDATE() $filter_query
                 ORDER BY e.event_date ASC";

$upcoming_result = $conn->query($upcoming_sql);

// Past events
$past_sql = "SELECT e.*, 
             d.dept_name,
             c.category_name,
             v.venue_name,
             r_check.reg_id as is_registered,
             (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.event_id AND r2.reg_status = 'confirmed') as current_participants
             FROM events e 
             LEFT JOIN departments d ON e.dept_id = d.dept_id
             LEFT JOIN categories c ON e.category_id = c.category_id
             LEFT JOIN venues v ON e.venue_id = v.venue_id
             LEFT JOIN registrations r_check ON e.event_id = r_check.event_id AND r_check.u_id = $u_id
             WHERE e.is_published = 1 AND e.event_date < CURDATE() $filter_query
             ORDER BY e.event_date DESC
             LIMIT 12";

$past_result = $conn->query($past_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include "includes/header.php"; ?>

    <main>
        <section class="hero-section">
            <h1>🎓 Everest College</h1>
            <p>Event Management System - Discover & Register for Amazing Events</p>
        </section>
        
        <section id="events">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> Upcoming Events</h2>
                <div class="filters">
                    <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select name="dept_id" onchange="this.form.submit()" class="filter-select">
                            <option value="">All Departments</option>
                            <?php foreach ($dept_list as $d): ?>
                                <option value="<?= $d['dept_id'] ?>" <?= $f_dept == $d['dept_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['dept_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="category_id" onchange="this.form.submit()" class="filter-select">
                            <option value="">All Categories</option>
                            <option value="1" <?= $f_cat == 1 ? 'selected' : '' ?>>Academic</option>
                            <option value="2" <?= $f_cat == 2 ? 'selected' : '' ?>>Workshop</option>
                            <option value="3" <?= $f_cat == 3 ? 'selected' : '' ?>>Sports</option>
                            <option value="4" <?= $f_cat == 4 ? 'selected' : '' ?>>Cultural</option>
                            <option value="5" <?= $f_cat == 5 ? 'selected' : '' ?>>Career</option>
                        </select>
                        <?php if($f_dept || $f_cat): ?>
                            <a href="index.php" class="filter-clear">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="events-grid">
                <?php if ($upcoming_result && $upcoming_result->num_rows > 0): ?>
                    <?php while($row = $upcoming_result->fetch_assoc()): ?>
                        <div class="event-card">
                            <div class="event-image">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="event-content">
                                <span class="event-category"><?= htmlspecialchars($row['category_name'] ?? 'Event') ?></span>
                                <h3 class="event-title"><?= htmlspecialchars($row['event_title']) ?></h3>
                                
                                <div class="event-details">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M d, Y H:i A', strtotime($row['event_date'])) ?>
                                </div>
                                
                                <?php if(!empty($row['venue_name'])): ?>
                                    <div class="event-details">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($row['venue_name']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-details">
                                    <i class="fas fa-users"></i>
                                    <?= $row['current_participants'] ?? 0 ?>/<?= $row['event_capacity'] ?> Registered
                                </div>
                            </div>
                            
                            <div class="event-footer">
                                <?php if($isLoggedIn): ?>
                                    <?php if($row['is_registered']): ?>
                                        <button class="btn-register btn-registered" disabled>
                                            <i class="fas fa-check"></i> Registered
                                        </button>
                                    <?php else: ?>
                                        <a href="api/register-event.php?event_id=<?= $row['event_id'] ?>" class="btn-register">
                                            Register Now
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login/" class="btn-register">Login to Register</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-events">
                        <i class="fas fa-inbox"></i><br>
                        No upcoming events. Check back soon!
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="cta-section">
            <div class="cta-banner">
                <img src="https://cdn-icons-png.flaticon.com/512/4341/4341134.png" width="150" alt="Host Event">
                <div class="cta-content">
                    <h3>Want to Host Your Event?</h3>
                    <p>Contact the events management team and register your event.</p>
                </div>
                <a href="contact/" class="btn-pink">Host An Event</a>
            </div>
        </section>

        <section class="past-events-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Past Events</h2>
            </div>
            <div class="events-grid">
                <?php if ($past_result && $past_result->num_rows > 0): ?>
                    <?php while($row = $past_result->fetch_assoc()): ?>
                        <div class="event-card">
                            <div class="event-image" style="opacity: 0.7;">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="event-content">
                                <span class="event-category"><?= htmlspecialchars($row['category_name'] ?? 'Event') ?></span>
                                <h3 class="event-title"><?= htmlspecialchars($row['event_title']) ?></h3>
                                
                                <div class="event-details">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M d, Y', strtotime($row['event_date'])) ?>
                                </div>
                                
                                <div class="event-details">
                                    <i class="fas fa-users"></i>
                                    <?= $row['current_participants'] ?? 0 ?> Participants
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-events">No past events to display.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include "includes/footer.php"; ?>
    <script src="assets/js/script.js"></script>
</body>
</html>