<?php
/**
 * Pedidos de contacto (leads).
 *
 * Fluxo: validação no servidor → antispam (honeypot, tempo mínimo, limite por IP) → deduplicação →
 * gravação privada do lead → tentativa de notificação por email (estado registado) → resposta.
 * O lead nunca depende do email: se o envio falhar, fica no admin com estado «falhou» e é repetido.
 * Funciona sem JavaScript (POST + redirecionamento) e com JavaScript (fetch, resposta JSON).
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Leads {

	public const RATE_MAX    = 6;    // pedidos
	public const RATE_WINDOW = 900;  // segundos
	public const MIN_SECONDS = 3;
	public const CV_MAX      = 2097152; // 2 MB

	public static function types(): array {
		return array(
			'visit'       => __( 'Pedido de visita', 'ppi-core' ),
			'info'        => __( 'Pedido de informações', 'ppi-core' ),
			'valuation'   => __( 'Pedido de avaliação (vender)', 'ppi-core' ),
			'search'      => __( 'Procura de imóvel (comprar)', 'ppi-core' ),
			'rental'      => __( 'Arrendamento', 'ppi-core' ),
			'exchange'    => __( 'Permuta', 'ppi-core' ),
			'recruitment' => __( 'Candidatura', 'ppi-core' ),
			'contact'     => __( 'Contacto geral', 'ppi-core' ),
		);
	}

	public static function statuses(): array {
		return array(
			'new'         => __( 'Novo', 'ppi-core' ),
			'in_progress' => __( 'Em tratamento', 'ppi-core' ),
			'answered'    => __( 'Respondido', 'ppi-core' ),
			'closed'      => __( 'Fechado', 'ppi-core' ),
			'spam'        => __( 'Spam', 'ppi-core' ),
		);
	}

	public static function init(): void {
		add_action( 'admin_post_nopriv_ppi_lead', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_ppi_lead', array( __CLASS__, 'handle' ) );
	}

	/* ------------------------------------------------------------------ Formulários */

	private static function field( string $type, string $name, string $label, array $o = array() ): string {
		$id   = 'ppi-' . $o['form'] . '-' . $name;
		$req  = ! empty( $o['required'] );
		$val  = $o['value'] ?? '';
		$err  = $o['error'] ?? '';
		$help = $o['help'] ?? '';
		$desc = array();
		if ( $help ) {
			$desc[] = $id . '-help';
		}
		if ( $err ) {
			$desc[] = $id . '-err';
		}
		$aria = ( $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '' ) . ( $err ? ' aria-invalid="true"' : '' ) . ( $req ? ' required aria-required="true"' : '' );
		$h  = '<div class="ppi-field ppi-field--' . esc_attr( $name ) . ( $err ? ' has-error' : '' ) . '"' . ( ! empty( $o['wrap_attr'] ) ? ' ' . $o['wrap_attr'] : '' ) . '>';
		$h .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $req ? ' <span class="ppi-req" aria-hidden="true">*</span>' : ' <span class="ppi-opt">' . esc_html__( '(opcional)', 'ppi-core' ) . '</span>' ) . '</label>';
		switch ( $type ) {
			case 'textarea':
				$h .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . (int) ( $o['rows'] ?? 4 ) . '" maxlength="3000"' . $aria . '>' . esc_textarea( (string) $val ) . '</textarea>';
				break;
			case 'select':
				$h .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $aria . '><option value="">' . esc_html( $o['placeholder'] ?? __( 'Selecionar', 'ppi-core' ) ) . '</option>';
				foreach ( $o['options'] as $k => $l ) {
					$h .= '<option value="' . esc_attr( (string) $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				$h .= '</select>';
				break;
			case 'file':
				$h .= '<input type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" accept="application/pdf,.pdf"' . $aria . '>';
				break;
			default:
				$extra = '';
				foreach ( array( 'autocomplete', 'inputmode', 'min', 'max', 'maxlength', 'placeholder' ) as $a ) {
					if ( isset( $o[ $a ] ) ) {
						$extra .= ' ' . $a . '="' . esc_attr( (string) $o[ $a ] ) . '"';
					}
				}
				$h .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '"' . $extra . $aria . '>';
		}
		if ( $help ) {
			$h .= '<p class="ppi-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</p>';
		}
		if ( $err ) {
			$h .= '<p class="ppi-error" id="' . esc_attr( $id ) . '-err">' . esc_html( $err ) . '</p>';
		}
		return $h . '</div>';
	}

	private static function location_options(): array {
		$o = array();
		foreach ( Search::locations_tree() as $n ) {
			$o[ $n['term']->name ] = $n['term']->name;
		}
		foreach ( array( 'Vila do Bispo', 'Lagos', 'Aljezur', 'Portimão', 'Monchique' ) as $m ) {
			$o[ $m ] = $m;
		}
		ksort( $o );
		$o['other'] = __( 'Outra localização', 'ppi-core' );
		return $o;
	}

	private static function type_options(): array {
		$o = array();
		foreach ( Migrations::type_terms() as $slug => [ $pt, $en ] ) {
			$o[ $slug ] = 'en' === I18n::current() ? $en : $pt;
		}
		return $o;
	}

	/**
	 * Formulário de contacto. $type: visit|info|valuation|search|rental|exchange|recruitment|contact.
	 * $ctx['property']: Property quando o pedido é sobre um imóvel (referência e URL derivadas no servidor).
	 */
	public static function form( string $type, array $ctx = array() ): string {
		if ( ! isset( self::types()[ $type ] ) ) {
			$type = 'contact';
		}
		/** @var Property|null $prop */
		$prop  = $ctx['property'] ?? null;
		$fid   = $type . ( $prop ? '-' . $prop->id : '' );
		$state = self::flash_state( $fid );
		$v     = $state['values'] ?? array();
		$e     = $state['errors'] ?? array();
		$f     = static fn( $t, $n, $l, $o = array() ) => self::field( $t, $n, $l, array_merge( array( 'form' => $fid, 'value' => $v[ $n ] ?? ( $o['value'] ?? '' ), 'error' => $e[ $n ] ?? '' ), $o ) );
		$is_prop = (bool) $prop;
		ob_start();
		?>
		<form class="ppi-form ppi-form--<?php echo esc_attr( $type ); ?>" id="ppi-form-<?php echo esc_attr( $fid ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" <?php echo 'recruitment' === $type ? 'enctype="multipart/form-data"' : ''; ?> novalidate data-ppi-form>
			<?php if ( ! empty( $state['ok'] ) ) : ?>
				<div class="ppi-form__done" role="status" tabindex="-1" data-ppi-focus><?php echo esc_html( self::success_message( $type ) ); ?></div>
			<?php endif; ?>
			<?php if ( $e ) : ?>
				<div class="ppi-form__errors" role="alert" tabindex="-1" data-ppi-focus>
					<p><?php esc_html_e( 'Não foi possível enviar. Verifique os campos assinalados.', 'ppi-core' ); ?></p>
					<?php if ( isset( $e['_form'] ) ) : ?><p><?php echo esc_html( $e['_form'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="ppi-form__status" role="status" aria-live="polite" data-ppi-status></div>
			<input type="hidden" name="action" value="ppi_lead">
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $fid ); ?>">
			<input type="hidden" name="lang" value="<?php echo esc_attr( I18n::current() ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>" data-ppi-token>
			<input type="hidden" name="ts" value="<?php echo esc_attr( self::sign_time() ); ?>">
			<input type="hidden" name="return" value="<?php echo esc_url( self::current_url() ); ?>">
			<?php if ( $prop ) : ?>
				<input type="hidden" name="property_id" value="<?php echo (int) $prop->id; ?>">
				<p class="ppi-form__about"><?php /* translators: %s reference */ printf( esc_html__( 'Sobre o imóvel ref. %s', 'ppi-core' ), '<strong>' . esc_html( $prop->reference() ) . '</strong>' ); ?></p>
				<fieldset class="ppi-field ppi-field--mode">
					<legend><?php esc_html_e( 'O que pretende?', 'ppi-core' ); ?></legend>
					<?php $mode = $v['type'] ?? ( 'info' === $type ? 'info' : 'visit' ); ?>
					<label class="ppi-radio"><input type="radio" name="type" value="visit" <?php checked( $mode, 'visit' ); ?> data-ppi-mode> <?php esc_html_e( 'Pedir visita', 'ppi-core' ); ?></label>
					<label class="ppi-radio"><input type="radio" name="type" value="info" <?php checked( $mode, 'info' ); ?> data-ppi-mode> <?php esc_html_e( 'Pedir informações', 'ppi-core' ); ?></label>
				</fieldset>
			<?php else : ?>
				<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
			<?php endif; ?>

			<?php
			echo $f( 'text', 'name', __( 'Nome', 'ppi-core' ), array( 'required' => true, 'autocomplete' => 'name', 'maxlength' => 120 ) ); // phpcs:ignore
			echo $f( 'email', 'email', __( 'Email', 'ppi-core' ), array( 'required' => true, 'autocomplete' => 'email', 'maxlength' => 190 ) ); // phpcs:ignore
			echo $f( 'tel', 'phone', __( 'Telefone', 'ppi-core' ), array( 'required' => 'recruitment' === $type, 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 30, 'help' => 'recruitment' === $type ? '' : __( 'Só se preferir que lhe liguemos.', 'ppi-core' ) ) ); // phpcs:ignore

			if ( $is_prop || 'visit' === $type ) {
				echo '<div class="ppi-form__visit" data-ppi-visit-fields>';
				echo $f( 'date', 'visit_day', __( 'Dia preferido para a visita', 'ppi-core' ), array( 'min' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ), 'help' => __( 'É uma preferência: a PPI confirma a disponibilidade consigo.', 'ppi-core' ) ) ); // phpcs:ignore
				echo $f( 'select', 'visit_period', __( 'Período', 'ppi-core' ), array( 'options' => array( 'morning' => __( 'Manhã', 'ppi-core' ), 'afternoon' => __( 'Tarde', 'ppi-core' ), 'any' => __( 'Indiferente', 'ppi-core' ) ), 'placeholder' => __( 'Indiferente', 'ppi-core' ) ) ); // phpcs:ignore
				echo '</div>';
			}
			if ( 'valuation' === $type ) {
				echo $f( 'select', 'p_location', __( 'Localização do imóvel', 'ppi-core' ), array( 'required' => true, 'options' => self::location_options() ) ); // phpcs:ignore
				echo $f( 'text', 'p_place', __( 'Localidade, morada ou zona', 'ppi-core' ), array( 'maxlength' => 200, 'autocomplete' => 'street-address' ) ); // phpcs:ignore
				echo $f( 'select', 'p_type', __( 'Tipo de imóvel', 'ppi-core' ), array( 'required' => true, 'options' => self::type_options() ) ); // phpcs:ignore
				echo $f( 'select', 'p_bedrooms', __( 'Quartos', 'ppi-core' ), array( 'options' => array( '0' => 'T0', '1' => 'T1', '2' => 'T2', '3' => 'T3', '4' => 'T4', '5' => 'T5+' ), 'placeholder' => __( 'Não se aplica', 'ppi-core' ) ) ); // phpcs:ignore
				echo $f( 'text', 'p_area', __( 'Área aproximada (m²)', 'ppi-core' ), array( 'inputmode' => 'numeric', 'maxlength' => 12, 'help' => __( 'Construção ou terreno; indique na mensagem se não souber.', 'ppi-core' ) ) ); // phpcs:ignore
				echo $f( 'select', 'p_timing', __( 'Quando pretende vender?', 'ppi-core' ), array( 'options' => array( 'now' => __( 'O quanto antes', 'ppi-core' ), '6m' => __( 'Nos próximos 6 meses', 'ppi-core' ), 'later' => __( 'Ainda estou a avaliar', 'ppi-core' ) ) ) ); // phpcs:ignore
			}
			if ( 'search' === $type ) {
				echo $f( 'select', 'p_location', __( 'Zona preferida', 'ppi-core' ), array( 'options' => self::location_options(), 'placeholder' => __( 'Qualquer zona', 'ppi-core' ) ) ); // phpcs:ignore
				echo $f( 'select', 'p_type', __( 'Tipo de imóvel', 'ppi-core' ), array( 'options' => self::type_options(), 'placeholder' => __( 'Qualquer tipo', 'ppi-core' ) ) ); // phpcs:ignore
				echo $f( 'select', 'p_bedrooms', __( 'Quartos (mínimo)', 'ppi-core' ), array( 'options' => array( '1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+', '5' => '5+' ), 'placeholder' => __( 'Indiferente', 'ppi-core' ) ) ); // phpcs:ignore
				echo $f( 'text', 'p_budget', __( 'Orçamento máximo (€)', 'ppi-core' ), array( 'inputmode' => 'numeric', 'maxlength' => 14 ) ); // phpcs:ignore
			}
			if ( 'rental' === $type ) {
				echo $f( 'select', 'p_intent', __( 'Situação', 'ppi-core' ), array( 'required' => true, 'options' => array( 'seek' => __( 'Procuro um imóvel para arrendar', 'ppi-core' ), 'offer' => __( 'Tenho um imóvel para arrendar', 'ppi-core' ) ) ) ); // phpcs:ignore
				echo $f( 'select', 'p_location', __( 'Zona', 'ppi-core' ), array( 'options' => self::location_options() ) ); // phpcs:ignore
				echo $f( 'select', 'p_type', __( 'Tipo de imóvel', 'ppi-core' ), array( 'options' => self::type_options() ) ); // phpcs:ignore
			}
			if ( 'exchange' === $type ) {
				echo $f( 'text', 'p_have', __( 'Imóvel que tem (tipo e zona)', 'ppi-core' ), array( 'required' => true, 'maxlength' => 200 ) ); // phpcs:ignore
				echo $f( 'text', 'p_want', __( 'Imóvel que procura (tipo e zona)', 'ppi-core' ), array( 'required' => true, 'maxlength' => 200 ) ); // phpcs:ignore
			}
			if ( 'recruitment' === $type ) {
				echo $f( 'select', 'p_situation', __( 'Situação atual', 'ppi-core' ), array( 'required' => true, 'options' => array( 'employed' => __( 'Empregado', 'ppi-core' ), 'unemployed' => __( 'Desempregado', 'ppi-core' ), 'student' => __( 'Estudante', 'ppi-core' ), 'other' => __( 'Outra', 'ppi-core' ) ) ) ); // phpcs:ignore
				echo $f( 'file', 'cv', __( 'Curriculum vitae (PDF, máx. 2 MB)', 'ppi-core' ), array( 'required' => true ) ); // phpcs:ignore
			}
			$msg_default = $prop ? sprintf( /* translators: %s reference */ __( 'Gostaria de saber mais sobre o imóvel ref. %s.', 'ppi-core' ), $prop->reference() ) : '';
			echo $f( 'textarea', 'message', __( 'Mensagem', 'ppi-core' ), array( 'required' => 'contact' === $type, 'value' => $msg_default, 'rows' => $prop ? 3 : 5 ) ); // phpcs:ignore
			echo $f( 'select', 'contact_pref', __( 'Como prefere ser contactado?', 'ppi-core' ), array( 'options' => array( 'email' => __( 'Email', 'ppi-core' ), 'phone' => __( 'Telefone', 'ppi-core' ) ), 'placeholder' => __( 'Indiferente', 'ppi-core' ) ) ); // phpcs:ignore
			?>
			<div class="ppi-hp" aria-hidden="true"><label for="ppi-<?php echo esc_attr( $fid ); ?>-website"><?php esc_html_e( 'Não preencher', 'ppi-core' ); ?></label><input type="text" id="ppi-<?php echo esc_attr( $fid ); ?>-website" name="website" tabindex="-1" autocomplete="off"></div>
			<label class="ppi-check ppi-check--marketing"><input type="checkbox" name="marketing" value="1" <?php checked( ! empty( $v['marketing'] ) ); ?>> <span><?php esc_html_e( 'Quero receber novidades de imóveis da PPI por email (opcional; pode cancelar a qualquer momento).', 'ppi-core' ); ?></span></label>
			<p class="ppi-privacy"><?php /* translators: %s privacy policy link */ printf( esc_html__( 'Usamos os seus dados apenas para responder a este pedido. Consulte a %s.', 'ppi-core' ), '<a href="' . esc_url( I18n::page_url( 'privacidade' ) ) . '">' . esc_html__( 'Política de Privacidade', 'ppi-core' ) . '</a>' ); ?></p>
			<button type="submit" class="ppi-btn ppi-btn--primary ppi-form__submit" data-label-busy="<?php esc_attr_e( 'A enviar…', 'ppi-core' ); ?>"><?php echo esc_html( self::submit_label( $type, $is_prop ) ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private static function submit_label( string $type, bool $is_prop ): string {
		if ( $is_prop ) {
			return __( 'Enviar pedido', 'ppi-core' );
		}
		return array(
			'valuation' => __( 'Solicitar avaliação', 'ppi-core' ), 'search' => __( 'Enviar pedido de procura', 'ppi-core' ),
			'recruitment' => __( 'Enviar candidatura', 'ppi-core' ),
		)[ $type ] ?? __( 'Enviar mensagem', 'ppi-core' );
	}

	public static function success_message( string $type ): string {
		if ( 'visit' === $type ) {
			return __( 'Recebemos o seu pedido de visita. A visita ainda não está marcada: a equipa PPI vai contactá-lo para confirmar o dia e a hora.', 'ppi-core' );
		}
		if ( 'recruitment' === $type ) {
			return __( 'Recebemos a sua candidatura. Obrigado pelo interesse na PPI.', 'ppi-core' );
		}
		return __( 'Recebemos a sua mensagem. A equipa PPI vai responder-lhe com a maior brevidade possível.', 'ppi-core' );
	}

	private static function current_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$uri = remove_query_arg( array( 'ppi_form', 'ppi_state' ), $uri );
		return home_url( $uri );
	}

	private static function sign_time(): string {
		$t = (string) time();
		return $t . '.' . substr( hash_hmac( 'sha256', $t, wp_salt( 'nonce' ) ), 0, 16 );
	}

	private static function check_time( string $sig ): bool {
		[ $t, $h ] = array_pad( explode( '.', $sig, 2 ), 2, '' );
		if ( ! ctype_digit( $t ) || ! hash_equals( substr( hash_hmac( 'sha256', $t, wp_salt( 'nonce' ) ), 0, 16 ), $h ) ) {
			return false;
		}
		return ( time() - (int) $t ) >= self::MIN_SECONDS;
	}

	/** Estado após POST sem JavaScript (mensagem/erros), lido uma vez. */
	private static function flash_state( string $fid ): array {
		$k = isset( $_GET['ppi_state'] ) ? sanitize_key( wp_unslash( $_GET['ppi_state'] ) ) : ''; // phpcs:ignore
		if ( ! $k ) {
			return array();
		}
		$st = get_transient( 'ppi_fs_' . $k );
		if ( ! is_array( $st ) || ( $st['form'] ?? '' ) !== $fid ) {
			return array();
		}
		delete_transient( 'ppi_fs_' . $k );
		return $st;
	}

	/* ------------------------------------------------------------------ Receção */

	private static function wants_json(): bool {
		return isset( $_SERVER['HTTP_ACCEPT'] ) && str_contains( (string) $_SERVER['HTTP_ACCEPT'], 'application/json' );
	}

	private static function client_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	public static function handle(): void {
		$in    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- formulário público; ver docs/seguranca.md
		$fid   = sanitize_key( (string) ( $in['form_id'] ?? '' ) );
		$fid   = preg_replace( '/[^a-z0-9\-]/', '', (string) ( $in['form_id'] ?? '' ) );
		$ret   = wp_validate_redirect( esc_url_raw( (string) ( $in['return'] ?? '' ) ), home_url( '/' ) );
		$res   = self::process( $in, $_FILES );
		if ( self::wants_json() ) {
			wp_send_json( array( 'ok' => $res['ok'], 'message' => $res['message'], 'errors' => $res['errors'] ), $res['ok'] ? 200 : ( $res['code'] ?? 422 ) );
		}
		$key = strtolower( wp_generate_password( 20, false ) );
		set_transient( 'ppi_fs_' . $key, array( 'form' => $fid, 'ok' => $res['ok'], 'errors' => $res['errors'], 'values' => $res['ok'] ? array() : $res['values'] ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'ppi_state', $key, $ret ) . '#ppi-form-' . $fid, 303 );
		exit;
	}

	/**
	 * Valida e grava. Devolve ok, message, errors, values, lead_id.
	 * Público para testes automáticos.
	 */
	public static function process( array $in, array $files = array() ): array {
		$type   = sanitize_key( (string) ( $in['type'] ?? '' ) );
		$errors = array();
		$values = array();
		if ( ! isset( self::types()[ $type ] ) ) {
			return array( 'ok' => false, 'message' => __( 'Pedido inválido.', 'ppi-core' ), 'errors' => array( '_form' => __( 'Pedido inválido.', 'ppi-core' ) ), 'values' => array(), 'code' => 400 );
		}
		$lang = in_array( $in['lang'] ?? '', I18n::languages(), true ) ? $in['lang'] : I18n::DEFAULT_LANG;
		if ( function_exists( 'PLL' ) && PLL()->model->get_language( $lang ) ) {
			PLL()->curlang = PLL()->model->get_language( $lang );
		}
		switch_to_locale( 'en' === $lang ? 'en_GB' : 'pt_PT' );

		// Antispam: campo isca preenchido ou envio instantâneo => resposta neutra, sem gravar.
		if ( ! empty( $in['website'] ) || ! self::check_time( (string) ( $in['ts'] ?? '' ) ) ) {
			return array( 'ok' => true, 'message' => self::success_message( $type ), 'errors' => array(), 'values' => array(), 'spam' => true );
		}

		$text = static fn( $k, $max = 200 ) => mb_substr( sanitize_text_field( (string) ( $in[ $k ] ?? '' ) ), 0, $max );
		$values['type']    = $type;
		$values['name']    = $text( 'name', 120 );
		$values['email']   = sanitize_email( (string) ( $in['email'] ?? '' ) );
		$values['phone']   = preg_replace( '/[^\d+\s()\-]/', '', $text( 'phone', 30 ) );
		$values['message'] = mb_substr( sanitize_textarea_field( (string) ( $in['message'] ?? '' ) ), 0, 3000 );
		$values['contact_pref'] = in_array( $in['contact_pref'] ?? '', array( 'email', 'phone' ), true ) ? $in['contact_pref'] : '';
		$values['marketing']    = ! empty( $in['marketing'] ) ? 1 : 0;

		if ( mb_strlen( $values['name'] ) < 2 ) {
			$errors['name'] = __( 'Indique o seu nome.', 'ppi-core' );
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Indique um email válido (ex.: nome@dominio.pt).', 'ppi-core' );
		}
		if ( $values['phone'] && strlen( preg_replace( '/\D/', '', $values['phone'] ) ) < 9 ) {
			$errors['phone'] = __( 'O telefone parece incompleto.', 'ppi-core' );
		}
		if ( ( 'recruitment' === $type || 'phone' === $values['contact_pref'] ) && ! $values['phone'] ) {
			$errors['phone'] = __( 'Indique um telefone para o podermos contactar.', 'ppi-core' );
		}
		if ( 'contact' === $type && mb_strlen( $values['message'] ) < 5 ) {
			$errors['message'] = __( 'Escreva a sua mensagem.', 'ppi-core' );
		}

		// Imóvel: referência e URL derivam do ID no servidor (nunca de campos do cliente).
		$prop = null;
		if ( ! empty( $in['property_id'] ) ) {
			$prop = Property::from( absint( $in['property_id'] ) );
			if ( ! $prop || 'publish' !== $prop->post->post_status ) {
				$errors['_form'] = __( 'O imóvel indicado não está disponível.', 'ppi-core' );
				$prop = null;
			}
		}
		if ( in_array( $type, array( 'visit', 'info' ), true ) && ! $prop ) {
			$errors['_form'] = $errors['_form'] ?? __( 'Falta indicar o imóvel.', 'ppi-core' );
		}
		if ( 'visit' === $type ) {
			$day = $text( 'visit_day', 10 );
			if ( $day ) {
				$d = \DateTime::createFromFormat( 'Y-m-d', $day );
				if ( ! $d || $d->format( 'Y-m-d' ) !== $day || $day <= gmdate( 'Y-m-d' ) || $day > gmdate( 'Y-m-d', strtotime( '+1 year' ) ) ) {
					$errors['visit_day'] = __( 'Escolha um dia a partir de amanhã.', 'ppi-core' );
				} else {
					$values['visit_day'] = $day;
				}
			}
			$values['visit_period'] = in_array( $in['visit_period'] ?? '', array( 'morning', 'afternoon', 'any' ), true ) ? $in['visit_period'] : 'any';
		}
		$extra = array(
			'valuation'   => array( 'p_location' => 1, 'p_place' => 0, 'p_type' => 1, 'p_bedrooms' => 0, 'p_area' => 0, 'p_timing' => 0 ),
			'search'      => array( 'p_location' => 0, 'p_type' => 0, 'p_bedrooms' => 0, 'p_budget' => 0 ),
			'rental'      => array( 'p_intent' => 1, 'p_location' => 0, 'p_type' => 0 ),
			'exchange'    => array( 'p_have' => 1, 'p_want' => 1 ),
			'recruitment' => array( 'p_situation' => 1 ),
		)[ $type ] ?? array();
		foreach ( $extra as $k => $req ) {
			$values[ $k ] = $text( $k, 200 );
			if ( $req && '' === $values[ $k ] ) {
				$errors[ $k ] = __( 'Campo obrigatório.', 'ppi-core' );
			}
		}
		foreach ( array( 'p_area', 'p_budget' ) as $k ) {
			if ( ! empty( $values[ $k ] ) && ! preg_match( '/^[\d\s.,]{1,14}$/', $values[ $k ] ) ) {
				$errors[ $k ] = __( 'Indique apenas números.', 'ppi-core' );
			}
		}

		$cv = null;
		if ( 'recruitment' === $type ) {
			$cv = self::validate_cv( $files['cv'] ?? null );
			if ( is_wp_error( $cv ) ) {
				$errors['cv'] = $cv->get_error_message();
				$cv = null;
			}
		}

		if ( $errors ) {
			return array( 'ok' => false, 'message' => __( 'Verifique os campos assinalados.', 'ppi-core' ), 'errors' => $errors, 'values' => $values );
		}

		// Limite por origem (IP com hash; o IP não é guardado).
		$client = self::client_hash();
		$rk     = 'ppi_rl_' . substr( $client, 0, 32 );
		$hits   = (int) get_transient( $rk );
		if ( $hits >= self::RATE_MAX ) {
			return array( 'ok' => false, 'message' => __( 'Recebemos vários pedidos seguidos deste dispositivo. Aguarde uns minutos ou ligue-nos.', 'ppi-core' ), 'errors' => array( '_form' => __( 'Demasiados pedidos seguidos. Tente de novo dentro de 15 minutos ou contacte-nos por telefone.', 'ppi-core' ) ), 'values' => $values, 'code' => 429 );
		}

		// Deduplicação: mesmo token+conteúdo (duplo clique, reenvio) ou mesmo conteúdo em 10 min.
		$token = preg_replace( '/[^a-f0-9\-]/', '', strtolower( (string) ( $in['token'] ?? '' ) ) );
		$fp    = hash( 'sha256', implode( '|', array( $type, strtolower( $values['email'] ), $prop ? $prop->id : 0, $values['message'], $values['visit_day'] ?? '' ) ) );
		$dupe  = get_posts( array(
			'post_type' => 'ppi_lead', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true,
			'meta_query' => array( 'relation' => 'OR',
				array( 'key' => '_ppi_dedupe', 'value' => hash( 'sha256', $token . '|' . $fp ) ),
				array( 'relation' => 'AND', array( 'key' => '_ppi_fingerprint', 'value' => $fp ), array( 'key' => '_ppi_received', 'value' => time() - 600, 'compare' => '>', 'type' => 'NUMERIC' ) ),
			),
		) );
		if ( $dupe ) {
			return array( 'ok' => true, 'message' => self::success_message( $type ), 'errors' => array(), 'values' => array(), 'lead_id' => (int) $dupe[0], 'duplicate' => true );
		}

		$lead_id = self::store( $type, $values, $prop, $lang, $token, $fp );
		if ( ! $lead_id ) {
			return array( 'ok' => false, 'message' => __( 'Não foi possível registar o pedido. Tente novamente ou contacte-nos por telefone.', 'ppi-core' ), 'errors' => array( '_form' => __( 'Erro ao registar o pedido.', 'ppi-core' ) ), 'values' => $values, 'code' => 500 );
		}
		set_transient( $rk, $hits + 1, self::RATE_WINDOW );
		if ( $cv ) {
			self::attach_cv( $lead_id, $cv );
		}
		Mail::notify( $lead_id );
		Consent::track_server( 'lead', $type );
		return array( 'ok' => true, 'message' => self::success_message( $type ), 'errors' => array(), 'values' => array(), 'lead_id' => $lead_id );
	}

	private static function store( string $type, array $values, ?Property $prop, string $lang, string $token, string $fp ): int {
		$title = sprintf( '%s — %s%s', self::types()[ $type ], $values['name'], $prop ? ' — ref. ' . $prop->reference() : '' );
		$id = wp_insert_post( array(
			'post_type'   => 'ppi_lead',
			'post_status' => 'private',
			'post_title'  => $title,
			'post_author' => 0,
		), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		$meta = array(
			'_ppi_type' => $type, '_ppi_name' => $values['name'], '_ppi_email' => $values['email'], '_ppi_phone' => $values['phone'],
			'_ppi_message' => $values['message'], '_ppi_contact_pref' => $values['contact_pref'], '_ppi_marketing' => $values['marketing'],
			'_ppi_lang' => $lang, '_ppi_status' => 'new', '_ppi_assignee' => 0, '_ppi_received' => time(),
			'_ppi_dedupe' => hash( 'sha256', $token . '|' . $fp ), '_ppi_fingerprint' => $fp, '_ppi_privacy_notice' => gmdate( 'c' ),
			'_ppi_mail_status' => 'pending', '_ppi_mail_attempts' => 0,
		);
		if ( $prop ) {
			$meta += array( '_ppi_property_id' => $prop->id, '_ppi_property_ref' => $prop->reference(), '_ppi_property_url' => $prop->url(), '_ppi_property_title' => $prop->title() );
		}
		foreach ( $values as $k => $v ) {
			if ( str_starts_with( $k, 'p_' ) || str_starts_with( $k, 'visit_' ) ) {
				$meta[ '_ppi_' . $k ] = $v;
			}
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		return (int) $id;
	}

	/** Valida o CV: PDF real (assinatura + extensão + tipo), até 2 MB. */
	private static function validate_cv( $file ) {
		if ( ! $file || ! is_array( $file ) || UPLOAD_ERR_NO_FILE === ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new \WP_Error( 'cv', __( 'Anexe o seu CV em PDF.', 'ppi-core' ) );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'cv', __( 'O envio do ficheiro falhou. Tente novamente.', 'ppi-core' ) );
		}
		if ( $file['size'] > self::CV_MAX ) {
			return new \WP_Error( 'cv', __( 'O ficheiro excede 2 MB.', 'ppi-core' ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'pdf' => 'application/pdf' ) );
		$head  = (string) file_get_contents( $file['tmp_name'], false, null, 0, 5 );
		if ( 'pdf' !== $check['ext'] || '%PDF-' !== $head ) {
			return new \WP_Error( 'cv', __( 'Só são aceites ficheiros PDF.', 'ppi-core' ) );
		}
		return $file;
	}

	private static function attach_cv( int $lead_id, array $file ): void {
		$dir  = Migrations::private_dir();
		$name = wp_generate_password( 24, false ) . '.pdf';
		if ( move_uploaded_file( $file['tmp_name'], "$dir/$name" ) ) {
			chmod( "$dir/$name", 0640 );
			update_post_meta( $lead_id, '_ppi_cv_file', $name );
		}
	}
}

Leads::init();
