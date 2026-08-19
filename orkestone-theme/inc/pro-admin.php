<?php
/** Pro Elite root admin panel. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vbb_pro_admin_menu() {
	add_menu_page(
		'OrkestOne Theme',
		'OrkestOne Theme',
		'manage_options',
		'vbb-pro-elite',
		'vbb_pro_render_admin_page',
		'dashicons-admin-customizer',
		58
	);
	add_submenu_page( 'vbb-pro-elite', 'Dashboard', 'Dashboard', 'manage_options', 'vbb-pro-elite', 'vbb_pro_render_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Verticales JSON', 'Verticales JSON', 'manage_options', 'vbb-verticals', 'vbb_render_verticals_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Export / Import', 'Export / Import', 'manage_options', 'vbb-pro-elite-import-export', 'vbb_pro_render_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Command Center', 'Command Center', 'manage_options', 'vbb-command-center', 'vbb_pro_render_command_center' );
}
add_action( 'admin_menu', 'vbb_pro_admin_menu' );
function vbb_pro_admin_assets( $hook ) {
	// More permissive check: load if we are on any pro-elite related page
	if ( false === strpos( $hook, 'vbb-pro' ) && false === strpos( $hook, 'vbb-verticals' ) && false === strpos( $hook, 'vbb-command-center' ) ) { return; }
	
	wp_enqueue_style( 'vbb-pro-admin', get_template_directory_uri() . '/assets/css/admin-pro.css', array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_script( 'vbb-pro-admin', get_template_directory_uri() . '/assets/js/admin-pro.js', array(), wp_get_theme()->get( 'Version' ) . '-' . time(), true );

	if ( false !== strpos( $hook, 'vbb-command-center' ) ) {
		wp_enqueue_media();
		$home_url = home_url( '/' );
		$presets = vbb_pro_get_builtin_presets();
		// Only pass name and presetKey to keep payload small; settings are fetched per-apply.
		$preset_list = array();
		foreach ( $presets as $key => $data ) {
			$preset_list[ $key ] = array(
				'name'      => $data['name'] ?? $key,
				'presetKey' => $data['presetKey'] ?? $key,
				'settings'  => $data['settings'] ?? array(),
			);
		}
		wp_enqueue_script( 'vbb-sortable', get_template_directory_uri() . '/assets/vendor/Sortable.min.js', array(), '1.15.6', true );
		wp_localize_script(
			'vbb-pro-admin',
			'vbbCommandCenterData',
			array(
				'restUrl'       => rest_url( 'orkestone/v1/' ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'previewUrl'    => $home_url . '?vbb_preview=' . time() . '&vbb_no_admin=1',
				'previewOrigin' => $home_url,
				'presets'       => $preset_list,
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'vbb_pro_admin_assets' );

function vbb_pro_handle_admin_actions() {
	if ( empty( $_POST['vbb_pro_action'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	check_admin_referer( 'vbb_pro_elite_action', 'vbb_pro_nonce' );
	$action = sanitize_key( wp_unslash( $_POST['vbb_pro_action'] ) );

	if ( 'save_profile' === $action ) {
		// Profiling via Command Center: the JS already persisted settings via XHR.
		// Read from the database to avoid empty $_POST wiping the data.
		$stored = vbb_pro_get_settings();
		$name   = sanitize_text_field( wp_unslash( $_POST['profileName'] ?? $stored['profileName'] ?? 'Pro Elite Profile' ) );
		vbb_pro_save_profile( $name, $stored );
		add_settings_error( 'vbb_pro_elite', 'saved', 'Perfil guardado desde la configuración actual.', 'updated' );
	} elseif ( 'save' === $action ) {
		$settings = array(
			'profileName' => sanitize_text_field( wp_unslash( $_POST['profileName'] ?? 'Pro Elite Profile' ) ),
			'colorMode'   => sanitize_key( wp_unslash( $_POST['colorMode'] ?? 'light' ) ),
			'palettes'    => array(
				'light' => isset( $_POST['palettes']['light'] ) ? wp_unslash( $_POST['palettes']['light'] ) : array(),
				'dark'  => isset( $_POST['palettes']['dark'] ) ? wp_unslash( $_POST['palettes']['dark'] ) : array(),
			),
			'typography'  => array(
				'heading' => wp_unslash( $_POST['typography']['heading'] ?? '' ),
				'body'    => wp_unslash( $_POST['typography']['body'] ?? '' ),
			),
			'layout'      => array(
				'contentWidth' => wp_unslash( $_POST['layout']['contentWidth'] ?? '' ),
				'wideWidth'    => wp_unslash( $_POST['layout']['wideWidth'] ?? '' ),
				'radius'       => wp_unslash( $_POST['layout']['radius'] ?? '' ),
				'shadow'       => wp_unslash( $_POST['layout']['shadow'] ?? '' ),
				'spacingScale' => wp_unslash( $_POST['layout']['spacingScale'] ?? '' ),
			),
			'blocks'      => array(
				'hero'         => ! empty( $_POST['blocks']['hero'] ),
				'servicesGrid' => ! empty( $_POST['blocks']['servicesGrid'] ),
				'benefits'     => ! empty( $_POST['blocks']['benefits'] ),
				'process'      => ! empty( $_POST['blocks']['process'] ),
				'testimonials' => ! empty( $_POST['blocks']['testimonials'] ),
				'faq'          => ! empty( $_POST['blocks']['faq'] ),
				'contact'      => ! empty( $_POST['blocks']['contact'] ),
				'ctaFinal'     => ! empty( $_POST['blocks']['ctaFinal'] ),
			),
			'buttons'     => array(
				'style'     => wp_unslash( $_POST['buttons']['style'] ?? '' ),
				'uppercase' => ! empty( $_POST['buttons']['uppercase'] ),
			),
		);
		$settings = vbb_pro_update_settings( $settings );
		add_settings_error( 'vbb_pro_elite', 'saved', 'Configuración Pro Elite guardada.', 'updated' );
	}

	if ( 'apply_preset' === $action ) {
		$preset = sanitize_key( wp_unslash( $_POST['presetKey'] ?? '' ) );
		$preset_settings = vbb_pro_get_preset_settings( $preset );
		if ( $preset_settings ) {
			vbb_pro_update_settings( $preset_settings );
			add_settings_error( 'vbb_pro_elite', 'preset', 'Preset aplicado.', 'updated' );
		}
	}

	if ( 'apply_profile' === $action ) {
		if ( vbb_pro_apply_profile( wp_unslash( $_POST['profileKey'] ?? '' ) ) ) {
			add_settings_error( 'vbb_pro_elite', 'profile', 'Perfil activado.', 'updated' );
		}
	}

	if ( 'reset' === $action ) {
		vbb_pro_reset_to_vertical();
		add_settings_error( 'vbb_pro_elite', 'reset', 'Configuración reiniciada desde la vertical activa.', 'updated' );
	}

	if ( 'import_json' === $action && ! empty( $_FILES['proJson']['tmp_name'] ) ) {
		$raw = file_get_contents( $_FILES['proJson']['tmp_name'] );
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			// Always restore global settings.
			$settings = isset( $data['settings'] ) ? $data['settings'] : $data;
			vbb_pro_update_settings( $settings );

			// Restore per-page overrides if present (schema >= 1.0.0).
			if ( isset( $data['pageOverrides'] ) && is_array( $data['pageOverrides'] ) ) {
				$existing = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
				foreach ( $data['pageOverrides'] as $page_id => $overrides ) {
					$page_id = (int) $page_id;
					if ( $page_id < 1 ) {
						continue; // skip invalid keys.
					}
					// Deep-merge with existing per-page settings to preserve data not in import.
					$existing[ $page_id ] = vbb_pro_deep_merge(
						isset( $existing[ $page_id ] ) ? $existing[ $page_id ] : array(),
						$overrides
					);
				}
				update_option( VBB_PRO_PAGE_SETTINGS_OPTION, $existing, false );
			}

			add_settings_error( 'vbb_pro_elite', 'imported', 'Configuración Pro Elite importada.', 'updated' );
		} else {
			add_settings_error( 'vbb_pro_elite', 'import_error', 'El JSON no es válido.', 'error' );
		}
	}
}
add_action( 'admin_init', 'vbb_pro_handle_admin_actions' );

function vbb_pro_export_url() {
	return wp_nonce_url( admin_url( 'admin-post.php?action=vbb_pro_export_settings' ), 'vbb_pro_export_settings' );
}

function vbb_pro_export_settings() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'vbb_pro_export_settings' ) ) { wp_die( 'No autorizado.' ); }
	$data = array(
		'exportedAt'   => current_time( 'mysql' ),
		'theme'        => 'vertical-block-base',
		'profileType'  => 'pro-elite-settings',
		'schemaVersion'=> '0.3.2',
		'settings'     => vbb_pro_get_settings(),
	);
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="vbb-pro-elite-settings-light-dark.json"' );
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}
add_action( 'admin_post_vbb_pro_export_settings', 'vbb_pro_export_settings' );

function vbb_pro_field_color_palette( $mode, $name, $label, $value ) {
	echo '<label><span>' . esc_html( $label ) . '</span><input type="color" name="palettes[' . esc_attr( $mode ) . '][' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '"></label>';
}

function vbb_pro_nav_tabs() {
	$tabs = array(
		'vbb-pro-elite'               => 'Dashboard',
		'vbb-verticals'               => 'Verticales JSON',
		'vbb-pro-elite-import-export' => 'Export / Import',
		'vbb-command-center'         => 'Command Center',
	);
	$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'vbb-pro-elite';
	echo '<nav class="nav-tab-wrapper vbb-pro-tabs">';
	foreach ( $tabs as $slug => $label ) {
		echo '<a class="nav-tab ' . esc_attr( $current === $slug ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
}

function vbb_pro_render_design_form( $s ) { ?>
	<h2>Diseño Light / Dark</h2>
	<label><span>Nombre del perfil</span><input type="text" name="profileName" value="<?php echo esc_attr( $s['profileName'] ); ?>"></label>
	<label><span>Modo de color</span><select name="colorMode">
		<?php foreach ( array( 'light' => 'Light', 'dark' => 'Dark', 'auto' => 'Auto según dispositivo' ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $s['colorMode'], $key, false ) . '>' . esc_html( $label ) . '</option>'; } ?>
	</select></label>
	<div class="vbb-pro-palettes">
		<div>
			<h3>Paleta Light</h3>
			<div class="vbb-pro-colors"><?php foreach ( $s['palettes']['light'] as $key => $value ) { vbb_pro_field_color_palette( 'light', $key, $key, $value ); } ?></div>
		</div>
		<div>
			<h3>Paleta Dark</h3>
			<div class="vbb-pro-colors"><?php foreach ( $s['palettes']['dark'] as $key => $value ) { vbb_pro_field_color_palette( 'dark', $key, $key, $value ); } ?></div>
		</div>
	</div>
	<h3>Tipografías</h3>
	<label><span>Heading</span><input type="text" name="typography[heading]" value="<?php echo esc_attr( $s['typography']['heading'] ); ?>"></label>
	<label><span>Body</span><input type="text" name="typography[body]" value="<?php echo esc_attr( $s['typography']['body'] ); ?>"></label>
	<h3>Layout</h3>
	<div class="vbb-pro-row">
		<label><span>Content width</span><input type="text" name="layout[contentWidth]" value="<?php echo esc_attr( $s['layout']['contentWidth'] ); ?>"></label>
		<label><span>Wide width</span><input type="text" name="layout[wideWidth]" value="<?php echo esc_attr( $s['layout']['wideWidth'] ); ?>"></label>
		<label><span>Radius</span><input type="text" name="layout[radius]" value="<?php echo esc_attr( $s['layout']['radius'] ); ?>"></label>
	</div>
	<div class="vbb-pro-row">
		<label><span>Sombra</span><select name="layout[shadow]"><?php foreach ( array( 'none', 'soft', 'medium', 'strong' ) as $o ) { echo '<option value="' . esc_attr( $o ) . '" ' . selected( $s['layout']['shadow'], $o, false ) . '>' . esc_html( $o ) . '</option>'; } ?></select></label>
		<label><span>Espaciado</span><select name="layout[spacingScale]"><?php foreach ( array( 'compact', 'comfortable', 'wide' ) as $o ) { echo '<option value="' . esc_attr( $o ) . '" ' . selected( $s['layout']['spacingScale'], $o, false ) . '>' . esc_html( $o ) . '</option>'; } ?></select></label>
		<label><span>Botones</span><select name="buttons[style]"><?php foreach ( array( 'pill', 'rounded', 'square', 'outline' ) as $o ) { echo '<option value="' . esc_attr( $o ) . '" ' . selected( $s['buttons']['style'], $o, false ) . '>' . esc_html( $o ) . '</option>'; } ?></select></label>
	</div>
	<label class="vbb-pro-check"><input type="checkbox" name="buttons[uppercase]" value="1" <?php checked( $s['buttons']['uppercase'] ); ?>> Botones en mayúsculas</label>
<?php }

function vbb_pro_render_blocks_form( $s ) { ?>
	<h2>Bloques / secciones activas</h2>
	<p class="description">Estos toggles controlan también el generador de páginas.</p>
	<div class="vbb-pro-checks">
		<?php foreach ( $s['blocks'] as $key => $enabled ) : ?>
			<label><input type="checkbox" name="blocks[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $enabled ); ?>> <?php echo esc_html( $key ); ?></label>
		<?php endforeach; ?>
	</div>
<?php }

function vbb_pro_hidden_current_settings_fields( $s ) {
	echo '<input type="hidden" name="profileName" value="' . esc_attr( $s['profileName'] ) . '">';
	echo '<input type="hidden" name="colorMode" value="' . esc_attr( $s['colorMode'] ) . '">';
	foreach ( array( 'light', 'dark' ) as $mode ) { foreach ( $s['palettes'][ $mode ] as $key => $value ) { echo '<input type="hidden" name="palettes[' . esc_attr( $mode ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">'; } }
	foreach ( $s['typography'] as $key => $value ) { echo '<input type="hidden" name="typography[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">'; }
	foreach ( $s['layout'] as $key => $value ) { echo '<input type="hidden" name="layout[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">'; }
	foreach ( $s['buttons'] as $key => $value ) { echo '<input type="hidden" name="buttons[' . esc_attr( $key ) . ']" value="' . esc_attr( is_bool( $value ) ? ( $value ? 1 : 0 ) : $value ) . '">'; }
}

function vbb_pro_render_admin_page() {
	$s = vbb_pro_get_settings();
	$presets = vbb_pro_get_builtin_presets();
	$profiles = vbb_pro_get_profiles();
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'vbb-pro-elite';
	?>
	<div class="wrap vbb-pro-wrap">
		<h1>OrkestOne Theme</h1>
		<p class="description">Configuración visual avanzada con menú raíz, paletas Light/Dark, bloques, perfiles y export/import.</p>
		<?php settings_errors( 'vbb_pro_elite' ); vbb_pro_nav_tabs(); ?>

		<?php if ( 'vbb-pro-elite' === $page ) : ?>
			<div class="vbb-pro-grid"><div class="vbb-pro-card"><h2>Dashboard</h2><p><strong>Modo activo:</strong> <?php echo esc_html( $s['colorMode'] ); ?></p><p><strong>Perfil:</strong> <?php echo esc_html( $s['profileName'] ); ?></p><p>Usa las pestañas para editar diseño, bloques, verticales y perfiles.</p></div><div class="vbb-pro-card"><h2>Estado</h2><p>Light/Dark activo, export/import compatible y toggles conectados al generador.</p></div></div>
		<?php elseif ( 'vbb-pro-elite-profiles' === $page ) : ?>
			<div class="vbb-pro-grid"><form method="post" class="vbb-pro-card"><?php wp_nonce_field( 'vbb_pro_elite_action', 'vbb_pro_nonce' ); ?><input type="hidden" name="vbb_pro_action" value="apply_preset"><h2>Presets Pro</h2><select name="presetKey"><?php foreach ( $presets as $key => $preset ) { echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $preset['name'] ?? $key ) . '</option>'; } ?></select><p><button class="button button-primary">Aplicar preset</button></p></form><form method="post" class="vbb-pro-card"><?php wp_nonce_field( 'vbb_pro_elite_action', 'vbb_pro_nonce' ); ?><input type="hidden" name="vbb_pro_action" value="apply_profile"><h2>Perfiles guardados</h2><select name="profileKey"><?php foreach ( $profiles as $key => $profile ) { echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $profile['name'] ?? $key ) . '</option>'; } ?></select><p><button class="button">Activar perfil</button></p></form></div>
		<?php elseif ( 'vbb-pro-elite-import-export' === $page ) : ?>
			<div class="vbb-pro-grid"><form method="post" enctype="multipart/form-data" class="vbb-pro-card"><?php wp_nonce_field( 'vbb_pro_elite_action', 'vbb_pro_nonce' ); ?><input type="hidden" name="vbb_pro_action" value="import_json"><h2>Import / Export</h2><input type="file" name="proJson" accept="application/json,.json"><p><button class="button">Importar configuración</button> <a class="button button-primary" href="<?php echo esc_url( vbb_pro_export_url() ); ?>">Exportar JSON</a></p></form><form method="post" class="vbb-pro-card"><?php wp_nonce_field( 'vbb_pro_elite_action', 'vbb_pro_nonce' ); ?><input type="hidden" name="vbb_pro_action" value="reset"><h2>Reset</h2><p>Vuelve a los valores de la vertical activa.</p><button class="button">Resetear</button></form></div>
		<?php else : ?>
			<form method="post" class="vbb-pro-card vbb-pro-wide-card">
				<?php wp_nonce_field( 'vbb_pro_elite_action', 'vbb_pro_nonce' ); ?>
				<input type="hidden" name="vbb_pro_action" value="save">
				<?php if ( 'vbb-pro-elite-blocks' === $page ) { vbb_pro_hidden_current_settings_fields( $s ); vbb_pro_render_blocks_form( $s ); } else { vbb_pro_render_design_form( $s ); vbb_pro_render_blocks_form( $s ); } ?>
				<p class="submit"><button class="button button-primary">Guardar configuración</button> <button class="button" name="vbb_pro_action" value="save_profile">Guardar como perfil</button></p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the Command Center admin page with card UI and live preview iframe.
 *
 * @return void
 */
/**
 * Check if any published page content contains unresolved {{vbb_*}} placeholders.
 *
 * @return bool True if at least one published page has raw tokens.
 */
function vbb_pro_has_unresolved_tokens() {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $pages as $page_id ) {
		$content = get_post_field( 'post_content', $page_id );
		if ( false !== strpos( $content, '{{vbb_' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Show admin notice when pages contain unresolved {{vbb_*}} placeholders.
 *
 * The notice links to the Command Center, which has its own "Regenerate Pages" button.
 * Also provides a direct "Regenerate All Pages Now" action via admin-post.
 * Does NOT display on the Command Center page itself (redundant).
 */
function vbb_pro_show_regenerate_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( $screen && false !== strpos( $screen->id, 'vbb-command-center' ) ) {
		return;
	}

	// Check cached detection flag, or scan on first visit.
	$token_detected = get_option( 'vbb_tokens_detected', 'not_scanned' );

	if ( 'not_scanned' === $token_detected ) {
		$found = vbb_pro_has_unresolved_tokens();
		update_option( 'vbb_tokens_detected', $found ? 'yes' : 'no', false );
		$token_detected = $found ? 'yes' : 'no';
	}

	if ( 'yes' !== $token_detected ) {
		return;
	}

	$command_center_url = admin_url( 'admin.php?page=vbb-command-center' );
	$regenerate_url     = wp_nonce_url(
		add_query_arg( 'vbb_action', 'regenerate_all', admin_url( 'admin.php' ) ),
		'vbb_pro_regenerate_action',
		'vbb_pro_nonce'
	);
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong>OrkestOne Theme:</strong>
			<?php esc_html_e( 'Some pages contain placeholder tokens from the No-Code Builder that have not been baked yet.', 'orkest-one' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( $command_center_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Open Command Center', 'orkest-one' ); ?>
			</a>
			<a href="<?php echo esc_url( $regenerate_url ); ?>" class="button">
				<?php esc_html_e( 'Regenerate All Pages Now', 'orkest-one' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'vbb_pro_show_regenerate_notice' );

/**
 * Handle "Regenerate All Pages Now" action from the admin notice.
 *
 * Runs full regeneration, clears the token detection flag, and redirects
 * to the Command Center with a success count.
 */
function vbb_pro_handle_regenerate_action() {
	if ( empty( $_GET['vbb_action'] ) || 'regenerate_all' !== $_GET['vbb_action'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No autorizado.', 'orkest-one' ) );
	}

	check_admin_referer( 'vbb_pro_regenerate_action', 'vbb_pro_nonce' );

	$count = vbb_pro_regenerate_all_pages();
	update_option( 'vbb_tokens_detected', 'no', false );

	wp_safe_redirect(
		add_query_arg(
			'vbb_regenerated',
			$count,
			admin_url( 'admin.php?page=vbb-command-center' )
		)
	);
	exit;
}
add_action( 'admin_init', 'vbb_pro_handle_regenerate_action' );

/**
 * Inject postMessage receiver script into the preview page <head>.
 * Runs only when ?vbb_preview= is present in the frontend URL.
 */
/**
 * Inject Google Fonts stylesheet link(s) into the preview <head>.
 * Only loads fonts that look like a single font family (no comma = not a font stack).
 * Uses display=swap to avoid blocking text render (FOUT acceptable).
 */
function vbb_pro_inject_preview_fonts() {
	$s = vbb_pro_get_settings();
	$fonts = array();
	if ( ! empty( $s['typography']['heading'] ) && false === strpos( $s['typography']['heading'], ',' ) ) {
		$fonts[] = $s['typography']['heading'];
	}
	if ( ! empty( $s['typography']['body'] ) && false === strpos( $s['typography']['body'], ',' ) ) {
		$fonts[] = $s['typography']['body'];
	}
	if ( empty( $fonts ) ) {
		return;
	}
	// Deduplicate
	$fonts = array_unique( $fonts );
	$family_strings = array();
	foreach ( $fonts as $font ) {
		$family_strings[] = 'family=' . rawurlencode( $font ) . ':wght@300;400;500;600;700';
	}
	$url = 'https://fonts.googleapis.com/css2?' . implode( '&', $family_strings ) . '&display=swap';
	echo '<link rel="stylesheet" id="vbb-pro-google-fonts" href="' . esc_url( $url ) . '">' . "\n";
}

function vbb_pro_inject_preview_script() {
	if ( is_admin() || empty( $_GET['vbb_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$home_url = home_url( '/' );

	// Inject Google Fonts stylesheet before the receiver script
	vbb_pro_inject_preview_fonts();
	?>
	<script id="vbb-pro-preview-receiver">
	(function(){
		var styleEl = document.createElement('style');
		styleEl.id = 'vbb-pro-injected-css';
		document.head.appendChild(styleEl);

		// Inject highlight styles for the section scroll/click feedback
		var highlightStyle = document.createElement('style');
		highlightStyle.id = 'vbb-pro-highlight-css';
		highlightStyle.textContent = '.vbb-section-highlight{outline:3px solid #3B82F6!important;outline-offset:-3px;border-radius:4px;transition:outline-color .3s}.vbb-section-highlight-pulse{animation:vbb-highlight-pulse 2s ease-out}@keyframes vbb-highlight-pulse{0%{outline-color:#3B82F6}70%{outline-color:rgba(59,130,246,.3)}100%{outline-color:transparent}}';
		document.head.appendChild(highlightStyle);

		var allowedOrigin = new URL(window.location.href).searchParams.get('vbb_origin') || '<?php echo esc_js( $home_url ); ?>';

		window.addEventListener('message', function(event) {
			if (event.origin !== allowedOrigin) return;
			var data = event.data;
			if (!data || typeof data !== 'object' || !data.type) return;
			if (data.type.indexOf('vbb:') !== 0) return;

			if (data.type === 'vbb:css-vars' && data.styleTag) {
				// Accumulate: merge new vars with existing, preserving both
				if (styleEl.textContent.trim()) {
					// Append new vars, separated by a comment to avoid CSS conflicts
					styleEl.textContent += '\n/* auto-merged from CC */\n' + data.styleTag;
				} else {
					styleEl.textContent = data.styleTag;
				}
			} else if (data.type === 'vbb:setting-update' && data.path && data.value !== undefined) {
				// Single field update — rebuild CSS vars via AJAX is not practical here,
				// so we rely on vbb:css-vars for batch updates.
			} else if (data.type === 'vbb:dark-preview') {
				// Dark preview state — handled entirely via vbb:css-vars from the parent.
			} else if (data.type === 'vbb:scroll-to-section' && data.sectionKey) {
				var target = document.querySelector('.vbb-section-' + data.sectionKey);
				if (target) {
					target.scrollIntoView({ behavior: 'smooth', block: 'start' });
					target.classList.add('vbb-section-highlight', 'vbb-section-highlight-pulse');
					setTimeout(function() {
						target.classList.remove('vbb-section-highlight', 'vbb-section-highlight-pulse');
					}, 2500);
				}
			}
		});

		// Helper: determine field name from clicked element
		function vbbGetField(el) {
			var tag = el.tagName.toLowerCase();
			if (tag === 'h1' || tag === 'h2' || tag === 'h3') return 'title';
			if (tag === 'img') return 'image';
			if (el.classList.contains('vbb-eyebrow')) return 'eyebrow';
			if (el.classList.contains('vbb-tagline')) return 'tagline';
			// CTA buttons inside a buttons container
			if (el.closest('.wp-block-buttons')) {
				var btnGroup = el.closest('.wp-block-buttons');
				if (btnGroup) {
					var btns = btnGroup.querySelectorAll('a, .wp-block-button');
					for (var bi = 0; bi < btns.length; bi++) {
						if (btns[bi] === el || btns[bi].contains(el)) {
							return bi === 0 ? 'primaryCta' : 'secondaryCta';
						}
					}
				}
				return 'primaryCta';
			}
			// Items with structured data (testimonial, team, pricing, etc.)
			if (el.classList.contains('vbb-item-name') || el.classList.contains('vbb-item-title')) return 'title';
			if (el.classList.contains('vbb-item-subtitle')) return 'subtitle';
			if (el.classList.contains('vbb-item-text')) return 'text';
			// Subtitle fallback
			if (tag === 'p') return 'subtitle';
			// Description, list items
			if (tag === 'li') return 'item';
			return '';
		}

		// Click delegation — detect ctrl+click for card selection, plain click for section scroll
		document.addEventListener('click', function(e) {
			// Find the nearest element with data-block-key (added by block-baker.php)
			var blockEl = e.target.closest('[data-block-key]');
			if (!blockEl) {
				// Fallback: try .vbb-section class for backward compat
				blockEl = e.target.closest('.vbb-section');
				if (!blockEl) return;
			}
			var blockKey = blockEl.getAttribute('data-block-key');
			if (!blockKey) {
				// Extract from vbb-section-{key} class
				var classes = blockEl.className.split(' ');
				for (var ci = 0; ci < classes.length; ci++) {
					if (classes[ci].indexOf('vbb-section-') === 0 && classes[ci] !== 'vbb-section') {
						blockKey = classes[ci].replace('vbb-section-', '');
						break;
					}
				}
				if (!blockKey) return;
			}

			if (e.ctrlKey || e.metaKey) {
				// Ctrl+Click / Cmd+Click → select card in Command Center
				e.preventDefault();
				var field = vbbGetField(e.target);
				window.parent.postMessage({
					type: 'vbb:card-select',
					blockKey: blockKey,
					field: field,
					rect: blockEl.getBoundingClientRect()
				}, '*');
			} else {
				// Regular click → scroll to section in preview (existing behavior)
				window.parent.postMessage({
					type: 'vbb:section-clicked',
					sectionKey: blockKey
				}, '*');
			}
		});

		// Ctrl+Click on any clickable element inside a block — intercept before default
		document.addEventListener('mousedown', function(e) {
			if ((e.ctrlKey || e.metaKey) && e.target.closest('[data-block-key]')) {
				e.preventDefault();
			}
		});

		// Signal that the preview receiver is alive
		window.parent.postMessage({
			type: 'vbb:ready',
			title: document.title,
			url: window.location.href
		}, '*');
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'vbb_pro_inject_preview_script', 5 );

function vbb_pro_render_command_center() {
	$home_url  = home_url( '/' );
	$preview_url = add_query_arg(
		array(
			'vbb_preview' => time(),
			'vbb_no_admin' => '1',
			'vbb_origin'  => rawurlencode( $home_url ),
		),
		$home_url
	);

	// Show success message if redirected from regeneration action.
	$regenerated = isset( $_GET['vbb_regenerated'] ) ? absint( $_GET['vbb_regenerated'] ) : 0;
	?>
	<div class="wrap vbb-pro-wrap vbb-command-center">
		<div class="vbb-cc-header">
			<h1>Command Center</h1>
			<button id="vbb-cc-dark-toggle" class="vbb-cc-dark-toggle" aria-label="Toggle dark mode">
				<span class="vbb-cc-dark-toggle-icon">&#9790;</span>
			</button>
		</div>

		<!-- Toast notification container — fixed top-right, outside grid -->
		<div id="vbb-cc-toast-container" aria-live="polite"></div>

		<?php if ( $regenerated > 0 ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( sprintf( _n( '%d page regenerated successfully.', '%d pages regenerated successfully.', $regenerated, 'orkest-one' ), $regenerated ) ); ?></p>
			</div>
		<?php endif; ?>
		<p class="description">Interactive theme settings — changes are saved automatically with debounce and previewed live.</p>
		<?php settings_errors( 'vbb_pro_elite' ); vbb_pro_nav_tabs(); ?>

		<div class="vbb-cc-layout">
			<div class="vbb-cc-page-selector" id="vbb-page-selector">
				<p class="vbb-cc-loading"><?php esc_html_e( 'Loading pages…', 'orkest-one' ); ?></p>
			</div>

			<!-- Global status bar — spans full grid width -->
			<div id="vbb-cc-status-bar" class="vbb-cc-status-bar vbb-cc-status-bar--idle" style="display:none;">
				<span class="vbb-cc-status-icon"></span>
				<span class="vbb-cc-status-text"></span>
			</div>

			<div class="vbb-cc-cards" id="vbb-cc-cards">
				<p class="vbb-cc-loading">Loading Command Center…</p>
			</div>

			<div class="vbb-cc-preview-column">
<!-- Preview toolbar — Inside column, at the top -->
			<div class="vbb-cc-preview-toolbar">
				<div class="vbb-cc-preview-presets" id="vbb-cc-preview-presets">
					<button class="vbb-cc-preset-btn vbb-cc-preset-btn--active" data-width="desktop">Desktop</button>
					<button class="vbb-cc-preset-btn" data-width="768">Tablet</button>
					<button class="vbb-cc-preset-btn" data-width="375">Mobile</button>
				</div>
				<button class="vbb-cc-compare-btn" id="vbb-cc-compare-btn" title="Mostrar estado guardado / Current state">Comparar</button>
				<button class="vbb-cc-undo-btn" id="vbb-cc-undo" title="Deshacer última cambio" disabled>⇦</button>
				<button class="vbb-cc-redo-btn" id="vbb-cc-redo" title="Rehacer último cambio" disabled>⇨</button>
				<button class="vbb-cc-export-profile-btn" id="vbb-cc-export-profile" title="Exportar perfil JSON">Export</button>
				<button class="vbb-cc-import-profile-btn" id="vbb-cc-import-profile" title="Importar perfil JSON">Import</button>
				<button class="vbb-cc-dark-preview-btn" id="vbb-cc-dark-preview-btn" title="Toggle dark preview">&#x2600; Light</button>
				<button class="vbb-cc-preset-btn" id="vbb-cc-zoom-btn" title="Zoom 2x">&#x26B0;</button>
				<button class="button vbb-cc-preview-refresh" id="vbb-cc-preview-refresh" title="Refresh preview">&#x21bb;</button>
				<span id="vbb-cc-preview-url" style="display:none"></span>
				<button class="vbb-cc-preview-btn" id="vbb-cc-preview-open" title="Abrir en nueva pestaña">&#x2197;</button>
				<button class="vbb-cc-preview-btn" id="vbb-cc-preview-copy" title="Copiar enlace">&#x1F4CB;</button>
				<!-- Keyboard shortcut indicator -->
				<span class="vbb-cc-keyshort-indicator">Ctrl+S: guardar | Ctrl+Z: deshacer</span>
				</div>

<!-- Preview Iframe Container — taking up 100% remaining vertical space -->
			<div class="vbb-cc-preview">
				<h2>Live Preview</h2>
				<div class="vbb-cc-preview-viewport" id="vbb-cc-preview-viewport">
					<div id="vbb-cc-preview-overlay" class="vbb-cc-preview-overlay" style="display:none;">
						<div class="vbb-cc-preview-overlay-spinner"></div>
						<div class="vbb-cc-preview-overlay-text">Loading preview…</div>
					</div>
					<iframe id="vbb-cc-iframe" src="about:blank" data-src="<?php echo esc_url( $preview_url ); ?>" title="Live Preview" loading="lazy"></iframe>
				</div>
			</div>

				<!-- Actions row — Inside column, at the bottom -->
			<div class="vbb-cc-actions-row">
				<button class="button button-primary" id="vbb-cc-save-profile">Save as Profile</button>
				<button class="vbb-cc-undo-btn" id="vbb-cc-undo" title="Deshacer última cambio" disabled>⇦</button>
				<button class="vbb-cc-redo-btn" id="vbb-cc-redo" title="Rehacer último cambio" disabled>⇨</button>
				<button class="button" id="vbb-cc-export">Export Site</button>
				<button class="button" id="vbb-cc-regenerate">Regenerate Pages</button>
				<button class="button" id="vbb-cc-reset">Reset to Vertical Defaults</button>
			</div>
			</div>
		</div>

		<form method="post" id="vbb-cc-hidden-form" style="display:none;">
			<?php wp_nonce_field( 'vbb_pro_elite_action', 'vbb_pro_nonce' ); ?>
			<input type="hidden" name="vbb_pro_action" value="save">
		</form>
	</div>
	<?php
}
