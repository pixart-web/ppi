<?php
/**
 * Importador de imóveis (formato ppi-properties/1 — ver docs/formato-importacao.md).
 *
 * Garantias:
 * - Idempotente: chave = ID externo (+ origem). Segunda execução não duplica imóveis nem media
 *   (media identificada por URL de origem e SHA-1 do ficheiro).
 * - Dry-run: calcula tudo e não escreve nada.
 * - Lotes (offset/limit) com estado gravado para retoma.
 * - Edições locais não são sobrescritas (política keep-local por defeito); a diferença fica registada.
 * - Conflitos materiais => estado «Pendente»; nunca publicado como validado.
 * - Imóveis ausentes da fonte são apenas reportados (nunca eliminados).
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Importer {

	private array $opt;
	private array $report = array( 'created' => array(), 'updated' => array(), 'unchanged' => array(), 'skipped' => array(), 'conflicts' => array(), 'local_edits' => array(), 'errors' => array(), 'media' => array( 'imported' => 0, 'reused' => 0, 'missing' => 0 ) );
	private array $media_index = array();
	private array $media_review = array();
	private array $log = array();

	public function __construct( array $opt = array() ) {
		$this->opt = array_merge( array(
			'dry_run'    => true,
			'offset'     => 0,
			'limit'      => 0,
			'max_photos' => 12,
			'policy'     => 'keep-local', // keep-local | overwrite
			'media_root' => '',
			'source'     => 'ppiestateagents.com',
			'lang'       => I18n::DEFAULT_LANG,
		), $opt );
	}

	public function log( string $m ): void {
		$this->log[] = $m;
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::log( $m );
		}
	}

	public static function load( string $file ): array {
		if ( ! is_readable( $file ) ) {
			throw new \RuntimeException( "Ficheiro ilegível: $file" );
		}
		$d = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $d ) || ( $d['format'] ?? '' ) !== 'ppi-properties/1' || ! isset( $d['properties'] ) ) {
			throw new \RuntimeException( 'Formato não reconhecido (esperado ppi-properties/1).' );
		}
		return $d;
	}

	public function load_media_index( string $file ): void {
		if ( ! is_readable( $file ) ) {
			return;
		}
		$d = json_decode( (string) file_get_contents( $file ), true );
		foreach ( (array) ( $d['files'] ?? array() ) as $f ) {
			$this->media_index[ $f['source_url'] ] = $f;
		}
	}

	/** Classificação manual das imagens (render, gráfico, planta) — migration/media-review.json. */
	public function load_media_review( string $file ): void {
		if ( is_readable( $file ) ) {
			$d = json_decode( (string) file_get_contents( $file ), true );
			$this->media_review = (array) ( $d['items'] ?? array() );
		}
	}

	/** Título só em maiúsculas → frase normal (o original fica guardado). */
	public static function title_case( string $t ): string {
		$letters = preg_replace( '/[^\p{L}]/u', '', $t );
		if ( mb_strlen( $letters ) < 6 || mb_strtoupper( $letters ) !== $letters ) {
			return $t;
		}
		$l = mb_strtolower( $t );
		return mb_strtoupper( mb_substr( $l, 0, 1 ) ) . mb_substr( $l, 1 );
	}

	public static function find_existing( string $external_id, string $lang = 'pt' ): int {
		$args = array(
			'post_type' => 'ppi_property', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 10, 'no_found_rows' => true,
			'meta_key' => 'ppi_external_id', 'meta_value' => $external_id, 'suppress_filters' => false, 'lang' => '',
		);
		foreach ( get_posts( $args ) as $id ) {
			if ( I18n::post_lang( (int) $id ) === $lang ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/** Converte um registo da fonte nos valores do modelo PPI (validados). */
	public static function map( array $r ): array {
		$vals = array(
			'reference'         => $r['reference'] ?? null,
			'business'          => 'Venda' === ( $r['business'] ?? '' ) ? 'sale' : ( str_starts_with( (string) ( $r['business'] ?? '' ), 'Arrend' ) ? 'rent' : 'sale' ),
			'commercial_status' => in_array( $r['commercial_status'] ?? '', array_keys( Fields::commercial_statuses() ), true ) ? $r['commercial_status'] : 'available',
			'price'             => empty( $r['price_on_request'] ) ? ( $r['price'] ?? null ) : null,
			'price_on_request'  => ! empty( $r['price_on_request'] ),
			'currency'          => $r['currency'] ?? 'EUR',
			'badge'             => $r['badge'] ? self::badge( $r['badge'] ) : null,
			'subtype'           => $r['subtype'] ?? null,
			'typology'          => $r['typology'] ?? null,
			'bedrooms'          => $r['bedrooms'] ?? null,
			'bathrooms'         => $r['bathrooms'] ?? null,
			'area_useful'       => $r['area_useful'] ?? null,
			'area_gross'        => $r['area_gross'] ?? null,
			'area_land'         => $r['area_land'] ?? null,
			'year'              => $r['year'] ?? null,
			'condition'         => Fields::condition_from_source( $r['condition'] ?? null ),
			'energy_class'      => in_array( $r['energy_class'] ?? '', Fields::energy_classes(), true ) ? $r['energy_class'] : null,
			'features'          => $r['features'] ?? array(),
			'postal_code'       => $r['postal_code'] ?? null,
			'location_precision'=> 'parish',
			'address_private'   => $r['location_query'] ?? null,
			'video_url'         => $r['video'] ?? null,
			'external_id'       => (string) $r['external_id'],
			'source'            => $r['source'] ?? 'import',
			'source_url'        => $r['source_url'] ?? null,
		);
		if ( 'Terreno' === ( $r['type'] ?? '' ) ) {
			$vals['bedrooms'] = $vals['bathrooms'] = $vals['typology'] = null;
		}
		$clean = array();
		$errors = array();
		foreach ( $vals as $k => $v ) {
			[ $sv, $err ] = Fields::sanitize( $k, $v );
			if ( $err ) {
				$errors[] = "$k: $err";
			}
			$clean[ $k ] = $sv;
		}
		return array( $clean, $errors );
	}

	/** Etiquetas da origem em maiúsculas → frase normal. */
	private static function badge( string $b ): string {
		$b = trim( $b );
		if ( mb_strtoupper( $b ) !== $b ) {
			return $b;
		}
		$l = mb_strtolower( $b );
		return mb_strtoupper( mb_substr( $l, 0, 1 ) ) . mb_substr( $l, 1 );
	}

	public static function type_slug( string $t ): string {
		$map = array( 'Moradia' => 'moradia', 'Apartamento' => 'apartamento', 'Terreno' => 'terreno', 'Quinta' => 'quinta', 'Loja' => 'loja', 'Escritório' => 'escritorio', 'Negócio' => 'negocio' );
		return $map[ $t ] ?? sanitize_title( $t );
	}

	public static function derive_filters( array $features ): array {
		$have = array();
		foreach ( $features as $f ) {
			$have[ ( $f['group'] ?? '' ) . '|' . ( $f['label'] ?? '' ) ] = $f['value'] ?? '';
			$have[ $f['label'] ?? '' ] = $f['value'] ?? '';
		}
		$out = array();
		foreach ( Migrations::feature_filters() as $slug => [ , , $rules ] ) {
			foreach ( $rules as $rule ) {
				if ( str_contains( $rule, '>=' ) ) {
					[ $lab, $min ] = explode( '>=', $rule );
					if ( isset( $have[ $lab ] ) && is_numeric( $have[ $lab ] ) && (int) $have[ $lab ] >= (int) $min ) {
						$out[] = $slug;
						break;
					}
				} elseif ( isset( $have[ $rule ] ) && 'Sim' === $have[ $rule ] ) {
					$out[] = $slug;
					break;
				}
			}
		}
		return $out;
	}

	private function location_terms( array $r ): array {
		$ids = array();
		$mun = trim( (string) ( $r['municipality'] ?? '' ) );
		$par = trim( (string) ( $r['parish'] ?? '' ) );
		if ( ! $mun ) {
			return $ids;
		}
		$m = term_exists( sanitize_title( $mun ), 'ppi_location', 0 ) ?: term_exists( $mun, 'ppi_location', 0 );
		if ( ! $m && ! $this->opt['dry_run'] ) {
			$m = wp_insert_term( $mun, 'ppi_location', array( 'slug' => sanitize_title( $mun ) ) );
		}
		if ( $m && ! is_wp_error( $m ) ) {
			$ids[] = (int) $m['term_id'];
			if ( $par ) {
				$slug = sanitize_title( $par );
				$p = term_exists( $slug, 'ppi_location', (int) $m['term_id'] );
				if ( ! $p && ! $this->opt['dry_run'] ) {
					$exists = get_term_by( 'slug', $slug, 'ppi_location' );
					if ( $exists && (int) $exists->parent !== (int) $m['term_id'] ) {
						$slug .= '-' . sanitize_title( $mun ); // mesmo nome noutro concelho
					}
					$p = wp_insert_term( $par, 'ppi_location', array( 'parent' => (int) $m['term_id'], 'slug' => $slug ) );
				}
				if ( $p && ! is_wp_error( $p ) ) {
					$ids[] = (int) $p['term_id'];
				}
			}
		}
		return $ids;
	}

	private function attachment_by_source( string $url ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ppi_source_url' AND meta_value = %s LIMIT 1", $url ) );
	}

	private function attachment_by_sha( string $sha ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ppi_source_sha1' AND meta_value = %s LIMIT 1", $sha ) );
	}

	/** Importa (ou reutiliza) uma imagem a partir da cópia local autorizada. Sem hotlinks. */
	private function media( string $url, int $post_id, string $label, string $fetched_at ): int {
		$existing = $this->attachment_by_source( $url );
		if ( $existing ) {
			$this->report['media']['reused']++;
			return $existing;
		}
		$info = $this->media_index[ $url ] ?? null;
		$path = $info ? rtrim( $this->opt['media_root'], '/' ) . '/' . preg_replace( '~^migration/~', '', $info['file'] ) : '';
		if ( ! $info || ! is_readable( $path ) ) {
			$this->report['media']['missing']++;
			return 0;
		}
		$sha = sha1_file( $path );
		if ( ( $dup = $this->attachment_by_sha( $sha ) ) ) {
			$this->report['media']['reused']++;
			return $dup;
		}
		if ( $this->opt['dry_run'] ) {
			$this->report['media']['imported']++;
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( basename( $path ) );
		copy( $path, $tmp );
		$file = array( 'name' => sanitize_file_name( basename( $path ) ), 'tmp_name' => $tmp );
		$aid  = media_handle_sideload( $file, $post_id, $label );
		if ( is_wp_error( $aid ) ) {
			@unlink( $tmp ); // phpcs:ignore
			$this->report['errors'][] = "media $url: " . $aid->get_error_message();
			return 0;
		}
		update_post_meta( $aid, '_ppi_source_url', $url );
		update_post_meta( $aid, '_ppi_source_sha1', $sha );
		update_post_meta( $aid, '_ppi_provenance', sprintf( 'Fotografia publicada em %s (com marca de água original), cópia recolhida em %s para migração autorizada.', $this->opt['source'], $fetched_at ) );
		$this->report['media']['imported']++;
		return (int) $aid;
	}

	private static function hash_source( array $r ): string {
		$copy = $r;
		unset( $copy['fetched_at'], $copy['review'] );
		return sha1( wp_json_encode( $copy ) );
	}

	/** Valores atuais (para detetar edições locais em relação à última importação). */
	private static function current_values( int $id ): array {
		$v = array( 'title' => get_post_field( 'post_title', $id ), 'content' => get_post_field( 'post_content', $id ) );
		foreach ( array_keys( Fields::all() ) as $k ) {
			$v[ $k ] = get_post_meta( $id, Fields::key( $k ), true );
		}
		return $v;
	}

	public function run( array $data ): array {
		$all   = $data['properties'];
		$slice = array_slice( $all, (int) $this->opt['offset'], $this->opt['limit'] ? (int) $this->opt['limit'] : null );
		wp_defer_term_counting( true );
		foreach ( $slice as $r ) {
			try {
				$this->one( $r );
			} catch ( \Throwable $e ) {
				$this->report['errors'][] = ( $r['reference'] ?? '?' ) . ': ' . $e->getMessage();
			}
		}
		wp_defer_term_counting( false );
		if ( ! $this->opt['dry_run'] ) {
			Search::flush();
		}
		$this->report['options'] = $this->opt;
		$this->report['processed'] = count( $slice );
		$this->report['total_in_file'] = count( $all );
		return $this->report;
	}

	private function one( array $r ): void {
		$ref = (string) ( $r['reference'] ?? '' );
		if ( empty( $r['external_id'] ) || '' === $ref || empty( $r['title'] ) ) {
			$this->report['skipped'][] = array( 'ref' => $ref, 'reason' => 'registo incompleto (ID externo, referência ou título em falta)' );
			return;
		}
		[ $vals, $errs ] = self::map( $r );
		if ( $errs ) {
			$this->report['errors'][] = "$ref: " . implode( '; ', $errs );
		}
		$conflicts = (array) ( $r['review']['conflicts'] ?? array() );
		$warnings  = (array) ( $r['review']['warnings'] ?? array() );
		$hash      = self::hash_source( $r );
		$id        = self::find_existing( (string) $r['external_id'], $this->opt['lang'] );

		// Referência já usada por outro imóvel (outra origem/ID): conflito, não sobrescrever.
		if ( ! $id ) {
			$clash = get_posts( array( 'post_type' => 'ppi_property', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => 'ppi_reference', 'meta_value' => $vals['reference'], 'lang' => '', 'suppress_filters' => false ) );
			if ( $clash ) {
				$this->report['conflicts'][] = array( 'ref' => $ref, 'reason' => 'referência já existe com outro ID externo', 'post' => $clash[0] );
				return;
			}
		}

		$action = $id ? 'updated' : 'created';
		if ( $id ) {
			$prev_hash = (string) get_post_meta( $id, '_ppi_import_hash', true );
			$imported  = (int) get_post_meta( $id, '_ppi_imported_at', true );
			$local     = (int) get_post_meta( $id, '_ppi_local_edit', true );
			$snapshot  = (string) get_post_meta( $id, '_ppi_import_snapshot', true );
			$edited    = $local > $imported || ( $snapshot && sha1( wp_json_encode( self::current_values( $id ) ) ) !== $snapshot );
			if ( $prev_hash === $hash ) {
				$this->report['unchanged'][] = $ref;
				return;
			}
			if ( $edited && 'overwrite' !== $this->opt['policy'] ) {
				$this->report['local_edits'][] = $ref;
				if ( ! $this->opt['dry_run'] ) {
					update_post_meta( $id, '_ppi_source_pending', wp_json_encode( $r, JSON_UNESCAPED_UNICODE ) );
				}
				return;
			}
		}
		if ( $this->opt['dry_run'] ) {
			$this->report[ $action ][] = $ref;
			if ( $conflicts ) {
				$this->report['conflicts'][] = array( 'ref' => $ref, 'reason' => 'conflitos de dados — ficará Pendente', 'codes' => array_column( $conflicts, 'code' ) );
			}
			$n = $this->opt['max_photos'] ? min( count( $r['photos'] ), (int) $this->opt['max_photos'] ) : count( $r['photos'] );
			foreach ( array_slice( $r['photos'], 0, $n ) as $u ) {
				$this->attachment_by_source( $u ) ? $this->report['media']['reused']++ : ( isset( $this->media_index[ $u ] ) ? $this->report['media']['imported']++ : $this->report['media']['missing']++ );
			}
			return;
		}

		$status = $conflicts ? 'pending' : 'publish';
		if ( $id ) {
			$cur = get_post_status( $id );
			// Não publicar automaticamente o que um editor despublicou; despublicar se surgirem conflitos.
			$status = $conflicts && ! get_post_meta( $id, '_ppi_review_resolved', true ) ? 'pending' : $cur;
		}
		$postarr = array(
			'post_type'    => 'ppi_property',
			'post_title'   => self::title_case( wp_strip_all_tags( (string) $r['title'] ) ),
			'post_content' => sanitize_textarea_field( (string) ( $r['description'] ?? '' ) ),
			'post_status'  => $status,
			'post_name'    => sanitize_title( $r['title'] . '-' . $ref ),
		);
		if ( $id ) {
			$postarr['ID'] = $id;
		}
		remove_filter( 'wp_insert_post_data', array( Admin_Property::class, 'gate_publish' ), 20 );
		$id = wp_insert_post( wp_slash( $postarr ), true );
		add_filter( 'wp_insert_post_data', array( Admin_Property::class, 'gate_publish' ), 20, 2 );
		if ( is_wp_error( $id ) ) {
			$this->report['errors'][] = "$ref: " . $id->get_error_message();
			return;
		}
		if ( function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $id, $this->opt['lang'] );
		}
		foreach ( $vals as $k => $v ) {
			null === $v ? delete_post_meta( $id, Fields::key( $k ) ) : update_post_meta( $id, Fields::key( $k ), $v );
		}
		wp_set_object_terms( $id, array( self::type_slug( (string) $r['type'] ) ), 'ppi_type' );
		wp_set_object_terms( $id, $this->location_terms( $r ), 'ppi_location' );
		wp_set_object_terms( $id, self::derive_filters( (array) $r['features'] ), 'ppi_feature' );

		$photos  = $this->opt['max_photos'] ? array_slice( $r['photos'], 0, (int) $this->opt['max_photos'] ) : $r['photos'];
		$review  = (array) ( $this->media_review[ $ref ] ?? array() );
		$kind_of = static function ( int $i ) use ( $review ): string {
			foreach ( array( 'render', 'graphic', 'plan' ) as $k ) {
				if ( in_array( $i, (array) ( $review[ $k ] ?? array() ), true ) ) {
					return $k;
				}
			}
			return 'photo';
		};
		$buckets = array( 'photo' => array(), 'plan' => array(), 'render' => array(), 'graphic' => array() );
		foreach ( $photos as $i => $u ) {
			/* translators: 1: ref, 2: n */
			$aid = $this->media( $u, $id, sprintf( 'Ref. %1$s — imagem %2$d', $ref, $i + 1 ), (string) ( $r['fetched_at'] ?? '' ) );
			if ( $aid ) {
				$k = $kind_of( $i );
				'photo' === $k ? delete_post_meta( $aid, '_ppi_media_kind' ) : update_post_meta( $aid, '_ppi_media_kind', $k );
				$buckets[ $k ][] = $aid;
			}
		}
		// Fotografias reais primeiro; renders, gráficos e plantas depois (ordem original preservada em _ppi_source_values).
		$gallery = array_merge( $buckets['photo'], $buckets['plan'], $buckets['render'], $buckets['graphic'] );
		if ( $review ) {
			$warnings[] = array( 'code' => $buckets['photo'] ? 'media_reordered' : 'no_real_photos', 'severity' => 'warning',
				'context' => $buckets['photo'] ? 'Capa original não era fotografia do imóvel (render/gráfico/planta); fotografias reais colocadas primeiro.' : 'Sem fotografias reais do imóvel nas imagens importadas: só renders/visualizações (etiquetadas no site).' );
		}
		$plans = array();
		foreach ( (array) $r['floorplans'] as $i => $u ) {
			$aid = $this->media( $u, $id, sprintf( 'Ref. %1$s — planta %2$d', $ref, $i + 1 ), (string) ( $r['fetched_at'] ?? '' ) );
			if ( $aid ) {
				$plans[] = $aid;
			}
		}
		Fields::set( $id, 'gallery', $gallery );
		Fields::set( $id, 'floorplans', $plans );
		$gallery ? set_post_thumbnail( $id, $gallery[0] ) : delete_post_thumbnail( $id );

		update_post_meta( $id, '_ppi_source_values', wp_json_encode( $r, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $id, '_ppi_photos_total_source', count( (array) $r['photos'] ) );
		$conflicts ? update_post_meta( $id, '_ppi_conflicts', wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE ) ) : delete_post_meta( $id, '_ppi_conflicts' );
		$warnings ? update_post_meta( $id, '_ppi_warnings', wp_json_encode( $warnings, JSON_UNESCAPED_UNICODE ) ) : delete_post_meta( $id, '_ppi_warnings' );
		if ( array_filter( $warnings, static fn( $w ) => 'description_language' === $w['code'] ) ) {
			update_post_meta( $id, '_ppi_content_lang', 'en' );
		} else {
			delete_post_meta( $id, '_ppi_content_lang' );
		}
		if ( ! empty( $r['agent']['name'] ) ) {
			update_post_meta( $id, '_ppi_agent_name', sanitize_text_field( $r['agent']['name'] ) );
		}
		$now = time();
		update_post_meta( $id, 'ppi_synced_at', gmdate( 'c', $now ) );
		update_post_meta( $id, '_ppi_import_hash', $hash );
		update_post_meta( $id, '_ppi_imported_at', $now );
		update_post_meta( $id, '_ppi_import_snapshot', sha1( wp_json_encode( self::current_values( $id ) ) ) );
		delete_post_meta( $id, '_ppi_source_pending' );
		I18n::sync_facts( $id );

		$this->report[ $action ][] = $ref;
		if ( $conflicts ) {
			$this->report['conflicts'][] = array( 'ref' => $ref, 'reason' => 'conflitos de dados — guardado como Pendente', 'codes' => array_column( $conflicts, 'code' ) );
		}
	}

	/** Referências presentes no site (desta origem) mas ausentes do ficheiro. Apenas reporta. */
	public static function missing_in_source( array $data ): array {
		$ext = array_map( static fn( $r ) => (string) $r['external_id'], (array) $data['properties'] );
		$db  = get_posts( array( 'post_type' => 'ppi_property', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => 'ppi_source', 'meta_compare' => 'EXISTS', 'lang' => I18n::DEFAULT_LANG, 'suppress_filters' => false ) );
		$out = array();
		foreach ( $db as $id ) {
			if ( ! in_array( (string) get_post_meta( $id, 'ppi_external_id', true ), $ext, true ) ) {
				$out[] = (string) get_post_meta( $id, 'ppi_reference', true );
			}
		}
		return $out;
	}

	public static function summary( array $rep ): string {
		return sprintf(
			'processados %d de %d · criados %d · atualizados %d · sem alterações %d · edições locais preservadas %d · ignorados %d · conflitos %d · erros %d · media importada %d, reutilizada %d, em falta %d',
			$rep['processed'] ?? 0, $rep['total_in_file'] ?? 0, count( $rep['created'] ), count( $rep['updated'] ), count( $rep['unchanged'] ), count( $rep['local_edits'] ),
			count( $rep['skipped'] ), count( $rep['conflicts'] ), count( $rep['errors'] ), $rep['media']['imported'], $rep['media']['reused'], $rep['media']['missing']
		);
	}
}

// Tamanhos de imagem: os necessários para srcset (cartões e galeria) + miniatura do painel.
add_action( 'after_setup_theme', static function () {
	add_image_size( 'ppi-480', 480, 0 );
}, 20 );
add_filter( 'intermediate_image_sizes_advanced', static function ( $sizes ) {
	unset( $sizes['medium'], $sizes['1536x1536'], $sizes['2048x2048'] );
	return $sizes;
} );
// WebP para os tamanhos gerados, quando o servidor suporta (o original JPEG é preservado).
add_filter( 'image_editor_output_format', static function ( $formats ) {
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
	}
	return $formats;
} );
add_filter( 'big_image_size_threshold', static fn() => 2400 );
