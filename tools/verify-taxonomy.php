<?php
/**
 * Verify immutable category reconciliation and editor-facing mutation boundaries.
 *
 * Run inside WordPress:
 * wp eval-file /var/www/html/.pgds-tools/verify-taxonomy.php
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$canonical = pgds_category_definitions();
$legacy    = pgds_legacy_category_map();
$failures  = array();
$fixtures  = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$track_fixture = static function ( $type, $id ) use ( &$fixtures ) {
	$fixtures[] = array(
		'type' => $type,
		'id'   => (int) $id,
	);
};

$forget_term = static function ( $term_id ) use ( &$fixtures ) {
	$fixtures = array_values(
		array_filter(
			$fixtures,
			static function ( $fixture ) use ( $term_id ) {
				return 'term' !== $fixture['type'] || (int) $term_id !== $fixture['id'];
			}
		)
	);
};

$cleanup = static function () use ( &$fixtures ) {
	foreach ( array_reverse( $fixtures ) as $fixture ) {
		if ( 'post' === $fixture['type'] ) {
			wp_delete_post( $fixture['id'], true );
		} elseif ( 'user' === $fixture['type'] ) {
			wp_delete_user( $fixture['id'] );
		}
	}

	pgds_category_migration_context( true );
	try {
		foreach ( array_reverse( $fixtures ) as $fixture ) {
			if ( 'term' === $fixture['type'] ) {
				wp_delete_term( $fixture['id'], 'category' );
			}
		}
	} finally {
		pgds_category_migration_context( false );
	}
};

$create_term = static function ( $name, $slug, $parent = 0 ) use ( &$failures, $track_fixture ) {
	pgds_category_migration_context( true );
	try {
		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'   => $slug,
				'parent' => $parent,
			)
		);
	} finally {
		pgds_category_migration_context( false );
	}

	if ( is_wp_error( $result ) ) {
		$failures[] = sprintf( 'Could not create term fixture "%s".', $slug );
		return 0;
	}

	$term_id = (int) $result['term_id'];
	$track_fixture( 'term', $term_id );
	return $term_id;
};

$create_post = static function ( $title, $category_ids ) use ( &$failures, $track_fixture ) {
	$post_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_title'    => $title,
			'post_category' => array_map( 'intval', $category_ids ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		$failures[] = sprintf( 'Could not create post fixture "%s".', $title );
		return 0;
	}
	$track_fixture( 'post', $post_id );
	return (int) $post_id;
};

$dispatch_rest = static function ( $method, $route, $parameters = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $parameters as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};

try {
	$first = pgds_seed_categories();
	if ( is_wp_error( $first ) ) {
		WP_CLI::error( 'Initial category reconciliation failed: ' . $first->get_error_message() );
	}

	$assert( count( $first ) === 10, 'The reconciler did not return exactly 10 canonical terms.' );
	foreach ( $canonical as $slug => $definition ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		$assert( $term instanceof WP_Term, sprintf( 'Canonical category "%s" is missing.', $slug ) );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}

		$expected_parent = $definition['parent'] ? (int) $first[ $definition['parent'] ] : 0;
		$assert( $definition['label'] === $term->name, sprintf( 'Category "%s" has the wrong name.', $slug ) );
		$assert( $expected_parent === (int) $term->parent, sprintf( 'Category "%s" has the wrong parent.', $slug ) );
		$assert( $definition['description'] === (string) $term->description, sprintf( 'Category "%s" has the wrong description.', $slug ) );
	}

	$all_terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	$assert( ! is_wp_error( $all_terms ) && 10 === count( $all_terms ), 'The database does not contain exactly 10 categories.' );
	$assert( (int) $first['tin-phat-su'] === (int) get_option( 'default_category' ), 'Tin Phật sự is not the WordPress default category.' );

	$second = pgds_seed_categories();
	$assert( ! is_wp_error( $second ) && $first === $second, 'A second reconciliation changed canonical term IDs.' );

	foreach ( $legacy as $legacy_slug => $target_slug ) {
		$legacy_id = $create_term( 'Legacy migration fixture', $legacy_slug );
		if ( ! $legacy_id ) {
			continue;
		}

		$post_id = $create_post(
			sprintf( 'Legacy migration fixture: %s', $legacy_slug ),
			array( $legacy_id, (int) $first['song-an-lanh'] )
		);
		if ( ! $post_id ) {
			continue;
		}

		pgds_category_migration_context( true );
		update_post_meta( $post_id, '_pgds_primary_cat', $legacy_id );
		pgds_category_migration_context( false );

		$migrated = pgds_seed_categories();
		$target   = pgds_category_term( $target_slug );
		$assert( ! is_wp_error( $migrated ), sprintf( 'Legacy category "%s" reconciliation failed.', $legacy_slug ) );
		$assert( false === get_term_by( 'slug', $legacy_slug, 'category' ), sprintf( 'Legacy category "%s" still exists.', $legacy_slug ) );
		$assert( $target instanceof WP_Term && has_term( $target->term_id, 'category', $post_id ), sprintf( 'Target relationship for "%s" was not added.', $legacy_slug ) );
		$assert( has_term( 'song-an-lanh', 'category', $post_id ), sprintf( 'Migrating "%s" removed an unrelated category.', $legacy_slug ) );
		$assert( $target instanceof WP_Term && (int) get_post_meta( $post_id, '_pgds_primary_cat', true ) === (int) $target->term_id, sprintf( 'Primary metadata for "%s" was not migrated.', $legacy_slug ) );
		$forget_term( $legacy_id );
	}

	$emagazine = pgds_category_term( 'emagazine' );
	if ( $emagazine instanceof WP_Term ) {
		$emagazine_id = (int) $emagazine->term_id;
		pgds_category_migration_context( true );
		try {
			$renamed = wp_update_term(
				$emagazine_id,
				'category',
				array(
					'name' => 'Infographic - Emagazine',
					'slug' => 'infographic-emagazine',
				)
			);
		} finally {
			pgds_category_migration_context( false );
		}
		$assert( ! is_wp_error( $renamed ), 'Could not prepare the E-magazine in-place rename fixture.' );
		$repaired = pgds_seed_categories();
		$assert( ! is_wp_error( $repaired ), 'E-magazine in-place rename reconciliation failed.' );
		$restored = pgds_category_term( 'emagazine' );
		$assert( $restored instanceof WP_Term && $emagazine_id === (int) $restored->term_id, 'The E-magazine in-place rename did not preserve its term ID.' );
	}

	$drifted = pgds_category_term( 'loi-song-xanh' );
	if ( $drifted instanceof WP_Term ) {
		$drifted_id = (int) $drifted->term_id;
		pgds_category_migration_context( true );
		try {
			$drift = wp_update_term(
				$drifted_id,
				'category',
				array(
					'name'        => 'Drifted fixture',
					'slug'        => 'drifted-loi-song-xanh',
					'parent'      => 0,
					'description' => 'Drifted fixture description.',
				)
			);
		} finally {
			pgds_category_migration_context( false );
		}
		$assert( ! is_wp_error( $drift ), 'Could not prepare the canonical drift fixture.' );
		$repaired = pgds_seed_categories();
		$restored = pgds_category_term( 'loi-song-xanh' );
		$assert( ! is_wp_error( $repaired ), 'Canonical drift reconciliation failed.' );
		$assert( $restored instanceof WP_Term && $drifted_id === (int) $restored->term_id, 'Canonical drift repair did not preserve the term ID.' );
		$assert( $restored instanceof WP_Term && 'Lối sống xanh' === $restored->name, 'Canonical drift repair did not restore the name.' );
		$assert( $restored instanceof WP_Term && (int) $first['song-an-lanh'] === (int) $restored->parent, 'Canonical drift repair did not restore the parent.' );
		$assert( $restored instanceof WP_Term && '' === (string) $restored->description, 'Canonical drift repair did not restore the description.' );
	}

	$deleted = pgds_category_term( 'am-thuc-chay' );
	if ( $deleted instanceof WP_Term ) {
		$deleted_id = (int) $deleted->term_id;
		pgds_category_migration_context( true );
		try {
			$removed = wp_delete_term( $deleted_id, 'category' );
		} finally {
			pgds_category_migration_context( false );
		}
		$assert( ! is_wp_error( $removed ) && false !== $removed && 0 !== $removed, 'Could not prepare the deleted canonical fixture.' );
		$repaired = pgds_seed_categories();
		$restored = pgds_category_term( 'am-thuc-chay' );
		$assert( ! is_wp_error( $repaired ), 'Deleted canonical category reconciliation failed.' );
		$assert( $restored instanceof WP_Term && $deleted_id !== (int) $restored->term_id, 'Deleted canonical category was not recreated.' );
	}

	$unknown_id = $create_term( 'Unknown category fixture', 'pgds-unknown-category' );
	if ( $unknown_id ) {
		$unknown_post_id = $create_post( 'Unknown category post fixture', array( $unknown_id ) );
		if ( $unknown_post_id ) {
			pgds_category_migration_context( true );
			update_post_meta( $unknown_post_id, '_pgds_primary_cat', $unknown_id );
			pgds_category_migration_context( false );
			$reconciled = pgds_seed_categories();
			$assert( ! is_wp_error( $reconciled ), 'Unknown category retirement failed.' );
			$assert( false === get_term_by( 'slug', 'pgds-unknown-category', 'category' ), 'Unknown category was not retired.' );
			$assert( has_term( 'tin-phat-su', 'category', $unknown_post_id ), 'Unknown-only post did not receive the canonical default category.' );
			$assert( '' === (string) get_post_meta( $unknown_post_id, '_pgds_primary_cat', true ), 'Unknown primary-category metadata was not removed.' );
			$forget_term( $unknown_id );
		}
	}

	$insert = wp_insert_term( 'Blocked fixture', 'category', array( 'slug' => 'pgds-blocked-fixture' ) );
	$assert( is_wp_error( $insert ) && 'pgds_category_locked' === $insert->get_error_code(), 'Direct category creation was not rejected.' );

	$term = pgds_category_term( 'tin-phat-su' );
	if ( $term instanceof WP_Term ) {
		$update_threw = false;
		try {
			wp_update_term( $term->term_id, 'category', array( 'name' => 'Blocked rename' ) );
		} catch ( RuntimeException $exception ) {
			$update_threw = true;
		}
		$assert( $update_threw, 'Direct category update was not rejected.' );
		$assert( 'Tin Phật sự' === get_term( $term->term_id, 'category' )->name, 'Direct category update changed a canonical category.' );

		$delete_threw    = false;
		$previous_default = (int) get_option( 'default_category' );

		try {
			update_option( 'default_category', (int) $first['song-an-lanh'] );
			wp_delete_term( $term->term_id, 'category' );
		} catch ( RuntimeException $exception ) {
			$delete_threw = true;
		} finally {
			update_option( 'default_category', $previous_default );
		}

		if ( ! pgds_category_term( 'tin-phat-su' ) instanceof WP_Term ) {
			$repair = pgds_seed_categories();
			$assert( ! is_wp_error( $repair ), 'Canonical category repair failed after the deletion guard test.' );
			if ( ! is_wp_error( $repair ) ) {
				$first = $repair;
			}
		}

		$assert( $delete_threw, 'Direct category deletion was not rejected.' );
		$assert( pgds_category_term( 'tin-phat-su' ) instanceof WP_Term, 'Direct deletion removed a canonical category.' );
	}

	$editor = get_role( 'editor' );
	if ( $editor ) {
		$assert( $editor->has_cap( 'edit_posts' ), 'Editors cannot assign existing categories to posts.' );
		$assert( ! $editor->has_cap( 'pgds_manage_locked_categories' ), 'Editors unexpectedly have the locked-category management capability.' );
	}

	$previous_user_id = get_current_user_id();
	$editor_user_id   = wp_insert_user(
		array(
			'user_login' => 'pgds-taxonomy-fixture-' . wp_generate_password( 8, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'pgds-taxonomy-fixture-' . wp_generate_password( 8, false, false ) . '@example.invalid',
			'role'       => 'editor',
		)
	);

	if ( is_wp_error( $editor_user_id ) ) {
		$failures[] = 'Could not create the REST editor fixture.';
	} else {
		$track_fixture( 'user', $editor_user_id );
		wp_set_current_user( $editor_user_id );

		$rest_create = $dispatch_rest(
			'POST',
			'/wp/v2/categories',
			array(
				'name' => 'Blocked REST fixture',
				'slug' => 'pgds-blocked-rest-fixture',
			)
		);
		$assert( 403 === $rest_create->get_status(), 'REST category creation was not rejected for an editor.' );

		$rest_update = $dispatch_rest(
			'POST',
			'/wp/v2/categories/' . (int) $first['phat-tich'],
			array( 'name' => 'Blocked REST rename' )
		);
		$assert( 403 === $rest_update->get_status(), 'REST category update was not rejected for an editor.' );

		$rest_delete_term = pgds_category_term( 'am-thuc-chay' );
		$rest_delete      = $dispatch_rest(
			'DELETE',
			'/wp/v2/categories/' . (int) $rest_delete_term->term_id,
			array( 'force' => true )
		);
		$assert( 403 === $rest_delete->get_status(), 'REST category deletion was not rejected for an editor.' );

		$rest_post_id = $create_post( 'REST category assignment fixture', array( (int) $first['tin-phat-su'] ) );
		if ( $rest_post_id ) {
			$rest_post = $dispatch_rest(
				'POST',
				'/wp/v2/posts/' . $rest_post_id,
				array(
					'categories' => array( (int) $first['video'] ),
					'meta'       => array( '_pgds_primary_cat' => (int) $first['video'] ),
				)
			);
			$rest_post_data = rest_get_server()->response_to_data( $rest_post, false );
			$rest_links     = isset( $rest_post_data['_links'] ) && is_array( $rest_post_data['_links'] ) ? $rest_post_data['_links'] : array();

			$assert( 200 === $rest_post->get_status(), 'REST could not update a post with an existing category and matching primary metadata.' );
			$assert( has_term( (int) $first['video'], 'category', $rest_post_id ), 'REST did not assign the existing canonical category.' );
			$assert( (int) $first['video'] === (int) get_post_meta( $rest_post_id, '_pgds_primary_cat', true ), 'REST removed valid primary metadata from the same post update.' );
			$assert( ! isset( $rest_links['wp:action-create-categories'] ), 'The REST post response still advertises category creation to editors.' );
			$assert( isset( $rest_links['wp:action-assign-categories'] ), 'The REST post response does not advertise existing-category assignment to editors.' );
		}

		wp_set_current_user( $previous_user_id );
	}

	wp_set_current_user( $previous_user_id );

	$assignment_post_id = $create_post( 'Canonical assignment fixture', array( (int) $first['video'] ) );
	if ( $assignment_post_id ) {
		update_post_meta( $assignment_post_id, '_pgds_primary_cat', (int) $first['video'] );
		$assert( has_term( 'video', 'category', $assignment_post_id ), 'A post could not be assigned an existing canonical category.' );
		$assert( (int) $first['video'] === (int) get_post_meta( $assignment_post_id, '_pgds_primary_cat', true ), 'A valid assigned primary category was rejected.' );
	}

	foreach ( array_keys( $legacy ) as $legacy_slug ) {
		$assert( false === get_term_by( 'slug', $legacy_slug, 'category' ), sprintf( 'Legacy category "%s" was not retired.', $legacy_slug ) );
	}

	$all_terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	$assert( ! is_wp_error( $all_terms ) && 10 === count( $all_terms ), 'Verification fixtures left a noncanonical category behind.' );
} finally {
	$cleanup();
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		WP_CLI::warning( $failure );
	}
	WP_CLI::error( sprintf( 'Immutable taxonomy verification failed with %d error(s).', count( $failures ) ) );
}

WP_CLI::success( 'Immutable taxonomy verification passed.' );
