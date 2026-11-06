<?php
/**
 * Tipos de conteúdo, taxonomias, metadados registados e capacidades.
 */

namespace PPI;

defined( 'ABSPATH' ) || exit;

final class Post_Types {

	/** Capacidades dos imóveis (mapeadas por map_meta_cap). */
	public const PROPERTY_CAPS = array(
		'edit_post' => 'edit_ppi_property', 'read_post' => 'read_ppi_property', 'delete_post' => 'delete_ppi_property',
		'edit_posts' => 'edit_ppi_properties', 'edit_others_posts' => 'edit_others_ppi_properties', 'publish_posts' => 'publish_ppi_properties',
		'read_private_posts' => 'read_private_ppi_properties', 'delete_posts' => 'delete_ppi_properties',
		'delete_private_posts' => 'delete_private_ppi_properties', 'delete_published_posts' => 'delete_published_ppi_properties',
		'delete_others_posts' => 'delete_others_ppi_properties', 'edit_private_posts' => 'edit_private_ppi_properties',
		'edit_published_posts' => 'edit_published_ppi_properties', 'create_posts' => 'edit_ppi_properties',
	);

	public const LEAD_CAPS = array(
		'edit_post' => 'ppi_manage_leads', 'read_post' => 'ppi_manage_leads', 'delete_post' => 'ppi_delete_leads',
		'edit_posts' => 'ppi_manage_leads', 'edit_others_posts' => 'ppi_manage_leads', 'publish_posts' => 'do_not_allow',
		'read_private_posts' => 'ppi_manage_leads', 'delete_posts' => 'ppi_delete_leads', 'delete_others_posts' => 'ppi_delete_leads',
		'delete_private_posts' => 'ppi_delete_leads', 'delete_published_posts' => 'ppi_delete_leads', 'edit_private_posts' => 'ppi_manage_leads',
		'edit_published_posts' => 'ppi_manage_leads', 'create_posts' => 'do_not_allow',
	);

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_filter( 'use_block_editor_for_post_type', static fn( $use, $type ) => in_array( $type, array( 'ppi_property', 'ppi_lead' ), true ) ? false : $use, 10, 2 );
	}

	public static function register(): void {
		register_post_type( 'ppi_property', array(
			'labels' => array(
				'name' => __( 'Imóveis', 'ppi-core' ), 'singular_name' => __( 'Imóvel', 'ppi-core' ), 'add_new' => __( 'Adicionar imóvel', 'ppi-core' ),
				'add_new_item' => __( 'Adicionar imóvel', 'ppi-core' ), 'edit_item' => __( 'Editar imóvel', 'ppi-core' ), 'new_item' => __( 'Novo imóvel', 'ppi-core' ),
				'view_item' => __( 'Ver imóvel', 'ppi-core' ), 'search_items' => __( 'Pesquisar imóveis', 'ppi-core' ), 'not_found' => __( 'Nenhum imóvel encontrado', 'ppi-core' ),
				'all_items' => __( 'Todos os imóveis', 'ppi-core' ), 'menu_name' => __( 'Imóveis', 'ppi-core' ),
			),
			'public'          => true,
			'has_archive'     => 'imoveis',
			'rewrite'         => array( 'slug' => 'imoveis', 'with_front' => false ),
			'menu_icon'       => 'dashicons-admin-home',
			'menu_position'   => 5,
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'show_in_rest'    => true,
			'rest_base'       => 'properties',
			'capability_type' => array( 'ppi_property', 'ppi_properties' ),
			'capabilities'    => self::PROPERTY_CAPS,
			'map_meta_cap'    => true,
		) );

		register_post_type( 'ppi_lead', array(
			'labels' => array(
				'name' => __( 'Contactos recebidos', 'ppi-core' ), 'singular_name' => __( 'Contacto recebido', 'ppi-core' ),
				'edit_item' => __( 'Contacto recebido', 'ppi-core' ), 'menu_name' => __( 'Contactos', 'ppi-core' ),
				'not_found' => __( 'Sem contactos', 'ppi-core' ), 'search_items' => __( 'Pesquisar contactos', 'ppi-core' ),
			),
			// Dados pessoais: nunca públicos, nunca na REST, pesquisa, sitemaps ou exportação nativa.
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'can_export'          => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-email-alt',
			'menu_position'       => 6,
			'supports'            => array( 'title' ),
			'capability_type'     => array( 'ppi_lead', 'ppi_leads' ),
			'capabilities'        => self::LEAD_CAPS,
			'map_meta_cap'        => false,
		) );

		register_taxonomy( 'ppi_location', array( 'ppi_property' ), array(
			'labels' => array( 'name' => __( 'Localizações', 'ppi-core' ), 'singular_name' => __( 'Localização', 'ppi-core' ), 'parent_item' => __( 'Concelho', 'ppi-core' ) ),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'localizacoes', 'hierarchical' => true, 'with_front' => false ),
			'capabilities'      => array( 'manage_terms' => 'edit_others_ppi_properties', 'edit_terms' => 'edit_others_ppi_properties', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_ppi_properties' ),
		) );
		register_taxonomy( 'ppi_type', array( 'ppi_property' ), array(
			'labels' => array( 'name' => __( 'Tipos de imóvel', 'ppi-core' ), 'singular_name' => __( 'Tipo de imóvel', 'ppi-core' ) ),
			'hierarchical'      => true,
			'public'            => true,
			'publicly_queryable'=> false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
			'capabilities'      => array( 'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_ppi_properties' ),
		) );
		register_taxonomy( 'ppi_feature', array( 'ppi_property' ), array(
			'labels' => array( 'name' => __( 'Filtros de características', 'ppi-core' ), 'singular_name' => __( 'Filtro de característica', 'ppi-core' ) ),
			'hierarchical'      => false,
			'public'            => true,
			'publicly_queryable'=> false,
			'show_in_rest'      => true,
			'rewrite'           => false,
			'capabilities'      => array( 'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_ppi_properties' ),
		) );

		foreach ( Fields::all() as $name => $def ) {
			$type = array( 'int' => 'integer', 'float' => 'number', 'bool' => 'boolean' )[ $def['type'] ] ?? 'string';
			register_post_meta( 'ppi_property', Fields::key( $name ), array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => ! empty( $def['public'] ),
				'auth_callback' => static fn() => current_user_can( 'edit_ppi_properties' ),
			) );
		}
		foreach ( array( 'label_en', 'intro_pt', 'intro_en', 'image', 'image_credit' ) as $m ) {
			register_term_meta( '', 'ppi_' . $m, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		}
	}

	/** Atribui capacidades a funções. Chamado pelas migrações. */
	public static function grant_caps(): void {
		$property = array_unique( array_values( self::PROPERTY_CAPS ) );
		foreach ( array( 'administrator', 'editor' ) as $r ) {
			$role = get_role( $r );
			if ( $role ) {
				foreach ( $property as $c ) {
					$role->add_cap( $c );
				}
			}
		}
		$admin = get_role( 'administrator' );
		foreach ( array( 'ppi_manage_leads', 'ppi_delete_leads', 'ppi_export_leads', 'ppi_import', 'ppi_settings' ) as $c ) {
			$admin->add_cap( $c );
		}
		// Editores NÃO recebem acesso a dados pessoais por defeito.
		$editor = get_role( 'editor' );
		if ( $editor ) {
			foreach ( array( 'ppi_manage_leads', 'ppi_delete_leads', 'ppi_export_leads', 'ppi_import' ) as $c ) {
				$editor->remove_cap( $c );
			}
		}
		add_role( 'ppi_property_editor', __( 'Gestor de imóveis', 'ppi-core' ), array_fill_keys( array_merge( array( 'read', 'upload_files' ), $property ), true ) );
		add_role( 'ppi_lead_manager', __( 'Gestor de contactos', 'ppi-core' ), array( 'read' => true, 'ppi_manage_leads' => true, 'ppi_export_leads' => true ) );
	}
}

Post_Types::init();
