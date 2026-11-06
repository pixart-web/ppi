<?php
/**
 * REST pública mínima (só leitura) para favoritos e comparação, e endurecimento da REST nativa.
 * Leads nunca são expostos (o tipo ppi_lead não tem show_in_rest). Coordenadas privadas e
 * dados de origem não estão registados para a REST.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		// Não listar utilizadores publicamente.
		add_filter( 'rest_endpoints', static function ( $e ) {
			if ( ! is_user_logged_in() ) {
				unset( $e['/wp/v2/users'], $e['/wp/v2/users/(?P<id>[\d]+)'] );
			}
			return $e;
		} );
		// Pedidos não autenticados à REST nativa de imóveis só veem publicados (comportamento do core) e sem conteúdo em revisão.
		add_filter( 'rest_ppi_property_query', static function ( $args ) {
			if ( ! current_user_can( 'edit_ppi_properties' ) ) {
				$args['post_status'] = 'publish';
			}
			return $args;
		} );
	}

	public static function routes(): void {
		register_rest_route( 'ppi/v1', '/cards', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array(
				'refs' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'lang' => array( 'type' => 'string', 'default' => 'pt', 'sanitize_callback' => 'sanitize_key' ),
			),
			'callback'            => array( __CLASS__, 'cards' ),
		) );
	}

	public static function id_by_ref( string $ref, string $lang ): int {
		$norm = Fields::normalize_reference( $ref );
		if ( '' === $norm ) {
			return 0;
		}
		$args = array(
			'post_type' => 'ppi_property', 'post_status' => array( 'publish' ), 'fields' => 'ids', 'posts_per_page' => 5, 'no_found_rows' => true,
			'meta_key' => 'ppi_reference', 'meta_value' => $ref, 'suppress_filters' => false,
		);
		if ( I18n::active() ) {
			$args['lang'] = $lang;
		}
		$ids = get_posts( $args );
		if ( ! $ids ) {
			// comparação normalizada (espaços/hífens) através do índice
			foreach ( Search::index( $lang ) as $r ) {
				if ( $r['refn'] === $norm ) {
					return (int) $r['id'];
				}
			}
			// pode existir apenas noutro idioma ou já não estar listável (vendido)
			unset( $args['lang'] );
			$ids = get_posts( $args );
		}
		return (int) ( $ids[0] ?? 0 );
	}

	public static function cards( \WP_REST_Request $req ): \WP_REST_Response {
		$lang = in_array( $req['lang'], I18n::languages(), true ) ? $req['lang'] : I18n::DEFAULT_LANG;
		if ( function_exists( 'PLL' ) && ( $l = PLL()->model->get_language( $lang ) ) ) {
			PLL()->curlang = $l;
		}
		switch_to_locale( 'en' === $lang ? 'en_GB' : 'pt_PT' );
		$refs = array_slice( array_unique( array_filter( array_map( static fn( $r ) => (string) Fields::sanitize( 'reference', $r )[0], explode( ',', (string) $req['refs'] ) ) ) ), 0, 50 );
		$out  = array();
		foreach ( $refs as $ref ) {
			$id = self::id_by_ref( $ref, $lang );
			$p  = $id ? Property::from( $id ) : null;
			if ( ! $p ) {
				$out[] = array( 'ref' => $ref, 'available' => false, 'missing' => true, 'title' => '', 'status' => __( 'Já não está disponível', 'ppi-core' ) );
				continue;
			}
			$d = $p->card_data();
			$d['html'] = $d['available'] ? Render::card( $p ) : '';
			$out[] = $d;
		}
		$res = new \WP_REST_Response( array( 'items' => $out ) );
		$res->header( 'Cache-Control', 'no-store' ); // disponibilidade tem de refletir o estado atual
		return $res;
	}
}

Rest::init();
