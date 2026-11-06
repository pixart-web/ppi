<?php
/**
 * Tema PPI Estates — apresentação. Os dados (imóveis, pesquisa, leads) vivem no plugin ppi-core:
 * trocar de tema não apaga conteúdo. Sem o plugin, o tema mostra páginas e artigos normalmente.
 */

defined( 'ABSPATH' ) || exit;

const PPI_THEME_VERSION = '1.0.0';

add_action( 'after_setup_theme', static function () {
	load_theme_textdomain( 'ppi-estates', get_template_directory() . '/languages' );
	add_theme_support( 'menus' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/site.css' );
	remove_theme_support( 'core-block-patterns' );
	register_nav_menus( array(
		'primary' => __( 'Navegação principal', 'ppi-estates' ),
		'footer'  => __( 'Rodapé — ligações institucionais', 'ppi-estates' ),
	) );
} );

add_action( 'wp_enqueue_scripts', static function () {
	$css = get_theme_file_path( 'assets/css/site.css' );
	wp_enqueue_style( 'ppi-estates', get_theme_file_uri( 'assets/css/site.css' ), array(), PPI_THEME_VERSION . '.' . filemtime( $css ) );
	wp_enqueue_script( 'ppi-estates', get_theme_file_uri( 'assets/js/theme.js' ), array(), PPI_THEME_VERSION . '.' . filemtime( get_theme_file_path( 'assets/js/theme.js' ) ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_dequeue_style( 'wp-block-library-theme' );
} );

// Sem emojis nem scripts não usados.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );

add_action( 'wp_head', static function () {
	foreach ( array( 'source-serif-4-latin-400-normal', 'source-sans-3-latin-400-normal' ) as $f ) {
		echo '<link rel="preload" href="' . esc_url( get_theme_file_uri( "assets/fonts/$f.woff2" ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
	echo '<link rel="icon" href="' . esc_url( get_theme_file_uri( 'assets/img/icon-32.png' ) ) . '" sizes="32x32">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( get_theme_file_uri( 'assets/img/icon-180.png' ) ) . '">' . "\n";
	echo '<meta name="theme-color" content="#F5F2EC">' . "\n";
}, 1 );

/** Texto de interface do tema por chave (traduzível; templates de blocos não traduzem texto fixo). */
function ppi_theme_text( string $key ): string {
	$t = array(
		'404_title'   => __( 'Esta página não existe.', 'ppi-estates' ),
		'404_text'    => __( 'O endereço pode ter mudado com a renovação do site. Procure no catálogo ou volte ao início.', 'ppi-estates' ),
		'skip'        => __( 'Saltar para o conteúdo', 'ppi-estates' ),
		'search_none' => __( 'Sem resultados para esta pesquisa.', 'ppi-estates' ),
	);
	return $t[ $key ] ?? '';
}

function ppi_theme_home(): string {
	return function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' );
}

function ppi_theme_page( string $pt_slug ): string {
	return class_exists( 'PPI\\I18n' ) ? PPI\I18n::page_url( $pt_slug ) : home_url( '/' . $pt_slug . '/' );
}

function ppi_theme_lang(): string {
	return class_exists( 'PPI\\I18n' ) ? PPI\I18n::current() : 'pt';
}

/** Seletor de idioma: aponta para a página equivalente; sem tradução, para a secção equivalente e indica-o. */
function ppi_language_switcher(): string {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return '';
	}
	$langs = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0, 'hide_if_no_translation' => 0 ) );
	if ( ! $langs || count( $langs ) < 2 ) {
		return '';
	}
	$o = '<nav class="lang" aria-label="' . esc_attr__( 'Idioma', 'ppi-estates' ) . '"><ul>';
	foreach ( $langs as $l ) {
		$url = $l['url'];
		$note = '';
		if ( ! empty( $l['no_translation'] ) ) {
			if ( is_singular( 'ppi_property' ) || is_post_type_archive( 'ppi_property' ) || is_tax( 'ppi_location' ) ) {
				$url  = get_post_type_archive_link( 'ppi_property' );
				$lang = PLL()->model->get_language( $l['slug'] );
				$url  = $lang ? PLL()->links_model->switch_language_in_link( $url, $lang ) : $url;
				$note = 'en' === $l['slug'] ? 'This listing is not yet available in English — opens the English catalogue' : 'Este anúncio ainda não tem versão portuguesa — abre o catálogo';
			} else {
				$note = 'en' === $l['slug'] ? 'Page not yet available in English — opens the English home page' : 'Página sem versão portuguesa — abre a página inicial';
			}
		}
		$label = strtoupper( $l['slug'] );
		$o .= '<li><a href="' . esc_url( $url ) . '" hreflang="' . esc_attr( $l['locale'] ? str_replace( '_', '-', $l['locale'] ) : $l['slug'] ) . '" lang="' . esc_attr( 'en' === $l['slug'] ? 'en' : 'pt-PT' ) . '"' . ( $l['current_lang'] ? ' aria-current="true"' : '' ) . ( $note ? ' title="' . esc_attr( $note ) . '"' : '' ) . '>';
		$o .= '<span aria-hidden="true">' . esc_html( $label ) . '</span><span class="screen-reader-text">' . esc_html( $l['name'] ) . ( $note ? ' — ' . esc_html( $note ) : '' ) . '</span></a></li>';
	}
	return $o . '</ul></nav>';
}

/** Menu de fallback (quando não há menu atribuído ao local). */
function ppi_fallback_menu(): string {
	$items = array(
		array( __( 'Imóveis', 'ppi-estates' ), class_exists( 'PPI\\I18n' ) ? PPI\I18n::archive_url() : home_url( '/imoveis/' ) ),
		array( __( 'Localizações', 'ppi-estates' ), ppi_theme_page( 'localizacoes' ) ),
		array( __( 'Vender', 'ppi-estates' ), ppi_theme_page( 'vender' ) ),
		array( __( 'Sobre a PPI', 'ppi-estates' ), ppi_theme_page( 'sobre' ) ),
		array( __( 'Contactos', 'ppi-estates' ), ppi_theme_page( 'contactos' ) ),
	);
	$o = '<ul class="menu">';
	foreach ( $items as [ $l, $u ] ) {
		$o .= '<li><a href="' . esc_url( $u ) . '">' . esc_html( $l ) . '</a></li>';
	}
	return $o . '</ul>';
}

add_filter( 'nav_menu_link_attributes', static function ( $atts, $item ) {
	if ( ! empty( $item->current ) || ( ! empty( $item->url ) && untrailingslashit( $item->url ) === untrailingslashit( home_url( add_query_arg( array() ) ) ) ) ) {
		$atts['aria-current'] = 'page';
	}
	return $atts;
}, 10, 2 );

function ppi_render_header(): string {
	$home = ppi_theme_home();
	$seal = get_theme_file_uri( 'assets/img/ppi-seal-176.webp' );
	$menu = has_nav_menu( 'primary' ) ? wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'echo' => false, 'depth' => 1, 'fallback_cb' => false ) ) : ppi_fallback_menu();
	ob_start();
	?>
	<a class="skip-link" href="#main"><?php echo esc_html( ppi_theme_text( 'skip' ) ); ?></a>
	<header class="site-header" data-site-header>
		<div class="site-header__inner">
			<a class="site-brand" href="<?php echo esc_url( $home ); ?>" <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>>
				<img src="<?php echo esc_url( $seal ); ?>" width="88" height="88" alt="<?php esc_attr_e( 'PPI — Portuguese Property Investments, página inicial', 'ppi-estates' ); ?>">
			</a>
			<nav class="site-nav" aria-label="<?php esc_attr_e( 'Principal', 'ppi-estates' ); ?>">
				<button type="button" class="site-nav__toggle" aria-expanded="false" aria-controls="site-menu" data-nav-toggle>
					<span class="site-nav__bars" aria-hidden="true"></span><span class="site-nav__label"><?php esc_html_e( 'Menu', 'ppi-estates' ); ?></span>
				</button>
				<div class="site-nav__panel" id="site-menu" data-nav-panel>
					<?php echo $menu; // phpcs:ignore -- wp_nav_menu ?>
					<div class="site-nav__extra">
						<?php echo ppi_language_switcher(); // phpcs:ignore ?>
					</div>
				</div>
			</nav>
			<div class="site-tools">
				<a class="site-tools__fav" href="<?php echo esc_url( ppi_theme_page( 'favoritos' ) ); ?>">
					<svg class="ppi-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 20.5s-7.5-4.6-7.5-10.1A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.8c0 5.5-7.5 10.1-7.5 10.1Z"/></svg>
					<span class="screen-reader-text"><?php esc_html_e( 'Favoritos', 'ppi-estates' ); ?></span>
					<span class="site-tools__count" data-ppi-fav-count hidden></span>
				</a>
				<div class="site-tools__lang"><?php echo ppi_language_switcher(); // phpcs:ignore ?></div>
			</div>
		</div>
	</header>
	<?php
	return (string) ob_get_clean();
}

function ppi_render_footer(): string {
	$s = class_exists( 'PPI\\Settings' ) ? PPI\Settings::all() : array();
	$g = static fn( $k ) => (string) ( $s[ $k ] ?? '' );
	$menu = has_nav_menu( 'footer' ) ? wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'echo' => false, 'depth' => 1, 'fallback_cb' => false ) ) : '';
	ob_start();
	?>
	<div class="ppi-cmpbar" data-ppi-cmpbar hidden>
		<p data-ppi-cmpbar-text></p>
		<a class="ppi-btn ppi-btn--primary ppi-btn--small" href="<?php echo esc_url( ppi_theme_page( 'comparar' ) ); ?>"><?php esc_html_e( 'Ver comparação', 'ppi-estates' ); ?></a>
	</div>
	<footer class="site-footer">
		<div class="site-footer__inner">
			<div class="site-footer__brand">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/ppi-seal-176.webp' ) ); ?>" width="96" height="96" alt="" loading="lazy">
				<p class="site-footer__claim"><?php esc_html_e( 'Imóveis no Algarve Ocidental, a partir de Budens.', 'ppi-estates' ); ?></p>
			</div>
			<div class="site-footer__col">
				<h2 class="site-footer__h"><?php esc_html_e( 'Contactos', 'ppi-estates' ); ?></h2>
				<address>
					<?php echo nl2br( esc_html( $g( 'address' ) ) ); ?>
				</address>
				<ul class="site-footer__list">
					<?php if ( $g( 'phone_landline' ) ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $g( 'phone_landline' ) ) ); ?>"><?php echo esc_html( $g( 'phone_landline' ) ); ?></a> <span class="note"><?php esc_html_e( 'rede fixa nacional', 'ppi-estates' ); ?></span></li><?php endif; ?>
					<?php if ( $g( 'phone_mobile' ) ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $g( 'phone_mobile' ) ) ); ?>"><?php echo esc_html( $g( 'phone_mobile' ) ); ?></a> <span class="note"><?php esc_html_e( 'rede móvel nacional', 'ppi-estates' ); ?></span></li><?php endif; ?>
					<?php if ( $g( 'email' ) ) : ?><li><a href="mailto:<?php echo esc_attr( antispambot( $g( 'email' ) ) ); ?>"><?php echo esc_html( antispambot( $g( 'email' ) ) ); ?></a></li><?php endif; ?>
				</ul>
			</div>
			<div class="site-footer__col">
				<h2 class="site-footer__h"><?php esc_html_e( 'Explorar', 'ppi-estates' ); ?></h2>
				<?php echo $menu ?: ppi_fallback_menu(); // phpcs:ignore ?>
			</div>
			<div class="site-footer__col">
				<h2 class="site-footer__h"><?php esc_html_e( 'Informação legal', 'ppi-estates' ); ?></h2>
				<ul class="site-footer__list">
					<li><a href="<?php echo esc_url( ppi_theme_page( 'privacidade' ) ); ?>"><?php esc_html_e( 'Política de Privacidade', 'ppi-estates' ); ?></a></li>
					<li><a href="<?php echo esc_url( ppi_theme_page( 'cookies' ) ); ?>"><?php esc_html_e( 'Política de Cookies', 'ppi-estates' ); ?></a></li>
					<li><button type="button" class="linklike" data-ppi-consent-open><?php esc_html_e( 'Preferências de cookies', 'ppi-estates' ); ?></button></li>
					<li><a href="<?php echo esc_url( ppi_theme_page( 'resolucao-de-litigios' ) ); ?>"><?php esc_html_e( 'Resolução alternativa de litígios', 'ppi-estates' ); ?></a></li>
					<?php if ( $g( 'complaints_url' ) ) : ?><li><a href="<?php echo esc_url( $g( 'complaints_url' ) ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Livro de Reclamações', 'ppi-estates' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(abre noutra janela)', 'ppi-estates' ); ?></span></a></li><?php endif; ?>
					<?php if ( $g( 'whistle_url' ) ) : ?><li><a href="<?php echo esc_url( $g( 'whistle_url' ) ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Canal de denúncias', 'ppi-estates' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(abre noutra janela)', 'ppi-estates' ); ?></span></a></li><?php endif; ?>
				</ul>
			</div>
		</div>
		<div class="site-footer__legal">
			<p><?php echo esc_html( $g( 'legal_name' ) ); ?> · <?php /* translators: %s AMI */ printf( esc_html__( 'Licença AMI %s', 'ppi-estates' ), esc_html( $g( 'ami' ) ) ); ?></p>
			<p class="site-footer__social">
				<?php if ( $g( 'facebook' ) ) : ?><a href="<?php echo esc_url( $g( 'facebook' ) ); ?>" rel="noopener" target="_blank">Facebook</a><?php endif; ?>
				<?php if ( $g( 'instagram' ) ) : ?><a href="<?php echo esc_url( $g( 'instagram' ) ); ?>" rel="noopener" target="_blank">Instagram</a><?php endif; ?>
			</p>
		</div>
	</footer>
	<?php
	return (string) ob_get_clean();
}

add_action( 'init', static function () {
	register_block_type( 'ppi-estates/site-header', array( 'api_version' => 3, 'title' => __( 'Cabeçalho PPI', 'ppi-estates' ), 'category' => 'theme', 'render_callback' => 'ppi_render_header', 'supports' => array( 'html' => false, 'inserter' => false ) ) );
	register_block_type( 'ppi-estates/site-footer', array( 'api_version' => 3, 'title' => __( 'Rodapé PPI', 'ppi-estates' ), 'category' => 'theme', 'render_callback' => 'ppi_render_footer', 'supports' => array( 'html' => false, 'inserter' => false ) ) );
	register_block_type( 'ppi-estates/text', array(
		'api_version' => 3, 'title' => __( 'Texto de interface', 'ppi-estates' ), 'category' => 'theme',
		'attributes' => array( 'key' => array( 'type' => 'string', 'default' => '' ), 'tag' => array( 'type' => 'string', 'default' => 'p' ) ),
		'supports' => array( 'html' => false, 'inserter' => false ),
		'render_callback' => static function ( $a ) {
			$tag = in_array( $a['tag'], array( 'h1', 'h2', 'p' ), true ) ? $a['tag'] : 'p';
			return '<' . $tag . ' class="ppi-ui-text ppi-ui-text--' . esc_attr( sanitize_html_class( $a['key'] ) ) . '">' . esc_html( ppi_theme_text( (string) $a['key'] ) ) . '</' . $tag . '>';
		},
	) );
	register_block_pattern_category( 'ppi', array( 'label' => 'PPI' ) );
	register_block_style( 'core/group', array( 'name' => 'ppi-band', 'label' => __( 'Faixa pedra', 'ppi-estates' ) ) );
	register_block_style( 'core/group', array( 'name' => 'ppi-dark', 'label' => __( 'Faixa carvão', 'ppi-estates' ) ) );
	register_block_style( 'core/button', array( 'name' => 'ppi-ghost', 'label' => __( 'Contorno', 'ppi-estates' ) ) );
	register_block_style( 'core/paragraph', array( 'name' => 'ppi-kicker', 'label' => __( 'Antetítulo', 'ppi-estates' ) ) );
	register_block_style( 'core/image', array( 'name' => 'ppi-caption-strong', 'label' => __( 'Legenda editorial', 'ppi-estates' ) ) );
} );

/**
 * Restrições de composição: editores (não administradores) usam um conjunto curado de blocos,
 * para manter a coerência editorial. Administradores mantêm acesso completo.
 */
add_filter( 'allowed_block_types_all', static function ( $allowed, $ctx ) {
	if ( current_user_can( 'manage_options' ) ) {
		return $allowed;
	}
	return array(
		'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/image', 'core/gallery', 'core/quote', 'core/buttons', 'core/button',
		'core/group', 'core/columns', 'core/column', 'core/separator', 'core/spacer', 'core/table', 'core/details', 'core/media-text', 'core/embed',
		'ppi/featured', 'ppi/locations', 'ppi/latest', 'ppi/lead-form', 'ppi/agency', 'ppi/team', 'ppi/quick-search',
	);
}, 10, 2 );
