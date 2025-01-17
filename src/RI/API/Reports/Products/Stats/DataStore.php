<?php // -*- coding: utf-8 -*-
/**
 * API\Reports\Products\Stats\DataStore class file.
 *
 *
 */

namespace QuickAssortments\COG\RI\API\Reports\Products\Stats;

defined('ABSPATH') || exit;

use QuickAssortments\COG\RI\API\Reports\DataStoreInterface;
use QuickAssortments\COG\RI\API\Reports\Products\DataStore as ProductsDataStore;
use QuickAssortments\COG\RI\TimeInterval;

/**
 * API\Reports\Products\Stats\DataStore.
 */
class DataStore extends ProductsDataStore implements DataStoreInterface
{
    /**
     * Mapping columns to data type to return correct response types.
     *
     * @var array
     */
    protected $column_types = [
        'date_start'       => 'strval',
        'date_end'         => 'strval',
        'product_id'       => 'intval',
        'gross_revenue'    => 'floatval',
        'net_revenue'      => 'floatval',
        'net_profit'       => 'floatval',
        'cost_of_goods_sold' => 'floatval',
        'products_count'   => 'intval',
        'variations_count' => 'intval',
    ];

    /**
     * SQL columns to select in the db query.
     *
     * @var array
     */
    protected $report_columns = [
        'gross_revenue'    => 'SUM(product_gross_revenue) as gross_revenue',
        'net_revenue'      => 'SUM(product_net_revenue) AS net_revenue',
        'net_profit'       => 'SUM(product_net_profit) AS net_profit',
        'cost_of_goods_sold' => 'SUM(product_cost) AS cost_of_goods_sold',
        'products_count'   => 'COUNT(DISTINCT product_id) as products_count',
        'variations_count' => 'COUNT(DISTINCT variation_id) as variations_count',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        // Avoid ambigious column order_id in SQL query.
        $this->report_columns['net_profit'] = str_replace('product_net_profit', $table_name . '.product_net_profit', $this->report_columns['net_profit']);

        $zero_costs = $wpdb->get_results("SELECT order_item_id, product_id, product_cost FROM $table_name WHERE product_cost='0'");
        foreach($zero_costs as $row) {
            $new_cost = get_post_meta($row->product_id, '_qa_cog_cost', true);
            if($new_cost) {
                $wpdb->update($table_name, ['product_cost' => $new_cost], ['product_id' => $row->product_id]);
            }
        }
    }

    /**
     * Returns the report data based on parameters supplied by the user.
     *
     * @param array $query_args Query parameters.
     *
     * @return stdClass|WP_Error Data.
     *
     * @since 3.5.0
     */
    public function get_data($query_args)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // These defaults are only partially applied when used via REST API, as that has its own defaults.
        $defaults   = [
            'per_page'         => get_option('posts_per_page'),
            'page'             => 1,
            'order'            => 'DESC',
            'orderby'          => 'date',
            'before'           => TimeInterval::default_before(),
            'after'            => TimeInterval::default_after(),
            'fields'           => '*',
            'categories'       => [],
            'interval'         => 'week',
            'product_includes' => [],
        ];
        $query_args = wp_parse_args($query_args, $defaults);
        $this->normalize_timezones($query_args, $defaults);

        /*
         * We need to get the cache key here because
         * parent::update_intervals_sql_params() modifies $query_args.
         */
        $cache_key = $this->get_cache_key($query_args);
        $data      = $this->get_cached_data($cache_key);

        if (false === $data) {
            $selections      = $this->selected_columns($query_args);
            $totals_query    = [];
            $intervals_query = [];
            $this->update_sql_query_params($query_args, $totals_query, $intervals_query);

            $db_intervals = $wpdb->get_col(
                "SELECT
							{$intervals_query['select_clause']} AS time_interval
						FROM
							{$table_name}
							{$intervals_query['from_clause']}
						WHERE
							1=1
							{$intervals_query['where_time_clause']}
							{$intervals_query['where_clause']}
						GROUP BY
							time_interval"
            ); // WPCS: cache ok, DB call ok, , unprepared SQL ok.

            $db_interval_count       = count($db_intervals);
            $expected_interval_count = TimeInterval::intervals_between($query_args['after'], $query_args['before'], $query_args['interval']);
            $total_pages             = (int) ceil($expected_interval_count / $intervals_query['per_page']);
            if ($query_args['page'] < 1 || $query_args['page'] > $total_pages) {
                return [];
            }

            $this->update_intervals_sql_params($intervals_query, $query_args, $db_interval_count, $expected_interval_count, $table_name);

            $totals = $wpdb->get_results(
                "SELECT
						{$selections}
					FROM
						{$table_name}
						{$totals_query['from_clause']}
					WHERE
						1=1
						{$totals_query['where_time_clause']}
						{$totals_query['where_clause']}",
                ARRAY_A
            ); // WPCS: cache ok, DB call ok, unprepared SQL ok.

            $segmenter             = new Segmenter($query_args, $this->report_columns);
            $totals[0]['segments'] = $segmenter->get_totals_segments($totals_query, $table_name);

            if (null === $totals) {
                return new \WP_Error('woocommerce_reports_products_stats_result_failed', __('Sorry, fetching revenue data failed.', 'qa-cost-of-goods-margins'));
            }

            if ('' !== $selections) {
                $selections = ', ' . $selections;
            }

            $intervals = $wpdb->get_results(
                "SELECT
							MAX(${table_name}.date_created) AS datetime_anchor,
							{$intervals_query['select_clause']} AS time_interval
							{$selections}
						FROM
							{$table_name}
							{$intervals_query['from_clause']}
						WHERE
							1=1
							{$intervals_query['where_time_clause']}
							{$intervals_query['where_clause']}
						GROUP BY
							time_interval
						ORDER BY
							{$intervals_query['order_by_clause']}
						{$intervals_query['limit']}",
                ARRAY_A
            ); // WPCS: cache ok, DB call ok, unprepared SQL ok.

            if (null === $intervals) {
                return new \WP_Error('woocommerce_reports_products_stats_result_failed', __('Sorry, fetching revenue data failed.', 'qa-cost-of-goods-margins'));
            }

            $totals = (object) $this->cast_numbers($totals[0]);

            $data = (object) [
                'totals'    => $totals,
                'intervals' => $intervals,
                'total'     => $expected_interval_count,
                'pages'     => $total_pages,
                'page_no'   => (int) $query_args['page'],
            ];

            if (TimeInterval::intervals_missing($expected_interval_count, $db_interval_count, $intervals_query['per_page'], $query_args['page'], $query_args['order'], $query_args['orderby'], count($intervals))) {
                $this->fill_in_missing_intervals($db_intervals, $query_args['adj_after'], $query_args['adj_before'], $query_args['interval'], $data);
                $this->sort_intervals($data, $query_args['orderby'], $query_args['order']);
                $this->remove_extra_records($data, $query_args['page'], $intervals_query['per_page'], $db_interval_count, $expected_interval_count, $query_args['orderby'], $query_args['order']);
            } else {
                $this->update_interval_boundary_dates($query_args['after'], $query_args['before'], $query_args['interval'], $data->intervals);
            }
            $segmenter->add_intervals_segments($data, $intervals_query, $table_name);
            $this->create_interval_subtotals($data->intervals);

            wp_cache_set($cache_key, $data, $this->cache_group);
        }

        return $data;
    }

    /**
     * Updates the database query with parameters used for Products Stats report: categories and order status.
     *
     * @param array $query_args       Query arguments supplied by the user.
     * @param array $totals_params    SQL parameters for the totals query.
     * @param array $intervals_params SQL parameters for the intervals query.
     */
    protected function update_sql_query_params($query_args, &$totals_params, &$intervals_params)
    {
        global $wpdb;

        $products_where_clause = '';
        $products_from_clause  = '';

        $order_product_lookup_table = $wpdb->prefix . self::TABLE_NAME;

        $included_products = $this->get_included_products($query_args);
        if ($included_products) {
            $products_where_clause .= " AND {$order_product_lookup_table}.product_id IN ({$included_products})";
        }

        $included_variations = $this->get_included_variations($query_args);
        if ($included_variations) {
            $products_where_clause .= " AND {$order_product_lookup_table}.variation_id IN ({$included_variations})";
        }

        $order_status_filter = $this->get_status_subquery($query_args);
        if ($order_status_filter) {
            $products_from_clause .= " JOIN {$wpdb->prefix}qa_cog_order_stats ON {$order_product_lookup_table}.order_id = {$wpdb->prefix}qa_cog_order_stats.order_id";
            $products_where_clause .= " AND ( {$order_status_filter} )";
        }

        $totals_params                 = array_merge($totals_params, $this->get_time_period_sql_params($query_args, $order_product_lookup_table));
        $totals_params['where_clause'] .= $products_where_clause;
        $totals_params['from_clause'] .= $products_from_clause;

        $intervals_params                 = array_merge($intervals_params, $this->get_intervals_sql_params($query_args, $order_product_lookup_table));
        $intervals_params['where_clause'] .= $products_where_clause;
        $intervals_params['from_clause'] .= $products_from_clause;
    }

    /**
     * Normalizes order_by clause to match to SQL query.
     *
     * @param string $order_by Order by option requeste by user.
     *
     * @return string
     */
    protected function normalize_order_by($order_by)
    {
        if ('date' === $order_by) {
            return 'time_interval';
        }

        return $order_by;
    }
}
