<?php
/**
 * Blocos dinâmicos (render no servidor). Usados nos templates do tema e nas páginas editáveis.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	public static function defs(): array {
		return array(
			'hero'          => array( 'title' => __( 'PPI — Abertura com imóvel', 'ppi-core' ), 'attributes' => array( 'heading' => array( 'type' => 'string', 'default' => '' ), 'text' => array( 'type' => 'string', 'default' => '' ) ) ),
			'featured'      => array( 'title' => __( 'PPI — Seleção PPI (destaques)', 'ppi-core' ), 'attributes' => array( 'heading' => array( 'type' => 'string', 'default' => '' ), 'text' => array( 'type' => 'string', 'default' => '' ) ) ),
			'locations'     => array( 'title' => __( 'PPI — Explorar a região', 'ppi-core' ), 'attributes' => array( 'heading' => array( 'type' => 'string', 'default' => '' ), 'text' => array( 'type' => 'string', 'default' => '' ), 'limit' => array( 'type' => 'number', 'default' => 4 ) ) ),
			'latest'        => array( 'title' => __( 'PPI — Novidades', 'ppi-core' ), 'attributes' => array( 'heading' => array( 'type' => 'string', 'default' => '' ), 'limit' => array( 'type' => 'number', 'default' => 6 ) ) ),
			'catalog'       => array( 'title' => __( 'PPI — Catálogo com filtros', 'ppi-core' ), 'attributes' => array() ),
			'property'      => array( 'title' => __( 'PPI — Ficha do imóvel', 'ppi-core' ), 'attributes' => array() ),
			'location-head' => array( 'title' => __( 'PPI — Cabeçalho de localidade', 'ppi-core' ), 'attributes' => array() ),
			'locations-index' => array( 'title' => __( 'PPI — Índice de localizações', 'ppi-core' ), 'attributes' => array() ),
			'lead-form'     => array( 'title' => __( 'PPI — Formulário', 'ppi-core' ), 'attributes' => array( 'type' => array( 'type' => 'string', 'default' => 'contact' ) ) ),
			'favorites'     => array( 'title' => __( 'PPI — Favoritos', 'ppi-core' ), 'attributes' => array() ),
			'compare'       => array( 'title' => __( 'PPI — Comparação', 'ppi-core' ), 'attributes' => array() ),
			'agency'        => array( 'title' => __( 'PPI — Dados da agência', 'ppi-core' ), 'attributes' => array( 'variant' => array( 'type' => 'string', 'default' => 'full' ) ) ),
			'team'          => array( 'title' => __( 'PPI — Equipa', 'ppi-core' ), 'attributes' => array() ),
			'quick-search'  => array( 'title' => __( 'PPI — Pesquisa rápida', 'ppi-core' ), 'attributes' => array() ),
			'breadcrumbs'   => array( 'title' => __( 'PPI — Caminho', 'ppi-core' ), 'attributes' => array() ),
		);
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', static function () {
			wp_enqueue_script( 'ppi-blocks-editor', PPI_CORE_URL . 'assets/blocks-editor.js', array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n' ), PPI_CORE_VERSION, true );
			$list = array();
			foreach ( self::defs() as $k => $d ) {
				$list[] = array( 'name' => 'ppi/' . $k, 'title' => $d['title'], 'attributes' => $d['attributes'] );
			}
			wp_localize_script( 'ppi-blocks-editor', 'ppiBlocks', array( 'blocks' => $list, 'formTypes' => Leads::types() ) );
		} );
		add_filter( 'block_categories_all', static function ( $cats ) {
			array_unshift( $cats, array( 'slug' => 'ppi', 'title' => 'PPI' ) );
			return $cats;
		} );
	}

	public static function register(): void {
		foreach ( self::defs() as $k => $d ) {
			register_block_type( 'ppi/' . $k, array(
				'api_version'     => 3,
				'title'           => $d['title'],
				'category'        => 'ppi',
				'attributes'      => $d['attributes'],
				'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
				'render_callback' => static fn( $atts, $content, $block ) => self::render( $k, (array) $atts, $block ),
			) );
		}
	}

	private static function render( string $k, array $a, $block ): string {
		$m = 'render_' . str_replace( '-', '_', $k );
		return method_exists( __CLASS__, $m ) ? (string) self::$m( $a, $block ) : '';
	}

	private static function context_property( $block ): ?Property {
		$id = $block->context['postId'] ?? get_the_ID();
		return Property::from( $id );
	}

	public static function hero_property(): ?Property {
		$ref = Settings::get( 'hero_reference' );
		$idx = Search::index();
		$id  = 0;
		if ( $ref ) {
			foreach ( $idx as $r ) {
				if ( Fields::normalize_reference( $r['ref'] ) === Fields::normalize_reference( $ref ) ) {
					$id = $r['id'];
					break;
				}
			}
		}
		if ( ! $id ) {
			$feat = array_filter( $idx, static fn( $r ) => $r['feat'] > 0 );
			usort( $feat, static fn( $a, $b ) => $a['feat'] <=> $b['feat'] );
			$id = $feat[0]['id'] ?? 0;
		}
		return $id ? Property::from( $id ) : null;
	}

	private static function render_hero( array $a ): string {
		$p       = self::hero_property();
		$heading = $a['heading'] ?: __( 'Encontre o seu lugar no Algarve.', 'ppi-core' );
		$text    = $a['text'] ?: __( 'Explore imóveis e descubra a região com a PPI.', 'ppi-core' );
		$img     = $p ? (int) ( Settings::get( 'hero_image' ) ?: ( $p->gallery()[0] ?? 0 ) ) : 0;
		if ( $img && $p && ( ! in_array( $img, $p->gallery(), true ) || get_post_meta( $img, '_ppi_media_kind', true ) ) ) {
			// A abertura usa sempre uma fotografia real do imóvel identificado (nunca render nem planta).
			$img = 0;
			foreach ( $p->gallery() as $g ) {
				if ( ! get_post_meta( $g, '_ppi_media_kind', true ) ) {
					$img = $g;
					break;
				}
			}
		}
		$total = count( Search::index() );
		ob_start();
		?>
		<section class="ppi-hero alignfull" aria-labelledby="ppi-hero-h">
			<div class="ppi-hero__text">
				<h1 id="ppi-hero-h" class="ppi-hero__title"><?php echo esc_html( $heading ); ?></h1>
				<p class="ppi-hero__lead"><?php echo esc_html( $text ); ?></p>
				<?php echo Render::quick_search(); // phpcs:ignore ?>
				<p class="ppi-hero__count"><a href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php /* translators: %s count */ echo esc_html( sprintf( _n( 'Ver o imóvel disponível', 'Ver os %s imóveis disponíveis', $total, 'ppi-core' ), number_format_i18n( $total ) ) ); ?></a></p>
			</div>
			<?php if ( $p && $img ) : ?>
				<figure class="ppi-hero__media">
					<a href="<?php echo esc_url( $p->url() ); ?>" class="ppi-hero__imglink" tabindex="-1" aria-hidden="true">
						<?php echo Render::image( $img, '(min-width: 960px) 64vw, 100vw', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => $p->image_alt( $img, 0 ) ), 'full' ); // phpcs:ignore ?>
					</a>
					<figcaption class="ppi-hero__caption">
						<a href="<?php echo esc_url( $p->url() ); ?>"><span class="ppi-hero__cap-title"><?php echo esc_html( $p->title() ); ?></span></a>
						<span class="ppi-hero__cap-meta"><?php echo esc_html( $p->location_label() ); ?> · <?php echo esc_html( $p->price_label() ); ?> · <?php /* translators: %s ref */ printf( esc_html__( 'Ref. %s', 'ppi-core' ), esc_html( $p->reference() ) ); ?></span>
					</figcaption>
				</figure>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_quick_search(): string {
		return Render::quick_search();
	}

	/** Seleção PPI: uma propriedade protagonista + secundárias, escolhidas no backoffice (ordem de destaque). */
	private static function render_featured( array $a ): string {
		$hero = self::hero_property();
		$feat = array_filter( Search::index(), static fn( $r ) => $r['feat'] > 0 && ( ! $hero || $r['id'] !== $hero->id ) );
		usort( $feat, static fn( $x, $y ) => $x['feat'] <=> $y['feat'] );
		$ids = array_slice( wp_list_pluck( $feat, 'id' ), 0, 5 );
		if ( ! $ids ) {
			return '';
		}
		Render::prime( $ids );
		$lead = Property::from( array_shift( $ids ) );
		ob_start();
		?>
		<section class="ppi-selection" aria-labelledby="ppi-sel-h">
			<div class="ppi-section-head">
				<p class="ppi-kicker"><?php esc_html_e( 'Seleção PPI', 'ppi-core' ); ?></p>
				<h2 id="ppi-sel-h" class="ppi-section-title"><?php echo esc_html( $a['heading'] ?: __( 'Casas e terrenos escolhidos pela equipa', 'ppi-core' ) ); ?></h2>
				<?php if ( $a['text'] ) : ?><p class="ppi-section-sub"><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>
			</div>
			<div class="ppi-selection__layout">
				<article class="ppi-feature" data-ref="<?php echo esc_attr( $lead->reference() ); ?>">
					<?php $img = $lead->gallery()[0] ?? 0; ?>
					<?php if ( $img ) : ?>
						<div class="ppi-feature__media"><?php echo Render::image( $img, '(min-width: 1000px) 58vw, 100vw', array( 'alt' => '' ), 'full' ); // phpcs:ignore ?></div>
					<?php endif; ?>
					<div class="ppi-feature__body">
						<p class="ppi-card__loc"><?php echo esc_html( $lead->location_label() ); ?></p>
						<h3 class="ppi-feature__title"><a class="ppi-card__link" href="<?php echo esc_url( $lead->url() ); ?>"><?php echo esc_html( $lead->title() ); ?></a></h3>
						<p class="ppi-feature__excerpt"><?php echo esc_html( $lead->excerpt( 210 ) ); ?></p>
						<p class="ppi-card__price"><?php echo esc_html( $lead->price_label() ); ?></p>
						<ul class="ppi-card__facts">
							<?php foreach ( $lead->key_facts( true ) as [ $k, , , $short ] ) : ?><li><?php echo esc_html( $short ); ?></li><?php endforeach; ?>
						</ul>
						<p class="ppi-card__ref"><?php /* translators: %s ref */ printf( esc_html__( 'Ref. %s', 'ppi-core' ), esc_html( $lead->reference() ) ); ?></p>
					</div>
					<?php echo Render::save_buttons( $lead ); // phpcs:ignore ?>
				</article>
				<?php if ( $ids ) : ?>
					<div class="ppi-selection__side">
						<?php echo Render::cards( $ids, array( 'variant' => 'row', 'sizes' => '(min-width: 1000px) 16vw, 40vw' ) ); // phpcs:ignore ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Localidades com imóveis, fotografia real licenciada e texto útil (term meta). */
	private static function render_locations( array $a ): string {
		$limit = max( 1, min( 6, (int) ( $a['limit'] ?? 4 ) ) );
		$lang  = I18n::current();
		$items = array();
		foreach ( Search::locations_tree() as $node ) {
			foreach ( array_merge( array( $node ), $node['children'] ) as $n ) {
				$t = $n['term'];
				if ( get_term_meta( $t->term_id, 'ppi_image', true ) && get_term_meta( $t->term_id, 'ppi_intro_' . $lang, true ) ) {
					$items[ $t->term_id ] = $n;
				}
			}
		}
		uasort( $items, static fn( $x, $y ) => $y['count'] <=> $x['count'] );
		$items = array_slice( $items, 0, $limit, true );
		if ( ! $items ) {
			return '';
		}
		ob_start();
		?>
		<section class="ppi-places" aria-labelledby="ppi-places-h">
			<div class="ppi-section-head">
				<p class="ppi-kicker"><?php esc_html_e( 'Explorar a região', 'ppi-core' ); ?></p>
				<h2 id="ppi-places-h" class="ppi-section-title"><?php echo esc_html( $a['heading'] ?: __( 'Do barlavento à Costa Vicentina', 'ppi-core' ) ); ?></h2>
				<?php if ( $a['text'] ) : ?><p class="ppi-section-sub"><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>
			</div>
			<ul class="ppi-places__list">
				<?php foreach ( $items as $n ) : ?>
					<?php
					$t    = $n['term'];
					$img  = (int) get_term_meta( $t->term_id, 'ppi_image', true );
					$cred = (string) get_term_meta( $t->term_id, 'ppi_image_credit', true );
					$intro = (string) get_term_meta( $t->term_id, 'ppi_intro_' . $lang, true );
					?>
					<li class="ppi-place">
						<figure class="ppi-place__media">
							<?php echo Render::image( $img, '(min-width: 1000px) 25vw, (min-width: 600px) 50vw, 100vw', array( 'alt' => '' ), 'large' ); // phpcs:ignore ?>
							<?php if ( $cred ) : ?><figcaption class="ppi-credit"><?php echo wp_kses( $cred, array( 'a' => array( 'href' => array(), 'rel' => array() ) ) ); ?></figcaption><?php endif; ?>
						</figure>
						<h3 class="ppi-place__title"><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a></h3>
						<p class="ppi-place__text"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $intro ), 26 ) ); ?></p>
						<p class="ppi-place__count"><?php /* translators: %d count */ echo esc_html( sprintf( _n( '%d imóvel disponível', '%d imóveis disponíveis', $n['count'], 'ppi-core' ), $n['count'] ) ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_latest( array $a ): string {
		$limit = max( 1, min( 6, (int) ( $a['limit'] ?? 6 ) ) );
		$res   = Search::run( array_merge( Search::params( array() ), array( 'ordem' => 'recentes' ) ) );
		$ids   = array_slice( $res['ids'], 0, $limit );
		if ( ! $ids ) {
			return '';
		}
		ob_start();
		?>
		<section class="ppi-latest" aria-labelledby="ppi-latest-h">
			<div class="ppi-section-head ppi-section-head--row">
				<h2 id="ppi-latest-h" class="ppi-section-title"><?php echo esc_html( $a['heading'] ?: __( 'Novidades no catálogo', 'ppi-core' ) ); ?></h2>
				<a class="ppi-link-arrow" href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php /* translators: %d total */ echo esc_html( sprintf( __( 'Ver todos os %d imóveis', 'ppi-core' ), $res['total'] ) ); ?></a>
			</div>
			<div class="ppi-grid ppi-grid--3"><?php echo Render::cards( $ids ); // phpcs:ignore ?></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_catalog(): string {
		$p = Search::params();
		if ( is_tax( 'ppi_location' ) && ! $p['local'] ) {
			$p['local'] = get_queried_object()->slug;
		}
		$res = Search::run( $p );
		$tax = is_tax( 'ppi_location' );
		ob_start();
		?>
		<header class="ppi-catalog-head">
			<?php if ( $tax ) : ?>
				<h2 class="ppi-section-title"><?php /* translators: %s place */ echo esc_html( sprintf( __( 'Imóveis em %s', 'ppi-core' ), single_term_title( '', false ) ) ); ?></h2>
			<?php else : ?>
				<h1 class="ppi-catalog-head__title"><?php esc_html_e( 'Imóveis à venda', 'ppi-core' ); ?></h1>
				<p class="ppi-catalog-head__lead"><?php esc_html_e( 'Moradias, terrenos, quintas e apartamentos no Algarve Ocidental — de Lagos à Costa Vicentina. Pesquise por zona, tipo e preço, ou pela referência do anúncio.', 'ppi-core' ); ?></p>
				<?php if ( 'pt' !== I18n::current() ) : ?>
					<?php $pt_total = count( Search::index( I18n::DEFAULT_LANG ) ); $here = count( Search::index() ); ?>
					<?php if ( $pt_total > $here ) : ?>
						<p class="ppi-note"><?php /* translators: 1: listings in this language, 2: total, 3: link */ printf( esc_html__( '%1$d of %2$d listings are currently available in English. The full catalogue is in %3$s.', 'ppi-core' ), (int) $here, (int) $pt_total, '<a href="' . esc_url( untrailingslashit( (string) get_option( 'home' ) ) . '/imoveis/' ) . '" hreflang="pt-PT" lang="pt-PT">português</a>' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		</header>
		<div class="ppi-catalog" data-ppi-catalog>
			<button type="button" class="ppi-btn ppi-btn--ghost ppi-catalog__toggle" aria-expanded="false" aria-controls="ppi-filters" hidden data-ppi-js data-ppi-filters-toggle><?php echo Render::icon( 'filter' ); // phpcs:ignore ?> <?php esc_html_e( 'Filtros', 'ppi-core' ); ?></button>
			<div class="ppi-catalog__aside"><?php echo Render::filters( $res ); // phpcs:ignore ?></div>
			<div class="ppi-catalog__main"><?php echo Render::results( $res ); // phpcs:ignore ?></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_property( array $a, $block ): string {
		$p = self::context_property( $block );
		if ( ! $p ) {
			return '';
		}
		if ( 'withdrawn' === $p->status() ) {
			return Render::unavailable( $p );
		}
		ob_start();
		?>
		<div class="ppi-property" data-ppi-property="<?php echo esc_attr( $p->reference() ); ?>">
			<?php echo Render::breadcrumbs( Render::property_crumbs( $p ) ); // phpcs:ignore ?>
			<?php echo Render::property_header( $p ); // phpcs:ignore ?>
			<?php echo Render::gallery( $p ); // phpcs:ignore ?>
			<div class="ppi-property__layout">
				<div class="ppi-property__main">
					<?php
					echo Render::property_summary( $p ); // phpcs:ignore
					echo Render::property_description( $p ); // phpcs:ignore
					echo Render::property_features( $p ); // phpcs:ignore
					echo Render::property_media( $p ); // phpcs:ignore
					echo Render::property_location( $p ); // phpcs:ignore
					?>
				</div>
				<?php echo Render::property_contact( $p ); // phpcs:ignore ?>
			</div>
			<?php echo Render::similar( $p ); // phpcs:ignore ?>
			<?php echo Render::mobile_bar( $p ); // phpcs:ignore ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_location_head(): string {
		$t = get_queried_object();
		if ( ! $t instanceof \WP_Term ) {
			return '';
		}
		$lang  = I18n::current();
		$intro = (string) get_term_meta( $t->term_id, 'ppi_intro_' . $lang, true );
		$img   = (int) get_term_meta( $t->term_id, 'ppi_image', true );
		$cred  = (string) get_term_meta( $t->term_id, 'ppi_image_credit', true );
		$parent = $t->parent ? get_term( $t->parent, 'ppi_location' ) : null;
		$crumbs = array( array( __( 'Início', 'ppi-core' ), I18n::home_url() ), array( __( 'Localizações', 'ppi-core' ), I18n::page_url( 'localizacoes' ) ) );
		if ( $parent && ! is_wp_error( $parent ) ) {
			$crumbs[] = array( $parent->name, get_term_link( $parent ) );
		}
		$crumbs[] = array( $t->name, '' );
		ob_start();
		echo Render::breadcrumbs( $crumbs ); // phpcs:ignore
		?>
		<header class="ppi-place-head<?php echo $img ? ' ppi-place-head--img' : ''; ?>">
			<div class="ppi-place-head__text">
				<p class="ppi-kicker"><?php echo esc_html( $parent && ! is_wp_error( $parent ) ? sprintf( /* translators: %s municipality */ __( 'Concelho de %s', 'ppi-core' ), $parent->name ) : __( 'Concelho', 'ppi-core' ) ); ?></p>
				<h1 class="ppi-place-head__title"><?php echo esc_html( $t->name ); ?></h1>
				<?php if ( $intro ) : ?>
					<div class="ppi-place-head__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
				<?php elseif ( current_user_can( 'edit_others_ppi_properties' ) ) : ?>
					<p class="ppi-note"><?php esc_html_e( 'Texto editorial desta localidade por preencher (visível só para editores).', 'ppi-core' ); ?></p>
				<?php endif; ?>
				<?php if ( $intro && 'pt' !== $lang && ! get_term_meta( $t->term_id, 'ppi_intro_' . $lang . '_reviewed', true ) && current_user_can( 'edit_others_ppi_properties' ) ) : ?>
					<p class="ppi-note"><?php esc_html_e( 'Tradução por rever (visível só para editores).', 'ppi-core' ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $img ) : ?>
				<figure class="ppi-place-head__media">
					<?php echo Render::image( $img, '(min-width: 960px) 50vw, 100vw', array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high' ), 'full' ); // phpcs:ignore ?>
					<?php if ( $cred ) : ?><figcaption class="ppi-credit"><?php echo wp_kses( $cred, array( 'a' => array( 'href' => array(), 'rel' => array() ) ) ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endif; ?>
		</header>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_locations_index(): string {
		$tree = Search::locations_tree();
		$lang = I18n::current();
		ob_start();
		?>
		<div class="ppi-locidx">
			<?php foreach ( $tree as $node ) : ?>
				<?php $t = $node['term']; $img = (int) get_term_meta( $t->term_id, 'ppi_image', true ); $intro = (string) get_term_meta( $t->term_id, 'ppi_intro_' . $lang, true ); ?>
				<section class="ppi-locidx__item" aria-labelledby="loc-<?php echo esc_attr( $t->slug ); ?>">
					<?php if ( $img ) : ?>
						<figure class="ppi-locidx__media"><?php echo Render::image( $img, '(min-width: 900px) 40vw, 100vw', array( 'alt' => '' ), 'large' ); // phpcs:ignore ?>
							<?php $cred = (string) get_term_meta( $t->term_id, 'ppi_image_credit', true ); if ( $cred ) : ?><figcaption class="ppi-credit"><?php echo wp_kses( $cred, array( 'a' => array( 'href' => array(), 'rel' => array() ) ) ); ?></figcaption><?php endif; ?>
						</figure>
					<?php endif; ?>
					<div class="ppi-locidx__body">
						<h2 id="loc-<?php echo esc_attr( $t->slug ); ?>"><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a></h2>
						<?php if ( $intro ) : ?><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $intro ), 40 ) ); ?></p><?php endif; ?>
						<p class="ppi-place__count"><?php /* translators: %d count */ echo esc_html( sprintf( _n( '%d imóvel disponível', '%d imóveis disponíveis', $node['count'], 'ppi-core' ), $node['count'] ) ); ?></p>
						<?php if ( $node['children'] ) : ?>
							<ul class="ppi-locidx__children">
								<?php foreach ( $node['children'] as $ch ) : ?>
									<li><a href="<?php echo esc_url( get_term_link( $ch['term'] ) ); ?>"><?php echo esc_html( $ch['term']->name ); ?></a> <span class="ppi-count">(<?php echo (int) $ch['count']; ?>)</span></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_lead_form( array $a ): string {
		return Leads::form( (string) ( $a['type'] ?? 'contact' ) );
	}

	private static function render_favorites(): string {
		ob_start();
		?>
		<div class="ppi-saved" data-ppi-saved="favorites" data-endpoint="<?php echo esc_url( rest_url( 'ppi/v1/cards' ) ); ?>" data-lang="<?php echo esc_attr( I18n::current() ); ?>">
			<noscript><p><?php esc_html_e( 'Os favoritos ficam guardados apenas neste navegador e precisam de JavaScript ativo.', 'ppi-core' ); ?></p></noscript>
			<p class="ppi-saved__empty" hidden data-ppi-empty><?php esc_html_e( 'Ainda não guardou imóveis. Use o coração em qualquer anúncio para os juntar aqui.', 'ppi-core' ); ?> <a href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php esc_html_e( 'Ver imóveis', 'ppi-core' ); ?></a></p>
			<div class="ppi-grid ppi-grid--3" data-ppi-list aria-live="polite"></div>
			<p class="ppi-note"><?php esc_html_e( 'Os favoritos ficam guardados neste dispositivo e navegador; não é necessário criar conta.', 'ppi-core' ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Comparação até 3 imóveis. Render no servidor a partir de ?refs= (partilhável e sem JS); JS sincroniza com a seleção local. */
	private static function render_compare(): string {
		$refs = isset( $_GET['refs'] ) ? array_slice( array_filter( array_map( static fn( $r ) => Fields::sanitize( 'reference', $r )[0], explode( ',', sanitize_text_field( wp_unslash( (string) $_GET['refs'] ) ) ) ) ), 0, 3 ) : array(); // phpcs:ignore
		$props = array();
		foreach ( $refs as $r ) {
			$id = Rest::id_by_ref( $r, I18n::current() );
			if ( $id && ( $p = Property::from( $id ) ) && 'publish' === $p->post->post_status ) {
				$props[] = $p;
			}
		}
		ob_start();
		?>
		<div class="ppi-compare" data-ppi-compare-page data-base="<?php echo esc_url( get_permalink() ); ?>">
			<?php if ( ! $props ) : ?>
				<p class="ppi-saved__empty"><?php esc_html_e( 'Escolha até 3 imóveis com o botão «Comparar» nos anúncios. A seleção fica neste navegador.', 'ppi-core' ); ?> <a href="<?php echo esc_url( I18n::archive_url() ); ?>"><?php esc_html_e( 'Ver imóveis', 'ppi-core' ); ?></a></p>
			<?php else : ?>
				<?php
				$rows = array(
					'price' => __( 'Preço', 'ppi-core' ), 'location' => __( 'Localização', 'ppi-core' ), 'type' => __( 'Tipo', 'ppi-core' ),
					'bedrooms' => __( 'Quartos', 'ppi-core' ), 'bathrooms' => __( 'Casas de banho', 'ppi-core' ), 'area_useful' => __( 'Área útil', 'ppi-core' ),
					'area_gross' => __( 'Área bruta', 'ppi-core' ), 'area_land' => __( 'Área de terreno', 'ppi-core' ), 'energy_class' => __( 'Certificado energético', 'ppi-core' ),
					'year' => __( 'Ano de construção', 'ppi-core' ), 'status' => __( 'Estado', 'ppi-core' ),
				);
				$val = static function ( Property $p, string $k ): string {
					switch ( $k ) {
						case 'price': return $p->price_label();
						case 'location': return $p->location_label();
						case 'type': return $p->type_label();
						case 'status': return $p->status_label();
						case 'area_useful': case 'area_gross': case 'area_land':
							$v = $p->get( $k ); return null === $v ? '' : Property::area_fmt( $v );
						case 'bedrooms': case 'bathrooms':
							return $p->is_land() ? '' : (string) ( $p->get( $k ) ?? '' );
						default:
							return (string) ( $p->get( $k ) ?? '' );
					}
				};
				?>
				<div class="ppi-compare__scroll" role="region" aria-label="<?php esc_attr_e( 'Tabela de comparação', 'ppi-core' ); ?>" tabindex="0">
					<table class="ppi-compare__table">
						<caption class="screen-reader-text"><?php esc_html_e( 'Comparação de imóveis; as linhas com valores diferentes estão assinaladas.', 'ppi-core' ); ?></caption>
						<thead>
							<tr>
								<td></td>
								<?php foreach ( $props as $p ) : ?>
									<th scope="col">
										<?php $img = $p->gallery()[0] ?? 0; if ( $img ) { echo Render::image( $img, '240px', array( 'alt' => '' ), 'medium_large' ); } // phpcs:ignore ?>
										<a href="<?php echo esc_url( $p->url() ); ?>"><?php echo esc_html( $p->title() ); ?></a>
										<span class="ppi-card__ref"><?php /* translators: %s ref */ printf( esc_html__( 'Ref. %s', 'ppi-core' ), esc_html( $p->reference() ) ); ?></span>
										<button type="button" class="ppi-btn ppi-btn--small ppi-btn--ghost" hidden data-ppi-js data-ppi-compare-remove="<?php echo esc_attr( $p->reference() ); ?>"><?php esc_html_e( 'Remover', 'ppi-core' ); ?></button>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $k => $label ) : ?>
								<?php
								$vals = array_map( static fn( $p ) => $val( $p, $k ), $props );
								if ( ! array_filter( $vals, 'strlen' ) ) {
									continue;
								}
								$diff = count( array_unique( $vals ) ) > 1;
								?>
								<tr class="<?php echo $diff ? 'is-diff' : ''; ?>">
									<th scope="row"><?php echo esc_html( $label ); ?><?php if ( $diff ) : ?> <span class="ppi-diff"><?php esc_html_e( '(difere)', 'ppi-core' ); ?></span><?php endif; ?></th>
									<?php foreach ( $vals as $v ) : ?><td><?php echo '' === $v ? '<span class="ppi-na">' . esc_html__( 'Não indicado', 'ppi-core' ) . '</span>' : esc_html( $v ); ?></td><?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_agency( array $a ): string {
		$s = Settings::all();
		ob_start();
		?>
		<div class="ppi-agency ppi-agency--<?php echo esc_attr( sanitize_html_class( $a['variant'] ?? 'full' ) ); ?>">
			<p class="ppi-agency__name"><strong><?php echo esc_html( $s['agency_name'] ); ?></strong></p>
			<p class="ppi-agency__legal"><?php echo esc_html( $s['legal_name'] ); ?> · <?php /* translators: %s AMI */ printf( esc_html__( 'AMI %s', 'ppi-core' ), esc_html( $s['ami'] ) ); ?></p>
			<address class="ppi-agency__address"><?php echo nl2br( esc_html( $s['address'] ) ); ?></address>
			<ul class="ppi-agency__contacts">
				<?php if ( $s['phone_landline'] ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $s['phone_landline'] ) ); ?>"><?php echo esc_html( $s['phone_landline'] ); ?></a> <span class="ppi-note"><?php esc_html_e( '(chamada para a rede fixa nacional)', 'ppi-core' ); ?></span></li><?php endif; ?>
				<?php if ( $s['phone_mobile'] ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $s['phone_mobile'] ) ); ?>"><?php echo esc_html( $s['phone_mobile'] ); ?></a> <span class="ppi-note"><?php esc_html_e( '(chamada para a rede móvel nacional)', 'ppi-core' ); ?></span></li><?php endif; ?>
				<li><a href="mailto:<?php echo esc_attr( antispambot( $s['email'] ) ); ?>"><?php echo esc_html( antispambot( $s['email'] ) ); ?></a></li>
			</ul>
			<?php if ( $s['hours'] ) : ?><p class="ppi-agency__hours"><?php echo nl2br( esc_html( $s['hours'] ) ); ?></p><?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Equipa: só membros validados nas Definições. Sem dados validados, nada é mostrado. */
	private static function render_team(): string {
		$team = Settings::team();
		if ( ! $team ) {
			return current_user_can( 'ppi_settings' ) ? '<p class="ppi-note">' . esc_html__( 'Equipa oculta: nenhum membro validado em Imóveis → Definições (visível só para administradores).', 'ppi-core' ) . '</p>' : '';
		}
		$lang = I18n::current();
		ob_start();
		?>
		<ul class="ppi-team">
			<?php foreach ( $team as $m ) : ?>
				<li class="ppi-team__member">
					<?php if ( ! empty( $m['photo'] ) ) : ?><?php echo Render::image( (int) $m['photo'], '200px', array( 'alt' => '', 'class' => 'ppi-team__photo' ), 'medium' ); // phpcs:ignore ?><?php endif; ?>
					<p class="ppi-team__name"><?php echo esc_html( $m['name'] ); ?></p>
					<?php $role = $m[ 'role_' . $lang ] ?? ( $m['role_pt'] ?? '' ); if ( $role ) : ?><p class="ppi-team__role"><?php echo esc_html( $role ); ?></p><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_breadcrumbs(): string {
		if ( is_singular() && ! is_front_page() ) {
			return Render::breadcrumbs( array( array( __( 'Início', 'ppi-core' ), I18n::home_url() ), array( get_the_title(), '' ) ) );
		}
		if ( is_post_type_archive( 'ppi_property' ) ) {
			return Render::breadcrumbs( array( array( __( 'Início', 'ppi-core' ), I18n::home_url() ), array( __( 'Imóveis', 'ppi-core' ), '' ) ) );
		}
		return '';
	}
}

Blocks::init();
