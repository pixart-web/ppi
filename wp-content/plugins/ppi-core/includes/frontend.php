<?php
/**
 * Scripts públicos do plugin e resposta parcial da listagem.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'script_loader_tag', static function ( $tag, $handle ) {
			return 'ppi' === $handle ? str_replace( ' src=', ' defer src=', $tag ) : $tag;
		}, 10, 2 );
		// Variar a cache pelo pedido parcial da listagem (fetch dos filtros).
		add_action( 'send_headers', static function () {
			if ( isset( $_SERVER['HTTP_X_PPI_PARTIAL'] ) ) {
				header( 'Vary: X-PPI-Partial', false );
			}
		} );
	}

	public static function assets(): void {
		wp_enqueue_script( 'ppi', PPI_CORE_URL . 'assets/ppi.js', array(), PPI_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'ppi', 'ppiCfg', array(
			'lang'       => I18n::current(),
			'compareUrl' => I18n::page_url( 'comparar' ),
			'i18n'       => array(
				'save' => __( 'Guardar', 'ppi-core' ), 'saved' => __( 'Guardado', 'ppi-core' ), 'compare' => __( 'Comparar', 'ppi-core' ), 'comparing' => __( 'Na comparação', 'ppi-core' ),
				/* translators: %s reference */
				'addedFav' => __( 'Imóvel ref. %s guardado nos favoritos.', 'ppi-core' ),
				/* translators: %s reference */
				'removedFav' => __( 'Imóvel ref. %s removido dos favoritos.', 'ppi-core' ),
				/* translators: %s reference */
				'addedCmp' => __( 'Imóvel ref. %s adicionado à comparação.', 'ppi-core' ),
				/* translators: %s reference */
				'removedCmp' => __( 'Imóvel ref. %s removido da comparação.', 'ppi-core' ),
				'cmpFull' => __( 'Pode comparar até 3 imóveis. Remova um antes de adicionar outro.', 'ppi-core' ),
				/* translators: %d count */
				'compareCount' => __( '%d de 3 imóveis para comparar', 'ppi-core' ),
				'unavailable' => __( 'Indisponível', 'ppi-core' ), 'unavailableHelp' => __( 'este anúncio já não está publicado.', 'ppi-core' ),
				'removeFromFav' => __( 'Remover dos favoritos', 'ppi-core' ), 'refLabel' => __( 'Ref.', 'ppi-core' ),
				'loadError' => __( 'Não foi possível carregar os favoritos. Tente de novo.', 'ppi-core' ),
				'searchError' => __( 'Não foi possível atualizar os resultados.', 'ppi-core' ), 'reload' => __( 'Recarregar a página', 'ppi-core' ),
				'required' => __( 'Campo obrigatório.', 'ppi-core' ), 'emailInvalid' => __( 'Indique um email válido.', 'ppi-core' ),
				'fixErrors' => __( 'Verifique os campos assinalados.', 'ppi-core' ), 'sending' => __( 'A enviar…', 'ppi-core' ),
				'sendError' => __( 'Não foi possível enviar agora. Verifique a ligação e tente de novo, ou contacte-nos por telefone.', 'ppi-core' ),
				'consentSaved' => __( 'Preferências de cookies guardadas.', 'ppi-core' ),
			),
		) );
	}
}

Frontend::init();
