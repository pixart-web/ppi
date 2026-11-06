<?php
/**
 * Metadados, dados estruturados, indexação e política de imóveis vendidos/retirados.
 *
 * Fonte única: se um plugin de SEO conhecido estiver ativo, este módulo deixa de gerar
 * título/descrição/OG/JSON-LD (mantém só robots de filtros e a política de estados).
 * hreflang é gerado pelo Polylang (recíproco); o canonical é o do WordPress, ajustado nos filtros.
 *
 * Política:
 * - Disponível/Reservado: indexável, no sitemap.
 * - Vendido: página mantida com indicação «Vendido», noindex, fora do sitemap, listagens e semelhantes.
 * - Retirado: responde 410 com ligação ao catálogo; fora do sitemap.
 * - Listagem com filtros: noindex,follow; canonical para a listagem sem filtros (página 1).
 * - Fora de produção: noindex global + X-Robots-Tag.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Seo {

	public static function external_seo(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	}

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'status_policy' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'demo_gate' ), 0 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'send_headers', static function () {
			if ( 'production' !== wp_get_environment_type() ) {
				header( 'X-Robots-Tag: noindex, nofollow', true );
			}
		} );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'archive_canonical' ), 1 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', static fn( $p, $name ) => 'users' === $name ? false : $p, 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', static function ( $t ) {
			unset( $t['ppi_type'], $t['ppi_feature'], $t['post_format'] );
			return $t;
		} );
		if ( ! self::external_seo() ) {
			add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
			add_filter( 'document_title_separator', static fn() => '|' );
			add_action( 'wp_head', array( __CLASS__, 'meta' ), 2 );
		}
	}

	/** Barreira opcional de pré-visualização (PPI_DEMO_PASSWORD). */
	public static function demo_gate(): void {
		$pass = Mail::env( 'DEMO_PASSWORD' );
		if ( ! $pass || is_user_logged_in() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$cookie = hash_hmac( 'sha256', $pass, wp_salt( 'auth' ) );
		if ( isset( $_COOKIE['ppi_preview'] ) && hash_equals( $cookie, (string) $_COOKIE['ppi_preview'] ) ) {
			return;
		}
		if ( isset( $_POST['ppi_preview_pass'] ) && hash_equals( $pass, (string) wp_unslash( $_POST['ppi_preview_pass'] ) ) ) { // phpcs:ignore
			setcookie( 'ppi_preview', $cookie, array( 'expires' => time() + WEEK_IN_SECONDS, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_ssl() ) );
			wp_safe_redirect( home_url( add_query_arg( array() ) ) );
			exit;
		}
		status_header( 401 );
		nocache_headers();
		echo '<!doctype html><html lang="pt-PT"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>PPI — pré-visualização</title><body style="font:16px/1.5 system-ui;background:#F5F2EC;color:#242825;display:grid;place-items:center;min-height:100vh;margin:0"><form method="post" style="max-width:22rem;padding:2rem"><h1 style="font-size:1.3rem">Pré-visualização privada</h1><p>Este ambiente de revisão está protegido.</p><label for="p">Palavra-passe</label><br><input id="p" type="password" name="ppi_preview_pass" autocomplete="current-password" style="width:100%;padding:.6rem;margin:.4rem 0 1rem"><button style="padding:.6rem 1.2rem;background:#31483E;color:#fff;border:0">Entrar</button></form></body></html>';
		exit;
	}

	public static function status_policy(): void {
		if ( ! is_singular( 'ppi_property' ) ) {
			return;
		}
		$p = Property::from( get_queried_object_id() );
		if ( $p && 'withdrawn' === $p->status() ) {
			status_header( 410 );
			nocache_headers();
		}
	}

	public static function robots( array $r ): array {
		$noindex = false;
		if ( is_post_type_archive( 'ppi_property' ) || is_tax( 'ppi_location' ) ) {
			$p = Search::params();
			$noindex = Search::has_filters( $p ) || 'recentes' !== $p['ordem'];
		}
		if ( is_singular( 'ppi_property' ) ) {
			$p = Property::from( get_queried_object_id() );
			$noindex = $p && in_array( $p->status(), array( 'sold', 'withdrawn' ), true );
		}
		if ( is_page( array( 'favoritos', 'comparar', 'favourites', 'compare' ) ) || isset( $_GET['ppi_state'] ) ) { // phpcs:ignore
			$noindex = true;
		}
		if ( $noindex ) {
			unset( $r['max-image-preview'] );
			$r['noindex'] = true;
			$r['follow']  = true;
		}
		return $r;
	}

	public static function canonical( $url, $post ) {
		return $url;
	}

	/** Listagens: canonical sem filtros nem ordenação; mantém a paginação. */
	public static function archive_canonical(): void {
		if ( self::external_seo() || ! ( is_post_type_archive( 'ppi_property' ) || is_tax( 'ppi_location' ) ) ) {
			return;
		}
		$base = is_tax() ? get_term_link( get_queried_object() ) : I18n::archive_url();
		$p    = Search::params();
		$url  = ( ! Search::has_filters( $p ) && $p['pagina'] > 1 ) ? add_query_arg( 'pagina', $p['pagina'], $base ) : $base;
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}

	public static function sitemap_args( array $args, string $type ): array {
		if ( 'ppi_property' === $type ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => 'ppi_commercial_status', 'value' => array( 'available', 'reserved' ), 'compare' => 'IN' ),
				array( 'key' => 'ppi_commercial_status', 'compare' => 'NOT EXISTS' ),
			);
		}
		return $args;
	}

	public static function title( array $parts ): array {
		if ( is_singular( 'ppi_property' ) ) {
			$p = Property::from( get_queried_object_id() );
			if ( $p ) {
				$parts['title'] = $p->title() . ' — ' . trim( $p->type_label() . ', ' . $p->location_label( true ), ', ' );
			}
		} elseif ( is_post_type_archive( 'ppi_property' ) ) {
			$parts['title'] = __( 'Imóveis à venda no Algarve Ocidental', 'ppi-core' );
			$p = Search::params();
			if ( $p['pagina'] > 1 ) {
				/* translators: %d page */
				$parts['page'] = sprintf( __( 'Página %d', 'ppi-core' ), $p['pagina'] );
			}
		} elseif ( is_tax( 'ppi_location' ) ) {
			/* translators: %s place */
			$parts['title'] = sprintf( __( 'Imóveis em %s', 'ppi-core' ), single_term_title( '', false ) );
		}
		$parts['site'] = 'PPI Estate Agents';
		unset( $parts['tagline'] );
		return $parts;
	}

	private static function description(): string {
		if ( is_singular( 'ppi_property' ) ) {
			$p = Property::from( get_queried_object_id() );
			if ( $p ) {
				$facts = implode( ', ', array_map( static fn( $f ) => $f[3], $p->key_facts( true ) ) );
				return trim( $p->price_label() . '. ' . ( $facts ? $facts . '. ' : '' ) . $p->excerpt( 120 ) );
			}
		}
		if ( is_post_type_archive( 'ppi_property' ) ) {
			/* translators: %d count */
			return sprintf( __( '%d imóveis à venda em Vila do Bispo, Lagos, Aljezur e arredores: moradias, terrenos, quintas e apartamentos apresentados pela PPI, agência em Budens.', 'ppi-core' ), count( Search::index() ) );
		}
		if ( is_tax( 'ppi_location' ) ) {
			$t = get_queried_object();
			$i = (string) get_term_meta( $t->term_id, 'ppi_intro_' . I18n::current(), true );
			return $i ? wp_trim_words( wp_strip_all_tags( $i ), 28, '…' ) : '';
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			$ex = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) ), 28, '…' );
			return trim( (string) $ex );
		}
		return '';
	}

	public static function meta(): void {
		$desc  = self::description();
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : ( is_tax() ? get_term_link( get_queried_object() ) : ( is_post_type_archive() ? I18n::archive_url() : I18n::home_url() ) );
		$img   = '';
		if ( is_singular( 'ppi_property' ) && ( $p = Property::from( get_queried_object_id() ) ) && ( $g = $p->gallery() ) ) {
			$img = wp_get_attachment_image_url( $g[0], 'large' );
		} elseif ( is_tax( 'ppi_location' ) && ( $i = (int) get_term_meta( get_queried_object_id(), 'ppi_image', true ) ) ) {
			$img = wp_get_attachment_image_url( $i, 'large' );
		} elseif ( is_front_page() && ( $h = Blocks::hero_property() ) && ( $g = $h->gallery() ) ) {
			$img = wp_get_attachment_image_url( $g[0], 'large' );
		}
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( mb_substr( $desc, 0, 300 ) ) . '">' . "\n";
		}
		$og = array(
			'og:type' => is_singular( 'ppi_property' ) ? 'product' : 'website', 'og:site_name' => 'PPI Estate Agents',
			'og:title' => $title, 'og:description' => $desc, 'og:url' => is_wp_error( $url ) ? '' : $url, 'og:image' => $img,
			'og:locale' => 'en' === I18n::current() ? 'en_GB' : 'pt_PT',
		);
		foreach ( array_filter( $og ) as $k => $v ) {
			echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( (string) $v ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		foreach ( self::schema() as $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
		}
	}

	public static function organization(): array {
		$s = Settings::all();
		return array(
			'@type' => 'RealEstateAgent', '@id' => home_url( '/#agency' ), 'name' => 'PPI — Portuguese Property Investments', 'legalName' => $s['legal_name'],
			'url' => home_url( '/' ), 'email' => $s['email'], 'telephone' => array_values( array_filter( array( $s['phone_landline'], $s['phone_mobile'] ) ) ),
			'logo' => get_theme_file_uri( 'assets/img/ppi-logo.png' ),
			'address' => array( '@type' => 'PostalAddress', 'streetAddress' => 'E.N. 125 — Entrada do Parque da Floresta, 1', 'postalCode' => '8650-060', 'addressLocality' => 'Budens, Vila do Bispo', 'addressRegion' => 'Faro', 'addressCountry' => 'PT' ),
			'identifier' => array( '@type' => 'PropertyValue', 'name' => 'AMI', 'value' => $s['ami'] ),
			'sameAs' => array_values( array_filter( array( $s['facebook'], $s['instagram'] ) ) ),
		);
	}

	public static function schema(): array {
		$out = array();
		if ( is_front_page() || is_page( array( 'contactos', 'contact', 'sobre', 'about' ) ) ) {
			$out[] = array( '@context' => 'https://schema.org' ) + self::organization();
		}
		if ( is_singular( 'ppi_property' ) && ( $p = Property::from( get_queried_object_id() ) ) && 'withdrawn' !== $p->status() ) {
			$imgs = array_map( static fn( $id ) => wp_get_attachment_image_url( $id, 'large' ), array_slice( $p->gallery(), 0, 6 ) );
			$listing = array(
				'@context' => 'https://schema.org', '@type' => 'RealEstateListing', 'name' => $p->title(), 'url' => $p->url(),
				'description' => $p->excerpt( 300 ), 'image' => array_values( array_filter( $imgs ) ), 'datePosted' => get_the_date( 'c', $p->post ),
				'identifier' => $p->reference(), 'provider' => array( '@id' => home_url( '/#agency' ) ),
			);
			if ( null !== $p->price() && ! $p->get( 'price_on_request' ) ) {
				$listing['offers'] = array(
					'@type' => 'Offer', 'price' => $p->price(), 'priceCurrency' => 'EUR', 'businessFunction' => 'http://purl.org/goodrelations/v1#Sell',
					'availability' => 'sold' === $p->status() ? 'https://schema.org/SoldOut' : ( 'reserved' === $p->status() ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock' ),
				);
			}
			$out[] = $listing;
			$items = array();
			foreach ( Render::property_crumbs( $p ) as $i => [ $label, $url ] ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $label ) + ( $url ? array( 'item' => $url ) : array() );
			}
			$out[] = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items );
		}
		return $out;
	}
}

Seo::init();
