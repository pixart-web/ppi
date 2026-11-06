<?php
/**
 * Redirecionamentos 301 dos URLs do site anterior (HCPro).
 *
 * - /imovel-<negócio>-<tipo>-...-<ID externo> → ficha correspondente (por ID externo, versão PT).
 *   Se o imóvel não existir no novo site, devolve 410 (não redireciona tudo para a homepage).
 * - Páginas institucionais → página equivalente.
 * - /imoveis?imovel=N → catálogo filtrado pelo tipo equivalente.
 * Só atua quando o WordPress não encontra o URL (404), sem interferir com páginas reais.
 * O mapa completo é exportado por `wp ppi redirects` (migration/redirects.csv) para regras no servidor.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Redirects {

	public static function static_map(): array {
		return array(
			'empresa'         => 'sobre',
			'equipa'          => 'sobre',
			'vender-imovel'   => 'vender',
			'comprar-imovel'  => 'comprar',
			'permutar-imovel' => 'permutar',
			'arrendar-imovel' => 'arrendar',
			'recrutamento'    => 'recrutamento',
			'contactos'       => 'contactos',
		);
	}

	/** IDs de tipo do sistema anterior → tipo do novo catálogo. */
	public static function legacy_types(): array {
		return array( 2 => 'apartamento', 4 => 'moradia', 7 => 'quinta', 10 => 'escritorio', 11 => 'loja', 12 => 'terreno' );
	}

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 5 );
	}

	public static function find_by_external( string $ext ): int {
		$ids = get_posts( array(
			'post_type' => 'ppi_property', 'post_status' => array( 'publish' ), 'fields' => 'ids', 'posts_per_page' => 5, 'no_found_rows' => true,
			'meta_key' => 'ppi_external_id', 'meta_value' => $ext, 'lang' => '', 'suppress_filters' => false,
		) );
		foreach ( $ids as $id ) {
			if ( I18n::DEFAULT_LANG === I18n::post_lang( $id ) ) {
				return (int) $id;
			}
		}
		return (int) ( $ids[0] ?? 0 );
	}

	public static function target( string $path, array $query = array() ): array {
		$path = trim( strtolower( $path ), '/' );
		if ( preg_match( '~^imovel-[a-z0-9-]*?-(\d{6,})$~', $path, $m ) ) {
			$id = self::find_by_external( $m[1] );
			return $id ? array( 301, get_permalink( $id ) ) : array( 410, '' );
		}
		if ( 'imoveis' === $path && ! empty( $query['imovel'] ) ) {
			$type = self::legacy_types()[ (int) $query['imovel'] ] ?? '';
			return array( 301, $type ? add_query_arg( 'tipo', $type, get_post_type_archive_link( 'ppi_property' ) ) : get_post_type_archive_link( 'ppi_property' ) );
		}
		$map = self::static_map();
		if ( isset( $map[ $path ] ) && $map[ $path ] !== $path ) {
			$page = get_page_by_path( $map[ $path ] );
			return array( 301, $page ? get_permalink( $page ) : home_url( '/' . $map[ $path ] . '/' ) );
		}
		return array( 0, '' );
	}

	public static function maybe_redirect(): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$rel  = ltrim( substr( $path, strlen( rtrim( $home, '/' ) ) ), '/' );
		$is_legacy_listing = 'imoveis' === trim( $rel, '/' ) && isset( $_GET['imovel'] ); // phpcs:ignore
		if ( ! is_404() && ! $is_legacy_listing ) {
			return;
		}
		[ $code, $to ] = self::target( $rel, array_map( 'sanitize_text_field', wp_unslash( $_GET ) ) ); // phpcs:ignore
		if ( 301 === $code && $to ) {
			wp_safe_redirect( $to, 301, 'PPI' );
			exit;
		}
		if ( 410 === $code ) {
			status_header( 410 );
		}
	}

	/** Linhas do mapa de redirecionamentos (origem → destino) a partir dos dados importados. */
	public static function rows(): array {
		$rows = array();
		$ids  = get_posts( array( 'post_type' => 'ppi_property', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'lang' => I18n::DEFAULT_LANG, 'suppress_filters' => false ) );
		foreach ( $ids as $id ) {
			$src = (string) get_post_meta( $id, 'ppi_source_url', true );
			if ( ! $src ) {
				continue;
			}
			$status = get_post_status( $id );
			$rows[] = array(
				'from'   => '/' . ltrim( (string) wp_parse_url( $src, PHP_URL_PATH ), '/' ),
				'to'     => 'publish' === $status ? wp_make_link_relative( get_permalink( $id ) ) : '',
				'code'   => 'publish' === $status ? 301 : 410,
				'note'   => 'publish' === $status ? 'ficha' : 'não publicado no novo site (' . $status . ')',
			);
		}
		foreach ( self::static_map() as $from => $to ) {
			$page = get_page_by_path( $to );
			$rows[] = array( 'from' => '/' . $from, 'to' => $page ? wp_make_link_relative( get_permalink( $page ) ) : '/' . $to . '/', 'code' => 301, 'note' => 'página' );
		}
		foreach ( self::legacy_types() as $n => $slug ) {
			$rows[] = array( 'from' => '/imoveis?imovel=' . $n . '&natureza=8', 'to' => wp_make_link_relative( add_query_arg( 'tipo', $slug, get_post_type_archive_link( 'ppi_property' ) ) ), 'code' => 301, 'note' => 'listagem por tipo' );
		}
		return $rows;
	}
}

Redirects::init();
