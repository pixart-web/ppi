<?php
/**
 * Marcação semântica dos componentes do catálogo. O tema define a apresentação (CSS);
 * os nomes de classes seguem o padrão ppi-<componente>__<elemento>.
 * Todo o texto dinâmico é escapado no ponto de saída.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Render {

	public static function icon( string $name ): string {
		$p = array(
			'heart'   => '<path d="M12 20.5s-7.5-4.6-7.5-10.1A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.8c0 5.5-7.5 10.1-7.5 10.1Z"/>',
			'compare' => '<path d="M8 4v16M16 4v16M4 8h8M12 16h8"/>',
			'bed'     => '<path d="M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7M3 14h18M7 9V6h4v3"/>',
			'bath'    => '<path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3ZM6 12V6a2 2 0 0 1 4 0"/>',
			'area'    => '<path d="M4 4h16v16H4zM4 9h5M15 20v-5"/>',
			'land'    => '<path d="M3 19 9 9l4 6 3-4 5 8Z"/>',
			'prev'    => '<path d="m15 5-7 7 7 7"/>',
			'next'    => '<path d="m9 5 7 7-7 7"/>',
			'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
			'grid'    => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
			'phone'   => '<path d="M6.6 3.5 9 4l1.2 4-2 1.5a12 12 0 0 0 6.3 6.3L16 13.8l4 1.2.5 2.4A2.3 2.3 0 0 1 18.2 20 15.5 15.5 0 0 1 4 5.8a2.3 2.3 0 0 1 2.6-2.3Z"/>',
			'mail'    => '<path d="M3 6h18v12H3zM3 7l9 6 9-6"/>',
			'pin'     => '<path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11Z"/><circle cx="12" cy="10" r="2"/>',
			'filter'  => '<path d="M4 6h16M7 12h10M10 18h4"/>',
			'expand'  => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
		);
		return '<svg class="ppi-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	/** Botões de favorito e comparação (só visíveis com JavaScript). */
	public static function save_buttons( Property $p, bool $labels = false ): string {
		$ref = $p->reference();
		/* translators: %s reference */
		$fav = sprintf( __( 'Guardar o imóvel ref. %s nos favoritos', 'ppi-core' ), $ref );
		/* translators: %s reference */
		$cmp = sprintf( __( 'Adicionar o imóvel ref. %s à comparação', 'ppi-core' ), $ref );
		$o  = '<div class="ppi-save" hidden data-ppi-js>';
		$o .= '<button type="button" class="ppi-save__btn ppi-save__btn--fav" data-ppi-fav="' . esc_attr( $ref ) . '" aria-pressed="false" aria-label="' . esc_attr( $fav ) . '">' . self::icon( 'heart' ) . ( $labels ? '<span>' . esc_html__( 'Guardar', 'ppi-core' ) . '</span>' : '' ) . '</button>';
		$o .= '<button type="button" class="ppi-save__btn ppi-save__btn--cmp" data-ppi-compare="' . esc_attr( $ref ) . '" aria-pressed="false" aria-label="' . esc_attr( $cmp ) . '">' . self::icon( 'compare' ) . ( $labels ? '<span>' . esc_html__( 'Comparar', 'ppi-core' ) . '</span>' : '' ) . '</button>';
		return $o . '</div>';
	}

	/** Etiqueta para imagens que não são fotografias do imóvel (render, gráfico, planta). */
	public static function media_label( int $id ): string {
		$k = (string) get_post_meta( $id, '_ppi_media_kind', true );
		$labels = array(
			'render'  => __( 'Imagem ilustrativa — não é fotografia do imóvel', 'ppi-core' ),
			'graphic' => __( 'Imagem promocional', 'ppi-core' ),
			'plan'    => __( 'Planta ou imagem de satélite', 'ppi-core' ),
		);
		return $labels[ $k ] ?? '';
	}

	public static function image( int $id, string $sizes, array $attr = array(), string $size = 'large' ): string {
		$attr = array_merge( array( 'sizes' => $sizes, 'decoding' => 'async', 'loading' => 'lazy' ), $attr );
		if ( empty( $attr['style'] ) ) {
			$attr['style'] = 'object-position:' . Property::focal( $id );
		}
		return wp_get_attachment_image( $id, $size, false, $attr );
	}

	public static function card( Property $p, array $o = array() ): string {
		$o = array_merge( array( 'sizes' => '(min-width: 1200px) 30vw, (min-width: 700px) 45vw, 100vw', 'heading' => 'h3', 'eager' => false, 'variant' => '' ), $o );
		$img   = $p->gallery()[0] ?? 0;
		$h     = in_array( $o['heading'], array( 'h2', 'h3', 'h4' ), true ) ? $o['heading'] : 'h3';
		$facts = $p->key_facts( true );
		$cls   = 'ppi-card' . ( $o['variant'] ? ' ppi-card--' . sanitize_html_class( $o['variant'] ) : '' );
		ob_start();
		?>
		<article class="<?php echo esc_attr( $cls ); ?>" data-ref="<?php echo esc_attr( $p->reference() ); ?>">
			<div class="ppi-card__media">
				<?php
				if ( $img ) {
					echo self::image( $img, $o['sizes'], $o['eager'] ? array( 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => '' ) : array( 'alt' => '' ), 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image
				} else {
					echo '<div class="ppi-card__noimg">' . esc_html__( 'Sem fotografia disponível', 'ppi-core' ) . '</div>';
				}
				$tags = array();
				if ( $img && ( $ml = self::media_label( $img ) ) ) {
					$tags[] = '<span class="ppi-tag ppi-tag--media">' . esc_html( $ml ) . '</span>';
				}
				if ( 'reserved' === $p->status() ) {
					$tags[] = '<span class="ppi-tag ppi-tag--status">' . esc_html( $p->status_label() ) . '</span>';
				}
				if ( $p->get( 'badge' ) && 'pt' === I18n::current() ) {
					$tags[] = '<span class="ppi-tag">' . esc_html( $p->get( 'badge' ) ) . '</span>';
				}
				echo $tags ? '<div class="ppi-card__tags">' . implode( '', $tags ) . '</div>' : ''; // phpcs:ignore
				?>
			</div>
			<div class="ppi-card__body">
				<p class="ppi-card__loc"><?php echo esc_html( $p->location_label() ); ?></p>
				<<?php echo $h; // phpcs:ignore ?> class="ppi-card__title"><a href="<?php echo esc_url( $p->url() ); ?>" class="ppi-card__link"><?php echo esc_html( $p->title() ); ?></a></<?php echo $h; // phpcs:ignore ?>>
				<p class="ppi-card__price"><?php echo esc_html( $p->price_label() ); ?></p>
				<?php if ( $facts ) : ?>
					<ul class="ppi-card__facts">
						<?php foreach ( $facts as [ $k, $label, $value, $short ] ) : ?>
							<li class="ppi-fact ppi-fact--<?php echo esc_attr( $k ); ?>"><?php echo self::icon( array( 'bedrooms' => 'bed', 'bathrooms' => 'bath', 'area_land' => 'land' )[ $k ] ?? 'area' ); // phpcs:ignore ?><span><?php echo esc_html( $short ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="ppi-card__ref"><?php /* translators: %s reference */ printf( esc_html__( 'Ref. %s', 'ppi-core' ), esc_html( $p->reference() ) ); ?> <span class="ppi-card__type"><?php echo esc_html( $p->type_label() ); ?></span></p>
			</div>
			<?php echo self::save_buttons( $p ); // phpcs:ignore ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/** Pré-carrega meta, termos e miniaturas dos imóveis a apresentar (evita consultas por cartão). */
	public static function prime( array $ids ): void {
		if ( ! $ids ) {
			return;
		}
		_prime_post_caches( $ids, true, true );
		$thumbs = array();
		foreach ( $ids as $id ) {
			$g = Fields::get( (int) $id, 'gallery' );
			if ( $g ) {
				$thumbs[] = $g[0];
			}
			$t = get_post_thumbnail_id( $id );
			if ( $t ) {
				$thumbs[] = $t;
			}
		}
		if ( $thumbs ) {
			_prime_post_caches( array_unique( $thumbs ), false, true );
		}
	}

	public static function cards( array $ids, array $o = array() ): string {
		self::prime( $ids );
		$out = '';
		foreach ( $ids as $i => $id ) {
			$p = Property::from( $id );
			if ( $p ) {
				$out .= self::card( $p, array_merge( $o, array( 'eager' => ! empty( $o['eager_first'] ) && 0 === $i ) ) );
			}
		}
		return $out;
	}

	private static function options( array $opts, $current, string $any ): string {
		$o = '<option value="">' . esc_html( $any ) . '</option>';
		foreach ( $opts as $v => $l ) {
			$o .= '<option value="' . esc_attr( (string) $v ) . '"' . selected( (string) $current, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $o;
	}

	public static function price_options(): array {
		$o = array();
		foreach ( Search::PRICE_STEPS as $s ) {
			$o[ $s ] = Property::money( $s );
		}
		return $o;
	}

	/** Seletor de localização: concelhos com freguesias (só as que têm imóveis). */
	public static function location_select( string $name, string $current, ?array $counts = null, string $id = 'ppi-f-local' ): string {
		$o = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"><option value="">' . esc_html__( 'Todas as localizações', 'ppi-core' ) . '</option>';
		foreach ( Search::locations_tree() as $node ) {
			$t = $node['term'];
			$c = null === $counts ? $node['count'] : ( $counts[ $t->slug ] ?? 0 );
			if ( ! $c && $current !== $t->slug ) {
				continue;
			}
			$o .= '<optgroup label="' . esc_attr( $t->name ) . '">';
			/* translators: 1: municipality, 2: count */
			$o .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $current, $t->slug, false ) . '>' . esc_html( sprintf( __( '%1$s — todo o concelho (%2$d)', 'ppi-core' ), $t->name, $c ) ) . '</option>';
			foreach ( $node['children'] as $ch ) {
				$cc = null === $counts ? $ch['count'] : ( $counts[ $ch['term']->slug ] ?? 0 );
				if ( ! $cc && $current !== $ch['term']->slug ) {
					continue;
				}
				$o .= '<option value="' . esc_attr( $ch['term']->slug ) . '"' . selected( $current, $ch['term']->slug, false ) . '>' . esc_html( sprintf( '%s (%d)', $ch['term']->name, $cc ) ) . '</option>';
			}
			$o .= '</optgroup>';
		}
		return $o . '</select>';
	}

	public static function type_options( ?array $counts, string $current ): array {
		$opts = array();
		foreach ( get_terms( array( 'taxonomy' => 'ppi_type', 'hide_empty' => false ) ) as $t ) {
			$c = $counts[ $t->slug ] ?? 0;
			if ( null === $counts || $c || $current === $t->slug ) {
				$opts[ $t->slug ] = null === $counts ? Property::term_label( $t ) : sprintf( '%s (%d)', Property::term_label( $t ), $c );
			}
		}
		return $opts;
	}

	/** Pesquisa simples da abertura: localização, tipo e intervalo de preço. */
	public static function quick_search(): string {
		$counts = array();
		foreach ( Search::index() as $r ) {
			$counts[ $r['type'] ] = ( $counts[ $r['type'] ] ?? 0 ) + 1;
		}
		$types = array();
		foreach ( get_terms( array( 'taxonomy' => 'ppi_type', 'hide_empty' => false ) ) as $t ) {
			if ( ! empty( $counts[ $t->slug ] ) ) {
				$types[ $t->slug ] = Property::term_label( $t );
			}
		}
		ob_start();
		?>
		<form class="ppi-quick" action="<?php echo esc_url( I18n::archive_url() ); ?>" method="get" role="search" aria-label="<?php esc_attr_e( 'Pesquisar imóveis', 'ppi-core' ); ?>">
			<div class="ppi-field">
				<label for="ppi-q-local"><?php esc_html_e( 'Localização', 'ppi-core' ); ?></label>
				<?php echo self::location_select( 'local', '', null, 'ppi-q-local' ); // phpcs:ignore ?>
			</div>
			<div class="ppi-field">
				<label for="ppi-q-tipo"><?php esc_html_e( 'Tipo de imóvel', 'ppi-core' ); ?></label>
				<select name="tipo" id="ppi-q-tipo"><?php echo self::options( $types, '', __( 'Todos os tipos', 'ppi-core' ) ); // phpcs:ignore ?></select>
			</div>
			<fieldset class="ppi-field ppi-field--range">
				<legend><?php esc_html_e( 'Preço', 'ppi-core' ); ?></legend>
				<label class="screen-reader-text" for="ppi-q-pmin"><?php esc_html_e( 'Preço mínimo', 'ppi-core' ); ?></label>
				<select name="preco_min" id="ppi-q-pmin"><?php echo self::options( self::price_options(), '', __( 'Mínimo', 'ppi-core' ) ); // phpcs:ignore ?></select>
				<label class="screen-reader-text" for="ppi-q-pmax"><?php esc_html_e( 'Preço máximo', 'ppi-core' ); ?></label>
				<select name="preco_max" id="ppi-q-pmax"><?php echo self::options( self::price_options(), '', __( 'Máximo', 'ppi-core' ) ); // phpcs:ignore ?></select>
			</fieldset>
			<button type="submit" class="ppi-btn ppi-btn--primary"><?php esc_html_e( 'Ver imóveis', 'ppi-core' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** Formulário de filtros completo da listagem (GET, funciona sem JavaScript). */
	public static function filters( array $res ): string {
		$p = $res['params'];
		$f = $res['facets'];
		$beds = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			if ( ! empty( $f['quartos'][ $i ] ) || $p['quartos'] === $i ) {
				/* translators: 1: bedrooms, 2: count */
				$beds[ $i ] = sprintf( __( '%1$d ou mais (%2$d)', 'ppi-core' ), $i, $f['quartos'][ $i ] ?? 0 );
			}
		}
		$feat_terms = array();
		foreach ( get_terms( array( 'taxonomy' => 'ppi_feature', 'hide_empty' => false ) ) as $t ) {
			$c = $f['carac'][ $t->slug ] ?? 0;
			if ( $c || in_array( $t->slug, $p['carac'], true ) ) {
				$feat_terms[] = array( $t, $c );
			}
		}
		$area = array();
		foreach ( Search::AREA_STEPS as $s ) {
			$area[ $s ] = Property::area_fmt( $s );
		}
		$land = array();
		foreach ( Search::LAND_STEPS as $s ) {
			$land[ $s ] = Property::area_fmt( $s );
		}
		ob_start();
		?>
		<form class="ppi-filters" id="ppi-filters" action="<?php echo esc_url( I18n::archive_url() ); ?>" method="get" role="search" aria-label="<?php esc_attr_e( 'Filtrar imóveis', 'ppi-core' ); ?>" data-ppi-filters>
			<div class="ppi-filters__head">
				<h2 class="ppi-filters__title"><?php esc_html_e( 'Filtrar', 'ppi-core' ); ?></h2>
			</div>
			<div class="ppi-field ppi-field--ref">
				<label for="ppi-f-ref"><?php esc_html_e( 'Referência', 'ppi-core' ); ?></label>
				<input type="search" name="ref" id="ppi-f-ref" value="<?php echo esc_attr( $p['ref'] ); ?>" maxlength="20" inputmode="text" autocomplete="off" placeholder="<?php esc_attr_e( 'ex.: 10450', 'ppi-core' ); ?>">
			</div>
			<div class="ppi-field">
				<label for="ppi-f-local"><?php esc_html_e( 'Localização', 'ppi-core' ); ?></label>
				<?php echo self::location_select( 'local', $p['local'], $f['local'] ); // phpcs:ignore ?>
			</div>
			<div class="ppi-field">
				<label for="ppi-f-tipo"><?php esc_html_e( 'Tipo de imóvel', 'ppi-core' ); ?></label>
				<select name="tipo" id="ppi-f-tipo"><?php echo self::options( self::type_options( $f['tipo'], $p['tipo'] ), $p['tipo'], __( 'Todos os tipos', 'ppi-core' ) ); // phpcs:ignore ?></select>
			</div>
			<fieldset class="ppi-field ppi-field--range">
				<legend><?php esc_html_e( 'Preço', 'ppi-core' ); ?></legend>
				<label for="ppi-f-pmin" class="screen-reader-text"><?php esc_html_e( 'Preço mínimo', 'ppi-core' ); ?></label>
				<select name="preco_min" id="ppi-f-pmin"><?php echo self::options( self::price_options(), $p['preco_min'], __( 'Mínimo', 'ppi-core' ) ); // phpcs:ignore ?></select>
				<label for="ppi-f-pmax" class="screen-reader-text"><?php esc_html_e( 'Preço máximo', 'ppi-core' ); ?></label>
				<select name="preco_max" id="ppi-f-pmax"><?php echo self::options( self::price_options(), $p['preco_max'], __( 'Máximo', 'ppi-core' ) ); // phpcs:ignore ?></select>
			</fieldset>
			<?php if ( $beds ) : ?>
				<div class="ppi-field">
					<label for="ppi-f-quartos"><?php esc_html_e( 'Quartos', 'ppi-core' ); ?></label>
					<select name="quartos" id="ppi-f-quartos"><?php echo self::options( $beds, $p['quartos'] ?: '', __( 'Qualquer número', 'ppi-core' ) ); // phpcs:ignore ?></select>
				</div>
			<?php endif; ?>
			<?php if ( $f['has_built'] ) : ?>
				<div class="ppi-field">
					<label for="ppi-f-area"><?php esc_html_e( 'Área construída mínima', 'ppi-core' ); ?></label>
					<select name="area_min" id="ppi-f-area"><?php echo self::options( $area, $p['area_min'] ?: '', __( 'Qualquer área', 'ppi-core' ) ); // phpcs:ignore ?></select>
				</div>
			<?php endif; ?>
			<?php if ( $f['has_land'] ) : ?>
				<div class="ppi-field">
					<label for="ppi-f-terreno"><?php esc_html_e( 'Área de terreno mínima', 'ppi-core' ); ?></label>
					<select name="terreno_min" id="ppi-f-terreno"><?php echo self::options( $land, $p['terreno_min'] ?: '', __( 'Qualquer área', 'ppi-core' ) ); // phpcs:ignore ?></select>
				</div>
			<?php endif; ?>
			<?php if ( $feat_terms ) : ?>
				<fieldset class="ppi-field ppi-field--checks">
					<legend><?php esc_html_e( 'Características', 'ppi-core' ); ?></legend>
					<?php foreach ( $feat_terms as [ $t, $c ] ) : ?>
						<label class="ppi-check"><input type="checkbox" name="carac[]" value="<?php echo esc_attr( $t->slug ); ?>" <?php checked( in_array( $t->slug, $p['carac'], true ) ); ?>> <span><?php echo esc_html( Property::term_label( $t ) ); ?> <span class="ppi-count">(<?php echo (int) $c; ?>)</span></span></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<?php if ( 'recentes' !== $p['ordem'] ) : ?>
				<input type="hidden" name="ordem" value="<?php echo esc_attr( $p['ordem'] ); ?>">
			<?php endif; ?>
			<div class="ppi-filters__actions">
				<button type="submit" class="ppi-btn ppi-btn--primary"><?php esc_html_e( 'Aplicar filtros', 'ppi-core' ); ?></button>
				<?php if ( Search::has_filters( $p ) ) : ?>
					<a class="ppi-btn ppi-btn--ghost" href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php esc_html_e( 'Limpar filtros', 'ppi-core' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** Lista de filtros ativos, cada um removível por ligação. */
	public static function active_filters( array $p ): string {
		$chips = array();
		$base  = array_merge( $p, array( 'pagina' => 1 ) );
		if ( $p['ref'] ) {
			/* translators: %s reference */
			$chips[] = array( sprintf( __( 'Referência: %s', 'ppi-core' ), $p['ref'] ), Search::url( $base, array( 'ref' => '' ) ) );
		}
		if ( $p['local'] ) {
			$t = get_term_by( 'slug', $p['local'], 'ppi_location' );
			$chips[] = array( $t ? $t->name : $p['local'], Search::url( $base, array( 'local' => '' ) ) );
		}
		if ( $p['tipo'] ) {
			$t = get_term_by( 'slug', $p['tipo'], 'ppi_type' );
			$chips[] = array( $t ? Property::term_label( $t ) : $p['tipo'], Search::url( $base, array( 'tipo' => '' ) ) );
		}
		if ( $p['preco_min'] ) {
			/* translators: %s price */
			$chips[] = array( sprintf( __( 'Desde %s', 'ppi-core' ), Property::money( $p['preco_min'] ) ), Search::url( $base, array( 'preco_min' => 0 ) ) );
		}
		if ( $p['preco_max'] ) {
			/* translators: %s price */
			$chips[] = array( sprintf( __( 'Até %s', 'ppi-core' ), Property::money( $p['preco_max'] ) ), Search::url( $base, array( 'preco_max' => 0 ) ) );
		}
		if ( $p['quartos'] ) {
			/* translators: %d bedrooms */
			$chips[] = array( sprintf( __( '%d+ quartos', 'ppi-core' ), $p['quartos'] ), Search::url( $base, array( 'quartos' => 0 ) ) );
		}
		if ( $p['area_min'] ) {
			/* translators: %s area */
			$chips[] = array( sprintf( __( 'Construção ≥ %s', 'ppi-core' ), Property::area_fmt( $p['area_min'] ) ), Search::url( $base, array( 'area_min' => 0 ) ) );
		}
		if ( $p['terreno_min'] ) {
			/* translators: %s area */
			$chips[] = array( sprintf( __( 'Terreno ≥ %s', 'ppi-core' ), Property::area_fmt( $p['terreno_min'] ) ), Search::url( $base, array( 'terreno_min' => 0 ) ) );
		}
		foreach ( $p['carac'] as $c ) {
			$t = get_term_by( 'slug', $c, 'ppi_feature' );
			$chips[] = array( $t ? Property::term_label( $t ) : $c, Search::url( $base, array( 'carac' => array_values( array_diff( $p['carac'], array( $c ) ) ) ) ) );
		}
		if ( ! $chips ) {
			return '';
		}
		$o = '<div class="ppi-active"><h2 class="screen-reader-text">' . esc_html__( 'Filtros ativos', 'ppi-core' ) . '</h2><ul class="ppi-active__list">';
		foreach ( $chips as [ $label, $url ] ) {
			/* translators: %s filter */
			$o .= '<li><a class="ppi-chip" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Remover filtro: %s', 'ppi-core' ), $label ) ) . '">' . esc_html( $label ) . self::icon( 'close' ) . '</a></li>';
		}
		$o .= '<li><a class="ppi-chip ppi-chip--clear" href="' . esc_url( I18n::archive_url() ) . '">' . esc_html__( 'Limpar todos', 'ppi-core' ) . '</a></li>';
		return $o . '</ul></div>';
	}

	public static function sort_form( array $p ): string {
		$opts = array( 'recentes' => __( 'Mais recentes', 'ppi-core' ), 'preco-asc' => __( 'Preço: do mais baixo', 'ppi-core' ), 'preco-desc' => __( 'Preço: do mais alto', 'ppi-core' ) );
		ob_start();
		?>
		<form class="ppi-sort" action="<?php echo esc_url( I18n::archive_url() ); ?>" method="get" data-ppi-sort>
			<?php
			foreach ( array( 'local', 'tipo', 'quartos', 'preco_min', 'preco_max', 'area_min', 'terreno_min', 'ref' ) as $k ) {
				if ( ! empty( $p[ $k ] ) ) {
					echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $p[ $k ] ) . '">';
				}
			}
			if ( $p['carac'] ) {
				echo '<input type="hidden" name="carac" value="' . esc_attr( implode( ',', $p['carac'] ) ) . '">';
			}
			?>
			<label for="ppi-sort"><?php esc_html_e( 'Ordenar', 'ppi-core' ); ?></label>
			<select name="ordem" id="ppi-sort"><?php foreach ( $opts as $v => $l ) { echo '<option value="' . esc_attr( $v ) . '"' . selected( $p['ordem'], $v, false ) . '>' . esc_html( $l ) . '</option>'; } ?></select>
			<noscript><button type="submit" class="ppi-btn ppi-btn--small"><?php esc_html_e( 'Ordenar', 'ppi-core' ); ?></button></noscript>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function pagination( array $res ): string {
		if ( $res['pages'] < 2 ) {
			return '';
		}
		$p   = $res['params'];
		$cur = $res['page'];
		$o   = '<nav class="ppi-pages" aria-label="' . esc_attr__( 'Páginas de resultados', 'ppi-core' ) . '"><ul>';
		if ( $cur > 1 ) {
			$o .= '<li><a rel="prev" href="' . esc_url( Search::url( $p, array( 'pagina' => $cur - 1 ) ) ) . '">' . self::icon( 'prev' ) . '<span>' . esc_html__( 'Anterior', 'ppi-core' ) . '</span></a></li>';
		}
		$range = array_unique( array_filter( array( 1, $cur - 1, $cur, $cur + 1, $res['pages'] ), static fn( $n ) => $n >= 1 && $n <= $res['pages'] ) );
		sort( $range );
		$last = 0;
		foreach ( $range as $n ) {
			if ( $n - $last > 1 ) {
				$o .= '<li class="ppi-pages__gap" aria-hidden="true">…</li>';
			}
			/* translators: %d page */
			$label = sprintf( __( 'Página %d', 'ppi-core' ), $n );
			$o .= $n === $cur
				? '<li><span aria-current="page" aria-label="' . esc_attr( $label ) . '">' . $n . '</span></li>'
				: '<li><a href="' . esc_url( Search::url( $p, array( 'pagina' => $n ) ) ) . '" aria-label="' . esc_attr( $label ) . '">' . $n . '</a></li>';
			$last = $n;
		}
		if ( $cur < $res['pages'] ) {
			$o .= '<li><a rel="next" href="' . esc_url( Search::url( $p, array( 'pagina' => $cur + 1 ) ) ) . '"><span>' . esc_html__( 'Seguinte', 'ppi-core' ) . '</span>' . self::icon( 'next' ) . '</a></li>';
		}
		return $o . '</ul></nav>';
	}

	/** Bloco de resultados (substituído por JavaScript sem recarregar a página). */
	public static function results( array $res, array $o = array() ): string {
		$p = $res['params'];
		ob_start();
		?>
		<div class="ppi-results" id="ppi-results" data-ppi-results aria-busy="false">
			<div class="ppi-results__bar">
				<p class="ppi-results__count" role="status" aria-live="polite" aria-atomic="true">
					<?php
					/* translators: %s number of properties */
					echo esc_html( sprintf( _n( '%s imóvel encontrado', '%s imóveis encontrados', $res['total'], 'ppi-core' ), number_format_i18n( $res['total'] ) ) );
					if ( $res['pages'] > 1 ) {
						/* translators: 1: page, 2: pages */
						echo ' <span class="ppi-results__page">' . esc_html( sprintf( __( '· página %1$d de %2$d', 'ppi-core' ), $res['page'], $res['pages'] ) ) . '</span>';
					}
					?>
				</p>
				<?php echo self::sort_form( $p ); // phpcs:ignore ?>
			</div>
			<?php echo self::active_filters( $p ); // phpcs:ignore ?>
			<?php if ( $res['ids'] ) : ?>
				<div class="ppi-grid">
					<?php echo self::cards( $res['ids'], array( 'heading' => 'h2', 'eager_first' => true ) ); // phpcs:ignore ?>
				</div>
				<?php echo self::pagination( $res ); // phpcs:ignore ?>
			<?php else : ?>
				<div class="ppi-empty">
					<h2><?php esc_html_e( 'Não há imóveis com estes critérios.', 'ppi-core' ); ?></h2>
					<p><?php esc_html_e( 'Experimente remover um dos filtros acima. Se procura algo específico, deixe-nos o pedido e avisamos quando surgir um imóvel adequado.', 'ppi-core' ); ?></p>
					<p><a class="ppi-btn ppi-btn--ghost" href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php esc_html_e( 'Ver todos os imóveis', 'ppi-core' ); ?></a> <a class="ppi-btn ppi-btn--primary" href="<?php echo esc_url( I18n::page_url( 'comprar' ) ); ?>"><?php esc_html_e( 'Deixar pedido de procura', 'ppi-core' ); ?></a></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function breadcrumbs( array $items ): string {
		$o = '<nav class="ppi-crumbs" aria-label="' . esc_attr__( 'Localização na página', 'ppi-core' ) . '"><ol>';
		$n = count( $items );
		foreach ( array_values( $items ) as $i => [ $label, $url ] ) {
			$o .= '<li>' . ( $url && $i < $n - 1 ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '<span' . ( $i === $n - 1 ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</span>' ) . '</li>';
		}
		return $o . '</ol></nav>';
	}

	public static function property_crumbs( Property $p ): array {
		[ $m, $par ] = $p->locations();
		$items = array( array( __( 'Início', 'ppi-core' ), I18n::home_url() ), array( __( 'Imóveis', 'ppi-core' ), I18n::archive_url() ) );
		if ( $m ) {
			$items[] = array( $m->name, Search::url( Search::params( array() ), array( 'local' => $m->slug ) ) );
		}
		if ( $par ) {
			$items[] = array( $par->name, Search::url( Search::params( array() ), array( 'local' => $par->slug ) ) );
		}
		/* translators: %s reference */
		$items[] = array( sprintf( __( 'Ref. %s', 'ppi-core' ), $p->reference() ), '' );
		return $items;
	}

	/** Galeria da ficha: imagem principal ampla + miniaturas; lightbox acessível (diálogo nativo). */
	public static function gallery( Property $p ): string {
		$ids = $p->gallery();
		if ( ! $ids ) {
			return '<div class="ppi-gallery ppi-gallery--empty"><p>' . esc_html__( 'Fotografias indisponíveis para este imóvel. Peça-nos mais informação.', 'ppi-core' ) . '</p></div>';
		}
		_prime_post_caches( $ids, false, true );
		$n = count( $ids );
		ob_start();
		?>
		<section class="ppi-gallery" aria-label="<?php esc_attr_e( 'Fotografias', 'ppi-core' ); ?>" data-ppi-gallery>
			<div class="ppi-gallery__grid ppi-gallery__grid--<?php echo (int) min( $n, 5 ); ?>">
				<?php foreach ( array_slice( $ids, 0, 5 ) as $i => $id ) : ?>
					<a class="ppi-gallery__item<?php echo 0 === $i ? ' ppi-gallery__item--main' : ''; ?>" href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-ppi-open="<?php echo (int) $i; ?>">
						<?php echo self::image( $id, 0 === $i ? '(min-width: 1100px) 62vw, 100vw' : '(min-width: 1100px) 18vw, 50vw', array( 'alt' => $p->image_alt( $id, $i ), 'loading' => 0 === $i ? 'eager' : 'lazy', 'fetchpriority' => 0 === $i ? 'high' : 'auto' ), 0 === $i ? 'full' : 'medium_large' ); // phpcs:ignore ?>
						<?php if ( $ml = self::media_label( $id ) ) : ?><span class="ppi-tag ppi-tag--media ppi-gallery__label"><?php echo esc_html( $ml ); ?></span><?php endif; ?>
						<?php if ( 4 === $i && $n > 5 ) : ?>
							<span class="ppi-gallery__more" aria-hidden="true">+<?php echo (int) ( $n - 5 ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
			<button type="button" class="ppi-btn ppi-btn--overlay ppi-gallery__all" data-ppi-open="0" hidden data-ppi-js>
				<?php echo self::icon( 'grid' ); // phpcs:ignore ?>
				<?php /* translators: %d photos */ echo esc_html( sprintf( _n( 'Ver a fotografia', 'Ver todas as fotografias (%d)', $n, 'ppi-core' ), $n ) ); ?>
			</button>
			<noscript><p class="ppi-gallery__nojs"><?php esc_html_e( 'Clique numa fotografia para a abrir em tamanho completo.', 'ppi-core' ); ?></p></noscript>
			<dialog class="ppi-lightbox" aria-label="<?php /* translators: %s title */ echo esc_attr( sprintf( __( 'Fotografias: %s', 'ppi-core' ), $p->title() ) ); ?>" data-ppi-lightbox>
				<div class="ppi-lightbox__bar">
					<p class="ppi-lightbox__count" aria-live="polite"><span data-ppi-current>1</span> / <?php echo (int) $n; ?></p>
					<button type="button" class="ppi-lightbox__close" data-ppi-close aria-label="<?php esc_attr_e( 'Fechar fotografias', 'ppi-core' ); ?>"><?php echo self::icon( 'close' ); // phpcs:ignore ?></button>
				</div>
				<ul class="ppi-lightbox__track" data-ppi-track>
					<?php foreach ( $ids as $i => $id ) : ?>
						<?php $src = wp_get_attachment_image_src( $id, 'full' ); ?>
						<li class="ppi-lightbox__slide" data-index="<?php echo (int) $i; ?>" <?php echo $i ? 'hidden' : ''; ?>>
							<figure>
								<img data-src="<?php echo esc_url( $src[0] ?? '' ); ?>" data-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $id, 'full' ) ); ?>" sizes="100vw" width="<?php echo (int) ( $src[1] ?? 0 ); ?>" height="<?php echo (int) ( $src[2] ?? 0 ); ?>" alt="<?php echo esc_attr( $p->image_alt( $id, $i ) ); ?>">
								<?php if ( $ml = self::media_label( $id ) ) : ?><figcaption class="ppi-lightbox__label"><?php echo esc_html( $ml ); ?></figcaption><?php endif; ?>
							</figure>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="button" class="ppi-lightbox__nav ppi-lightbox__nav--prev" data-ppi-prev aria-label="<?php esc_attr_e( 'Fotografia anterior', 'ppi-core' ); ?>"><?php echo self::icon( 'prev' ); // phpcs:ignore ?></button>
				<button type="button" class="ppi-lightbox__nav ppi-lightbox__nav--next" data-ppi-next aria-label="<?php esc_attr_e( 'Fotografia seguinte', 'ppi-core' ); ?>"><?php echo self::icon( 'next' ); // phpcs:ignore ?></button>
			</dialog>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function property_header( Property $p ): string {
		ob_start();
		?>
		<header class="ppi-prop-head">
			<p class="ppi-prop-head__loc"><?php echo self::icon( 'pin' ); // phpcs:ignore ?><span><?php echo esc_html( $p->location_label() ); ?></span></p>
			<h1 class="ppi-prop-head__title"><?php echo esc_html( $p->title() ); ?></h1>
			<div class="ppi-prop-head__meta">
				<p class="ppi-prop-head__price"><?php echo esc_html( $p->price_label() ); ?></p>
				<p class="ppi-prop-head__ref">
					<?php /* translators: %s reference */ printf( esc_html__( 'Ref. %s', 'ppi-core' ), esc_html( $p->reference() ) ); ?>
					<?php if ( 'available' !== $p->status() ) : ?><span class="ppi-tag ppi-tag--status"><?php echo esc_html( $p->status_label() ); ?></span><?php endif; ?>
					<?php if ( $p->get( 'badge' ) && 'pt' === I18n::current() ) : ?><span class="ppi-tag"><?php echo esc_html( $p->get( 'badge' ) ); ?></span><?php endif; ?>
				</p>
				<?php echo self::save_buttons( $p, true ); // phpcs:ignore ?>
			</div>
		</header>
		<?php
		return (string) ob_get_clean();
	}

	public static function property_summary( Property $p ): string {
		$facts = $p->key_facts();
		ob_start();
		?>
		<section class="ppi-summary" aria-labelledby="ppi-summary-h">
			<h2 id="ppi-summary-h" class="ppi-section-title"><?php esc_html_e( 'Resumo', 'ppi-core' ); ?></h2>
			<?php if ( $facts ) : ?>
				<ul class="ppi-summary__key">
					<?php foreach ( $facts as [ $k, $label, $value ] ) : ?>
						<li><span class="ppi-summary__label"><?php echo esc_html( $label ); ?></span><span class="ppi-summary__value"><?php echo esc_html( $value ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<dl class="ppi-summary__table">
				<?php foreach ( $p->summary() as [ $label, $value ] ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( (string) $value ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<?php if ( $p->is_land() ) : ?>
				<p class="ppi-note"><?php esc_html_e( 'A classificação do solo e a possibilidade de construção dependem de confirmação junto da câmara municipal e dos instrumentos de ordenamento em vigor. Peça-nos a documentação disponível.', 'ppi-core' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function property_description( Property $p ): string {
		$content = trim( $p->post->post_content );
		if ( ! $content ) {
			return '';
		}
		$lang = get_post_meta( $p->id, '_ppi_content_lang', true );
		ob_start();
		?>
		<section class="ppi-desc" aria-labelledby="ppi-desc-h"<?php echo $lang ? ' lang="' . esc_attr( I18n::locale_attr( $lang ) ) . '"' : ''; ?>>
			<h2 id="ppi-desc-h" class="ppi-section-title"><?php esc_html_e( 'Descrição', 'ppi-core' ); ?></h2>
			<div class="ppi-desc__body"><?php echo wp_kses_post( wpautop( $content ) ); ?></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function property_features( Property $p ): string {
		$groups = $p->feature_groups();
		if ( ! $groups ) {
			return '';
		}
		ob_start();
		?>
		<section class="ppi-features" aria-labelledby="ppi-feat-h">
			<h2 id="ppi-feat-h" class="ppi-section-title"><?php esc_html_e( 'Características', 'ppi-core' ); ?></h2>
			<div class="ppi-features__groups">
				<?php foreach ( $groups as $g => $items ) : ?>
					<div class="ppi-features__group">
						<h3><?php echo esc_html( $g ); ?></h3>
						<ul>
							<?php foreach ( $items as $it ) : ?>
								<li<?php echo 'pt' === $it['lang'] && 'en' === I18n::current() ? ' lang="pt-PT"' : ''; ?>><?php echo esc_html( $it['label'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="ppi-note"><?php esc_html_e( 'Características indicadas pela agência com base na informação do proprietário. Confirme os pontos decisivos durante a visita.', 'ppi-core' ); ?></p>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function property_media( Property $p ): string {
		$plans = $p->floorplans();
		$video = (string) $p->get( 'video_url' );
		$tour  = (string) $p->get( 'tour_url' );
		if ( ! $plans && ! $video && ! $tour ) {
			return '';
		}
		ob_start();
		?>
		<section class="ppi-media" aria-labelledby="ppi-media-h">
			<h2 id="ppi-media-h" class="ppi-section-title"><?php echo esc_html( $plans ? __( 'Plantas e vídeo', 'ppi-core' ) : __( 'Vídeo e visita', 'ppi-core' ) ); ?></h2>
			<?php if ( $plans ) : ?>
				<div class="ppi-media__plans">
					<?php foreach ( $plans as $i => $id ) : ?>
						<figure>
							<a href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>"><?php echo self::image( $id, '(min-width: 900px) 40vw, 100vw', array( /* translators: %d plan number */ 'alt' => sprintf( __( 'Planta %d', 'ppi-core' ), $i + 1 ), 'style' => '' ), 'large' ); // phpcs:ignore ?></a>
							<figcaption><?php /* translators: %d plan number */ echo esc_html( sprintf( __( 'Planta %d — abrir em tamanho completo', 'ppi-core' ), $i + 1 ) ); ?></figcaption>
						</figure>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $video ) : ?>
				<?php echo Consent::embed( $video, __( 'Vídeo do imóvel', 'ppi-core' ) ); // phpcs:ignore ?>
			<?php endif; ?>
			<?php if ( $tour ) : ?>
				<p><a class="ppi-btn ppi-btn--ghost" href="<?php echo esc_url( $tour ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Abrir visita virtual (nova janela)', 'ppi-core' ); ?></a></p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function property_location( Property $p ): string {
		[ $m, $par ] = $p->locations();
		if ( ! $m && ! $par ) {
			return '';
		}
		$coords = $p->public_coordinates();
		$prec   = $p->get( 'location_precision' ) ?: 'parish';
		$intro  = '';
		foreach ( array( $par, $m ) as $t ) {
			if ( $t && ! $intro ) {
				$intro = (string) get_term_meta( $t->term_id, 'ppi_intro_' . I18n::current(), true );
				$intro_term = $t;
			}
		}
		ob_start();
		?>
		<section class="ppi-location" aria-labelledby="ppi-loc-h">
			<h2 id="ppi-loc-h" class="ppi-section-title"><?php esc_html_e( 'Localização', 'ppi-core' ); ?></h2>
			<p class="ppi-location__line"><?php echo self::icon( 'pin' ); // phpcs:ignore ?> <strong><?php echo esc_html( $p->location_label() ); ?></strong>, <?php esc_html_e( 'distrito de Faro', 'ppi-core' ); ?></p>
			<?php if ( 'exact' !== $prec ) : ?>
				<p class="ppi-note"><?php esc_html_e( 'Localização aproximada (freguesia). A localização exata é partilhada pela agência no contacto ou na visita.', 'ppi-core' ); ?></p>
			<?php endif; ?>
			<?php if ( $coords && Settings::get( 'map_provider' ) === 'osm' ) : ?>
				<?php echo Consent::map( $coords, 'exact' !== $prec ); // phpcs:ignore ?>
			<?php endif; ?>
			<?php if ( $intro ) : ?>
				<div class="ppi-location__intro"><?php echo wp_kses_post( wpautop( wp_trim_words( $intro, 70 ) ) ); ?></div>
				<p><a href="<?php echo esc_url( get_term_link( $intro_term ) ); ?>"><?php /* translators: %s place */ echo esc_html( sprintf( __( 'Conhecer %s', 'ppi-core' ), $intro_term->name ) ); ?></a></p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Coluna de contacto (sticky em desktop) com formulário de pedido de visita / informações. */
	public static function property_contact( Property $p ): string {
		$agent = $p->agent();
		$s     = Settings::all();
		ob_start();
		?>
		<aside class="ppi-contact" id="contactar" aria-labelledby="ppi-contact-h">
			<div class="ppi-contact__inner">
				<p class="ppi-contact__price"><?php echo esc_html( $p->price_label() ); ?></p>
				<h2 id="ppi-contact-h" class="ppi-contact__title"><?php esc_html_e( 'Falar com a PPI sobre este imóvel', 'ppi-core' ); ?></h2>
				<?php if ( 'sold' === $p->status() ) : ?>
					<p><?php esc_html_e( 'Este imóvel já foi vendido. Podemos ajudar a encontrar uma alternativa.', 'ppi-core' ); ?></p>
				<?php endif; ?>
				<?php echo Leads::form( 'sold' === $p->status() ? 'search' : 'visit', array( 'property' => $p ) ); // phpcs:ignore ?>
				<div class="ppi-contact__direct">
					<p class="ppi-contact__who"><?php echo esc_html( $agent ? $agent['name'] : $s['agency_name'] ); ?></p>
					<?php if ( $s['phone_landline'] ) : ?>
						<p><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $s['phone_landline'] ) ); ?>"><?php echo self::icon( 'phone' ); // phpcs:ignore ?> <?php echo esc_html( $s['phone_landline'] ); ?></a> <span class="ppi-note"><?php esc_html_e( '(rede fixa nacional)', 'ppi-core' ); ?></span></p>
					<?php endif; ?>
					<?php if ( $s['phone_mobile'] ) : ?>
						<p><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $s['phone_mobile'] ) ); ?>"><?php echo self::icon( 'phone' ); // phpcs:ignore ?> <?php echo esc_html( $s['phone_mobile'] ); ?></a> <span class="ppi-note"><?php esc_html_e( '(rede móvel nacional)', 'ppi-core' ); ?></span></p>
					<?php endif; ?>
					<?php if ( $s['whatsapp'] ) : ?>
						<?php /* translators: %s reference */ $wa_text = sprintf( __( 'Olá, tenho interesse no imóvel ref. %s', 'ppi-core' ), $p->reference() ); ?>
						<p><a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $s['whatsapp'] ) . '?text=' . rawurlencode( $wa_text ) ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'WhatsApp', 'ppi-core' ); ?></a></p>
					<?php endif; ?>
					<p><a href="mailto:<?php echo esc_attr( antispambot( $s['email'] ) ); ?>?subject=<?php echo rawurlencode( sprintf( 'Ref. %s', $p->reference() ) ); ?>"><?php echo self::icon( 'mail' ); // phpcs:ignore ?> <?php echo esc_html( antispambot( $s['email'] ) ); ?></a></p>
				</div>
			</div>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

	/** Barra inferior móvel: pedir visita / contactar. */
	public static function mobile_bar( Property $p ): string {
		$s = Settings::all();
		$tel = $s['phone_mobile'] ?: $s['phone_landline'];
		ob_start();
		?>
		<div class="ppi-mbar" data-ppi-mbar>
			<p class="ppi-mbar__price"><?php echo esc_html( $p->price_label() ); ?></p>
			<a class="ppi-btn ppi-btn--primary" href="#contactar" data-ppi-goto-form><?php esc_html_e( 'Pedir visita', 'ppi-core' ); ?></a>
			<?php if ( $tel ) : ?>
				<a class="ppi-btn ppi-btn--ghost" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $tel ) ); ?>"><?php esc_html_e( 'Ligar', 'ppi-core' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function similar( Property $p ): string {
		$sim = Search::similar( $p );
		if ( ! $sim['ids'] ) {
			return '';
		}
		ob_start();
		?>
		<section class="ppi-similar" aria-labelledby="ppi-sim-h">
			<div class="ppi-section-head">
				<h2 id="ppi-sim-h" class="ppi-section-title"><?php esc_html_e( 'Imóveis semelhantes', 'ppi-core' ); ?></h2>
				<p class="ppi-section-sub"><?php echo esc_html( $sim['criteria'] ); ?></p>
			</div>
			<div class="ppi-grid ppi-grid--3"><?php echo self::cards( $sim['ids'], array( 'heading' => 'h3' ) ); // phpcs:ignore ?></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Página de imóvel indisponível (retirado): estado 410 com caminho útil. */
	public static function unavailable( Property $p ): string {
		ob_start();
		?>
		<div class="ppi-unavailable">
			<h1><?php esc_html_e( 'Este imóvel já não está disponível', 'ppi-core' ); ?></h1>
			<p><?php /* translators: %s reference */ printf( esc_html__( 'O anúncio ref. %s foi retirado do mercado.', 'ppi-core' ), esc_html( $p->reference() ) ); ?></p>
			<p><a class="ppi-btn ppi-btn--primary" href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php esc_html_e( 'Ver imóveis disponíveis', 'ppi-core' ); ?></a></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
