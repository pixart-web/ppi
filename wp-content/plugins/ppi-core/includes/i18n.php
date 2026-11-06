<?php
/**
 * Integração com Polylang (opcional). Sem Polylang o site funciona apenas em PT.
 * Os factos (preço, áreas, referência, estado...) são copiados para todas as traduções ao gravar.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class I18n {

	public const DEFAULT_LANG = 'pt';

	public static function active(): bool {
		return function_exists( 'pll_current_language' );
	}

	public static function current(): string {
		if ( self::active() ) {
			$l = pll_current_language( 'slug' );
			if ( $l ) {
				return $l;
			}
		}
		return self::DEFAULT_LANG;
	}

	public static function languages(): array {
		if ( self::active() && function_exists( 'pll_languages_list' ) ) {
			$l = pll_languages_list( array( 'fields' => 'slug' ) );
			return $l ?: array( self::DEFAULT_LANG );
		}
		return array( self::DEFAULT_LANG );
	}

	public static function post_lang( int $post_id ): string {
		if ( self::active() && function_exists( 'pll_get_post_language' ) ) {
			return pll_get_post_language( $post_id, 'slug' ) ?: self::DEFAULT_LANG;
		}
		return self::DEFAULT_LANG;
	}

	/** ID da tradução no idioma pedido, ou 0. */
	public static function translation( int $post_id, ?string $lang = null ): int {
		$lang = $lang ?: self::current();
		if ( self::active() && function_exists( 'pll_get_post' ) ) {
			return (int) pll_get_post( $post_id, $lang );
		}
		return $post_id;
	}

	public static function translations( int $post_id ): array {
		if ( self::active() && function_exists( 'pll_get_post_translations' ) ) {
			return pll_get_post_translations( $post_id );
		}
		return array( self::DEFAULT_LANG => $post_id );
	}

	/** URL de página institucional pelo slug PT, já no idioma atual. */
	public static function page_url( string $pt_slug ): string {
		$page = get_page_by_path( $pt_slug );
		if ( ! $page ) {
			return home_url( '/' . $pt_slug . '/' );
		}
		$id = self::translation( $page->ID ) ?: $page->ID;
		return get_permalink( $id );
	}

	public static function home_url(): string {
		if ( self::active() && function_exists( 'pll_home_url' ) ) {
			return pll_home_url();
		}
		return home_url( '/' );
	}

	public static function archive_url(): string {
		$url = get_post_type_archive_link( 'ppi_property' );
		if ( self::active() && function_exists( 'PLL' ) && self::current() !== self::default_lang() ) {
			$lang = PLL()->model->get_language( self::current() );
			if ( $lang ) {
				$url = PLL()->links_model->switch_language_in_link( $url, $lang );
			}
		}
		return $url;
	}

	public static function default_lang(): string {
		if ( self::active() && function_exists( 'pll_default_language' ) ) {
			return pll_default_language( 'slug' ) ?: self::DEFAULT_LANG;
		}
		return self::DEFAULT_LANG;
	}

	/** Atributo lang para conteúdo que ficou noutro idioma (ex.: característica sem tradução). */
	public static function locale_attr( string $lang ): string {
		return 'en' === $lang ? 'en' : 'pt-PT';
	}

	/** Copia os campos factuais e as taxonomias partilhadas para as traduções. */
	public static function sync_facts( int $post_id ): void {
		if ( ! self::active() ) {
			return;
		}
		static $running = false;
		if ( $running ) {
			return;
		}
		$running = true;
		$keys = Fields::fact_keys();
		foreach ( self::translations( $post_id ) as $lang => $tid ) {
			$tid = (int) $tid;
			if ( $tid === $post_id ) {
				continue;
			}
			foreach ( $keys as $k ) {
				$v = get_post_meta( $post_id, $k, true );
				if ( '' === $v || null === $v ) {
					delete_post_meta( $tid, $k );
				} else {
					update_post_meta( $tid, $k, $v );
				}
			}
			foreach ( array( 'ppi_location', 'ppi_type', 'ppi_feature' ) as $tax ) {
				$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) ) {
					wp_set_object_terms( $tid, $terms, $tax );
				}
			}
			// O thumbnail acompanha a galeria.
			$thumb = get_post_thumbnail_id( $post_id );
			$thumb ? set_post_thumbnail( $tid, $thumb ) : delete_post_thumbnail( $tid );
			Search::flush();
		}
		$running = false;
	}

	/**
	 * Vocabulário de características (origem HCPro) com tradução inglesa.
	 * Rótulos sem correspondência ficam em português com lang="pt-PT" no modo EN.
	 */
	public static function feature_label( string $label, string $lang ): array {
		if ( 'en' !== $lang ) {
			return array( $label, 'pt' );
		}
		$d = self::feature_dictionary();
		return isset( $d[ $label ] ) ? array( $d[ $label ], 'en' ) : array( $label, 'pt' );
	}

	public static function feature_dictionary(): array {
		return array(
			// Grupos
			'Acessos' => 'Access', 'Alarme' => 'Alarm', 'Aquecimento Central' => 'Central heating', 'Ar Condicionado' => 'Air conditioning',
			'Caixilharia' => 'Window frames', 'Centralidade' => 'Setting', 'Climatização' => 'Climate control', 'Cozinha' => 'Kitchen',
			'Elevador' => 'Lift', 'Equipamento' => 'Amenities', 'Exposição Solar' => 'Sun exposure', 'Fuga de Fumos' => 'Chimney',
			'Garagem' => 'Garage', 'Gerais' => 'General', 'Geral' => 'General', 'Infraestruturas' => 'Infrastructure', 'Isolamento' => 'Insulation',
			'Luz Natural' => 'Natural light', 'Música Ambiente' => 'Sound system', 'Painel Solar' => 'Solar panels',
			'Paredes Hall de Entrada' => 'Entrance hall walls', 'Pavimento' => 'Flooring', 'Pavimento Hall de Entrada' => 'Entrance hall flooring',
			'Proximidade' => 'Nearby', 'Quarto de Banho' => 'Bathroom', 'Revestimento Exterior' => 'Exterior finish',
			'Tecto Hall de Entrada' => 'Entrance hall ceiling', 'Terreno' => 'Land', 'Tipo de Empreendimento' => 'Development type',
			'Vistas' => 'Views', 'Água' => 'Water supply',
			// Valores
			'Alcatrão' => 'Tarmac', 'Asfalto' => 'Asphalt', 'Calçada' => 'Cobbled', 'Terra Batida' => 'Dirt track', 'Urbano' => 'Urban',
			'CCTV' => 'CCTV', 'Completo' => 'Full', 'Central' => 'Central', 'Aluminio' => 'Aluminium', 'Dupla' => 'Double',
			'Termolacada' => 'Thermo-lacquered', 'Vidro Duplo' => 'Double glazing', 'Centro da cidade' => 'Town centre',
			'Distante da cidade' => 'Away from town', 'Junto à cidade' => 'Next to town', 'Periferia da cidade' => 'Edge of town', 'Rural' => 'Rural',
			'Aquecimento central' => 'Central heating', 'Ar condicionado' => 'Air conditioning', 'Caldeira' => 'Boiler',
			'Chão radiante / aquecido' => 'Underfloor heating', 'Cilindro eléctrico' => 'Electric water heater', 'Esquentador' => 'Gas water heater',
			'Lareira' => 'Fireplace', 'Recuperador' => 'Wood burner', 'Cerâmica' => 'Ceramic', 'Exaustor' => 'Extractor hood', 'Forno' => 'Oven',
			'Frigorífico' => 'Fridge', 'Micro-ondas' => 'Microwave', 'Máquina de lavar loiça' => 'Dishwasher', 'Máquina de lavar roupa' => 'Washing machine',
			'Móveis Faia' => 'Beech units', 'Móveis lacado' => 'Lacquered units', 'Móveis madeira' => 'Wooden units', 'Placa' => 'Hob',
			'Nº de Elevadores' => 'Number of lifts', 'Churrasqueira' => 'Barbecue', 'Ginásio' => 'Gym', 'Jardins' => 'Gardens',
			'Parabólica' => 'Satellite dish', 'Parque infantil' => 'Playground', 'Piscina exterior' => 'Outdoor pool', 'Rega automática' => 'Automatic irrigation',
			'Rega Automática' => 'Automatic irrigation', 'Som ambiente' => 'Sound system', 'TV Cabo' => 'Cable TV', 'Video-Porteiro' => 'Video intercom',
			'Nascente' => 'East', 'Norte' => 'North', 'Poente' => 'West', 'Sul' => 'South', 'Chaminé de alvenaria' => 'Masonry chimney',
			'Casa de Banho' => 'Bathroom', 'Electricidade' => 'Electricity', 'Fechada Exterior' => 'Enclosed, outside', 'Fechada Interior' => 'Enclosed, inside',
			'Individual' => 'Private', 'Local' => 'Space', 'Lugar Interior' => 'Indoor space', 'Lugar exterior' => 'Outdoor space', 'Luz' => 'Lighting',
			'Portão automático' => 'Automatic gate', 'Acesso mobilidade reduzida' => 'Step-free access', 'Adaptado a mobilidade reduzida' => 'Adapted for reduced mobility',
			'Alpendre' => 'Covered porch', 'Anexo' => 'Annex', 'Armário de roupa / closet' => 'Wardrobe / walk-in closet', 'Auto-Estrada' => 'Motorway access',
			'Despensa' => 'Pantry', 'Eira' => 'Threshing floor', 'Estores elétricos' => 'Electric shutters', 'Furo/Poço' => 'Borehole / well',
			'Jacuzzi' => 'Jacuzzi', 'Lavandaria' => 'Laundry room', 'Mobilado' => 'Furnished', 'Murada' => 'Walled',
			'Nº de Arrecadações' => 'Storage rooms', 'Nº de Casas de Banho' => 'Bathrooms', 'Nº de Divisões' => 'Rooms', 'Nº de Escritórios' => 'Offices',
			'Nº de Frentes' => 'Frontages', 'Nº de Garagens' => 'Garages', 'Nº de Inquilinos' => 'Tenants', 'Nº de Lugares de Estacionamento' => 'Parking spaces',
			'Nº de Marquises' => 'Enclosed balconies', 'Nº de Piscinas' => 'Pools', 'Nº de Pisos' => 'Floors', 'Nº de Terraços' => 'Terraces',
			'Nº de Varandas' => 'Balconies', 'Permite animais' => 'Pets allowed', 'Perto de mar' => 'Near the sea', 'Porão' => 'Basement',
			'Recomendado para crianças' => 'Suitable for children', 'Reservatório de agua' => 'Water tank', 'Rés do chão' => 'Ground floor',
			'Suite' => 'En-suite', 'Sótão' => 'Attic', 'Tanque de Pedra' => 'Stone tank', 'Transportes públicos' => 'Public transport',
			'Árvores de Fruto' => 'Fruit trees', 'A Efectuar' => 'To be completed', 'Concluídas' => 'Completed', 'Esgotos' => 'Mains drainage',
			'Iluminação de rua' => 'Street lighting', 'Acústico' => 'Acoustic', 'Térmico' => 'Thermal', 'Iluminado' => 'Bright', 'Muito Iluminado' => 'Very bright',
			'Pintadas' => 'Painted', 'Flutuante' => 'Laminate', 'Madeira' => 'Wood', 'Pedra natural' => 'Natural stone', 'Tijoleira' => 'Terracotta tiles',
			'Bancos' => 'Banks', 'Bombeiros' => 'Fire station', 'Clínicas' => 'Clinics', 'Escolas' => 'Schools', 'Farmácias' => 'Pharmacies',
			'Ginásios' => 'Gyms', 'Hospitais' => 'Hospitals', 'Jardins de infância' => 'Nurseries', 'Padarias' => 'Bakeries', 'Polícia' => 'Police',
			'Praias' => 'Beaches', 'Serviços públicos' => 'Public services', 'Supermercados' => 'Supermarkets', 'Comum' => 'Shared', 'Próprio' => 'Private',
			'Pintado' => 'Painted', 'Falso madeira' => 'Wooden false ceiling', 'Focos' => 'Spotlights', 'Agrícola' => 'Agricultural',
			'Conservação da Natureza' => 'Nature conservation', 'Florestal' => 'Forestry', 'Reservado p/ Urbanização' => 'Reserved for urban development',
			'Residencial' => 'Residential', 'Turístico' => 'Tourism', 'Condomínio fechado' => 'Gated community', 'Habitação' => 'Residential',
			'Lojas' => 'Shops', 'Campo' => 'Countryside', 'Cidade' => 'Town', 'Mar' => 'Sea', 'Panorâmicas' => 'Panoramic', 'Pastagens' => 'Pasture',
			'Piscina' => 'Pool', 'Praia' => 'Beach', 'Rio' => 'River', 'Serra' => 'Hills', 'Chuva' => 'Rainwater', 'Companhia' => 'Mains water',
			'Depósito' => 'Tank', 'Furo' => 'Borehole', 'Nascente própria' => 'Own spring', 'Poço' => 'Well', 'Sim' => 'Yes',
		);
	}
}

// Despublicar a versão principal retira também as traduções (nenhum anúncio fica vivo noutro idioma); republicar repõe-nas.
add_action( 'transition_post_status', static function ( $new, $old, $post ) {
	static $busy = false;
	if ( $busy || 'ppi_property' !== $post->post_type || $new === $old || ! I18n::active() || I18n::default_lang() !== I18n::post_lang( $post->ID ) ) {
		return;
	}
	$busy = true;
	foreach ( I18n::translations( $post->ID ) as $tid ) {
		$tid = (int) $tid;
		if ( $tid === (int) $post->ID ) {
			continue;
		}
		if ( 'publish' === $old && 'publish' === get_post_status( $tid ) ) {
			wp_update_post( array( 'ID' => $tid, 'post_status' => $new ) );
			update_post_meta( $tid, '_ppi_auto_unpublished', 1 );
		} elseif ( 'publish' === $new && get_post_meta( $tid, '_ppi_auto_unpublished', true ) ) {
			wp_update_post( array( 'ID' => $tid, 'post_status' => 'publish' ) );
			delete_post_meta( $tid, '_ppi_auto_unpublished' );
		}
	}
	$busy = false;
}, 20, 3 );

// Sem Polylang, garantir que o idioma interno existe para os filtros.
add_filter( 'pll_copy_post_metas', static function ( $metas, $sync ) {
	return array_values( array_unique( array_merge( (array) $metas, Fields::fact_keys() ) ) );
}, 10, 2 );

// Tipos geridos por Polylang (o imóvel é traduzível; leads nunca).
add_filter( 'pll_get_post_types', static function ( $types, $is_settings ) {
	$types['ppi_property'] = 'ppi_property';
	unset( $types['ppi_lead'] );
	return $types;
}, 10, 2 );
// Localizações, tipos e características são partilhados entre idiomas (nomes próprios / vocabulário controlado).
add_filter( 'pll_get_taxonomies', static function ( $taxes, $is_settings ) {
	unset( $taxes['ppi_location'], $taxes['ppi_type'], $taxes['ppi_feature'] );
	return $taxes;
}, 10, 2 );
