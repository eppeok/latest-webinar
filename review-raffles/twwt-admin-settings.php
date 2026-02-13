<?php
register_activation_hook(__FILE__, 'twwt_woo_set_options');
function twwt_woo_set_options (){
	$defaults = array(
		'login_restrict' => 0,
		'randomGenerator_restrict' => 0,
		'producttab' => 1,
		'winner_noto_others' => 1,
		'login_button_text' => 'Login',
		'enable_notification' => 1,
		'video_show' => 1,
		'bootstrap_show' => 1,
		'default_webinar_category' => '',
		'winner' => 1,
		'id' => '',
		'twilio_sid' => '',
		'twilio_token' => '',
		'twilio_from' => '',
		'sms_provider'       => 'twilio',
		'ottertext_api_key'  => '',
		'ottertext_partner'  => '',
		'aiq_api_key'        => '',
		'new_product_notification_sub' => 'New Webinar Available: %product_name%',
		'new_product_notification' => '<p>Hi %first_name%,</p><p>A new webinar is now available: <strong>%product_name%</strong>.</p><p><a href="%url%">Book your seat now</a></p>',
		'wwinner_notification_sub' => 'Congratulations! You won the %product_name% raffle!',
		'wwinner_notification' => '<p>Hi %first_name%,</p><p>Congratulations! You have been selected as the winner for <strong>%product_name%</strong>!</p><p>Your screen name: %screen_name%</p><p>Thank you for participating!</p>',
		'winner_noti_others_sub' => 'Winner Announced for %product_name%',
		'winner_noti_others' => '<p>Hi %first_name%,</p><p>The winner for <strong>%product_name%</strong> has been selected: <strong>%screen_name%</strong>.</p><p>Thank you for participating!</p>',
		'ezoom_notification_sub' => 'Your Webinar Details: %product_name%',
		'ezoom_notification' => '<p>Hi %first_name%,</p><p>Here are your webinar details for <strong>%product_name%</strong>:</p><p>Event URL: <a href="%event_url%">%event_url%</a><br>Event Time: %event_time%</p><p>See you there!</p>',
		'sms_new_product_notification' => 'Hi %first_name%! New webinar available: %product_name%. Book now: %url%',
		'sms_wwinner_notification' => 'Congrats %first_name%! You won the %product_name% raffle! Screen name: %screen_name%',
		'sms_winner_noti_others' => 'Winner for %product_name% has been announced: %screen_name%. Thanks for participating!',
		'sms_zoom_notification' => 'Hi %first_name%! Your webinar %product_name% is at %event_time%. Join: %event_url%',
		'batch_new_product_notification_sub' => 'Today\'s New Webinars',
		'batch_new_product_notification'     => '<p>Hi %first_name%,</p><p>Here are today\'s new webinars:</p>%webinar_list%<p>Don\'t miss out — book your seat today!</p>',
		'sms_batch_new_product_notification' => 'New webinars available: %webinar_titles%. Book now: %url%',
		'twwt_plugin_license_key' => '',
		'notification_mode' => 'immediate',
		'notification_batch_time' => '09:00',
		'seat_hold_minutes' => 10,
		'winner_primary_color' => '#d63638',
		'winner_primary_hover' => '#b32d2f',
		'winner_table_header_bg' => '#f5f5f5',
		'winner_button_text' => '#ffffff',
	);
	add_option('twwt_woo_settings', $defaults);
}

// One-time migration: backfill empty template fields with sensible defaults
add_action( 'admin_init', 'twwt_backfill_template_defaults' );
function twwt_backfill_template_defaults() {
	if ( get_option( 'twwt_templates_backfilled_v1' ) ) {
		return;
	}
	update_option( 'twwt_templates_backfilled_v1', 1 );

	$settings = get_option( 'twwt_woo_settings', array() );
	$template_defaults = array(
		'new_product_notification_sub' => 'New Webinar Available: %product_name%',
		'new_product_notification' => '<p>Hi %first_name%,</p><p>A new webinar is now available: <strong>%product_name%</strong>.</p><p><a href="%url%">Book your seat now</a></p>',
		'wwinner_notification_sub' => 'Congratulations! You won the %product_name% raffle!',
		'wwinner_notification' => '<p>Hi %first_name%,</p><p>Congratulations! You have been selected as the winner for <strong>%product_name%</strong>!</p><p>Your screen name: %screen_name%</p><p>Thank you for participating!</p>',
		'winner_noti_others_sub' => 'Winner Announced for %product_name%',
		'winner_noti_others' => '<p>Hi %first_name%,</p><p>The winner for <strong>%product_name%</strong> has been selected: <strong>%screen_name%</strong>.</p><p>Thank you for participating!</p>',
		'ezoom_notification_sub' => 'Your Webinar Details: %product_name%',
		'ezoom_notification' => '<p>Hi %first_name%,</p><p>Here are your webinar details for <strong>%product_name%</strong>:</p><p>Event URL: <a href="%event_url%">%event_url%</a><br>Event Time: %event_time%</p><p>See you there!</p>',
		'sms_new_product_notification' => 'Hi %first_name%! New webinar available: %product_name%. Book now: %url%',
		'sms_wwinner_notification' => 'Congrats %first_name%! You won the %product_name% raffle! Screen name: %screen_name%',
		'sms_winner_noti_others' => 'Winner for %product_name% has been announced: %screen_name%. Thanks for participating!',
		'sms_zoom_notification' => 'Hi %first_name%! Your webinar %product_name% is at %event_time%. Join: %event_url%',
		'batch_new_product_notification_sub' => 'Today\'s New Webinars',
		'batch_new_product_notification' => '<p>Hi %first_name%,</p><p>Here are today\'s new webinars:</p>%webinar_list%<p>Don\'t miss out — book your seat today!</p>',
		'sms_batch_new_product_notification' => 'New webinars available: %webinar_titles%. Book now: %url%',
		'winner_primary_color' => '#d63638',
		'winner_primary_hover' => '#b32d2f',
		'winner_table_header_bg' => '#f5f5f5',
		'winner_button_text' => '#ffffff',
	);
	$changed = false;
	foreach ( $template_defaults as $key => $default_val ) {
		if ( empty( $settings[ $key ] ) ) {
			$settings[ $key ] = $default_val;
			$changed = true;
		}
	}
	if ( $changed ) {
		update_option( 'twwt_woo_settings', $settings );
	}
}

register_deactivation_hook( __FILE__, 'twwt_woo_deactivate' );
function twwt_woo_deactivate(){
    delete_option('twwt_woo_settings');
}


class twwt_woo_settings_page {
	private $options;
			
	public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'page_init' ) );
	}
			
	public function add_plugin_page() {
		add_menu_page( 'Review Raffles', 'Review Raffles', 'administrator', 'review-raffles', array($this,'create_admin_page'), 'dashicons-tickets-alt' );
		add_submenu_page('review-raffles', 'License Settings', 'License', 'manage_options', 'twwt-plugin-license', array($this, 'twwt_plugin_license_page'));
	}
						
	public function create_admin_page() {
		 if (!$this->twwt_license_key_valid()) {
            wp_redirect(admin_url('admin.php?page=twwt-plugin-license'));
            exit;
        }
		$this->options = get_option( 'twwt_woo_settings' );
		if(!$this->options){
			twwt_woo_set_options();
			$this->options = get_option( 'twwt_woo_settings' );
		}
		?>
		<div class="wrap">

				<form method="post" action="options.php" autocomplete="off">
				<!-- Hidden dummy fields to absorb Chrome autofill -->
				<input type="text" name="twwt_fake_user" style="display:none" tabindex="-1" autocomplete="username" />
				<input type="password" name="twwt_fake_pass" style="display:none" tabindex="-1" autocomplete="current-password" />
				<?php
						settings_fields( 'twwt_woo_settings' );
						do_settings_sections( 'twwt_woo-settings' );
						submit_button();
				?>
				</form>
		</div>
		<?php
	}

	public function twwt_plugin_license_page() {
		$this->options = get_option( 'twwt_plugin_license_options' );
				if(!$this->options){
					twwt_woo_set_options();
					$this->options = get_option( 'twwt_plugin_license_options' );
				}

		// Handle manual license test
		$test_results = null;
		if ( isset( $_POST['twwt_test_license'] ) && check_admin_referer( 'twwt_test_license_nonce' ) ) {
			// Clear all caches to force a fresh remote check
			delete_transient( 'twwt_license_valid_cache' );
			delete_option( 'twwt_plugin_local_key' );

			$license_key = isset( $this->options['twwt_plugin_license_key'] )
				? sanitize_text_field( $this->options['twwt_plugin_license_key'] )
				: '';

			if ( ! empty( $license_key ) ) {
				$test_results = $this->firearm_check_license( $license_key, '' );
				// Re-cache if active
				if ( isset( $test_results['status'] ) && $test_results['status'] == 'Active' ) {
					if ( ! empty( $test_results['localkey'] ) ) {
						update_option( 'twwt_plugin_local_key', $test_results['localkey'] );
					}
					set_transient( 'twwt_license_valid_cache', 'yes', DAY_IN_SECONDS );
				} else {
					set_transient( 'twwt_license_valid_cache', 'no', DAY_IN_SECONDS );
				}
			} else {
				$test_results = array( 'status' => 'Error', 'description' => 'No license key entered.' );
			}
		}

	    ?>
	   		<div class="wrap">
					<form method="post" action="options.php" autocomplete="off">
					<?php
							settings_fields( 'twwt_plugin_license_options' );
							do_settings_sections( 'twwt_plugin_license_options' );
							submit_button();
					?>
					</form>

					<hr />
					<h2>Test License Connection</h2>
					<p>Clears the 24-hour cache and sends a live validation request to the WHMCS server. Check your WHMCS System Activity Log after clicking.</p>
					<form method="post">
						<?php wp_nonce_field( 'twwt_test_license_nonce' ); ?>
						<input type="submit" name="twwt_test_license" class="button button-secondary" value="Test License Now" />
					</form>

					<?php if ( $test_results !== null ) : ?>
					<div style="margin-top: 15px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid <?php echo ( isset($test_results['status']) && $test_results['status'] === 'Active' ) ? '#00a32a' : '#d63638'; ?>;">
						<h3 style="margin-top:0;">WHMCS Response</h3>
						<table class="widefat striped" style="max-width: 600px;">
							<tbody>
							<?php foreach ( $test_results as $key => $value ) :
								if ( $key === 'localkey' ) { $value = substr( $value, 0, 40 ) . '...'; }
							?>
								<tr>
									<td><strong><?php echo esc_html( $key ); ?></strong></td>
									<td><?php echo esc_html( $value ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p style="margin-top:10px;color:#666;">
							Tested at: <?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?><br>
							Domain sent: <code><?php echo esc_html( $_SERVER['SERVER_NAME'] ); ?></code><br>
							IP sent: <code><?php echo esc_html( isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : ( isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : 'unknown' ) ); ?></code><br>
							Directory sent: <code><?php echo esc_html( dirname( __FILE__ ) ); ?></code>
						</p>
					</div>
					<?php endif; ?>
			</div>
	    <?php
	}

			
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$sanitized = array();
		$text_fields = array(
			'login_button_text',
			'twilio_sid', 'twilio_token', 'twilio_from',
			'ottertext_api_key', 'ottertext_partner', 'aiq_api_key',
			'new_product_notification_sub', 'wwinner_notification_sub',
			'winner_noti_others_sub', 'ezoom_notification_sub',
			'batch_new_product_notification_sub',
			'sms_new_product_notification', 'sms_wwinner_notification',
			'sms_winner_noti_others', 'sms_zoom_notification',
			'sms_batch_new_product_notification',
			'notification_batch_time', 'twwt_plugin_license_key',
		);
		$color_fields = array(
			'winner_primary_color', 'winner_primary_hover',
			'winner_table_header_bg', 'winner_button_text',
		);
		$html_fields = array(
			'new_product_notification', 'wwinner_notification',
			'winner_noti_others', 'ezoom_notification',
			'batch_new_product_notification',
		);
		$int_fields = array(
			'login_restrict', 'randomGenerator_restrict', 'producttab',
			'winner_noto_others', 'enable_notification', 'video_show',
			'bootstrap_show', 'winner',
			'default_webinar_category', 'seat_hold_minutes',
		);
		foreach ( $text_fields as $key ) {
			$sanitized[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
		}
		foreach ( $html_fields as $key ) {
			$sanitized[$key] = isset($input[$key]) ? wp_kses_post($input[$key]) : '';
		}
		foreach ( $int_fields as $key ) {
			$sanitized[$key] = isset($input[$key]) ? intval($input[$key]) : 0;
		}
		foreach ( $color_fields as $key ) {
			$sanitized[$key] = isset($input[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $input[$key])
				? $input[$key]
				: '';
		}
		if ( isset($input['sms_provider']) && in_array($input['sms_provider'], array('twilio', 'ottertext', 'aiq'), true) ) {
			$sanitized['sms_provider'] = $input['sms_provider'];
		} else {
			$sanitized['sms_provider'] = 'twilio';
		}
		if ( isset($input['notification_mode']) && in_array($input['notification_mode'], array('immediate', 'daily'), true) ) {
			$sanitized['notification_mode'] = $input['notification_mode'];
		} else {
			$sanitized['notification_mode'] = 'immediate';
		}
		return $sanitized;
	}

	public function page_init() {
		register_setting(
				'twwt_woo_settings',
				'twwt_woo_settings',
				array( $this, 'sanitize' )
		);
		
		add_settings_section(
				'twwt_woo_settings_behaviour',
				'Review Raffles Basic Settings',
				array( $this, 'twwt_woo_settings_behaviour_header' ),
				'twwt_woo-settings'
		);

		add_settings_section(
				'twwt_woo_settings_twilio',
				'Twilio Settings',
				array( $this, 'twwt_woo_settings_twilio_header' ),
				'twwt_woo-settings'
		);
		
		add_settings_section(
				'twwt_woo_settings_sms_provider',
				'SMS Provider',
				array( $this, 'twwt_woo_settings_sms_provider_header' ),
				'twwt_woo-settings'
		);

		add_settings_field(
				'sms_provider',
				'Active Provider',
				array( $this, 'sms_provider_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_provider'
		);

		add_settings_field(
				'ottertext_api_key',
				'OtterText API Key',
				array( $this, 'ottertext_api_key_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_provider'
		);

		add_settings_field(
				'ottertext_partner',
				'OtterText Partner (optional)',
				array( $this, 'ottertext_partner_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_provider'
		);

		add_settings_field(
			'aiq_api_key',
			'AIQ API Key',
			array( $this, 'aiq_api_key_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_sms_provider'
		);

		add_settings_section(
				'twwt_woo_settings_email_template',
				'Email Template',
				array( $this, 'twwt_woo_settings_email_template_header' ),
				'twwt_woo-settings'
		);
		
		add_settings_section(
				'twwt_woo_settings_sms_content',
				'SMS Content',
				array( $this, 'twwt_woo_settings_sms_content_header' ),
				'twwt_woo-settings'
		);
        
		add_settings_field(
			'randomGenerator_restrict',
			'Random Seat Generator',
			array( $this, 'randomGenerator_restrict_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);

		add_settings_field(
			'winner_noto_others',
			'Winner Notification to Others',
			array( $this, 'winner_noto_others_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);
		add_settings_field(
			'producttab',
			'Enable Participant Tab',
			array( $this, 'producttab_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);
		add_settings_field(
				'login_restrict',
				'Login Restriction',
				array( $this, 'login_restrict_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_behaviour'
		);
		add_settings_field(
				'login_button_text',
				'Login Button Text',
				array( $this, 'login_button_text_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_behaviour'
		);
		add_settings_field(
				'winner',
				'Enable Winner Selection',
				array( $this, 'winner_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_behaviour'
		);
		add_settings_field(
				'enable_notification',
				'Enable Notification',
				array( $this, 'enable_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_behaviour'
		);

		add_settings_field(
			'notification_mode',
			'Notification Mode',
			array( $this, 'notification_mode_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);

		add_settings_field(
			'notification_batch_time',
			'Batch Time (HH:MM)',
			array( $this, 'notification_batch_time_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);

		add_settings_field(
			'seat_hold_minutes',
			'Seat Hold Timer (minutes)',
			array( $this, 'seat_hold_minutes_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);

		add_settings_field(
				'video_show',
				'Show Video',
				array( $this, 'video_show_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_behaviour'
		);
		add_settings_field(
				'bootstrap_show',
				'Add Bootstrap To Winner Page',
				array( $this, 'bootstrap_show_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_behaviour'
		);
		add_settings_field(
				'twilio_sid',
				'SID',
				array( $this, 'twilio_sid_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_twilio'
		);
		add_settings_field(
				'twilio_token',
				'Token',
				array( $this, 'twilio_token_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_twilio'
		);
		add_settings_field(
				'twilio_from',
				'From Number',
				array( $this, 'twilio_from_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_twilio'
		);
		add_settings_field(
				'new_product_notification_sub',
				'New Product Notification<br>(Subject)',
				array( $this, 'new_product_notification_sub_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'new_product_notification',
				'New Product Notification<br>(Email Body)',
				array( $this, 'new_product_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'wwinner_notification_sub',
				'Winner Notification to Winner (Subject)',
				array( $this, 'wwinner_notification_sub_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'wwinner_notification',
				'Winner Notification to Winner (Email Body)',
				array( $this, 'wwinner_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'winner_noti_others_sub',
				'Winner Notification to Others (Subject)',
				array( $this, 'winner_noti_others_sub_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'winner_noti_others',
				'Winner Notification to Others (Email Body)',
				array( $this, 'winner_noti_others_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'ezoom_notification_sub',
				'Zoom Notification <br>(Subject)',
				array( $this, 'ezoom_notification_sub_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
				'ezoom_notification',
				'Zoom Notification <br>(Email Body)',
				array( $this, 'ezoom_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_email_template'
		);
		add_settings_field(
			'batch_new_product_notification_sub',
			'Batch Webinar Notification<br>(Subject)',
			array($this, 'batch_new_product_notification_sub_callback'),
			'twwt_woo-settings',
			'twwt_woo_settings_email_template'
		);

		add_settings_field(
			'batch_new_product_notification',
			'Batch Webinar Notification<br>(Email Body)',
			array($this, 'batch_new_product_notification_callback'),
			'twwt_woo-settings',
			'twwt_woo_settings_email_template'
		);

		add_settings_field(
				'sms_new_product_notification',
				'New Product Notification<br>(SMS)',
				array( $this, 'sms_new_product_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_content'
		);
		add_settings_field(
				'sms_wwinner_notification',
				'Winner Notification to Winner (SMS)',
				array( $this, 'sms_wwinner_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_content'
		);
		add_settings_field(
				'sms_winner_noti_others',
				'Winner Notification to Others SMS)',
				array( $this, 'sms_winner_noti_others_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_content'
		);
		add_settings_field(
				'sms_zoom_notification',
				'Zoom Notification<br>(SMS)',
				array( $this, 'sms_zoom_notification_callback' ),
				'twwt_woo-settings',
				'twwt_woo_settings_sms_content'
		);
		add_settings_field(
			'sms_batch_new_product_notification',
			'Batch Webinar Notification<br>(SMS)',
			array($this, 'sms_batch_new_product_notification_callback'),
			'twwt_woo-settings',
			'twwt_woo_settings_sms_content'
		);

		register_setting(
				'twwt_plugin_license_options',
				'twwt_plugin_license_options',
				array( $this, 'sanitize' )
		);

        add_settings_section(
            'twwt_plugin_license_options_behaviour',
            'License Settings',
            array($this, 'licence_section_info'),
            'twwt_plugin_license_options'
        );

        add_settings_field(
            'twwt_plugin_license_key',
            'License Key',
            array($this, 'license_key_callback'),
            'twwt_plugin_license_options',
            'twwt_plugin_license_options_behaviour'
        );

		add_settings_field(
			'default_webinar_category',
			'Default Webinar Category',
			array($this, 'default_webinar_category_callback'),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);

		// --- Winner Page Appearance ---
		add_settings_section(
			'twwt_woo_settings_winner_colors',
			'Winner Page Appearance',
			array( $this, 'twwt_woo_settings_winner_colors_header' ),
			'twwt_woo-settings'
		);

		add_settings_field(
			'winner_primary_color',
			'Primary Color',
			array( $this, 'winner_primary_color_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_winner_colors'
		);

		add_settings_field(
			'winner_primary_hover',
			'Primary Hover Color',
			array( $this, 'winner_primary_hover_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_winner_colors'
		);

		add_settings_field(
			'winner_table_header_bg',
			'Table Header Background',
			array( $this, 'winner_table_header_bg_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_winner_colors'
		);

		add_settings_field(
			'winner_button_text',
			'Button Text Color',
			array( $this, 'winner_button_text_callback' ),
			'twwt_woo-settings',
			'twwt_woo_settings_winner_colors'
		);

	}

	public function twwt_woo_settings_behaviour_header() {
            echo '<p>General plugin behavior: notifications, winner selection, login restrictions, and display options.</p>';
	}
	public function twwt_woo_settings_twilio_header() {
            echo '<p>Enter your Twilio account credentials. Required when using Twilio as the SMS provider.</p>';
	}

	public function twwt_woo_settings_sms_provider_header() {
		echo '<p>Select which SMS provider to use. Keep your Twilio settings as-is; set OtterText API key if choosing OtterText.</p>';
	}
	public function sms_provider_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['sms_provider']) ? $this->options['sms_provider'] : 'twilio';
		?>
		<select id="sms_provider" name="twwt_woo_settings[sms_provider]">
			<option value="twilio"   <?php selected($val, 'twilio'); ?>>Twilio</option>
			<option value="ottertext"<?php selected($val, 'ottertext'); ?>>OtterText</option>
			<option value="aiq"      <?php selected($val, 'aiq'); ?>>AIQ</option>
		</select>
		<?php
	}
	public function ottertext_api_key_callback(){
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['ottertext_api_key']) ? esc_attr($this->options['ottertext_api_key']) : '';
		echo '<input type="password" id="ottertext_api_key" name="twwt_woo_settings[ottertext_api_key]" value="'.$val.'" class="regular-text" autocomplete="off" />';
	}
	public function ottertext_partner_callback(){
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['ottertext_partner']) ? esc_attr($this->options['ottertext_partner']) : '';
		echo '<input type="text" id="ottertext_partner" name="twwt_woo_settings[ottertext_partner]" value="'.$val.'" class="regular-text" autocomplete="off" />';
	}
	public function aiq_api_key_callback(){
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['aiq_api_key'])
			? esc_attr($this->options['aiq_api_key'])
			: '';

		echo '<input type="password"
			id="aiq_api_key"
			name="twwt_woo_settings[aiq_api_key]"
			value="'.$val.'"
			class="regular-text"
			autocomplete="off" />';

		echo '<p class="description">Alpine IQ API key used for sending SMS.</p>';
	}

	public function twwt_woo_settings_email_template_header() {
            echo '<p>Configure the email subject and body for each notification type. Use the merge tags listed under each field to personalize your messages.</p>';
	}
	public function twwt_woo_settings_sms_content_header() {
            echo '<p>Configure the SMS text for each notification type. Keep messages concise (160 characters recommended). Use the merge tags listed under each field.</p>';
	}
	public function randomGenerator_restrict_callback(){
		if(isset( $this->options['randomGenerator_restrict'] ) && $this->options['randomGenerator_restrict'] == 1){
		$yes = ' selected="selected"';
		$no = '';
	} else {
		$yes = '';
		$no = ' selected="selected"';
	}
	echo '<select id="randomGenerator_restrict" name="twwt_woo_settings[randomGenerator_restrict]">
		<option value="1"'.$yes.'>Yes</option>
		<option value="0"'.$no.'>No</option>
	</select>';
	echo '<p class="description">When enabled, the random.org number generator is shown on the winner selection page to help pick a random seat.</p>';
	}
	public function winner_noto_others_callback(){
		if(isset( $this->options['winner_noto_others'] ) && $this->options['winner_noto_others'] == 1){
		$yes = ' selected="selected"';
		$no = '';
	} else {
		$yes = '';
		$no = ' selected="selected"';
	}
	echo '<select id="winner_noto_others" name="twwt_woo_settings[winner_noto_others]">
		<option value="1"'.$yes.'>Yes</option>
		<option value="0"'.$no.'>No</option>
	</select>';
	echo '<p class="description">When enabled, all attendees (not just the winner) receive a notification when a winner is selected.</p>';
	}
	public function producttab_callback() {
		if(isset( $this->options['producttab'] ) && $this->options['producttab'] == 1){
		$yes = ' selected="selected"';
		$no = '';
	} else {
		$yes = '';
		$no = ' selected="selected"';
	}
	echo '<select id="producttab" name="twwt_woo_settings[producttab]">
		<option value="1"'.$yes.'>Yes</option>
		<option value="0"'.$no.'>No</option>
	</select>';
	echo '<p class="description">Show a "Participants" tab on the webinar product page listing attendees and their seat numbers.</p>';
	}
	public function login_restrict_callback() {
			if(isset( $this->options['login_restrict'] ) && $this->options['login_restrict'] == 1){
			$yes = ' selected="selected"';
			$no = '';
		} else {
			$yes = '';
			$no = ' selected="selected"';
		}
		echo '<select id="login_restrict" name="twwt_woo_settings[login_restrict]">
			<option value="1"'.$yes.'>Yes</option>
			<option value="0"'.$no.'>No</option>
		</select>';
		echo '<p class="description">When enabled, only logged-in users can view and purchase webinar seats. Guests see a login prompt.</p>';
	}
	public function login_button_text_callback(){
		echo '<input type="text" id="login_button_text" name="twwt_woo_settings[login_button_text]" size="50" value="'.esc_attr($this->options['login_button_text']).'" autocomplete="off" />';
		echo '<p class="description">Text shown on the login button when login restriction is enabled.</p>';
	}
	public function winner_callback(){
		if(isset( $this->options['winner'] ) && $this->options['winner'] == 1){
			$yes = ' selected="selected"';
			$no = '';
		} else {
			$yes = '';
			$no = ' selected="selected"';
		}
		echo '<select id="winner" name="twwt_woo_settings[winner]">
			<option value="1"'.$yes.'>Yes</option>
			<option value="0"'.$no.'>No</option>
		</select>';
		echo '<p class="description">Enable the "Select Attendee" button on product pages and the winner selection page for raffles.</p>';
	}
	public function enable_notification_callback(){
		if(isset( $this->options['enable_notification'] ) && $this->options['enable_notification'] == 1){
			$yes = ' selected="selected"';
			$no = '';
		} else {
			$yes = '';
			$no = ' selected="selected"';
		}
		echo '<select id="enable_notification" name="twwt_woo_settings[enable_notification]">
			<option value="1"'.$yes.'>Yes</option>
			<option value="0"'.$no.'>No</option>
		</select>';
		echo '<p class="description">Master switch for all email and SMS notifications. When disabled, no notifications are sent.</p>';
	}
	public function notification_mode_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['notification_mode']) ? $this->options['notification_mode'] : 'immediate';
		?>
		<label><input type="radio" name="twwt_woo_settings[notification_mode]" value="immediate" <?php checked('immediate', $val); ?> /> Immediate — send each time a webinar is published.</label><br>
		<label><input type="radio" name="twwt_woo_settings[notification_mode]" value="daily" <?php checked('daily', $val); ?> /> Daily batch — collect new webinars and send a single notification each day.</label>
		<?php
	}

	public function notification_batch_time_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['notification_batch_time']) ? esc_attr($this->options['notification_batch_time']) : '09:00';
		$mode = isset($this->options['notification_mode']) ? $this->options['notification_mode'] : 'immediate';

		$tz_string = get_option( 'timezone_string' );
		if ( function_exists('wp_timezone') ) {
			$tz = wp_timezone();
		} else {
			$tz = new DateTimeZone( $tz_string ? $tz_string : ( get_option('gmt_offset', 0) ? 'UTC' : 'UTC' ) );
		}

		$now = new DateTime('now', $tz);
		$now_label = $now->format('Y-m-d H:i:s');

		if ( empty( $tz_string ) ) {
			$offset = $tz->getOffset( $now );
			$sign = $offset >= 0 ? '+' : '-';
			$offset_hours = floor( abs( $offset ) / 3600 );
			$offset_minutes = ( abs( $offset ) % 3600 ) / 60;
			$tz_label = sprintf('UTC%s%02d:%02d', $sign, $offset_hours, $offset_minutes);
		} else {
			$offset = $tz->getOffset( $now );
			$sign = $offset >= 0 ? '+' : '-';
			$offset_hours = floor( abs( $offset ) / 3600 );
			$offset_minutes = ( abs( $offset ) % 3600 ) / 60;
			$tz_label = sprintf('%s (UTC%s%02d:%02d)', $tz_string, $sign, $offset_hours, $offset_minutes);
		}

		echo '<input type="time" id="notification_batch_time" name="twwt_woo_settings[notification_batch_time]" value="' . $val . '" />';

		echo '<p class="description">Current site time (' . esc_html( $tz_label ) . '): <strong>' . esc_html( $now_label ) . '</strong></p>';

		if ( $mode === 'daily' ) {
			if ( preg_match('/^(\d{2}):(\d{2})$/', $val, $m) ) {
				$hour = intval($m[1]);
				$minute = intval($m[2]);

				$next = new DateTime('now', $tz);
				$next->setTime($hour, $minute, 0);
				if ( $next <= $now ) {
					$next->modify('+1 day');
				}
				$next_label = $next->format('Y-m-d H:i:s');

				echo '<p class="description">Batch will run daily at <strong>' . esc_html($val) . '</strong> (site timezone). <br>';
				echo 'Next occurrence (calculated): <strong>' . esc_html( $next_label ) . '</strong></p>';
			} else {
				echo '<p class="description">Batch time format is invalid; use HH:MM.</p>';
			}

			$scheduled_ts = wp_next_scheduled('twwt_daily_batch_hook');
			if ( $scheduled_ts ) {
				$dt_scheduled = new DateTime('@' . $scheduled_ts);
				$dt_scheduled->setTimezone($tz);
				echo '<p class="description">WP-Cron next-scheduled run for <code>twwt_daily_batch_hook</code>: <strong>' . esc_html( $dt_scheduled->format('Y-m-d H:i:s') ) . '</strong> (site timezone)</p>';
			} else {
				echo '<p class="description">WP-Cron event for the daily batch is not scheduled yet. It will be created/updated when you save settings.</p>';
			}

			echo '<p class="description">After saving these settings, the plugin will (re)schedule the daily batch to run at the chosen time in the site timezone.</p>';
		} else {
			echo '<p class="description">Batch timing hidden while Notification Mode is set to Immediate.</p>';
		}
	}

	public function seat_hold_minutes_callback(){
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['seat_hold_minutes']) ? intval($this->options['seat_hold_minutes']) : 10;
		echo '<input type="number" id="seat_hold_minutes" name="twwt_woo_settings[seat_hold_minutes]" value="' . esc_attr($val) . '" min="1" max="120" step="1" style="width:80px;" />';
		echo '<p class="description">How long (in minutes) a seat is held in the cart before it is released. Minimum 1 minute, default 10.</p>';
	}
	public function video_show_callback(){
		if(isset( $this->options['video_show'] ) && $this->options['video_show'] == 1){
			$yes = ' selected="selected"';
			$no = '';
		} else {
			$yes = '';
			$no = ' selected="selected"';
		}
		echo '<select id="video_show" name="twwt_woo_settings[video_show]">
			<option value="1"'.$yes.'>To All</option>
			<option value="0"'.$no.'>Purchased Customer</option>
		</select>';
		echo '<p class="description">Controls who can see webinar replay videos. "To All" shows videos publicly; "Purchased Customer" restricts them to customers who bought the webinar.</p>';
	}
	public function bootstrap_show_callback(){
		if(isset( $this->options['bootstrap_show'] ) && $this->options['bootstrap_show'] == 1){
			$yes = ' selected="selected"';
			$no = '';
		} else {
			$yes = '';
			$no = ' selected="selected"';
		}
		echo '<select id="bootstrap_show" name="twwt_woo_settings[bootstrap_show]">
			<option value="1"'.$yes.'>Yes</option>
			<option value="0"'.$no.'>No</option>
		</select>';
		echo '<p class="description">Load Bootstrap CSS/JS on the winner selection page. Disable if your theme already includes Bootstrap or if it causes style conflicts.</p>';
		wp_enqueue_style( 'twwt_woo_date', plugins_url('asset/css/jquery-ui.min.css',__FILE__ ), array(), TWWT_VERSION );
		wp_enqueue_style( 'twwt_woo_time', plugins_url('asset/css/jquery-ui-timepicker-addon.min.css',__FILE__ ), array(), TWWT_VERSION );
		wp_enqueue_script( 'twwt_woo_timepicker', plugins_url('asset/js/jquery-ui-timepicker-addon.min.js',__FILE__ ), array('jquery'), TWWT_VERSION, true );
	}
	public function twilio_sid_callback(){
		echo '<input type="text" id="twilio_sid" name="twwt_woo_settings[twilio_sid]" size="50" value="'.esc_attr($this->options['twilio_sid']).'" autocomplete="off" />';
		echo '<p class="description">Your Twilio Account SID, found on the Twilio Console dashboard.</p>';
	}
	public function twilio_token_callback(){
		echo '<input type="text" id="twilio_token" name="twwt_woo_settings[twilio_token]" size="50" value="'.esc_attr($this->options['twilio_token']).'" autocomplete="off" />';
		echo '<p class="description">Your Twilio Auth Token, found on the Twilio Console dashboard.</p>';
	}
	public function twilio_from_callback(){
		echo '<input type="text" id="twilio_from" name="twwt_woo_settings[twilio_from]" size="50" value="'.esc_attr($this->options['twilio_from']).'" autocomplete="off" />';
		echo '<p class="description">The Twilio phone number to send SMS from (e.g. +15551234567).</p>';
	}

	public function new_product_notification_sub_callback(){
		echo '<p class="description">Sent to all customers when a new webinar product is published.<br>Available merge tags: <b>%first_name%</b>, <b>%product_name%</b>, <b>%url%</b></p>';
		echo '<input type="text" id="new_product_notification_sub" name="twwt_woo_settings[new_product_notification_sub]" size="180" autocomplete="off" value="'.esc_attr($this->options['new_product_notification_sub']).'" />';
	}
	public function new_product_notification_callback() {
		$content = $this->options['new_product_notification'];

		$editor_id = 'new_product_notification';

		echo '<p class="description">Email body sent to all customers when a new webinar is published.<br>Available merge tags: <b>%first_name%</b> (customer first name), <b>%product_name%</b> (webinar title), <b>%url%</b> (link to the webinar product page)</p>';
		$settings = array(
			'textarea_name' => 'twwt_woo_settings[new_product_notification]',
			'textarea_rows' => 10,
		);

		wp_editor($content, $editor_id, $settings);
	}
	public function wwinner_notification_sub_callback(){
		echo '<p class="description">Sent to the selected winner when they are chosen from the winner selection page.<br>Available merge tags: <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b>, <b>%phone%</b></p>';
		echo '<input type="text" id="wwinner_notification_sub" name="twwt_woo_settings[wwinner_notification_sub]" size="180" autocomplete="off" value="'.esc_attr($this->options['wwinner_notification_sub']).'" />';
	}
	public function wwinner_notification_callback() {
		$content1 = $this->options['wwinner_notification'];

		$editor_id1 = 'wwinner_notification';

		echo '<p class="description">Email body sent to the winner.<br>Available merge tags: <b>%first_name%</b> (winner first name), <b>%last_name%</b> (winner last name), <b>%product_name%</b> (webinar title), <b>%screen_name%</b> (winner display name), <b>%phone%</b> (winner phone)</p>';

		$settings1 = array(
			'textarea_name' => 'twwt_woo_settings[wwinner_notification]',
			'textarea_rows' => 10,
		);
		wp_editor($content1, $editor_id1, $settings1);
	}
	public function winner_noti_others_sub_callback(){
		echo '<p class="description">Sent to all other attendees (non-winners) when a winner is selected. Requires "Winner Notification to Others" to be enabled.<br>Available merge tags: <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b> (winner screen name), <b>%phone%</b></p>';
		echo '<input type="text" id="winner_noti_others_sub" name="twwt_woo_settings[winner_noti_others_sub]" size="180" autocomplete="off" value="'.esc_attr($this->options['winner_noti_others_sub']).'" />';
	}
	public function winner_noti_others_callback() {
		$content2 = $this->options['winner_noti_others'];

		$editor_id2 = 'winner_noti_others';
		echo '<p class="description">Email body sent to all other attendees when a winner is selected.<br>Available merge tags: <b>%first_name%</b> (attendee first name), <b>%last_name%</b> (attendee last name), <b>%product_name%</b> (webinar title), <b>%screen_name%</b> (winner display name), <b>%phone%</b></p>';

		$settings2 = array(
			'textarea_name' => 'twwt_woo_settings[winner_noti_others]',
			'textarea_rows' => 10,
		);

		wp_editor($content2, $editor_id2, $settings2);
	}
	public function ezoom_notification_sub_callback(){
		echo '<p class="description">Sent to all attendees when the admin triggers the Zoom/event notification from the product edit page.<br>Available merge tags: <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%event_url%</b>, <b>%event_time%</b></p>';
		echo '<input type="text" id="ezoom_notification_sub" name="twwt_woo_settings[ezoom_notification_sub]" size="180" autocomplete="off" value="'.esc_attr($this->options['ezoom_notification_sub']).'" />';
	}
	public function ezoom_notification_callback() {
		$content3 = $this->options['ezoom_notification'];

		$editor_id3 = 'ezoom_notification';
		echo '<p class="description">Email body sent to attendees with the event/Zoom joining details.<br>Available merge tags: <b>%first_name%</b> (attendee first name), <b>%last_name%</b> (attendee last name), <b>%product_name%</b> (webinar title), <b>%event_url%</b> (Zoom/meeting link), <b>%event_time%</b> (scheduled event time)</p>';
		$settings3 = array(
			'textarea_name' => 'twwt_woo_settings[ezoom_notification]',
			'textarea_rows' => 10,
		);

		wp_editor($content3, $editor_id3, $settings3);
	}
	public function batch_new_product_notification_sub_callback() {
		echo '<p class="description">Subject line for the daily batch email that groups all new webinars published that day into a single message.<br>Available merge tags: <b>%first_name%</b></p>';
		echo '<input type="text" size="180"
			name="twwt_woo_settings[batch_new_product_notification_sub]"
			value="'. esc_attr($this->options['batch_new_product_notification_sub']) .'" />';
	}

	public function batch_new_product_notification_callback() {
		echo '<p class="description">Email body for the daily batch notification. Only used when Notification Mode is set to "Daily batch".<br>Available merge tags: <b>%first_name%</b> (customer first name), <b>%webinar_list%</b> (HTML list of new webinar names and links)</p>';

		wp_editor(
			$this->options['batch_new_product_notification'],
			'batch_new_product_notification',
			array(
				'textarea_name' => 'twwt_woo_settings[batch_new_product_notification]',
				'textarea_rows' => 10,
			)
		);
	}
	
	public function sms_new_product_notification_callback(){
		echo '<p class="description">SMS sent to all customers when a new webinar is published.<br>Merge tags: <b>%first_name%</b>, <b>%product_name%</b>, <b>%url%</b></p>';
		echo '<textarea id="sms_new_product_notification" name="twwt_woo_settings[sms_new_product_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_new_product_notification']) . '</textarea>';
	}
	public function sms_wwinner_notification_callback(){
		echo '<p class="description">SMS sent to the selected winner.<br>Merge tags: <b>%first_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b></p>';
		echo '<textarea id="sms_wwinner_notification" name="twwt_woo_settings[sms_wwinner_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_wwinner_notification']) . '</textarea>';
	}
	public function sms_winner_noti_others_callback(){
		echo '<p class="description">SMS sent to all other attendees when a winner is selected. Requires "Winner Notification to Others" to be enabled.<br>Merge tags: <b>%first_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b> (winner display name)</p>';
		echo '<textarea id="sms_winner_noti_others" name="twwt_woo_settings[sms_winner_noti_others]" rows="4" cols="180">' . esc_textarea($this->options['sms_winner_noti_others']) . '</textarea>';
	}
	public function sms_zoom_notification_callback(){
		echo '<p class="description">SMS sent to all attendees with event/Zoom details when the admin sends the Zoom notification.<br>Merge tags: <b>%first_name%</b>, <b>%product_name%</b>, <b>%event_url%</b>, <b>%event_time%</b></p>';
		echo '<textarea id="sms_zoom_notification" name="twwt_woo_settings[sms_zoom_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_zoom_notification']) . '</textarea>';
	}
	public function sms_batch_new_product_notification_callback() {
		echo '<p class="description">SMS for the daily batch notification. Only used when Notification Mode is set to "Daily batch".<br>Merge tags: <b>%webinar_titles%</b> (comma-separated list of new webinar names), <b>%url%</b> (shop link)</p>';

		echo '<textarea rows="4" cols="180"
			name="twwt_woo_settings[sms_batch_new_product_notification]">' .
			esc_textarea($this->options['sms_batch_new_product_notification']) .
			'</textarea>';
	}


	// --- Winner Page Color Callbacks ---
	public function twwt_woo_settings_winner_colors_header() {
		echo '<p>Customize the colors used on the winner selection page. Use any valid hex color code.</p>';
	}
	public function winner_primary_color_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['winner_primary_color']) ? esc_attr($this->options['winner_primary_color']) : '#d63638';
		echo '<input type="color" id="winner_primary_color" name="twwt_woo_settings[winner_primary_color]" value="' . $val . '" />';
		echo '<code style="margin-left:8px;">' . $val . '</code>';
		echo '<p class="description">Used for buttons, alert borders, and accent elements on the winner page.</p>';
	}
	public function winner_primary_hover_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['winner_primary_hover']) ? esc_attr($this->options['winner_primary_hover']) : '#b32d2f';
		echo '<input type="color" id="winner_primary_hover" name="twwt_woo_settings[winner_primary_hover]" value="' . $val . '" />';
		echo '<code style="margin-left:8px;">' . $val . '</code>';
		echo '<p class="description">Button hover/active state color on the winner page.</p>';
	}
	public function winner_table_header_bg_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['winner_table_header_bg']) ? esc_attr($this->options['winner_table_header_bg']) : '#f5f5f5';
		echo '<input type="color" id="winner_table_header_bg" name="twwt_woo_settings[winner_table_header_bg]" value="' . $val . '" />';
		echo '<code style="margin-left:8px;">' . $val . '</code>';
		echo '<p class="description">Background color for the attendee table header row.</p>';
	}
	public function winner_button_text_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['winner_button_text']) ? esc_attr($this->options['winner_button_text']) : '#ffffff';
		echo '<input type="color" id="winner_button_text" name="twwt_woo_settings[winner_button_text]" value="' . $val . '" />';
		echo '<code style="margin-left:8px;">' . $val . '</code>';
		echo '<p class="description">Text color for buttons on the winner page.</p>';
	}

	public function default_webinar_category_callback() {
		$this->options = get_option('twwt_woo_settings');
		$selected = isset($this->options['default_webinar_category'])
			? intval($this->options['default_webinar_category'])
			: '';

		$categories = get_terms(array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		));

		echo '<select name="twwt_woo_settings[default_webinar_category]">';
		echo '<option value="">— Select Category —</option>';

		foreach ($categories as $cat) {
			echo '<option value="' . esc_attr($cat->term_id) . '" ' .
				selected($selected, $cat->term_id, false) . '>' .
				esc_html($cat->name) .
			'</option>';
		}

		echo '</select>';
		echo '<p class="description">This category will be pre-selected when adding a new webinar.</p>';
	}

    public function licence_section_info() {
        echo '<p>Enter your license key below:</p>';
    }

    public function license_key_callback() {
    	$this->options = get_option('twwt_plugin_license_options'); 
        $license_status = '';
        $license_key = isset($this->options['twwt_plugin_license_key']) ? sanitize_text_field($this->options['twwt_plugin_license_key']) : '';
        if (!empty($license_key)) {
            $license_status = $this->twwt_license_key_valid() ? 'Valid' : 'Invalid';
        }
        echo '<input type="password" name="twwt_plugin_license_options[twwt_plugin_license_key]" value="' . $license_key . '" />';
        echo '<span style="font-weight: bold; color: ' . ($license_status === 'Valid' ? 'green' : 'red') . ';"> ' . $license_status . '</span>';
    }

	public function twwt_license_key_valid() {

    $cached = get_transient('twwt_license_valid_cache');

    if ($cached === 'yes') {
        return true;
    }

    if ($cached === 'no') {
        return false;
    }

    $this->options = get_option('twwt_plugin_license_options');
    $localKey = get_option('twwt_plugin_local_key', '');
    $license_key = isset($this->options['twwt_plugin_license_key'])
        ? sanitize_text_field($this->options['twwt_plugin_license_key'])
        : '';

    $results = $this->firearm_check_license($license_key, $localKey);

    if ($results['status'] == 'Active') {
        update_option('twwt_plugin_local_key', $results['localkey']);

        set_transient('twwt_license_valid_cache', 'yes', DAY_IN_SECONDS);

        return true;
    } else {

        set_transient('twwt_license_valid_cache', 'no', DAY_IN_SECONDS);

        return false;
    }
}

public function twwt_license_invalidate_cache() {
    delete_transient('twwt_license_valid_cache');
}

	public function firearm_check_license($licensekey, $localkey='') {
	    $whmcsurl = 'https://hosting.gigapress.net/';
	    $licensing_secret_key = 'smcreative';
	    $localkeydays = 1;
	    $allowcheckfaildays = 1;
	    $check_token = time() . md5(mt_rand(1000000000, 9999999999) . $licensekey);
	    $checkdate = date("Ymd");
	    $domain = $_SERVER['SERVER_NAME'];
	    $usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];
	    $dirpath = dirname(__FILE__);
	    $verifyfilepath = 'modules/servers/licensing/verify.php';
	    $localkeyvalid = false;
	    if ($localkey) {
	        $localkey = str_replace("\n", '', $localkey);
	        $localdata = substr($localkey, 0, strlen($localkey) - 32);
	        $md5hash = substr($localkey, strlen($localkey) - 32);
	        if (hash_equals(md5($localdata . $licensing_secret_key), $md5hash)) {
	            $localdata = strrev($localdata);
	            $md5hash = substr($localdata, 0, 32);
	            $localdata = substr($localdata, 32);
	            $localdata = base64_decode($localdata);
	            $localkeyresults = json_decode($localdata, true);
	            if ( ! is_array($localkeyresults) ) {
	                $localkeyresults = @unserialize($localdata);
	            }
	            if ( ! is_array($localkeyresults) ) {
	                $localkeyresults = array();
	            }
	            $originalcheckdate = isset($localkeyresults['checkdate']) ? $localkeyresults['checkdate'] : '';
	            if (hash_equals(md5($originalcheckdate . $licensing_secret_key), $md5hash)) {
	                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));
	                if ($originalcheckdate > $localexpiry) {
	                    $localkeyvalid = true;
	                    $results = $localkeyresults;
	                    $validdomains = explode(',', $results['validdomain']);
	                    if (!in_array($_SERVER['SERVER_NAME'], $validdomains)) {
	                        $localkeyvalid = false;
	                        $localkeyresults['status'] = "Invalid";
	                        $results = array();
	                    }
	                    $validips = explode(',', $results['validip']);
	                    if (!in_array($usersip, $validips)) {
	                        $localkeyvalid = false;
	                        $localkeyresults['status'] = "Invalid";
	                        $results = array();
	                    }
	                    $validdirs = explode(',', $results['validdirectory']);
	                    if (!in_array($dirpath, $validdirs)) {
	                        $localkeyvalid = false;
	                        $localkeyresults['status'] = "Invalid";
	                        $results = array();
	                    }
	                }
	            }
	        }
	    }
	    if (!$localkeyvalid) {
			error_log('TWWT LICENSE: Remote WHMCS check triggered at ' . current_time('mysql'));
	        $postfields = array(
	            'licensekey' => $licensekey,
	            'domain' => $domain,
	            'ip' => $usersip,
	            'dir' => $dirpath,
	        );
	        if ($check_token) $postfields['check_token'] = $check_token;
	        $query_string = '';
	        foreach ($postfields AS $k=>$v) {
	            $query_string .= $k.'='.urlencode($v).'&';
	        }
	        if (function_exists('curl_exec')) {
	            $ch = curl_init();
	            curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
	            curl_setopt($ch, CURLOPT_POST, 1);
	            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
	            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	            $data = curl_exec($ch);
	            curl_close($ch);
	        } else {
	            $fp = fsockopen($whmcsurl, 80, $errno, $errstr, 5);
	            if ($fp) {
	                $newlinefeed = "\r\n";
	                $header = "POST ".$whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;
	                $header .= "Host: ".$whmcsurl . $newlinefeed;
	                $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;
	                $header .= "Content-length: ".@strlen($query_string) . $newlinefeed;
	                $header .= "Connection: close" . $newlinefeed . $newlinefeed;
	                $header .= $query_string;
	                $data = '';
	                @stream_set_timeout($fp, 20);
	                @fputs($fp, $header);
	                $status = @socket_get_status($fp);
	                while (!@feof($fp)&&$status) {
	                    $data .= @fgets($fp, 1024);
	                    $status = @socket_get_status($fp);
	                }
	                @fclose ($fp);
	            }
	        }
	        if (!$data) {
	            $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));
				$originalcheckdate = isset($originalcheckdate) ? $originalcheckdate : 0;
	            if ($originalcheckdate > $localexpiry) {
	                $results = $localkeyresults;
	            } else {
	                $results = array();
	                $results['status'] = "Invalid";
	                $results['description'] = "Remote Check Failed";
	                return $results;
	            }
	        } else {
	            preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);
	            $results = array();
	            foreach ($matches[1] AS $k=>$v) {
	                $results[$v] = $matches[2][$k];
	            }
	        }
	        if (!is_array($results)) {
	            return array('status' => 'Invalid', 'description' => 'Invalid License Server Response');
	        }
	        if (!empty($results['md5hash'])) {
	            if ($results['md5hash'] != md5($licensing_secret_key . $check_token)) {
	                $results['status'] = "Invalid";
	                $results['description'] = "MD5 Checksum Verification Failed";
	                return $results;
	            }
	        }
	        if ($results['status'] == "Active") {
	            $results['checkdate'] = $checkdate;
	            $data_encoded = wp_json_encode($results);
	            $data_encoded = base64_encode($data_encoded);
	            $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
	            $data_encoded = strrev($data_encoded);
	            $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
	            $data_encoded = wordwrap($data_encoded, 80, "\n", true);
	            $results['localkey'] = $data_encoded;
	        }
	        $results['remotecheck'] = true;
	    }
	    unset($postfields,$data,$matches,$whmcsurl,$licensing_secret_key,$checkdate,$usersip,$localkeydays,$allowcheckfaildays,$md5hash);
	    return $results;
	}
}

add_action('update_option_twwt_plugin_license_options', function () {
    delete_transient('twwt_license_valid_cache');
});


add_action('update_option_twwt_woo_settings', 'twwt_reschedule_on_option_change', 10, 3);
function twwt_reschedule_on_option_change($old_value, $value, $option_name) {
    if (function_exists('twwt_schedule_daily_batch')) {
        twwt_schedule_daily_batch();
    }
}


if( is_admin() ){
		$twwt_woo_settings_page = new twwt_woo_settings_page();
}