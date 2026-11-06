<?php
/**
 * Migrações versionadas e idempotentes (opção ppi_db_version). Nunca apagam dados.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Migrations {

	public static function maybe_run(): void {
		if ( (int) get_option( 'ppi_db_version', 0 ) < PPI_DB_VERSION ) {
			add_action( 'init', array( __CLASS__, 'run' ), 20 );
		}
	}

	public static function run(): void {
		$v = (int) get_option( 'ppi_db_version', 0 );
		if ( $v < 1 ) {
			Post_Types::grant_caps();
			self::types();
			self::features();
			Settings::install_defaults();
		}
		if ( $v < 2 ) {
			self::private_dir();
		}
		if ( $v < 3 ) {
			Post_Types::grant_caps(); // funções atualizadas na v3 (export de leads)
		}
		if ( ! wp_next_scheduled( 'ppi_retry_notifications' ) ) {
			wp_schedule_event( time() + 300, 'ppi_quarter_hour', 'ppi_retry_notifications' );
		}
		if ( ! wp_next_scheduled( 'ppi_daily_maintenance' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'ppi_daily_maintenance' );
		}
		update_option( 'ppi_db_version', PPI_DB_VERSION, false );
	}

	/** Vocabulário controlado de tipos (nome PT + rótulo EN). */
	public static function type_terms(): array {
		return array(
			'moradia'     => array( 'Moradia', 'House' ),
			'apartamento' => array( 'Apartamento', 'Apartment' ),
			'terreno'     => array( 'Terreno', 'Land' ),
			'quinta'      => array( 'Quinta', 'Farm estate' ),
			'loja'        => array( 'Loja', 'Shop' ),
			'escritorio'  => array( 'Escritório', 'Office' ),
			'negocio'     => array( 'Negócio', 'Business' ),
		);
	}

	public static function types(): void {
		foreach ( self::type_terms() as $slug => [ $pt, $en ] ) {
			$t = term_exists( $slug, 'ppi_type' );
			if ( ! $t ) {
				$t = wp_insert_term( $pt, 'ppi_type', array( 'slug' => $slug ) );
			}
			if ( ! is_wp_error( $t ) ) {
				update_term_meta( (int) $t['term_id'], 'ppi_label_en', $en );
			}
		}
	}

	/**
	 * Filtros de características: conjunto curto e útil, derivado das características da origem.
	 * Cada filtro: [PT, EN, regras (grupo|rótulo ou rótulo>=1 para contagens)].
	 */
	public static function feature_filters(): array {
		return array(
			'piscina'         => array( 'Piscina', 'Swimming pool', array( 'Equipamento|Piscina exterior', 'Nº de Piscinas>=1' ) ),
			'vista-mar'       => array( 'Vista mar', 'Sea view', array( 'Vistas|Mar', 'Vistas|Praia' ) ),
			'garagem'         => array( 'Garagem', 'Garage', array( 'Nº de Garagens>=1', 'Garagem|Individual', 'Garagem|Fechada Interior', 'Garagem|Fechada Exterior' ) ),
			'ar-condicionado' => array( 'Ar condicionado', 'Air conditioning', array( 'Climatização|Ar condicionado', 'Ar Condicionado|Completo', 'Ar Condicionado|Central' ) ),
			'lareira'         => array( 'Lareira ou recuperador', 'Fireplace or wood burner', array( 'Climatização|Lareira', 'Climatização|Recuperador' ) ),
			'mobilado'        => array( 'Mobilado', 'Furnished', array( 'Gerais|Mobilado' ) ),
			'agua-propria'    => array( 'Furo, poço ou nascente', 'Borehole, well or spring', array( 'Gerais|Furo/Poço', 'Água|Furo', 'Água|Poço', 'Água|Nascente própria' ) ),
			'jardim'          => array( 'Jardim', 'Garden', array( 'Equipamento|Jardins' ) ),
			'mobilidade'      => array( 'Mobilidade reduzida', 'Reduced mobility access', array( 'Gerais|Acesso mobilidade reduzida', 'Gerais|Adaptado a mobilidade reduzida' ) ),
		);
	}

	public static function features(): void {
		foreach ( self::feature_filters() as $slug => [ $pt, $en ] ) {
			$t = term_exists( $slug, 'ppi_feature' );
			if ( ! $t ) {
				$t = wp_insert_term( $pt, 'ppi_feature', array( 'slug' => $slug ) );
			}
			if ( ! is_wp_error( $t ) ) {
				update_term_meta( (int) $t['term_id'], 'ppi_label_en', $en );
			}
		}
	}

	/** Pasta privada para CVs: sem listagem, sem execução, sem acesso direto. */
	public static function private_dir(): string {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'ppi-private';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( "$dir/.htaccess" ) ) {
			file_put_contents( "$dir/.htaccess", "Require all denied\nOptions -Indexes\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n" );
		}
		if ( ! file_exists( "$dir/index.php" ) ) {
			file_put_contents( "$dir/index.php", "<?php // Silence.\n" );
		}
		return $dir;
	}
}

add_filter( 'cron_schedules', static function ( $s ) {
	$s['ppi_quarter_hour'] = array( 'interval' => 900, 'display' => 'PPI — 15 min' );
	return $s;
} );
