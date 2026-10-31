<?php
/**
 * Pickory Product Filter — POT Generator
 * Extracts all translatable strings for text domain 'pickory-product-filter'
 * and outputs a standard GNU gettext POT catalog.
 */

$text_domain = 'pickory-product-filter';
$base_dir    = dirname( __DIR__ );
$output_file = $base_dir . '/languages/pickory-product-filter.pot';

$exclude_dirs = array(
	'node_modules',
	'vendor',
	'.git',
	'dist-production',
	'tests',
);

$files = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
);

foreach ( $iterator as $path => $info ) {
	$relative = str_replace( $base_dir . DIRECTORY_SEPARATOR, '', $path );
	$parts    = explode( DIRECTORY_SEPARATOR, $relative );
	if ( in_array( $parts[0], $exclude_dirs, true ) ) {
		continue;
	}
	$ext = pathinfo( $path, PATHINFO_EXTENSION );
	if ( in_array( $ext, array( 'php', 'js' ), true ) ) {
		$files[] = $path;
	}
}

$extracted = array(); // [ [ 'msgid' => ..., 'msgid_plural' => ..., 'context' => ..., 'references' => [ ... ] ] ]

$functions = array(
	'__'          => 'single',
	'_e'          => 'single',
	'esc_html__'  => 'single',
	'esc_html_e'  => 'single',
	'esc_attr__'  => 'single',
	'esc_attr_e'  => 'single',
	'_x'          => 'context',
	'_ex'         => 'context',
	'esc_html_x'  => 'context',
	'esc_attr_x'  => 'context',
	'_n'          => 'plural',
	'_nx'         => 'plural_context',
);

foreach ( $files as $file_path ) {
	$ext = pathinfo( $file_path, PATHINFO_EXTENSION );
	$relative_path = str_replace( '\\', '/', str_replace( $base_dir . DIRECTORY_SEPARATOR, '', $file_path ) );
	$code = file_get_contents( $file_path );

	if ( 'php' === $ext ) {
		$tokens = token_get_all( $code );
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) ) {
				continue;
			}

			$token_id   = $tokens[ $i ][0];
			$token_text = $tokens[ $i ][1];
			$line       = $tokens[ $i ][2];

			if ( T_STRING === $token_id && isset( $functions[ $token_text ] ) ) {
				$func_type = $functions[ $token_text ];

				// Look ahead for (
				$j = $i + 1;
				while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
					$j++;
				}

				if ( $j < $count && '(' === $tokens[ $j ] ) {
					// Parse arguments
					$args = array();
					$current_arg = '';
					$paren_depth = 1;
					$k = $j + 1;

					while ( $k < $count && $paren_depth > 0 ) {
						if ( is_array( $tokens[ $k ] ) ) {
							$current_arg .= $tokens[ $k ][1];
						} else {
							if ( '(' === $tokens[ $k ] ) {
								$paren_depth++;
							} elseif ( ')' === $tokens[ $k ] ) {
								$paren_depth--;
								if ( 0 === $paren_depth ) {
									$args[] = trim( $current_arg );
									break;
								}
							} elseif ( ',' === $tokens[ $k ] && 1 === $paren_depth ) {
								$args[] = trim( $current_arg );
								$current_arg = '';
								$k++;
								continue;
							}
							$current_arg .= $tokens[ $k ];
						}
						$k++;
					}

					// Verify arguments
					$clean_args = array();
					foreach ( $args as $arg ) {
						$arg = trim( $arg );
						if ( ( str_starts_with( $arg, "'" ) && str_ends_with( $arg, "'" ) ) ||
						     ( str_starts_with( $arg, '"' ) && str_ends_with( $arg, '"' ) ) ) {
							$clean_args[] = substr( $arg, 1, -1 );
						} else {
							$clean_args[] = null;
						}
					}

					$msgid        = null;
					$msgid_plural = null;
					$context      = null;
					$domain       = null;

					if ( 'single' === $func_type && count( $clean_args ) >= 2 ) {
						$msgid  = $clean_args[0];
						$domain = $clean_args[1];
					} elseif ( 'context' === $func_type && count( $clean_args ) >= 3 ) {
						$msgid   = $clean_args[0];
						$context = $clean_args[1];
						$domain  = $clean_args[2];
					} elseif ( 'plural' === $func_type && count( $clean_args ) >= 4 ) {
						$msgid        = $clean_args[0];
						$msgid_plural = $clean_args[1];
						$domain       = $clean_args[3];
					} elseif ( 'plural_context' === $func_type && count( $clean_args ) >= 5 ) {
						$msgid        = $clean_args[0];
						$msgid_plural = $clean_args[1];
						$context      = $clean_args[3];
						$domain       = $clean_args[4];
					}

					if ( $domain === $text_domain && null !== $msgid && '' !== $msgid ) {
						$key = ( $context ? $context . "\x04" : '' ) . $msgid;
						if ( ! isset( $extracted[ $key ] ) ) {
							$extracted[ $key ] = array(
								'msgid'        => $msgid,
								'msgid_plural' => $msgid_plural,
								'context'      => $context,
								'references'   => array(),
							);
						}
						$ref = "{$relative_path}:{$line}";
						if ( ! in_array( $ref, $extracted[ $key ]['references'], true ) ) {
							$extracted[ $key ]['references'][] = $ref;
						}
					}
				}
			}
		}
	} elseif ( 'js' === $ext ) {
		// Simple regex for JS i18n
		if ( preg_match_all( "/\\b__(?:\\s*\\(\\s*['\"]([^'\"]+)['\"]\\s*,\\s*['\"]pickory-product-filter['\"])/", $code, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $match ) {
				$msgid = $match[0];
				$offset = $match[1];
				$line = substr_count( substr( $code, 0, $offset ), "\n" ) + 1;
				$key = $msgid;
				if ( ! isset( $extracted[ $key ] ) ) {
					$extracted[ $key ] = array(
						'msgid'        => $msgid,
						'msgid_plural' => null,
						'context'      => null,
						'references'   => array(),
					);
				}
				$ref = "{$relative_path}:{$line}";
				if ( ! in_array( $ref, $extracted[ $key ]['references'], true ) ) {
					$extracted[ $key ]['references'][] = $ref;
				}
			}
		}
	}
}

echo "Extracted " . count( $extracted ) . " unique translation strings.\n";

$plugin_file = dirname( __DIR__ ) . '/pickory-product-filter.php';
$plugin_version = '1.0.3';
if ( file_exists( $plugin_file ) ) {
	$header_content = file_get_contents( $plugin_file );
	if ( preg_match( '/\*\s*Version:\s*([^\r\n]+)/i', $header_content, $m ) ) {
		$plugin_version = trim( $m[1] );
	}
}

$pot = '# Copyright (C) ' . date( 'Y' ) . ' Pickory' . "\n";
$pot .= '# This file is distributed under the GPL-2.0-or-later.' . "\n";
$pot .= 'msgid ""' . "\n";
$pot .= 'msgstr ""' . "\n";
$pot .= '"Project-Id-Version: Pickory Product Filter ' . $plugin_version . '\n"' . "\n";
$pot .= '"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/pickory-product-filter\n"' . "\n";
$pot .= '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:i:s+00:00' ) . '\n"' . "\n";
$pot .= '"MIME-Version: 1.0\n"' . "\n";
$pot .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$pot .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$pot .= '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"' . "\n";
$pot .= '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"' . "\n";
$pot .= '"Language-Team: LANGUAGE <LL@li.org>\n"' . "\n";
$pot .= '"X-Generator: Pickory POT Generator 1.0\n"' . "\n";
$pot .= '"X-Domain: pickory-product-filter\n"' . "\n\n";

ksort( $extracted );

foreach ( $extracted as $entry ) {
	if ( ! empty( $entry['references'] ) ) {
		$pot .= '#: ' . implode( ' ', $entry['references'] ) . "\n";
	}
	if ( ! empty( $entry['context'] ) ) {
		$pot .= 'msgctxt "' . addcslashes( $entry['context'], '"' ) . "\"\n";
	}
	$pot .= 'msgid "' . addcslashes( $entry['msgid'], '"' ) . "\"\n";
	if ( ! empty( $entry['msgid_plural'] ) ) {
		$pot .= 'msgid_plural "' . addcslashes( $entry['msgid_plural'], '"' ) . "\"\n";
		$pot .= 'msgstr[0] ""' . "\n";
		$pot .= 'msgstr[1] ""' . "\n\n";
	} else {
		$pot .= 'msgstr ""' . "\n\n";
	}
}

file_put_contents( $output_file, $pot );
echo "Wrote POT file to: {$output_file}\n";
