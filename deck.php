<?php
/* =========================================================================
   DECK VIEW FEATURE
   Shortcode: [crm_deck_view]
   Description: Displays entries that have a 'Deck Stage' (1-10) selected.
   ========================================================================= */

add_shortcode('crm_deck_view', 'show_crm_deck_view');

function show_crm_deck_view() {
    // 1. Security Check
    if ( !is_user_logged_in() ) {
        return '<p style="text-align:center; padding:50px;">Please log in to view the Deck.</p>';
    }

    // 2. Query Database
    // We strictly look for entries where 'deck_stage' is NOT empty
    $args = array(
        'post_type'      => 'crm_entry', 
        'post_status'    => 'publish', // Change to 'any' if you want to see Archived deals in Deck too
        'posts_per_page' => -1,        // Show all
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

    ob_start(); 
    ?>
    
    <!-- MAIN CONTAINER -->
    <div style="font-family:'Inter', sans-serif; font-size: 14px; max-width:1550px; margin:40px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); overflow-x: auto;">
        
        <!-- HEADER -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:2px solid #f0f0f0; padding-bottom:15px;">
            <div>
                <!-- Link back to main CRM -->
                <a href="/crm/" style="text-decoration:none; background:#f0f0f0; padding:10px 20px; border-radius:6px; color:#333; font-weight:600; font-size:13px; transition:0.2s;">
                    &larr; Back to All Entries
                </a>
            </div>
        </div>

        <!-- TABLE -->
        <table style="width:100%; border-collapse:collapse; min-width: 900px;">
            <thead>
                <tr style="background:#fafafa; text-align:left; color:#666; font-size:11px; text-transform:uppercase; border-bottom: 2px solid #eee;">
                    <th style="padding:15px; width:80px; text-align:center;">Deck Stage</th>
                    <th style="padding:15px;">Business Name</th>
                    <th style="padding:15px;">Contact</th>
                    <th style="padding:15px;">Phone</th>
                    <th style="padding:15px;">Email</th>
                    <th style="padding:15px;">Location</th>
                    <th style="padding:15px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $q->have_posts() ) : while ( $q->have_posts() ) : $q->the_post(); 
                $id = get_the_ID(); 
                $m = get_post_meta($id);
                
                // Helper function for safe data retrieval
                $val = function($key) use ($m) { return isset($m[$key][0]) ? $m[$key][0] : '-'; };
                
                // Get Specific Data
                $deck_stage = $val('deck_stage');
                $fname = isset($m['contact_first_name'][0]) ? $m['contact_first_name'][0] : '';
                $lname = isset($m['contact_last_name'][0]) ? $m['contact_last_name'][0] : '';
                $fullname = trim($fname . ' ' . $lname);
                
                // Color Code the Stage Badge based on value
                $badge_bg = '#333';
                if($deck_stage >= 8) $badge_bg = '#166534'; // Green for high probability
                elseif($deck_stage >= 5) $badge_bg = '#ca8a04'; // Yellow/Orange for medium
                else $badge_bg = '#dc2626'; // Red for low
            ?>
            <tr style="border-bottom:1px solid #f5f5f5; font-size:13px; color:#333; transition:background 0.2s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                
                <!-- Deck Stage Badge -->
                <td style="padding:15px; text-align:center;">
                    <span style="background:<?php echo $badge_bg; ?>; color:#fff; padding:6px 10px; border-radius:50%; font-weight:700; font-size:14px; width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;">
                        <?php echo $deck_stage; ?>
                    </span>
                </td>
                
                <!-- Business Name -->
                <td style="padding:15px;">
                    <strong style="color:#000; font-size:14px;"><?php the_title(); ?></strong>
                </td>
                
                <!-- Contact Name -->
                <td style="padding:15px;"><?php echo $fullname; ?></td>
                
                <!-- Phone -->
                <td style="padding:15px;"><?php echo $val('phone'); ?></td>
                
                <!-- Email -->
                <td style="padding:15px;">
                    <a href="mailto:<?php echo $val('email'); ?>" style="color:#007cba; text-decoration:none;"><?php echo $val('email'); ?></a>
                </td>
                
                <!-- Location -->
                <td style="padding:15px;"><?php echo $val('city') . ', ' . $val('state'); ?></td>
                
                <!-- Edit Action -->
                <td style="padding:15px; text-align:right;">
                    <a href="/add-new-entry/?entry_id=<?php echo $id; ?>" style="background:#fff; border:1px solid #ddd; color:#333; padding:6px 12px; border-radius:4px; text-decoration:none; font-size:12px; font-weight:600; display:inline-block;">
                        Edit Entry
                    </a>
                </td>
            </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="7" style="padding:60px; text-align:center; color:#999;">
                        <div style="font-size:18px; margin-bottom:10px;">No deals in the Deck.</div>
                        <div style="font-size:13px;">Go to the CRM and select a "Deck Stage" (1-10) for an entry to see it here.</div>
                    </td>
                </tr>
            <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
    
    <?php return ob_get_clean();
}
