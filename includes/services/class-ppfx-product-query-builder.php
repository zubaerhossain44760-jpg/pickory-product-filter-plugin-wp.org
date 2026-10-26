<?php
/**
 * Product query orchestration for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once PPFX_PLUGIN_DIR . 'includes/services/class-ppfx-query-hook-scope.php';
require_once PPFX_PLUGIN_DIR . 'includes/services/class-ppfx-query-clause-compiler.php';

/**
 * Backward-compatible façade for filtered product queries.
 */
class PPFX_Product_Query_Builder {

	/**
	 * REST request instance.
	 *
	 * @var WP_REST_Request|null
	 */
	private $request = null;

	/**
	 * Plain request parameters used by server-side filtering.
	 *
	 * @var array<string, mixed>
	 */
	private $params = array();

	/**
	 * Filter-clause compiler.
	 *
	 * @var PPFX_Query_Clause_Compiler|null
	 */
	private $compiler = null;

	/**
	 * Create a builder from a REST request or server-side parameters.
	 *
	 * @param WP_REST_Request|array|null $request REST request or plain SSR parameters.
	 */
	public function __construct( $request = null ) {
		if ( $request instanceof WP_REST_Request ) {
			$this->request = $request;
			$this->params  = PPFX_Utility::normalize_public_filter_params( $request );
			return;
		}

		$this->params = is_array( $request ) ? PPFX_Utility::normalize_public_filter_params( $request ) : array();
	}

	/**
	 * Apply this builder's filters to WooCommerce's existing main query.
	 *
	 * SQL hooks stay attached until this exact query has completed, preventing
	 * nested or secondary queries from removing them early.
	 *
	 * @param WP_Query $query Main WooCommerce product query.
	 * @return void
	 */
	public function apply_to_query( WP_Query $query ): void {
		$compilation = $this->compiler()->compile( $this->get_params() );
		$args        = $compilation['args'];

		$query->set( 'ppfx_is_filter_query', true );
		if ( ! empty( $args['tax_query'] ) ) {
			$query->set( 'tax_query', self::merge_query_clauses( $query->get( 'tax_query' ), $args['tax_query'] ) );
		}
		if ( ! empty( $args['meta_query'] ) ) {
			$query->set( 'meta_query', self::merge_query_clauses( $query->get( 'meta_query' ), $args['meta_query'] ) );
		}
		if ( isset( $args['orderby'] ) ) {
			$query->set( 'orderby', $args['orderby'] );
		}
		if ( isset( $args['order'] ) ) {
			$query->set( 'order', $args['order'] );
		}
		if ( isset( $args['meta_key'] ) && '' !== $args['meta_key'] ) {
			$query->set( 'meta_key', $args['meta_key'] );
		}

		$compilation['scope']->detach_after_query( $query );
	}

	/**
	 * Apply filters from the request and return an isolated WP_Query.
	 *
	 * @param bool $ids_only When true, return only product IDs.
	 * @return WP_Query
	 */
	public function get_query( bool $ids_only = false ): WP_Query {
		$compilation = $this->compiler()->compile( $this->get_params(), $ids_only );
		$scope       = $compilation['scope'];

		$scope->attach();
		try {
			$query = new WP_Query( $compilation['args'] );
		} finally {
			$scope->detach();
		}

		return $query;
	}

	/**
	 * Read all query parameters from the REST request or server-side input.
	 *
	 * @return array<string, mixed>
	 */
	private function get_params(): array {
		return $this->request instanceof WP_REST_Request ? $this->request->get_params() : $this->params;
	}

	/**
	 * Combine PPFX clauses with clauses WooCommerce already applied.
	 *
	 * @param mixed $existing Existing tax_query or meta_query value.
	 * @param array $additional PPFX clauses.
	 * @return array
	 */
	public static function merge_query_clauses( $existing, array $additional ): array {
		if ( ! is_array( $existing ) || empty( $existing ) ) {
			return $additional;
		}

		return array(
			'relation' => 'AND',
			$existing,
			$additional,
		);
	}

	/**
	 * Merge PPFX filter parameters into an existing WP_Query args array (e.g. for shortcodes or block queries).
	 *
	 * Ensures native shortcode attributes (such as category="hoodies") are intersected safely
	 * with URL-driven filters without blind overwrites.
	 *
	 * @param array $query_args Existing query args.
	 * @param array $raw_params Filter parameters.
	 * @return array Modified query args.
	 */
	public static function merge_query_args_with_params( array $query_args, array $raw_params ): array {
		$builder     = new self( $raw_params );
		$compilation = $builder->compiler()->compile( $raw_params );
		$ppfx_args   = $compilation['args'];

		$query_args['ppfx_is_filter_query'] = true;

		if ( ! empty( $ppfx_args['tax_query'] ) ) {
			$query_args['tax_query'] = self::merge_query_clauses( $query_args['tax_query'] ?? array(), $ppfx_args['tax_query'] );
		}
		if ( ! empty( $ppfx_args['meta_query'] ) ) {
			$query_args['meta_query'] = self::merge_query_clauses( $query_args['meta_query'] ?? array(), $ppfx_args['meta_query'] );
		}
		if ( ! empty( $raw_params['orderby'] ) && isset( $ppfx_args['orderby'] ) ) {
			$query_args['orderby'] = $ppfx_args['orderby'];
		}
		if ( ! empty( $raw_params['order'] ) && isset( $ppfx_args['order'] ) ) {
			$query_args['order'] = $ppfx_args['order'];
		}
		if ( isset( $ppfx_args['meta_key'] ) && '' !== $ppfx_args['meta_key'] ) {
			$query_args['meta_key'] = $ppfx_args['meta_key'];
		}

		// Attach SQL hook scope to global query lifecycle if SQL-level filters are needed.
		$compilation['scope']->attach_until_first_query();

		return $query_args;
	}

	/**
	 * Get the shared query-clause compiler.
	 *
	 * @return PPFX_Query_Clause_Compiler
	 */
	private function compiler(): PPFX_Query_Clause_Compiler {
		if ( null === $this->compiler ) {
			$this->compiler = new PPFX_Query_Clause_Compiler();
		}

		return $this->compiler;
	}
}
