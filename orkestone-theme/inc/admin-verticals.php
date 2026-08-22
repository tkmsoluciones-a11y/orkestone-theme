<?php
/**
 * Admin UI for importing and activating vertical JSON files.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin page under Appearance.
 *
 * @return void
 */
function vbb_register_verticals_admin_page() {
	// v0.3.2: Verticales JSON se registra como submenú del menú raíz VBB Pro Elite.
}
// Registro en Appearance desactivado intencionalmente.

/**
 * Handle admin form actions.
 *
 * @return array
 */
function vbb_handle_verticals_admin_actions() {
	$notice = array();

	if ( empty( $_POST['vbb_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $notice;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'type'    => 'error',
			'message' => __( 'No tienes permisos para administrar verticales.', 'vertical-block-base' ),
		);
	}

	check_admin_referer( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' );

	$action = sanitize_key( wp_unslash( $_POST['vbb_action'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( 'import_json' === $action ) {
		if ( empty( $_FILES['vbb_vertical_json']['tmp_name'] ) || ! is_uploaded_file( $_FILES['vbb_vertical_json']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return array(
				'type'    => 'error',
				'message' => __( 'Selecciona un archivo JSON válido.', 'vertical-block-base' ),
			);
		}

		$raw    = file_get_contents( $_FILES['vbb_vertical_json']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$config = json_decode( $raw, true );

		if ( ! is_array( $config ) ) {
			return array(
				'type'    => 'error',
				'message' => __( 'El archivo no es un JSON válido.', 'vertical-block-base' ),
			);
		}

		$result = vbb_save_imported_vertical_config( $config );

		if ( is_wp_error( $result ) ) {
			return array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
		}

		if ( ! empty( $_POST['vbb_activate_after_import'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_option( 'vbb_active_vertical', $result['key'] );
		}

		return array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: %s: vertical key */
				__( 'Vertical JSON importada correctamente: %s.', 'vertical-block-base' ),
				esc_html( $result['key'] )
			),
		);
	}

	if ( 'activate_vertical' === $action ) {
		$key       = isset( $_POST['vbb_vertical_key'] ) ? sanitize_key( wp_unslash( $_POST['vbb_vertical_key'] ) ) : '';
		$verticals = vbb_list_available_verticals();

		if ( '' === $key || empty( $verticals[ $key ] ) || empty( $verticals[ $key ]['valid'] ) ) {
			return array(
				'type'    => 'error',
				'message' => __( 'No se pudo activar la vertical seleccionada.', 'vertical-block-base' ),
			);
		}

		update_option( 'vbb_active_vertical', $key );

		return array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: %s: vertical key */
				__( 'Vertical activa actualizada: %s.', 'vertical-block-base' ),
				esc_html( $key )
			),
		);
	}

	if ( 'generate_pages' === $action ) {
		$summary = vbb_generate_vertical_pages();

		return array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: 1: created count, 2: skipped count, 3: errors count */
				__( 'Páginas procesadas. Creadas: %1$d. Omitidas: %2$d. Errores: %3$d.', 'vertical-block-base' ),
				count( $summary['created'] ),
				count( $summary['skipped'] ),
				count( $summary['errors'] )
			),
		);
	}

	if ( 'generate_navigation' === $action ) {
		$summary = vbb_generate_vertical_navigation();

		if ( ! empty( $summary['error'] ) || ! empty( $summary['reason'] ) ) {
			return array(
				'type'    => 'warning',
				'message' => __( 'Navegación procesada con advertencias. Revisa que navigation.primary exista en el JSON.', 'vertical-block-base' ),
			);
		}

		return array(
			'type'    => 'success',
			'message' => __( 'Navegación Gutenberg creada o actualizada desde el JSON.', 'vertical-block-base' ),
		);
	}

	if ( 'apply_front_page' === $action ) {
		$summary = vbb_apply_vertical_front_page();

		if ( empty( $summary['applied'] ) ) {
			return array(
				'type'    => 'warning',
				'message' => __( 'No se pudo asignar la página de inicio. Genera primero las páginas o revisa importOptions.homepageKey.', 'vertical-block-base' ),
			);
		}

		return array(
			'type'    => 'success',
			'message' => __( 'Página de inicio asignada correctamente desde el JSON.', 'vertical-block-base' ),
		);
	}

	if ( 'import_media' === $action ) {
		$limit   = isset( $_POST['vbb_media_limit'] ) ? absint( wp_unslash( $_POST['vbb_media_limit'] ) ) : 25;
		$summary = vbb_import_vertical_media( $limit );

		return array(
			'type'    => empty( $summary['errors'] ) ? 'success' : 'warning',
			'message' => sprintf(
				/* translators: 1: imported count, 2: skipped count, 3: errors count */
				__( 'Medios procesados. Importados: %1$d. Omitidos: %2$d. Errores: %3$d.', 'vertical-block-base' ),
				count( $summary['imported'] ),
				count( $summary['skipped'] ),
				count( $summary['errors'] )
			),
		);
	}

	if ( 'import_all' === $action ) {
		$summary = vbb_import_active_vertical_blueprint();

		return array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: 1: pages created, 2: pages skipped */
				__( 'Blueprint aplicado. Páginas creadas: %1$d. Páginas omitidas: %2$d.', 'vertical-block-base' ),
				count( $summary['pages']['created'] ),
				count( $summary['pages']['skipped'] )
			),
		);
	}

	if ( 'import_vertical' === $action ) {
		$key = vbb_get_active_vertical_key();

		if ( '' === $key ) {
			return array(
				'type'    => 'error',
				'message' => __( 'No hay una vertical activa para importar. Activa una vertical primero.', 'vertical-block-base' ),
			);
		}

		$result = vbb_import_vertical_full( $key );

		if ( empty( $result['success'] ) ) {
			$error_msg = isset( $result['error'] ) ? $result['error'] : __( 'Error desconocido.', 'vertical-block-base' );
			return array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: 1: vertical key, 2: error message */
					__( 'Error al importar "%1$s": %2$s', 'vertical-block-base' ),
					esc_html( $key ),
					esc_html( $error_msg )
				),
			);
		}

		// Build comprehensive import report message.
		$report = $result['report'];

		$message_parts = array();
		$message_parts[] = sprintf(
			/* translators: %d: number of pages created */
			__( 'Páginas: %d', 'vertical-block-base' ),
			$report['pages_created']
		);
		$message_parts[] = sprintf(
			/* translators: %d: number of media items imported */
			__( 'Medios: %d', 'vertical-block-base' ),
			$report['media_sideloaded']
		);

		if ( $report['media_failed'] > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: number of media failures */
				__( 'Fallos medios: %d', 'vertical-block-base' ),
				$report['media_failed']
			);
		}

		if ( ! empty( $result['navigation']['created'] ) ) {
			$message_parts[] = __( 'Nav: creada', 'vertical-block-base' );
		} elseif ( ! empty( $result['navigation']['updated'] ) ) {
			$message_parts[] = __( 'Nav: actualizada', 'vertical-block-base' );
		} else {
			$message_parts[] = __( 'Nav: omitida', 'vertical-block-base' );
		}

		if ( ! empty( $result['woocommerce']['configured'] ) ) {
			$message_parts[] = sprintf(
				/* translators: %s: catalog mode */
				__( 'WooCommerce: modo %s', 'vertical-block-base' ),
				$result['woocommerce']['mode']
			);
		} elseif ( ! empty( $result['woocommerce']['notice'] ) ) {
			$message_parts[] = __( 'WooCommerce: no activo', 'vertical-block-base' );
		}

		$message = sprintf(
			/* translators: 1: vertical key, 2: comma-separated report */
			__( 'Vertical "%1$s" importada correctamente. %2$s', 'vertical-block-base' ),
			esc_html( $key ),
			implode( ' — ', $message_parts )
		);

		return array(
			'type'    => 'success',
			'message' => $message,
		);
	}

	return $notice;
}

/**
 * Render admin page.
 *
 * @return void
 */
function vbb_render_verticals_admin_page() {
	$notice        = vbb_handle_verticals_admin_actions();
	$active_key    = vbb_get_active_vertical_key();
	$active_config = vbb_get_vertical_config();
	$verticals     = vbb_list_available_verticals();
	$media_count   = count( vbb_get_vertical_media_items() );
	?>
	<div class="wrap vbb-pro-wrap">
		<h1>OrkestOne Theme</h1>
		<p class="description"><?php echo esc_html__( 'Importa, activa y aplica verticales JSON sin editar archivos del theme manualmente.', 'vertical-block-base' ); ?></p>
		<?php if ( function_exists( 'vbb_pro_nav_tabs' ) ) { vbb_pro_nav_tabs(); } ?>
		<p><?php echo esc_html__( 'Importa, activa y aplica verticales JSON sin editar archivos del theme manualmente.', 'vertical-block-base' ); ?></p>

		<?php if ( ! empty( $notice['message'] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
		<?php endif; ?>

		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;max-width:1200px;">
			<div class="card" style="max-width:none;">
				<h2><?php echo esc_html__( 'Vertical activa', 'vertical-block-base' ); ?></h2>
				<p><strong><?php echo esc_html( $active_key ); ?></strong></p>
				<p><?php echo esc_html( isset( $active_config['name'] ) ? $active_config['name'] : '' ); ?></p>
				<p><?php echo esc_html( isset( $active_config['description'] ) ? $active_config['description'] : '' ); ?></p>
			</div>

			<div class="card" style="max-width:none;">
				<h2><?php echo esc_html__( 'Importar JSON', 'vertical-block-base' ); ?></h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="import_json" />
					<p><input type="file" name="vbb_vertical_json" accept="application/json,.json" required /></p>
					<label><input type="checkbox" name="vbb_activate_after_import" value="1" checked /> <?php echo esc_html__( 'Activar después de importar', 'vertical-block-base' ); ?></label>
					<p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Importar vertical JSON', 'vertical-block-base' ); ?></button></p>
				</form>
			</div>
		</div>

		<h2><?php echo esc_html__( 'Verticales disponibles', 'vertical-block-base' ); ?></h2>
		<table class="widefat striped" style="max-width:1200px;">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Key', 'vertical-block-base' ); ?></th>
					<th><?php echo esc_html__( 'Nombre', 'vertical-block-base' ); ?></th>
					<th><?php echo esc_html__( 'Origen', 'vertical-block-base' ); ?></th>
					<th><?php echo esc_html__( 'Estado', 'vertical-block-base' ); ?></th>
					<th><?php echo esc_html__( 'Acción', 'vertical-block-base' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $verticals as $vertical ) : ?>
					<tr>
						<td><code><?php echo esc_html( $vertical['key'] ); ?></code></td>
						<td><?php echo esc_html( $vertical['name'] ); ?></td>
						<td><?php echo esc_html( $vertical['source'] ); ?></td>
						<td><?php echo $vertical['valid'] ? esc_html__( 'Válida', 'vertical-block-base' ) : esc_html__( 'Inválida', 'vertical-block-base' ); ?></td>
						<td>
							<?php if ( $active_key === $vertical['key'] ) : ?>
								<strong><?php echo esc_html__( 'Activa', 'vertical-block-base' ); ?></strong>
							<?php elseif ( $vertical['valid'] ) : ?>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
									<input type="hidden" name="vbb_action" value="activate_vertical" />
									<input type="hidden" name="vbb_vertical_key" value="<?php echo esc_attr( $vertical['key'] ); ?>" />
									<button class="button" type="submit"><?php echo esc_html__( 'Activar', 'vertical-block-base' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Aplicar blueprint de la vertical activa', 'vertical-block-base' ); ?></h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:1200px;">
			<div class="card" style="max-width:none;border-left:4px solid #2271b1;">
				<h3><?php echo esc_html__( 'Importar Vertical (completo)', 'vertical-block-base' ); ?></h3>
				<p><?php echo esc_html__( 'Ejecuta el proceso completo: reset → medios → páginas → navegación → WooCommerce → informe. Recomendado para importaciones finales.', 'vertical-block-base' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="import_vertical" />
					<button class="button button-primary" type="submit"><?php echo esc_html__( 'Importar vertical activa', 'vertical-block-base' ); ?></button>
				</form>
			</div>

			<div class="card" style="max-width:none;">
				<h3><?php echo esc_html__( 'Páginas', 'vertical-block-base' ); ?></h3>
				<p><?php echo esc_html__( 'Crea las páginas declaradas en pages[] sin duplicar slugs existentes.', 'vertical-block-base' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="generate_pages" />
					<button class="button button-primary" type="submit"><?php echo esc_html__( 'Generar páginas', 'vertical-block-base' ); ?></button>
				</form>
			</div>

			<div class="card" style="max-width:none;">
				<h3><?php echo esc_html__( 'Menú / Navegación', 'vertical-block-base' ); ?></h3>
				<p><?php echo esc_html__( 'Crea o actualiza el menú OrkestOne Theme desde navigation.primary.', 'vertical-block-base' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="generate_navigation" />
					<button class="button" type="submit"><?php echo esc_html__( 'Generar navegación', 'vertical-block-base' ); ?></button>
				</form>
			</div>

			<div class="card" style="max-width:none;">
				<h3><?php echo esc_html__( 'Página de inicio', 'vertical-block-base' ); ?></h3>
				<p><?php echo esc_html__( 'Asigna la home según importOptions.homepageKey.', 'vertical-block-base' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="apply_front_page" />
					<button class="button" type="submit"><?php echo esc_html__( 'Asignar home', 'vertical-block-base' ); ?></button>
				</form>
			</div>

			<div class="card" style="max-width:none;">
				<h3><?php echo esc_html__( 'Gráficos / medios', 'vertical-block-base' ); ?></h3>
				<p><?php echo esc_html( sprintf( __( 'URLs detectadas en la vertical activa: %d.', 'vertical-block-base' ), $media_count ) ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="import_media" />
					<label><?php echo esc_html__( 'Límite por ejecución:', 'vertical-block-base' ); ?> <input type="number" name="vbb_media_limit" value="25" min="1" max="100" /></label>
					<p><button class="button" type="submit"><?php echo esc_html__( 'Importar medios', 'vertical-block-base' ); ?></button></p>
				</form>
			</div>

			<div class="card" style="max-width:none;">
				<h3><?php echo esc_html__( 'Importación rápida', 'vertical-block-base' ); ?></h3>
				<p><?php echo esc_html__( 'Ejecuta páginas, navegación y página de inicio. Los medios se importan aparte para evitar timeouts.', 'vertical-block-base' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vbb_verticals_admin_action', 'vbb_verticals_nonce' ); ?>
					<input type="hidden" name="vbb_action" value="import_all" />
					<button class="button button-primary" type="submit"><?php echo esc_html__( 'Aplicar blueprint', 'vertical-block-base' ); ?></button>
				</form>
			</div>
		</div>
	</div>
	<?php
}
