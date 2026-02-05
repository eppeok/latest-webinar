<?php
#ZOOM
function twwt_send_zoom_notification($product_id, $event_url, $event_time, $event_password=""){
	global $wpdb;
    $product_id = intval($product_id);
	$product_name = get_the_title($product_id);
	$statuses = array_map( 'esc_sql', wc_get_is_paid_statuses() );
	/*$order_ids = $wpdb->get_col("
	   SELECT p.ID, pm.meta_value FROM {$wpdb->posts} AS p
	   INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
	   INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
	   INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
	   WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
	   AND pm.meta_key IN ( '_billing_email' )
	   AND im.meta_key IN ( '_product_id', '_variation_id' )
	   AND im.meta_value = $product_id
	");*/
    $order_ids = $wpdb->get_col( $wpdb->prepare("
    SELECT p.ID FROM {$wpdb->posts} AS p
    INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
    INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
    WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
    AND pm.meta_key = '_billing_email'
    AND im.meta_key IN ( '_product_id', '_variation_id' )
    AND im.meta_value = %d
    ", $product_id ) );
	$done = 0;
	$scounter = 0;
	foreach($order_ids as $order_id){
		if(!twwy_has_refunds_v2($order_id)){
		$sent_zoom_link = get_post_meta($order_id, '_sent_zoom_link_' . $product_id, true);
		if($sent_zoom_link!=1){
			$scounter++;
			$done = 1;
			$order = wc_get_order( $order_id );
			$screen_name = get_post_meta( $order_id, '_screen_name', true );
			if($screen_name!=""){
			}
			else{
				$cuser = $order->get_user();
				if(!empty($cuser->display_name)){
					$screen_name = $cuser->display_name;
				}
				else{
					$screen_name = $order->get_billing_first_name();
				}
			}
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();
			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
			
			twwt_zoom_email_send($email, $first_name, $last_name, $product_name, $event_url, $event_time, $event_password);
			####
			$settings = get_option( 'twwt_woo_settings' );
				$smscontent = $settings['sms_zoom_notification'];
				// Replace placeholders with actual values
				$smscontent = str_replace('%first_name%', $first_name, $smscontent);
				$smscontent = str_replace('%product_name%', $product_name, $smscontent);
				$smscontent = str_replace('%event_time%', $event_time, $smscontent);
				$smscontent = str_replace('%event_url%', $event_url, $smscontent);
				$twilio_msg = $smscontent;
			if($event_password!=""){
				$twilio_msg .= "(Passcode: {$event_password})";
			}
			wp_twilio_sms($phone, $twilio_msg);
			//update_post_meta($order_id, '_sent_zoom_link', 1);
			update_post_meta($order_id, '_sent_zoom_link_' . $product_id, 1);
			#sleep(1);
		}
	}
	}
	echo $done;
}

function twwt_zoom_email_send($email, $first_name, $last_name, $product_name, $event_url, $event_time, $event_password=""){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = $settings['ezoom_notification_sub'];
	$emailcontent = $settings['ezoom_notification'];
	// Replace placeholders with actual values
	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
	$emailsubject = str_replace('%last_name%', $last_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%event_url%', $event_url, $emailsubject);
	$emailsubject = str_replace('%event_time%', $event_time, $emailsubject);
    $emailcontent = str_replace('%first_name%', $first_name, $emailcontent);
    $emailcontent = str_replace('%last_name%', $last_name, $emailcontent);
    $emailcontent = str_replace('%product_name%', $product_name, $emailcontent);
    $emailcontent = str_replace('%event_url%', $event_url, $emailcontent);
	$emailcontent = str_replace('%event_time%', $event_time, $emailcontent);
	
	$email_heading = $emailsubject;
	ob_start();
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	echo wpautop($emailcontent);
	if($event_password!=""){
		echo "<p><strong>Event Passcode:</strong> {$event_password}</p>";
	}
	wc_get_template( 'emails/email-footer.php' );
	#echo '<p style="text-align:center;margin-top:0;">If you would prefer not receiving our emails Please <a href="'.home_url('/?unsub=email&email='.urlencode($email)).'">unsubscribe</a></p>';
	$message = ob_get_clean();
	$subject = $email_heading;
	return $mailer->send( $email, $subject, $message);
}

add_action( 'post_submitbox_misc_actions', 'add_notfication_sender_button', 10, 2 );
function add_notfication_sender_button( $post_obj = null, $which = null ) {
	//post or whatever custom post type you'd like to add the text

	$license_manager = new twwt_woo_settings_page();
   if ($license_manager->twwt_license_key_valid()) {

		if ( 'product' === $post_obj->post_type && 'publish' === $post_obj->post_status ) {
			$post_id = $post_obj->ID;
			$notification_sent = get_post_meta($post_id, 'twwt_np_notification_sent', true);
			if($notification_sent==1){
                ?>
                <div class="misc-pub-section">
                    <span class="twwt-badge twwt-badge-success">
                        ✔ Notification is sent
                    </span>
                </div>
                <?php
			}
			else{
				$np_post_id = get_option( 'twwt_np_notification_auto_start_post_id' );
				if($np_post_id>0){
					?>
	                <div class="misc-pub-section"><p>Notify your customer about the arrival of new webinar/event.</p>
	                <p>Please wait a process is already runing.</p></div>
	                <?php
				}
				else{
				?>
	            <div class="misc-pub-section">
				<p>Notify your customer about the arrival of new webinar/event.</p>
	            <p ><label><input type="checkbox" name="twwt_np_notification_sent" value="1" />Send Notification</label></p></div>
	            <?php
				}
			}
		}
	}

}

add_action('save_post', 'save_post_send_notfication');

function save_post_send_notfication($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['twwt_np_notification_sent'])) {
        // Get plugin settings array
        $settings = get_option('twwt_woo_settings');
        $mode = isset($settings['notification_mode']) ? $settings['notification_mode'] : 'immediate';

        if ($mode === 'immediate') {
            // Keep existing behaviour: single post id option (existing code)
            add_option('twwt_np_notification_auto_start_post_id', $post_id);
            update_post_meta($post_id, 'twwt_np_notification_sent', 1);
        } else {
            // Daily batch mode: add the post ID to a persisted queue (avoid duplicates)
            $queue = get_option('twwt_batch_queue', array());
            if (!is_array($queue)) $queue = array();
            $post_id = intval($post_id);
            if (!in_array($post_id, $queue)) {
                $queue[] = $post_id;
                update_option('twwt_batch_queue', $queue);
            }
            // mark post meta so admin sees it's queued
            update_post_meta($post_id, 'twwt_np_notification_sent', 1);
        }
    }
}

//add_action( 'twwt_cron_hook', 'twwt_send_np_notification' );
function twwt_send_np_notification() {
    $np_post_id = get_option('twwt_np_notification_auto_start_post_id');

    if ($np_post_id > 0) {
        $product_name = get_the_title($np_post_id);
        $url = get_the_permalink($np_post_id);

        $customers = get_users();

        foreach ($customers as $customer) {
            $user_id = $customer->ID;
            $email = $customer->user_email;
            $first_name = get_user_meta($user_id, 'first_name', true);
            $phone = get_user_meta($user_id, 'billing_phone', true);
            $unsubscribe_notification_email = get_user_meta($user_id, 'unsubscribe_notification_email', true);

			// Opt-in check (only proceed if user opted in)
            $opt_notification = get_user_meta($user_id, 'twwy_opt_notification', true);
            if ($opt_notification != 1) {
                continue; // skip users who did not opt in
            }

            // Check if this user has already been notified about this product
            $notified_products = get_user_meta($user_id, 'twwt_notified_products', true);
            if (!is_array($notified_products)) {
                $notified_products = array();
            }

            if (in_array($np_post_id, $notified_products)) {
                continue; // Skip this user, they’ve already been notified
            }

            if ($unsubscribe_notification_email != 1) {
                twwt_npo_email_send($email, $first_name, $product_name, $url);

                // Send SMS
                $settings = get_option('twwt_woo_settings');
                $smscontent = $settings['sms_new_product_notification'];
                $smscontent = str_replace('%first_name%', $first_name, $smscontent);
                $smscontent = str_replace('%product_name%', $product_name, $smscontent);
                $smscontent = str_replace('%url%', $url, $smscontent);

                //wp_twilio_sms($phone, $smscontent);
                // Send SMS (defensive)
                if (!empty($phone)) {
                    if (function_exists('wp_twilio_sms')) {
                        $sms_result = wp_twilio_sms($phone, $smscontent);
                        if ($sms_result === false) {
                            error_log("[TWWT] wp_twilio_sms() returned false for phone {$phone}");
                        } else {
                            error_log("[TWWT] wp_twilio_sms() invoked for phone {$phone}");
                        }
                    } else {
                        // Twilio function not available in this runtime (cron). Log for debugging.
                        error_log("[TWWT] wp_twilio_sms() not found; SMS not sent. Phone: {$phone}. Message start: " . substr($smscontent, 0, 80));
                    }
                } else {
                    error_log("[TWWT] No phone for user {$user_id}; SMS skipped.");
                }

                sleep(1);
            }

            // Mark this product as notified for the user
            $notified_products[] = $np_post_id;
            update_user_meta($user_id, 'twwt_notified_products', $notified_products);
        }

        delete_option('twwt_np_notification_auto_start_post_id');
    }
}

function twwt_npo_email_send($email, $first_name, $product_name, $url){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = $settings['new_product_notification_sub'];
	$emailcontent = $settings['new_product_notification'];
	// Replace placeholders with actual values
	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%url%', $url, $emailsubject);
    $emailcontent = str_replace('%first_name%', $first_name, $emailcontent);
    $emailcontent = str_replace('%product_name%', $product_name, $emailcontent);
    $emailcontent = str_replace('%url%', $url, $emailcontent);
	
	$email_heading = $emailsubject;
	ob_start();
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	echo wpautop($emailcontent);
	wc_get_template( 'emails/email-footer.php' );
	echo '<p style="text-align:center;margin-top:0;">If you would prefer not receiving our emails Please <a href="'.home_url('/?unsub=email&email='.urlencode($email)).'">unsubscribe</a></p>';
	$message = ob_get_clean();
	$subject = $email_heading;
	return $mailer->send( $email, $subject, $message);
	//return true;
}

/*function twwt_npo_email_send_batch( $to_email, $first_name, $titles = array(), $links = array() ) {
    if ( empty( $to_email ) ) return false;

    // Subject (fixed as requested)
    $subject = "Today's New webinars lists";

    // Build greeting + simple list
    $greeting = '<p>Hi ' . esc_html( $first_name ? $first_name : 'there' ) . ',</p>';
    $intro = '<p>Here are today\'s new webinars:</p>';

    $list_html = '';
    if ( ! empty( $titles ) && is_array( $titles ) ) {
        $list_html .= '<ul style="line-height:1.5;margin:0 0 1em 1.2em;">';
        foreach ( $titles as $i => $t ) {
            $title_safe = wp_strip_all_tags( $t );
            $link = isset( $links[ $i ] ) ? esc_url( $links[ $i ] ) : '';
            if ( $link ) {
                $list_html .= '<li>' . esc_html( $title_safe ) . ' &ndash; <a href="' . $link . '">' . $link . '</a></li>';
            } else {
                $list_html .= '<li>' . esc_html( $title_safe ) . '</li>';
            }
        }
        $list_html .= '</ul>';
    } else {
        // fallback
        $list_html .= '<p>No items available.</p>';
    }

    $closing = '<p>Thank you,<br/>Creative Dev Team</p>';

    // Optional unsubscribe line (uses the same method as your existing function)
    $unsubscribe_url = add_query_arg( array(
        'unsub' => 'email',
        'email' => rawurlencode( $to_email )
    ), home_url('/') );
    $unsubscribe_html = '<p style="text-align:center;margin-top:0;font-size:90%;">If you would prefer not receiving our emails please <a href="' . esc_url( $unsubscribe_url ) . '">unsubscribe</a>.</p>';

    // Combine message
    $message = '<html><body style="font-family:Arial,Helvetica,sans-serif;color:#222;">';
    $message .= $greeting . $intro . $list_html . $closing . $unsubscribe_html;
    $message .= '</body></html>';

    // Try WooCommerce mailer first if available (preserves WP/WC email templates automatically in other function,
    // but for the simple batch email we send a simple HTML message)
    $sent = false;
    if ( function_exists('WC') && WC() && method_exists(WC(), 'mailer') ) {
        try {
            $mailer = WC()->mailer();
            $sent = $mailer->send( $to_email, $subject, $message );
        } catch ( Exception $e ) {
            error_log('[TWWT] Batch mailer exception: ' . $e->getMessage());
            $sent = false;
        }
    }

    // Fallback to wp_mail
    if ( ! $sent ) {
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $sent = wp_mail( $to_email, $subject, $message, $headers );
    }

    if ( $sent ) {
        error_log("[TWWT] Batch email sent to {$to_email} (subject: " . substr($subject,0,80) . ')');
    } else {
        error_log("[TWWT] Batch email FAILED for {$to_email} (subject: " . substr($subject,0,80) . ')');
    }

    return (bool) $sent;
}*/
function twwt_npo_email_send_batch( $to_email, $first_name, $titles = array(), $links = array() ) {
    if ( empty( $to_email ) ) return false;

    $settings = get_option('twwt_woo_settings');

    // SUBJECT (admin controlled)
    $subject = !empty($settings['batch_new_product_notification_sub'])
        ? $settings['batch_new_product_notification_sub']
        : 'Today\'s New Webinars';

    $subject = str_replace('%first_name%', $first_name, $subject);

    // BUILD WEBINAR LIST HTML
    $webinar_list = '<ul>';
    foreach ($titles as $i => $title) {
        $url = isset($links[$i]) ? esc_url($links[$i]) : '';
        $webinar_list .= '<li><a href="'.$url.'">'.esc_html($title).'</a></li>';
    }
    $webinar_list .= '</ul>';

    // BODY TEMPLATE
    $body = $settings['batch_new_product_notification'];
    $body = str_replace('%first_name%', esc_html($first_name), $body);
    $body = str_replace('%webinar_list%', $webinar_list, $body);

    // WC EMAIL WRAPPER
    ob_start();
    wc_get_template( 'emails/email-header.php', array( 'email_heading' => $subject ) );
    echo wpautop($body);
    wc_get_template( 'emails/email-footer.php' );
    $message = ob_get_clean();

    // SEND
    if ( function_exists('WC') && WC()->mailer() ) {
        return WC()->mailer()->send( $to_email, $subject, $message );
    }

    return wp_mail( $to_email, $subject, $message, array('Content-Type: text/html') );
}


// ---------- Batch queue + scheduler + sender ----------

add_action('twwt_daily_batch_hook', 'twwt_send_daily_batch_notifications');

function twwt_schedule_daily_batch() {
    // Read settings
    $settings = get_option('twwt_woo_settings', array());
    $mode = isset($settings['notification_mode']) ? $settings['notification_mode'] : 'immediate';
    if ($mode !== 'daily') {
        // If not daily, clear any existing scheduled hook and return
        if ( wp_next_scheduled('twwt_daily_batch_hook') ) {
            wp_clear_scheduled_hook('twwt_daily_batch_hook');
            error_log('[TWWT] Mode not daily; cleared scheduled twwt_daily_batch_hook.');
        } else {
            error_log('[TWWT] Mode not daily; nothing to schedule.');
        }
        return;
    }

    $batch_time = isset($settings['notification_batch_time']) ? $settings['notification_batch_time'] : '09:00';
    if (!preg_match('/^\d{2}:\d{2}$/', $batch_time)) {
        $batch_time = '09:00';
    }
    list($hour, $minute) = explode(':', $batch_time);

    // Determine site timezone
    if ( function_exists('wp_timezone') ) {
        $tz = wp_timezone();
    } else {
        $tz_string = get_option('timezone_string');
        $tz = new DateTimeZone( $tz_string ? $tz_string : 'UTC' );
    }

    // Build desired next occurrence in site timezone
    $now = new DateTime('now', $tz);
    $next = new DateTime('now', $tz);
    $next->setTime( intval($hour), intval($minute), 0 );
    if ($next <= $now) {
        $next->modify('+1 day');
    }
    
    // Convert site-time DateTime to UTC timestamp for WP-Cron
    $desired_ts = $next->getTimestamp();

    // WP-Cron expects UTC timestamps; ensure we compare in UTC
    $utc_now = time();

    // If scheduled time already passed (or too close), push to next day
    if ( $desired_ts <= $utc_now + 60 ) {
        $next->modify('+1 day');
        $desired_ts = $next->getTimestamp();
    }

    // Check existing schedule
    $existing = wp_next_scheduled('twwt_daily_batch_hook');

    if ($existing && abs($existing - $desired_ts) <= 60) {
        // Already scheduled at (nearly) the same time — do nothing
        $tmp = new DateTime('@' . $existing);
        $tmp->setTimezone($tz);
        error_log(sprintf('[TWWT] Schedule unchanged; next run is %s (site tz: %s). Raw timestamp: %d',
            $tmp->format('Y-m-d H:i:s'),
            $tz instanceof DateTimeZone ? $tz->getName() : 'unknown',
            $existing
        ));
        return;
    }

    // Otherwise clear the previous hook (if any) and schedule new
    if ($existing) {
        wp_clear_scheduled_hook('twwt_daily_batch_hook');
        error_log('[TWWT] Cleared existing twwt_daily_batch_hook (rescheduling).');
    }

    wp_schedule_event( $desired_ts, 'daily', 'twwt_daily_batch_hook' );

    $scheduled_dt = new DateTime('@' . wp_next_scheduled('twwt_daily_batch_hook'));
    $scheduled_dt->setTimezone($tz);

    error_log(sprintf('[TWWT] Scheduled twwt_daily_batch_hook at %s (site tz: %s). Raw timestamp: %d',
        $scheduled_dt->format('Y-m-d H:i:s'),
        $tz instanceof DateTimeZone ? $tz->getName() : 'unknown',
        wp_next_scheduled('twwt_daily_batch_hook')
    ));
}

/**
 * DAILY BATCH — HTML list in WooCommerce email via twwt_npo_email_send()
 * and SMS via wp_twilio_sms() using existing SMS template.
 */
/* ---------------------------
   Replacement: twwt_send_daily_batch_notifications
   Processes twwt_batch_queue, builds HTML list, sends emails and SMS.
   Adds extensive error_log lines for debugging cron context.
   --------------------------- */
function twwt_send_daily_batch_notifications() {
    error_log('[TWWT] Batch fired at UTC: ' . gmdate('Y-m-d H:i:s'));
    error_log('[TWWT] Batch fired at site time: ' . wp_date('Y-m-d H:i:s'));
    // Simple lock to avoid concurrent runs (transient or option)
    $lock_name = 'twwt_batch_lock';
    $lock_ttl = 5 * 60; // 5 minutes

    // Attempt to acquire lock
    if ( get_transient( $lock_name ) ) {
        error_log('[TWWT] Batch: another run is active; aborting this run.');
        return;
    }
    // Set temporary lock
    set_transient( $lock_name, time(), $lock_ttl );

    try {
        $queue = get_option('twwt_batch_queue', array());
        if (!is_array($queue) || empty($queue)) {
            error_log('[TWWT] Batch queue empty: nothing to send.');
            // release lock
            delete_transient( $lock_name );
            return; // nothing queued
        }

        // Build items list
        $items = array();
        $titles = array();
        $links = array();
        foreach ($queue as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            $title = get_the_title($post_id);
            $link = get_permalink($post_id);
            if (!$link) $link = '';
            $items[] = array('title' => $title, 'link' => $link);
            $titles[] = $title;
            $links[] = $link;
        }

        if (empty($items)) {
            update_option('twwt_batch_queue', array());
            update_option('twwt_last_batch_sent', time());
            error_log('[TWWT] After filtering, no valid items in queue.');
            // release lock
            delete_transient( $lock_name );
            return;
        }

        // HTML list for email
        $html_list = '<ul style="line-height:1.5;margin:0 0 1em 1.2em;">';
        foreach ($items as $it) {
            $safe_title = wp_strip_all_tags( $it['title'] );
            $safe_link  = esc_url( $it['link'] );
            if ( $safe_link ) {
                $html_list .= '<li><a href="' . $safe_link . '">' . esc_html( $safe_title ) . '</a></li>';
            } else {
                $html_list .= '<li>' . esc_html( $safe_title ) . '</li>';
            }
        }
        $html_list .= '</ul>';

        $combined_titles_short = implode(' ; ', $titles);
        $combined_urls_plain = implode("\n", array_filter($links));
        $combined_urls_sms = implode(' ', array_filter($links));

        $settings = get_option('twwt_woo_settings', array());
        $sms_template = isset($settings['sms_new_product_notification']) ? $settings['sms_new_product_notification'] : 'New webinars: %product_name% - %url%';

        // Collect all users who opted in — use a WP_User_Query to fetch only opted-in customers.
        $opted_in_users = array();
        $user_query = new WP_User_Query( array(
            'role'       => 'customer',
            'meta_key'   => 'twwy_opt_notification',
            'meta_value' => '1',
            'fields'     => 'all',
            'number'     => 0, // 0 = no limit; careful on very large sites (consider paged loop)
        ));
        $opted_in_users = $user_query->get_results();

        if (empty($opted_in_users)) {
            error_log('[TWWT] No users opted-in (twwy_opt_notification not set to 1 for any user).');
            // release lock
            delete_transient( $lock_name );
            return;
        }

        foreach ($opted_in_users as $customer) {
            $user_id = $customer->ID;
            $email = $customer->user_email;
            $first_name = get_user_meta($user_id, 'first_name', true);
            $phone = get_user_meta($user_id, 'billing_phone', true);
            $unsubscribe_notification_email = get_user_meta($user_id, 'unsubscribe_notification_email', true);

            if ($unsubscribe_notification_email == 1) {
                // user unsubscribed
                continue;
            }

            // EMAIL: pass HTML list and plain URLs
            if (!empty($email)) {
                //$sent = twwt_npo_email_send($email, $first_name, $html_list, $combined_urls_plain);
                $sent = twwt_npo_email_send_batch( $email, $first_name, $titles, $links );
                if (!$sent) {
                    error_log("[TWWT] twwt_npo_email_send failed for user {$user_id} ({$email})");
                } else {
                    error_log("[TWWT] Batch email sent to user {$user_id} ({$email})");
                }
            } else {
                error_log("[TWWT] No email for user {$user_id}");
            }

            // SMS: prepare and send
            if (!empty($phone)) {
                // Create a short title list e.g. "Webinar A; Webinar B; Webinar C"
                $sms_titles_arr = array();
                foreach ($titles as $t) {
                    $t_clean = trim( wp_strip_all_tags( $t ) );
                    if ( $t_clean !== '' ) $sms_titles_arr[] = $t_clean;
                }
                // join with semicolon; shorten to a reasonable length
                $sms_titles_joined = implode(' ; ', $sms_titles_arr);
                if ( mb_strlen( $sms_titles_joined ) > 200 ) {
                    // trim to ~180 chars without breaking multibyte
                    $sms_titles_joined = mb_substr( $sms_titles_joined, 0, 180 ) . '...';
                }

                // Use first product link as the booking link (if available)
                $booking_link = '';
                if ( ! empty( $links ) && ! empty( $links[0] ) ) {
                    $booking_link = $links[0];
                } elseif ( ! empty( $combined_urls_sms ) ) {
                    // fallback to the first URL parsed from combined string
                    $parts = preg_split('/\s+/', trim($combined_urls_sms));
                    $booking_link = isset($parts[0]) ? $parts[0] : '';
                }

                // Compose SMS text
                // Example: "New webinars: Webinar A; Webinar B. Book: https://example.com/first-link"
                /*$sms_text = 'New webinars: ' . $sms_titles_joined;
                if ( $booking_link ) {
                    // append booking link (space-separated)
                    $sms_text .= ' Book: ' . esc_url_raw( $booking_link );
                }

                // Ensure SMS length is reasonable (<= 320 chars)
                if ( mb_strlen( $sms_text ) > 320 ) {
                    $sms_text = mb_substr( $sms_text, 0, 317 ) . '...';
                }*/
                
                // Prepare SMS using template
                // Build SMS from admin template (BATCH)
                $sms_template = isset($settings['sms_batch_new_product_notification'])
                    ? $settings['sms_batch_new_product_notification']
                    : 'New webinars: %webinar_titles% %url%';

                // Join titles safely
                $sms_titles_joined = implode(' ; ', array_map('wp_strip_all_tags', $titles));
                if ( mb_strlen($sms_titles_joined) > 180 ) {
                    $sms_titles_joined = mb_substr($sms_titles_joined, 0, 177) . '...';
                }

                // Replace placeholders
                $sms_text = str_replace('%webinar_titles%', $sms_titles_joined, $sms_template);
                $sms_text = str_replace('%url%', $booking_link, $sms_text);

                // Final safety trim
                if ( mb_strlen($sms_text) > 320 ) {
                    $sms_text = mb_substr($sms_text, 0, 317) . '...';
                }

                // Send via Twilio (or other provider)
                if ( function_exists('wp_twilio_sms') ) {
                    $sms_sent = wp_twilio_sms( $phone, $sms_text );
                    if ( $sms_sent === false ) {
                        error_log( "[TWWT] wp_twilio_sms() returned false for phone {$phone}" );
                    } else {
                        error_log( "[TWWT] wp_twilio_sms() invoked for phone {$phone}" );
                    }
                } else {
                    // provider not available in this context — log the payload for debugging
                    error_log( "[TWWT] wp_twilio_sms() not found; SMS not sent. Phone: {$phone}. Message: " . substr( $sms_text, 0, 200 ) );
                }
            }


            // mark user as notified for these products (persist per user ASAP)
            $notified_products = get_user_meta($user_id, 'twwt_notified_products', true);
            if (!is_array($notified_products)) $notified_products = array();
            foreach ($queue as $pid) {
                if (!in_array($pid, $notified_products)) {
                    $notified_products[] = $pid;
                }
            }
            update_user_meta($user_id, 'twwt_notified_products', $notified_products);

            // small throttle to avoid hitting provider rate limits
            sleep(1);
        }

        // Clear queue and stamp time
        update_option('twwt_batch_queue', array());
        update_option('twwt_last_batch_sent', time());
        error_log('[TWWT] Batch notifications finished and queue cleared.');

    } catch ( Exception $e ) {
        error_log('[TWWT] Exception in batch: ' . $e->getMessage());
    }

    // Always release lock at end
    delete_transient( $lock_name );
}


//subscribe
add_action('init', 'mt_unsubscribe');
function mt_unsubscribe(){
    if(@$_GET['unsub']=="email"){
        $email = $_GET['email'];
        $user = get_user_by( 'email', $email );
        $user_id = $user->ID;
        update_user_meta( $user_id, 'unsubscribe_notification_email', 1);
        if ( wp_redirect( home_url('/?alertmsg='.urlencode('You are successfully unsubscribed.')) ) ) {
            exit;
        }
    }
}