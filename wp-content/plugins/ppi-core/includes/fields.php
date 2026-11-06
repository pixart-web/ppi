<?php
/**
 * Esquema dos campos do imóvel: fonte única para o editor, o importador, a pesquisa,
 * a sincronização entre traduções e a REST pública.
 *
 * - fact: valor factual sincronizado entre PT e EN (preço, áreas, referência...). Traduzir não altera factos.
 * - public: pode aparecer na REST pública. Coordenadas privadas e dados de origem nunca são públicos.
 * - Valores ausentes são guardados como meta inexistente (null), nunca 0.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Fields {

	public const PREFIX = 'ppi_';

	/** Estados comerciais (independentes do estado de publicação do WordPress). */
	public static function commercial_statuses(): array {
		return array(
			'available' => __( 'Disponível', 'ppi-core' ),
			'reserved'  => __( 'Reservado', 'ppi-core' ),
			'sold'      => __( 'Vendido', 'ppi-core' ),
			'withdrawn' => __( 'Retirado do mercado', 'ppi-core' ),
		);
	}

	public static function businesses(): array {
		return array(
			'sale' => __( 'Venda', 'ppi-core' ),
			'rent' => __( 'Arrendamento', 'ppi-core' ),
		);
	}

	public static function energy_classes(): array {
		return array( 'A+', 'A', 'B', 'B-', 'C', 'D', 'E', 'F', 'G', 'Isento', 'Em execução' );
	}

	public static function conditions(): array {
		return array(
			'new'           => __( 'Novo', 'ppi-core' ),
			'under-construction' => __( 'Em construção', 'ppi-core' ),
			'project'       => __( 'Em projeto', 'ppi-core' ),
			'excellent'     => __( 'Excelente', 'ppi-core' ),
			'renovated'     => __( 'Remodelado', 'ppi-core' ),
			'recovered'     => __( 'Recuperado', 'ppi-core' ),
			'habitable'     => __( 'Habitável', 'ppi-core' ),
			'used'          => __( 'Usado', 'ppi-core' ),
			'reasonable'    => __( 'Razoável', 'ppi-core' ),
			'to-recover'    => __( 'Por recuperar', 'ppi-core' ),
			'ruin'          => __( 'Ruína', 'ppi-core' ),
		);
	}

	/** Mapeamento dos textos de estado da origem (HCPro) para as chaves internas. */
	public static function condition_from_source( ?string $v ): ?string {
		$map = array(
			'Novo' => 'new', 'Em Construção' => 'under-construction', 'Em Projecto' => 'project', 'Excelente' => 'excellent',
			'Remodelado' => 'renovated', 'Recuperado' => 'recovered', 'Habitável' => 'habitable', 'Usado' => 'used',
			'Razoável' => 'reasonable', 'Por Recuperar' => 'to-recover', 'Ruína' => 'ruin',
		);
		return $v && isset( $map[ $v ] ) ? $map[ $v ] : null;
	}

	/**
	 * Definição dos campos. type: string|int|float|bool|enum|text|url|ids|json.
	 * tab: separador do editor.
	 */
	public static function all(): array {
		static $fields = null;
		if ( null !== $fields ) {
			return $fields;
		}
		$fields = array(
			// Identificação
			'reference'         => array( 'type' => 'string', 'tab' => 'summary', 'fact' => true, 'public' => true, 'label' => __( 'Referência', 'ppi-core' ), 'required' => true, 'help' => __( 'Única. É a chave usada por favoritos, comparação, importação e traduções.', 'ppi-core' ) ),
			'business'          => array( 'type' => 'enum', 'options' => self::businesses(), 'tab' => 'summary', 'fact' => true, 'public' => true, 'label' => __( 'Negócio', 'ppi-core' ), 'default' => 'sale' ),
			'commercial_status' => array( 'type' => 'enum', 'options' => self::commercial_statuses(), 'tab' => 'summary', 'fact' => true, 'public' => true, 'label' => __( 'Estado comercial', 'ppi-core' ), 'default' => 'available', 'help' => __( 'Independente da publicação. Vendido mantém a página com indicação; Retirado devolve «indisponível» (410).', 'ppi-core' ) ),
			'price'             => array( 'type' => 'int', 'tab' => 'summary', 'fact' => true, 'public' => true, 'label' => __( 'Preço (€)', 'ppi-core' ), 'min' => 0, 'max' => 100000000, 'help' => __( 'Deixe vazio se não houver preço publicado. Nunca usar 0.', 'ppi-core' ) ),
			'price_on_request'  => array( 'type' => 'bool', 'tab' => 'summary', 'fact' => true, 'public' => true, 'label' => __( 'Preço sob consulta', 'ppi-core' ) ),
			'currency'          => array( 'type' => 'string', 'tab' => 'summary', 'fact' => true, 'public' => true, 'label' => __( 'Moeda', 'ppi-core' ), 'default' => 'EUR' ),
			'badge'             => array( 'type' => 'string', 'tab' => 'summary', 'fact' => false, 'public' => true, 'label' => __( 'Etiqueta editorial', 'ppi-core' ), 'help' => __( 'Opcional, curta (ex.: «Descida de preço»). Só afirmações verdadeiras.', 'ppi-core' ) ),
			// Características principais
			'subtype'           => array( 'type' => 'string', 'tab' => 'details', 'fact' => false, 'public' => true, 'label' => __( 'Subtipo', 'ppi-core' ) ),
			'typology'          => array( 'type' => 'string', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Tipologia', 'ppi-core' ), 'help' => __( 'T0…T6+. Vazio em terrenos.', 'ppi-core' ) ),
			'bedrooms'          => array( 'type' => 'int', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Quartos', 'ppi-core' ), 'min' => 0, 'max' => 50 ),
			'bathrooms'         => array( 'type' => 'int', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Casas de banho', 'ppi-core' ), 'min' => 0, 'max' => 50 ),
			'area_useful'       => array( 'type' => 'float', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Área útil (m²)', 'ppi-core' ), 'min' => 0, 'max' => 10000000 ),
			'area_gross'        => array( 'type' => 'float', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Área bruta de construção (m²)', 'ppi-core' ), 'min' => 0, 'max' => 10000000 ),
			'area_land'         => array( 'type' => 'float', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Área de terreno (m²)', 'ppi-core' ), 'min' => 0, 'max' => 100000000 ),
			'year'              => array( 'type' => 'int', 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Ano de construção', 'ppi-core' ), 'min' => 1500, 'max' => 2100 ),
			'condition'         => array( 'type' => 'enum', 'options' => self::conditions(), 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Estado de conservação', 'ppi-core' ) ),
			'energy_class'      => array( 'type' => 'enum', 'options' => array_combine( self::energy_classes(), self::energy_classes() ), 'tab' => 'details', 'fact' => true, 'public' => true, 'label' => __( 'Certificado energético', 'ppi-core' ) ),
			'features'          => array( 'type' => 'json', 'tab' => 'features', 'fact' => true, 'public' => true, 'label' => __( 'Características', 'ppi-core' ) ),
			// Localização
			'postal_code'       => array( 'type' => 'string', 'tab' => 'location', 'fact' => true, 'public' => true, 'label' => __( 'Código postal', 'ppi-core' ) ),
			'location_precision'=> array( 'type' => 'enum', 'options' => array( 'parish' => __( 'Freguesia (aproximada)', 'ppi-core' ), 'approximate' => __( 'Ponto aproximado autorizado', 'ppi-core' ), 'exact' => __( 'Localização exata autorizada', 'ppi-core' ) ), 'tab' => 'location', 'fact' => true, 'public' => true, 'label' => __( 'Precisão pública da localização', 'ppi-core' ), 'default' => 'parish' ),
			'lat_public'        => array( 'type' => 'float', 'tab' => 'location', 'fact' => true, 'public' => true, 'label' => __( 'Latitude pública', 'ppi-core' ), 'min' => -90, 'max' => 90, 'help' => __( 'Só preencher com autorização. Use um ponto deslocado se a localização for aproximada.', 'ppi-core' ) ),
			'lng_public'        => array( 'type' => 'float', 'tab' => 'location', 'fact' => true, 'public' => true, 'label' => __( 'Longitude pública', 'ppi-core' ), 'min' => -180, 'max' => 180 ),
			'lat_private'       => array( 'type' => 'float', 'tab' => 'location', 'fact' => true, 'public' => false, 'label' => __( 'Latitude privada (interna)', 'ppi-core' ), 'min' => -90, 'max' => 90, 'help' => __( 'Nunca é enviada para o site público, REST ou JavaScript.', 'ppi-core' ) ),
			'lng_private'       => array( 'type' => 'float', 'tab' => 'location', 'fact' => true, 'public' => false, 'label' => __( 'Longitude privada (interna)', 'ppi-core' ), 'min' => -180, 'max' => 180 ),
			'address_private'   => array( 'type' => 'string', 'tab' => 'location', 'fact' => true, 'public' => false, 'label' => __( 'Morada interna', 'ppi-core' ) ),
			// Media
			'gallery'           => array( 'type' => 'ids', 'tab' => 'media', 'fact' => true, 'public' => true, 'label' => __( 'Galeria (ordenada)', 'ppi-core' ) ),
			'floorplans'        => array( 'type' => 'ids', 'tab' => 'media', 'fact' => true, 'public' => true, 'label' => __( 'Plantas', 'ppi-core' ) ),
			'video_url'         => array( 'type' => 'url', 'tab' => 'media', 'fact' => true, 'public' => true, 'label' => __( 'Vídeo (YouTube/Vimeo)', 'ppi-core' ) ),
			'tour_url'          => array( 'type' => 'url', 'tab' => 'media', 'fact' => true, 'public' => true, 'label' => __( 'Visita virtual (URL)', 'ppi-core' ) ),
			// Equipa e destaques
			'featured_order'    => array( 'type' => 'int', 'tab' => 'summary', 'fact' => true, 'public' => false, 'label' => __( 'Destaque na homepage (ordem)', 'ppi-core' ), 'min' => 0, 'max' => 99, 'help' => __( '1 = protagonista da «Seleção PPI». Vazio = não destacado. Recomendado: 4 a 6 imóveis.', 'ppi-core' ) ),
			// Origem e sincronização (nunca públicos)
			'external_id'       => array( 'type' => 'string', 'tab' => 'source', 'fact' => true, 'public' => false, 'label' => __( 'ID externo (origem)', 'ppi-core' ) ),
			'source'            => array( 'type' => 'string', 'tab' => 'source', 'fact' => true, 'public' => false, 'label' => __( 'Origem', 'ppi-core' ) ),
			'source_url'        => array( 'type' => 'url', 'tab' => 'source', 'fact' => true, 'public' => false, 'label' => __( 'URL original', 'ppi-core' ) ),
			'synced_at'         => array( 'type' => 'string', 'tab' => 'source', 'fact' => true, 'public' => false, 'label' => __( 'Última sincronização', 'ppi-core' ) ),
		);
		return $fields;
	}

	public static function key( string $name ): string {
		return self::PREFIX . $name;
	}

	public static function fact_keys(): array {
		$keys = array();
		foreach ( self::all() as $name => $def ) {
			if ( ! empty( $def['fact'] ) ) {
				$keys[] = self::key( $name );
			}
		}
		// Estado de revisão e etiqueta de idioma do título são por tradução; não sincronizar.
		return $keys;
	}

	/** Lê um campo com tipo; devolve null quando ausente. */
	public static function get( int $post_id, string $name ) {
		$def = self::all()[ $name ] ?? null;
		if ( ! $def ) {
			return null;
		}
		$raw = get_post_meta( $post_id, self::key( $name ), true );
		if ( '' === $raw || null === $raw || false === $raw && 'bool' !== $def['type'] ) {
			return 'bool' === $def['type'] ? false : ( $def['default'] ?? null );
		}
		return self::cast( $def, $raw );
	}

	public static function cast( array $def, $raw ) {
		switch ( $def['type'] ) {
			case 'int':
				return is_numeric( $raw ) ? (int) $raw : null;
			case 'float':
				return is_numeric( $raw ) ? (float) $raw : null;
			case 'bool':
				return (bool) $raw;
			case 'ids':
				$ids = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
				return array_values( array_filter( array_map( 'absint', $ids ) ) );
			case 'json':
				if ( is_array( $raw ) ) {
					return $raw;
				}
				$d = json_decode( (string) $raw, true );
				return is_array( $d ) ? $d : array();
			default:
				return (string) $raw;
		}
	}

	/**
	 * Valida e normaliza um valor recebido (editor, importador). Devolve array( valor|null, erro|null ).
	 * Vazio => null (remove a meta).
	 */
	public static function sanitize( string $name, $value ): array {
		$def = self::all()[ $name ] ?? null;
		if ( ! $def ) {
			return array( null, 'unknown' );
		}
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		if ( null === $value || '' === $value || ( is_array( $value ) && ! $value && 'json' !== $def['type'] ) ) {
			return array( null, null );
		}
		switch ( $def['type'] ) {
			case 'int':
			case 'float':
				$s = is_string( $value ) ? str_replace( array( ' ', "\u{00A0}" ), '', $value ) : $value;
				if ( is_string( $s ) && preg_match( '/^\d{1,3}(\.\d{3})+$/', $s ) ) {
					$s = str_replace( '.', '', $s ); // 1.125.000
				}
				if ( is_string( $s ) ) {
					$s = str_replace( ',', '.', $s );
				}
				if ( ! is_numeric( $s ) ) {
					/* translators: %s: field label */
					return array( null, sprintf( __( '«%s» tem de ser numérico.', 'ppi-core' ), $def['label'] ) );
				}
				$n = 'int' === $def['type'] ? (int) round( (float) $s ) : round( (float) $s, 2 );
				if ( ( isset( $def['min'] ) && $n < $def['min'] ) || ( isset( $def['max'] ) && $n > $def['max'] ) ) {
					/* translators: %s: field label */
					return array( null, sprintf( __( '«%s» está fora do intervalo permitido.', 'ppi-core' ), $def['label'] ) );
				}
				return array( $n, null );
			case 'bool':
				return array( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 1 : null, null );
			case 'enum':
				return isset( $def['options'][ $value ] ) ? array( (string) $value, null ) : array( null, sprintf( /* translators: %s: label */ __( 'Valor inválido em «%s».', 'ppi-core' ), $def['label'] ) );
			case 'url':
				$u = esc_url_raw( (string) $value, array( 'http', 'https' ) );
				return $u ? array( $u, null ) : array( null, sprintf( /* translators: %s: label */ __( 'URL inválido em «%s».', 'ppi-core' ), $def['label'] ) );
			case 'ids':
				$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
				$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
				return array( $ids ? implode( ',', $ids ) : null, null );
			case 'json':
				$arr = is_array( $value ) ? $value : json_decode( (string) $value, true );
				if ( ! is_array( $arr ) ) {
					return array( null, null );
				}
				$clean = array();
				foreach ( $arr as $row ) {
					if ( ! is_array( $row ) || empty( $row['label'] ) ) {
						continue;
					}
					$clean[] = array(
						'group' => sanitize_text_field( $row['group'] ?? '' ),
						'label' => sanitize_text_field( $row['label'] ),
						'value' => sanitize_text_field( $row['value'] ?? '' ),
					);
				}
				return array( $clean ? wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) : null, null );
			default:
				$s = sanitize_text_field( (string) $value );
				if ( 'reference' === $name ) {
					$s = preg_replace( '/[^\p{L}\p{N} _\-\/.]/u', '', $s );
					$s = mb_substr( $s, 0, 32 );
				}
				return array( '' === $s ? null : $s, null );
		}
	}

	/** Grava (ou remove quando null) um campo já validado. */
	public static function set( int $post_id, string $name, $value ): void {
		[ $v, $err ] = self::sanitize( $name, $value );
		if ( $err ) {
			return;
		}
		if ( null === $v ) {
			delete_post_meta( $post_id, self::key( $name ) );
		} else {
			update_post_meta( $post_id, self::key( $name ), $v );
		}
	}

	public static function normalize_reference( string $ref ): string {
		return strtoupper( preg_replace( '/[\s_\-\/.]+/', '', $ref ) );
	}
}
