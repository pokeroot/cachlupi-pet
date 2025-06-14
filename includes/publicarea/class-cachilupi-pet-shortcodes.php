<?php

namespace CachilupiPet\PublicArea;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Autoloader should handle Utils class: \CachilupiPet\Utils\Cachilupi_Pet_Utils

/**
 * Handles the registration and rendering of plugin shortcodes.
 *
 * @package CachilupiPet\PublicArea
 */
class Cachilupi_Pet_Shortcodes {

	/**
	 * Constructor.
	 * Can be used to inject dependencies if needed in the future.
	 */
	public function __construct() {
		// Initialization, if any.
	}

	/**
	 * Renders the driver panel shortcode.
	 *
	 * @return string HTML output for the driver panel.
	 */
	public function render_driver_panel_shortcode() {
		if ( ! current_user_can( 'access_driver_panel' ) ) {
			return '<p>' . esc_html__( 'No tienes permiso para acceder a este panel.', 'cachilupi-pet' ) . '</p>';
		}
		$user = wp_get_current_user(); // Still needed for current_driver_id

		ob_start(); // Start output buffering
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		$current_driver_id = $user->ID;

		// Fetch all requests for the current driver (any status) AND all 'pending' unassigned requests.
		// Sorted by time DESC, then created_at DESC.
		$all_requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name as client_name
				 FROM {$table_name} r
				 LEFT JOIN {$wpdb->users} u ON r.client_user_id = u.ID
				 WHERE (r.driver_id = %d OR (r.status = %s AND r.driver_id IS NULL))
				 ORDER BY r.time DESC, r.created_at DESC",
				$current_driver_id,
				'pending'
			)
		);

		$active_requests = [];
		$historical_requests = [];

		$active_statuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'picked_up'];
		$historical_statuses = ['completed', 'rejected'];

		if ( $all_requests ) {
			foreach ( $all_requests as $request ) {
				if ( in_array( $request->status, $active_statuses, true ) ) {
					// Include unassigned pending requests for all drivers to see
					if ( $request->status === 'pending' && is_null( $request->driver_id ) ) {
						$active_requests[] = $request;
					} elseif ( ! is_null( $request->driver_id ) && $request->driver_id == $current_driver_id ) { // Using == for DB value comparison is common in WP
						// Include requests assigned to the current driver
						$active_requests[] = $request;
					}
				} elseif ( in_array( $request->status, $historical_statuses, true ) && ! is_null( $request->driver_id ) && $request->driver_id == $current_driver_id ) { // Using == for DB value comparison
					$historical_requests[] = $request;
				}
			}
		}
		?>
		<div class="wrap cachilupi-driver-panel">
			<h2><?php esc_html_e('Panel del Conductor', 'cachilupi-pet'); ?></h2>
			<div id="driver-panel-feedback" class="feedback-messages-container" style="margin-bottom: 15px;"></div>

			<h2 class="nav-tab-wrapper">
				<a href="#active-requests" class="nav-tab nav-tab-active"><?php esc_html_e('Solicitudes Activas', 'cachilupi-pet'); ?></a>
				<a href="#historical-requests" class="nav-tab"><?php esc_html_e('Historial de Solicitudes', 'cachilupi-pet'); ?></a>
			</h2>

			<div id="active-requests" class="tab-content">
				<?php if ( ! empty( $active_requests ) ) : ?>
					<table class="widefat fixed striped" cellspacing="0">
						<thead>
							<tr>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('ID', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Fecha y Hora', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Origen', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Cliente', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Destino', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Estado', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Mascota', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Instrucciones Mascota', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Notas', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Acciones', 'cachilupi-pet'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $active_requests as $request ) : ?>
								<tr data-request-id="<?php echo esc_attr( $request->id ); ?>">
									<td class="column-columnname" data-label="<?php esc_attr_e('ID:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->id ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Fecha y Hora:', 'cachilupi-pet'); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->time ) ) ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Origen:', 'cachilupi-pet'); ?>">
										<?php echo esc_html( $request->pickup_address ); ?>
										<br>
										<a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($request->pickup_address); ?>" target="_blank" class="map-link"><?php esc_html_e('Ver en Google Maps', 'cachilupi-pet'); ?></a>
									</td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Cliente:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->client_name ? $request->client_name : __('N/A', 'cachilupi-pet') ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Destino:', 'cachilupi-pet'); ?>">
										<?php echo esc_html( $request->dropoff_address ); ?>
										<br>
										<a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($request->dropoff_address); ?>" target="_blank" class="map-link"><?php esc_html_e('Ver en Google Maps', 'cachilupi-pet'); ?></a>
									</td>
									<td class="column-columnname request-status" data-label="<?php esc_attr_e('Estado:', 'cachilupi-pet'); ?>"><?php echo esc_html( \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request->status ) ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_type ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Instrucciones Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_instructions ? $request->pet_instructions : '--' ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Notas:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->notes ? $request->notes : '--'); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Acciones:', 'cachilupi-pet'); ?>">
										<?php
										$current_status_slug = strtolower( $request->status );
										$accept_class   = 'accept-request';
										$reject_class   = 'reject-request';
										$arrive_class   = 'arrive-request';
										$on_the_way_class = 'on-the-way-request';
										$picked_up_class = 'picked-up-request';
										$complete_class = 'complete-request';
										$action_button_shown = false;

										if ( $current_status_slug === 'pending' && (is_null($request->driver_id) || $request->driver_id == $current_driver_id) ) : ?>
											<button class="button <?php echo esc_attr($accept_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="accept"><?php esc_html_e('Aceptar', 'cachilupi-pet'); ?></button>
											<button class="button <?php echo esc_attr($reject_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="reject"><?php esc_html_e('Rechazar', 'cachilupi-pet'); ?></button>
											<?php $action_button_shown = true; ?>
										<?php elseif ( $current_status_slug === 'accepted' && $request->driver_id == $current_driver_id ) : ?>
											<button class="button <?php echo esc_attr($on_the_way_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="on_the_way"><?php esc_html_e('Iniciar Viaje', 'cachilupi-pet'); ?></button>
											<?php $action_button_shown = true; ?>
										<?php elseif ( $current_status_slug === 'on_the_way' && $request->driver_id == $current_driver_id ) : ?>
											<button class="button <?php echo esc_attr($arrive_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="arrive"><?php esc_html_e('He Llegado al Origen', 'cachilupi-pet'); ?></button>
											<?php $action_button_shown = true; ?>
										<?php elseif ( $current_status_slug === 'arrived' && $request->driver_id == $current_driver_id ) : ?>
											<button class="button <?php echo esc_attr($picked_up_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="picked_up"><?php esc_html_e('Mascota Recogida', 'cachilupi-pet'); ?></button>
											<?php $action_button_shown = true; ?>
										<?php elseif ( $current_status_slug === 'picked_up' && $request->driver_id == $current_driver_id ) : ?>
											<button class="button <?php echo esc_attr($complete_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="complete"><?php esc_html_e('Completar Viaje', 'cachilupi-pet'); ?></button>
											<?php $action_button_shown = true; ?>
										<?php endif; ?>

										<?php if ( !$action_button_shown ) : ?>
											<span><?php esc_html_e('--', 'cachilupi-pet'); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e('No hay solicitudes activas en este momento.', 'cachilupi-pet'); ?></p>
				<?php endif; ?>
			</div>

			<div id="historical-requests" class="tab-content" style="display:none;">
				<?php if ( ! empty( $historical_requests ) ) : ?>
					<table class="widefat fixed striped" cellspacing="0">
						<thead>
							<tr>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('ID', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Fecha y Hora', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Origen', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Cliente', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Destino', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Mascota', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Instrucciones Mascota', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Notas', 'cachilupi-pet'); ?></th>
								<th class="manage-column column-columnname" scope="col"><?php esc_html_e('Estado Final', 'cachilupi-pet'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $historical_requests as $request ) : ?>
								<tr data-request-id="<?php echo esc_attr( $request->id ); ?>">
									<td class="column-columnname" data-label="<?php esc_attr_e('ID:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->id ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Fecha y Hora:', 'cachilupi-pet'); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->time ) ) ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Origen:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pickup_address ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Cliente:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->client_name ? $request->client_name : __('N/A', 'cachilupi-pet') ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Destino:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->dropoff_address ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_type ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Instrucciones Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_instructions ? $request->pet_instructions : '--' ); ?></td>
									<td class="column-columnname" data-label="<?php esc_attr_e('Notas:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->notes ? $request->notes : '--'); ?></td>
									<td class="column-columnname request-status" data-label="<?php esc_attr_e('Estado Final:', 'cachilupi-pet'); ?>"><?php echo esc_html( \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request->status ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e('No hay solicitudes en el historial.', 'cachilupi-pet'); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				// Tab switching
				$( '.nav-tab-wrapper .nav-tab' ).click( function( e ) {
					e.preventDefault();
					var tab_id = $( this ).attr( 'href' );

					$( '.nav-tab-wrapper .nav-tab' ).removeClass( 'nav-tab-active' );
					$( '.tab-content' ).hide();

					$( this ).addClass( 'nav-tab-active' );
					$( tab_id ).show();
				} );
			} );
		</script>
		<?php
		return ob_get_clean(); // Return the buffered content
	}

	/**
	 * Renders the client booking form and map shortcode.
	 *
	 * @return string HTML output for the client booking form.
	 */
	public function render_client_booking_form_shortcode() {
		if ( ! current_user_can( 'access_booking_form' ) ) {
			return '<p>' . esc_html__( 'No tienes permiso para acceder a este formulario de reserva.', 'cachilupi-pet' ) . '</p>';
		}
		$user = wp_get_current_user(); // Still needed for user ID if they view their requests

		ob_start();
		?>
		<div class="cachilupi-booking-container">
			<div class="cachilupi-booking-form">
				<h1><?php esc_html_e('Solicitar Servicio', 'cachilupi-pet'); ?></h1>

				<fieldset id="cachilupi-trip-info-section">
					<legend><?php esc_html_e('Información del Viaje', 'cachilupi-pet'); ?></legend>

					<div class="form-group">
						<label id="pickup-location-label" for="pickup-location-input" class="required-field-label"><?php esc_html_e('Lugar de Recogida:', 'cachilupi-pet'); ?></label>
						<div id="pickup-geocoder-container" class="geocoder-container"></div>
					</div>

					<div class="form-group">
						<label id="dropoff-location-label" for="dropoff-location-input" class="required-field-label"><?php esc_html_e('Lugar de Destino:', 'cachilupi-pet'); ?></label>
						<div id="dropoff-geocoder-container" class="geocoder-container"></div>
					</div>

					<div class="form-group">
						<label for="service-date" class="required-field-label"><?php esc_html_e('Fecha de Servicio:', 'cachilupi-pet'); ?></label>
						<input type="text" id="service-date" class="required-field form-control cachilupi-datetime-picker" placeholder="<?php esc_attr_e('Selecciona fecha', 'cachilupi-pet'); ?>">
					</div>

					<div class="form-group">
						<label for="service-time" class="required-field-label"><?php esc_html_e('Hora de Servicio:', 'cachilupi-pet'); ?></label>
						<input type="text" id="service-time" class="required-field form-control cachilupi-datetime-picker" placeholder="<?php esc_attr_e('Selecciona hora', 'cachilupi-pet'); ?>">
					</div>

					 <div id="cachilupi-pet-distance" class="distance-display"></div>
				</fieldset>

				<fieldset id="cachilupi-pet-details-section">
					<legend><?php esc_html_e('Detalles de la Mascota', 'cachilupi-pet'); ?></legend>

					<div class="form-group">
						<label for="cachilupi-pet-pet-type" class="required-field-label"><?php esc_html_e('Tipo de Mascota:', 'cachilupi-pet'); ?></label>
						<select id="cachilupi-pet-pet-type" class="required-field form-control">
						<option value=""><?php esc_html_e('-- Selecciona una opción --', 'cachilupi-pet'); ?></option>
						<option value="perro"><?php esc_html_e('Perro', 'cachilupi-pet'); ?></option>
						<option value="gato"><?php esc_html_e('Gato', 'cachilupi-pet'); ?></option>
						<option value="otro"><?php esc_html_e('Otro', 'cachilupi-pet'); ?></option>
					</select>
					</div>

					<div class="form-group">
						<label for="cachilupi-pet-instructions"><?php esc_html_e('Instrucciones Específicas para la Mascota:', 'cachilupi-pet'); ?></label>
						<textarea id="cachilupi-pet-instructions" class="form-control" placeholder="<?php esc_attr_e('Ej: Comportamiento con extraños, si necesita bozal, si es amigable con otros animales, medicación, etc.', 'cachilupi-pet'); ?>"></textarea>
					</div>

					<div class="form-group">
						<label for="cachilupi-pet-notes"><?php esc_html_e('Notas Adicionales:', 'cachilupi-pet'); ?></label>
						<textarea id="cachilupi-pet-notes" class="form-control" placeholder="<?php esc_attr_e('Ej: Referencias de la dirección (casa esquina, portón rojo), consideraciones para el transporte (ej. necesita jaula grande), contacto alternativo, etc.', 'cachilupi-pet'); ?>"></textarea>
					</div>
				</fieldset>

				<button id="submit-service-request" type="button" class="button-primary"><?php esc_html_e('Solicitar Servicio', 'cachilupi-pet'); ?></button>

			</div>
			<div id="cachilupi-pet-map" class="booking-map"></div>
		</div>
		<?php

		// Section to display user's own requests
		if ( current_user_can( 'view_own_requests' ) || current_user_can( 'manage_options' ) ) {
			// Ensure $user is available if not already defined earlier in the broader scope (it is in this case)
			// $user = wp_get_current_user(); // This line would be redundant if $user is already from the top of the method
			global $wpdb;
			$requests_table_name = $wpdb->prefix . 'cachilupi_requests';
			$client_id = $user->ID; // This will show requests for the current user (client or admin acting as client)

			$all_client_requests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, u.display_name as driver_name
					 FROM {$requests_table_name} r
					 LEFT JOIN {$wpdb->users} u ON r.driver_id = u.ID
					 WHERE r.client_user_id = %d ORDER BY r.time DESC, r.created_at DESC",
					$client_id
				)
			);

			$active_client_requests = [];
			$historical_client_requests = [];
			$client_active_statuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'picked_up'];
			$client_historical_statuses = ['completed', 'rejected'];

			if ( $all_client_requests ) {
				foreach ( $all_client_requests as $request_item ) {
					if ( in_array( strtolower( $request_item->status ), $client_historical_statuses, true ) ) {
						$historical_client_requests[] = $request_item;
					} else {
						$active_client_requests[] = $request_item;
					}
				}
			}

			echo '<div class="cachilupi-client-requests-panel">';
			echo '<h2>' . esc_html__( 'Mis Solicitudes de Servicio', 'cachilupi-pet' ) . '</h2>';

			echo '<h2 class="nav-tab-wrapper">';
			echo '<a href="#client-active-requests" class="nav-tab nav-tab-active">' . esc_html__( 'Solicitudes Activas', 'cachilupi-pet' ) . '</a>';
			echo '<a href="#client-historical-requests" class="nav-tab">' . esc_html__( 'Historial de Solicitudes', 'cachilupi-pet' ) . '</a>';
			echo '</h2>';

			echo '<div id="client-active-requests" class="tab-content active">';
			if ( ! empty( $active_client_requests ) ) {
				echo '<table class="widefat fixed striped" cellspacing="0">';
				echo '<thead><tr>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__( 'ID', 'cachilupi-pet' ) . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Fecha Programada', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Origen', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Destino', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Mascota', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Estado', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Conductor', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Seguimiento', 'cachilupi-pet') . '</th>';
				echo '</tr></thead><tbody>';

				foreach ( $active_client_requests as $request_item ) {
					echo '<tr data-request-id="' . esc_attr( $request_item->id ) . '">';
					echo '<td class="column-columnname" data-label="' . esc_attr__('ID:', 'cachilupi-pet') . '">' . esc_html( $request_item->id ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Fecha Programada:', 'cachilupi-pet') . '">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request_item->time ) ) ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Origen:', 'cachilupi-pet') . '">' . esc_html( $request_item->pickup_address ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Destino:', 'cachilupi-pet') . '">' . esc_html( $request_item->dropoff_address ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Mascota:', 'cachilupi-pet') . '">' . esc_html( $request_item->pet_type ) . '</td>';
					$status_slug_class = 'request-status-' . esc_attr( strtolower( $request_item->status ) );
					echo '<td class="column-columnname request-status ' . $status_slug_class . '" data-label="' . esc_attr__('Estado:', 'cachilupi-pet') . '"><span>' . esc_html( \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request_item->status ) ) . '</span></td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Conductor:', 'cachilupi-pet') . '">' . esc_html( $request_item->driver_name ? $request_item->driver_name : __('No asignado aún', 'cachilupi-pet') ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Seguimiento:', 'cachilupi-pet') . '">';
					switch ( strtolower($request_item->status) ) {
						case 'pending':
							echo esc_html__('Seguimiento disponible una vez que el conductor acepte e inicie el viaje.', 'cachilupi-pet');
							break;
						case 'accepted':
							echo esc_html__('Seguimiento disponible cuando el conductor inicie el viaje.', 'cachilupi-pet');
							break;
						case 'on_the_way':
							if ( $request_item->driver_id ) {
								echo '<button class="button cachilupi-follow-driver-btn" data-request-id="' . esc_attr( $request_item->id ) . '">' . esc_html__('Seguir Viaje en Tiempo Real', 'cachilupi-pet') . '</button>';
							} else {
								echo esc_html__('Información de seguimiento no disponible.', 'cachilupi-pet');
							}
							break;
						case 'arrived':
							echo esc_html__('El conductor ha llegado al punto de recogida.', 'cachilupi-pet');
							break;
						case 'picked_up':
							 if ( $request_item->driver_id ) {
								echo '<button class="button cachilupi-follow-driver-btn" data-request-id="' . esc_attr( $request_item->id ) . '">' . esc_html__('Seguir Viaje (Mascota a Bordo)', 'cachilupi-pet') . '</button>';
							} else {
								echo esc_html__('Mascota recogida, seguimiento no disponible.', 'cachilupi-pet');
							}
							break;
						default:
							echo esc_html( \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request_item->status ) );
							break;
					}
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			} else {
				echo '<p>' . esc_html__( 'No tienes solicitudes activas en este momento.', 'cachilupi-pet' ) . '</p>';
			}
			echo '</div>';

			echo '<div id="client-historical-requests" class="tab-content" style="display:none;">';
			if ( ! empty( $historical_client_requests ) ) {
				echo '<table class="widefat fixed striped" cellspacing="0">';
				echo '<thead><tr>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__( 'ID', 'cachilupi-pet' ) . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Fecha Programada', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Origen', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Destino', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Mascota', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Estado Final', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Conductor', 'cachilupi-pet') . '</th>';
				echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Detalles del Viaje', 'cachilupi-pet') . '</th>';
				echo '</tr></thead><tbody>';

				foreach ( $historical_client_requests as $request_item ) {
					echo '<tr data-request-id="' . esc_attr( $request_item->id ) . '">';
					echo '<td class="column-columnname" data-label="' . esc_attr__('ID:', 'cachilupi-pet') . '">' . esc_html( $request_item->id ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Fecha Programada:', 'cachilupi-pet') . '">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request_item->time ) ) ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Origen:', 'cachilupi-pet') . '">' . esc_html( $request_item->pickup_address ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Destino:', 'cachilupi-pet') . '">' . esc_html( $request_item->dropoff_address ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Mascota:', 'cachilupi-pet') . '">' . esc_html( $request_item->pet_type ) . '</td>';
					$status_slug_class = 'request-status-' . esc_attr( strtolower( $request_item->status ) );
					echo '<td class="column-columnname request-status ' . $status_slug_class . '" data-label="' . esc_attr__('Estado Final:', 'cachilupi-pet') . '"><span>' . esc_html( \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request_item->status ) ) . '</span></td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Conductor:', 'cachilupi-pet') . '">' . esc_html( $request_item->driver_name ? $request_item->driver_name : __('N/A', 'cachilupi-pet') ) . '</td>';
					echo '<td class="column-columnname" data-label="' . esc_attr__('Detalles del Viaje:', 'cachilupi-pet') . '">';
					switch ( strtolower($request_item->status) ) {
						case 'completed':
							echo esc_html__('Viaje finalizado con éxito.', 'cachilupi-pet');
							break;
						case 'rejected':
							echo esc_html__('Solicitud rechazada.', 'cachilupi-pet');
							break;
						default:
							echo esc_html( \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request_item->status ) );
							break;
					}
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			} else {
				echo '<p>' . esc_html__( 'No tienes solicitudes en el historial.', 'cachilupi-pet' ) . '</p>';
			}
			echo '</div>';
			echo '</div>';

			echo '<div id="cachilupi-follow-modal" style="display:none; position:fixed; top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:10000;"><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80%;max-width:700px;height:70%;background-color:white;padding:20px;border-radius:8px;"><h3 id="cachilupi-follow-modal-title">' . esc_html__( 'Siguiendo Viaje', 'cachilupi-pet' ) . '</h3><div id="cachilupi-client-follow-map" style="width:100%;height:80%;"></div><button id="cachilupi-close-follow-modal" style="margin-top:10px;">' . esc_html__( 'Cerrar', 'cachilupi-pet' ) . '</button></div></div>';
		}
		return ob_get_clean();
	}

	// If cachilupi_pet_translate_status is needed before its own refactor,
	// it could be temporarily duplicated here as a private method.
	// private function translate_status( $status_slug ) { ... }
}
