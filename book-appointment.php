<?php
/* =========================================================================
   PICKBREW APPOINTMENT SYSTEM (Fixed Icon Layout)
   Shortcode: [pickbrew_appointment]
   Features: 
   1. UI matches Espressly/Calendly style (Date Strip > Time > Form)
   2. Stores data in Custom Post Type 'booking_entry'
   3. CSV Export Feature in Admin
   ========================================================================= */

/* -------------------------------------------------------------------------
   1. BACKEND: REGISTER POST TYPE & CSV EXPORT
   ------------------------------------------------------------------------- */

// Register CPT
add_action('init', 'register_booking_cpt');
function register_booking_cpt() {
    register_post_type('booking_entry', array(
        'labels' => array(
            'name' => 'Appointments',
            'singular_name' => 'Appointment',
            'menu_name' => 'Bookings'
        ),
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => array('title', 'custom-fields'),
        'capabilities' => array('create_posts' => false) // Admin only view
    ));
}

// Add CSV Export Button to Admin List
add_action('manage_posts_extra_tablenav', 'add_export_bookings_button');
function add_export_bookings_button($which) {
    global $typenow;
    if ($typenow === 'booking_entry' && $which === 'top') {
        echo '<div class="alignleft actions"><a href="?post_type=booking_entry&page=export_bookings_csv" class="button button-primary">Download CSV</a></div>';
    }
}

// Handle CSV Download Logic
add_action('admin_init', 'handle_booking_csv_export');
function handle_booking_csv_export() {
    if (isset($_GET['page']) && $_GET['page'] == 'export_bookings_csv') {
        if (!current_user_can('manage_options')) return;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pickbrew-bookings-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, array('ID', 'Date Submitted', 'Meeting Date', 'Meeting Time', 'Timezone', 'Name', 'Email', 'Phone', 'Notes'));

        $args = array('post_type' => 'booking_entry', 'posts_per_page' => -1);
        $query = new WP_Query($args);

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $meta = get_post_meta($id);

            fputcsv($output, array(
                $id,
                get_the_date('Y-m-d H:i:s'),
                isset($meta['meeting_date'][0]) ? $meta['meeting_date'][0] : '',
                isset($meta['meeting_time'][0]) ? $meta['meeting_time'][0] : '',
                isset($meta['meeting_timezone'][0]) ? $meta['meeting_timezone'][0] : '',
                get_the_title(), // Name is stored as title
                isset($meta['client_email'][0]) ? $meta['client_email'][0] : '',
                isset($meta['client_phone'][0]) ? $meta['client_phone'][0] : '',
                isset($meta['client_notes'][0]) ? $meta['client_notes'][0] : '',
            ));
        }
        fclose($output);
        exit;
    }
}

// Add Columns to Admin Dashboard
add_filter('manage_booking_entry_posts_columns', 'set_booking_columns');
function set_booking_columns($columns) {
    $new = array();
    $new['cb'] = $columns['cb'];
    $new['title'] = 'Name';
    $new['meeting_info'] = 'Meeting Time';
    $new['contact_info'] = 'Contact Details';
    $new['date'] = 'Date Submitted';
    return $new;
}

add_action('manage_booking_entry_posts_custom_column', 'fill_booking_columns', 10, 2);
function fill_booking_columns($column, $post_id) {
    if ($column === 'meeting_info') {
        echo '📅 ' . get_post_meta($post_id, 'meeting_date', true) . '<br>';
        echo '⏰ ' . get_post_meta($post_id, 'meeting_time', true) . '<br>';
        echo '🌍 ' . get_post_meta($post_id, 'meeting_timezone', true);
    }
    if ($column === 'contact_info') {
        echo '📧 ' . get_post_meta($post_id, 'client_email', true) . '<br>';
        echo '📱 ' . get_post_meta($post_id, 'client_phone', true);
    }
}

/* -------------------------------------------------------------------------
   2. FRONTEND: SHORTCODE & UI
   ------------------------------------------------------------------------- */

add_shortcode('pickbrew_appointment', 'render_pickbrew_appointment_ui');

function render_pickbrew_appointment_ui() {
    
    // --- HANDLE SUBMISSION ---
    $success_data = false;
    if (isset($_POST['pb_submit_booking'])) {
        $name = sanitize_text_field($_POST['pb_name']);
        $email = sanitize_email($_POST['pb_email']);
        $phone = sanitize_text_field($_POST['pb_phone']);
        $notes = sanitize_textarea_field($_POST['pb_notes']);
        $date = sanitize_text_field($_POST['pb_date']);
        $time = sanitize_text_field($_POST['pb_time']);
        $tz = sanitize_text_field($_POST['pb_timezone']);

        // Create Post
        $pid = wp_insert_post(array(
            'post_title' => $name,
            'post_type' => 'booking_entry',
            'post_status' => 'publish'
        ));

        if ($pid) {
            update_post_meta($pid, 'client_email', $email);
            update_post_meta($pid, 'client_phone', $phone);
            update_post_meta($pid, 'client_notes', $notes);
            update_post_meta($pid, 'meeting_date', $date);
            update_post_meta($pid, 'meeting_time', $time);
            update_post_meta($pid, 'meeting_timezone', $tz);

            // Send Email to Admin
            $admin_to = 'amit@pickbrew.com'; // Add more separated by comma
            $subject = "New Booking: $name - $date @ $time";
            $message = "New App Review Call Booked.\n\nName: $name\nDate: $date\nTime: $time\nTimezone: $tz\nEmail: $email\nPhone: $phone\nNotes: $notes";
            wp_mail($admin_to, $subject, $message);

            // Send Email to Client
            $c_subject = "Your Appointment is Confirmed: Mobile App Review Call";
            $c_msg = "Hi $name,\n\nYour appointment is confirmed for $date at $time ($tz).\n\nLooking forward to connecting.\n\nPickBrew Team";
            wp_mail($email, $c_subject, $c_msg);

            // Set Success Data for View
            $success_data = array(
                'name' => $name, 'email' => $email, 'phone' => $phone, 'notes' => $notes,
                'date' => $date, 'time' => $time, 'tz' => $tz
            );
        }
    }

    ob_start();
    ?>
    <style>
        /* CSS Matching Screenshots */
        .pb-wrapper { font-family: 'Inter', sans-serif; max-width: 900px; margin: 40px auto; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; color: #333; box-shadow: 0 4px 12px rgba(0,0,0,0.05); min-height: 500px; position: relative; }
        
        /* Header */
        .pb-header { padding: 30px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; }
        .pb-title { font-size: 20px; font-weight: 700; margin: 0 0 5px 0; color: #111; }
        .pb-dur { font-size: 14px; color: #666; font-weight: 500; }
        .pb-tz-box { background: #f9f9f9; padding: 8px 12px; border-radius: 4px; font-size: 13px; color: #555; display: flex; align-items: center; gap: 5px; cursor: pointer; }
        
        /* Views Container */
        .pb-body { padding: 0; min-height: 400px; }
        
        /* VIEW 1: DATE SELECTOR */
        .view-section { display: none; padding: 30px; animation: fadeIn 0.3s ease; }
        .view-section.active { display: block; }

        .pb-cal-header { text-align: center; margin-bottom: 30px; font-size: 18px; font-weight: 500; }
        
        /* Date Strip (Horizontal) */
        .date-strip-wrap { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 30px; }
        .ds-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #999; padding: 10px; }
        .ds-btn:hover { color: #333; }
        
        .date-container { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 10px; scroll-behavior: smooth; -ms-overflow-style: none; scrollbar-width: none; width: 100%; justify-content: flex-start; }
        .date-container::-webkit-scrollbar { display: none; }
        
        .d-card { min-width: 100px; border: 1px solid #eee; border-radius: 6px; padding: 15px 10px; text-align: center; cursor: pointer; transition: 0.2s; background: #fff; flex-shrink: 0; }
        .d-card:hover { border-color: #8dc63f; box-shadow: 0 4px 10px rgba(141, 198, 63, 0.2); }
        .d-card.selected { background: #f0f9eb; border-color: #8dc63f; }
        .d-day { display: block; font-size: 13px; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
        .d-date { display: block; font-size: 16px; font-weight: 700; color: #000; }

        /* Time Grid (Hidden initially) */
        .time-area { display: none; margin-top: 30px; border-top: 1px solid #eee; padding-top: 30px; }
        .time-area.show { display: block; }
        .time-title { text-align: center; margin-bottom: 20px; font-weight: 600; font-size: 16px; }
        .time-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 15px; max-width: 600px; margin: 0 auto; }
        
        .t-slot { background: #fff; border: 1px solid #8dc63f; color: #8dc63f; padding: 12px; border-radius: 4px; text-align: center; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; }
        .t-slot:hover { background: #8dc63f; color: #fff; }

        /* VIEW 2: FORM (Fixed CSS) */
        .back-btn { display: inline-block; padding: 8px 16px; border: 1px solid #8dc63f; color: #8dc63f; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 700; margin-bottom: 20px; cursor: pointer; text-transform: uppercase; }
        .form-header { font-size: 22px; margin-bottom: 10px; font-weight: 600; }
        .form-sub { font-size: 14px; color: #666; margin-bottom: 30px; }
        
        /* Updated Input Field Styles */
        .pb-field { 
            margin-bottom: 20px; 
            position: relative; 
            width: 100%;
        }
        
        /* Icon Positioning */
        .pb-wrapper .pb-field i { 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); /* Perfectly vertically centered */
            color: #888; 
            font-size: 16px; 
            z-index: 10;
            pointer-events: none; /* Allows click-through to input */
        }
        
        /* Input Padding Forced */
        .pb-wrapper .pb-input { 
            width: 100%; 
            padding: 14px 14px 14px 45px !important; /* Force padding-left so text doesn't hit icon */
            border: 1px solid #ddd; 
            background: #f9f9f9; 
            border-radius: 4px; 
            font-size: 15px; 
            line-height: 1.5;
            box-sizing: border-box; 
            height: auto;
        }
        
        .pb-wrapper .pb-input:focus { 
            background: #fff; 
            border-color: #8dc63f; 
            outline: none; 
        }

        .pb-label { display: none; } 

        .pb-submit { background: #8dc63f; color: #fff; border: none; padding: 15px 30px; font-size: 14px; font-weight: 700; text-transform: uppercase; border-radius: 4px; cursor: pointer; width: 100%; letter-spacing: 0.5px; margin-top: 10px; }
        .pb-submit:hover { background: #7ab332; }

        /* VIEW 3: SUCCESS */
        .s-box { padding: 10px; }
        .s-title { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
        .s-sub { font-size: 14px; color: #666; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .s-detail-row { margin-bottom: 15px; font-size: 15px; }
        .s-label { font-weight: 700; color: #000; width: 80px; display: inline-block; }
        .s-val { color: #333; }
        .s-notice { background: #e3f2fd; color: #0d47a1; padding: 15px; border-left: 4px solid #2196f3; font-size: 14px; margin-top: 30px; display: flex; align-items: center; gap: 10px; }
        .footer-actions { margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; display: flex; gap: 20px; font-size: 12px; font-weight: 700; color: #8dc63f; text-transform: uppercase; }
        .footer-actions span { cursor: pointer; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <div class="pb-wrapper">
        
        <?php if(!$success_data): ?>
        <div class="pb-header">
            <div>
                <h3 class="pb-title">Mobile App Review Call</h3>
                <div class="pb-dur">15 minutes</div>
            </div>
            <div class="pb-tz-box">
                <span id="displayTz">Detecting...</span> <i class="fas fa-pencil-alt" style="font-size:10px;"></i>
            </div>
        </div>
        <?php endif; ?>

        <div class="pb-body">
            
            <?php if($success_data): ?>
            <div class="view-section active" style="padding: 40px;">
                <h2 class="s-title">Thank you! Your appointment is booked: Mobile App Review Call</h2>
                <div class="s-sub"><?php echo $success_data['date'] . ' ' . $success_data['time'] . ' ' . $success_data['tz']; ?></div>
                
                <div class="s-detail-row"><span class="s-label">Name:</span> <span class="s-val"><?php echo esc_html($success_data['name']); ?></span></div>
                <div class="s-detail-row"><span class="s-label">Email:</span> <span class="s-val"><?php echo esc_html($success_data['email']); ?></span></div>
                <div class="s-detail-row"><span class="s-label">Phone:</span> <span class="s-val"><?php echo esc_html($success_data['phone']); ?></span></div>
                <div class="s-detail-row"><span class="s-label">Notes:</span> <span class="s-val"><?php echo esc_html($success_data['notes']); ?></span></div>
                
                <div class="s-notice">
                    <i class="far fa-calendar-check" style="font-size: 18px;"></i>
                    A calendar invitation has been sent to your email address
                </div>

                <div class="footer-actions">
                    <span onclick="window.location.reload()">Schedule a new appointment</span>
                </div>
            </div>
            
            <?php else: ?>

            <div id="viewCalendar" class="view-section active">
                <div class="pb-cal-header">Select a date</div>
                
                <div class="date-strip-wrap">
                    <button class="ds-btn" onclick="scrollDates(-1)"><i class="fas fa-chevron-left"></i></button>
                    <div class="date-container" id="dateScroll">
                        </div>
                    <button class="ds-btn" onclick="scrollDates(1)"><i class="fas fa-chevron-right"></i></button>
                </div>

                <div id="timeSection" class="time-area">
                    <div class="time-title">Select a time on <span id="selDateDisplay">...</span></div>
                    <div class="time-grid" id="timeGrid">
                        </div>
                </div>
            </div>

            <div id="viewForm" class="view-section">
                <div class="back-btn" onclick="showCalendar()"><i class="fas fa-arrow-left"></i> Back</div>
                
                <div class="form-header">You are booking: Mobile App Review Call</div>
                <div class="form-sub" id="confirmTimeDisplay">...</div>

                <form method="post">
                    <input type="hidden" name="pb_date" id="in_date">
                    <input type="hidden" name="pb_time" id="in_time">
                    <input type="hidden" name="pb_timezone" id="in_tz">

                    <div class="pb-field">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" name="pb_name" class="pb-input" placeholder="Name *" required>
                    </div>
                    <div class="pb-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="pb_email" class="pb-input" placeholder="Email *" required>
                    </div>
                    <div class="pb-field">
                        <i class="fas fa-mobile-alt"></i>
                        <input type="text" name="pb_phone" class="pb-input" placeholder="Phone *" required>
                    </div>
                    <div class="pb-field">
                        <i class="fas fa-clipboard-list"></i>
                        <input type="text" name="pb_notes" class="pb-input" placeholder="Notes">
                    </div>

                    <button type="submit" name="pb_submit_booking" class="pb-submit">Book This Appointment</button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
    // --- JAVASCRIPT LOGIC ---
    
    // 1. Timezone Detection
    const userTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const tzDisplay = document.getElementById('displayTz');
    const inputTz = document.getElementById('in_tz');
    
    if(tzDisplay) {
        tzDisplay.innerText = userTz;
        inputTz.value = userTz;
    }

    // 2. Generate Date Strip (Next 14 Days)
    const dateContainer = document.getElementById('dateScroll');
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    
    let selectedDateStr = "";

    function initDates() {
        if(!dateContainer) return;
        const today = new Date();
        
        for(let i=0; i<14; i++) {
            let d = new Date();
            d.setDate(today.getDate() + i);
            
            // Skip Weekends (Optional - matching business logic)
            if(d.getDay() === 0 || d.getDay() === 6) continue;

            let dayName = dayNames[d.getDay()];
            let fullDate = monthNames[d.getMonth()] + " " + d.getDate() + ", " + d.getFullYear(); // December 9, 2025
            
            let card = document.createElement('div');
            card.className = 'd-card';
            card.onclick = function() { selectDate(this, fullDate); };
            card.innerHTML = `<span class="d-day">${dayName}</span><span class="d-date">${monthNames[d.getMonth()] + " " + d.getDate()}</span>`;
            
            dateContainer.appendChild(card);
        }
    }

    function scrollDates(dir) {
        dateContainer.scrollBy({ left: dir * 200, behavior: 'smooth' });
    }

    // 3. Select Date & Show Times
    function selectDate(el, dateStr) {
        // UI
        document.querySelectorAll('.d-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        
        // Data
        selectedDateStr = dateStr;
        document.getElementById('selDateDisplay').innerText = dateStr;
        document.getElementById('in_date').value = dateStr;
        
        // Show Times
        document.getElementById('timeSection').classList.add('show');
        renderTimes();
    }

    function renderTimes() {
        const grid = document.getElementById('timeGrid');
        grid.innerHTML = '';
        
        // Fake slots (Mockup logic)
        // In a real app, you'd fetch available slots via AJAX here
        const startHour = 9; // 9 AM
        const endHour = 17; // 5 PM
        
        let times = [
            "6:30 PM", "6:45 PM", "7:00 PM", "7:15 PM", "7:30 PM", "7:45 PM", 
            "8:00 PM", "8:15 PM", "8:30 PM", "8:45 PM" // Matching screenshot mostly
        ];

        times.forEach(t => {
            let btn = document.createElement('div');
            btn.className = 't-slot';
            btn.innerText = t;
            btn.onclick = function() { selectTime(t); };
            grid.appendChild(btn);
        });
    }

    // 4. Select Time & Go to Form
    function selectTime(timeStr) {
        document.getElementById('in_time').value = timeStr;
        
        // Transition Views
        document.getElementById('viewCalendar').classList.remove('active');
        document.getElementById('viewForm').classList.add('active');
        
        // Update Form Header
        document.getElementById('confirmTimeDisplay').innerText = selectedDateStr + " " + timeStr + " " + userTz;
    }

    // 5. Back Button
    function showCalendar() {
        document.getElementById('viewForm').classList.remove('active');
        document.getElementById('viewCalendar').classList.add('active');
    }

    // Run Init
    document.addEventListener('DOMContentLoaded', initDates);

    </script>
    <?php
    return ob_get_clean();
}