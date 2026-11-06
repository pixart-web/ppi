<?php
/**
 * Notificações de leads por email.
 *
 * - SMTP configurado por ambiente (constantes PPI_SMTP_* no wp-config.php ou variáveis de ambiente).
 * - Fora de produção, TODOS os emails vão para PPI_MAIL_TEST_TO (nunca para contactos reais).
 * - Estado por lead: pending | sent | failed | not_configured. «sent» significa que o servidor SMTP
 *   aceitou a mensagem — não prova entrega na caixa. Repetição a cada 15 min, até 5 tentativas.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Mail {

	public const MAX_ATTEMPTS = 5;

	private static ?string $last_error = null;

	public static function init(): void {
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ) );
		add_action( 'wp_mail_failed', static function ( $err ) {
			self::$last_error = $err instanceof \WP_Error ? $err->get_error_message() : 'wp_mail_failed';
		} );
		add_action( 'ppi_retry_notifications', array( __CLASS__, 'retry_failed' ) );
		// Salvaguarda global: em staging nenhum email do WordPress sai para destinatários reais.
		add_filter( 'wp_mail', array( __CLASS__, 'guard' ), 999 );
	}

	public static function env( string $k ): string {
		$c = 'PPI_' . $k;
		if ( defined( $c ) ) {
			return (string) constant( $c );
		}
		$v = getenv( $c );
		return false === $v ? '' : (string) $v;
	}

	public static function configured(): bool {
		return '' !== self::env( 'SMTP_HOST' );
	}

	public static function is_production(): bool {
		return 'production' === wp_get_environment_type();
	}

	public static function test_recipient(): string {
		return sanitize_email( self::env( 'MAIL_TEST_TO' ) ) ?: 'leads@ppi.test';
	}

	public static function guard( array $args ): array {
		if ( ! self::is_production() ) {
			$args['to']      = self::test_recipient();
			$args['headers'] = array_values( array_filter( (array) $args['headers'], static fn( $h ) => ! preg_match( '/^\s*(cc|bcc)\s*:/i', (string) $h ) ) );
		}
		return $args;
	}

	public static function smtp( $phpmailer ): void {
		if ( ! self::configured() ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = self::env( 'SMTP_HOST' );
		$phpmailer->Port       = (int) ( self::env( 'SMTP_PORT' ) ?: 587 );
		$phpmailer->SMTPAuth   = '' !== self::env( 'SMTP_USER' );
		$phpmailer->Username   = self::env( 'SMTP_USER' );
		$phpmailer->Password   = self::env( 'SMTP_PASS' );
		$secure                = self::env( 'SMTP_SECURE' );
		$phpmailer->SMTPSecure = in_array( $secure, array( 'tls', 'ssl' ), true ) ? $secure : '';
		$phpmailer->SMTPAutoTLS = '' !== $secure;
		$phpmailer->Timeout    = 15;
		$from = sanitize_email( self::env( 'MAIL_FROM' ) );
		if ( $from ) {
			$phpmailer->setFrom( $from, 'PPI Website', false );
		}
	}

	/** Envia (ou tenta) a notificação de um lead já gravado. Nunca lança exceções para o pedido. */
	public static function notify( int $lead_id ): string {
		$attempts = (int) get_post_meta( $lead_id, '_ppi_mail_attempts', true );
		if ( ! self::configured() && self::is_production() ) {
			update_post_meta( $lead_id, '_ppi_mail_status', 'not_configured' );
			update_post_meta( $lead_id, '_ppi_mail_error', __( 'SMTP não configurado: o lead está apenas no painel.', 'ppi-core' ) );
			return 'not_configured';
		}
		$to = sanitize_email( (string) Settings::get( 'notify_to' ) );
		if ( ! $to ) {
			update_post_meta( $lead_id, '_ppi_mail_status', 'not_configured' );
			update_post_meta( $lead_id, '_ppi_mail_error', __( 'Sem destinatário definido nas Definições.', 'ppi-core' ) );
			return 'not_configured';
		}
		$m       = static fn( $k ) => (string) get_post_meta( $lead_id, '_ppi_' . $k, true );
		$types   = Leads::types();
		$subject = sprintf( '[PPI] %s%s — %s', $types[ $m( 'type' ) ] ?? $m( 'type' ), $m( 'property_ref' ) ? ' · ref. ' . $m( 'property_ref' ) : '', $m( 'name' ) );
		$lines   = array(
			'Tipo: ' . ( $types[ $m( 'type' ) ] ?? $m( 'type' ) ),
			'Nome: ' . $m( 'name' ),
			'Email: ' . $m( 'email' ),
			'Telefone: ' . ( $m( 'phone' ) ?: '—' ),
			'Contacto preferido: ' . ( $m( 'contact_pref' ) ?: 'indiferente' ),
			'Idioma: ' . $m( 'lang' ),
		);
		if ( $m( 'property_ref' ) ) {
			$lines[] = 'Imóvel: ref. ' . $m( 'property_ref' ) . ' — ' . $m( 'property_title' );
			$lines[] = 'URL: ' . $m( 'property_url' );
		}
		if ( $m( 'visit_day' ) || $m( 'visit_period' ) ) {
			$lines[] = 'Preferência de visita (por confirmar): ' . ( $m( 'visit_day' ) ?: 'sem dia' ) . ' · ' . ( $m( 'visit_period' ) ?: 'indiferente' );
		}
		foreach ( get_post_meta( $lead_id ) as $k => $v ) {
			if ( str_starts_with( $k, '_ppi_p_' ) && '' !== $v[0] ) {
				$lines[] = substr( $k, 7 ) . ': ' . $v[0];
			}
		}
		$lines[] = '';
		$lines[] = 'Mensagem:';
		$lines[] = $m( 'message' ) ?: '—';
		$lines[] = '';
		$lines[] = 'Marketing: ' . ( $m( 'marketing' ) ? 'aceitou receber novidades' : 'não' );
		$lines[] = 'Ver no painel: ' . admin_url( 'post.php?post=' . $lead_id . '&action=edit' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( is_email( $m( 'email' ) ) ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>' ), '', $m( 'name' ) ) . ' <' . $m( 'email' ) . '>';
		}
		self::$last_error = null;
		$ok = false;
		try {
			$ok = wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
		} catch ( \Throwable $e ) {
			self::$last_error = $e->getMessage();
		}
		update_post_meta( $lead_id, '_ppi_mail_attempts', $attempts + 1 );
		update_post_meta( $lead_id, '_ppi_mail_last', time() );
		if ( $ok ) {
			update_post_meta( $lead_id, '_ppi_mail_status', 'sent' );
			update_post_meta( $lead_id, '_ppi_mail_to', self::is_production() ? $to : self::test_recipient() . ' (teste)' );
			delete_post_meta( $lead_id, '_ppi_mail_error' );
			return 'sent';
		}
		update_post_meta( $lead_id, '_ppi_mail_status', 'failed' );
		update_post_meta( $lead_id, '_ppi_mail_error', mb_substr( (string) ( self::$last_error ?: 'wp_mail devolveu false' ), 0, 300 ) );
		return 'failed';
	}

	public static function retry_failed(): int {
		$ids = get_posts( array(
			'post_type' => 'ppi_lead', 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 20, 'no_found_rows' => true,
			'meta_query' => array(
				array( 'key' => '_ppi_mail_status', 'value' => array( 'failed', 'pending' ), 'compare' => 'IN' ),
				array( 'key' => '_ppi_mail_attempts', 'value' => self::MAX_ATTEMPTS, 'compare' => '<', 'type' => 'NUMERIC' ),
			),
		) );
		$n = 0;
		foreach ( $ids as $id ) {
			$last = (int) get_post_meta( $id, '_ppi_mail_last', true );
			$att  = (int) get_post_meta( $id, '_ppi_mail_attempts', true );
			if ( time() - $last < 300 * max( 1, $att ) ) { // espera crescente
				continue;
			}
			if ( 'sent' === self::notify( $id ) ) {
				$n++;
			}
		}
		return $n;
	}
}

Mail::init();
