<?php
/**
 * Páginas, menus, destaques e definições da proposta PPI. Executado por bin/setup.sh (`wp eval-file`).
 * Idempotente: uma página alterada no painel não é sobrescrita (comparação com o hash do conteúdo semeado).
 * Só usa afirmações presentes no site original ou factos verificáveis; ver docs/pendencias.md.
 */

use PPI\Fields;
use PPI\I18n;
use PPI\Settings;

$p      = static fn( string $t, string $cls = '' ) => '<!-- wp:paragraph' . ( $cls ? ' {"className":"' . $cls . '"}' : '' ) . ' --><p' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->';
$h2     = static fn( string $t ) => '<!-- wp:heading --><h2 class="wp-block-heading">' . $t . '</h2><!-- /wp:heading -->';
$form   = static fn( string $type ) => '<!-- wp:group {"className":"ppi-formwrap"} --><div class="wp-block-group ppi-formwrap"><!-- wp:ppi/lead-form {"type":"' . $type . '"} /--></div><!-- /wp:group -->';
$button = static fn( string $label, string $url ) => '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . $label . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
$steps  = static function ( array $items ) {
	$o = '<!-- wp:html --><ol class="ppi-steps">';
	foreach ( $items as [ $t, $d ] ) {
		$o .= '<li><strong>' . $t . '</strong>' . $d . '</li>';
	}
	return $o . '</ol><!-- /wp:html -->';
};
$region_image = static function ( string $slug ): array {
	$t  = get_term_by( 'slug', $slug, 'ppi_location' );
	$id = $t ? (int) get_term_meta( $t->term_id, 'ppi_image', true ) : 0;
	return array( $id, $id ? (string) wp_get_attachment_image_url( $id, 'large' ) : '', $t ? (string) get_term_meta( $t->term_id, 'ppi_image_credit', true ) : '' );
};
$split = static function ( array $img, string $alt, string $inner, string $cls = '' ) {
	[ $id, $url, $credit ] = $img;
	$fig = $url ? '<!-- wp:html --><figure class="ppi-split__media"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" width="1024" height="683"><figcaption class="ppi-credit">' . $credit . '</figcaption></figure><!-- /wp:html -->' : '';
	return '<!-- wp:group {"align":"wide","className":"ppi-split ' . $cls . '"} --><div class="wp-block-group alignwide ppi-split ' . $cls . '">' . $fig . '<!-- wp:group --><div class="wp-block-group">' . $inner . '</div><!-- /wp:group --></div><!-- /wp:group -->';
};
$band = static fn( string $inner, string $style = 'ppi-band' ) => '<!-- wp:group {"align":"full","className":"is-style-' . $style . '"} --><div class="wp-block-group alignfull is-style-' . $style . '">' . $inner . '</div><!-- /wp:group -->';

$url = static function ( string $pt_path, string $en_path, string $lang ): string {
	return home_url( 'en' === $lang ? '/en/' . $en_path . '/' : '/' . $pt_path . '/' );
};

$privacy_lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) @file_get_contents( '/migration/legal/privacy-original.txt' ) ) ) ) );
$privacy_pt = '<!-- wp:html --><div class="legal-review"><strong>Por rever pelo responsável.</strong> Texto preservado do site anterior. Deve ser atualizado antes da publicação, nomeadamente sobre os novos formulários, prazos de conservação, direitos dos titulares e localização dos servidores.</div><!-- /wp:html -->';
foreach ( array_slice( $privacy_lines, 2 ) as $l ) {
	if ( '<div class="' === $l || str_starts_with( $l, '(Chamada' ) || str_starts_with( $l, ',' ) ) {
		continue;
	}
	$privacy_pt .= preg_match( '/^\d+\. /', $l ) ? $h2( esc_html( $l ) ) : $p( esc_html( $l ) );
}

$ral = array(
	array( 'CNIACC — Centro Nacional de Informação e Arbitragem de Conflitos de Consumo', 'https://www.cniacc.pt/pt/', 'www.cniacc.pt' ),
	array( 'Centro de Arbitragem de Conflitos de Consumo do Distrito de Coimbra', 'http://www.centrodearbitragemdecoimbra.com/', 'www.centrodearbitragemdecoimbra.com' ),
	array( 'Centro de Arbitragem de Conflitos de Consumo de Lisboa', 'http://www.centroarbitragemlisboa.pt', 'www.centroarbitragemlisboa.pt' ),
	array( 'Centro de Arbitragem de Conflitos de Consumo da Região Autónoma da Madeira', 'https://www.madeira.gov.pt/cacc', 'www.madeira.gov.pt/cacc' ),
	array( 'Centro de Informação e Arbitragem do Porto', 'http://www.cicap.pt/', 'www.cicap.pt' ),
	array( 'Centro de Informação e Arbitragem do Vale do Ave', 'http://www.triave.pt/', 'www.triave.pt' ),
	array( 'Centro de Informação e Arbitragem do Vale do Cávado', 'http://www.ciab.pt/', 'www.ciab.pt' ),
	array( 'Centro de Informação, Mediação e Arbitragem do Algarve', 'http://www.consumidoronline.pt/', 'www.consumidoronline.pt' ),
);
$ral_list = '<!-- wp:list --><ul class="wp-block-list">';
foreach ( $ral as [ $n, $u, $l ] ) {
	$ral_list .= '<!-- wp:list-item --><li>' . esc_html( $n ) . ' — <a href="' . esc_url( $u ) . '" rel="noopener">' . esc_html( $l ) . '</a></li><!-- /wp:list-item -->';
}
$ral_list .= '</ul><!-- /wp:list -->';

$cookies_tbl = static fn( array $rows, array $head ) => '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>' . implode( '</th><th>', $head ) . '</th></tr></thead><tbody>' . implode( '', array_map( static fn( $r ) => '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>', $rows ) ) . '</tbody></table></figure><!-- /wp:table -->';

$sell_img = $region_image( 'budens' );
$about_img = $region_image( 'vila-do-bispo' );

$home = static function ( string $lang ) use ( $p, $h2, $button, $split, $band, $sell_img, $url ): string {
	$en = 'en' === $lang;
	$sell = $split( $sell_img, $en ? 'Boca do Rio beach, Budens' : 'Praia da Boca do Rio, Budens',
		$p( $en ? 'Sell with PPI' : 'Vender com a PPI', 'is-style-ppi-kicker' ) .
		$h2( $en ? 'Thinking of selling your property in the Algarve?' : 'Está a pensar vender o seu imóvel no Algarve?' ) .
		$p( $en ? 'Tell us about the house or land. We look at the property, compare it with the local market and suggest how to present it. A valuation is a conversation with the team, not an automatic figure.' : 'Fale-nos da casa ou do terreno. Visitamos o imóvel, comparamo-lo com o mercado local e propomos a melhor forma de o apresentar. A avaliação é uma conversa com a equipa, não um valor automático.' ) .
		$button( $en ? 'Request a valuation' : 'Solicitar avaliação', $url( 'vender', 'sell', $lang ) ) );
	$agency = '<!-- wp:group {"align":"wide","className":"ppi-split"} --><div class="wp-block-group alignwide ppi-split"><!-- wp:group --><div class="wp-block-group">' .
		$p( $en ? 'The agency' : 'A agência', 'is-style-ppi-kicker' ) .
		$h2( $en ? 'At the entrance to the Santo António golf resort, in Budens' : 'À entrada do resort de golfe de Santo António, em Budens' ) .
		$p( $en ? 'PPI — Portuguese Property Investments works mainly in the municipality of Vila do Bispo and along the coast to Aljezur. Come and see us, or send us what you are looking for.' : 'A PPI — Portuguese Property Investments trabalha sobretudo no concelho de Vila do Bispo e ao longo da costa até Aljezur. Visite-nos, ou diga-nos o que procura.' ) .
		$button( $en ? 'About PPI' : 'Sobre a PPI', $url( 'sobre', 'about', $lang ) ) .
		'</div><!-- /wp:group --><!-- wp:ppi/agency {"variant":"home"} /--></div><!-- /wp:group -->';
	return '<!-- wp:ppi/hero {"align":"full"} /-->' . "\n" . '<!-- wp:ppi/featured {"align":"wide"} /-->' . "\n" . '<!-- wp:ppi/locations {"align":"wide"} /-->' . "\n" . '<!-- wp:ppi/latest {"align":"wide"} /-->' . "\n" . $band( $sell ) . "\n" . '<!-- wp:group {"align":"full","className":"ppi-section-pad"} --><div class="wp-block-group alignfull ppi-section-pad">' . $agency . '</div><!-- /wp:group -->';
};

$pages = array(
	'home' => array( 'pt' => array( 'inicio', 'Início' ), 'en' => array( 'home', 'Home' ), 'template' => '', 'pt_c' => $home( 'pt' ), 'en_c' => $home( 'en' ) ),
	'locations' => array(
		'pt' => array( 'localizacoes', 'Localizações' ), 'en' => array( 'locations', 'Locations' ),
		'pt_c' => $p( 'Do Barlavento algarvio à Costa Vicentina: as zonas onde a PPI tem imóveis disponíveis neste momento.', 'page-intro' ) . '<!-- wp:ppi/locations-index {"align":"wide"} /-->',
		'en_c' => $p( 'From the western Algarve to the Vicentine Coast: the areas where PPI currently has properties available.', 'page-intro' ) . '<!-- wp:ppi/locations-index {"align":"wide"} /-->',
	),
	'sell' => array(
		'pt' => array( 'vender', 'Vender com a PPI' ), 'en' => array( 'sell', 'Sell with PPI' ),
		'pt_c' => $p( 'Conte-nos o que tem para vender. Respondemos com uma opinião fundamentada e com o caminho que propomos, sem compromisso.', 'page-intro' ) .
			$h2( 'Como funciona' ) . $steps( array( array( 'Fala connosco', 'Preencha o formulário ou ligue-nos, com os dados básicos do imóvel.' ), array( 'Visitamos o imóvel', 'Vemos a casa ou o terreno e a documentação disponível.' ), array( 'Avaliação', 'Comparamos com o mercado da zona e discutimos consigo um preço realista.' ), array( 'Apresentação', 'Preparamos fotografias, descrição e anúncio, com informação rigorosa.' ) ) ) .
			$p( 'A avaliação depende do imóvel, da documentação e do mercado no momento. Este formulário não gera qualquer valor automático.' ) . $h2( 'Solicitar avaliação' ) . $form( 'valuation' ),
		'en_c' => $p( 'Tell us what you have to sell. We will reply with a reasoned opinion and the approach we suggest, with no obligation.', 'page-intro' ) .
			$h2( 'How it works' ) . $steps( array( array( 'Talk to us', 'Fill in the form or call us, with the basic details of the property.' ), array( 'We visit', 'We see the house or land and the available paperwork.' ), array( 'Valuation', 'We compare it with the local market and discuss a realistic price with you.' ), array( 'Presentation', 'We prepare photographs, description and listing, with accurate information.' ) ) ) .
			$p( 'A valuation depends on the property, the paperwork and the market at the time. This form does not generate an automatic figure.' ) . $h2( 'Request a valuation' ) . $form( 'valuation' ),
	),
	'buy' => array(
		'pt' => array( 'comprar', 'Procura um imóvel?' ), 'en' => array( 'buy', 'Looking for a property?' ),
		'pt_c' => $p( 'Se ainda não encontrou o imóvel certo no catálogo, diga-nos o que procura. Avisamos quando surgir algo adequado.', 'page-intro' ) . $form( 'search' ),
		'en_c' => $p( 'If you have not found the right property in the catalogue, tell us what you are looking for. We will let you know when something suitable comes up.', 'page-intro' ) . $form( 'search' ),
	),
	'about' => array(
		'pt' => array( 'sobre', 'Sobre a PPI' ), 'en' => array( 'about', 'About PPI' ),
		'pt_c' => $p( 'A PPI é uma agência imobiliária localizada junto à entrada do resort de golfe de Santo António, no Barlavento Algarvio.', 'page-intro' ) .
			$p( 'A Portuguese Property Investments fica junto à Estrada Nacional 125, em Budens. Trabalha com imóveis sobretudo no concelho de Vila do Bispo e ao longo da costa até Aljezur. A agência indica uma experiência de mais de 25 anos neste negócio, e a sua marca assinala «Established since 1998».' ) .
			'<!-- wp:ppi/team /-->' . $h2( 'Onde estamos' ) . '<!-- wp:ppi/agency /-->',
		'en_c' => $p( 'PPI is an estate agency located at the entrance to the Santo António golf resort, in the western Algarve.', 'page-intro' ) .
			$p( 'Portuguese Property Investments is on the Estrada Nacional 125, in Budens. It works with properties mainly in the municipality of Vila do Bispo and along the coast to Aljezur. The agency states more than 25 years of experience in this business, and its logo reads “Established since 1998”.' ) .
			'<!-- wp:ppi/team /-->' . $h2( 'Where we are' ) . '<!-- wp:ppi/agency /-->',
	),
	'contact' => array(
		'pt' => array( 'contactos', 'Contactos' ), 'en' => array( 'contact', 'Contact' ),
		'pt_c' => '<!-- wp:ppi/agency /-->' . $h2( 'Enviar mensagem' ) . $form( 'contact' ),
		'en_c' => '<!-- wp:ppi/agency /-->' . $h2( 'Send a message' ) . $form( 'contact' ),
	),
	'favourites' => array( 'pt' => array( 'favoritos', 'Favoritos' ), 'en' => array( 'favourites', 'Favourites' ), 'pt_c' => '<!-- wp:ppi/favorites {"align":"wide"} /-->', 'en_c' => '<!-- wp:ppi/favorites {"align":"wide"} /-->' ),
	'compare' => array( 'pt' => array( 'comparar', 'Comparar imóveis' ), 'en' => array( 'compare', 'Compare properties' ), 'pt_c' => '<!-- wp:ppi/compare {"align":"wide"} /-->', 'en_c' => '<!-- wp:ppi/compare {"align":"wide"} /-->' ),
	'rent' => array(
		'pt' => array( 'arrendar', 'Arrendamento' ), 'en' => array( 'rent', 'Renting' ),
		'pt_c' => $p( 'Procura casa para arrendar ou tem um imóvel para colocar no mercado? Diga-nos a sua situação.', 'page-intro' ) . $form( 'rental' ),
		'en_c' => $p( 'Looking for a home to rent, or do you have a property to let? Tell us about your situation.', 'page-intro' ) . $form( 'rental' ),
	),
	'exchange' => array(
		'pt' => array( 'permutar', 'Permuta de imóveis' ), 'en' => array( 'exchange', 'Property exchange' ),
		'pt_c' => $p( 'Quer trocar o seu imóvel por outro? Indique o que tem e o que procura.', 'page-intro' ) . $form( 'exchange' ),
		'en_c' => $p( 'Would you like to exchange your property for another? Tell us what you have and what you are looking for.', 'page-intro' ) . $form( 'exchange' ),
	),
	'careers' => array(
		'pt' => array( 'recrutamento', 'Recrutamento' ), 'en' => array( 'careers', 'Careers' ),
		'pt_c' => $p( 'Envie-nos a sua candidatura. O CV fica numa área privada, acessível apenas à gestão da agência.', 'page-intro' ) . $form( 'recruitment' ),
		'en_c' => $p( 'Send us your application. Your CV is kept in a private area, accessible only to the agency’s management.', 'page-intro' ) . $form( 'recruitment' ),
	),
	'privacy' => array(
		'pt' => array( 'privacidade', 'Política de Privacidade' ), 'en' => array( 'privacy', 'Privacy Policy' ),
		'pt_c' => $privacy_pt,
		'en_c' => '<!-- wp:html --><div class="legal-review"><strong>English version pending.</strong> The privacy policy must be reviewed and translated by the data controller. Until then, the Portuguese text applies.</div><!-- /wp:html -->' . $p( '<a href="' . esc_url( home_url( '/privacidade/' ) ) . '" hreflang="pt-PT" lang="pt-PT">Política de Privacidade (português)</a>' ),
	),
	'cookies' => array(
		'pt' => array( 'cookies', 'Política de Cookies' ), 'en' => array( 'cookies-policy', 'Cookie Policy' ),
		'pt_c' => '<!-- wp:html --><div class="legal-review"><strong>Por rever pelo responsável.</strong> Proposta que descreve o funcionamento técnico deste site.</div><!-- /wp:html -->' .
			$p( 'Este site guarda o mínimo necessário no seu navegador. Pode alterar a sua escolha a qualquer momento em «Preferências de cookies», no rodapé.' ) .
			$cookies_tbl( array( array( 'ppi_consent', 'Cookie', 'Guarda a sua escolha de cookies', '6 meses' ), array( 'pll_language', 'Cookie', 'Guarda o idioma escolhido', '1 ano' ), array( 'ppi_favs, ppi_compare', 'Armazenamento local', 'Guarda favoritos e comparação neste navegador, sem envio para servidores', 'Até os apagar' ) ), array( 'Nome', 'Tipo', 'Finalidade', 'Duração' ) ) .
			$p( 'Vídeos e mapas de serviços externos só são carregados depois de os autorizar. A medição de audiência só é ativada se estiver configurada e se a aceitar.' ),
		'en_c' => '<!-- wp:html --><div class="legal-review"><strong>To be reviewed by the data controller.</strong> A proposal describing how this site works technically.</div><!-- /wp:html -->' .
			$p( 'This site stores the minimum necessary in your browser. You can change your choice at any time under “Cookie preferences” in the footer.' ) .
			$cookies_tbl( array( array( 'ppi_consent', 'Cookie', 'Stores your cookie choice', '6 months' ), array( 'pll_language', 'Cookie', 'Stores your chosen language', '1 year' ), array( 'ppi_favs, ppi_compare', 'Local storage', 'Keeps favourites and comparison in this browser, not sent to any server', 'Until you delete them' ) ), array( 'Name', 'Type', 'Purpose', 'Duration' ) ) .
			$p( 'Videos and maps from external services are only loaded after you allow them. Audience measurement is only active if it is configured and you accept it.' ),
	),
	'disputes' => array(
		'pt' => array( 'resolucao-de-litigios', 'Resolução alternativa de litígios' ), 'en' => array( 'dispute-resolution', 'Alternative dispute resolution' ),
		'pt_c' => $p( 'Em caso de litígio, o consumidor pode recorrer a um Centro de Resolução de Litígios de consumo:' ) . $ral_list . $p( 'Para atualizações e mais informações, consulte o Portal do Consumidor em <a href="https://www.consumidor.pt/" rel="noopener">www.consumidor.pt</a> (ao abrigo do artigo 18.º da Lei n.º 144/2015, de 8 de setembro).' ),
		'en_c' => $p( 'In the event of a dispute, consumers may turn to a consumer dispute resolution centre:' ) . $ral_list . $p( 'For updates and more information, see the Consumer Portal at <a href="https://www.consumidor.pt/" rel="noopener">www.consumidor.pt</a> (under article 18 of Law no. 144/2015 of 8 September).' ),
	),
);

$ids = array();
foreach ( $pages as $key => $def ) {
	foreach ( array( 'pt', 'en' ) as $lang ) {
		[ $slug, $title ] = $def[ $lang ];
		$content = $def[ $lang . '_c' ];
		$hash    = sha1( $content );
		$found   = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_ppi_page_key', 'meta_value' => "$key:$lang", 'lang' => '' ) );
		$id      = (int) ( $found[0] ?? 0 );
		if ( $id ) {
			$seeded = (string) get_post_meta( $id, '_ppi_seed_hash', true );
			if ( sha1( get_post_field( 'post_content', $id ) ) === $seeded ) {
				wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content, 'post_title' => $title ) ) );
				update_post_meta( $id, '_ppi_seed_hash', sha1( get_post_field( 'post_content', $id ) ) );
			} else {
				WP_CLI::log( "Mantida (editada no painel): $slug" );
			}
		} else {
			$id = (int) wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ) ) );
			pll_set_post_language( $id, $lang );
			update_post_meta( $id, '_ppi_page_key', "$key:$lang" );
			update_post_meta( $id, '_ppi_seed_hash', sha1( get_post_field( 'post_content', $id ) ) );
		}
		$ids[ $key ][ $lang ] = $id;
	}
	pll_save_post_translations( $ids[ $key ] );
}
foreach ( array( 'sample-page', 'pagina-de-exemplo', 'privacy-policy', 'politica-de-privacidade' ) as $junk ) {
	$j = get_page_by_path( $junk );
	if ( $j && ! get_post_meta( $j->ID, '_ppi_page_key', true ) ) {
		wp_delete_post( $j->ID, true );
	}
}
$hello = get_page_by_path( 'hello-world', OBJECT, 'post' ) ?: get_page_by_path( 'ola-mundo', OBJECT, 'post' );
if ( $hello ) {
	wp_delete_post( $hello->ID, true );
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home']['pt'] );
update_option( 'wp_page_for_privacy_policy', $ids['privacy']['pt'] );

// Menus por idioma.
$menus = array(
	'primary' => array( 'catalog', 'locations', 'sell', 'about', 'contact' ),
	'footer'  => array( 'catalog', 'buy', 'rent', 'exchange', 'careers', 'favourites', 'compare' ),
);
$pll = (array) get_option( 'polylang', array() );
$theme = get_stylesheet();
$locations = array();
foreach ( $menus as $loc => $keys ) {
	foreach ( array( 'pt', 'en' ) as $lang ) {
		$name = "PPI $loc ($lang)";
		$menu = wp_get_nav_menu_object( $name );
		$mid  = $menu ? $menu->term_id : wp_create_nav_menu( $name );
		if ( ! $menu ) {
			foreach ( $keys as $i => $k ) {
				if ( 'catalog' === $k ) {
					wp_update_nav_menu_item( $mid, 0, array( 'menu-item-title' => 'en' === $lang ? 'Properties' : 'Imóveis', 'menu-item-url' => home_url( ( 'en' === $lang ? '/en' : '' ) . '/imoveis/' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-position' => $i + 1 ) );
				} else {
					$labels = array( 'locations' => array( 'Localizações', 'Locations' ), 'sell' => array( 'Vender', 'Sell' ), 'about' => array( 'Sobre a PPI', 'About PPI' ), 'contact' => array( 'Contactos', 'Contact' ), 'buy' => array( 'Pedido de procura', 'Property search request' ), 'rent' => array( 'Arrendamento', 'Renting' ), 'exchange' => array( 'Permuta', 'Exchange' ), 'careers' => array( 'Recrutamento', 'Careers' ), 'favourites' => array( 'Favoritos', 'Favourites' ), 'compare' => array( 'Comparar', 'Compare' ) );
					wp_update_nav_menu_item( $mid, 0, array( 'menu-item-title' => $labels[ $k ][ 'en' === $lang ? 1 : 0 ], 'menu-item-object-id' => $ids[ $k ][ $lang ], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $i + 1 ) );
				}
			}
		}
		if ( 'pt' === $lang ) {
			$locations[ $loc ] = $mid;
		}
		$pll['nav_menus'][ $theme ][ $loc ][ $lang ] = $mid;
	}
}
set_theme_mod( 'nav_menu_locations', $locations );
$opts = PLL()->options;
if ( is_object( $opts ) && method_exists( $opts, 'set' ) ) {
	$opts->set( 'nav_menus', $pll['nav_menus'] );
	if ( method_exists( $opts, 'save' ) ) {
		$opts->save();
	}
} else {
	update_option( 'polylang', $pll );
}

// Destaques escolhidos para a proposta (editáveis em cada imóvel) e imóvel da abertura.
foreach ( array( '10347' => 1, '10392' => 2, '10466' => 3, '10378-A' => 4, '10400 A' => 5 ) as $ref => $order ) {
	$fid = PPI\Rest::id_by_ref( (string) $ref, 'pt' );
	if ( $fid ) {
		Fields::set( $fid, 'featured_order', $order );
		I18n::sync_facts( $fid );
	} else {
		WP_CLI::warning( "Destaque $ref não encontrado." );
	}
}
// Equipa do site anterior, por validar: guardada mas NÃO mostrada até a PPI confirmar.
$team = array();
foreach ( array( array( 'Dino Claudino', 'Consultor Imobiliário', 'Property Consultant' ), array( 'Erik Johansson', 'Consultor Imobiliário', 'Property Consultant' ), array( 'Paul Murrall', 'Consultor Imobiliário', 'Property Consultant' ), array( 'Telma Macedo', 'Apoio administrativo', 'Administrative support' ) ) as [ $n, $pt, $en ] ) {
	$team[] = array( 'name' => $n, 'role_pt' => $pt, 'role_en' => $en, 'photo' => 0, 'validated' => false );
}
$s = Settings::all();
Settings::update( array( 'hero_reference' => '10300', 'team' => $s['team'] ?: $team ) );
PPI\Search::flush();
WP_CLI::success( count( $ids ) . ' páginas (PT/EN), menus e destaques configurados.' );
