<?php
/**
 * Source-inspection helpers shared by the test cases.
 *
 * Kept apart from helper-testcase.php because a file may declare either
 * functions or a class, not both.
 *
 * @package WP-Print
 */

/**
 * Read a file from the plugin root.
 *
 * @param string $relative Path relative to the plugin root.
 * @return string
 */
function wp_print_test_read( $relative ) {
	return (string) file_get_contents( WP_PRINT_DIR . $relative );
}

/**
 * Every shipped PHP file, as paths relative to the plugin root.
 *
 * Built from two glob() calls rather than one GLOB_BRACE pattern: GLOB_BRACE is
 * a GNU extension and is not defined in every PHP build, including some of the
 * musl-based ones, where it would silently match nothing.
 *
 * @return string[]
 */
function wp_print_test_php_files() {
	$files = array_merge(
		(array) glob( WP_PRINT_DIR . '*.php' ),
		(array) glob( WP_PRINT_DIR . 'includes/*.php' )
	);

	return array_map(
		static function ( $path ) {
			return str_replace( WP_PRINT_DIR, '', $path );
		},
		array_filter( $files )
	);
}

/**
 * One shipped file with its comments removed.
 *
 * Grepping raw source for a retired symbol gives false positives from the very
 * comment that explains why the symbol is gone, so these assertions only ever
 * look at live code.
 *
 * @param string $relative Path relative to the plugin root.
 * @return string
 */
function wp_print_test_code( $relative ) {
	$code = '';

	foreach ( token_get_all( wp_print_test_read( $relative ) ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}

			$code .= $token[1];
			continue;
		}

		$code .= $token;
	}

	return $code;
}

/**
 * Every shipped PHP file concatenated, with all comments removed.
 *
 * @param string[] $skip Relative paths to leave out.
 * @return string
 */
function wp_print_test_source_code( array $skip = array() ) {
	$code = '';

	foreach ( wp_print_test_php_files() as $relative ) {
		if ( in_array( $relative, $skip, true ) ) {
			continue;
		}

		$code .= wp_print_test_code( $relative );
	}

	return $code;
}
