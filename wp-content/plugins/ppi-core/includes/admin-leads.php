<?php
/**
 * Contactos recebidos no painel. Acesso apenas com ppi_manage_leads (administradores e «Gestor de contactos»).
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Admin_Leads {

	public static function init(): void {
		add_action( 'add_meta_boxes_ppi_lead', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_ppi_lead', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_ppi_lead_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_ppi_lead_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'admin_post_ppi_lead_cv', array( __CLASS__, 'download_cv' ) );
		add_action( 'admin_post_ppi_lead_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_ppi_leads_export', array( __CLASS__, 'export' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'export_button' ) );
		add_filter( 'post_row_actions', static function ( $a, $post ) {
			if ( 'ppi_lead' === $post->post_type ) {
				unset( $a['inline hide-if-no-js'], $a['view'] );
			}
			return $a;
		}, 10, 2 );
		add_filter( 'bulk_actions-edit-ppi_lead', static fn( $a ) => array_intersect_key( $a, array( 'trash' => 1, 'delete' => 1 ) ) );
		add_action( 'admin_notices', array( __CLASS__, 'mail_notice' ) );
	}

	private static function m( int $id, string $k ): string {
		return (string) get_post_meta( $id, '_ppi_' . $k, true );
	}

	public static function boxes(): void {
		remove_meta_box( 'submitdiv', 'ppi_lead', 'side' );
		remove_meta_box( 'slugdiv', 'ppi_lead', 'normal' );
		add_meta_box( 'ppi-lead-detail', __( 'Pedido', 'ppi-core' ), array( __CLASS__, 'detail' ), 'ppi_lead', 'normal', 'high' );
		add_meta_box( 'ppi-lead-manage', __( 'Tratamento', 'ppi-core' ), array( __CLASS__, 'manage' ), 'ppi_lead', 'side', 'high' );
	}

	public static function detail( \WP_Post $post ): void {
		$id = $post->ID;
		$rows = array(
			__( 'Tipo', 'ppi-core' ) => Leads::types()[ self::m( $id, 'type' ) ] ?? self::m( $id, 'type' ),
			__( 'Recebido', 'ppi-core' ) => wp_date( 'Y-m-d H:i', (int) self::m( $id, 'received' ) ),
			__( 'Nome', 'ppi-core' ) => self::m( $id, 'name' ),
			__( 'Email', 'ppi-core' ) => self::m( $id, 'email' ),
			__( 'Telefone', 'ppi-core' ) => self::m( $id, 'phone' ),
			__( 'Contacto preferido', 'ppi-core' ) => self::m( $id, 'contact_pref' ),
			__( 'Idioma', 'ppi-core' ) => self::m( $id, 'lang' ),
			__( 'Marketing', 'ppi-core' ) => self::m( $id, 'marketing' ) ? __( 'Aceitou', 'ppi-core' ) : __( 'Não', 'ppi-core' ),
		);
		if ( self::m( $id, 'visit_day' ) || 'visit' === self::m( $id, 'type' ) ) {
			$rows[ __( 'Preferência de visita (por confirmar)', 'ppi-core' ) ] = trim( self::m( $id, 'visit_day' ) . ' · ' . self::m( $id, 'visit_period' ), ' ·' );
		}
		foreach ( get_post_meta( $id ) as $k => $v ) {
			if ( str_starts_with( $k, '_ppi_p_' ) && '' !== $v[0] ) {
				$rows[ ucfirst( str_replace( '_', ' ', substr( $k, 7 ) ) ) ] = $v[0];
			}
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $l => $v ) {
			echo '<tr><th scope="row">' . esc_html( $l ) . '</th><td>' . esc_html( $v ?: '—' ) . '</td></tr>';
		}
		if ( self::m( $id, 'property_ref' ) ) {
			$pid = (int) self::m( $id, 'property_id' );
			echo '<tr><th scope="row">' . esc_html__( 'Imóvel', 'ppi-core' ) . '</th><td>ref. <strong>' . esc_html( self::m( $id, 'property_ref' ) ) . '</strong> — ' . esc_html( self::m( $id, 'property_title' ) ) . '<br><a href="' . esc_url( self::m( $id, 'property_url' ) ) . '" target="_blank" rel="noopener">' . esc_html( self::m( $id, 'property_url' ) ) . '</a>' . ( $pid && current_user_can( 'edit_ppi_property', $pid ) ? ' · <a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html__( 'editar imóvel', 'ppi-core' ) . '</a>' : '' ) . '</td></tr>';
		}
		if ( self::m( $id, 'cv_file' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=ppi_lead_cv&lead=' . $id ), 'ppi_lead_cv_' . $id );
			echo '<tr><th scope="row">' . esc_html__( 'CV', 'ppi-core' ) . '</th><td><a href="' . esc_url( $url ) . '">' . esc_html__( 'Descarregar PDF', 'ppi-core' ) . '</a></td></tr>';
		}
		echo '</tbody></table><h3>' . esc_html__( 'Mensagem', 'ppi-core' ) . '</h3><div class="ppi-msg">' . nl2br( esc_html( self::m( $id, 'message' ) ?: '—' ) ) . '</div>';
	}

	public static function manage( \WP_Post $post ): void {
		$id = $post->ID;
		wp_nonce_field( 'ppi_lead_manage', 'ppi_lead_nonce' );
		$status = self::m( $id, 'status' ) ?: 'new';
		echo '<p><label for="ppi-lead-status">' . esc_html__( 'Estado', 'ppi-core' ) . '</label><br><select id="ppi-lead-status" name="ppi_lead_status">';
		foreach ( Leads::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></p>';
		$users = get_users( array( 'capability' => 'ppi_manage_leads', 'fields' => array( 'ID', 'display_name' ) ) );
		echo '<p><label for="ppi-lead-assignee">' . esc_html__( 'Responsável', 'ppi-core' ) . '</label><br><select id="ppi-lead-assignee" name="ppi_lead_assignee"><option value="0">—</option>';
		foreach ( $users as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( (int) self::m( $id, 'assignee' ), (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="ppi-lead-notes">' . esc_html__( 'Notas internas', 'ppi-core' ) . '</label><textarea id="ppi-lead-notes" name="ppi_lead_notes" rows="4" class="widefat">' . esc_textarea( self::m( $id, 'notes' ) ) . '</textarea></p>';
		submit_button( __( 'Guardar', 'ppi-core' ), 'primary', 'save', false );
		$ms = self::m( $id, 'mail_status' );
		$labels = array( 'sent' => __( 'Aceite pelo servidor de email', 'ppi-core' ), 'failed' => __( 'Falhou', 'ppi-core' ), 'pending' => __( 'Pendente', 'ppi-core' ), 'not_configured' => __( 'Email não configurado', 'ppi-core' ) );
		echo '<hr><p><strong>' . esc_html__( 'Notificação', 'ppi-core' ) . ':</strong> ' . esc_html( $labels[ $ms ] ?? $ms ) . '<br><span class="description">' . esc_html( sprintf( /* translators: %d attempts */ __( 'Tentativas: %d', 'ppi-core' ), (int) self::m( $id, 'mail_attempts' ) ) ) . ( self::m( $id, 'mail_to' ) ? ' · ' . esc_html( self::m( $id, 'mail_to' ) ) : '' ) . '</span>';
		if ( self::m( $id, 'mail_error' ) ) {
			echo '<br><span class="ppi-danger">' . esc_html( self::m( $id, 'mail_error' ) ) . '</span>';
		}
		echo '</p><p class="description">' . esc_html__( '«Aceite» confirma apenas que o servidor SMTP recebeu a mensagem; não garante a entrega.', 'ppi-core' ) . '</p>';
		if ( 'sent' !== $ms ) {
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ppi_lead_resend&lead=' . $id ), 'ppi_lead_resend_' . $id ) ) . '">' . esc_html__( 'Tentar enviar novamente', 'ppi-core' ) . '</a></p>';
		}
	}

	public static function save( int $id, \WP_Post $post ): void {
		if ( ! isset( $_POST['ppi_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppi_lead_nonce'] ) ), 'ppi_lead_manage' ) || ! current_user_can( 'ppi_manage_leads' ) ) {
			return;
		}
		$st = sanitize_key( $_POST['ppi_lead_status'] ?? 'new' );
		if ( isset( Leads::statuses()[ $st ] ) ) {
			update_post_meta( $id, '_ppi_status', $st );
		}
		$as = absint( $_POST['ppi_lead_assignee'] ?? 0 );
		update_post_meta( $id, '_ppi_assignee', ( $as && user_can( $as, 'ppi_manage_leads' ) ) ? $as : 0 );
		update_post_meta( $id, '_ppi_notes', sanitize_textarea_field( wp_unslash( (string) ( $_POST['ppi_lead_notes'] ?? '' ) ) ) );
		if ( 'private' !== $post->post_status ) {
			remove_action( 'save_post_ppi_lead', array( __CLASS__, 'save' ) );
			wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		}
	}

	public static function columns(): array {
		return array(
			'cb' => '<input type="checkbox">', 'title' => __( 'Pedido', 'ppi-core' ), 'ppi_type' => __( 'Tipo', 'ppi-core' ),
			'ppi_ref' => __( 'Imóvel', 'ppi-core' ), 'ppi_status' => __( 'Estado', 'ppi-core' ), 'ppi_assignee' => __( 'Responsável', 'ppi-core' ),
			'ppi_mail' => __( 'Notificação', 'ppi-core' ), 'ppi_received' => __( 'Recebido', 'ppi-core' ),
		);
	}

	public static function column( string $c, int $id ): void {
		switch ( $c ) {
			case 'ppi_type':
				echo esc_html( Leads::types()[ self::m( $id, 'type' ) ] ?? '' );
				break;
			case 'ppi_ref':
				echo esc_html( self::m( $id, 'property_ref' ) ?: '—' );
				break;
			case 'ppi_status':
				echo esc_html( Leads::statuses()[ self::m( $id, 'status' ) ] ?? '' );
				break;
			case 'ppi_assignee':
				$u = get_userdata( (int) self::m( $id, 'assignee' ) );
				echo esc_html( $u ? $u->display_name : '—' );
				break;
			case 'ppi_mail':
				$s = self::m( $id, 'mail_status' );
				echo '<span class="ppi-pill ppi-pill--' . esc_attr( 'sent' === $s ? 'ok' : ( 'failed' === $s ? 'bad' : 'wait' ) ) . '">' . esc_html( $s ) . '</span>';
				break;
			case 'ppi_received':
				echo esc_html( wp_date( 'Y-m-d H:i', (int) self::m( $id, 'received' ) ) );
				break;
		}
	}

	public static function filters( string $type ): void {
		if ( 'ppi_lead' !== $type ) {
			return;
		}
		foreach ( array( 'ppi_ltype' => Leads::types(), 'ppi_lstatus' => Leads::statuses() ) as $name => $opts ) {
			$cur = isset( $_GET[ $name ] ) ? sanitize_key( $_GET[ $name ] ) : ''; // phpcs:ignore
			echo '<label class="screen-reader-text" for="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</label><select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '"><option value="">' . esc_html( 'ppi_ltype' === $name ? __( 'Todos os tipos', 'ppi-core' ) : __( 'Todos os estados', 'ppi-core' ) ) . '</option>';
			foreach ( $opts as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
		}
	}

	public static function query( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || 'ppi_lead' !== $q->get( 'post_type' ) ) {
			return;
		}
		$mq = array();
		foreach ( array( 'ppi_ltype' => '_ppi_type', 'ppi_lstatus' => '_ppi_status' ) as $p => $k ) {
			if ( ! empty( $_GET[ $p ] ) ) { // phpcs:ignore
				$mq[] = array( 'key' => $k, 'value' => sanitize_key( $_GET[ $p ] ) ); // phpcs:ignore
			}
		}
		if ( $mq ) {
			$q->set( 'meta_query', $mq );
		}
	}

	public static function export_button( string $which ): void {
		$s = get_current_screen();
		if ( 'top' === $which && $s && 'edit-ppi_lead' === $s->id && current_user_can( 'ppi_export_leads' ) ) {
			echo '<div class="alignleft actions"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ppi_leads_export' ), 'ppi_leads_export' ) ) . '">' . esc_html__( 'Exportar CSV', 'ppi-core' ) . '</a></div>';
		}
	}

	/** Neutraliza fórmulas em folhas de cálculo (=, +, -, @, tab, CR). */
	public static function csv_safe( string $v ): string {
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	}

	public static function export(): void {
		if ( ! current_user_can( 'ppi_export_leads' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'ppi-core' ), 403 );
		}
		check_admin_referer( 'ppi_leads_export' );
		$ids = get_posts( array( 'post_type' => 'ppi_lead', 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ppi-contactos-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		$cols = array( 'received', 'type', 'status', 'name', 'email', 'phone', 'contact_pref', 'property_ref', 'property_url', 'visit_day', 'visit_period', 'message', 'marketing', 'lang', 'mail_status' );
		fputcsv( $out, $cols, ',', '"', '\\' );
		foreach ( $ids as $id ) {
			$row = array();
			foreach ( $cols as $c ) {
				$v = self::m( $id, $c );
				if ( 'received' === $c ) {
					$v = wp_date( 'Y-m-d H:i', (int) $v );
				}
				$row[] = self::csv_safe( $v );
			}
			fputcsv( $out, $row, ',', '"', '\\' );
		}
		fclose( $out );
		exit;
	}

	public static function download_cv(): void {
		$id = absint( $_GET['lead'] ?? 0 );
		if ( ! current_user_can( 'ppi_manage_leads' ) || ! $id ) {
			wp_die( esc_html__( 'Sem permissão.', 'ppi-core' ), 403 );
		}
		check_admin_referer( 'ppi_lead_cv_' . $id );
		$name = basename( self::m( $id, 'cv_file' ) );
		$path = Migrations::private_dir() . '/' . $name;
		if ( ! $name || ! preg_match( '/^[A-Za-z0-9]{24}\.pdf$/', $name ) || ! is_file( $path ) ) {
			wp_die( esc_html__( 'Ficheiro não encontrado.', 'ppi-core' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="cv-lead-' . $id . '.pdf"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore
		exit;
	}

	public static function resend(): void {
		$id = absint( $_GET['lead'] ?? 0 );
		if ( ! current_user_can( 'ppi_manage_leads' ) || ! $id ) {
			wp_die( esc_html__( 'Sem permissão.', 'ppi-core' ), 403 );
		}
		check_admin_referer( 'ppi_lead_resend_' . $id );
		Mail::notify( $id );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}

	public static function mail_notice(): void {
		$s = get_current_screen();
		if ( ! $s || 'edit-ppi_lead' !== $s->id ) {
			return;
		}
		if ( ! Mail::configured() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'SMTP não configurado: os contactos ficam registados aqui, mas não são enviadas notificações por email.', 'ppi-core' ) . '</p></div>';
		}
		$failed = get_posts( array( 'post_type' => 'ppi_lead', 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_ppi_mail_status', 'meta_value' => 'failed' ) );
		if ( $failed ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Há notificações por email que falharam. Os contactos estão guardados; o sistema tenta reenviar a cada 15 minutos.', 'ppi-core' ) . '</p></div>';
		}
	}
}

Admin_Leads::init();
