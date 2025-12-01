<?php
/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ASTRA_CHILD_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

/* =========================================================================
   MY CUSTOM CRM - ADVANCED FORM LOGIC (UPDATED)
   ========================================================================= */

/* 1. DATABASE SETUP */
function create_my_crm_database() {
    register_post_type( 'crm_entry', array(
        'labels' => array('name' => 'CRM Entries', 'singular_name' => 'CRM Entry', 'menu_name' => 'Private CRM'),
        'public' => false, 'show_ui' => true, 'capability_type' => 'post', 'supports' => array('title', 'custom-fields'),
    ));
}
add_action( 'init', 'create_my_crm_database' );

function register_archived_status() {
    register_post_status( 'archived', array('label' => 'Archived', 'public' => false, 'exclude_from_search' => true, 'show_in_admin_status_list' => true));
}
add_action( 'init', 'register_archived_status' );

/* 2. DASHBOARD (Shortcode: [crm_dashboard]) */
add_shortcode('crm_dashboard', 'show_crm_dashboard');
function show_crm_dashboard() {
    if ( !is_user_logged_in() ) return '<p style="text-align:center; padding:50px;">Please log in.</p>';

    // --- ACTION LOGIC (Delete, Archive, Restore) ---
    if ( isset($_GET['action']) && isset($_GET['entry_id']) ) {
        $tid = intval($_GET['entry_id']);
        
        // 1. DELETE
        if ( $_GET['action'] == 'delete' ) { 
            if(current_user_can('edit_posts')) {
                wp_delete_post($tid, true); 
                echo '<script>window.location.href="/crm/";</script>'; 
            }
        }
        
        // 2. ARCHIVE
        if ( $_GET['action'] == 'archive' ) { 
            wp_update_post(array('ID'=>$tid, 'post_status'=>'archived')); 
            echo '<script>window.location.href="/crm/";</script>'; 
        }
        
        // 3. RESTORE
        if ( $_GET['action'] == 'restore' ) { 
            wp_update_post(array('ID'=>$tid, 'post_status'=>'publish')); 
            echo '<script>window.location.href="/crm/?view=archived";</script>'; 
        }
    }

    // --- VIEW LOGIC ---
    $view = isset($_GET['view']) ? $_GET['view'] : 'active';
    $status = ($view == 'archived') ? 'archived' : 'publish';
    
    // Query the database
    $q = new WP_Query( array(
        'post_type' => 'crm_entry', 
        'post_status' => $status, 
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    // --- MAP VALUES TO LABELS ---
    // This array maps the DB value to the Full Text Label
    $stage_map = [
        'Working'                => '2. Working on getting commitment',
        'Shop Ownership'         => '2b. Shop ownership changing hands, on pause',
        'Actively Communication' => '2c. Actively communication to get final commitment',
        'Commitment Obtained'    => '3. Commitment obtained, not yet signed',
        'Signed'                 => '4. Signed',
        'Archive'                => 'Archive'
    ];

    ob_start(); 
    ?>
    <div style="font-family:'Inter', sans-serif; max-width:1550px; margin:40px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); overflow-x: auto;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h2 style="margin:0; font-weight:700;">Private: CRM</h2>
            <a href="/add-new-entry/" style="background:#000; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:600; font-size:14px;">+ Add New Entry</a>
        </div>

        <div style="margin-bottom:20px; border-bottom:2px solid #f0f0f0;">
            <a href="/crm/" style="display:inline-block; padding-bottom:15px; margin-right:20px; font-weight:600; text-decoration:none; color:<?php echo ($view=='active')?'#000':'#888'; ?>; border-bottom:2px solid <?php echo ($view=='active')?'#000':'transparent'; ?>;">Active Entries</a>
            <a href="/crm/?view=archived" style="display:inline-block; padding-bottom:15px; font-weight:600; text-decoration:none; color:<?php echo ($view=='archived')?'#000':'#888'; ?>; border-bottom:2px solid <?php echo ($view=='archived')?'#000':'transparent'; ?>;">Archived</a>
        </div>

        <table style="width:100%; border-collapse:collapse; min-width: 900px;">
            <thead>
                <tr style="background:#fafafa; text-align:left; color:#666; font-size:12px; text-transform:uppercase; border-bottom: 2px solid #eee;">
                    <th style="padding:15px;">Sales Stage</th>
                    <th style="padding:15px;">Business Name</th>
                    <th style="padding:15px;">Name</th>
                    <th style="padding:15px;">Email</th>
                    <th style="padding:15px;">Phone</th>
                    <th style="padding:15px;">Website</th>
                    <th style="padding:15px;">City</th>
                    <th style="padding:15px;">State</th>
                    <th style="padding:15px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $q->have_posts() ) : while ( $q->have_posts() ) : $q->the_post(); 
                $id = get_the_ID(); 
                $m = get_post_meta($id);
                
                // Helper to get meta values safely
                $val = function($key) use ($m) { return isset($m[$key][0]) ? $m[$key][0] : '-'; };
                
                // Combine Name
                $fname = isset($m['contact_first_name'][0]) ? $m['contact_first_name'][0] : '';
                $lname = isset($m['contact_last_name'][0]) ? $m['contact_last_name'][0] : '';
                $fullname = trim($fname . ' ' . $lname);
                if(empty($fullname)) $fullname = '-';

                // Resolve Full Text for Sales Stage
                $raw_stage = $val('sales_stage');
                $display_stage = isset($stage_map[$raw_stage]) ? $stage_map[$raw_stage] : $raw_stage;
            ?>
            <tr style="border-bottom:1px solid #f5f5f5; font-size:13px; color:#333;">
                <td style="padding:15px; font-weight:500;"><?php echo $display_stage; ?></td>
                
                <td style="padding:15px; font-weight:700; color:#000;"><?php the_title(); ?></td>
                
                <td style="padding:15px;"><?php echo $fullname; ?></td>
                
                <td style="padding:15px;">
                    <a href="mailto:<?php echo $val('email'); ?>" style="color:#007cba; text-decoration:none;"><?php echo $val('email'); ?></a>
                </td>
                
                <td style="padding:15px;"><?php echo $val('phone'); ?></td>
                
                <td style="padding:15px;">
                    <?php 
                        $site = $val('website_url');
                        if($site != '-') {
                            $link = (strpos($site, 'http') === 0) ? $site : 'https://' . $site;
                            echo '<a href="'.$link.'" target="_blank" style="color:#007cba; text-decoration:none;">'.$site.'</a>';
                        } else {
                            echo '-';
                        }
                    ?>
                </td>
                
                <td style="padding:15px;"><?php echo $val('city'); ?></td>
                
                <td style="padding:15px;"><?php echo $val('state'); ?></td>
                
                <td style="padding:15px; text-align:right; font-weight:600;">
                    <?php if ($view=='active'): ?>
                        <a href="/add-new-entry/?entry_id=<?php echo $id; ?>" style="color:#000; text-decoration:none; margin-right:8px;">Edit</a>
                        
                        <span style="color:#ddd;">|</span>
                        <a href="?entry_id=<?php echo $id; ?>&action=archive" style="color:#d97706; text-decoration:none; margin:0 8px;">Archive</a>
                        
                        <span style="color:#ddd;">|</span>
                        <a href="?entry_id=<?php echo $id; ?>&action=delete" style="color:#dc2626; text-decoration:none; margin-left:8px;" onclick="return confirm('Are you sure you want to permanently delete this entry?');">Delete</a>
                    
                    <?php else: ?>
                        <a href="?entry_id=<?php echo $id; ?>&action=restore" style="color:green; text-decoration:none;">Restore</a>
                        
                        <span style="color:#ddd;">|</span>
                        <a href="?entry_id=<?php echo $id; ?>&action=delete" style="color:#dc2626; text-decoration:none; margin-left:8px;" onclick="return confirm('Permanently delete?');">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="9" style="padding:40px; text-align:center; color:#999;">No entries found.</td></tr>
            <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
    <?php return ob_get_clean();
}

/* 3. THE FORM (Shortcode: [crm_entry_form]) */
add_shortcode('crm_entry_form', 'show_crm_form');
function show_crm_form() {
    
    // --- 1. SAVE & EMAIL LOGIC ---
    if ( isset($_POST['submit_crm_entry']) && is_user_logged_in() ) {
        
        // =================================================================
        // 📧 ADMIN EMAIL SETTINGS
        // =================================================================
        $admin_emails = array( 
            'amit.kumar@fenebrisindia.com',   // Admin 1
            // 'partner@example.com',         // Admin 2
        ); 
        // =================================================================

        // A. Validation
        $errors = [];
        $required_fields = ['business_name', 'sales_stage']; 
        
        if(isset($_POST['location_type']) && $_POST['location_type'] == 'POS Integrated') $required_fields[] = 'pos_system';
        if(isset($_POST['source']) && $_POST['source'] == 'Other') $required_fields[] = 'source_other';
        if(isset($_POST['sales_stage']) && in_array($_POST['sales_stage'], ['Signed', 'Archive'])) $required_fields[] = 'date_signed';
        if(isset($_POST['sales_stage']) && $_POST['sales_stage'] == 'Archive') $required_fields[] = 'archive_reason';
        
        foreach($required_fields as $rf) {
            if(empty($_POST[$rf])) $errors[] = "Missing required field: " . ucwords(str_replace('_',' ',$rf));
        }

        if(empty($errors)) {
            $eid = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
            $title = sanitize_text_field($_POST['business_name']);
            
            // B. Save Post
            $p = array('post_title'=>$title, 'post_status'=>'publish', 'post_type'=>'crm_entry');
            if($eid > 0){ 
                $p['ID'] = $eid; 
                wp_update_post($p); 
                $is_new = false;
            } else { 
                $eid = wp_insert_post($p); 
                $is_new = true;
            }

            // C. Save Meta Fields
            $fields = [
                'website_url', 'deck_stage', 'date_of_entry', 'referred_by', 'sales_stage', 
                'num_locations', 'location_type', 'deal_type_radio', 
                'commission_rate_val', 'monthly_fee_val', 'proposed_rate_val', 'monthly_cap',
                'source', 'contact_first_name', 'contact_last_name', 
                'phone', 'email', 'email_cc', 'city', 'state', 'zip', 'country', 
                'send_mockup_check', 'mockup_theme', 'client_username', 'client_password',
                'big_picture_stage', 'date_signed', 'archive_reason', 'archive_comments', 
                'bp_archived_details', 'pos_system', 'source_other'
            ];
            
            foreach($fields as $f) {
                $val = isset($_POST[$f]) ? sanitize_text_field($_POST[$f]) : '';
                update_post_meta($eid, $f, $val);
            }
            
            if(isset($_POST['notes_data_json'])) {
                update_post_meta($eid, 'crm_notes_json', json_decode(stripslashes($_POST['notes_data_json']), true));
            }

            // =================================================================
            // D. SEND EMAIL TO ADMINS
            // =================================================================
            
            $subject = "CRM Update: " . $title . " (" . ($is_new ? "New Entry" : "Updated") . ")";
            $domain = parse_url(get_site_url(), PHP_URL_HOST);
            // Fallback sender if not set by SMTP plugin
            $sender_email = 'wordpress@' . $domain;
            
            $headers = array();
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: CRM System <' . $sender_email . '>';
            
            // Build Email
            $msg  = "<html><body style='font-family: Arial, sans-serif; color: #333;'>";
            $msg .= "<h2 style='background: #000; color: #fff; padding: 10px;'>CRM Entry Details</h2>";
            $msg .= "<p><strong>Business:</strong> $title <br><strong>Status:</strong> " . ($is_new ? "New" : "Updated") . "</p>";
            
            $msg .= "<table style='border-collapse: collapse; width: 100%; max-width: 600px; margin-top: 20px;'>";
            $msg .= "<tr style='background:#f9f9f9; text-align:left;'><th style='padding:8px; border:1px solid #ddd;'>Field</th><th style='padding:8px; border:1px solid #ddd;'>Value</th></tr>";
            
            // --- LOGIC HELPERS FOR EMAIL ---
            $deal_type   = isset($_POST['deal_type_radio']) ? $_POST['deal_type_radio'] : '';
            $sales_stage = isset($_POST['sales_stage']) ? $_POST['sales_stage'] : '';
            $bp_stage    = isset($_POST['big_picture_stage']) ? $_POST['big_picture_stage'] : '';
            $loc_type    = isset($_POST['location_type']) ? $_POST['location_type'] : '';
            $source_val  = isset($_POST['source']) ? $_POST['source'] : '';
            $do_mockup   = isset($_POST['send_mockup_check']) && $_POST['send_mockup_check'] === 'yes';

            $relevant_stages_for_bp = ['Commitment Obtained', 'Signed', 'Archive'];

            foreach($fields as $f) {
                // Skip if field is not submitted or empty
                if(!isset($_POST[$f]) || $_POST[$f] === '') continue;

                // --- 1. FILTER: Deal Type Logic ---
                if ($f === 'commission_rate_val' && $deal_type !== 'Commission') continue;
                if ($f === 'proposed_rate_val'   && $deal_type !== 'Daily_2') continue;
                if ($f === 'monthly_fee_val'     && $deal_type !== 'Flat') continue;

                // --- 2. FILTER: Big Picture Stage Logic ---
                // Only show BP Stage if Sales Stage is relevant
                if ($f === 'big_picture_stage' && !in_array($sales_stage, $relevant_stages_for_bp)) continue;

                // --- 3. FILTER: Big Picture Archive Details ---
                // Only show BP Archive Details if BP Stage is actually 'Archive' (and visible)
                if ($f === 'bp_archived_details') {
                    if (!in_array($sales_stage, $relevant_stages_for_bp) || $bp_stage !== 'Archive') continue;
                }

                // --- 4. FILTER: Mockup Details ---
                if (in_array($f, ['mockup_theme', 'client_username', 'client_password']) && !$do_mockup) continue;

                // --- 5. FILTER: POS System (Double Check) ---
                if ($f === 'pos_system' && $loc_type !== 'POS Integrated') continue;

                // --- 6. FILTER: Source Other (Double Check) ---
                if ($f === 'source_other' && $source_val !== 'Other') continue;

                // --- OUTPUT VALID FIELD ---
                $nice_name = ucwords(str_replace(['_', 'val'], [' ', ''], $f)); 
                $nice_val = sanitize_text_field($_POST[$f]);
                $msg .= "<tr><td style='padding:8px; border:1px solid #ddd;'>$nice_name</td><td style='padding:8px; border:1px solid #ddd;'>$nice_val</td></tr>";
            }
            $msg .= "</table>";
            
            // Include Notes in Email
            if(isset($_POST['notes_data_json'])) {
                $notes_arr = json_decode(stripslashes($_POST['notes_data_json']), true);
                if(!empty($notes_arr)) {
                    $msg .= "<h3>Recent Notes</h3><ul>";
                    foreach($notes_arr as $n) {
                        $msg .= "<li><strong>" . $n['date'] . ":</strong> " . $n['text'] . "</li>";
                    }
                    $msg .= "</ul>";
                }
            }
            $msg .= "<p><a href='" . home_url('/add-new-entry/?entry_id='.$eid) . "'>View in CRM</a></p></body></html>";

            // Send
            $mail_sent = wp_mail( $admin_emails, $subject, $msg, $headers );
            
            // Redirect with Status
            $status_msg = $mail_sent ? 'success' : 'mail_error';

            // E. Client Mockup Email (Optional)
            if ( isset($_POST['send_mockup_check']) && $_POST['send_mockup_check']=='yes' ) {
                $to = sanitize_email($_POST['email']);
                $cc = isset($_POST['email_cc']) ? sanitize_email($_POST['email_cc']) : '';

                $u = isset($_POST['client_username']) ? sanitize_text_field($_POST['client_username']) : '';
                $pw = isset($_POST['client_password']) ? sanitize_text_field($_POST['client_password']) : '';
                $theme = isset($_POST['mockup_theme']) ? sanitize_text_field($_POST['mockup_theme']) : '';
                
                // 1. Prepare Headers (CC)
                $client_headers = array();
                if ( !empty($cc) ) {
                    $client_headers[] = 'Cc: ' . $cc;
                }

                // 2. Prepare Attachments based on selection
                $attachments = array();
                
                // FIX: Use relative paths from the uploads folder, NOT the full URL.
                if ( $theme === 'Light' ) {
                    $attachments[] = WP_CONTENT_DIR . '/uploads/2025/12/demo-light.webp'; 
                } elseif ( $theme === 'Dark' ) {
                    $attachments[] = WP_CONTENT_DIR . '/uploads/2025/12/demo-dark.webp'; 
                }

                // 3. Send Email
                $msg_body = "Hello,\n\nHere are your login details:\nUser: $u\nPass: $pw\n\n(See attached mockup)";
                
                wp_mail( $to, "Your Theme Mockup", $msg_body, $client_headers, $attachments );
            }
            
            echo '<script>window.location.href="/crm/?msg='.$status_msg.'";</script>'; exit;
        } else {
             echo '<div style="background:#ffe6e6; padding:15px; border:1px solid red; margin-bottom:20px; color:#c00;">'.implode('<br>', $errors).'</div>';
        }
    }

    // --- 2. LOAD DATA ---
    $mode = 'add'; $eid = 0; $db = []; $notes = []; $title = '';
    
    if(isset($_GET['entry_id'])) {
        $mode = 'edit'; 
        $eid = intval($_GET['entry_id']); 
        $db = get_post_meta($eid); 
        $notes = get_post_meta($eid,'crm_notes_json',true) ?: [];
        $post_obj = get_post($eid);
        if($post_obj) $title = $post_obj->post_title;
    }
    
    // --- FIX: Use Anonymous Function to prevent Redeclaration Error ---
    $gv = function($k, $d){ return isset($d[$k][0]) ? $d[$k][0] : ''; };
    
    $date_val = $gv('date_of_entry', $db) ?: date('Y-m-d');
    $date_signed_val = $gv('date_signed', $db) ?: date('Y-m-d');

    ob_start(); 
    
    // Status Message Display
    if(isset($_GET['msg'])) {
        if($_GET['msg'] == 'success') echo '<div style="background:#d4edda; color:#155724; padding:15px; margin:20px 0; border-radius:5px; text-align:center;">✅ <strong>Success!</strong> Entry updated and Admin Notification sent.</div>';
        elseif($_GET['msg'] == 'mail_error') echo '<div style="background:#f8d7da; color:#721c24; padding:15px; margin:20px 0; border-radius:5px; text-align:center;">⚠️ <strong>Notice:</strong> Entry saved, but Email failed to send. Check SMTP settings.</div>';
    }
    ?>
    <style>
        .crm-wrap { font-family:'Inter', sans-serif; background:#fff; max-width:850px; margin:40px auto; padding:50px; border:1px solid #e0e0e0; box-shadow:0 5px 20px rgba(0,0,0,0.03); }
        .crm-title { text-align:center; font-size:26px; font-weight:700; margin-bottom:40px; color:#111; }
        .crm-row { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:25px; }
        .crm-full { grid-column:1 / -1; margin-bottom:25px; }
        .crm-label { display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; }
        .crm-input, .crm-select, .crm-textarea { width:100%; padding:12px; border:1px solid #ccc; border-radius:4px; font-size:14px; box-sizing:border-box; background: #fff; height: 45px; }
        .crm-textarea { height: 100px; font-family:'Inter'; }
        .crm-input:focus, .crm-select:focus, .crm-textarea:focus { border-color:#000; outline:none; }
        .conditional-box { background:#f8f9fa; border-left:4px solid #000; padding:20px; margin-top:15px; margin-bottom:20px; display:none; }
        .notes-area { background:#fafafa; border:1px dashed #ccc; padding:20px; border-radius:6px; }
        .note-controls { display:flex; gap:10px; align-items:flex-end; display:none; }
        .note-list { list-style:none; padding:0; margin-top:15px; }
        .note-item { background:#fff; border:1px solid #e5e5e5; padding:12px; margin-bottom:8px; border-radius:4px; display:flex; justify-content:space-between; align-items:center; }
        .btn-txt { border:none; background:none; cursor:pointer; font-weight:600; font-size:13px; padding:0; }
        .btn-add { background:#000; color:#fff; padding:10px 20px; border-radius:4px; }
        .btn-del { color:#cc0000; text-decoration:underline; }
        .mockup-box { background:#f4f8fb; border:1px solid #dceefc; padding:25px; border-radius:6px; margin-top:10px; }
        .theme-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        .t-card { border:2px solid transparent; padding:10px; cursor:pointer; text-align:center; background:#fff; }
        .t-card img { width:100%; border-radius:4px; opacity:0.6; }
        .t-card.sel { border-color:#007cba; } .t-card.sel img { opacity:1; }
        .sub-btn { background:#000; color:#fff; width:100%; padding:16px; border:none; font-size:16px; font-weight:700; cursor:pointer; margin-top:30px; border-radius:4px; }
    </style>

    <form method="post" id="crmForm" novalidate>
        <?php if($mode=='edit') echo '<input type="hidden" name="entry_id" value="'.$eid.'">'; ?>

        <div class="crm-row">
            <div class="crm-full">
                <label class="crm-label">Business Name *</label>
                <input type="text" name="business_name" class="crm-input" value="<?php echo esc_attr($title); ?>" required>
            </div>
            <div class="crm-full">
                <label class="crm-label">Website URL</label>
                <input type="text" name="website_url" class="crm-input" value="<?php echo $gv('website_url',$db); ?>">
            </div>
        </div>

        <div class="crm-full notes-area">
            <label style="cursor:pointer; font-weight:700;">
                <input type="checkbox" id="noteTog" style="transform:scale(1.2); margin-right:8px;"> Add Note
            </label>
            <div id="noteInputs" class="note-controls" style="margin-top:15px;">
                <div style="flex:1;">
                    <label class="crm-label">Date</label>
                    <input type="date" id="noteDate" class="crm-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div style="flex:3;">
                    <label class="crm-label">Note Content</label>
                    <input type="text" id="noteTxt" class="crm-input" placeholder="Type note here...">
                </div>
                <div>
                    <button type="button" id="addNoteBtn" class="btn-txt btn-add">Add Note</button>
                </div>
            </div>
            <ul id="noteList" class="note-list"></ul>
            <input type="hidden" name="notes_data_json" id="jsonNotes">
        </div>

        <div style="height:1px; background:#eee; margin:30px 0;"></div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Deck Stage (1-10)</label>
                <select name="deck_stage" class="crm-select">
                    <option value="">Select Stage</option>
                    <?php for($i=1;$i<=10;$i++) { $s=($gv('deck_stage',$db)==$i)?'selected':''; echo "<option value='$i' $s>$i</option>"; } ?>
                </select>
            </div> 
        </div>
        <div class="crm-full">
            <div>
                <label class="crm-label">Date of Entry</label>
                <input type="date" name="date_of_entry" class="crm-input" value="<?php echo $date_val; ?>">
            </div>
        </div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Referred By</label>
                <input type="text" name="referred_by" class="crm-input" value="<?php echo $gv('referred_by',$db); ?>">
            </div>
        </div>
        
        <div class="crm-full">
            <div>
                <label class="crm-label">Sales Stage *</label>
                <select name="sales_stage" id="sales_stage" class="crm-select" required onchange="runLogic()">
                    <option value="">Select Stage</option>
                    <option value="Working" <?php selected($gv('sales_stage',$db),'Working'); ?>>2. Working on getting commitment</option>
                    <option value="Shop Ownership" <?php selected($gv('sales_stage',$db),'Shop Ownership'); ?>>2b. Shop ownership changing hands, on pause</option>
                    <option value="Actively Communication" <?php selected($gv('sales_stage',$db),'Actively Communication'); ?>>2c. Actively communication to get final commitment</option>
                    <option value="Commitment Obtained" <?php selected($gv('sales_stage',$db),'Commitment Obtained'); ?>>3. Commitment obtained, not yet signed</option>
                    <option value="Signed" <?php selected($gv('sales_stage',$db),'Signed'); ?>>4. Signed</option>
                    <option value="Archive" <?php selected($gv('sales_stage',$db),'Archive'); ?>>Archive</option>
                </select>
            </div>
        </div>

        <div id="wrap_date_signed" class="conditional-box">
            <label class="crm-label">Date Agreement Was Signed *</label>
            <input type="date" name="date_signed" class="crm-input logic-req" value="<?php echo $date_signed_val; ?>">
        </div>

        <div id="wrap_archive_details" class="conditional-box" style="background:#fff4f4; border-color:#cc0000;">
            <label class="crm-label">Archive – Details *</label>
            <select name="archive_reason" class="crm-select logic-req">
                <option value="">Select Reason</option>
                <option value="Sales Lost" <?php selected($gv('archive_reason',$db),'Sales Lost'); ?>>Sales Lost</option>
                <option value="Duplicate" <?php selected($gv('archive_reason',$db),'Duplicate'); ?>>Duplicate</option>
                <option value="Not Interested" <?php selected($gv('archive_reason',$db),'Not Interested'); ?>>Not Interested</option>
                <option value="Testing" <?php selected($gv('archive_reason',$db),'Testing'); ?>>Testing</option>
            </select>
            <br><br>
            <label class="crm-label">Archive – Comments</label>
            <textarea name="archive_comments" class="crm-textarea"><?php echo $gv('archive_comments',$db); ?></textarea>
        </div>

        <div id="wrap_big_picture" class="conditional-box" style="background:#eefaff; border-color:#007cba;">
            <label class="crm-label">Big Picture Stage (for signed deals)</label>
            <select name="big_picture_stage" id="bp_stage" class="crm-select" onchange="runLogic()">
                <option value="">Select Option</option>
                <option value="In Production" <?php selected($gv('big_picture_stage',$db),'In Production'); ?>>1. In Production</option>
                <option value="App link sent" <?php selected($gv('big_picture_stage',$db),'App link sent'); ?>>2. App link sent, in password protected state</option>
                <option value="Live" <?php selected($gv('big_picture_stage',$db),'Live'); ?>>3. Live</option>
                <option value="Archive" <?php selected($gv('big_picture_stage',$db),'Archive'); ?>>Archive</option>
            </select>

            <div id="wrap_bp_archive_details" style="margin-top:15px; padding-left:15px; border-left:2px solid #ccc; display:none;">
                <label class="crm-label">Archived Details</label>
                <select name="bp_archived_details" class="crm-select">
                    <option value="">Select Detail</option>
                    <option value="Merchant request" <?php selected($gv('bp_archived_details',$db),'Merchant request'); ?>>Merchant request app to be taken down</option>
                    <option value="Business closed" <?php selected($gv('bp_archived_details',$db),'Business closed'); ?>>Business closed down permanently</option>
                </select>
            </div>
        </div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Number of Locations</label>
                <select name="num_locations" class="crm-select">
                    <option value="">Select Number</option>
                    <?php for($i=1; $i<=100; $i++) { $sel = ($gv('num_locations',$db) == $i) ? 'selected' : ''; echo "<option value='$i' $sel>$i</option>"; } ?>
                    <option value="More than 100" <?php selected($gv('num_locations',$db),'More than 100'); ?>>More than 100</option>
                </select>
            </div>
        </div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Type</label>
                <select name="location_type" id="loc_type" class="crm-select" onchange="runLogic()">
                    <option value="">Select Type</option>
                    <option value="Freestanding" <?php selected($gv('location_type',$db),'Freestanding'); ?>>Freestanding</option>
                    <option value="POS Integrated" <?php selected($gv('location_type',$db),'POS Integrated'); ?>>POS Integrated</option>
                </select>
            </div>
            
            <div id="wrap_pos_select" class="conditional-box">
                <label class="crm-label">POS system we will be integrating with *</label>
                <select name="pos_system" class="crm-select logic-req">
                    <option value="">Select POS</option>
                    <option value="Square" <?php selected($gv('pos_system',$db),'Square'); ?>>Square</option>
                    <option value="DripOS" <?php selected($gv('pos_system',$db),'DripOS'); ?>>DripOS</option>
                    <option value="DiamondScan" <?php selected($gv('pos_system',$db),'DiamondScan'); ?>>DiamondScan</option>
                    <option value="Toast" <?php selected($gv('pos_system',$db),'Toast'); ?>>Toast</option>
                    <option value="Linga" <?php selected($gv('pos_system',$db),'Linga'); ?>>Linga</option>
                    <option value="Shopkeep" <?php selected($gv('pos_system',$db),'Shopkeep'); ?>>Shopkeep</option>
                    <option value="Clover" <?php selected($gv('pos_system',$db),'Clover'); ?>>Clover</option>
                    <option value="Katalyst" <?php selected($gv('pos_system',$db),'Katalyst'); ?>>Katalyst</option>
                    <option value="Upserve" <?php selected($gv('pos_system',$db),'Upserve'); ?>>Upserve</option>
                    <option value="AlphaPOS" <?php selected($gv('pos_system',$db),'AlphaPOS'); ?>>AlphaPOS</option>
                </select>
            </div>
        </div>

        <div class="crm-full">
            <label class="crm-label">Deal Type Proposed</label>
            <div class="radio-group" style="margin-top:10px;">
                <label><input type="radio" name="deal_type_radio" value="Commission" onclick="toggleDeal(1)" <?php checked($gv('deal_type_radio',$db),'Commission'); ?>> Commission</label> <br>
                <label><input type="radio" name="deal_type_radio" value="Daily_1" onclick="toggleDeal(0)" <?php checked($gv('deal_type_radio',$db),'Daily_1'); ?>> $4-$10 Pricing (Daily per location)</label><br>
                <label><input type="radio" name="deal_type_radio" value="Flat" onclick="toggleDeal(3)" <?php checked($gv('deal_type_radio',$db),'Flat'); ?>> Flat Monthly (per location)</label><br>
                <label><input type="radio" name="deal_type_radio" value="Daily_2" onclick="toggleDeal(2)" <?php checked($gv('deal_type_radio',$db),'Daily_2'); ?>> Per Order</label>
            </div>
            
            <div id="box_comm" style="display:none; margin-top:10px;">
                <label class="crm-label">Proposed Commission Rate</label>
                <input type="text" name="commission_rate_val" class="crm-input" value="<?php echo $gv('commission_rate_val',$db) ? $gv('commission_rate_val',$db) : '6%'; ?>">
            </div>

            <div id="box_daily" style="display:none; margin-top:10px;">
                <label class="crm-label">Proposed Per Order Fee</label>
                <input type="text" name="proposed_rate_val" class="crm-input" value="<?php echo $gv('proposed_rate_val',$db) ? $gv('proposed_rate_val',$db) : '.25'; ?>">
            </div>

            <div id="box_flat" style="display:none; margin-top:10px;">
                <label class="crm-label">Monthly Fee</label>
                <select name="monthly_fee_val" class="crm-select">
                    <option value="">Select Fee</option>
                    <?php 
                    $fees = ['299.99', '349.99', '399.99', '449.99', '499.99', '599.99']; 
                    foreach($fees as $f) { 
                        $db_clean = str_replace('$', '', $gv('monthly_fee_val',$db));
                        $sel = ($db_clean == $f) ? 'selected' : ''; 
                        echo "<option value='$f' $sel>$$f</option>"; 
                    } 
                    ?>
                </select>
            </div>
        </div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Monthly Cap</label>
                <input type="text" name="monthly_cap" class="crm-input" placeholder="$" value="<?php echo $gv('monthly_cap',$db); ?>">
            </div>
        </div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Source (Where did we meet this merchant?)</label>
                <select name="source" id="source_sel" class="crm-select" onchange="runLogic()">
                    <option value="">Select Source</option>
                    <option value="Odeko" <?php selected($gv('source',$db),'Odeko'); ?>>Odeko</option>
                    <option value="Joe Coffee" <?php selected($gv('source',$db),'Joe Coffee'); ?>>Joe Coffee</option>
                    <option value="Keys" <?php selected($gv('source',$db),'Keys'); ?>>Keys to the Shop</option>
                    <option value="Facebook Group" <?php selected($gv('source',$db),'Facebook Group'); ?>>Facebook Coffee Business Owner Group</option>
                    <option value="Coffee USA" <?php selected($gv('source',$db),'Coffee USA'); ?>>Coffee Shop List USA</option>
                    <option value="Cold Call" <?php selected($gv('source',$db),'Cold Call'); ?>>Cold Call</option>
                    <option value="Facebook" <?php selected($gv('source',$db),'Facebook'); ?>>Facebook</option>
                    <option value="LinkedIn" <?php selected($gv('source',$db),'LinkedIn'); ?>>LinkedIn</option>
                    <option value="Referral" <?php selected($gv('source',$db),'Referral'); ?>>Referral</option>
                    <option value="Roast Magazine" <?php selected($gv('source',$db),'Roast Magazine'); ?>>Roast Magazine</option>
                    <option value="Dailycoffeenews" <?php selected($gv('source',$db),'Dailycoffeenews'); ?>>Dailycoffeenews.com</option>
                    <option value="Google Search" <?php selected($gv('source',$db),'Google Search'); ?>>Google</option>
                    <option value="Other" <?php selected($gv('source',$db),'Other'); ?>>Other</option>
                </select>
            </div>
            <div id="wrap_source_other" class="conditional-box">
                <label class="crm-label">"Other" Source *</label>
                <input type="text" name="source_other" class="crm-input logic-req" value="<?php echo $gv('source_other',$db); ?>">
            </div>
        </div>

        <div class="mockup-box">
            <label style="font-weight:700; cursor:pointer;">
                <input type="checkbox" id="mockCheck" name="send_mockup_check" value="yes" style="transform:scale(1.3); margin-right:10px;" <?php checked($gv('send_mockup_check',$db),'yes'); ?>> 
                Send Mockup Images
            </label>
            <div id="mockOpts" style="display:none; margin-top:20px;">
                <input type="hidden" name="mockup_theme" id="thmInput" value="<?php echo $gv('mockup_theme',$db); ?>">
                <div class="theme-grid">
                    <div class="t-card" onclick="selTheme('Light',this)">
                        <img src="https://via.placeholder.com/300x150?text=Light+Theme" alt="Light">
                        <strong>Light Theme</strong>
                    </div>
                    <div class="t-card" onclick="selTheme('Dark',this)">
                        <img src="https://via.placeholder.com/300x150?text=Dark+Theme" alt="Dark">
                        <strong>Dark Theme</strong>
                    </div>
                </div>
                <div class="crm-full">
                    <div><label class="crm-label">Username</label><input type="text" name="client_username" class="crm-input" value="<?php echo $gv('client_username',$db); ?>"></div>
                </div>
                <div class="crm-full">
                    <div><label class="crm-label">Password</label><input type="text" name="client_password" class="crm-input" value="<?php echo $gv('client_password',$db); ?>"></div>
                </div>
            </div>
        </div>

        <div style="height:1px; background:#eee; margin:30px 0;"></div>
        <div style="font-weight:700; margin-bottom:15px; font-size:16px;">CRM Contact and Key Data</div>

        <div class="crm-row">
            <div class="crm-full">
                <label class="crm-label">Contact First Name</label>
                <input type="text" name="contact_first_name" class="crm-input" value="<?php echo $gv('contact_first_name',$db); ?>">
            </div>
            <div class="crm-full"> 
                <label class="crm-label">Contact Last Name</label>
                <input type="text" name="contact_last_name" class="crm-input" value="<?php echo $gv('contact_last_name',$db); ?>">
            </div>
        </div>
        
        <div class="crm-full"><div><label class="crm-label">Phone</label><input type="text" name="phone" class="crm-input" value="<?php echo $gv('phone',$db); ?>"></div></div>
        <div class="crm-full"><div><label class="crm-label">Email</label><input type="email" name="email" class="crm-input" value="<?php echo $gv('email',$db); ?>"></div></div>
        <div class="crm-full"><div><label class="crm-label">Email (CC)</label><input type="email" name="email_cc" class="crm-input" value="<?php echo $gv('email_cc',$db); ?>"></div></div>

        <div class="crm-full">
            <div>
                <label class="crm-label">Country</label>
                <select name="country" id="countrySel" class="crm-select">
                    <option value="">Select Country</option>
                    <option value="USA">USA</option>
                    <option value="Canada">Canada</option>
                    <option value="Australia">Australia</option>
                    <option value="England">England</option>
                </select>
            </div><br>
            <div class="crm-full"><label class="crm-label">City</label><input type="text" name="city" class="crm-input" value="<?php echo $gv('city',$db); ?>"></div>
            <div class="crm-full">
                <label class="crm-label" id="stateLabel">State</label>
                <select name="state" id="stateSel" class="crm-select">
                    <option value="">Select State</option>
                </select>
            </div>
        </div>
        <div class="crm-full">
            <label class="crm-label">Postal Code</label>
            <input type="text" name="zip" class="crm-input" value="<?php echo $gv('zip',$db); ?>">
        </div>

        <button type="submit" name="submit_crm_entry" class="sub-btn"><?php echo ($mode=='edit')?'Update Entry':'Update'; ?></button>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        
        const form = document.getElementById('crmForm');

        // --- VALIDATOR LOGIC ---
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let firstError = null;
            
            const requiredInputs = form.querySelectorAll('input[required], select[required], .logic-req');
            
            requiredInputs.forEach(input => {
                if(input.offsetParent !== null && input.value.trim() === '') {
                    input.style.borderColor = 'red';
                    isValid = false;
                    if(!firstError) firstError = input;
                } else {
                    input.style.borderColor = '#ccc';
                }
            });

            if(!isValid) {
                e.preventDefault();
                alert('Please fill out all required fields marked in red.');
                if(firstError) firstError.focus();
            }
        });

        // --- VISIBILITY TOGGLE HELPER ---
        window.toggleVis = function(elId, show) {
            const el = document.getElementById(elId);
            if(!el) return;
            if(show) {
                el.style.display = 'block';
                const inputs = el.querySelectorAll('.logic-req');
                inputs.forEach(i => i.setAttribute('required', 'true'));
            } else {
                el.style.display = 'none';
                const inputs = el.querySelectorAll('.logic-req');
                inputs.forEach(i => {
                    i.removeAttribute('required'); 
                    i.value = ''; 
                }); 
            }
        }

        // --- MASTER LOGIC RUNNER ---
        window.runLogic = function() {
            const stage = document.getElementById('sales_stage').value;
            const bpStage = document.getElementById('bp_stage').value;
            const locType = document.getElementById('loc_type').value;
            const source = document.getElementById('source_sel').value;

            // 1. Sales Stage
            const showBP = ['Commitment Obtained', 'Signed', 'Archive'].includes(stage);
            toggleVis('wrap_big_picture', showBP);

            const showDate = ['Signed', 'Archive'].includes(stage);
            toggleVis('wrap_date_signed', showDate);

            const showArch = (stage === 'Archive');
            toggleVis('wrap_archive_details', showArch);

            // 2. BP Archive
            const showBpArch = (showBP && bpStage === 'Archive');
            const bpWrap = document.getElementById('wrap_bp_archive_details');
            bpWrap.style.display = showBpArch ? 'block' : 'none';

            // 3. POS
            toggleVis('wrap_pos_select', (locType === 'POS Integrated'));

            // 4. Source Other
            toggleVis('wrap_source_other', (source === 'Other'));
        }

        // --- DEAL TYPE LOGIC ---
        window.toggleDeal = function(type) {
            document.getElementById('box_comm').style.display = (type===1) ? 'block' : 'none';
            document.getElementById('box_daily').style.display = (type===2) ? 'block' : 'none';
            document.getElementById('box_flat').style.display = (type===3) ? 'block' : 'none';
        }
        
        const savedDeal = "<?php echo $gv('deal_type_radio',$db); ?>";
        if(savedDeal === 'Commission') toggleDeal(1);
        else if(savedDeal === 'Daily_2') toggleDeal(2);
        else if(savedDeal === 'Flat') toggleDeal(3);

        // --- MOCKUP LOGIC ---
        const mCheck = document.getElementById('mockCheck');
        const mBox = document.getElementById('mockOpts');
        mCheck.addEventListener('change', function(){ mBox.style.display = this.checked ? 'block' : 'none'; });
        if(mCheck.checked) mBox.style.display='block';

        window.selTheme = function(name, el) {
            document.getElementById('thmInput').value=name;
            document.querySelectorAll('.t-card').forEach(c => c.classList.remove('sel'));
            el.classList.add('sel');
        }
        const curTheme = "<?php echo $gv('mockup_theme',$db); ?>";
        const cards = document.querySelectorAll('.t-card');
        if(curTheme==='Light') cards[0].classList.add('sel');
        if(curTheme==='Dark') cards[1].classList.add('sel');

        // --- NOTES LOGIC ---
        const nTog = document.getElementById('noteTog');
        const nInputs = document.getElementById('noteInputs');
        const nList = document.getElementById('noteList');
        const nJson = document.getElementById('jsonNotes');
        let notes = <?php echo json_encode($notes); ?> || [];

        function renderNotes(){
            nList.innerHTML = '';
            notes.forEach((n,i) => {
                let li = document.createElement('li');
                li.className = 'note-item';
                li.innerHTML = `<span><b>${n.date}:</b> ${n.text}</span> <button type="button" class="btn-txt btn-del" onclick="delNote(${i})">Delete</button>`;
                nList.appendChild(li);
            });
            nJson.value = JSON.stringify(notes);
        }
        
        nTog.addEventListener('change', function(){ nInputs.style.display = this.checked ? 'flex' : 'none'; });
        
        document.getElementById('addNoteBtn').addEventListener('click', function(){
            const d = document.getElementById('noteDate').value;
            const t = document.getElementById('noteTxt').value;
            if(!t) return;
            notes.push({date:d, text:t});
            document.getElementById('noteTxt').value=''; 
            renderNotes();
        });

        window.delNote = function(i) {
            if(confirm('Delete note?')) { notes.splice(i,1); renderNotes(); }
        }
        renderNotes();

        // --- COUNTRY / STATE LOGIC ---
        const data = {
            "USA": ["Alabama","Alaska","Arizona","California","Florida","New York","Texas","Washington"],
            "Canada": ["Alberta","British Columbia","Manitoba","Ontario","Quebec"],
            "Australia": ["New South Wales","Queensland","Victoria","Western Australia"],
            "England": ["Bedfordshire","Cambridgeshire","Essex","Hampshire","Kent","London","Yorkshire"]
        };
        const cSel = document.getElementById('countrySel');
        const sSel = document.getElementById('stateSel');
        const sLbl = document.getElementById('stateLabel');
        const savedCountry = "<?php echo $gv('country',$db); ?>";
        const savedState = "<?php echo $gv('state',$db); ?>";

        function updateStates() {
            const c = cSel.value;
            sSel.innerHTML = '<option value="">Select Option</option>'; 
            
            if(c === 'Canada') sLbl.innerText = 'Province';
            else if(c === 'England') sLbl.innerText = 'County';
            else sLbl.innerText = 'State';

            if(data[c]) {
                data[c].forEach(s => {
                    let opt = document.createElement('option');
                    opt.value = s;
                    opt.innerText = s;
                    if(s === savedState && c === savedCountry) opt.selected = true;
                    sSel.appendChild(opt);
                });
            }
        }

        cSel.addEventListener('change', updateStates);
        if(savedCountry) {
            cSel.value = savedCountry;
            updateStates();
        }

        runLogic();
    });
    </script>
    <?php return ob_get_clean();
}