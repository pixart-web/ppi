<?php
/**
 * Editor de imóveis no painel: separadores, validação, galeria ordenável, ponto focal,
 * revisão de conflitos de dados, colunas e ajuda contextual.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Admin_Property {

	public static function init(): void {
		add_action( 'add_meta_boxes_ppi_property', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_ppi_property', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'gate_publish' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_ppi_property_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_ppi_property_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-ppi_property_sortable_columns', static fn( $c ) => $c + array( 'ppi_price' => 'ppi_price', 'ppi_ref' => 'ppi_ref' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_query' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filters' ) );
		add_action( 'load-post.php', array( __CLASS__, 'help' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'help' ) );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'focal_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'focal_save' ), 10, 2 );
		add_action( 'wp_ajax_ppi_focal', array( __CLASS__, 'ajax_focal' ) );
		add_filter( 'enter_title_here', static fn( $t, $post ) => 'ppi_property' === $post->post_type ? __( 'Título curto e factual (ex.: Moradia T3 com piscina em Budens)', 'ppi-core' ) : $t, 10, 2 );
	}

	public static function assets( $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'ppi_property', 'ppi_lead' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'ppi-admin', PPI_CORE_URL . 'assets/admin.css', array(), PPI_CORE_VERSION );
		if ( 'ppi_property' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'ppi-admin', PPI_CORE_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), PPI_CORE_VERSION, true );
			wp_localize_script( 'ppi-admin', 'ppiAdmin', array(
				'nonce' => wp_create_nonce( 'ppi_focal' ),
				'i18n'  => array(
					'choose' => __( 'Escolher fotografias do imóvel', 'ppi-core' ), 'add' => __( 'Adicionar', 'ppi-core' ),
					'remove' => __( 'Remover', 'ppi-core' ), 'focal' => __( 'Ponto focal', 'ppi-core' ),
					'focalHelp' => __( 'Clique no ponto da fotografia que deve ficar sempre visível nos recortes. Esc para fechar.', 'ppi-core' ),
					'saved' => __( 'Ponto focal guardado', 'ppi-core' ), 'cover' => __( 'Capa', 'ppi-core' ),
				),
			) );
		}
	}

	public static function boxes(): void {
		remove_meta_box( 'tagsdiv-ppi_feature', 'ppi_property', 'side' );
		remove_meta_box( 'ppi_typediv', 'ppi_property', 'side' );
		remove_meta_box( 'postimagediv', 'ppi_property', 'side' );
		remove_meta_box( 'postexcerpt', 'ppi_property', 'normal' );
		add_meta_box( 'ppi-review', __( 'Revisão de dados', 'ppi-core' ), array( __CLASS__, 'review_box' ), 'ppi_property', 'side', 'high' );
		add_meta_box( 'ppi-data', __( 'Dados do imóvel', 'ppi-core' ), array( __CLASS__, 'data_box' ), 'ppi_property', 'normal', 'high' );
	}

	private static function tabs(): array {
		return array(
			'summary'  => __( 'Resumo e preço', 'ppi-core' ),
			'details'  => __( 'Áreas e características principais', 'ppi-core' ),
			'location' => __( 'Localização', 'ppi-core' ),
			'media'    => __( 'Fotografias e media', 'ppi-core' ),
			'features' => __( 'Características', 'ppi-core' ),
			'source'   => __( 'Origem', 'ppi-core' ),
		);
	}

	private static function input( string $name, array $def, $value ): string {
		$id  = 'ppi-f-' . $name;
		$n   = 'ppi[' . $name . ']';
		$req = ! empty( $def['required'] ) ? ' required' : '';
		$h   = '<div class="ppi-row ppi-row--' . esc_attr( $def['type'] ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . ( $req ? ' *' : '' ) . '</label>';
		switch ( $def['type'] ) {
			case 'enum':
				$h .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $n ) . '"><option value="">' . esc_html__( '— não indicado —', 'ppi-core' ) . '</option>';
				foreach ( $def['options'] as $k => $l ) {
					$h .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				$h .= '</select>';
				break;
			case 'bool':
				$h = '<div class="ppi-row ppi-row--bool"><label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $n ) . '" value="1"' . checked( (bool) $value, true, false ) . '> ' . esc_html( $def['label'] ) . '</label>';
				break;
			case 'int':
			case 'float':
				$h .= '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( null === $value ? '' : (string) $value ) . '" step="' . ( 'float' === $def['type'] ? 'any' : '1' ) . '"' . ( isset( $def['min'] ) ? ' min="' . esc_attr( (string) $def['min'] ) . '"' : '' ) . ( isset( $def['max'] ) ? ' max="' . esc_attr( (string) $def['max'] ) . '"' : '' ) . $req . '>';
				break;
			default:
				$h .= '<input type="' . ( 'url' === $def['type'] ? 'url' : 'text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( (string) $value ) . '"' . $req . ' class="regular-text">';
		}
		if ( ! empty( $def['help'] ) ) {
			$h .= '<p class="description">' . esc_html( $def['help'] ) . '</p>';
		}
		return $h . '</div>';
	}

	public static function data_box( \WP_Post $post ): void {
		wp_nonce_field( 'ppi_property_meta', 'ppi_property_nonce' );
		$fields = Fields::all();
		$tabs   = self::tabs();
		echo '<div class="ppi-tabs" data-ppi-tabs><div role="tablist" class="ppi-tabs__list" aria-label="' . esc_attr__( 'Secções do imóvel', 'ppi-core' ) . '">';
		$first = true;
		foreach ( $tabs as $k => $l ) {
			echo '<button type="button" role="tab" id="ppi-tab-' . esc_attr( $k ) . '" aria-controls="ppi-panel-' . esc_attr( $k ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '"' . ( $first ? '' : ' tabindex="-1"' ) . '>' . esc_html( $l ) . '</button>';
			$first = false;
		}
		echo '</div>';
		$first = true;
		foreach ( $tabs as $k => $l ) {
			echo '<div role="tabpanel" class="ppi-tabs__panel" id="ppi-panel-' . esc_attr( $k ) . '" aria-labelledby="ppi-tab-' . esc_attr( $k ) . '"' . ( $first ? '' : ' hidden' ) . '>';
			$first = false;
			if ( 'summary' === $k ) {
				$types = get_terms( array( 'taxonomy' => 'ppi_type', 'hide_empty' => false ) );
				$cur   = wp_get_object_terms( $post->ID, 'ppi_type', array( 'fields' => 'ids' ) );
				echo '<div class="ppi-row"><label for="ppi-f-type">' . esc_html__( 'Tipo de imóvel', 'ppi-core' ) . ' *</label><select id="ppi-f-type" name="ppi_type_term" required><option value="">' . esc_html__( '— escolher —', 'ppi-core' ) . '</option>';
				foreach ( $types as $t ) {
					echo '<option value="' . (int) $t->term_id . '"' . selected( in_array( $t->term_id, (array) $cur, true ), true, false ) . '>' . esc_html( $t->name ) . '</option>';
				}
				echo '</select><p class="description">' . esc_html__( 'Em terrenos não são mostrados quartos nem casas de banho.', 'ppi-core' ) . '</p></div>';
				echo '<div class="ppi-row"><label for="ppi-f-agentname">' . esc_html__( 'Consultor responsável', 'ppi-core' ) . '</label><input id="ppi-f-agentname" class="regular-text" name="ppi_agent_name" value="' . esc_attr( (string) get_post_meta( $post->ID, '_ppi_agent_name', true ) ) . '"><p class="description">' . esc_html__( 'Nome igual ao da lista de equipa. Só aparece no site se o membro estiver validado; caso contrário mostra-se o contacto da agência.', 'ppi-core' ) . '</p></div>';
			}
			if ( 'features' === $k ) {
				self::features_editor( $post );
			}
			if ( 'media' === $k ) {
				self::media_editor( $post );
			}
			foreach ( $fields as $name => $def ) {
				if ( $def['tab'] !== $k || in_array( $def['type'], array( 'ids', 'json' ), true ) ) {
					continue;
				}
				$raw = get_post_meta( $post->ID, Fields::key( $name ), true );
				$val = '' === $raw ? ( $def['default'] ?? null ) : $raw;
				if ( 'source' === $k ) {
					echo '<div class="ppi-row"><span class="ppi-label">' . esc_html( $def['label'] ) . '</span> <code>' . esc_html( (string) $val ?: '—' ) . '</code></div>';
					continue;
				}
				echo self::input( $name, $def, $val ); // phpcs:ignore -- escapado em input()
			}
			if ( 'source' === $k ) {
				$orig = get_post_meta( $post->ID, '_ppi_source_values', true );
				if ( $orig ) {
					echo '<details><summary>' . esc_html__( 'Valores originais da importação', 'ppi-core' ) . '</summary><pre class="ppi-pre">' . esc_html( wp_json_encode( json_decode( (string) $orig, true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
				}
				echo '<p class="description">' . esc_html__( 'Campos de origem só de leitura. Alterações editoriais locais são preservadas pelo importador.', 'ppi-core' ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	private static function features_editor( \WP_Post $post ): void {
		$terms = get_terms( array( 'taxonomy' => 'ppi_feature', 'hide_empty' => false ) );
		$cur   = wp_get_object_terms( $post->ID, 'ppi_feature', array( 'fields' => 'ids' ) );
		echo '<fieldset class="ppi-row"><legend>' . esc_html__( 'Filtros da pesquisa', 'ppi-core' ) . '</legend><div class="ppi-checks">';
		foreach ( $terms as $t ) {
			echo '<label><input type="checkbox" name="ppi_feature_terms[]" value="' . (int) $t->term_id . '"' . checked( in_array( $t->term_id, (array) $cur, true ), true, false ) . '> ' . esc_html( $t->name ) . '</label>';
		}
		echo '</div><p class="description">' . esc_html__( 'Na importação são preenchidos a partir da lista abaixo; pode corrigi-los.', 'ppi-core' ) . '</p></fieldset>';
		$rows = (array) Fields::get( $post->ID, 'features' );
		echo '<table class="widefat ppi-feat-table" data-ppi-features><thead><tr><th scope="col">' . esc_html__( 'Grupo', 'ppi-core' ) . '</th><th scope="col">' . esc_html__( 'Característica', 'ppi-core' ) . '</th><th scope="col">' . esc_html__( 'Valor', 'ppi-core' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Ações', 'ppi-core' ) . '</span></th></tr></thead><tbody>';
		$rows[] = array( 'group' => '', 'label' => '', 'value' => '' );
		foreach ( $rows as $i => $r ) {
			echo '<tr><td><input aria-label="' . esc_attr__( 'Grupo', 'ppi-core' ) . '" name="ppi_features[' . (int) $i . '][group]" value="' . esc_attr( $r['group'] ) . '"></td><td><input aria-label="' . esc_attr__( 'Característica', 'ppi-core' ) . '" name="ppi_features[' . (int) $i . '][label]" value="' . esc_attr( $r['label'] ) . '"></td><td><input aria-label="' . esc_attr__( 'Valor', 'ppi-core' ) . '" name="ppi_features[' . (int) $i . '][value]" value="' . esc_attr( $r['value'] ) . '" placeholder="Sim"></td><td><button type="button" class="button-link" data-ppi-row-remove>' . esc_html__( 'Remover', 'ppi-core' ) . '</button></td></tr>';
		}
		echo '</tbody></table><p><button type="button" class="button" data-ppi-row-add>' . esc_html__( 'Adicionar característica', 'ppi-core' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Valores numéricos a 0 e «Não» não são mostrados no site. Não repita aqui quartos/casas de banho.', 'ppi-core' ) . '</p>';
	}

	private static function media_list( string $field, array $ids, string $label, string $help ): void {
		echo '<div class="ppi-row ppi-media" data-ppi-media="' . esc_attr( $field ) . '"><span class="ppi-label">' . esc_html( $label ) . '</span>';
		echo '<input type="hidden" name="ppi[' . esc_attr( $field ) . ']" value="' . esc_attr( implode( ',', $ids ) ) . '" data-ppi-media-input>';
		echo '<ul class="ppi-media__list" data-ppi-media-list>';
		foreach ( $ids as $i => $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( ! $src ) {
				continue;
			}
			echo '<li data-id="' . (int) $id . '" data-focal="' . esc_attr( Property::focal( $id ) ) . '" data-full="' . esc_url( (string) wp_get_attachment_image_url( $id, 'medium_large' ) ) . '"><img src="' . esc_url( $src ) . '" alt="" width="96" height="96" style="object-position:' . esc_attr( Property::focal( $id ) ) . '">';
			echo '<span class="ppi-media__actions"><button type="button" class="button-link" data-ppi-focal>' . esc_html__( 'Ponto focal', 'ppi-core' ) . '</button> <button type="button" class="button-link ppi-danger" data-ppi-media-remove>' . esc_html__( 'Remover', 'ppi-core' ) . '</button></span></li>';
		}
		echo '</ul><p><button type="button" class="button" data-ppi-media-add>' . esc_html__( 'Adicionar ficheiros', 'ppi-core' ) . '</button></p><p class="description">' . esc_html( $help ) . '</p></div>';
	}

	private static function media_editor( \WP_Post $post ): void {
		self::media_list( 'gallery', (array) Fields::get( $post->ID, 'gallery' ), __( 'Galeria', 'ppi-core' ), __( 'Arraste para ordenar. A primeira é a capa. Use apenas fotografias deste imóvel; mantenha as marcas de água originais.', 'ppi-core' ) );
		self::media_list( 'floorplans', (array) Fields::get( $post->ID, 'floorplans' ), __( 'Plantas', 'ppi-core' ), __( 'Só plantas reais deste imóvel.', 'ppi-core' ) );
	}

	public static function review_box( \WP_Post $post ): void {
		$conf = json_decode( (string) get_post_meta( $post->ID, '_ppi_conflicts', true ), true ) ?: array();
		$warn = json_decode( (string) get_post_meta( $post->ID, '_ppi_warnings', true ), true ) ?: array();
		$res  = get_post_meta( $post->ID, '_ppi_review_resolved', true );
		if ( ! $conf && ! $warn ) {
			echo '<p>' . esc_html__( 'Sem divergências detetadas na importação.', 'ppi-core' ) . '</p>';
			return;
		}
		if ( $conf ) {
			echo '<div class="ppi-review ' . ( $res ? 'is-resolved' : 'is-open' ) . '"><p><strong>' . esc_html( $res ? __( 'Conflitos verificados', 'ppi-core' ) : __( 'Conflitos por resolver — publicação bloqueada', 'ppi-core' ) ) . '</strong></p><ul>';
			foreach ( $conf as $c ) {
				echo '<li><code>' . esc_html( $c['code'] ) . '</code> ' . esc_html( self::conflict_text( $c ) ) . '</li>';
			}
			echo '</ul>';
			if ( $res ) {
				$r = json_decode( (string) $res, true );
				/* translators: 1: user, 2: date */
				echo '<p class="description">' . esc_html( sprintf( __( 'Verificado por %1$s em %2$s: %3$s', 'ppi-core' ), $r['user'] ?? '?', $r['date'] ?? '?', $r['note'] ?? '' ) ) . '</p>';
			} else {
				echo '<p><label><input type="checkbox" name="ppi_review_ok" value="1"> ' . esc_html__( 'Confirmo que corrigi/validei estes dados com a fonte.', 'ppi-core' ) . '</label></p>';
				echo '<p><label for="ppi-review-note">' . esc_html__( 'Nota (obrigatória)', 'ppi-core' ) . '</label><textarea id="ppi-review-note" name="ppi_review_note" rows="2" class="widefat"></textarea></p>';
			}
			echo '</div>';
		}
		if ( $warn ) {
			echo '<details><summary>' . esc_html( sprintf( /* translators: %d count */ _n( '%d alerta não bloqueante', '%d alertas não bloqueantes', count( $warn ), 'ppi-core' ), count( $warn ) ) ) . '</summary><ul class="ppi-warnings">';
			foreach ( $warn as $c ) {
				echo '<li><code>' . esc_html( $c['code'] ) . '</code> ' . esc_html( self::conflict_text( $c ) ) . '</li>';
			}
			echo '</ul></details>';
		}
	}

	public static function conflict_text( array $c ): string {
		$fv = $c['field_value'] ?? null;
		if ( is_array( $fv ) ) {
			$fv = implode( ' / ', array_map( static fn( $k, $v ) => $k . ': ' . ( null === $v ? '—' : $v ), array_keys( $fv ), $fv ) );
		}
		$parts = array();
		if ( isset( $c['text_value'] ) ) {
			/* translators: 1: source, 2: value */
			$parts[] = sprintf( __( 'no %1$s: %2$s', 'ppi-core' ), $c['source'] ?? 'texto', $c['text_value'] );
		}
		if ( null !== $fv && '' !== $fv ) {
			/* translators: %s value */
			$parts[] = sprintf( __( 'no campo: %s', 'ppi-core' ), $fv );
		}
		$txt = implode( ' · ', $parts );
		return trim( $txt . ( ! empty( $c['context'] ) ? ' — «' . mb_substr( $c['context'], 0, 140 ) . '»' : '' ) );
	}

	public static function has_open_conflicts( int $id ): bool {
		$conf = json_decode( (string) get_post_meta( $id, '_ppi_conflicts', true ), true );
		return $conf && ! get_post_meta( $id, '_ppi_review_resolved', true );
	}

	/** Impede publicar um imóvel com conflitos materiais por verificar (passa a «Pendente»). */
	public static function gate_publish( array $data, array $postarr ): array {
		if ( 'ppi_property' !== $data['post_type'] || 'publish' !== $data['post_status'] || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$resolving = ! empty( $_POST['ppi_review_ok'] ) && trim( (string) ( $_POST['ppi_review_note'] ?? '' ) ); // phpcs:ignore -- verificado em save()
		if ( self::has_open_conflicts( (int) $postarr['ID'] ) && ! $resolving ) {
			$data['post_status'] = 'pending';
			set_transient( 'ppi_notice_' . get_current_user_id(), array( 'error', __( 'Imóvel guardado como «Pendente»: há conflitos de dados por verificar (caixa «Revisão de dados»).', 'ppi-core' ) ), 60 );
		}
		return $data;
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! isset( $_POST['ppi_property_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppi_property_nonce'] ) ), 'ppi_property_meta' ) || ! current_user_can( 'edit_ppi_property', $post_id ) ) {
			return;
		}
		$in     = (array) wp_unslash( $_POST['ppi'] ?? array() );
		$errors = array();
		foreach ( Fields::all() as $name => $def ) {
			if ( 'source' === $def['tab'] || 'features' === $name ) {
				continue;
			}
			if ( 'bool' === $def['type'] ) {
				$in[ $name ] = ! empty( $in[ $name ] );
			}
			if ( ! array_key_exists( $name, $in ) ) {
				continue;
			}
			[ $v, $err ] = Fields::sanitize( $name, $in[ $name ] );
			if ( $err ) {
				$errors[] = $err;
				continue;
			}
			if ( 'reference' === $name && $v && self::reference_taken( (string) $v, $post_id ) ) {
				/* translators: %s reference */
				$errors[] = sprintf( __( 'A referência %s já existe noutro imóvel. Mantida a anterior.', 'ppi-core' ), $v );
				continue;
			}
			null === $v ? delete_post_meta( $post_id, Fields::key( $name ) ) : update_post_meta( $post_id, Fields::key( $name ), $v );
		}
		if ( Fields::get( $post_id, 'price_on_request' ) ) {
			// Preço sob consulta: não guardar preço numérico (evita «0 €» e ordenações enganadoras).
			delete_post_meta( $post_id, 'ppi_price' );
		}
		if ( isset( $_POST['ppi_features'] ) ) {
			Fields::set( $post_id, 'features', array_values( (array) wp_unslash( $_POST['ppi_features'] ) ) );
		}
		if ( isset( $_POST['ppi_type_term'] ) ) {
			$t = absint( $_POST['ppi_type_term'] );
			wp_set_object_terms( $post_id, $t ? array( $t ) : array(), 'ppi_type' );
		}
		$feat_terms = array_map( 'absint', (array) ( $_POST['ppi_feature_terms'] ?? array() ) );
		wp_set_object_terms( $post_id, $feat_terms, 'ppi_feature' );
		if ( isset( $_POST['ppi_agent_name'] ) ) {
			update_post_meta( $post_id, '_ppi_agent_name', sanitize_text_field( wp_unslash( $_POST['ppi_agent_name'] ) ) );
		}
		$gallery = (array) Fields::get( $post_id, 'gallery' );
		$gallery ? set_post_thumbnail( $post_id, $gallery[0] ) : delete_post_thumbnail( $post_id );
		if ( ! empty( $_POST['ppi_review_ok'] ) ) {
			$note = sanitize_textarea_field( wp_unslash( (string) ( $_POST['ppi_review_note'] ?? '' ) ) );
			if ( $note ) {
				update_post_meta( $post_id, '_ppi_review_resolved', wp_json_encode( array( 'user' => wp_get_current_user()->user_login, 'date' => current_time( 'Y-m-d H:i' ), 'note' => $note ), JSON_UNESCAPED_UNICODE ) );
			} else {
				$errors[] = __( 'Para dar os conflitos como verificados, escreva uma nota.', 'ppi-core' );
			}
		}
		// Alteração editorial local: o importador não a sobrescreve sem política explícita.
		update_post_meta( $post_id, '_ppi_local_edit', time() );
		if ( $errors ) {
			set_transient( 'ppi_notice_' . get_current_user_id(), array( 'error', implode( ' ', $errors ) ), 60 );
		}
		I18n::sync_facts( $post_id );
		Search::flush();
	}

	public static function reference_taken( string $ref, int $post_id ): bool {
		$group = array_map( 'intval', array_values( I18n::translations( $post_id ) ) );
		$group[] = $post_id;
		$ids = get_posts( array( 'post_type' => 'ppi_property', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 20, 'meta_key' => 'ppi_reference', 'meta_value' => $ref, 'lang' => '', 'suppress_filters' => false ) );
		return (bool) array_diff( array_map( 'intval', $ids ), $group );
	}

	public static function notices(): void {
		$n = get_transient( 'ppi_notice_' . get_current_user_id() );
		if ( $n ) {
			delete_transient( 'ppi_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . esc_attr( $n[0] ) . ' is-dismissible"><p>' . esc_html( $n[1] ) . '</p></div>';
		}
		$screen = get_current_screen();
		if ( $screen && 'edit-ppi_property' === $screen->id ) {
			$open = get_posts( array( 'post_type' => 'ppi_property', 'post_status' => array( 'pending', 'draft' ), 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_ppi_conflicts', 'meta_compare' => 'EXISTS', 'lang' => '' ) );
			$open = array_filter( $open, array( __CLASS__, 'has_open_conflicts' ) );
			if ( $open ) {
				/* translators: %d count */
				echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( _n( '%d imóvel aguarda verificação de conflitos de dados antes de ser publicado.', '%d imóveis aguardam verificação de conflitos de dados antes de serem publicados.', count( $open ), 'ppi-core' ), count( $open ) ) ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=ppi_property&ppi_review=open' ) ) . '">' . esc_html__( 'Ver lista', 'ppi-core' ) . '</a></p></div>';
			}
		}
	}

	public static function columns( array $c ): array {
		$new = array();
		foreach ( $c as $k => $v ) {
			if ( 'title' === $k ) {
				$new['ppi_thumb'] = '<span class="screen-reader-text">' . esc_html__( 'Fotografia', 'ppi-core' ) . '</span>';
				$new['ppi_ref']   = __( 'Ref.', 'ppi-core' );
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['ppi_price']  = __( 'Preço', 'ppi-core' );
				$new['ppi_status'] = __( 'Estado comercial', 'ppi-core' );
				$new['ppi_review'] = __( 'Revisão', 'ppi-core' );
				$new['ppi_feat']   = __( 'Destaque', 'ppi-core' );
			}
		}
		unset( $new['date'] );
		$new['date'] = $c['date'] ?? __( 'Data', 'ppi-core' );
		return $new;
	}

	public static function column( string $col, int $id ): void {
		$p = Property::from( $id );
		switch ( $col ) {
			case 'ppi_thumb':
				$g = $p->gallery();
				echo $g ? wp_get_attachment_image( $g[0], array( 56, 56 ), false, array( 'alt' => '' ) ) : '—'; // phpcs:ignore
				break;
			case 'ppi_ref':
				echo '<strong>' . esc_html( $p->reference() ) . '</strong>';
				break;
			case 'ppi_price':
				echo esc_html( $p->price_label() );
				break;
			case 'ppi_status':
				echo esc_html( $p->status_label() );
				break;
			case 'ppi_review':
				if ( self::has_open_conflicts( $id ) ) {
					echo '<span class="ppi-pill ppi-pill--bad">' . esc_html__( 'Conflito', 'ppi-core' ) . '</span>';
				} elseif ( get_post_meta( $id, '_ppi_conflicts', true ) ) {
					echo '<span class="ppi-pill ppi-pill--ok">' . esc_html__( 'Verificado', 'ppi-core' ) . '</span>';
				} elseif ( 'proposal' === get_post_meta( $id, '_ppi_translation_status', true ) ) {
					echo '<span class="ppi-pill">' . esc_html__( 'Tradução por rever', 'ppi-core' ) . '</span>';
				} else {
					echo '—';
				}
				break;
			case 'ppi_feat':
				$f = (int) $p->get( 'featured_order' );
				echo $f ? esc_html( '#' . $f ) : '—';
				break;
		}
	}

	public static function admin_query( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || 'ppi_property' !== $q->get( 'post_type' ) ) {
			return;
		}
		$ob = $q->get( 'orderby' );
		if ( 'ppi_price' === $ob ) {
			$q->set( 'meta_key', 'ppi_price' );
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( 'ppi_ref' === $ob ) {
			$q->set( 'meta_key', 'ppi_reference' );
			$q->set( 'orderby', 'meta_value' );
		}
		$s = $q->get( 's' );
		if ( $s && preg_match( '/^[\d\s_\-A-Za-z]{2,12}$/', $s ) && preg_match( '/\d/', $s ) ) {
			// pesquisa por referência no painel
			$q->set( 's', '' );
			$q->set( 'meta_query', array( array( 'key' => 'ppi_reference', 'value' => $s, 'compare' => 'LIKE' ) ) );
		}
		if ( isset( $_GET['ppi_review'] ) && 'open' === $_GET['ppi_review'] ) { // phpcs:ignore
			$q->set( 'meta_query', array( array( 'key' => '_ppi_conflicts', 'compare' => 'EXISTS' ), array( 'key' => '_ppi_review_resolved', 'compare' => 'NOT EXISTS' ) ) );
		}
		if ( ! empty( $_GET['ppi_cstatus'] ) ) { // phpcs:ignore
			$q->set( 'meta_query', array( array( 'key' => 'ppi_commercial_status', 'value' => sanitize_key( $_GET['ppi_cstatus'] ) ) ) ); // phpcs:ignore
		}
	}

	public static function list_filters( string $type ): void {
		if ( 'ppi_property' !== $type ) {
			return;
		}
		$cur = isset( $_GET['ppi_cstatus'] ) ? sanitize_key( $_GET['ppi_cstatus'] ) : ''; // phpcs:ignore
		echo '<label class="screen-reader-text" for="ppi-cstatus">' . esc_html__( 'Estado comercial', 'ppi-core' ) . '</label><select name="ppi_cstatus" id="ppi-cstatus"><option value="">' . esc_html__( 'Todos os estados comerciais', 'ppi-core' ) . '</option>';
		foreach ( Fields::commercial_statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}

	public static function help(): void {
		$s = get_current_screen();
		if ( ! $s || 'ppi_property' !== $s->post_type ) {
			return;
		}
		$s->add_help_tab( array( 'id' => 'ppi-help-basics', 'title' => __( 'Criar um imóvel', 'ppi-core' ), 'content' =>
			'<p>' . esc_html__( '1. Título curto e factual. 2. Descrição fiel no editor. 3. Separador «Resumo e preço»: referência única, tipo, estado comercial e preço (vazio se não publicado; «Preço sob consulta» quando aplicável). 4. Áreas: útil, bruta e terreno são campos distintos — nunca preencher um com o valor do outro. 5. Localização: marque o concelho e a freguesia na caixa «Localizações». 6. Fotografias: adicione e ordene; a primeira é a capa. 7. Publicar.', 'ppi-core' ) . '</p>' ) );
		$s->add_help_tab( array( 'id' => 'ppi-help-status', 'title' => __( 'Estados', 'ppi-core' ), 'content' =>
			'<p>' . esc_html__( 'Publicação (WordPress) e estado comercial são independentes. Reservado: continua na listagem com etiqueta. Vendido: página mantida com indicação, sai da listagem e do sitemap. Retirado: a página responde «indisponível» (410). Para remover temporariamente sem perder dados, passe a Rascunho.', 'ppi-core' ) . '</p>' ) );
		$s->add_help_tab( array( 'id' => 'ppi-help-translate', 'title' => __( 'Traduções', 'ppi-core' ), 'content' =>
			'<p>' . esc_html__( 'Use a caixa «Idiomas» (Polylang) para criar a versão inglesa. Preço, áreas, referência, estado, fotografias e localização são copiados automaticamente e mantêm-se iguais; traduza apenas título e descrição.', 'ppi-core' ) . '</p>' ) );
	}

	public static function focal_field( array $fields, \WP_Post $post ): array {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}
		$fields['ppi_focal'] = array(
			'label' => __( 'Ponto focal (x y, %)', 'ppi-core' ),
			'input' => 'text',
			'value' => (string) get_post_meta( $post->ID, '_ppi_focal', true ),
			'helps' => __( 'Ex.: «50 40». Define o centro dos recortes. A fotografia completa é sempre visível na galeria.', 'ppi-core' ),
		);
		return $fields;
	}

	public static function focal_save( array $post, array $att ): array {
		if ( isset( $att['ppi_focal'] ) && current_user_can( 'edit_post', $post['ID'] ) ) {
			$v = trim( (string) $att['ppi_focal'] );
			preg_match( '/^(\d{1,3})\s+(\d{1,3})$/', $v, $m ) ? update_post_meta( $post['ID'], '_ppi_focal', min( 100, (int) $m[1] ) . ' ' . min( 100, (int) $m[2] ) ) : delete_post_meta( $post['ID'], '_ppi_focal' );
		}
		return $post;
	}

	public static function ajax_focal(): void {
		check_ajax_referer( 'ppi_focal', 'nonce' );
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! wp_attachment_is_image( $id ) ) {
			wp_send_json_error( null, 403 );
		}
		$x = min( 100, absint( $_POST['x'] ?? 50 ) );
		$y = min( 100, absint( $_POST['y'] ?? 50 ) );
		update_post_meta( $id, '_ppi_focal', "$x $y" );
		Search::flush();
		wp_send_json_success( array( 'focal' => "$x% $y%" ) );
	}
}

Admin_Property::init();
