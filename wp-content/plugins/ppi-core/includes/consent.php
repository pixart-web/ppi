<?php
/**
 * Consentimento de cookies e conteúdos externos.
 *
 * Categorias: necessários (sempre), medição (Google Analytics, só se configurado) e conteúdos externos
 * (YouTube, mapas). Nada não essencial é carregado antes da escolha. Os favoritos/comparação usam
 * localStorage apenas por ação do utilizador (funcionalidade pedida), sem envio para servidores.
 * A escolha fica no cookie ppi_consent (6 meses) e pode ser alterada no rodapé.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Consent {

	public const VERSION = 1;

	public static function init(): void {
		add_action( 'wp_footer', array( __CLASS__, 'banner' ), 5 );
	}

	public static function banner(): void {
		$ga = (string) Settings::get( 'ga4_id' );
		?>
		<div class="ppi-consent" id="ppi-consent" role="dialog" aria-modal="false" aria-labelledby="ppi-consent-h" hidden data-ppi-consent data-version="<?php echo (int) self::VERSION; ?>"<?php echo $ga ? ' data-ga="' . esc_attr( $ga ) . '"' : ''; ?>>
			<div class="ppi-consent__inner">
				<h2 id="ppi-consent-h" class="ppi-consent__title"><?php esc_html_e( 'Cookies e conteúdos externos', 'ppi-core' ); ?></h2>
				<p><?php esc_html_e( 'Usamos apenas o essencial para o site funcionar. Com a sua autorização, podemos medir visitas de forma agregada e mostrar vídeos e mapas de serviços externos.', 'ppi-core' ); ?> <a href="<?php echo esc_url( I18n::page_url( 'cookies' ) ); ?>"><?php esc_html_e( 'Saber mais', 'ppi-core' ); ?></a></p>
				<form class="ppi-consent__prefs" hidden data-ppi-consent-prefs>
					<label class="ppi-check"><input type="checkbox" checked disabled> <span><?php esc_html_e( 'Necessários — sempre ativos', 'ppi-core' ); ?></span></label>
					<?php if ( $ga ) : ?>
						<label class="ppi-check"><input type="checkbox" name="analytics" value="1"> <span><?php esc_html_e( 'Medição de audiência (Google Analytics)', 'ppi-core' ); ?></span></label>
					<?php endif; ?>
					<label class="ppi-check"><input type="checkbox" name="external" value="1"> <span><?php esc_html_e( 'Conteúdos externos (YouTube, mapas)', 'ppi-core' ); ?></span></label>
				</form>
				<div class="ppi-consent__actions">
					<button type="button" class="ppi-btn ppi-btn--ghost" data-ppi-consent-act="reject"><?php esc_html_e( 'Só necessários', 'ppi-core' ); ?></button>
					<button type="button" class="ppi-btn ppi-btn--ghost" data-ppi-consent-act="customize" aria-expanded="false"><?php esc_html_e( 'Personalizar', 'ppi-core' ); ?></button>
					<button type="button" class="ppi-btn ppi-btn--ghost" data-ppi-consent-act="save" hidden><?php esc_html_e( 'Guardar escolha', 'ppi-core' ); ?></button>
					<button type="button" class="ppi-btn ppi-btn--primary" data-ppi-consent-act="accept"><?php esc_html_e( 'Aceitar todos', 'ppi-core' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/** Vídeo externo: placeholder até haver consentimento (ou clique explícito). */
	public static function embed( string $url, string $title ): string {
		$id = '';
		if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
			$id  = $m[1];
			$src = 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0';
		} elseif ( preg_match( '~vimeo\.com/(\d+)~', $url, $m ) ) {
			$src = 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		} else {
			return '<p><a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">' . esc_html( $title ) . '</a></p>';
		}
		ob_start();
		?>
		<div class="ppi-embed" data-ppi-embed data-src="<?php echo esc_url( $src ); ?>" data-title="<?php echo esc_attr( $title ); ?>">
			<div class="ppi-embed__ph">
				<p><?php esc_html_e( 'Este vídeo é servido por um serviço externo, que pode usar cookies.', 'ppi-core' ); ?></p>
				<p>
					<button type="button" class="ppi-btn ppi-btn--primary" data-ppi-embed-load hidden data-ppi-js><?php esc_html_e( 'Carregar vídeo', 'ppi-core' ); ?></button>
					<a href="<?php echo esc_url( $url ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Ver no site do serviço (nova janela)', 'ppi-core' ); ?></a>
				</p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Mapa OpenStreetMap (iframe) com bbox; só com coordenadas públicas e após consentimento. */
	public static function map( array $c, bool $approx ): string {
		[ $lat, $lng ] = $c;
		$d   = $approx ? 0.03 : 0.008;
		$src = sprintf( 'https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik', $lng - $d, $lat - $d / 1.6, $lng + $d, $lat + $d / 1.6 );
		if ( ! $approx ) {
			$src .= sprintf( '&marker=%F,%F', $lat, $lng );
		}
		ob_start();
		?>
		<div class="ppi-embed ppi-embed--map" data-ppi-embed data-src="<?php echo esc_url( $src ); ?>" data-title="<?php esc_attr_e( 'Mapa da zona', 'ppi-core' ); ?>">
			<div class="ppi-embed__ph">
				<p><?php echo esc_html( $approx ? __( 'Mapa da zona aproximada (OpenStreetMap).', 'ppi-core' ) : __( 'Mapa (OpenStreetMap).', 'ppi-core' ) ); ?></p>
				<button type="button" class="ppi-btn ppi-btn--ghost" data-ppi-embed-load hidden data-ppi-js><?php esc_html_e( 'Mostrar mapa', 'ppi-core' ); ?></button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Contagem agregada no servidor (sem dados pessoais). Só ativa com medição configurada. */
	public static function track_server( string $event, string $type ): void {
		if ( ! Settings::get( 'ga4_id' ) ) {
			return;
		}
		$k = 'ppi_metrics_' . gmdate( 'Y_m' );
		$m = get_option( $k, array() );
		$m[ $event . ':' . $type ] = ( $m[ $event . ':' . $type ] ?? 0 ) + 1;
		update_option( $k, $m, false );
	}
}

Consent::init();
