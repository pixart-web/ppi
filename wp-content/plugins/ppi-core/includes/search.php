<?php
/**
 * Pesquisa de imóveis no servidor.
 *
 * Um índice compacto por idioma (uma linha por imóvel publicado e listável) é gerado com
 * uma única consulta e guardado em cache; é invalidado sempre que um imóvel, termo ou preço muda.
 * Filtros, contagens dependentes, ordenação e paginação correm sobre o índice; os cartões
 * da página atual são carregados de uma vez (sem consultas por cartão).
 * Testado com 3000 imóveis sintéticos (ver tests/volume).
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Search {

	public const PER_PAGE   = 12;
	public const CACHE_VER  = 4;
	public const PRICE_STEPS = array( 50000, 100000, 150000, 200000, 250000, 300000, 400000, 500000, 600000, 750000, 1000000, 1500000, 2000000, 3000000 );
	public const AREA_STEPS  = array( 50, 80, 100, 150, 200, 300, 500 );
	public const LAND_STEPS  = array( 500, 1000, 2500, 5000, 10000, 20000, 50000, 100000 );

	private static array $memo = array();

	public static function init(): void {
		foreach ( array( 'save_post_ppi_property', 'deleted_post', 'trashed_post', 'untrashed_post', 'edited_ppi_location', 'edited_ppi_type', 'edited_ppi_feature', 'delete_ppi_location' ) as $h ) {
			add_action( $h, array( __CLASS__, 'flush' ) );
		}
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_flush_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_flush_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'maybe_flush_meta' ), 10, 3 );
		add_action( 'transition_post_status', static function ( $new, $old, $post ) {
			if ( 'ppi_property' === $post->post_type && $new !== $old ) {
				self::flush();
			}
		}, 10, 3 );
	}

	public static function maybe_flush_meta( $meta_id, $object_id, $key ): void {
		if ( is_string( $key ) && str_starts_with( $key, Fields::PREFIX ) ) {
			self::flush();
		}
	}

	public static function flush(): void {
		self::$memo = array();
		foreach ( I18n::languages() as $l ) {
			delete_transient( 'ppi_index_' . self::CACHE_VER . '_' . $l );
		}
		delete_transient( 'ppi_index_' . self::CACHE_VER . '_pt' );
		wp_cache_set( 'ppi_index_gen', microtime( true ), 'ppi' );
		do_action( 'ppi_index_flushed' );
	}

	/** Índice do idioma atual: id => linha. */
	public static function index( ?string $lang = null ): array {
		$lang = $lang ?: I18n::current();
		if ( isset( self::$memo[ $lang ] ) ) {
			return self::$memo[ $lang ];
		}
		$key = 'ppi_index_' . self::CACHE_VER . '_' . $lang;
		$idx = get_transient( $key );
		if ( ! is_array( $idx ) ) {
			$idx = self::build( $lang );
			set_transient( $key, $idx, DAY_IN_SECONDS );
		}
		return self::$memo[ $lang ] = $idx;
	}

	private static function build( string $lang ): array {
		$args = array(
			'post_type'              => 'ppi_property',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'suppress_filters'       => false,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
		);
		if ( I18n::active() ) {
			$args['lang'] = $lang;
		}
		$ids = get_posts( $args );
		if ( ! $ids ) {
			return array();
		}
		update_meta_cache( 'post', $ids );
		update_object_term_cache( $ids, 'ppi_property' );
		$rows = array();
		foreach ( $ids as $id ) {
			$status = get_post_meta( $id, 'ppi_commercial_status', true ) ?: 'available';
			if ( ! in_array( $status, array( 'available', 'reserved' ), true ) ) {
				continue;
			}
			$mun = $par = '';
			foreach ( (array) get_the_terms( $id, 'ppi_location' ) as $t ) {
				if ( $t instanceof \WP_Term ) {
					if ( $t->parent ) {
						$par = $t->slug;
						if ( ! $mun ) {
							$p = get_term( $t->parent, 'ppi_location' );
							$mun = $p && ! is_wp_error( $p ) ? $p->slug : '';
						}
					} else {
						$mun = $t->slug;
					}
				}
			}
			$type  = get_the_terms( $id, 'ppi_type' );
			$feats = get_the_terms( $id, 'ppi_feature' );
			$num   = static fn( $k ) => ( '' === ( $v = get_post_meta( $id, $k, true ) ) || null === $v ) ? null : (float) $v;
			$useful = $num( 'ppi_area_useful' );
			$gross  = $num( 'ppi_area_gross' );
			$ref    = (string) get_post_meta( $id, 'ppi_reference', true );
			$rows[ $id ] = array(
				'id'    => $id,
				'ref'   => $ref,
				'refn'  => Fields::normalize_reference( $ref ),
				'date'  => (int) get_post_time( 'U', true, $id ),
				'ext'   => (int) get_post_meta( $id, 'ppi_external_id', true ),
				'price' => null === ( $p = $num( 'ppi_price' ) ) ? null : (int) $p,
				'por'   => (bool) get_post_meta( $id, 'ppi_price_on_request', true ),
				'beds'  => null === ( $b = $num( 'ppi_bedrooms' ) ) ? null : (int) $b,
				'built' => ( null === $useful && null === $gross ) ? null : max( (float) $useful, (float) $gross ),
				'land'  => $num( 'ppi_area_land' ),
				'type'  => ( $type && ! is_wp_error( $type ) ) ? $type[0]->slug : '',
				'mun'   => $mun,
				'par'   => $par,
				'feats' => ( $feats && ! is_wp_error( $feats ) ) ? wp_list_pluck( $feats, 'slug' ) : array(),
				'feat'  => (int) get_post_meta( $id, 'ppi_featured_order', true ),
				'st'    => $status,
			);
		}
		return $rows;
	}

	/** Lê e valida os parâmetros da query string. Valores inválidos são descartados (nunca causam erro 500). */
	public static function params( ?array $src = null ): array {
		$src = $src ?? $_GET; // phpcs:ignore WordPress.Security.NonceVerification -- pesquisa pública, só leitura.
		$g   = static fn( $k ) => isset( $src[ $k ] ) && ! is_array( $src[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $src[ $k ] ) ) : '';
		$p   = array(
			'local'       => sanitize_title( $g( 'local' ) ),
			'tipo'        => sanitize_title( $g( 'tipo' ) ),
			'quartos'     => min( 6, max( 0, absint( $g( 'quartos' ) ) ) ),
			'preco_min'   => self::clamp_step( $g( 'preco_min' ), self::PRICE_STEPS ),
			'preco_max'   => self::clamp_step( $g( 'preco_max' ), self::PRICE_STEPS ),
			'area_min'    => self::clamp_step( $g( 'area_min' ), self::AREA_STEPS ),
			'terreno_min' => self::clamp_step( $g( 'terreno_min' ), self::LAND_STEPS ),
			'carac'       => array(),
			'ref'         => mb_substr( preg_replace( '/[^\p{L}\p{N} _\-\/.]/u', '', $g( 'ref' ) ), 0, 20 ),
			'ordem'       => in_array( $g( 'ordem' ), array( 'recentes', 'preco-asc', 'preco-desc' ), true ) ? $g( 'ordem' ) : 'recentes',
			'pagina'      => max( 1, min( 500, absint( $g( 'pagina' ) ) ) ),
		);
		$carac = $src['carac'] ?? '';
		$carac = is_array( $carac ) ? $carac : explode( ',', (string) $carac );
		$valid = array_keys( Migrations::feature_filters() );
		foreach ( $carac as $c ) {
			$c = sanitize_title( wp_unslash( (string) $c ) );
			if ( in_array( $c, $valid, true ) && ! in_array( $c, $p['carac'], true ) ) {
				$p['carac'][] = $c;
			}
		}
		sort( $p['carac'] );
		if ( $p['preco_min'] && $p['preco_max'] && $p['preco_min'] > $p['preco_max'] ) {
			[ $p['preco_min'], $p['preco_max'] ] = array( $p['preco_max'], $p['preco_min'] );
		}
		if ( $p['tipo'] && ! isset( Migrations::type_terms()[ $p['tipo'] ] ) ) {
			$p['tipo'] = '';
		}
		if ( $p['local'] && ! term_exists( $p['local'], 'ppi_location' ) ) {
			$p['local'] = '';
		}
		return $p;
	}

	private static function clamp_step( string $v, array $steps ): int {
		$n = absint( preg_replace( '/\D/', '', $v ) );
		return in_array( $n, $steps, true ) ? $n : 0;
	}

	public static function has_filters( array $p ): bool {
		return (bool) ( $p['local'] || $p['tipo'] || $p['quartos'] || $p['preco_min'] || $p['preco_max'] || $p['area_min'] || $p['terreno_min'] || $p['carac'] || $p['ref'] );
	}

	/** Aplica todos os filtros exceto $except (para contagens dependentes). */
	private static function filter( array $rows, array $p, string $except = '' ): array {
		return array_filter( $rows, static function ( $r ) use ( $p, $except ) {
			if ( $p['ref'] && 'ref' !== $except && ! str_contains( $r['refn'], Fields::normalize_reference( $p['ref'] ) ) ) {
				return false;
			}
			if ( $p['local'] && 'local' !== $except && $r['mun'] !== $p['local'] && $r['par'] !== $p['local'] ) {
				return false;
			}
			if ( $p['tipo'] && 'tipo' !== $except && $r['type'] !== $p['tipo'] ) {
				return false;
			}
			if ( $p['quartos'] && 'quartos' !== $except && ( null === $r['beds'] || $r['beds'] < $p['quartos'] ) ) {
				return false;
			}
			if ( 'preco' !== $except ) {
				if ( $p['preco_min'] && ( null === $r['price'] || $r['price'] < $p['preco_min'] ) ) {
					return false;
				}
				if ( $p['preco_max'] && ( null === $r['price'] || $r['price'] > $p['preco_max'] ) ) {
					return false;
				}
			}
			if ( $p['area_min'] && 'area_min' !== $except && ( null === $r['built'] || $r['built'] < $p['area_min'] ) ) {
				return false;
			}
			if ( $p['terreno_min'] && 'terreno_min' !== $except && ( null === $r['land'] || $r['land'] < $p['terreno_min'] ) ) {
				return false;
			}
			if ( $p['carac'] && 'carac' !== $except && array_diff( $p['carac'], $r['feats'] ) ) {
				return false;
			}
			return true;
		} );
	}

	public static function run( ?array $p = null ): array {
		$p    = $p ?? self::params();
		$all  = self::index();
		$rows = self::filter( $all, $p );

		usort( $rows, static function ( $a, $b ) use ( $p ) {
			if ( 'recentes' !== $p['ordem'] ) {
				// Imóveis sem preço publicado ficam sempre no fim, em ambas as ordens.
				if ( null === $a['price'] || null === $b['price'] ) {
					return ( null === $a['price'] ) <=> ( null === $b['price'] ) ?: $b['ext'] <=> $a['ext'];
				}
				$c = 'preco-asc' === $p['ordem'] ? $a['price'] <=> $b['price'] : $b['price'] <=> $a['price'];
				return $c ?: $b['ext'] <=> $a['ext'];
			}
			return $b['date'] <=> $a['date'] ?: $b['ext'] <=> $a['ext'];
		} );

		$total = count( $rows );
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = min( $p['pagina'], $pages );
		$slice = array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		return array(
			'params' => $p,
			'total'  => $total,
			'pages'  => $pages,
			'page'   => $page,
			'ids'    => wp_list_pluck( $slice, 'id' ),
			'facets' => self::facets( $all, $p ),
			'out_of_range' => $p['pagina'] > $pages,
		);
	}

	private static function facets( array $all, array $p ): array {
		$f = array( 'local' => array(), 'tipo' => array(), 'quartos' => array(), 'carac' => array() );
		foreach ( self::filter( $all, $p, 'local' ) as $r ) {
			if ( $r['mun'] ) {
				$f['local'][ $r['mun'] ] = ( $f['local'][ $r['mun'] ] ?? 0 ) + 1;
			}
			if ( $r['par'] ) {
				$f['local'][ $r['par'] ] = ( $f['local'][ $r['par'] ] ?? 0 ) + 1;
			}
		}
		foreach ( self::filter( $all, $p, 'tipo' ) as $r ) {
			if ( $r['type'] ) {
				$f['tipo'][ $r['type'] ] = ( $f['tipo'][ $r['type'] ] ?? 0 ) + 1;
			}
		}
		foreach ( self::filter( $all, $p, 'quartos' ) as $r ) {
			for ( $i = 1; $i <= 6; $i++ ) {
				if ( null !== $r['beds'] && $r['beds'] >= $i ) {
					$f['quartos'][ $i ] = ( $f['quartos'][ $i ] ?? 0 ) + 1;
				}
			}
		}
		foreach ( self::filter( $all, $p, 'carac' ) as $r ) {
			foreach ( $r['feats'] as $s ) {
				$f['carac'][ $s ] = ( $f['carac'][ $s ] ?? 0 ) + 1;
			}
		}
		$f['has_built'] = (bool) array_filter( $all, static fn( $r ) => null !== $r['built'] );
		$f['has_land']  = (bool) array_filter( $all, static fn( $r ) => null !== $r['land'] );
		return $f;
	}

	/** URL partilhável com os parâmetros normalizados (só os que diferem do padrão). */
	public static function url( array $p, array $changes = array(), ?string $base = null ): string {
		$p    = array_merge( $p, $changes );
		$base = $base ?? I18n::archive_url();
		$q    = array();
		foreach ( array( 'local', 'tipo', 'quartos', 'preco_min', 'preco_max', 'area_min', 'terreno_min', 'ref' ) as $k ) {
			if ( ! empty( $p[ $k ] ) ) {
				$q[ $k ] = $p[ $k ];
			}
		}
		if ( ! empty( $p['carac'] ) ) {
			$q['carac'] = implode( ',', $p['carac'] );
		}
		if ( 'recentes' !== ( $p['ordem'] ?? 'recentes' ) ) {
			$q['ordem'] = $p['ordem'];
		}
		if ( ( $p['pagina'] ?? 1 ) > 1 ) {
			$q['pagina'] = (int) $p['pagina'];
		}
		return $q ? add_query_arg( array_map( 'rawurlencode', $q ), $base ) : $base;
	}

	/** Imóveis semelhantes: mesma localidade/concelho e tipo; preço próximo quando existe. Sem preenchimento aleatório. */
	public static function similar( Property $prop, int $limit = 3 ): array {
		$all  = self::index( I18n::post_lang( $prop->id ) );
		$me   = $all[ $prop->id ] ?? null;
		[ $m ] = $prop->locations();
		$type  = $prop->type_slug();
		$price = $prop->price();
		$mun   = $me['mun'] ?? ( $m ? $m->slug : '' );
		if ( ! $mun || ! $type ) {
			return array( 'ids' => array(), 'criteria' => '' );
		}
		$cands = array_filter( $all, static fn( $r ) => $r['id'] !== $prop->id && $r['mun'] === $mun && $r['type'] === $type && 'available' === $r['st'] );
		$with_price = $price ? array_filter( $cands, static fn( $r ) => $r['price'] && $r['price'] >= $price * 0.6 && $r['price'] <= $price * 1.6 ) : array();
		$use = $with_price ?: $cands;
		usort( $use, static function ( $a, $b ) use ( $price, $me ) {
			$sa = ( $me && $me['par'] && $a['par'] === $me['par'] ) ? 0 : 1;
			$sb = ( $me && $me['par'] && $b['par'] === $me['par'] ) ? 0 : 1;
			if ( $sa !== $sb ) {
				return $sa <=> $sb;
			}
			if ( $price && $a['price'] && $b['price'] ) {
				return abs( $a['price'] - $price ) <=> abs( $b['price'] - $price );
			}
			return $b['ext'] <=> $a['ext'];
		} );
		$criteria = $with_price
			? __( 'Mesmo tipo de imóvel, no mesmo concelho e com preço próximo.', 'ppi-core' )
			: __( 'Mesmo tipo de imóvel, no mesmo concelho.', 'ppi-core' );
		return array( 'ids' => array_slice( wp_list_pluck( $use, 'id' ), 0, $limit ), 'criteria' => $criteria );
	}

	/** Localizações com imóveis (para seletores e páginas de localidade). */
	public static function locations_tree(): array {
		$counts = array();
		foreach ( self::index() as $r ) {
			foreach ( array( $r['mun'], $r['par'] ) as $s ) {
				if ( $s ) {
					$counts[ $s ] = ( $counts[ $s ] ?? 0 ) + 1;
				}
			}
		}
		$tree  = array();
		$terms = get_terms( array( 'taxonomy' => 'ppi_location', 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		foreach ( $terms as $t ) {
			if ( ! $t->parent && ! empty( $counts[ $t->slug ] ) ) {
				$tree[ $t->term_id ] = array( 'term' => $t, 'count' => $counts[ $t->slug ], 'children' => array() );
			}
		}
		foreach ( $terms as $t ) {
			if ( $t->parent && isset( $tree[ $t->parent ] ) && ! empty( $counts[ $t->slug ] ) ) {
				$tree[ $t->parent ]['children'][] = array( 'term' => $t, 'count' => $counts[ $t->slug ] );
			}
		}
		uasort( $tree, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
		return $tree;
	}
}

Search::init();
