<?php
/* =========================================================================
   AGREEMENT SYSTEM (agreement.php)
   Shortcode: [agreement_form]
   UI: Matches CRM Entry Form (Inter font, Grid layout, Styled Inputs)
   ========================================================================= */

// 1. Register 'Agreement' Post Type
function register_pickbrew_agreement_cpt() {
    $args = array(
        'labels'      => array(
            'name'          => 'Agreements',
            'singular_name' => 'Agreement',
            'menu_name'     => 'Agreements'
        ),
        'public'      => true,
        'show_ui'     => true,
        'has_archive' => false,
        'supports'    => array('title', 'editor', 'custom-fields'),
        'menu_icon'   => 'dashicons-media-document',
        'rewrite'     => array('slug' => 'agreement'), 
    );
    register_post_type('agreement', $args);
}
add_action('init', 'register_pickbrew_agreement_cpt');

// 2. Shortcode to Display the Form
add_shortcode('agreement_form', 'render_agreement_form');

function render_agreement_form() {
    // Security check
    if (!is_user_logged_in()) {
        return '<p style="text-align:center; padding:50px; font-family:\'Inter\', sans-serif;">Please <a href="/wp-login.php">log in</a> to create an agreement.</p>';
    }

    $message = '';

    // --- FORM SUBMISSION HANDLING ---
    if (isset($_POST['submit_agreement_bttn'])) {
        
        // A. Sanitize Data
        $creator   = sanitize_text_field($_POST['created_by']);
        $merchant  = sanitize_text_field($_POST['merchant_name']);
        $email     = sanitize_email($_POST['merchant_email']);
        $phone     = sanitize_text_field($_POST['merchant_phone']);
        $status    = sanitize_text_field($_POST['agreement_status']);
        $deal      = sanitize_text_field($_POST['deal_type']);
        $cap       = sanitize_text_field($_POST['monthly_cap']);
        $loc_type  = sanitize_text_field($_POST['location_type']);
        $pos       = isset($_POST['pos_system']) ? sanitize_text_field($_POST['pos_system']) : '';

        // Additional Deal Fields
        $comm_rate = isset($_POST['commission_rate_val']) ? sanitize_text_field($_POST['commission_rate_val']) : '';
        $order_rate = isset($_POST['proposed_rate_val']) ? sanitize_text_field($_POST['proposed_rate_val']) : '';
        $flat_fee  = isset($_POST['monthly_fee_val']) ? sanitize_text_field($_POST['monthly_fee_val']) : '';

        // B. Generate Password & Title
        $password = wp_generate_password(8, false); 
        $post_title = $merchant . ' - Agreement';

        // C. Create the Content
        $html_content  = "<h2>Agreement for $merchant</h2>";
        $html_content .= "<p><strong>Date:</strong> " . date('F j, Y') . "<br>";
        $html_content .= "<strong>Prepared By:</strong> $creator</p><hr>";
        $html_content .= "<h3>Agreement Terms</h3><ul>";
        
        // Format Deal Display
        $display_deal = $deal;
        if($deal == 'Commission') $display_deal .= " ($comm_rate)";
        if($deal == 'Daily_2') $display_deal .= " ($order_rate per order)";
        if($deal == 'Flat') $display_deal .= " ($$flat_fee / month)";

        $html_content .= "<li><strong>Deal Type:</strong> $display_deal</li>";
        $html_content .= "<li><strong>Monthly Cap:</strong> $cap</li>";
        $html_content .= "<li><strong>Location Type:</strong> $loc_type</li>";
        if($pos) $html_content .= "<li><strong>POS System:</strong> $pos</li>";
        $html_content .= "</ul>";
        $html_content .= "<hr><h3>Official Agreement</h3>";
        $html_content .= "<p>[Insert your full legal agreement text here. This content is protected.]</p>";

        // D. Insert Post
        $post_id = wp_insert_post(array(
            'post_title'    => $post_title,
            'post_content'  => $html_content,
            'post_status'   => 'publish',
            'post_type'     => 'agreement',
            'post_password' => $password
        ));

        if ($post_id) {
            update_post_meta($post_id, 'merchant_email', $email);
            update_post_meta($post_id, 'agreement_status', $status);
            update_post_meta($post_id, 'created_by', $creator);

            // E. Send Emails
            $link = get_permalink($post_id);
            $headers = array('Content-Type: text/html; charset=UTF-8');

            // Email to Merchant
            $msg  = "Hello $merchant,<br><br>";
            $msg .= "Please find a link to the PickBrew agreement. Let us know if you have any questions.<br>";
            $msg .= "We appreciate the opportunity to help and look forward to making this amazing for you and your brand.<br><br>";
            $msg .= "Upon clicking on the agreement link, you will be prompted to enter a password:<br>";
            $msg .= "<strong>Password:</strong> <span style='background:#eee; padding:5px; font-weight:bold;'>$password</span><br><br>";
            $msg .= "Click to view the AGREEMENT: <a href='$link'>$link</a><br><br>";
            $msg .= "Sincerely,<br>The team at PickBrew<br>PickBrew.com<br>";
            $msg .= "Feel free to schedule a call with us <a href='#'>HERE</a>";
            
            wp_mail($email, "Agreement: $merchant", $msg, $headers);

            // Email to Admin
            $admin_email = get_option('admin_email');
            $admin_msg = "New Agreement created by $creator.<br>Merchant: $merchant<br>Link: $link<br>Pass: $password";
            wp_mail($admin_email, "New Agreement Generated", $admin_msg, $headers);

            $message = '<div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:5px; text-align:center; font-family:\'Inter\';">✅ Agreement Created & Email Sent!</div>';
        }
    }

    ob_start();
    echo $message;
    ?>
    <style>
        /* Exact CRM Entry Form CSS */
        .crm-wrap { font-family:'Inter', sans-serif; background:#fff; max-width:850px; margin:40px auto; padding:50px; border:1px solid #e0e0e0; box-shadow:0 5px 20px rgba(0,0,0,0.03); }
        .crm-title { font-size:26px; font-weight:700; margin-bottom:40px; color:#111; }
        .crm-row { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:25px; }
        .crm-full { grid-column:1 / -1; margin-bottom:25px; }
        .crm-label { display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; }
        .crm-input, .crm-select { width:100%; padding:12px; border:1px solid #ccc; border-radius:4px; font-size:14px; box-sizing:border-box; background: #fff; height: 45px; }
        .crm-input:focus, .crm-select:focus { border-color:#000; outline:none; }
        .conditional-box { background:#f8f9fa; border-left:4px solid #000; padding:20px; margin-top:15px; margin-bottom:20px; display:none; }
        .sub-btn { background:#000; color:#fff; width:100%; padding:16px; border:none; font-size:16px; font-weight:700; cursor:pointer; margin-top:30px; border-radius:4px; transition: background 0.2s; }
        .sub-btn:hover { background:#333; }
        .radio-group label { margin-right: 20px; font-weight: 500; cursor: pointer; display:inline-block; margin-bottom:5px; }
    </style>

    <div class="crm-wrap">
        <h2 class="crm-title">Generate Agreement</h2>
        
        <form method="post" id="agreementForm">
            
            <div class="crm-full">
                <label class="crm-label">Created By *</label>
                <div class="radio-group" style="margin-top:10px;">
                    <label><input type="radio" name="created_by" value="Aryan" required> Aryan</label>
                    <label><input type="radio" name="created_by" value="Shyam"> Shyam</label>
                    <label><input type="radio" name="created_by" value="Sapna"> Sapna</label>
                    <label><input type="radio" name="created_by" value="Amit"> Amit</label>
                </div>
            </div>

            <div class="crm-full">
                <div>
                    <label class="crm-label">Merchant Name *</label>
                    <input type="text" name="merchant_name" class="crm-input" required>
                </div>
            </div>
			
			<div class="crm-full">
                <div>
                    <label class="crm-label">Merchant Phone *</label>
                    <input type="text" name="merchant_phone" class="crm-input" placeholder="(555)-555-5555" required>
                </div>
            </div>

            <div class="crm-full">
                <label class="crm-label">Merchant Email *</label>
                <input type="email" name="merchant_email" class="crm-input" required>
            </div>

            <div class="crm-full">
                <label class="crm-label">Agreement Status *</label>
                <select name="agreement_status" class="crm-select" required>
                    <option value="">Select Status</option>
                    <option value="Agreement Sent Initially">1. Agreement Sent Initially</option>
                    <option value="Agreement sent/not signed">2. Agreement sent / not signed yet</option>
                    <option value="Commited">3. Commited / Not Signed yet</option>
                    <option value="Signed">4. Signed</option>
                    <option value="Archive">5. Archive</option>
                </select>
            </div>

            <div style="height:1px; background:#eee; margin:30px 0;"></div>

            <div class="crm-full">
                <label class="crm-label">Deal Type Proposed *</label>
                <div class="radio-group" style="margin-top:10px;">
                    <label><input type="radio" name="deal_type" value="Commission" onclick="toggleDeal(1)" required> Commission</label><br>
                    <label><input type="radio" name="deal_type" value="Daily_1" onclick="toggleDeal(0)"> $4-$10 Pricing (Daily per location)</label><br>
                    <label><input type="radio" name="deal_type" value="Flat" onclick="toggleDeal(3)"> Flat Monthly (per location)</label><br>
                    <label><input type="radio" name="deal_type" value="Daily_2" onclick="toggleDeal(2)"> Per Order</label>
                </div>
                
                <div id="box_comm" class="conditional-box">
                    <label class="crm-label">Proposed Commission Rate</label>
                    <input type="text" name="commission_rate_val" class="crm-input" value="6%">
                </div>

                <div id="box_daily" class="conditional-box">
                    <label class="crm-label">Proposed Per Order Fee</label>
                    <input type="text" name="proposed_rate_val" class="crm-input" value=".25">
                </div>

                <div id="box_flat" class="conditional-box">
                    <label class="crm-label">Monthly Fee</label>
                    <select name="monthly_fee_val" class="crm-select">
                        <option value="299.99">$299.99</option>
                        <option value="349.99">$349.99</option>
                        <option value="399.99">$399.99</option>
                        <option value="499.99">$499.99</option>
                        <option value="599.99">$599.99</option>
                    </select>
                </div>
            </div>

            <div class="crm-full">
                <label class="crm-label">Monthly Cap *</label>
                <select name="monthly_cap" class="crm-select" required>
                    <option value="">Select Cap Option</option>
                    <?php 
                    $rates = [5, 6, 7];
                    $prices = [249.99, 299.99, 349.99, 379.99];
                    foreach($rates as $r) {
                        foreach($prices as $p) {
                            echo "<option value='{$r}%, capped at \${$p}/month'>{$r}%, capped at \${$p}/month, per location</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="crm-full">
                <label class="crm-label">Location Type *</label>
                <select name="location_type" id="locType" class="crm-select" onchange="togglePos()" required>
                    <option value="Freestanding">Freestanding</option>
                    <option value="POS Integrated">POS Integrated</option>
                </select>

                <div id="posBox" class="conditional-box">
                    <label class="crm-label">POS System *</label>
                    <select name="pos_system" class="crm-select">
                        <option value="Square">Square</option>
                        <option value="Toast">Toast</option>
                        <option value="Clover">Clover</option>
                        <option value="Linga">Linga</option>
                        <option value="Upserve">Upserve</option>
                        <option value="DripOS">DripOS</option>
                        <option value="DiamondScan">DiamondScan</option>
                        <option value="Shopkeep">Shopkeep</option>
                        <option value="Katalyst">Katalyst</option>
                        <option value="AlphaPOS">AlphaPOS</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="crm-full" style="margin-top:20px;">
                <label style="font-weight:700; cursor:pointer; color:#007cba; display:flex; align-items:center;">
                    <input type="checkbox" name="send_sms" value="yes" style="width:20px; height:20px; margin-right:10px;">
                    Send Confirmation Via Text Message Also
                </label>
            </div>

            <button type="submit" name="submit_agreement_bttn" class="sub-btn">Send Agreement</button>
        </form>
    </div>

    <script>
    function toggleDeal(type) {
        document.getElementById('box_comm').style.display = (type===1) ? 'block' : 'none';
        document.getElementById('box_daily').style.display = (type===2) ? 'block' : 'none';
        document.getElementById('box_flat').style.display = (type===3) ? 'block' : 'none';
    }

    function togglePos() {
        var loc = document.getElementById('locType').value;
        document.getElementById('posBox').style.display = (loc === 'POS Integrated') ? 'block' : 'none';
    }
    
    // Formatting Phone Number
    document.querySelector('input[name="merchant_phone"]').addEventListener('input', function (e) {
        let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
        e.target.value = !x[2] ? x[1] : '(' + x[1] + ')-' + x[2] + (x[3] ? '-' + x[3] : '');
    });
    </script>
    <?php
    return ob_get_clean();
}