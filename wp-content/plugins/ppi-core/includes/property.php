<?php
/**
 * Leitura e formatação de um imóvel. Nunca inventa valores: ausente => não apresentado.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Property {

	public int $id;
	public \WP_Post $post;
	private array $cache = array();

	public function __construct( $post ) {
		$this->post = get_post( $post );
		$this->id   = (int) $this->post->ID;
	}

	public static function from( $post ): ?self {
		$p = get_post( $post );
		return ( $p && 'ppi_property' === $p->post_type ) ? new self( $p ) : null;
	}

	public function get( string $field ) {
		if ( ! array_key_exists( $field, $this->cache ) ) {
			$this->cache[ $field ] = Fields::get( $this->id, $field );
		}
		return $this->cache[ $field ];
	}

	public function title(): string {
		return get_the_title( $this->post );
	}

	public function url(): string {
		return get_permalink( $this->post );
	}

	public function reference(): string {
		return (string) $this->get( 'reference' );
	}

	public function status(): string {
		return $this->get( 'commercial_status' ) ?: 'available';
	}

	public function is_listable(): bool {
		return 'publish' === $this->post->post_status && in_array( $this->status(), array( 'available', 'reserved' ), true );
	}

	public function status_label(): string {
		return Fields::commercial_statuses()[ $this->status() ] ?? '';
	}

	/** Tipo (termo ppi_type) com rótulo no idioma do imóvel. */
	public function type(): ?\WP_Term {
		$t = get_the_terms( $this->id, 'ppi_type' );
		return ( $t && ! is_wp_error( $t ) ) ? $t[0] : null;
	}

	public function type_slug(): string {
		$t = $this->type();
		return $t ? $t->slug : '';
	}

	public function type_label(): string {
		$t = $this->type();
		return $t ? self::term_label( $t ) : '';
	}

	public static function term_label( \WP_Term $t ): string {
		if ( 'en' === I18n::current() ) {
			$en = get_term_meta( $t->term_id, 'ppi_label_en', true );
			if ( $en ) {
				return $en;
			}
		}
		return $t->name;
	}

	public function is_land(): bool {
		return 'terreno' === $this->type_slug();
	}

	/** Localização: [concelho, freguesia] (termos). */
	public function locations(): array {
		$terms = get_the_terms( $this->id, 'ppi_location' );
		$municipality = $parish = null;
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( $t->parent ) {
					$parish = $t;
				} else {
					$municipality = $t;
				}
			}
			if ( $parish && ! $municipality ) {
				$municipality = get_term( $parish->parent, 'ppi_location' );
			}
		}
		return array( $municipality, $parish );
	}

	public function location_label( bool $short = false ): string {
		[ $m, $p ] = $this->locations();
		$parts = array();
		if ( $p ) {
			$parts[] = $p->name;
		}
		if ( $m && ( ! $short || ! $p ) ) {
			$parts[] = $m->name;
		}
		return implode( ' · ', $parts );
	}

	public function price(): ?int {
		$p = $this->get( 'price' );
		return null === $p ? null : (int) $p;
	}

	public static function money( int $amount ): string {
		if ( 'en' === I18n::current() ) {
			return '€' . number_format( $amount, 0, '.', ',' );
		}
		return number_format( $amount, 0, ',', '.' ) . "\u{00A0}€";
	}

	public function price_label(): string {
		if ( $this->get( 'price_on_request' ) ) {
			return __( 'Preço sob consulta', 'ppi-core' );
		}
		$p = $this->price();
		if ( null === $p ) {
			return __( 'Preço não publicado', 'ppi-core' );
		}
		return self::money( $p );
	}

	public static function area_fmt( $v ): string {
		$v = (float) $v;
		if ( $v >= 10000 ) {
			$ha = $v / 10000;
			/* translators: 1: square meters, 2: hectares */
			$en = 'en' === I18n::current();
			return sprintf( __( '%1$s m² (%2$s ha)', 'ppi-core' ), number_format( $v, 0, $en ? '.' : ',', $en ? ',' : '.' ), number_format( $ha, 2, $en ? '.' : ',', $en ? ',' : '.' ) );
		}
		$dec = floor( $v ) == $v ? 0 : 2;
		$num = 'en' === I18n::current() ? number_format( $v, $dec, '.', ',' ) : number_format( $v, $dec, ',', '.' );
		return $num . "\u{00A0}m²";
	}

	/** Factos chave para cartão/resumo. Cada item: [key, label, value, short]. */
	public function key_facts( bool $card = false ): array {
		$f = array();
		$land = $this->is_land();
		$beds = $this->get( 'bedrooms' );
		if ( ! $land && $beds ) {
			/* translators: %d bedrooms */
			$f[] = array( 'bedrooms', __( 'Quartos', 'ppi-core' ), (string) $beds, sprintf( _n( '%d quarto', '%d quartos', $beds, 'ppi-core' ), $beds ) );
		}
		$baths = $this->get( 'bathrooms' );
		if ( ! $land && $baths ) {
			/* translators: %d bathrooms */
			$f[] = array( 'bathrooms', __( 'Casas de banho', 'ppi-core' ), (string) $baths, sprintf( _n( '%d casa de banho', '%d casas de banho', $baths, 'ppi-core' ), $baths ) );
		}
		$useful = $this->get( 'area_useful' );
		$gross  = $this->get( 'area_gross' );
		if ( null !== $useful ) {
			$f[] = array( 'area_useful', __( 'Área útil', 'ppi-core' ), self::area_fmt( $useful ), sprintf( /* translators: %s area */ __( '%s úteis', 'ppi-core' ), self::area_fmt( $useful ) ) );
		}
		if ( null !== $gross && ( ! $card || null === $useful ) ) {
			$f[] = array( 'area_gross', __( 'Área bruta', 'ppi-core' ), self::area_fmt( $gross ), sprintf( /* translators: %s area */ __( '%s de construção', 'ppi-core' ), self::area_fmt( $gross ) ) );
		}
		$landa = $this->get( 'area_land' );
		if ( null !== $landa ) {
			$f[] = array( 'area_land', __( 'Terreno', 'ppi-core' ), self::area_fmt( $landa ), sprintf( /* translators: %s area */ __( '%s de terreno', 'ppi-core' ), self::area_fmt( $landa ) ) );
		}
		return $f;
	}

	/** Restantes dados factuais da ficha (quartos, casas de banho e áreas estão em key_facts). */
	public function summary(): array {
		$rows = array();
		$add = static function ( $label, $value ) use ( &$rows ) {
			if ( null !== $value && '' !== $value ) {
				$rows[] = array( $label, $value );
			}
		};
		$add( __( 'Tipo', 'ppi-core' ), trim( $this->type_label() . ( $this->get( 'subtype' ) ? ' — ' . $this->subtype_label() : '' ) ) );
		if ( ! $this->is_land() ) {
			$add( __( 'Tipologia', 'ppi-core' ), $this->get( 'typology' ) );
		}
		$add( __( 'Ano de construção', 'ppi-core' ), $this->get( 'year' ) );
		$c = $this->get( 'condition' );
		$add( __( 'Estado de conservação', 'ppi-core' ), $c ? ( Fields::conditions()[ $c ] ?? null ) : null );
		$e = $this->get( 'energy_class' );
		$add( __( 'Certificado energético', 'ppi-core' ), $e ? $this->energy_label( $e ) : null );
		$add( __( 'Referência', 'ppi-core' ), $this->reference() );
		return $rows;
	}

	public function energy_label( string $e ): string {
		if ( 'Isento' === $e ) {
			return __( 'Isento', 'ppi-core' );
		}
		if ( 'Em execução' === $e ) {
			return __( 'Em execução', 'ppi-core' );
		}
		return $e;
	}

	public function subtype_label(): string {
		$s = (string) $this->get( 'subtype' );
		if ( 'en' !== I18n::current() ) {
			return $s;
		}
		$map = array(
			'Moradia Isolada' => 'Detached house', 'Moradia Geminada' => 'Semi-detached house', 'Moradia em Banda' => 'Terraced house',
			'Terreno Urbano' => 'Urban plot', 'Terreno Rústico' => 'Rural land', 'Terreno Misto' => 'Mixed-use land', 'Lote' => 'Building plot',
			'Quinta' => 'Farm estate', 'Herdade' => 'Large estate', 'Apartamento' => 'Apartment', 'Duplex' => 'Duplex', 'Moradia' => 'House',
			'Terreno para Construção' => 'Building plot', 'Terreno' => 'Land', 'Moradia Rústica' => 'Country house', 'Monte' => 'Country house (monte)',
			'Ruína' => 'Ruin', 'Prédio' => 'Building', 'Armazém' => 'Warehouse',
		);
		return $map[ $s ] ?? $s;
	}

	/**
	 * Características agrupadas, sem repetir o que já está no resumo e sem contagens a zero.
	 * @return array<string, array<int, array{label:string,lang:string}>>
	 */
	public function feature_groups(): array {
		$lang  = I18n::current();
		$skip  = array( 'Nº de Casas de Banho', 'Nº de Inquilinos' );
		$out   = array();
		$seen  = array();
		foreach ( (array) $this->get( 'features' ) as $row ) {
			$label = $row['label'] ?? '';
			$value = $row['value'] ?? '';
			if ( ! $label || in_array( $label, $skip, true ) || '0' === $value || 'Não' === $value ) {
				continue;
			}
			[ $g ] = I18n::feature_label( $row['group'] ?: 'Gerais', $lang );
			[ $l, $llang ] = I18n::feature_label( $label, $lang );
			if ( is_numeric( $value ) ) {
				$l .= ': ' . $value;
			} elseif ( $value && 'Sim' !== $value ) {
				$l .= ': ' . $value;
			}
			$key = $g . '|' . $l;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[ $g ][]  = array( 'label' => $l, 'lang' => $llang );
		}
		return $out;
	}

	/** IDs da galeria (ordem do editor); a imagem de destaque entra primeiro se não estiver na galeria. */
	public function gallery(): array {
		$ids = (array) $this->get( 'gallery' );
		$t   = (int) get_post_thumbnail_id( $this->id );
		if ( $t && ! in_array( $t, $ids, true ) ) {
			array_unshift( $ids, $t );
		}
		return array_values( array_filter( $ids, static fn( $id ) => 'attachment' === get_post_type( $id ) ) );
	}

	public function floorplans(): array {
		return array_values( array_filter( (array) $this->get( 'floorplans' ), static fn( $id ) => 'attachment' === get_post_type( $id ) ) );
	}

	/** Ponto focal "x% y%" de uma imagem (definido no editor de media). */
	public static function focal( int $attachment_id ): string {
		$f = get_post_meta( $attachment_id, '_ppi_focal', true );
		if ( is_string( $f ) && preg_match( '/^(\d{1,3}) (\d{1,3})$/', $f, $m ) ) {
			return min( 100, (int) $m[1] ) . '% ' . min( 100, (int) $m[2] ) . '%';
		}
		return '50% 50%';
	}

	/** Texto alternativo factual quando a imagem não tem alt próprio. */
	public function image_alt( int $attachment_id, int $index ): string {
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( $alt ) {
			return $alt;
		}
		/* translators: 1: property title, 2: photo number, 3: reference */
		$kind = (string) get_post_meta( $attachment_id, '_ppi_media_kind', true );
		if ( 'render' === $kind ) {
			/* translators: 1: property title, 2: number, 3: reference */
			return sprintf( __( '%1$s — imagem ilustrativa %2$d, não é fotografia (ref. %3$s)', 'ppi-core' ), $this->title(), $index + 1, $this->reference() );
		}
		if ( 'plan' === $kind ) {
			/* translators: 1: property title, 2: number, 3: reference */
			return sprintf( __( '%1$s — planta ou imagem de satélite %2$d (ref. %3$s)', 'ppi-core' ), $this->title(), $index + 1, $this->reference() );
		}
		return sprintf( __( '%1$s — fotografia %2$d (ref. %3$s)', 'ppi-core' ), $this->title(), $index + 1, $this->reference() );
	}

	public function public_coordinates(): ?array {
		$lat = $this->get( 'lat_public' );
		$lng = $this->get( 'lng_public' );
		return ( null !== $lat && null !== $lng ) ? array( (float) $lat, (float) $lng ) : null;
	}

	public function excerpt( int $len = 155 ): string {
		$t = has_excerpt( $this->post ) ? $this->post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $this->post->post_content ) );
		$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
		if ( mb_strlen( $t ) <= $len ) {
			return $t;
		}
		$cut = mb_substr( $t, 0, $len );
		$cut = preg_replace( '/\s+\S*$/u', '', $cut );
		return $cut . '…';
	}

	/** Consultor validado (nome na lista de equipa) ou null => usar contacto da agência. */
	public function agent(): ?array {
		$name = trim( (string) get_post_meta( $this->id, '_ppi_agent_name', true ) );
		if ( ! $name ) {
			return null;
		}
		foreach ( Settings::team() as $m ) {
			if ( 0 === strcasecmp( $m['name'], $name ) ) {
				return $m;
			}
		}
		return null;
	}

	/** Dados mínimos para favoritos/comparação (REST pública). */
	public function card_data(): array {
		[ $m, $p ] = $this->locations();
		$img = $this->gallery()[0] ?? 0;
		return array(
			'ref'       => $this->reference(),
			'id'        => $this->id,
			'title'     => $this->title(),
			'url'       => $this->url(),
			'available' => $this->is_listable(),
			'status'    => $this->status_label(),
			'price'     => $this->price_label(),
			'location'  => $this->location_label(),
			'type'      => $this->type_label(),
			'image'     => $img ? wp_get_attachment_image_url( $img, 'medium_large' ) : null,
			'facts'     => array_map( static fn( $f ) => array( 'key' => $f[0], 'label' => $f[1], 'value' => $f[2] ), $this->key_facts() ),
		);
	}
}
