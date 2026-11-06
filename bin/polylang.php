<?php
/**
 * Configuração do Polylang: PT (predefinido, sem prefixo — preserva URLs portugueses) e EN em /en/.
 * Executado por bin/setup.sh com `wp eval-file`. Idempotente.
 */
if ( ! function_exists( 'PLL' ) ) {
	WP_CLI::error( 'Polylang inativo.' );
}
$model = PLL()->model;
$defs  = array(
	array( 'name' => 'Português', 'slug' => 'pt', 'locale' => 'pt_PT', 'rtl' => false, 'term_group' => 0, 'flag' => 'pt' ),
	array( 'name' => 'English', 'slug' => 'en', 'locale' => 'en_GB', 'rtl' => false, 'term_group' => 1, 'flag' => 'gb' ),
);
foreach ( $defs as $def ) {
	if ( $model->get_language( $def['slug'] ) ) {
		continue;
	}
	$r = ( isset( $model->languages ) && method_exists( $model->languages, 'add' ) ) ? $model->languages->add( $def ) : $model->add_language( $def );
	if ( is_wp_error( $r ) ) {
		WP_CLI::warning( $r->get_error_message() );
	}
}
$values = array(
	'force_lang' => 1, 'hide_default' => true, 'rewrite' => true, 'browser' => false, 'redirect_lang' => true,
	'media_support' => false, 'default_lang' => 'pt', 'post_types' => array( 'ppi_property' ), 'taxonomies' => array(),
	'sync' => array( 'taxonomies', 'post_meta', 'post_format', 'sticky_posts', '_thumbnail_id', 'page_template' ),
);
$opts = PLL()->options;
if ( is_object( $opts ) && method_exists( $opts, 'set' ) ) {
	foreach ( $values as $k => $v ) {
		$opts->set( $k, $v );
	}
	if ( method_exists( $opts, 'save' ) ) {
		$opts->save();
	}
} else {
	update_option( 'polylang', array_merge( (array) get_option( 'polylang', array() ), $values ) );
}
if ( method_exists( $model, 'clean_languages_cache' ) ) {
	$model->clean_languages_cache();
}
// Conteúdo existente sem idioma (ex.: página de exemplo) passa a PT.
if ( method_exists( $model, 'set_language_in_mass' ) ) {
	$model->set_language_in_mass( 'pt' );
}
WP_CLI::success( 'Polylang configurado (pt predefinido sem prefixo, en em /en/).' );
