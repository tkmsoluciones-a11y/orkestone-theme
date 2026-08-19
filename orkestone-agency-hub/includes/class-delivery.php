<?php
/**
 * Token Delivery System — activation token generation and lifecycle.
 *
 * Generates UUID v4 activation tokens when an orke_configuration transitions
 * to published status. Manages token expiry, revocation, and regeneration.
 *
 * Tokens are multi-use by design (AD7) — they remain valid until explicitly
 * revoked by the agency admin. Expiry is stored as a Unix timestamp in
 * post meta (24 hours from generation by default, AD4).
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles token generation, revocation, and regeneration.
 *
 * Token lifecycle:
 * 1. Generated on `orke_configuration_paid` action (via publish transition)
 * 2. Stored as `_orke_delivery_token` (UUID v4) + `_orke_token_expires_at` (timestamp)
 * 3. Revocable via admin action → meta set to `revoked-{timestamp}`
 * 4. Regenerable → old token revoked, new one generated
 */
class Orkestone_Delivery {

	/**
	 * Default token TTL in seconds (24 hours).
	 */
	const DEFAULT_TOKEN_TTL = DAY_IN_SECONDS;

	/**
	 * Hook into WordPress for token lifecycle management.
	 */
	public static function register_hooks(): void {
		// Token generation on payment completion.
		add_action( 'orke_configuration_paid', array( __CLASS__, 'generate_token' ), 10, 1 );

		// Admin actions for revocation and regeneration.
		add_action( 'admin_post_orke_revoke_token', array( __CLASS__, 'handle_revoke_token' ) );
		add_action( 'admin_post_orke_regenerate_token', array( __CLASS__, 'handle_regenerate_token' ) );

		// Show token meta box on configuration edit screen.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_token_meta_box' ) );

		// Cleanup expired tokens (daily via WP Cron).
		add_action( 'orke_daily_token_cleanup', array( __CLASS__, 'cleanup_expired_tokens' ) );
		if ( ! wp_next_scheduled( 'orke_daily_token_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'orke_daily_token_cleanup' );
		}
	}

	/**
	 * Generate an activation token for a configuration post.
	 *
	 * Called when the `orke_configuration_paid` action fires.
	 * Generates a UUID v4 token, sets the expiry timestamp (24h),
	 * and stores both as post meta (REQ-AH15).
	 *
	 * If a token already exists and is not revoked, it is NOT
	 * regenerated — preserves the existing token (multi-use, AD7).
	 *
	 * @param int $post_id The configuration post ID.
	 * @return string|false The generated token, or false on failure.
	 */
	public static function generate_token( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || Orkestone_Configuration_CPT::POST_TYPE !== $post->post_type ) {
			return false;
		}

		// If the post is not published, don't generate a token yet.
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		// Check if a valid token already exists (multi-use, AD7).
		$existing_token = get_post_meta( $post_id, '_orke_delivery_token', true );
		if ( ! empty( $existing_token ) && strpos( $existing_token, 'revoked-' ) !== 0 ) {
			// Token already exists and is not revoked — return it.
			return $existing_token;
		}

		// Generate UUID v4 token.
		$token = wp_generate_uuid4();

		// Calculate expiry (24 hours from now by default).
		$ttl = self::DEFAULT_TOKEN_TTL;
		/**
		 * Filter the token TTL.
		 *
		 * Allows overriding the default 24-hour token lifetime.
		 *
		 * @since 1.0.0
		 *
		 * @param int $ttl Time-to-live in seconds.
		 */
		$ttl = apply_filters( 'orke_token_ttl', $ttl );

		$expires_at = time() + $ttl;

		// Store the client site origin if available.
		$allowed_origin = get_post_meta( $post_id, '_orke_token_allowed_origin', true );
		if ( empty( $allowed_origin ) ) {
			// Default to empty — origin validation is optional.
			update_post_meta( $post_id, '_orke_token_allowed_origin', '' );
		}

		update_post_meta( $post_id, '_orke_delivery_token', $token );
		update_post_meta( $post_id, '_orke_token_expires_at', $expires_at );

		error_log(
			sprintf(
				'Orkestone Agency Hub — Token generated for config %d: %s (expires: %s)',
				$post_id,
				$token,
				gmdate( 'Y-m-d H:i:s', $expires_at )
			)
		);

		return $token;
	}

	/**
	 * Validate a token and return the associated configuration post ID.
	 *
	 * Checks:
	 * - Token exists in post meta
	 * - Token is not revoked (doesn't start with 'revoked-')
	 * - Token is not expired (current time <= expires_at)
	 *
	 * @param string $token The activation token to validate.
	 * @return int|false Post ID if valid, false otherwise.
	 */
	public static function validate_token( string $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		// Find the configuration post by token meta.
		$posts = get_posts(
			array(
				'post_type'      => Orkestone_Configuration_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_orke_delivery_token',
						'value' => $token,
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		$post_id = (int) $posts[0];

		// Check: is the token revoked?
		$stored_token = get_post_meta( $post_id, '_orke_delivery_token', true );
		if ( strpos( $stored_token, 'revoked-' ) === 0 ) {
			return false;
		}

		// Check: is the token expired? (REQ-AH16)
		$expires_at = intval( get_post_meta( $post_id, '_orke_token_expires_at', true ) );
		if ( $expires_at > 0 && time() > $expires_at ) {
			// Auto-revoke expired tokens.
			update_post_meta( $post_id, '_orke_delivery_token', 'revoked-' . time() . '-expired' );
			return false;
		}

		return $post_id;
	}

	/**
	 * Revoke a token by marking it as revoked.
	 *
	 * Sets the token meta to `revoked-{timestamp}` (REQ-AH19).
	 * The token endpoint will return 404 for revoked tokens.
	 *
	 * @param int $post_id The configuration post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function revoke_token( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || Orkestone_Configuration_CPT::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$current_token = get_post_meta( $post_id, '_orke_delivery_token', true );
		if ( empty( $current_token ) ) {
			return false;
		}

		// Don't re-revoke already revoked tokens.
		if ( strpos( $current_token, 'revoked-' ) === 0 ) {
			return true;
		}

		$user_id = get_current_user_id();
		$revoked_value = 'revoked-' . time() . '-user-' . $user_id;

		update_post_meta( $post_id, '_orke_delivery_token', $revoked_value );

		error_log(
			sprintf(
				'Orkestone Agency Hub — Token revoked for config %d by user %d: %s',
				$post_id,
				$user_id,
				$revoked_value
			)
		);

		return true;
	}

	/**
	 * Regenerate a token for a configuration.
	 *
	 * Revokes the existing token and generates a new one (REQ-AH19).
	 * The old token becomes invalid immediately.
	 *
	 * @param int $post_id The configuration post ID.
	 * @return string|false New token, or false on failure.
	 */
	public static function regenerate_token( int $post_id ) {
		// Revoke the current token first.
		self::revoke_token( $post_id );

		// Generate a new token.
		return self::generate_token( $post_id );
	}

	/**
	 * Handle the "Revoke Token" admin action.
	 */
	public static function handle_revoke_token(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'orkestone-agency-hub' ) );
		}

		check_admin_referer( 'orke_revoke_token' );

		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		if ( $post_id < 1 ) {
			wp_die( esc_html__( 'Invalid post ID.', 'orkestone-agency-hub' ) );
		}

		self::revoke_token( $post_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'         => $post_id,
					'action'       => 'edit',
					'orke_revoked' => '1',
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the "Regenerate Token" admin action.
	 */
	public static function handle_regenerate_token(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'orkestone-agency-hub' ) );
		}

		check_admin_referer( 'orke_regenerate_token' );

		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		if ( $post_id < 1 ) {
			wp_die( esc_html__( 'Invalid post ID.', 'orkestone-agency-hub' ) );
		}

		self::regenerate_token( $post_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'           => $post_id,
					'action'         => 'edit',
					'orke_regenerated' => '1',
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Add a meta box to the configuration edit screen showing token status.
	 *
	 * Displays:
	 * - Current token (if exists) or "No token generated"
	 * - Token status (valid, revoked, expired)
	 * - Expiry time
	 * - Action buttons: Revoke / Regenerate
	 * - Mark as Paid (if draft and user has manage_options)
	 */
	public static function add_token_meta_box(): void {
		add_meta_box(
			'orke_token_status',
			__( 'Token & Payment Status', 'orkestone-agency-hub' ),
			array( __CLASS__, 'render_token_meta_box' ),
			Orkestone_Configuration_CPT::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render the token status meta box.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public static function render_token_meta_box( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$post_id = $post->ID;

		// Payment status.
		$payment_status = get_post_meta( $post_id, '_orke_payment_status', true );
		$payment_id     = get_post_meta( $post_id, '_orke_payment_id', true );
		$post_status    = $post->post_status;

		// Token data.
		$token       = get_post_meta( $post_id, '_orke_delivery_token', true );
		$expires_at  = intval( get_post_meta( $post_id, '_orke_token_expires_at', true ) );
		$is_revoked  = ! empty( $token ) && strpos( $token, 'revoked-' ) === 0;
		$is_expired  = ! $is_revoked && $expires_at > 0 && time() > $expires_at;

		// Payment status badge.
		$status_label = ucfirst( ! empty( $payment_status ) ? $payment_status : 'pending' );
		$badge_class  = 'orke-hub-badge--' . $payment_status;
		?>
		<div class="orke-hub-meta-box-content">
			<div class="orke-hub-meta-row">
				<span class="orke-hub-meta-label"><?php esc_html_e( 'Status', 'orkestone-agency-hub' ); ?></span>
				<span class="orke-hub-meta-value">
					<span class="orke-hub-badge <?php echo esc_attr( $badge_class ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</span>
			</div>

			<div class="orke-hub-meta-row">
				<span class="orke-hub-meta-label"><?php esc_html_e( 'Post Status', 'orkestone-agency-hub' ); ?></span>
				<span class="orke-hub-meta-value"><?php echo esc_html( $post_status ); ?></span>
			</div>

			<?php if ( ! empty( $payment_id ) ) : ?>
				<div class="orke-hub-meta-row">
					<span class="orke-hub-meta-label"><?php esc_html_e( 'Payment ID', 'orkestone-agency-hub' ); ?></span>
					<span class="orke-hub-meta-value" style="font-size:11px;word-break:break-all;"><?php echo esc_html( $payment_id ); ?></span>
				</div>
			<?php endif; ?>

			<hr style="margin:12px 0;" />

			<?php if ( ! empty( $token ) ) : ?>
				<div class="orke-hub-meta-row">
					<span class="orke-hub-meta-label"><?php esc_html_e( 'Token', 'orkestone-agency-hub' ); ?></span>
					<span class="orke-hub-meta-value" style="font-size:11px;word-break:break-all;font-family:monospace;">
						<?php echo $is_revoked ? esc_html__( 'Revoked', 'orkestone-agency-hub' ) : esc_html( $token ); ?>
					</span>
				</div>

				<?php if ( $is_revoked ) : ?>
					<div class="orke-hub-meta-row">
						<span class="orke-hub-meta-label"><?php esc_html_e( 'Token Status', 'orkestone-agency-hub' ); ?></span>
						<span class="orke-hub-badge orke-hub-badge--revoked"><?php esc_html_e( 'Revoked', 'orkestone-agency-hub' ); ?></span>
					</div>
				<?php elseif ( $is_expired ) : ?>
					<div class="orke-hub-meta-row">
						<span class="orke-hub-meta-label"><?php esc_html_e( 'Token Status', 'orkestone-agency-hub' ); ?></span>
						<span class="orke-hub-badge orke-hub-badge--revoked"><?php esc_html_e( 'Expired', 'orkestone-agency-hub' ); ?></span>
					</div>
				<?php else : ?>
					<div class="orke-hub-meta-row">
						<span class="orke-hub-meta-label"><?php esc_html_e( 'Expires', 'orkestone-agency-hub' ); ?></span>
						<span class="orke-hub-meta-value">
							<?php echo esc_html( $expires_at > 0 ? gmdate( 'Y-m-d H:i', $expires_at ) : '—' ); ?>
						</span>
					</div>
				<?php endif; ?>

				<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
					<?php if ( ! $is_revoked ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orke_revoke_token&post_id=' . $post_id ), 'orke_revoke_token' ) ); ?>"
							class="orke-hub-button orke-hub-button--danger"
							onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke this token? The client will no longer be able to activate using it.', 'orkestone-agency-hub' ); ?>');">
							<?php esc_html_e( 'Revoke Token', 'orkestone-agency-hub' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orke_regenerate_token&post_id=' . $post_id ), 'orke_regenerate_token' ) ); ?>"
						class="orke-hub-button orke-hub-button--secondary"
						onclick="return confirm('<?php esc_attr_e( 'Regenerating will revoke the current token. Continue?', 'orkestone-agency-hub' ); ?>');">
						<?php esc_html_e( 'Regen Token', 'orkestone-agency-hub' ); ?>
					</a>
				</div>

			<?php elseif ( 'publish' === $post_status ) : ?>
				<p><em><?php esc_html_e( 'No token generated yet.', 'orkestone-agency-hub' ); ?></em></p>
			<?php endif; ?>

			<?php if ( 'draft' === $post_status && current_user_can( 'manage_options' ) ) : ?>
				<hr style="margin:12px 0;" />
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orke_mark_as_paid&post_id=' . $post_id ), 'orke_mark_as_paid' ) ); ?>"
					class="orke-hub-button orke-hub-button--primary"
					onclick="return confirm('<?php esc_attr_e( 'Mark this configuration as paid? This will transition it to published and generate an activation token.', 'orkestone-agency-hub' ); ?>');">
					<?php esc_html_e( 'Mark as Paid', 'orkestone-agency-hub' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( ! empty( $is_revoked ) || $is_expired ) : ?>
				<hr style="margin:12px 0;" />
				<div class="orke-hub-notice orke-hub-notice--warning" style="margin:0;font-size:12px;">
					<?php esc_html_e( 'This token is no longer valid. Use "Regen Token" to create a new one.', 'orkestone-agency-hub' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Clean up expired tokens.
	 *
	 * Marks tokens that have passed their expiry as revoked.
	 * Runs daily via WP Cron.
	 */
	public static function cleanup_expired_tokens(): void {
		$posts = get_posts(
			array(
				'post_type'      => Orkestone_Configuration_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_query'     => array(
					array(
						'key'     => '_orke_token_expires_at',
						'value'   => time(),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_orke_delivery_token',
						'value'   => 'revoked-',
						'compare' => 'NOT LIKE',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			$token = get_post_meta( $post_id, '_orke_delivery_token', true );
			if ( ! empty( $token ) && strpos( $token, 'revoked-' ) !== 0 ) {
				update_post_meta( $post_id, '_orke_delivery_token', 'revoked-' . time() . '-expired' );
				error_log(
					sprintf(
						'Orkestone Agency Hub — Token auto-revoked (expired) for config %d: %s',
						$post_id,
						$token
					)
				);
			}
		}
	}

	/**
	 * Check if a token is valid for a given origin.
	 *
	 * If `_orke_token_allowed_origin` is set, validates that the request
	 * origin matches the bound client URL.
	 *
	 * @param string $token  The activation token.
	 * @param string $origin The request Origin header value.
	 * @return bool True if origin is allowed or validation is not configured.
	 */
	public static function validate_origin( string $token, string $origin ): bool {
		if ( empty( $origin ) ) {
			// No origin header — allow (legacy clients).
			return true;
		}

		$post_id = self::validate_token( $token );
		if ( ! $post_id ) {
			return false;
		}

		$allowed_origin = get_post_meta( $post_id, '_orke_token_allowed_origin', true );
		if ( empty( $allowed_origin ) ) {
			// No origin bound to token — allow.
			return true;
		}

		// Normalize URLs for comparison.
		$allowed_host = strtolower( wp_parse_url( $allowed_origin, PHP_URL_HOST ) );
		$request_host = strtolower( wp_parse_url( $origin, PHP_URL_HOST ) );

		return $allowed_host === $request_host;
	}

	/**
	 * Get the configuration JSON for a valid token.
	 *
	 * @param string $token The activation token.
	 * @return array|null The config array, or null if invalid.
	 */
	public static function get_config_for_token( string $token ): ?array {
		$post_id = self::validate_token( $token );
		if ( ! $post_id ) {
			return null;
		}

		$json_blob = get_post_meta( $post_id, '_orke_vertical_json_blob', true );
		if ( empty( $json_blob ) ) {
			// Fall back to post_content.
			$post = get_post( $post_id );
			if ( $post && ! empty( $post->post_content ) ) {
				$config = json_decode( $post->post_content, true );
				return is_array( $config ) ? $config : null;
			}
			return null;
		}

		$config = json_decode( $json_blob, true );
		return is_array( $config ) ? $config : null;
	}
}
