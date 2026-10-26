<?php
/**
 * Temporary WordPress SQL-hook lifecycle for product queries.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Attaches query-specific SQL filters and removes the exact same callbacks.
 */
class PPFX_Query_Hook_Scope {

	/**
	 * Posts join callback.
	 *
	 * @var callable|null
	 */
	private $join_callback;

	/**
	 * Posts where callback.
	 *
	 * @var callable|null
	 */
	private $where_callback;

	/**
	 * Posts groupby callback.
	 *
	 * @var callable|null
	 */
	private $groupby_callback;

	/**
	 * Posts having callback.
	 *
	 * @var callable|null
	 */
	private $having_callback;

	/**
	 * Posts orderby callback.
	 *
	 * @var callable|null
	 */
	private $orderby_callback;

	/**
	 * Deferred the_posts cleanup callback.
	 *
	 * @var callable|null
	 */
	private $after_query_callback = null;

	/**
	 * Whether the SQL callbacks are attached.
	 *
	 * @var bool
	 */
	private $attached = false;

	/**
	 * Create a scope from optional SQL callbacks.
	 *
	 * @param callable|null $join_callback posts_join callback.
	 * @param callable|null $where_callback posts_where callback.
	 * @param callable|null $groupby_callback posts_groupby callback.
	 * @param callable|null $having_callback posts_having callback.
	 * @param callable|null $orderby_callback posts_orderby callback.
	 */
	public function __construct( $join_callback = null, $where_callback = null, $groupby_callback = null, $having_callback = null, $orderby_callback = null ) {
		$this->join_callback    = $join_callback;
		$this->where_callback   = $where_callback;
		$this->groupby_callback = $groupby_callback;
		$this->having_callback  = $having_callback;
		$this->orderby_callback = $orderby_callback;
	}

	/**
	 * Combined posts_clauses callback.
	 *
	 * @var callable|null
	 */
	private $clauses_callback = null;

	/**
	 * Attach the configured SQL hooks.
	 *
	 * Uses posts_clauses with priority 20 so that WooCommerce's default posts_clauses
	 * filters (such as order_by_price_desc_post_clauses at priority 10) do not overwrite
	 * or drop PPFX's compiled SQL clauses.
	 *
	 * @return void
	 */
	public function attach(): void {
		if ( $this->attached || ! $this->has_hooks() ) {
			return;
		}

		$this->clauses_callback = function ( $clauses, $wp_query ) {
			// If bound to a specific query, skip secondary queries.
			if ( null !== $this->target_query_hash && ( ! ( $wp_query instanceof WP_Query ) || spl_object_hash( $wp_query ) !== $this->target_query_hash ) ) {
				return $clauses;
			}

			if ( $this->join_callback ) {
				$clauses['join'] = call_user_func( $this->join_callback, $clauses['join'], $wp_query );
			}
			if ( $this->where_callback ) {
				$clauses['where'] = call_user_func( $this->where_callback, $clauses['where'], $wp_query );
			}
			if ( $this->groupby_callback ) {
				$clauses['groupby'] = call_user_func( $this->groupby_callback, $clauses['groupby'], $wp_query );
			}
			if ( $this->having_callback ) {
				$clauses['having'] = call_user_func( $this->having_callback, $clauses['having'] ?? '', $wp_query );
			}
			if ( $this->orderby_callback ) {
				$clauses['orderby'] = call_user_func( $this->orderby_callback, $clauses['orderby'], $wp_query );
			}
			return $clauses;
		};

		add_filter( 'posts_clauses', $this->clauses_callback, 20, 2 );

		$this->attached = true;
	}

	/**
	 * Target query object hash for strict instance binding.
	 *
	 * @var string|null
	 */
	private $target_query_hash = null;

	/**
	 * Deferred loop_end cleanup callback.
	 *
	 * @var callable|null
	 */
	private $loop_end_callback = null;

	/**
	 * Check if this scope has any active SQL callbacks configured.
	 *
	 * @return bool
	 */
	public function has_hooks(): bool {
		return null !== $this->join_callback
			|| null !== $this->where_callback
			|| null !== $this->groupby_callback
			|| null !== $this->having_callback
			|| null !== $this->orderby_callback;
	}

	/**
	 * Remove all configured SQL hooks.
	 *
	 * @return void
	 */
	public function detach(): void {
		if ( ! $this->attached ) {
			return;
		}

		if ( $this->clauses_callback ) {
			remove_filter( 'posts_clauses', $this->clauses_callback, 20 );
			$this->clauses_callback = null;
		}

		if ( $this->after_query_callback ) {
			remove_filter( 'the_posts', $this->after_query_callback, PHP_INT_MAX );
			$this->after_query_callback = null;
		}
		if ( $this->loop_end_callback ) {
			remove_action( 'loop_end', $this->loop_end_callback, PHP_INT_MAX );
			$this->loop_end_callback = null;
		}
		remove_action( 'shutdown', array( $this, 'detach' ), PHP_INT_MAX );

		$this->target_query_hash = null;
		$this->attached          = false;
	}

	/**
	 * Attach hooks until the specified main query has finished executing.
	 *
	 * Binds strictly to the specific WP_Query instance via spl_object_hash to prevent
	 * secondary loops from detaching early, and installs loop_end and shutdown safety nets.
	 *
	 * @param WP_Query $query Query that owns this deferred scope.
	 * @return void
	 */
	public function detach_after_query( WP_Query $query ): void {
		if ( ! $this->has_hooks() ) {
			return;
		}

		$this->target_query_hash = spl_object_hash( $query );
		$this->attach();

		$this->after_query_callback = function ( $posts, $executed_query ) {
			if ( ! ( $executed_query instanceof WP_Query ) ) {
				return $posts;
			}
			if ( null === $this->target_query_hash || spl_object_hash( $executed_query ) !== $this->target_query_hash ) {
				return $posts;
			}

			$this->detach();

			return $posts;
		};

		// Fallback safety net: If the query completes or loop ends without the_posts firing cleanly,
		// ensure SQL closures never leak into subsequent queries or widgets.
		$this->loop_end_callback = function ( $executed_query = null ) {
			if ( $executed_query instanceof WP_Query && null !== $this->target_query_hash ) {
				if ( spl_object_hash( $executed_query ) !== $this->target_query_hash ) {
					return;
				}
			}
			$this->detach();
		};

		add_filter( 'the_posts', $this->after_query_callback, PHP_INT_MAX, 2 );
		add_action( 'loop_end', $this->loop_end_callback, PHP_INT_MAX, 1 );
		add_action( 'shutdown', array( $this, 'detach' ), PHP_INT_MAX );
	}

	/**
	 * Attach hooks and automatically detach after the next filtered query completes.
	 *
	 * Used by shortcode queries where WP_Query is instantiated after query_args filtering.
	 * Detaches on the_posts (even if 0 posts found), with loop_end and shutdown fallbacks.
	 *
	 * @return void
	 */
	public function attach_until_first_query(): void {
		if ( ! $this->has_hooks() ) {
			return;
		}

		$this->attach();

		$this->after_query_callback = function ( $posts, $executed_query ) {
			if ( $executed_query instanceof WP_Query && $executed_query->get( 'ppfx_is_filter_query' ) ) {
				$this->detach();
			}
			return $posts;
		};

		$this->loop_end_callback = function ( $executed_query = null ) {
			if ( $executed_query instanceof WP_Query && $executed_query->get( 'ppfx_is_filter_query' ) ) {
				$this->detach();
			}
		};

		add_filter( 'the_posts', $this->after_query_callback, PHP_INT_MAX, 2 );
		add_action( 'loop_end', $this->loop_end_callback, PHP_INT_MAX, 1 );
		add_action( 'shutdown', array( $this, 'detach' ), PHP_INT_MAX );
	}
}
