<?php
/** Pro Elite root admin panel. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vbb_pro_admin_menu() {
	add_menu_page(
		'VBB Pro Elite',
		'VBB Pro Elite',
		'manage_options',
		'vbb-pro-elite',
		'vbb_pro_render_admin_page',
		'dashicons-admin-customizer',
		58
	);
	add_submenu_page( 'vbb-pro-elite', 'Dashboard', 'Dashboard', 'manage_options', 'vbb-pro-elite', 'vbb_pro_render_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Diseño', 'Diseño', 'manage_options', 'vbb-pro-elite-design', 'vbb_pro_render_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Verticales JSON', 'Verticales JSON', 'manage_options', 'vbb-verticals', 'vbb_render_verticals_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Bloques', 'Bloques', 'manage_options', 'vbb-pro-elite-blocks', 'vbb_pro_render_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Perfiles', 'Perfiles', 'manage_options', 'vbb-pro-elite-profiles', 'vbb_pro_render_admin_page' );
	add_submenu_page( 'vbb-pro-elite', 'Export / Import', 'Export / Import', 'manage_options', 'vbb-pro-elite-import-export', 'vbb_pro_render_admin_page' );
}
add_action( 'admin_menu', 'vbb_pro_admin_menu' );

function vbb_pro_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'vbb-pro-elite' ) && false === strpos( $hook, 'vbb-verticals' ) ) { return; }
	wp_enqueue_style( 'vbb-pro-admin', get_template_directory_uri() . '/assets/css/admin-pro.css', array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_script( 'vbb-pro-admin', get_template_directory_uri() . '/assets/js/admin-pro.js', array(), wp_get_theme()->get( 'Version' ), true );
}
add_action( 'admin_enqueue_scripts', 'vbb_pro_admin_assets' );

function vbb_pro_handle_admin_actions() {
	if ( empty( $_POST['vbb_pro_action'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	check_admin_referer( 'vbb_pro_elite_action', 'vbb_pro_nonce' );
	$action = sanitize_key( wp_unslash( $_POST['vbb_pro_action'] ) );

	if ( 'save' === $action || 'save_profile' === $action ) {
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
		if ( 'save_profile' === $action ) { vbb_pro_save_profile( $settings['profileName'], $settings ); }
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
			$settings = isset( $data['settings'] ) ? $data['settings'] : $data;
			vbb_pro_update_settings( $settings );
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
		'vbb-pro-elite-design'        => 'Diseño',
		'vbb-verticals'               => 'Verticales JSON',
		'vbb-pro-elite-blocks'        => 'Bloques',
		'vbb-pro-elite-profiles'      => 'Perfiles',
		'vbb-pro-elite-import-export' => 'Export / Import',
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
		<h1>VBB Pro Elite</h1>
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
