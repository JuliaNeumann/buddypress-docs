<?php

/**
 * Miscellaneous utility functions
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */


/**
 * Return the bp_doc post type name
 *
 * @package BuddyPress_Docs
 * @since 1.2
 *
 * @return str The name of the bp_doc post type
 */
function bp_docs_get_post_type_name() {
	global $bp;
	return $bp->bp_docs->post_type_name;
}

/**
 * Return the associated_item taxonomy name
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_get_associated_item_tax_name() {
	global $bp;
	return $bp->bp_docs->associated_item_tax_name;
}

/**
 * Return the access taxonomy name
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_get_access_tax_name() {
	global $bp;
	return $bp->bp_docs->access_tax_name;
}

/**
 * Utility function to get and cache the current doc
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @return obj Current doc
 */
function bp_docs_get_current_doc() {
	global $bp, $post;

	if ( empty( $bp->bp_docs->doc_slug ) )
		return false;

	$doc = false;

	if ( empty( $bp->bp_docs->current_post ) ) {

		if ( bp_docs_has_docs( array( 'doc_slug' => $bp->bp_docs->doc_slug ) ) ) {
			while ( bp_docs_has_docs() ) {
				bp_docs_the_doc();
				$doc = $bp->bp_docs->current_post = $post;
				break;
			}
		}

	} else {
		$doc = $bp->bp_docs->current_post;
	}

	return $doc;
}


/**
 * Get an item_id based on a taxonomy term slug
 *
 * BuddyPress Docs are associated with groups and users through a taxonomy called
 * bp_docs_associated_item. Terms belonging to this taxonomy have slugs that look like this
 * (since 1.2):
 *   4-user
 *   103-group
 * (where 4-user corresponds to the user with the ID 4, and 103 is the group with group_id 103).
 * If you have a term slug, you can use this function to parse the item id out of it. Note that
 * it will return 0 if you pass a slug that belongs to a different item type.
 *
 * @since 1.2
 * @param str $term_slug The 'slug' property of a WP term object
 * @param str $item_type 'user', 'group', or your custom item type
 * @return mixed Returns false if you don't pass in the proper parameters.
 *		 Returns 0 if you pass a slug that does not correspond to your item_type
 *	         Returns an int (the unique item_id) if successful
 */
function bp_docs_get_associated_item_id_from_term_slug( $term_slug = '', $item_type = '' ) {
	if ( !$term_slug || !$item_type ) {
		return false;
	}

	$item_id = 0;

	// The item_type should be hidden in the slug
	$slug_array = explode( '-', $term_slug );

	if ( isset( $slug_array[1] ) && $item_type == $slug_array[1] ) {
		$item_id = $slug_array[0];
	}

	return apply_filters( 'bp_docs_get_associated_item_id_from_term_slug', $item_id, $term_slug, $item_type );
}

/**
 * Get the term_id for the associated_item term corresponding to a item_id
 *
 * Will create it if it's not found
 *
 * @since 1.2
 *
 * @param int $item_id Such as the group_id or user_id
 * @param str $item_type Such as 'user' or 'group' (slug of the parent term)
 * @param str $item_name Optional. This is the value that will be used to describe the term in the
 *    Dashboard.
 * @return int $item_term_id
 */
function bp_docs_get_item_term_id( $item_id, $item_type, $item_name = '' ) {
	global $bp;

	if ( empty( $item_id ) ) {
		return;
	}

	// Sanitization
	// @todo Maybe this should be more generous
	$item_type = 'group' == $item_type ? 'group' : 'user';

	$item_term_slug = 'bp_docs_associated_' . $item_type . '_' . $item_id;

	$item_term = get_term_by( 'slug', $item_term_slug, bp_docs_get_associated_item_tax_name() );

	// If the item term doesn't exist, then create it
	if ( empty( $item_term ) ) {
		// Set up the arguments for creating the term. Filter this to set your own
		switch ( $item_type ) {
			case 'group' :
				$item = groups_get_group( 'group_id=' . $item_id );
				$item_name = $item->name;
				break;

			case 'user' :
			default :
				$item_name = bp_core_get_user_displayname( $item_id );
				break;
		}

		$item_term_args = apply_filters( 'bp_docs_item_term_values', array(
			'description' => sprintf( __( 'Docs associated with the %1$s %2$s', 'bp-docs' ), $item_type, $item_name ),
			'slug'        => $item_term_slug,
		) );

		// Create the item term
		if ( ! $item_term = wp_insert_term( $item_name, bp_docs_get_associated_item_tax_name(), $item_term_args ) )
			return false;
	}

	return apply_filters( 'bp_docs_get_item_term_id', $item_term->term_id, $item_id, $item_type, $item_name );
}

/**
 * Get the absolute path of a given template.
 *
 * Looks first for a template in [theme-dir]/docs/, and falls back on the provided templates.
 *
 * Ideally, I would not need this function. But WP's locate_template() plays funny with directory
 * paths, and bp_core_load_template() does not have an option that will let you locate but not load
 * the found template.
 *
 * @package BuddyPress Docs
 * @since 1.0.5
 *
 * @param str $template This string should be of the format 'edit-docs.php'. Ie, you need '.php',
 *                      but you don't need the leading '/docs/'
 * @return str $template_path The absolute path of the located template file.
 */
function bp_docs_locate_template( $template = '', $load = false, $require_once = true ) {
	if ( empty( $template ) )
		return false;

	// Try to load custom templates first
	$stylesheet_path = STYLESHEETPATH . '/docs/';
	$template_path   = TEMPLATEPATH . '/docs/';

	if ( file_exists( $stylesheet_path . $template ) )
		$template_path = $stylesheet_path . $template;
	elseif ( file_exists( $template_path . $template ) )
		$template_path = $template_path . $template;
	else
		$template_path = BP_DOCS_INCLUDES_PATH . 'templates/docs/' . $template;

	$template_path = apply_filters( 'bp_docs_locate_template', $template_path, $template );

	if ( $load ) {
		load_template( $template_path, $require_once );
	} else {
		return $template_path;
	}
}

/**
 * Determine whether the current user can do something the current doc
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @param str $action The cap being tested
 * @return bool $user_can
 */
function bp_docs_current_user_can( $action = 'edit', $doc_id = false ) {
	$user_can = bp_docs_user_can( $action, bp_loggedin_user_id(), $doc_id );

	return apply_filters( 'bp_docs_current_user_can', $user_can, $action );
}

/**
 * Determine whether a given user can do something with a given doc
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @param str $action Optional. The action being queried. Eg 'edit', 'read_comments', 'manage'
 * @param int $user_id Optional. Unique user id for the user being tested. Defaults to logged-in ID
 * @param int $doc_id Optional. Unique doc id. Defaults to doc currently being viewed
 */
function bp_docs_user_can( $action = 'edit', $user_id = false, $doc_id = false ) {
	global $bp, $post;

	if ( !$user_id )
		$user_id = bp_loggedin_user_id();

	// Only certain actions are checked against doc_ids
	$need_doc_ids_actions = apply_filters( 'bp_docs_need_doc_ids_actions', array( 'edit', 'manage', 'view_history', 'read', 'read_comments', 'post_comments' ) );

	$doc_id = false;

	if ( in_array( $action, $need_doc_ids_actions ) ) {
		if ( !$doc_id ) {
			if ( !empty( $post->ID ) ) {
				$doc_id = $post->ID;
				$doc = $post;
			} else {
				$doc = bp_docs_get_current_doc();
				if ( isset( $doc->ID ) ) {
					$doc_id = $doc->ID;
				}
			}
		}
	}

	$user_can = false;

	if ( ! empty( $doc ) ) {
		$doc_settings = get_post_meta( $doc_id, 'bp_docs_settings', true );
		$the_setting  = isset( $doc_settings[ $action ] ) ? $doc_settings[ $action ] : '';

		if ( empty( $the_setting ) ) {
			$the_setting = 'anyone';
		}

		switch ( $the_setting ) {
			case 'anyone' :
				$user_can = true;
				break;

			case 'loggedin' :
				$user_can = is_user_logged_in();
				break;

			case 'creator' :
				$user_can = $doc->post_author == $user_id;
				break;
			// Do nothing with other settings - they are passed through
		}
	} else if ( 'create' == $action ) {

		// In the case of Doc creation, this value gets passed through
		// to other components
		$user_can = is_user_logged_in();
	}

	if ( $user_id ) {
		if ( is_super_admin() ) {
			// Super admin always gets to edit. What a big shot
			$user_can = true;
		} else {
			// Filter this so that groups-integration and other plugins can give their
			// own rules. Done inside the conditional so that plugins don't have to
			// worry about the is_super_admin() check
			$user_can = apply_filters( 'bp_docs_user_can', $user_can, $action, $user_id, $doc_id );
		}
	}

	return $user_can;
}

/**
 * Update the Doc count for a given item
 *
 * @since 1.2
 */
function bp_docs_update_doc_count( $item_id = 0, $item_type = '' ) {
	global $bp;

	$doc_count = 0;
	$docs_args = array( 'doc_slug' => '' );

	switch ( $item_type ) {
		case 'group' :
			$docs_args['user_id']  = '';
			$docs_args['group_id'] = $item_id;
			break;

		case 'user' :
			$docs_args['user_id']  = $item_id;
			$docs_args['group_id'] = '';
			break;

		default :
			$docs_args['user_id']  = '';
			$docs_args['group_id'] = '';
			break;
	}

	$query = new BP_Docs_Query( $docs_args );
	$query->get_wp_query();
	if ( $query->query->have_posts() ) {
		$doc_count = $query->query->found_posts;
	}

	// BP has a stupid bug that makes it delete groupmeta when it equals 0. We'll save
	// a string instead of zero to work around this
	if ( !$doc_count )
		$doc_count = '0';

	// Save the count
	switch ( $item_type ) {
		case 'group' :
			groups_update_groupmeta( $item_id, 'bp-docs-count', $doc_count );
			break;

		case 'user' :
			update_user_meta( $item_id, 'bp_docs_count', $doc_count );
			break;

		default :
			bp_update_option( 'bp_docs_count', $doc_count );
			break;
	}

	return $doc_count;
}

/**
 * Is this the BP Docs component?
 */
function bp_docs_is_docs_component() {
	$retval = false;

	$p = get_queried_object();

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) ) {
		$retval = true;
	} else if ( isset( $p->post_type ) && bp_docs_get_post_type_name() == $p->post_type ) {
		$retval = true;
	} else if ( bp_is_current_component( bp_docs_get_slug() ) ) {
		// This covers cases where we're looking at the Docs component of a user
		$retval = true;
	}

	return $retval;
}

/**
 * Get the Doc settings array
 *
 * This will prepopulate many of the required settings, for cases where the settings have not
 * yet been saved for this Doc.
 *
 * @param int $doc_id
 * @return array
 */
function bp_docs_get_doc_settings( $doc_id = 0 ) {
	$doc_settings = array();

	$q = get_queried_object();
	if ( !$doc_id && isset( $q->ID ) ) {
		$doc_id = $q->ID;
	}

	$saved_settings = get_post_meta( $doc_id, 'bp_docs_settings', true );
	if ( !is_array( $saved_settings ) ) {
		$saved_settings = array();
	}


	$default_settings = array(
		'read'          => 'anyone',
		'edit'          => 'loggedin',
		'read_comments' => 'anyone',
		'post_comments' => 'anyone',
		'view_history'  => 'anyone'
	);

	$doc_settings = wp_parse_args( $saved_settings, $default_settings );

	return apply_filters( 'bp_docs_get_doc_settings', $doc_settings, $doc_id, $default_settings );
}

function bp_docs_define_tiny_mce() {
	BP_Docs_Query::define_wp_tiny_mce();
}

/**
 * Outputs a list of access options
 *
 * Access options are things like 'anyone', 'loggedin', 'group-members', etc
 */
function bp_docs_get_access_options( $settings_field, $doc_id = 0, $group_id = 0 ) {
	$options = array();

	// The base options for every setting
	$options = array(
		20 => array(
			'name'  => 'loggedin',
			'label' => __( 'Logged-in Users', 'bp-docs' ),
			'default' => 1 // default to 'loggedin' for most options. See below for override
		),
		90 => array(
			'name'  => 'creator',
			'label' => __( 'The Doc author only', 'bp-docs' )
		),
	);

	// Allow anonymous reading
	if ( in_array( $settings_field, array( 'read', 'read_comments', 'view_history' ) ) ) {
		$options[10] = array(
			'name'  => 'anyone',
			'label' => __( 'Anyone', 'bp-docs' ),
			'default' => 1
		);

		$options[20]['default'] = 0; // Default to 'anyone' instead
	}

	// Other integration pieces can mod the options with this filter
	$options = apply_filters( 'bp_docs_get_access_options', $options, $settings_field, $doc_id, $group_id );

	// Options are sorted by the numeric key
	ksort( $options );

	return $options;
}

/**
 * Verifies the settings associated with a given Doc
 *
 * @since 1.2
 * @param array $settings Settings passed from the Edit form
 * @param int $doc_id The numeric ID of the doc
 * @param int $user_id The id of the user
 * @return array $is_allowed Keyed by settings names, with boolean values
 */
function bp_docs_verify_settings( $settings, $doc_id, $user_id = 0 ) {
	$verified_settings = array();

	foreach ( $settings as $setting_name => $setting_value ) {
		$allowed_values = bp_docs_get_access_options( $setting_name, $doc_id );

		$verified_settings[ $setting_name ] = array(
			'original_value'  => $setting_value,
			'verified_value'  => '',
			'setting_default' => '',
		);

		// Loop through to collect whitelisted values as well as the
		// default setting, which will be used if the user-provided
		// value doesn't match the whitelist
		foreach ( $allowed_values as $allowed_value ) {
			if ( empty( $verified_settings[ $setting_name ]['verified_value'] ) && $setting_value == $allowed_value['name'] ) {
				$verified_settings[ $setting_name ]['verified_value'] = $setting_value;
			}

			if ( empty( $verified_settings[ $setting_name ]['setting_default'] ) && 1 == $allowed_value['default'] ) {
				$verified_settings[ $setting_name ]['setting_default'] = 1;
			}
		}

		// If no whitelisted value has been found, attempt to fall
		// back on a default value for that option
		if ( empty( $verified_settings[ $setting_name ] ) ) {
			$verified_settings[ $setting_name ]['verified_value'] = $verified_settings[ $setting_name ]['setting_default'];
		}
	}

	return $verified_settings;
}

/**
 * Get the access term for 'anyone'
 *
 * @since 1.2
 * @return string The term slug
 */
function bp_docs_get_access_term_anyone() {
	return apply_filters( 'bp_docs_get_access_term_anyone', 'bp_docs_access_anyone' );
}

/**
 * Get the access term for 'loggedin'
 *
 * @since 1.2
 * @return string The term slug
 */
function bp_docs_get_access_term_loggedin() {
	return apply_filters( 'bp_docs_get_access_term_loggedin', 'bp_docs_access_loggedin' );
}

/**
 * Get the access term for a user id
 *
 * @since 1.2
 * @param int|bool $user_id Defaults to logged in user
 * @return string The term slug
 */
function bp_docs_get_access_term_user( $user_id = false ) {
	if ( false === $user_id ) {
		$user_id = bp_loggedin_user_id();
	}

	return apply_filters( 'bp_docs_get_access_term_user', 'bp_docs_access_user_' . intval( $user_id ) );
}

/**
 * Get the access term corresponding to group-members for a given group
 *
 * @since 1.2
 * @param int|bool $user_id Defaults to logged in user
 * @return string The term slug
 */
function bp_docs_get_access_term_group_member( $user_id = false ) {
	if ( false === $user_id ) {
		$user_id = bp_loggedin_user_id();
	}

	return apply_filters( 'bp_docs_get_access_term_group_member', 'bp_docs_access_group_member_' . intval( $user_id ) );
}

/**
 * Get the access term corresponding to admins-mods for a given group
 *
 * @since 1.2
 * @param int|bool $user_id Defaults to logged in user
 * @return string The term slug
 */
function bp_docs_get_access_term_group_adminmod( $user_id = false ) {
	if ( false === $user_id ) {
		$user_id = bp_loggedin_user_id();
	}

	return apply_filters( 'bp_docs_get_access_term_group_adminmod', 'bp_docs_access_group_adminmod_' . intval( $user_id ) );
}

function bp_docs_update_doc_access( $doc_id, $access_setting = 'anyone' ) {

	$doc = get_post( $doc_id );

	if ( ! $doc || is_wp_error( $doc ) ) {
		return false;
	}

	// Convert the access setting to a WP taxonomy term
	switch ( $access_setting ) {
		case 'anyone' :
		case 'loggedin' :
			$access_term = 'bp_docs_access_' . $access_setting;
			break;

		case 'group-members' :
		case 'admins-mods' :
			$associated_group = bp_docs_get_associated_group_id( $doc_id );
			$access_term = 'group-members' == $access_setting ? bp_docs_get_access_term_group_member( $associated_group ) : bp_docs_get_access_term_group_adminmod( $associated_group );
			break;

		case 'creator' :
		case 'no-one' :
			// @todo Don't know how these are different
			$access_term = bp_docs_get_access_term_user( $doc->post_author );
			break;
	}

	$retval = wp_set_post_terms( $doc_id, $access_term, bp_docs_get_access_tax_name() );

	if ( ! $retval || is_wp_error( $retval ) ) {
		return false;
	} else {
		return true;
	}

}

