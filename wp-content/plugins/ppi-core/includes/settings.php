<?php
/**
 * Definições da agência, notificações, privacidade, mapa, medição e equipa (Imóveis → Definições).
 * Contactos opcionais vazios (WhatsApp, horário) não são apresentados.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'ppi_settings';

	public static function defaults(): array {
		return array(
			'agency_name'     => 'PPI — Portuguese Property Investments',
			'legal_name'      => 'Portuguese Property Investments — Sociedade de Mediação Imobiliária, Unipessoal Lda',
			'ami'             => '9454',
			'address'         => "E.N. 125 — Entrada do Parque da Floresta, 1\n8650-060 Budens, Vila do Bispo\nFaro, Portugal",
			'phone_landline'  => '+351 282 698 621',
			'phone_mobile'    => '+351 964 981 661',
			'email'           => 'info@ppiestateagents.com',
			'whatsapp'        => '',
			'hours'           => '',
			'facebook'        => 'https://www.facebook.com/ppi.lda/',
			'instagram'       => 'https://www.instagram.com/ppiestateagents/',
			'complaints_url'  => 'https://www.livroreclamacoes.pt/',
			'whistle_url'     => 'https://denunciasinternas.pt/@ppi/informar',
			'notify_to'       => 'info@ppiestateagents.com',
			'retention_days'  => 730,
			'map_provider'    => 'none',
			'ga4_id'          => '',
			'hero_reference'  => '',
			'hero_image'      => 0,
			'team'            => array(),
		);
	}

	public static function install_defaults(): void {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults(), '', true );
		}
	}

	public static function all(): array {
		$o = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $o ) ? $o : array() );
	}

	public static function get( string $k ) {
		return self::all()[ $k ] ?? null;
	}

	public static function update( array $changes ): void {
		update_option( self::OPTION, array_merge( self::all(), $changes ) );
	}

	/** Membros da equipa validados para publicação. */
	public static function team( bool $only_validated = true ): array {
		$t = (array) self::get( 'team' );
		return array_values( array_filter( $t, static fn( $m ) => ! $only_validated || ! empty( $m['validated'] ) ) );
	}

	public static function init(): void {
		add_action( 'admin_menu', static function () {
			add_submenu_page( 'edit.php?post_type=ppi_property', __( 'Definições PPI', 'ppi-core' ), __( 'Definições', 'ppi-core' ), 'ppi_settings', 'ppi-settings', array( __CLASS__, 'page' ) );
		} );
		add_action( 'admin_post_ppi_settings', array( __CLASS__, 'save' ) );
	}

	private static function text_fields(): array {
		return array(
			'agency_name' => __( 'Nome comercial', 'ppi-core' ), 'legal_name' => __( 'Designação legal', 'ppi-core' ), 'ami' => __( 'Licença AMI', 'ppi-core' ),
			'phone_landline' => __( 'Telefone fixo', 'ppi-core' ), 'phone_mobile' => __( 'Telemóvel', 'ppi-core' ), 'email' => __( 'Email público', 'ppi-core' ),
			'whatsapp' => __( 'WhatsApp (só se confirmado)', 'ppi-core' ), 'facebook' => 'Facebook', 'instagram' => 'Instagram',
			'complaints_url' => __( 'Livro de Reclamações (URL)', 'ppi-core' ), 'whistle_url' => __( 'Canal de denúncias (URL)', 'ppi-core' ),
			'notify_to' => __( 'Enviar notificações de contactos para', 'ppi-core' ), 'retention_days' => __( 'Conservar contactos (dias)', 'ppi-core' ),
			'ga4_id' => __( 'Google Analytics 4 (ID, opcional — só após consentimento)', 'ppi-core' ), 'hero_reference' => __( 'Imóvel da abertura (referência)', 'ppi-core' ),
		);
	}

	public static function page(): void {
		if ( ! current_user_can( 'ppi_settings' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'ppi-core' ), 403 );
		}
		$s = self::all();
		?>
		<div class="wrap ppi-admin">
			<h1><?php esc_html_e( 'Definições PPI', 'ppi-core' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore ?><div class="notice notice-success"><p><?php esc_html_e( 'Definições guardadas.', 'ppi-core' ); ?></p></div><?php endif; ?>
			<?php if ( 'production' !== wp_get_environment_type() ) : ?>
				<div class="notice notice-info"><p><?php /* translators: %s address */ printf( esc_html__( 'Ambiente de teste: todas as notificações são enviadas para %s, independentemente do destinatário abaixo.', 'ppi-core' ), '<code>' . esc_html( Mail::test_recipient() ) . '</code>' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ppi_settings">
				<?php wp_nonce_field( 'ppi_settings' ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( self::text_fields() as $k => $l ) : ?>
						<tr><th scope="row"><label for="ppi-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label></th>
						<td><input class="regular-text" id="ppi-<?php echo esc_attr( $k ); ?>" name="s[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) $s[ $k ] ); ?>"></td></tr>
					<?php endforeach; ?>
					<tr><th scope="row"><label for="ppi-address"><?php esc_html_e( 'Morada', 'ppi-core' ); ?></label></th><td><textarea id="ppi-address" class="large-text" rows="3" name="s[address]"><?php echo esc_textarea( $s['address'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="ppi-hours"><?php esc_html_e( 'Horário (vazio = não mostrar)', 'ppi-core' ); ?></label></th><td><textarea id="ppi-hours" class="large-text" rows="2" name="s[hours]"><?php echo esc_textarea( $s['hours'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="ppi-map"><?php esc_html_e( 'Mapa', 'ppi-core' ); ?></label></th><td>
						<select id="ppi-map" name="s[map_provider]">
							<option value="none" <?php selected( $s['map_provider'], 'none' ); ?>><?php esc_html_e( 'Desativado', 'ppi-core' ); ?></option>
							<option value="osm" <?php selected( $s['map_provider'], 'osm' ); ?>><?php esc_html_e( 'OpenStreetMap (só imóveis com coordenadas públicas, após consentimento)', 'ppi-core' ); ?></option>
						</select></td></tr>
				</table>
				<h2><?php esc_html_e( 'Equipa', 'ppi-core' ); ?></h2>
				<p><?php esc_html_e( 'Só membros marcados como «validado» aparecem no site. Uma linha por membro: nome | função PT | função EN | ID da fotografia | validado (1/0).', 'ppi-core' ); ?></p>
				<?php
				$lines = array();
				foreach ( (array) $s['team'] as $m ) {
					$lines[] = implode( ' | ', array( $m['name'] ?? '', $m['role_pt'] ?? '', $m['role_en'] ?? '', $m['photo'] ?? '', ! empty( $m['validated'] ) ? '1' : '0' ) );
				}
				?>
				<textarea class="large-text code" rows="6" name="team" aria-label="<?php esc_attr_e( 'Equipa', 'ppi-core' ); ?>"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function save(): void {
		if ( ! current_user_can( 'ppi_settings' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'ppi-core' ), 403 );
		}
		check_admin_referer( 'ppi_settings' );
		$in  = (array) wp_unslash( $_POST['s'] ?? array() );
		$out = array();
		foreach ( array_keys( self::text_fields() ) as $k ) {
			$v = sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
			if ( in_array( $k, array( 'facebook', 'instagram', 'complaints_url', 'whistle_url' ), true ) ) {
				$v = esc_url_raw( $v );
			}
			if ( in_array( $k, array( 'email', 'notify_to' ), true ) ) {
				$v = sanitize_email( $v );
			}
			if ( 'retention_days' === $k ) {
				$v = max( 30, min( 3650, absint( $v ) ) );
			}
			if ( 'ga4_id' === $k && $v && ! preg_match( '/^G-[A-Z0-9]{4,12}$/', $v ) ) {
				$v = '';
			}
			$out[ $k ] = $v;
		}
		$out['address']      = sanitize_textarea_field( (string) ( $in['address'] ?? '' ) );
		$out['hours']        = sanitize_textarea_field( (string) ( $in['hours'] ?? '' ) );
		$out['map_provider'] = in_array( $in['map_provider'] ?? '', array( 'none', 'osm' ), true ) ? $in['map_provider'] : 'none';
		$team = array();
		foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['team'] ?? '' ) ) as $line ) {
			$c = array_map( 'trim', explode( '|', $line ) );
			if ( empty( $c[0] ) ) {
				continue;
			}
			$team[] = array( 'name' => sanitize_text_field( $c[0] ), 'role_pt' => sanitize_text_field( $c[1] ?? '' ), 'role_en' => sanitize_text_field( $c[2] ?? '' ), 'photo' => absint( $c[3] ?? 0 ), 'validated' => '1' === ( $c[4] ?? '0' ) );
		}
		$out['team'] = $team;
		self::update( $out );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ppi_property&page=ppi-settings&updated=1' ) );
		exit;
	}
}

Settings::init();
