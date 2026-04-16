<?php
/**
 * Plugin Name:       CM Instagram Feed
 * Description:       Display Instagram posts in a beautiful grid with optional mobile carousel. Connect via Access Token.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Ian Harris
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cm-instagram-feed
 *
 * @package CMInstagramFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'CM_INSTAGRAM_FEED_VERSION', '1.0.0' );
define( 'CM_INSTAGRAM_FEED_PATH', plugin_dir_path( __FILE__ ) );
define( 'CM_INSTAGRAM_FEED_URL', plugin_dir_url( __FILE__ ) );
define( 'CM_INSTAGRAM_FEED_PLUGIN_SLUG', 'cm-instagram-feed' );
define( 'CM_INSTAGRAM_FEED_GITHUB_URL', 'https://github.com/carronmedia/cm-instagram-feed/' );

require_once CM_INSTAGRAM_FEED_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Setup GitHub updater
if (!defined('LHB_GITHUB_TOKEN') || !LHB_GITHUB_TOKEN) {
	error_log('[CM Instagram Feed] Warning: LHB_GITHUB_TOKEN is not defined. Automatic updates from GitHub will not work.');
} else {
	$cmif_update_checker = PucFactory::buildUpdateChecker(
			CM_INSTAGRAM_FEED_GITHUB_URL,
			__FILE__,
			CM_INSTAGRAM_FEED_PLUGIN_SLUG
	);

	//Set the branch that contains the stable release.
	$cmif_update_checker->setBranch('main');

	$cmif_update_checker->setAuthentication(LHB_GITHUB_TOKEN);
}

/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function cm_instagram_feed_block_init() {
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
		return;
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		register_block_type( __DIR__ . "/build/{$block_type}" );
	}
}
add_action( 'init', 'cm_instagram_feed_block_init' );

/**
 * Add settings page to WordPress admin menu
 */
function cm_instagram_feed_add_admin_menu() {
	add_options_page(
		__( 'Instagram Feed Settings', 'cm-instagram-feed' ),
		__( 'Instagram Feed', 'cm-instagram-feed' ),
		'manage_options',
		'cm-instagram-feed-settings',
		'cm_instagram_feed_settings_page'
	);
}
add_action( 'admin_menu', 'cm_instagram_feed_add_admin_menu' );

/**
 * Register plugin settings
 */
function cm_instagram_feed_settings_init() {
	register_setting( 'cm_instagram_feed_settings', 'cm_instagram_access_token' );
	register_setting( 'cm_instagram_feed_settings', 'cm_instagram_user_id' );
	register_setting( 'cm_instagram_feed_settings', 'cm_instagram_username' );
}
add_action( 'admin_init', 'cm_instagram_feed_settings_init' );

/**
 * Handle form submissions
 */
function cm_instagram_feed_handle_form_submission() {
	// Handle disconnect
	if ( isset( $_POST['cm_instagram_disconnect'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'cm-instagram-feed' ) );
		}
		
		check_admin_referer( 'cm_instagram_disconnect' );
		
		$access_token = get_option( 'cm_instagram_access_token' );
		
		delete_option( 'cm_instagram_access_token' );
		delete_option( 'cm_instagram_user_id' );
		delete_option( 'cm_instagram_username' );
		
		// Clear cache
		if ( $access_token ) {
			delete_transient( 'cm_instagram_posts_' . md5( $access_token ) );
		}
		
		set_transient( 'cm_instagram_message', array(
			'type'    => 'success',
			'message' => __( 'Instagram account disconnected successfully.', 'cm-instagram-feed' ),
		), 60 );
		
		wp_safe_redirect( admin_url( 'options-general.php?page=cm-instagram-feed-settings' ) );
		exit;
	}
	
	// Handle token connection
	if ( isset( $_POST['cm_instagram_connect'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'cm-instagram-feed' ) );
		}
		
		check_admin_referer( 'cm_instagram_connect' );
		
		$access_token = isset( $_POST['cm_instagram_token'] ) ? sanitize_text_field( $_POST['cm_instagram_token'] ) : '';
		
		if ( empty( $access_token ) ) {
			set_transient( 'cm_instagram_message', array(
				'type'    => 'error',
				'message' => __( 'Please enter an access token.', 'cm-instagram-feed' ),
			), 60 );
			wp_safe_redirect( admin_url( 'options-general.php?page=cm-instagram-feed-settings' ) );
			exit;
		}
		
		// Test the token by making an API request
		$test_url = add_query_arg(
			array(
				'fields'       => 'id,username',
				'access_token' => $access_token,
			),
			'https://graph.instagram.com/me'
		);
		
		$response = wp_remote_get( $test_url );
		
		if ( is_wp_error( $response ) ) {
			set_transient( 'cm_instagram_message', array(
				'type'    => 'error',
				'message' => __( 'Failed to verify access token. Please check your internet connection.', 'cm-instagram-feed' ),
			), 60 );
			wp_safe_redirect( admin_url( 'options-general.php?page=cm-instagram-feed-settings' ) );
			exit;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( isset( $data['error'] ) ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Invalid access token.', 'cm-instagram-feed' );
			set_transient( 'cm_instagram_message', array(
				'type'    => 'error',
				'message' => $error_msg,
			), 60 );
			wp_safe_redirect( admin_url( 'options-general.php?page=cm-instagram-feed-settings' ) );
			exit;
		}
		
		if ( isset( $data['username'] ) && isset( $data['id'] ) ) {
			update_option( 'cm_instagram_access_token', $access_token );
			update_option( 'cm_instagram_username', $data['username'] );
			update_option( 'cm_instagram_user_id', $data['id'] );
			
			// Clear any old cache
			delete_transient( 'cm_instagram_posts_' . md5( $access_token ) );
			
			set_transient( 'cm_instagram_message', array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %s: Instagram username */
					__( 'Successfully connected to @%s!', 'cm-instagram-feed' ),
					$data['username']
				),
			), 60 );
		} else {
			set_transient( 'cm_instagram_message', array(
				'type'    => 'error',
				'message' => __( 'Invalid response from Instagram API.', 'cm-instagram-feed' ),
			), 60 );
		}
		
		wp_safe_redirect( admin_url( 'options-general.php?page=cm-instagram-feed-settings' ) );
		exit;
	}
}
add_action( 'admin_init', 'cm_instagram_feed_handle_form_submission' );

/**
 * Render the settings page
 */
function cm_instagram_feed_settings_page() {
	$access_token = get_option( 'cm_instagram_access_token' );
	$username     = get_option( 'cm_instagram_username' );
	$is_connected = ! empty( $access_token ) && ! empty( $username );
	
	// Get any messages
	$message = get_transient( 'cm_instagram_message' );
	if ( $message ) {
		delete_transient( 'cm_instagram_message' );
	}
	?>
	<style>
		.cm-instagram-wrap {
			max-width: 800px;
			margin-top: 20px;
		}
		.cm-instagram-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 24px;
			margin-bottom: 20px;
		}
		.cm-instagram-card h2 {
			margin-top: 0;
			padding-top: 0;
			border-bottom: 1px solid #eee;
			padding-bottom: 12px;
		}
		.cm-instagram-connected {
			display: flex;
			align-items: center;
			gap: 16px;
			padding: 16px;
			background: #f0f6fc;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			margin-bottom: 16px;
		}
		.cm-instagram-connected-icon {
			font-size: 40px;
		}
		.cm-instagram-connected-info h3 {
			margin: 0 0 4px 0;
		}
		.cm-instagram-connected-info p {
			margin: 0;
			color: #50575e;
		}
		.cm-instagram-steps {
			background: #f6f7f7;
			padding: 20px;
			border-radius: 4px;
			margin: 16px 0;
		}
		.cm-instagram-steps ol {
			margin: 0;
			padding-left: 20px;
		}
		.cm-instagram-steps li {
			margin-bottom: 12px;
		}
		.cm-instagram-steps li:last-child {
			margin-bottom: 0;
		}
		.cm-instagram-steps a {
			font-weight: 600;
		}
		.cm-instagram-token-field {
			width: 100%;
			margin-bottom: 12px;
		}
		.cm-instagram-note {
			background: #fff8e5;
			border-left: 4px solid #dba617;
			padding: 12px;
			margin-top: 16px;
		}
	</style>
	
	<div class="wrap cm-instagram-wrap">
		<h1><?php esc_html_e( 'Instagram Feed Settings', 'cm-instagram-feed' ); ?></h1>
		
		<?php if ( $message ) : ?>
			<div class="notice notice-<?php echo esc_attr( $message['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $message['message'] ); ?></p>
			</div>
		<?php endif; ?>
		
		<?php if ( $is_connected ) : ?>
			<!-- Connected State -->
			<div class="cm-instagram-card">
				<h2><?php esc_html_e( 'Connection Status', 'cm-instagram-feed' ); ?></h2>
				
				<div class="cm-instagram-connected">
					<span class="cm-instagram-connected-icon">📸</span>
					<div class="cm-instagram-connected-info">
						<h3><?php esc_html_e( 'Connected', 'cm-instagram-feed' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: %s: Instagram username */
								esc_html__( 'Instagram account: @%s', 'cm-instagram-feed' ),
								esc_html( $username )
							);
							?>
						</p>
					</div>
				</div>
				
				<p><?php esc_html_e( 'Your Instagram account is connected. You can now use the Instagram Feed block in your pages and posts.', 'cm-instagram-feed' ); ?></p>
				
				<form method="post">
					<?php wp_nonce_field( 'cm_instagram_disconnect' ); ?>
					<button type="submit" name="cm_instagram_disconnect" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect your Instagram account?', 'cm-instagram-feed' ) ); ?>')">
						<?php esc_html_e( 'Disconnect Account', 'cm-instagram-feed' ); ?>
					</button>
				</form>
			</div>
			
		<?php else : ?>
			<!-- Connection Form -->
			<div class="cm-instagram-card">
				<h2><?php esc_html_e( 'Connect Your Instagram Account', 'cm-instagram-feed' ); ?></h2>
				
				<p><?php esc_html_e( 'To display your Instagram feed, you need to generate an Access Token and enter it below.', 'cm-instagram-feed' ); ?></p>
				
				<div class="cm-instagram-steps">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'How to Get Your Access Token:', 'cm-instagram-feed' ); ?></h3>
					<ol>
						<li>
							<?php
							echo wp_kses(
								__( 'Go to <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a> and log in with your Facebook account.', 'cm-instagram-feed' ),
								array( 'a' => array( 'href' => array(), 'target' => array() ) )
							);
							?>
						</li>
						<li><?php esc_html_e( 'Create a new app (select "Business" type, then "Other" for use case).', 'cm-instagram-feed' ); ?></li>
						<li><?php esc_html_e( 'In your app dashboard, add the "Instagram Basic Display" product.', 'cm-instagram-feed' ); ?></li>
						<li><?php esc_html_e( 'In "Instagram Basic Display" settings, add your Instagram account as a test user.', 'cm-instagram-feed' ); ?></li>
						<li><?php esc_html_e( 'Go to Instagram Settings > Apps and Websites and accept the authorization.', 'cm-instagram-feed' ); ?></li>
						<li><?php esc_html_e( 'Back in Facebook Developers, go to "Basic Display" > "User Token Generator" and click "Generate Token" for your account.', 'cm-instagram-feed' ); ?></li>
						<li><?php esc_html_e( 'Copy the generated token and paste it below.', 'cm-instagram-feed' ); ?></li>
					</ol>
				</div>
				
				<form method="post">
					<?php wp_nonce_field( 'cm_instagram_connect' ); ?>
					<input 
						type="text" 
						name="cm_instagram_token" 
						class="regular-text cm-instagram-token-field" 
						placeholder="<?php esc_attr_e( 'Paste your Instagram Access Token here', 'cm-instagram-feed' ); ?>"
					/>
					<button type="submit" name="cm_instagram_connect" class="button button-primary">
						<?php esc_html_e( 'Connect Account', 'cm-instagram-feed' ); ?>
					</button>
				</form>
				
				<div class="cm-instagram-note">
					<strong><?php esc_html_e( 'Note:', 'cm-instagram-feed' ); ?></strong>
					<?php esc_html_e( 'Your Instagram account must be a Business or Creator account to use this feature. Personal accounts are no longer supported by Instagram\'s API.', 'cm-instagram-feed' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * REST API endpoint to fetch Instagram posts
 */
function cm_instagram_feed_register_rest_routes() {
	register_rest_route(
		'cm-instagram-feed/v1',
		'/posts',
		array(
			'methods'             => 'GET',
			'callback'            => 'cm_instagram_feed_get_posts',
			'permission_callback' => '__return_true',
		)
	);
	
	register_rest_route(
		'cm-instagram-feed/v1',
		'/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'cm_instagram_feed_get_status',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'cm_instagram_feed_register_rest_routes' );

/**
 * Get connection status
 */
function cm_instagram_feed_get_status() {
	$access_token = get_option( 'cm_instagram_access_token' );
	$username     = get_option( 'cm_instagram_username' );
	
	return rest_ensure_response( array(
		'connected' => ! empty( $access_token ) && ! empty( $username ),
		'username'  => $username ?: '',
	) );
}

/**
 * Fetch Instagram posts from API
 */
function cm_instagram_feed_get_posts( $request ) {
	$access_token = get_option( 'cm_instagram_access_token' );
	
	if ( empty( $access_token ) ) {
		return new WP_Error(
			'not_connected',
			__( 'Instagram account not connected. Please configure your account in Settings → Instagram Feed.', 'cm-instagram-feed' ),
			array( 'status' => 400 )
		);
	}
	
	// Get cached posts
	$cache_key    = 'cm_instagram_posts_' . md5( $access_token );
	$cached_posts = get_transient( $cache_key );
	
	if ( false !== $cached_posts ) {
		return rest_ensure_response( $cached_posts );
	}
	
	// Fetch from API
	$api_url = add_query_arg(
		array(
			'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
			'access_token' => $access_token,
			'limit'        => 25, // Fetch more than needed for flexibility
		),
		'https://graph.instagram.com/me/media'
	);
	
	$response = wp_remote_get( $api_url );
	
	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'api_error',
			__( 'Failed to connect to Instagram API. Please check your internet connection.', 'cm-instagram-feed' ),
			array( 'status' => 500 )
		);
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( isset( $data['error'] ) ) {
		// Token might be expired - clear it
		if ( isset( $data['error']['code'] ) && in_array( $data['error']['code'], array( 190, 463 ), true ) ) {
			delete_option( 'cm_instagram_access_token' );
			delete_option( 'cm_instagram_username' );
			delete_option( 'cm_instagram_user_id' );
		}
		
		return new WP_Error(
			'instagram_api_error',
			$data['error']['message'] ?? __( 'Instagram API returned an error. Please check your access token.', 'cm-instagram-feed' ),
			array( 'status' => 400 )
		);
	}
	
	if ( ! isset( $data['data'] ) ) {
		return new WP_Error(
			'invalid_response',
			__( 'Invalid response from Instagram API.', 'cm-instagram-feed' ),
			array( 'status' => 500 )
		);
	}
	
	$posts = array_reverse( $data['data'] );
	
	// Cache for 1 hour
	set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
	
	return rest_ensure_response( $posts );
}

/**
 * Refresh token automatically (Instagram tokens expire in 60 days)
 */
function cm_instagram_feed_refresh_token() {
	$access_token = get_option( 'cm_instagram_access_token' );
	
	if ( empty( $access_token ) ) {
		return;
	}
	
	$refresh_url = add_query_arg(
		array(
			'grant_type'   => 'ig_refresh_token',
			'access_token' => $access_token,
		),
		'https://graph.instagram.com/refresh_access_token'
	);
	
	$response = wp_remote_get( $refresh_url );
	
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( isset( $data['access_token'] ) ) {
			update_option( 'cm_instagram_access_token', $data['access_token'] );
		}
	}
}

// Schedule token refresh
function cm_instagram_feed_schedule_refresh() {
	if ( ! wp_next_scheduled( 'cm_instagram_refresh_token' ) ) {
		// Refresh every 50 days (tokens expire in 60 days)
		wp_schedule_event( time(), 'cm_instagram_50_days', 'cm_instagram_refresh_token' );
	}
}
add_action( 'wp', 'cm_instagram_feed_schedule_refresh' );
add_action( 'cm_instagram_refresh_token', 'cm_instagram_feed_refresh_token' );

// Add custom cron interval
function cm_instagram_feed_cron_schedules( $schedules ) {
	$schedules['cm_instagram_50_days'] = array(
		'interval' => 50 * DAY_IN_SECONDS,
		'display'  => __( 'Every 50 days', 'cm-instagram-feed' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'cm_instagram_feed_cron_schedules' );

// Clean up on deactivation
function cm_instagram_feed_deactivate() {
	wp_clear_scheduled_hook( 'cm_instagram_refresh_token' );
}
register_deactivation_hook( __FILE__, 'cm_instagram_feed_deactivate' );
