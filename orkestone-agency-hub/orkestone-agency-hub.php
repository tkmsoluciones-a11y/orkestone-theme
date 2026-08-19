<?php
/**
 * Orkestone Agency Hub
 *
 * @package           OrkestoneAgencyHub
 * @author            Orkestone
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Orkestone Agency Hub
 * Plugin URI:        https://orkestone.io/agency-hub
 * Description:       Professional agency distribution platform — manage client briefings, calculate budgets, process payments, and deliver site configurations via activation tokens.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Orkestone
 * Author URI:        https://orkestone.io
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       orkestone-agency-hub
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORKE_AGENCY_HUB_VERSION', '1.0.0' );
define( 'ORKE_AGENCY_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORKE_AGENCY_HUB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 *
 * Bootstraps all Agency Hub functionality:
 * - Loads PHP class files from the includes/ directory
 * - Registers CPTs and post meta on init
 * - Enqueues admin assets on Hub admin pages
 * - Handles plugin activation/deactivation
 */
class Orkestone_Agency_Hub {

	/**
	 * Singleton instance.
	 *
	 * @var Orkestone_Agency_Hub
	 */
	private static $instance = null;

	/**
	 * Files to load from the includes/ directory.
	 *
	 * @var array
	 */
	private $dependencies = array(
		'includes/class-configuration-cpt.php',
		'includes/class-asset-library.php',
		'includes/class-json-builder.php',
		'includes/class-pricing.php',
		'includes/class-briefing-form.php',
		'includes/class-payment-gateway.php',
		'includes/class-delivery.php',
		'includes/class-hub-rest-api.php',
	);

	/**
	 * Get the singleton instance.
	 *
	 * @return Orkestone_Agency_Hub
	 */
	public static function get_instance(): Orkestone_Agency_Hub {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Load all required PHP files.
	 */
	private function load_dependencies(): void {
		foreach ( $this->dependencies as $file ) {
			$path = ORKE_AGENCY_HUB_DIR . $file;

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'init_plugin' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_menu', 'orke_agency_hub_register_menu', 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		if ( class_exists( 'Orkestone_Briefing_Form' ) ) {
			Orkestone_Briefing_Form::register_hooks();
		}

		if ( class_exists( 'Orkestone_Payment_Gateway' ) ) {
			Orkestone_Payment_Gateway::register_hooks();
		}

		if ( class_exists( 'Orkestone_Delivery' ) ) {
			Orkestone_Delivery::register_hooks();
		}

		if ( class_exists( 'Orkestone_Hub_REST_API' ) ) {
			Orkestone_Hub_REST_API::register_hooks();
		}
	}

	/**
	 * Add the Agency Hub settings page.
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=orke_configuration',
			__( 'Agency Hub Settings', 'orkestone-agency-hub' ),
			__( 'Settings', 'orkestone-agency-hub' ),
			'manage_options',
			'orke-hub-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the Agency Hub settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'orkestone-agency-hub' ) );
		}

		// Save settings if submitted.
		if ( isset( $_POST['orke_save_settings'] ) && check_admin_referer( 'orke_save_settings', '_orke_settings_nonce' ) ) {
			if ( isset( $_POST['orke_stripe_secret_key'] ) ) {
				update_option( 'orke_stripe_secret_key', sanitize_text_field( wp_unslash( $_POST['orke_stripe_secret_key'] ) ) );
			}
			if ( isset( $_POST['orke_stripe_publishable_key'] ) ) {
				update_option( 'orke_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['orke_stripe_publishable_key'] ) ) );
			}
			if ( isset( $_POST['orke_stripe_webhook_secret'] ) ) {
				update_option( 'orke_stripe_webhook_secret', sanitize_text_field( wp_unslash( $_POST['orke_stripe_webhook_secret'] ) ) );
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'orkestone-agency-hub' ) . '</p></div>';
		}

		$stripe_secret      = get_option( 'orke_stripe_secret_key', '' );
		$stripe_publishable = get_option( 'orke_stripe_publishable_key', '' );
		$webhook_secret     = get_option( 'orke_stripe_webhook_secret', '' );
		$webhook_url        = rest_url( 'orke-hub/v1/webhook/stripe' );
		?>
		<div class="wrap orke-hub-wrap">
			<div class="orke-hub-header">
				<h1><?php esc_html_e( 'Agency Hub Settings', 'orkestone-agency-hub' ); ?></h1>
			</div>

			<div class="orke-hub-card">
				<h2><?php esc_html_e( 'Stripe Integration', 'orkestone-agency-hub' ); ?></h2>
				<p><?php esc_html_e( 'Configure your Stripe API keys to accept payments via credit card. All keys can be found in your Stripe Dashboard under Developers → API keys.', 'orkestone-agency-hub' ); ?></p>

				<form method="post" action="">
					<?php wp_nonce_field( 'orke_save_settings', '_orke_settings_nonce' ); ?>

					<div class="orke-hub-field">
						<label for="orke_stripe_secret_key"><?php esc_html_e( 'Stripe Secret Key', 'orkestone-agency-hub' ); ?></label>
						<input type="password" id="orke_stripe_secret_key" name="orke_stripe_secret_key"
							value="<?php echo esc_attr( $stripe_secret ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Start with sk_live_ or sk_test_', 'orkestone-agency-hub' ); ?></p>
					</div>

					<div class="orke-hub-field">
						<label for="orke_stripe_publishable_key"><?php esc_html_e( 'Stripe Publishable Key', 'orkestone-agency-hub' ); ?></label>
						<input type="text" id="orke_stripe_publishable_key" name="orke_stripe_publishable_key"
							value="<?php echo esc_attr( $stripe_publishable ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Start with pk_live_ or pk_test_', 'orkestone-agency-hub' ); ?></p>
					</div>

					<div class="orke-hub-field">
						<label for="orke_stripe_webhook_secret"><?php esc_html_e( 'Stripe Webhook Secret', 'orkestone-agency-hub' ); ?></label>
						<input type="password" id="orke_stripe_webhook_secret" name="orke_stripe_webhook_secret"
							value="<?php echo esc_attr( $webhook_secret ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Signing secret (whsec_...) from Stripe Dashboard → Webhooks.', 'orkestone-agency-hub' ); ?>
							<br />
							<strong><?php esc_html_e( 'Webhook URL:', 'orkestone-agency-hub' ); ?></strong>
							<code><?php echo esc_url( $webhook_url ); ?></code>
						</p>
					</div>

					<p class="submit">
						<button type="submit" name="orke_save_settings" class="orke-hub-button orke-hub-button--primary">
							<?php esc_html_e( 'Save Settings', 'orkestone-agency-hub' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Initialize plugin — registers CPTs and meta.
	 */
	public function init_plugin(): void {
		if ( class_exists( 'Orkestone_Configuration_CPT' ) ) {
			Orkestone_Configuration_CPT::register();
		}

		if ( class_exists( 'Orkestone_Asset_Library' ) ) {
			Orkestone_Asset_Library::register();
		}
	}

	/**
	 * Enqueue admin CSS and JS on Hub admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'orke' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'orke-agency-hub-admin',
			ORKE_AGENCY_HUB_URL . 'assets/css/admin.css',
			array(),
			ORKE_AGENCY_HUB_VERSION
		);

		// Enqueue briefing form JS on the new briefing page (legacy and top-level menu).
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'orke-new-configuration' === $page || 'orkestone-agency-hub' === $page ) {
			wp_enqueue_script(
				'orke-briefing-form',
				ORKE_AGENCY_HUB_URL . 'assets/js/briefing-form.js',
				array(),
				ORKE_AGENCY_HUB_VERSION,
				true
			);
		}
	}
}

/**
 * Register the Agency Hub top-level menu and submenus.
 *
 * Creates a visible top-level "Agency Hub" entry in the WordPress admin menu
 * so users can access the briefing form, configurations, and settings
 * without needing to navigate through the Configuration CPT.
 *
 * @since 1.0.0
 */
function orke_agency_hub_register_menu(): void {
	if ( ! class_exists( 'Orkestone_Briefing_Form' ) ) {
		return;
	}

	$menu_slug = 'orkestone-agency-hub';

	add_menu_page(
		__( 'Agency Hub', 'orkestone-agency-hub' ),
		__( 'Agency Hub', 'orkestone-agency-hub' ),
		'edit_posts',
		$menu_slug,
		'orke_agency_hub_render_main_page',
		'dashicons-businessman',
		30
	);

	// Submenu: New Briefing (same slug as parent = default/active view).
	add_submenu_page(
		$menu_slug,
		__( 'New Briefing', 'orkestone-agency-hub' ),
		__( 'New Briefing', 'orkestone-agency-hub' ),
		'edit_posts',
		$menu_slug,
		'orke_agency_hub_render_main_page'
	);

	// Submenu: All Configurations (links to the CPT list table).
	add_submenu_page(
		$menu_slug,
		__( 'All Configurations', 'orkestone-agency-hub' ),
		__( 'All Configurations', 'orkestone-agency-hub' ),
		'edit_posts',
		'edit.php?post_type=orke_configuration'
	);

	// Submenu: Settings.
	add_submenu_page(
		$menu_slug,
		__( 'Agency Hub Settings', 'orkestone-agency-hub' ),
		__( 'Settings', 'orkestone-agency-hub' ),
		'manage_options',
		'orke-hub-settings',
		'orke_agency_hub_render_settings_page'
	);
}

/**
 * Render the main Agency Hub page.
 *
 * Shows the Briefing Form (4-tab form: Branding, Pages, Content, Navigation/SEO)
 * as the default view. This is what users see when they click "Agency Hub" in
 * the admin menu.
 *
 * @since 1.0.0
 */
function orke_agency_hub_render_main_page(): void {
	if ( class_exists( 'Orkestone_Briefing_Form' ) ) {
		Orkestone_Briefing_Form::render_page();
	} else {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Agency Hub', 'orkestone-agency-hub' ); ?></h1>
			<div class="notice notice-warning">
				<p><?php echo esc_html__( 'Briefing Form is not available. Please ensure the plugin files are fully installed.', 'orkestone-agency-hub' ); ?></p>
			</div>
		</div>
		<?php
	}
}

/**
 * Render the Agency Hub Settings page.
 *
 * Delegates to the existing Orkestone_Agency_Hub::render_settings_page()
 * via the plugin singleton so that no code duplication is needed.
 *
 * @since 1.0.0
 */
function orke_agency_hub_render_settings_page(): void {
	orke_agency_hub()->render_settings_page();
}

/**
 * Plugin activation handler.
 *
 * Registers CPTs so they exist immediately after activation,
 * then flushes rewrite rules so the REST API routes work.
 */
function orke_agency_hub_activate(): void {
	if ( class_exists( 'Orkestone_Configuration_CPT' ) ) {
		Orkestone_Configuration_CPT::register();
	}

	if ( class_exists( 'Orkestone_Asset_Library' ) ) {
		Orkestone_Asset_Library::register();
	}

	flush_rewrite_rules();
}

/**
 * Plugin deactivation handler.
 *
 * Flushes rewrite rules to clean up CPT routes.
 */
function orke_agency_hub_deactivate(): void {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'orke_agency_hub_activate' );
register_deactivation_hook( __FILE__, 'orke_agency_hub_deactivate' );

/**
 * Bootstrap the plugin.
 *
 * @return Orkestone_Agency_Hub
 */
function orke_agency_hub(): Orkestone_Agency_Hub {
	return Orkestone_Agency_Hub::get_instance();
}

orke_agency_hub();
