<?php
/**
 * Comandos WP-CLI: wp ppi <comando>.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class CLI {

	/**
	 * Importa imóveis (ppi-properties/1). Por defeito é um ensaio (dry-run): use --apply para gravar.
	 *
	 * ## OPTIONS
	 * <file>
	 * : Ficheiro JSON.
	 * [--apply]
	 * : Grava as alterações (sem esta opção nada é escrito).
	 * [--batch=<n>]
	 * : Tamanho do lote (0 = tudo de uma vez). Default 25.
	 * [--offset=<n>]
	 * : Começar neste registo.
	 * [--resume]
	 * : Continuar a partir do último lote concluído deste ficheiro.
	 * [--max-photos=<n>]
	 * : Fotografias por imóvel (0 = todas as disponíveis). Default 12.
	 * [--policy=<p>]
	 * : keep-local (default) preserva edições locais; overwrite reaplica a fonte.
	 * [--media=<file>]
	 * : Índice de media local (default: media-index.json ao lado do ficheiro).
	 * [--report=<file>]
	 * : Gravar o relatório JSON neste caminho.
	 */
	public function import( $args, $assoc ) {
		$file  = $args[0];
		$apply = isset( $assoc['apply'] );
		$batch = (int) ( $assoc['batch'] ?? 25 );
		$data  = Importer::load( $file );
		$total = count( $data['properties'] );
		$key   = 'ppi_import_state_' . md5( realpath( $file ) ?: $file );
		$off   = (int) ( $assoc['offset'] ?? 0 );
		if ( isset( $assoc['resume'] ) ) {
			$st  = get_option( $key, array() );
			$off = (int) ( $st['next'] ?? 0 );
			\WP_CLI::log( "Retomar a partir do registo $off." );
		}
		$media = $assoc['media'] ?? dirname( $file ) . '/media-index.json';
		$merged = null;
		$batch = $batch > 0 ? $batch : $total;
		for ( $o = $off; $o < $total; $o += $batch ) {
			$imp = new Importer( array(
				'dry_run' => ! $apply, 'offset' => $o, 'limit' => ( 0 === $off && $batch >= $total ) ? 0 : $batch,
				'max_photos' => (int) ( $assoc['max-photos'] ?? 12 ), 'policy' => in_array( $assoc['policy'] ?? '', array( 'keep-local', 'overwrite' ), true ) ? $assoc['policy'] : 'keep-local',
				'media_root' => dirname( $file ),
			) );
			$imp->load_media_index( $media );
			$imp->load_media_review( dirname( $file ) . '/media-review.json' );
			$rep = $imp->run( $data );
			$merged = $merged ? self::merge( $merged, $rep ) : $rep;
			if ( $apply ) {
				update_option( $key, array( 'next' => $o + $batch, 'at' => gmdate( 'c' ) ), false );
			}
			\WP_CLI::log( sprintf( 'Lote %d–%d: %s', $o + 1, min( $o + $batch, $total ), Importer::summary( $rep ) ) );
		}
		if ( ! $merged ) {
			\WP_CLI::success( 'Nada a processar (importação já concluída; use --offset=0 para repetir).' );
			return;
		}
		// Ausentes da fonte (relatório da execução completa)
		if ( $off === 0 ) {
			$merged['missing_in_source'] = Importer::missing_in_source( $data );
		}
		$merged['processed'] = $total - $off;
		if ( $apply ) {
			update_option( $key, array( 'next' => $total, 'done' => gmdate( 'c' ) ), false );
		}
		$path = $assoc['report'] ?? Migrations::private_dir() . '/import-' . gmdate( 'Ymd-His' ) . ( $apply ? '' : '-dry' ) . '.json';
		file_put_contents( $path, wp_json_encode( $merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		\WP_CLI::log( ( $apply ? 'APLICADO' : 'ENSAIO (nada gravado)' ) . ' — ' . Importer::summary( $merged ) );
		\WP_CLI::log( 'Relatório: ' . $path );
		if ( ! empty( $merged['missing_in_source'] ) ) {
			\WP_CLI::warning( 'Imóveis no site ausentes da fonte (não eliminados): ' . implode( ', ', $merged['missing_in_source'] ) );
		}
		$merged['errors'] ? \WP_CLI::warning( count( $merged['errors'] ) . ' erros — ver relatório.' ) : \WP_CLI::success( 'Concluído.' );
	}

	private static function merge( array $a, array $b ): array {
		foreach ( array( 'created', 'updated', 'unchanged', 'skipped', 'conflicts', 'local_edits', 'errors' ) as $k ) {
			$a[ $k ] = array_merge( $a[ $k ], $b[ $k ] );
		}
		foreach ( array( 'imported', 'reused', 'missing' ) as $k ) {
			$a['media'][ $k ] += $b['media'][ $k ];
		}
		$a['processed'] += $b['processed'];
		return $a;
	}

	/**
	 * Cria/atualiza traduções a partir de um ficheiro {ref: {title, content, status}}.
	 * Factos (preço, áreas, referência, fotografias) são copiados da versão PT; só o texto é traduzido.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Ficheiro JSON de traduções.
	 *
	 * [--lang=<lang>]
	 * : Idioma de destino. Default en.
	 *
	 * [--force]
	 * : Reaplica o texto mesmo sobre traduções editadas localmente.
	 */
	public function translate( $args, $assoc ) {
		if ( ! function_exists( 'pll_save_post_translations' ) ) {
			\WP_CLI::error( 'Polylang não está ativo.' );
		}
		$lang = $assoc['lang'] ?? 'en';
		$data = json_decode( (string) file_get_contents( $args[0] ), true );
		$n = 0;
		foreach ( (array) ( $data['items'] ?? array() ) as $ref => $t ) {
			$pt = Rest::id_by_ref( (string) $ref, 'pt' );
			if ( ! $pt ) {
				$pt = (int) ( get_posts( array( 'post_type' => 'ppi_property', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => 'ppi_reference', 'meta_value' => $ref, 'lang' => 'pt' ) )[0] ?? 0 );
			}
			if ( ! $pt ) {
				\WP_CLI::warning( "Ref. $ref não encontrada." );
				continue;
			}
			$tr  = pll_get_post_translations( $pt );
			$tid = (int) ( $tr[ $lang ] ?? 0 );
			$arr = array(
				'post_type' => 'ppi_property', 'post_title' => sanitize_text_field( $t['title'] ), 'post_content' => sanitize_textarea_field( $t['content'] ),
				'post_status' => get_post_status( $pt ), 'post_name' => sanitize_title( $t['title'] . '-' . $ref ),
			);
			if ( $tid ) {
				if ( get_post_meta( $tid, '_ppi_local_edit', true ) && empty( $assoc['force'] ) ) {
					\WP_CLI::log( "Ref. $ref: tradução editada localmente, mantida." );
					continue;
				}
				$arr['ID'] = $tid;
			}
			remove_filter( 'wp_insert_post_data', array( Admin_Property::class, 'gate_publish' ), 20 );
			$tid = wp_insert_post( wp_slash( $arr ), true );
			add_filter( 'wp_insert_post_data', array( Admin_Property::class, 'gate_publish' ), 20, 2 );
			if ( is_wp_error( $tid ) ) {
				\WP_CLI::warning( $tid->get_error_message() );
				continue;
			}
			pll_set_post_language( $tid, $lang );
			$tr[ $lang ] = $tid;
			pll_save_post_translations( $tr );
			update_post_meta( $tid, '_ppi_translation_status', sanitize_key( $t['status'] ?? 'proposal' ) );
			delete_post_meta( $tid, '_ppi_content_lang' );
			foreach ( array( '_ppi_conflicts', '_ppi_warnings', '_ppi_review_resolved', '_ppi_agent_name' ) as $k ) {
				$v = get_post_meta( $pt, $k, true );
				'' === $v ? delete_post_meta( $tid, $k ) : update_post_meta( $tid, $k, $v );
			}
			I18n::sync_facts( $pt );
			$n++;
		}
		Search::flush();
		\WP_CLI::success( "$n traduções criadas/atualizadas ($lang)." );
	}

	/**
	 * Conteúdo editorial das localidades: {slug: {intro_pt, intro_en, image_file, credit}}.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Ficheiro JSON das localidades.
	 */
	public function locations( $args ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$data = json_decode( (string) file_get_contents( $args[0] ), true );
		$base = dirname( $args[0] );
		foreach ( (array) $data['items'] as $slug => $d ) {
			$t = get_term_by( 'slug', $slug, 'ppi_location' );
			if ( ! $t ) {
				\WP_CLI::warning( "Localidade $slug não existe (sem imóveis importados?)." );
				continue;
			}
			foreach ( array( 'intro_pt', 'intro_en', 'image_credit' ) as $k ) {
				if ( isset( $d[ $k ] ) ) {
					update_term_meta( $t->term_id, 'ppi_' . $k, wp_kses_post( $d[ $k ] ) );
				}
			}
			if ( ! empty( $d['image_file'] ) && is_readable( "$base/{$d['image_file']}" ) ) {
				$src = 'local:' . $d['image_file'];
				global $wpdb;
				$aid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ppi_source_url' AND meta_value=%s", $src ) );
				if ( ! $aid ) {
					$tmp = wp_tempnam( basename( $d['image_file'] ) );
					copy( "$base/{$d['image_file']}", $tmp );
					$aid = media_handle_sideload( array( 'name' => basename( $d['image_file'] ), 'tmp_name' => $tmp ), 0, $t->name );
					if ( is_wp_error( $aid ) ) {
						\WP_CLI::warning( $aid->get_error_message() );
						continue;
					}
					update_post_meta( $aid, '_ppi_source_url', $src );
					update_post_meta( $aid, '_ppi_provenance', wp_strip_all_tags( $d['image_credit'] ?? '' ) . ' — ' . ( $d['image_source'] ?? '' ) );
					update_post_meta( $aid, '_wp_attachment_image_alt', '' );
				}
				update_term_meta( $t->term_id, 'ppi_image', $aid );
			}
			\WP_CLI::log( "✓ $slug" );
		}
		Search::flush();
		\WP_CLI::success( 'Localidades atualizadas.' );
	}

	/** Mostra o estado do catálogo, revisão e contactos. */
	public function status() {
		$counts = array();
		foreach ( I18n::languages() as $l ) {
			foreach ( array( 'publish', 'pending', 'draft' ) as $s ) {
				$counts[ "$l/$s" ] = count( get_posts( array( 'post_type' => 'ppi_property', 'post_status' => $s, 'fields' => 'ids', 'posts_per_page' => -1, 'lang' => $l, 'suppress_filters' => false ) ) );
			}
			$counts[ "$l/listáveis" ] = count( Search::index( $l ) );
		}
		foreach ( $counts as $k => $v ) {
			\WP_CLI::log( str_pad( $k, 18 ) . $v );
		}
		\WP_CLI::log( 'contactos         ' . count( get_posts( array( 'post_type' => 'ppi_lead', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) ) ) );
		\WP_CLI::log( 'ambiente          ' . wp_get_environment_type() . ' · SMTP ' . ( Mail::configured() ? Mail::env( 'SMTP_HOST' ) : 'não configurado' ) );
	}

	/**
	 * Exporta o mapa de redirecionamentos (CSV).
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Caminho do CSV (por defeito, saída padrão).
	 */
	public function redirects( $args, $assoc ) {
		$rows = Redirects::rows();
		$fh = fopen( $assoc['file'] ?? 'php://stdout', 'w' );
		fputcsv( $fh, array( 'from', 'to', 'code', 'note' ), ',', '"', '\\' );
		foreach ( $rows as $r ) {
			fputcsv( $fh, $r, ',', '"', '\\' );
		}
		fclose( $fh );
		if ( isset( $assoc['file'] ) ) {
			\WP_CLI::success( count( $rows ) . ' linhas em ' . $assoc['file'] );
		}
	}

	/** Reconstrói o índice de pesquisa. */
	public function reindex() {
		Search::flush();
		foreach ( I18n::languages() as $l ) {
			\WP_CLI::log( "$l: " . count( Search::index( $l ) ) . ' imóveis listáveis' );
		}
	}

	/** Repete notificações de leads falhadas/pendentes. */
	public function retry_mail() {
		\WP_CLI::success( Mail::retry_failed() . ' notificações enviadas.' );
	}

	/** Aplica a retenção de contactos agora. */
	public function retention() {
		\WP_CLI::success( Privacy::retention() . ' contactos eliminados por retenção.' );
	}
}

\WP_CLI::add_command( 'ppi', CLI::class );
