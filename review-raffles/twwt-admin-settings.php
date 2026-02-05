<?php

// Set up settings defaults
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
		'winner' => 0,
		'enable_livestrom' => 0,
		'token' => '',
		'owner_id' => '',
		'copy_from_event_id' => '',
		'id' => '',
		'twilio_sid' => '',
		'twilio_token' => '',
		'twilio_from' => '',

		/* ==== NEW (OtterText) ==== */
		'sms_provider'       => 'twilio', // 'twilio' (default) | 'ottertext'
		'ottertext_api_key'  => '',
		'ottertext_partner'  => '',
		/* ========================= */

		'new_product_notification_sub' => '',
		'new_product_notification' => '',
		'wwinner_notification_sub' => '',
		'wwinner_notification' => '',
		'winner_noti_others_sub' => '',
		'winner_noti_others' => '',
		'ezoom_notification_sub' => '',
		'ezoom_notification' => '',
		'elivestrom_notification_sub' => '',
		'elivestrom_notification' => '',
		'sms_new_product_notification' => '',
		'sms_wwinner_notification' => '',
		'sms_winner_noti_others' => '',
		'sms_zoom_notification' => '',
		'sms_livestrom_notification' => '',
		'batch_new_product_notification_sub' => 'Today\'s New Webinars',
		'batch_new_product_notification'     => '',
		'sms_batch_new_product_notification' => '',
		'twwt_plugin_license_key' => '',
		'notification_mode' => 'immediate',
		'notification_batch_time' => '09:00'
	);
	add_option('twwt_woo_settings', $defaults);
}
// Clean up on uninstall
// Clean up on uninstall — use deactivation hook (was incorrectly using activation hook)
register_deactivation_hook( __FILE__, 'twwt_woo_deactivate' );
function twwt_woo_deactivate(){
    delete_option('twwt_woo_settings');
}


// Render the settings page
class twwt_woo_settings_page {
	// Holds the values to be used in the fields callbacks
	private $options;
			
	// Start up
	public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'page_init' ) );
	}
			
	// Add settings page
	public function add_plugin_page() {
		//add_submenu_page('edit.php?post_type=twwt_woo', 'Settings',  'Settings', 'manage_options', 'twabc-settings', array($this,'create_admin_page'));	
		add_menu_page( 'Review Raffles', 'Review Raffles', 'administrator', 'review-raffles', array($this,'create_admin_page'), 'dashicons-tickets-alt' );
		//submenu page new
		add_submenu_page('review-raffles', 'License Settings', 'License', 'manage_options', 'twwt-plugin-license', array($this, 'twwt_plugin_license_page'));
	}
						
	// Options page callback
	public function create_admin_page() {
		 if (!$this->twwt_license_key_valid()) {
            // Redirect to the license page if the license is invalid
            wp_redirect(admin_url('admin.php?page=twwt-plugin-license'));
            exit;
        }
		// Set class property
		$this->options = get_option( 'twwt_woo_settings' );
		if(!$this->options){
			twwt_woo_set_options();
			$this->options = get_option( 'twwt_woo_settings' );
		}
		?>
		<div class="wrap">
							 
				<form method="post" action="options.php">
				<?php
						settings_fields( 'twwt_woo_settings' );   
						do_settings_sections( 'twwt_woo-settings' );
						submit_button(); 
				?>
				</form>
		</div>
		<?php
	}


	// submenu page callback function 
	public function twwt_plugin_license_page() {
		$this->options = get_option( 'twwt_plugin_license_options' );
				if(!$this->options){
					twwt_woo_set_options();
					$this->options = get_option( 'twwt_plugin_license_options' );
				}				
	    ?>
	   		<div class="wrap">								 
					<form method="post" action="options.php">
					<?php
							settings_fields( 'twwt_plugin_license_options' );   
							do_settings_sections( 'twwt_plugin_license_options' );
							submit_button(); 
					?>
					</form>
			</div>
	    <?php
	}

			
	// Register and add settings
	public function page_init() {		
		register_setting(
				'twwt_woo_settings', // Option group
				'twwt_woo_settings', // Option name
				array( $this, 'sanitize' ) // Sanitize
		);
		
        // Sections
		add_settings_section(
				'twwt_woo_settings_behaviour', // ID
				'Review Raffles Basic Settings', // Title
				array( $this, 'twwt_woo_settings_behaviour_header' ), // Callback
				'twwt_woo-settings' // Page
		);
		// Sections #2
		add_settings_section(
				'twwt_woo_settings_livestrom', // ID
				'Livestrom Settings', // Title
				array( $this, 'twwt_woo_settings_livestrom_header' ), // Callback
				'twwt_woo-settings' // Page
		);
		// Sections #3
		add_settings_section(
				'twwt_woo_settings_twilio', // ID
				'Twilio Settings', // Title
				array( $this, 'twwt_woo_settings_twilio_header' ), // Callback
				'twwt_woo-settings' // Page
		);
		
		/* ==== NEW (OtterText) - SMS Provider Section ==== */
		add_settings_section(
				'twwt_woo_settings_sms_provider', // ID
				'SMS Provider', // Title
				array( $this, 'twwt_woo_settings_sms_provider_header' ), // Callback
				'twwt_woo-settings' // Page
		);

		// Provider select
		add_settings_field(
				'sms_provider', // ID
				'Active Provider', // Title
				array( $this, 'sms_provider_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_provider' // Section
		);

		// OtterText API key
		add_settings_field(
				'ottertext_api_key', // ID
				'OtterText API Key', // Title
				array( $this, 'ottertext_api_key_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_provider' // Section
		);

		// OtterText Partner (optional)
		add_settings_field(
				'ottertext_partner', // ID
				'OtterText Partner (optional)', // Title
				array( $this, 'ottertext_partner_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_provider' // Section
		);
		/* ================================================ */
		
		// Sections #4
		add_settings_section(
				'twwt_woo_settings_email_template', // ID
				'Email Template', // Title
				array( $this, 'twwt_woo_settings_email_template_header' ), // Callback
				'twwt_woo-settings' // Page
		);
		
		// Sections #5
		add_settings_section(
				'twwt_woo_settings_sms_content', // ID
				'SMS Content', // Title
				array( $this, 'twwt_woo_settings_sms_content_header' ), // Callback
				'twwt_woo-settings' // Page
		);
        
		// Behaviour Fields
		add_settings_field(
			'randomGenerator_restrict', // ID
			'Random Seat Generator', // Title
			array( $this, 'randomGenerator_restrict_callback' ), // Callback randomGenerator_restrict
			'twwt_woo-settings', // Page
			'twwt_woo_settings_behaviour' // Section
		);
		// Behaviour Fields
		add_settings_field(
			'winner_noto_others', // ID
			'Winner Notification to Others', // Title
			array( $this, 'winner_noto_others_callback' ), // Callback winner_noto_others
			'twwt_woo-settings', // Page
			'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
			'producttab', // ID
			'Enable Participant Tab', // Title
			array( $this, 'producttab_callback' ), // Callback producttab
			'twwt_woo-settings', // Page
			'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
				'login_restrict', // ID
				'Login Restriction', // Title
				array( $this, 'login_restrict_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
				'login_button_text', // ID
				'Login Button Text', // Title
				array( $this, 'login_button_text_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
				'winner', // ID
				'Enable Winner Selection', // Title
				array( $this, 'winner_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
				'enable_notification', // ID
				'Enable Notification', // Title
				array( $this, 'enable_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_behaviour' // Section
		);
		// Notification mode (Immediate | Daily)
		add_settings_field(
			'notification_mode', // ID
			'Notification Mode', // Title
			array( $this, 'notification_mode_callback' ), // Callback
			'twwt_woo-settings', // Page
			'twwt_woo_settings_behaviour' // Section
		);

		// Batch time for daily mode
		add_settings_field(
			'notification_batch_time', // ID
			'Batch Time (HH:MM)', // Title
			array( $this, 'notification_batch_time_callback' ), // Callback
			'twwt_woo-settings', // Page
			'twwt_woo_settings_behaviour' // Section
		);

		add_settings_field(
				'video_show', // ID
				'Show Video', // Title
				array( $this, 'video_show_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
				'bootstrap_show', // ID
				'Add Bootstrap To Winner Page', // Title
				array( $this, 'bootstrap_show_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_behaviour' // Section
		);
		add_settings_field(
				'enable_livestrom', // ID
				'Enable Livestrom', // Title
				array( $this, 'enable_livestrom_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_livestrom' // Section
		);
		add_settings_field(
				'token', // ID
				'Token', // Title
				array( $this, 'token_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_livestrom' // Section
		);
		add_settings_field(
				'owner_id', // ID
				'Owner ID', // Title
				array( $this, 'owner_id_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_livestrom' // Section
		);
		add_settings_field(
				'copy_from_event_id', // ID
				'Copy From Event ID', // Title
				array( $this, 'copy_from_event_id_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_livestrom' // Section
		);
		
		add_settings_field(
				'twilio_sid', // ID
				'SID', // Title
				array( $this, 'twilio_sid_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_twilio' // Section
		);
		add_settings_field(
				'twilio_token', // ID
				'Token', // Title
				array( $this, 'twilio_token_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_twilio' // Section
		);
		add_settings_field(
				'twilio_from', // ID
				'From Number', // Title
				array( $this, 'twilio_from_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_twilio' // Section
		);
		add_settings_field(
				'new_product_notification_sub', // ID
				'New Product Notification<br>(Subject)', // Title
				array( $this, 'new_product_notification_sub_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'new_product_notification', // ID
				'New Product Notification<br>(Email Body)', // Title
				array( $this, 'new_product_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'wwinner_notification_sub', // ID
				'Winner Notification to Winner (Subject)', // Title
				array( $this, 'wwinner_notification_sub_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'wwinner_notification', // ID
				'Winner Notification to Winner (Email Body)', // Title
				array( $this, 'wwinner_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'winner_noti_others_sub', // ID
				'Winner Notification to Others (Subject)', // Title
				array( $this, 'winner_noti_others_sub_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'winner_noti_others', // ID
				'Winner Notification to Others (Email Body)', // Title
				array( $this, 'winner_noti_others_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'ezoom_notification_sub', // ID
				'Zoom Notification <br>(Subject)', // Title
				array( $this, 'ezoom_notification_sub_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'ezoom_notification', // ID
				'Zoom Notification <br>(Email Body)', // Title
				array( $this, 'ezoom_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'elivestrom_notification_sub', // ID
				'LiveStrom Notification<br>(Subject)', // Title
				array( $this, 'elivestrom_notification_sub_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
		);
		add_settings_field(
				'elivestrom_notification', // ID
				'LiveStrom Notification<br>(Email Body)', // Title
				array( $this, 'elivestrom_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_email_template' // Section
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
				'sms_new_product_notification', // ID
				'New Product Notification<br>(SMS)', // Title
				array( $this, 'sms_new_product_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_content' // Section
		);
		add_settings_field(
				'sms_wwinner_notification', // ID
				'Winner Notification to Winner (SMS)', // Title
				array( $this, 'sms_wwinner_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_content' // Section
		);
		add_settings_field(
				'sms_winner_noti_others', // ID
				'Winner Notification to Others SMS)', // Title
				array( $this, 'sms_winner_noti_others_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_content' // Section
		);
		add_settings_field(
				'sms_zoom_notification', // ID
				'Zoom Notification<br>(SMS)', // Title
				array( $this, 'sms_zoom_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_content' // Section
		);
		add_settings_field(
				'sms_livestrom_notification', // ID
				'Livestrom Event Notification (SMS)', // Title
				array( $this, 'sms_livestrom_notification_callback' ), // Callback
				'twwt_woo-settings', // Page
				'twwt_woo_settings_sms_content' // Section
		);
		add_settings_field(
			'sms_batch_new_product_notification',
			'Batch Webinar Notification<br>(SMS)',
			array($this, 'sms_batch_new_product_notification_callback'),
			'twwt_woo-settings',
			'twwt_woo_settings_sms_content'
		);

		// submenu setting 
		register_setting(
				'twwt_plugin_license_options', // Option group
				'twwt_plugin_license_options', // Option name
				array( $this, 'sanitize' ) // Sanitize
		);

		// submenu setting section 
		  // Add a settings section
        add_settings_section(
            'twwt_plugin_license_options_behaviour', // ID
            'License Settings', // Title
            array($this, 'licence_section_info'), // Callback
            'twwt_plugin_license_options' // Page
        );

       // submenu setting field new
        add_settings_field(
            'twwt_plugin_license_key', // ID
            'License Key', // Title
            array($this, 'license_key_callback'), // Callback
            'twwt_plugin_license_options', // Page
            'twwt_plugin_license_options_behaviour' // Section
        );

		add_settings_field(
			'default_webinar_category',
			'Default Webinar Category',
			array($this, 'default_webinar_category_callback'),
			'twwt_woo-settings',
			'twwt_woo_settings_behaviour'
		);

	}


	// Sanitize each setting field as needed -  @param array $input Contains all settings fields as array keys		
	// Print the Section text
	public function twwt_woo_settings_behaviour_header() {
            echo '<p>'.__('Settings for Review Raffles.', 'twwt_woo-settings').'</p>';
	}
	public function twwt_woo_settings_livestrom_header() {
            echo '<p>'.__('Settings for Livestrom.', 'twwt_woo-settings').'</p>';
	}
	public function twwt_woo_settings_twilio_header() {
            echo '<p>'.__('Settings for Twilio.', 'twwt_woo-settings').'</p>';
	}

	/* ==== NEW (OtterText) ==== */
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
		echo '<input type="text" id="ottertext_partner" name="twwt_woo_settings[ottertext_partner]" value="'.$val.'" class="regular-text" />';
	}
	/* ========================= */

	public function twwt_woo_settings_email_template_header() {
            echo '<p>'.__('Set Your Email Template here.', 'twwt_woo-settings').'</p>';
	}
	public function twwt_woo_settings_sms_content_header() {
            echo '<p>'.__('Set Your SMS Text here.', 'twwt_woo-settings').'</p>';
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
	}
	public function login_button_text_callback(){
		echo '<input type="text" id="login_button_text" name="twwt_woo_settings[login_button_text]" size="50" value="'.$this->options['login_button_text'].'" />';
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
	}
	// Notification mode callback
	public function notification_mode_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['notification_mode']) ? $this->options['notification_mode'] : 'immediate';
		?>
		<label><input type="radio" name="twwt_woo_settings[notification_mode]" value="immediate" <?php checked('immediate', $val); ?> /> Immediate — send each time a webinar is published.</label><br>
		<label><input type="radio" name="twwt_woo_settings[notification_mode]" value="daily" <?php checked('daily', $val); ?> /> Daily batch — collect new webinars and send a single notification each day.</label>
		<?php
	}

	// Notification batch time callback (shows current WP site time and next scheduled run)
	public function notification_batch_time_callback() {
		$this->options = get_option( 'twwt_woo_settings' );
		$val = isset($this->options['notification_batch_time']) ? esc_attr($this->options['notification_batch_time']) : '09:00';
		$mode = isset($this->options['notification_mode']) ? $this->options['notification_mode'] : 'immediate';

		// Determine WP timezone object & label
		$tz_string = get_option( 'timezone_string' );
		if ( function_exists('wp_timezone') ) {
			$tz = wp_timezone();
		} else {
			$tz = new DateTimeZone( $tz_string ? $tz_string : ( get_option('gmt_offset', 0) ? 'UTC' : 'UTC' ) );
		}

		// Current site time
		$now = new DateTime('now', $tz);
		$now_label = $now->format('Y-m-d H:i:s');

		// Determine timezone label for display
		if ( empty( $tz_string ) ) {
			$offset = $tz->getOffset( $now );
			$sign = $offset >= 0 ? '+' : '-';
			$offset_hours = floor( abs( $offset ) / 3600 );
			$offset_minutes = ( abs( $offset ) % 3600 ) / 60;
			$tz_label = sprintf('UTC%s%02d:%02d', $sign, $offset_hours, $offset_minutes);
		} else {
			// show timezone string and current offset
			$offset = $tz->getOffset( $now );
			$sign = $offset >= 0 ? '+' : '-';
			$offset_hours = floor( abs( $offset ) / 3600 );
			$offset_minutes = ( abs( $offset ) % 3600 ) / 60;
			$tz_label = sprintf('%s (UTC%s%02d:%02d)', $tz_string, $sign, $offset_hours, $offset_minutes);
		}

		// Render the time input
		echo '<input type="time" id="notification_batch_time" name="twwt_woo_settings[notification_batch_time]" value="' . $val . '" />';

		// Show current site time & timezone
		echo '<p class="description">Current site time (' . esc_html( $tz_label ) . '): <strong>' . esc_html( $now_label ) . '</strong></p>';

		// If daily mode selected, show next scheduled run computed from chosen HH:MM
		if ( $mode === 'daily' ) {
			// compute next run based on selected HH:MM in site timezone
			if ( preg_match('/^(\d{2}):(\d{2})$/', $val, $m) ) {
				$hour = intval($m[1]);
				$minute = intval($m[2]);

				// Create DateTime for next occurrence in site tz
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

			// Also show actual wp_next_scheduled value if exists (useful after saving)
			$scheduled_ts = wp_next_scheduled('twwt_daily_batch_hook');
			if ( $scheduled_ts ) {
				// convert scheduled timestamp to site timezone for display
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
		wp_enqueue_style( 'twwt_woo_date', plugins_url('asset/css/jquery-ui.min.css',__FILE__ ), array(), TWWT_VERSION );
		wp_enqueue_style( 'twwt_woo_time', plugins_url('asset/css/jquery-ui-timepicker-addon.min.css',__FILE__ ), array(), TWWT_VERSION );
		wp_enqueue_script( 'twwt_woo_timepicker', plugins_url('asset/js/jquery-ui-timepicker-addon.min.js',__FILE__ ), array('jquery'), TWWT_VERSION, true );
	}
	public function enable_livestrom_callback(){
		if(isset( $this->options['enable_livestrom'] ) && $this->options['enable_livestrom'] == 1){
			$yes = ' selected="selected"';
			$no = '';
		} else {
			$yes = '';
			$no = ' selected="selected"';
		}
		echo '<select id="enable_livestrom" name="twwt_woo_settings[enable_livestrom]">
			<option value="1"'.$yes.'>Yes</option>
			<option value="0"'.$no.'>No</option>
		</select>';
	}
	public function token_callback(){
		echo '<input type="password" id="token" name="twwt_woo_settings[token]" value="'.$this->options['token'].'" style="width:100%;" />';
	}
	public function owner_id_callback(){
		echo '<input type="text" id="owner_id" name="twwt_woo_settings[owner_id]" size="50" value="'.$this->options['owner_id'].'" />';
	}
	public function copy_from_event_id_callback(){
		echo '<input type="text" id="copy_from_event_id" name="twwt_woo_settings[copy_from_event_id]" size="50" value="'.$this->options['copy_from_event_id'].'" />';
	}
	public function twilio_sid_callback(){
		echo '<input type="text" id="twilio_sid" name="twwt_woo_settings[twilio_sid]" size="50" value="'.$this->options['twilio_sid'].'" />';
	}
	public function twilio_token_callback(){
		echo '<input type="text" id="twilio_token" name="twwt_woo_settings[twilio_token]" size="50" value="'.$this->options['twilio_token'].'" />';
	}
	public function twilio_from_callback(){
		echo '<input type="text" id="twilio_from" name="twwt_woo_settings[twilio_from]" size="50" value="'.$this->options['twilio_from'].'" />';
	}
	//email template
	public function new_product_notification_sub_callback(){
		// Description for the field
		echo '<p>Enter the subject for the new product notification. You can use placeholders like <b>%first_name%</b>, <b>%product_name%</b>, and <b>%url%</b>.</p><p></p><p></p>';
		echo '<input type="text" id="new_product_notification_sub" name="twwt_woo_settings[new_product_notification_sub]" size="180" value="'.$this->options['new_product_notification_sub'].'" />';
	}
	public function new_product_notification_callback() {
		$content = $this->options['new_product_notification']; // Retrieve the saved content

		$editor_id = 'new_product_notification'; // Unique editor ID

        // Description for the field
		echo '<p>Enter the email body for the new product notification. You can use placeholders like <b>%first_name%</b>, <b>%product_name%</b>, and <b>%url%</b>.</p><p></p><p></p>';
		// Arguments for wp_editor()
		$settings = array(
			'textarea_name' => 'twwt_woo_settings[new_product_notification]',
			'textarea_rows' => 10, // Adjust the number of rows as needed
		);

		// Output the WYSIWYG editor
		wp_editor($content, $editor_id, $settings);
	}
	public function wwinner_notification_sub_callback(){
		// Description for the field
		echo '<p>Enter the subject for the winner notification to winner. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b> and <b>%phone%</b>.</p><p></p><p></p>';
		echo '<input type="text" id="wwinner_notification_sub" name="twwt_woo_settings[wwinner_notification_sub]" size="180" value="'.$this->options['wwinner_notification_sub'].'" />';
	}
	public function wwinner_notification_callback() {
		$content1 = $this->options['wwinner_notification']; // Retrieve the saved content

		$editor_id1 = 'wwinner_notification'; // Unique editor ID
		
		// Description for the field
		echo '<p>Enter the email body for the winner notification to winner. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b> and <b>%phone%</b>.</p><p></p><p></p>';

		// Arguments for wp_editor()
		$settings1 = array(
			'textarea_name' => 'twwt_woo_settings[wwinner_notification]',
			'textarea_rows' => 10, // Adjust the number of rows as needed
		);

		// Output the WYSIWYG editor
		wp_editor($content1, $editor_id1, $settings1);
	}
	public function winner_noti_others_sub_callback(){
		// Description for the field
		echo '<p>Enter the subject for the winner notification to others. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b> and <b>%phone%</b>.</p><p></p><p></p>';
		echo '<input type="text" id="winner_noti_others_sub" name="twwt_woo_settings[winner_noti_others_sub]" size="180" value="'.$this->options['winner_noti_others_sub'].'" />';
	}
	public function winner_noti_others_callback() {
		$content2 = $this->options['winner_noti_others']; // Retrieve the saved content

		$editor_id2 = 'winner_noti_others'; // Unique editor ID
		
		// Description for the field
		echo '<p>Enter the email body for the winner notification to others. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%screen_name%</b> and <b>%phone%</b>.</p><p></p><p></p>';

		// Arguments for wp_editor()
		$settings2 = array(
			'textarea_name' => 'twwt_woo_settings[winner_noti_others]',
			'textarea_rows' => 10, // Adjust the number of rows as needed
		);

		// Output the WYSIWYG editor
		wp_editor($content2, $editor_id2, $settings2);
	}
	public function ezoom_notification_sub_callback(){
		// Description for the field
		echo '<p>Enter the subject for the winner notification to others. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%event_url%</b> and <b>%event_time%</b>.</p><p></p><p></p>';
		echo '<input type="text" id="ezoom_notification_sub" name="twwt_woo_settings[ezoom_notification_sub]" size="180" value="'.$this->options['ezoom_notification_sub'].'" />';
	}
	public function ezoom_notification_callback() {
		$content3 = $this->options['ezoom_notification']; // Retrieve the saved content

		$editor_id3 = 'ezoom_notification'; // Unique editor ID
        
		// Description for the field
		echo '<p>Enter the email body for the Zoom notification. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%event_url%</b> and <b>%event_time%</b>.</p><p></p><p></p>';
		
		// Arguments for wp_editor()
		$settings3 = array(
			'textarea_name' => 'twwt_woo_settings[ezoom_notification]',
			'textarea_rows' => 10, // Adjust the number of rows as needed
		);

		// Output the WYSIWYG editor
		wp_editor($content3, $editor_id3, $settings3);
	}
	public function elivestrom_notification_sub_callback(){
		// Description for the field
		echo '<p>Enter the subject for the Livestrom notification. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%event_name%</b> and <b>%event_time%</b>.</p><p></p><p></p>';
		echo '<input type="text" id="elivestrom_notification_sub" name="twwt_woo_settings[elivestrom_notification_sub]" size="180" value="'.$this->options['elivestrom_notification_sub'].'" />';
	}
	public function elivestrom_notification_callback() {
		$content4 = $this->options['elivestrom_notification']; // Retrieve the saved content

		$editor_id4 = 'elivestrom_notification'; // Unique editor ID
		
		// Description for the field
		echo '<p>Enter the email body for the Livestrom notification. You can use placeholders like <b>%first_name%</b>, <b>%last_name%</b>, <b>%product_name%</b>, <b>%event_name%</b>, <b>%event_time%</b> and <b>%product_url%</b>.</p><p></p><p></p>';

		// Arguments for wp_editor()
		$settings4 = array(
			'textarea_name' => 'twwt_woo_settings[elivestrom_notification]',
			'textarea_rows' => 10, // Adjust the number of rows as needed
		);

		// Output the WYSIWYG editor
		wp_editor($content4, $editor_id4, $settings4);
	}

	public function batch_new_product_notification_sub_callback() {
		echo '<p>Email subject for daily batch webinar notifications.</p>';
		echo '<input type="text" size="180"
			name="twwt_woo_settings[batch_new_product_notification_sub]"
			value="'. esc_attr($this->options['batch_new_product_notification_sub']) .'" />';
	}

	public function batch_new_product_notification_callback() {
		echo '<p>Available placeholders: <b>%first_name%</b>, <b>%webinar_list%</b></p>';

		wp_editor(
			$this->options['batch_new_product_notification'],
			'batch_new_product_notification',
			array(
				'textarea_name' => 'twwt_woo_settings[batch_new_product_notification]',
				'textarea_rows' => 10,
			)
		);
	}
	
	//SMS 
	
	public function sms_new_product_notification_callback(){
		echo '<p>Enter the SMS Text for the new product notification. You can use placeholders like <b>%first_name%</b>, <b>%product_name%</b>, and <b>%url%</b>.</p><p></p><p></p>';
		echo '<textarea id="sms_new_product_notification" name="twwt_woo_settings[sms_new_product_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_new_product_notification']) . '</textarea>';
	}
	public function sms_wwinner_notification_callback(){
		echo '<p>Enter the SMS Text for the winner notification to winner. You can use placeholders like <b>%first_name%</b>, <b>%product_name%</b>, and <b>%screen_name%</b>.</p><p></p><p></p>';
		echo '<textarea id="sms_wwinner_notification" name="twwt_woo_settings[sms_wwinner_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_wwinner_notification']) . '</textarea>';
	}
	public function sms_winner_noti_others_callback(){
		echo '<p>Enter the SMS Text for the winner notification to others. You can use placeholders like <b>%first_name%</b>, <b>%product_name%</b>, and <b>%screen_name%</b>.</p><p></p><p></p>';
		echo '<textarea id="sms_winner_noti_others" name="twwt_woo_settings[sms_winner_noti_others]" rows="4" cols="180">' . esc_textarea($this->options['sms_winner_noti_others']) . '</textarea>';
	}
	public function sms_zoom_notification_callback(){
		echo '<p>Enter the SMS Text for the Zoom notification. You can use placeholders like <b>%first_name%</b>, <b>%product_name%</b>, <b>%event_url%</b> and <b>%event_time%</b>.</p><p></p><p></p>';
		echo '<textarea id="sms_zoom_notification" name="twwt_woo_settings[sms_zoom_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_zoom_notification']) . '</textarea>';
	}
	public function sms_livestrom_notification_callback(){
		echo '<p>Enter the SMS Text for the Livestrom event notification. You can use placeholders like <b>%first_name%</b>, <b>%event_name%</b>, and <b>%product_url%</b>.</p><p></p><p></p>';
		echo '<textarea id="sms_livestrom_notification" name="twwt_woo_settings[sms_livestrom_notification]" rows="4" cols="180">' . esc_textarea($this->options['sms_livestrom_notification']) . '</textarea>';
	}
	public function sms_batch_new_product_notification_callback() {
		echo '<p>Available placeholders:</p>
			<p><b>%webinar_titles%</b>, <b>%url%</b></p>';

		echo '<textarea rows="4" cols="180"
			name="twwt_woo_settings[sms_batch_new_product_notification]">' .
			esc_textarea($this->options['sms_batch_new_product_notification']) .
			'</textarea>';
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

    //  submenu setting heading  
    public function licence_section_info() {
        echo '<p>Enter your license key below:</p>';
    }

   //  submenu setting licene field  
    public function license_key_callback() {
    	$this->options = get_option('twwt_plugin_license_options'); 
        $license_status = '';
        $license_key = isset($this->options['twwt_plugin_license_key']) ? esc_attr($this->options['twwt_plugin_license_key']) : '';
        if (!empty($license_key)) {
            $license_status = $this->twwt_license_key_valid() ? 'Valid' : 'Invalid';
        }
        echo '<input type="password" name="twwt_plugin_license_options[twwt_plugin_license_key]" value="' . $license_key . '" />';
        echo '<span style="font-weight: bold; color: ' . ($license_status === 'Valid' ? 'green' : 'red') . ';"> ' . $license_status . '</span>';
    }

	// submenu setting licene validation  
	public function twwt_license_key_valid() {
		$this->options = get_option('twwt_plugin_license_options'); 
        $localKey = get_option('twwt_plugin_local_key', '');
        $license_key = isset($this->options['twwt_plugin_license_key']) ? esc_attr($this->options['twwt_plugin_license_key']) : '';
        $results = $this->firearm_check_license($license_key, $localKey);
        if ($results['status'] == 'Active') {
            update_option('twwt_plugin_local_key', $results['localkey']);
            return true;
        } else {
            return false;
        }
    }

	// submenu check status of licence key and local key 
	public function firearm_check_license($licensekey, $localkey='') {
	    $whmcsurl = 'https://hosting.gigapress.net/';
	    //$whmcsurl = 'https://test.jytfvty.biz/';
	    $licensing_secret_key = 'smcreative';
	    $localkeydays = 15;
	    $allowcheckfaildays = 5;
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
	        if ($md5hash == md5($localdata . $licensing_secret_key)) {
	            $localdata = strrev($localdata); 
	            $md5hash = substr($localdata, 0, 32); 
	            $localdata = substr($localdata, 32); 
	            $localdata = base64_decode($localdata);
	            $localkeyresults = unserialize($localdata);
	            $originalcheckdate = $localkeyresults['checkdate'];
	            if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
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
	            die("Invalid License Server Response");
	        }
	        if ($results['md5hash']) {
	            if ($results['md5hash'] != md5($licensing_secret_key . $check_token)) {
	                $results['status'] = "Invalid";
	                $results['description'] = "MD5 Checksum Verification Failed";
	                return $results;
	            }
	        }
	        if ($results['status'] == "Active") {
	            $results['checkdate'] = $checkdate;
	            $data_encoded = serialize($results);
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

// When the options array is updated, reschedule the daily batch
add_action('update_option_twwt_woo_settings', 'twwt_reschedule_on_option_change', 10, 3);
function twwt_reschedule_on_option_change($old_value, $value, $option_name) {
    // $value is the new settings array. We only care if notification settings changed.
    if (function_exists('twwt_schedule_daily_batch')) {
        twwt_schedule_daily_batch();
    }
}


if( is_admin() ){
		$twwt_woo_settings_page = new twwt_woo_settings_page();
}