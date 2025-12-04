<?php
/* =========================================================================
   DECK VIEW FEATURE (UI MATCHING CRM DASHBOARD)
   Shortcode: [crm_deck_view]
   Description: Displays entries that have a 'Deck Stage' (1-10) selected.
   ========================================================================= */

add_shortcode('crm_deck_view', 'show_crm_deck_view');

function show_crm_deck_view() {
    // 1. Security Check
    if ( !is_user_logged_in() ) {
        return '<p style="text-align:center; padding:50px;">Please log in to view the Deck.</p>';
    }

    // --- ACTION LOGIC (Delete, Archive) ---
    // Kept similar to main CRM so you can manage entries directly from the Deck
    if ( isset($_GET['action']) && isset($_GET['entry_id']) ) {
        $tid = intval($_GET['entry_id']);
        
        // 1. DELETE
        if ( $_GET['action'] == 'delete' ) { 
            if(current_user_can('edit_posts')) {
                wp_delete_post($tid, true); 
                // Reload current page
                echo '<script>window.location.href = window.location.pathname;</script>'; 
            }
        }
        
        // 2. ARCHIVE
        if ( $_GET['action'] == 'archive' ) { 
            wp_update_post(array('ID'=>$tid, 'post_status'=>'archived')); 
            echo '<script>window.location.href = window.location.pathname;</script>'; 
        }
    }

    // 2. Query Database
    // Strictly look for entries where 'deck_stage' is NOT empty
    $args = array(
        'post_type'      => 'crm_entry', 
        'post_status'    => 'publish', // Showing active deals in Deck
        'posts_per_page' => -1,        
        'meta_query'     => array(
            array(
                'key'     => 'deck_stage',
                'value'   => '',
                'compare' => '!=',     // Logic: Value is NOT empty
            ),
        ),
        'meta_key'       => 'deck_stage', 
        'orderby'        => array( 
            'meta_value_num' => 'DESC', // Sort by Deck Stage (10 at top)
            'date'           => 'DESC'  // Secondary sort by date
        )
    );
    
    $q = new WP_Query( $args );

    // --- MAP VALUES TO LABELS (Same as Main CRM) ---
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
    <div style="font-family:'Inter', sans-serif; font-size: 14px; max-width:1550px; margin:40px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); overflow-x: auto;">
		
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
            <h2 style="margin:0; font-weight:700;">Private: CRM</h2>
        </div>
		
		<div style="margin: 20px 0">
            <a href="/add-new-entry/" style="padding:10px 0; text-decoration:none; border-radius:6px; font-weight:500; font-size:22px;">Add New Entry</a>
        </div>
        
        <div style="margin: 20px 0">
            <a href="/crm/" style="padding:10px 0; text-decoration:none; border-radius:6px; font-weight:500; font-size:14px;">Go to CRM</a>
        </div>

        <div style="margin-bottom:20px; border-bottom:2px solid #f0f0f0;">
            <a href="/crm/" style="display:inline-block; padding-bottom:15px; margin-right:20px; font-weight:600; text-decoration:none; color:#888; border-bottom:2px solid transparent;">Active Entries</a>
            <span style="display:inline-block; padding-bottom:15px; margin-right:20px; font-weight:600; text-decoration:none; color:#000; border-bottom:2px solid #000;">Deck View</span>
            <a href="/crm/?view=archived" style="display:inline-block; padding-bottom:15px; font-weight:600; text-decoration:none; color:#888; border-bottom:2px solid transparent;">Archived</a>
        </div>

        <table style="width:100%; border-collapse:collapse; min-width: 900px;">
            <thead>
                <tr style="background:#fafafa; text-align:left; color:#000000; font-size:12px; text-transform:uppercase; border-bottom: 2px solid #eee;">
                    <th style="padding:5px; text-align:center;">DS</th>
                    <th style="padding:5px;">Sales Stage</th>
                    <th style="padding:5px;">Business Name</th>
                    <th style="padding:5px;">Name</th>
                    <th style="padding:5px;">Email</th>
                    <th style="padding:5px;">Phone</th>
                    <th style="padding:5px;">Website</th>
                    <th style="padding:5px;">City</th>
                    <th style="padding:5px;">State</th>
                    <th style="padding:5px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $q->have_posts() ) : while ( $q->have_posts() ) : $q->the_post(); 
                $id = get_the_ID(); 
                $m = get_post_meta($id);
                
                // Helper to get meta values safely
                $val = function($key) use ($m) { return isset($m[$key][0]) ? $m[$key][0] : '-'; };
                
                // Deck Badge Logic
                $deck_stage = $val('deck_stage');
                $badge_bg = '#333';
                if($deck_stage >= 8) $badge_bg = '#166534'; // Green
                elseif($deck_stage >= 5) $badge_bg = '#ca8a04'; // Yellow
                else $badge_bg = '#dc2626'; // Red

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
                
                <td style="padding:5px; text-align:center;">
                    <span style="background:<?php echo $badge_bg; ?>; color:#fff; padding:4px 8px; border-radius:50%; font-weight:700; font-size:12px; width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center;">
                        <?php echo $deck_stage; ?>
                    </span>
                </td>

                <td style="padding:5px; font-weight:500;"><?php echo $display_stage; ?></td>
                
                <td style="padding:5px; font-weight:700; color:#000;"><?php the_title(); ?></td>
                
                <td style="padding:5px;"><?php echo $fullname; ?></td>
                
                <td style="padding:5px;">
                    <a href="mailto:<?php echo $val('email'); ?>" style="color:#007cba; text-decoration:none;"><?php echo $val('email'); ?></a>
                </td>
                
                <td style="padding:5px;"><?php echo $val('phone'); ?></td>
                
                <td style="padding:5px;">
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
                
                <td style="padding:5px;"><?php echo $val('city'); ?></td>
                
                <td style="padding:5px;"><?php echo $val('state'); ?></td>
                
                <td style="padding:5px; font-weight:600;">
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:4px; white-space:nowrap;">
                        <a href="/add-new-entry/?entry_id=<?php echo $id; ?>" style="color:#000; text-decoration:none; font-size: 11px;">Edit</a>
                        
                        <span style="color:#ddd;">|</span>
                        <a href="?entry_id=<?php echo $id; ?>&action=archive" style="color:#d97706; text-decoration:none; font-size: 11px;">Archive</a>
                        
                        <span style="color:#ddd;">|</span>
                        <a href="?entry_id=<?php echo $id; ?>&action=delete" style="color:#dc2626; text-decoration:none; font-size: 11px;" onclick="return confirm('Are you sure you want to permanently delete this entry?');">Delete</a>
                    </div>
                </td>
            </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="10" style="padding:40px; text-align:center; color:#999;">No entries in the Deck.</td></tr>
            <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
    <?php return ob_get_clean();
}