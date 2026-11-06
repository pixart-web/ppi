<?php
/**
 * Dados pessoais: exportação e eliminação pelas ferramentas nativas do WordPress
 * (Ferramentas → Exportar/Apagar dados pessoais) e retenção automática dos contactos.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Privacy {

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', static function ( $e ) {
			$e['ppi-leads'] = array( 'exporter_friendly_name' => __( 'Contactos PPI', 'ppi-core' ), 'callback' => array( __CLASS__, 'export' ) );
			return $e;
		} );
		add_filter( 'wp_privacy_personal_data_erasers', static function ( $e ) {
			$e['ppi-leads'] = array( 'eraser_friendly_name' => __( 'Contactos PPI', 'ppi-core' ), 'callback' => array( __CLASS__, 'erase' ) );
			return $e;
		} );
		add_action( 'ppi_daily_maintenance', array( __CLASS__, 'retention' ) );
		add_action( 'admin_init', static function () {
			if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
				wp_add_privacy_policy_content( 'PPI Core', wp_kses_post( '<p>' . __( 'Os formulários guardam nome, email, telefone (opcional), mensagem, imóvel em causa, preferências de visita e a escolha de marketing, para responder ao pedido. O endereço IP não é guardado; é usado apenas um resumo criptográfico temporário para limitar abusos. Os contactos são eliminados automaticamente após o prazo definido nas Definições PPI (por defeito 730 dias).', 'ppi-core' ) . '</p>' ) );
			}
		} );
	}

	private static function by_email( string $email, int $page ): array {
		return get_posts( array(
			'post_type' => 'ppi_lead', 'post_status' => 'any', 'posts_per_page' => 50, 'paged' => $page, 'fields' => 'ids',
			'meta_key' => '_ppi_email', 'meta_value' => $email,
		) );
	}

	public static function export( string $email, int $page = 1 ): array {
		$data = array();
		foreach ( self::by_email( $email, $page ) as $id ) {
			$items = array();
			foreach ( array( 'type', 'name', 'email', 'phone', 'message', 'property_ref', 'visit_day', 'visit_period', 'contact_pref', 'marketing', 'lang' ) as $k ) {
				$v = (string) get_post_meta( $id, '_ppi_' . $k, true );
				if ( '' !== $v ) {
					$items[] = array( 'name' => $k, 'value' => $v );
				}
			}
			$items[] = array( 'name' => 'received', 'value' => wp_date( 'c', (int) get_post_meta( $id, '_ppi_received', true ) ) );
			$data[]  = array( 'group_id' => 'ppi-leads', 'group_label' => __( 'Contactos PPI', 'ppi-core' ), 'item_id' => 'lead-' . $id, 'data' => $items );
		}
		return array( 'data' => $data, 'done' => count( $data ) < 50 );
	}

	public static function erase( string $email, int $page = 1 ): array {
		$n = 0;
		foreach ( self::by_email( $email, 1 ) as $id ) {
			self::delete_lead( $id );
			$n++;
		}
		return array( 'items_removed' => $n, 'items_retained' => false, 'messages' => array(), 'done' => $n < 50 );
	}

	public static function delete_lead( int $id ): void {
		$cv = (string) get_post_meta( $id, '_ppi_cv_file', true );
		if ( $cv && preg_match( '/^[A-Za-z0-9]{24}\.pdf$/', $cv ) ) {
			wp_delete_file( Migrations::private_dir() . '/' . $cv );
		}
		wp_delete_post( $id, true );
	}

	public static function retention(): int {
		$days = max( 30, (int) Settings::get( 'retention_days' ) );
		$ids  = get_posts( array(
			'post_type' => 'ppi_lead', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 200,
			'meta_query' => array( array( 'key' => '_ppi_received', 'value' => time() - $days * DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ) ),
		) );
		foreach ( $ids as $id ) {
			self::delete_lead( $id );
		}
		return count( $ids );
	}
}

Privacy::init();
