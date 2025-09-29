<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPDB_Table_Renderer extends WP_List_Table {

    protected $raw_data = array();
    protected $columns = array();
    protected $enable_pagination = true;
    protected $enable_sorting = true;
    protected $enable_search = true;
    protected $enable_filters = false;
    protected $enable_export = false;
    protected $enable_row_actions = false;
    protected $row_actions_callbacks = array();
    protected $cell_callbacks = array();
    protected $searchable_columns = array();
    protected $filterable_columns = array();
    protected $total_items = 0;

    /**
     * Constructor.
     *
     * @param array $data
     * @param array $columns
     * @param bool  $enable_pagination
     * @param bool  $enable_sorting
     * @param bool  $enable_search
     * @param array $searchable_columns
     * @param bool  $enable_filters
     * @param array $filterable_columns
     * @param bool  $enable_export
     * @param bool  $enable_row_actions
     * @param array $row_actions_callbacks (formato: [ 'col_key' => callback ])
     * @param array $cell_callbacks (formato: [ 'col_name' => callback ])
     */
    public function __construct( $data = array(), $columns = array(), $enable_pagination = true, $enable_sorting = true, $enable_search = true, $searchable_columns = array(), $enable_filters = false, $filterable_columns = array(), $enable_export = false, $enable_row_actions = false, $row_actions_callbacks = array(), $cell_callbacks = array() ) {
        parent::__construct( array(
            'singular' => 'item',
            'plural'   => 'items',
            'ajax'     => true,
        ) );

        $this->raw_data               = $data;
        $this->columns                = empty( $columns ) && ! empty( $data ) ? array_keys( (array) $data[0] ) : $columns;
        $this->columns                = array_map( 'strval', $this->columns );
        $this->enable_pagination      = $enable_pagination;
        $this->enable_sorting         = $enable_sorting;
        $this->enable_search          = $enable_search;
        $this->enable_filters         = $enable_filters;
        $this->enable_export          = $enable_export;
        $this->enable_row_actions     = $enable_row_actions;
        $this->row_actions_callbacks  = $row_actions_callbacks;
        $this->cell_callbacks         = $cell_callbacks;
        $this->searchable_columns     = empty( $searchable_columns ) ? null : array_map( 'strval', $searchable_columns );
        $this->filterable_columns     = array_map( 'strval', $filterable_columns );
        $this->total_items            = count( $data );

        add_action( 'wp_ajax_wpdb_table_action', array( $this, 'ajax_response' ) );
        add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
    }

    public function get_columns() {
        $cols = array();
        foreach ( $this->columns as $col ) {
            $cols[ $col ] = ucfirst( str_replace( '_', ' ', $col ) );
        }
        return $cols;
    }

    protected function get_sortable_columns() {
        if ( ! $this->enable_sorting ) return array();
        $sortable = array();
        foreach ( $this->columns as $col ) {
            $sortable[ $col ] = array( $col, true );
        }
        return $sortable;
    }

    public function get_search_term() {
        return ! empty( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
    }

    protected function filter_data_by_search( $data, $search_term ) {
        if ( empty( $search_term ) ) return $data;
        $searchable = $this->searchable_columns ?: $this->columns;
        $filtered = array();
        foreach ( $data as $row ) {
            foreach ( $searchable as $col ) {
                if ( isset( $row[ $col ] ) && is_scalar( $row[ $col ] ) ) {
                    if ( stripos( (string) $row[ $col ], $search_term ) !== false ) {
                        $filtered[] = $row;
                        break;
                    }
                }
            }
        }
        return $filtered;
    }

    protected function get_views() {
        if ( ! $this->enable_filters || empty( $this->filterable_columns ) ) {
            return array();
        }

        $views = array();
        $current_filter = ! empty( $_GET['column_filter'] ) ? sanitize_text_field( $_GET['column_filter'] ) : '';
        $current_value  = ! empty( $_GET['filter_value'] )  ? sanitize_text_field( $_GET['filter_value'] )  : '';

        foreach ( $this->filterable_columns as $col ) {
            if ( ! in_array( $col, $this->columns ) ) continue;

            $values = wp_list_pluck( $this->raw_data, $col );
            $values = array_unique( array_filter( $values, 'strlen' ) );
            sort( $values );

            $views[ $col ] = '<select name="filter_value" onchange="this.form.submit()">';
            $views[ $col ] .= '<option value="">' . sprintf( 'Todos %s', ucfirst( str_replace( '_', ' ', $col ) ) ) . '</option>';
            foreach ( $values as $val ) {
                $selected = ( $current_filter === $col && $current_value === $val ) ? ' selected' : '';
                $views[ $col ] .= '<option value="' . esc_attr( $val ) . '"' . $selected . '>' . esc_html( $val ) . '</option>';
            }
            $views[ $col ] .= '</select>';
            $views[ $col ] .= '<input type="hidden" name="column_filter" value="' . esc_attr( $col ) . '" />';
        }

        return $views;
    }

    protected function filter_data_by_column_filter( $data ) {
        $column_filter = ! empty( $_GET['column_filter'] ) ? sanitize_text_field( $_GET['column_filter'] ) : '';
        $filter_value  = ! empty( $_GET['filter_value'] )  ? sanitize_text_field( $_GET['filter_value'] )  : '';

        if ( ! $this->enable_filters || ! $column_filter || ! $filter_value ) {
            return $data;
        }

        if ( ! in_array( $column_filter, $this->filterable_columns ) ) {
            return $data;
        }

        return array_filter( $data, function( $row ) use ( $column_filter, $filter_value ) {
            return isset( $row[ $column_filter ] ) && (string) $row[ $column_filter ] === (string) $filter_value;
        } );
    }

    public function handle_csv_export() {
        if ( ! $this->enable_export ) return;
        if ( ! isset( $_GET['wpdb_export_csv'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'export_csv' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acceso denegado.' );

        $data = $this->raw_data;
        $data = $this->filter_data_by_search( $data, $this->get_search_term() );
        $data = $this->filter_data_by_column_filter( $data );

        $orderby = ! empty( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : '';
        $order   = ! empty( $_GET['order'] )   ? sanitize_text_field( $_GET['order'] )   : 'asc';
        if ( $orderby && $this->enable_sorting && in_array( $orderby, $this->columns ) ) {
            usort( $data, function( $a, $b ) use ( $orderby, $order ) {
                $val_a = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
                $val_b = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';
                if ( is_numeric( $val_a ) && is_numeric( $val_b ) ) {
                    $cmp = $val_a - $val_b;
                } else {
                    $cmp = strcasecmp( (string) $val_a, (string) $val_b );
                }
                return ( 'asc' === strtolower( $order ) ) ? $cmp : -$cmp;
            });
        }

        $filename = 'export-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, $this->columns );
        foreach ( $data as $row ) {
            $line = array();
            foreach ( $this->columns as $col ) {
                $line[] = isset( $row[ $col ] ) ? $row[ $col ] : '';
            }
            fputcsv( $output, $line );
        }
        fclose( $output );
        exit;
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $data = $this->raw_data;
        $data = $this->filter_data_by_search( $data, $this->get_search_term() );
        $data = $this->filter_data_by_column_filter( $data );

        $orderby = ! empty( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : '';
        $order   = ! empty( $_GET['order'] )   ? sanitize_text_field( $_GET['order'] )   : 'asc';
        if ( $orderby && $this->enable_sorting && in_array( $orderby, $this->columns ) ) {
            usort( $data, function( $a, $b ) use ( $orderby, $order ) {
                $val_a = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
                $val_b = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';
                if ( is_numeric( $val_a ) && is_numeric( $val_b ) ) {
                    $cmp = $val_a - $val_b;
                } else {
                    $cmp = strcasecmp( (string) $val_a, (string) $val_b );
                }
                return ( 'asc' === strtolower( $order ) ) ? $cmp : -$cmp;
            });
        }

        $total_items = count( $data );
        $per_page = $this->enable_pagination ? $this->get_items_per_page( 'items_per_page', 20 ) : $total_items;
        $current_page = $this->get_pagenum();
        if ( $this->enable_pagination ) {
            $data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
        }

        $this->items = $data;
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $this->enable_pagination ? ceil( $total_items / $per_page ) : 1,
        ) );
    }

    public function column_default( $item, $column_name ) {
        // Callback personalizado
        if ( isset( $this->cell_callbacks[ $column_name ] ) && is_callable( $this->cell_callbacks[ $column_name ] ) ) {
            return call_user_func( $this->cell_callbacks[ $column_name ], $item, $column_name );
        }

        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    protected function handle_row_actions( $item, $column_name, $primary ) {
        if ( ! $this->enable_row_actions || ! $primary ) {
            return '';
        }

        $actions = array();

        // Callback por columna primaria
        if ( isset( $this->row_actions_callbacks[ $column_name ] ) && is_callable( $this->row_actions_callbacks[ $column_name ] ) ) {
            $actions = call_user_func( $this->row_actions_callbacks[ $column_name ], $item );
        } else {
            // Acciones por defecto (solo como ejemplo)
            $actions['view'] = '<a href="#">Ver</a>';
        }

        return $this->row_actions( $actions );
    }

    public function render_table() {
        echo '<div class="wrap wpdb-table-renderer">';

        if ( $this->enable_export ) {
            $export_url = add_query_arg( array(
                'wpdb_export_csv' => '1',
                '_wpnonce'        => wp_create_nonce( 'export_csv' )
            ) );
            foreach ( array( 's', 'orderby', 'order', 'column_filter', 'filter_value' ) as $param ) {
                if ( ! empty( $_GET[ $param ] ) ) {
                    $export_url = add_query_arg( $param, $_GET[ $param ], $export_url );
                }
            }
            echo '<a href="' . esc_url( $export_url ) . '" class="page-title-action">Exportar a CSV</a>';
        }

        $this->prepare_items();
        if ( $this->enable_filters ) {
            $this->views();
        }
        $this->search_box( 'Buscar', 'wpdb_search' );
        $this->display();

        echo '</div>';
    }

    public function ajax_response() {
        ob_start();
        $this->render_table();
        $output = ob_get_clean();
        wp_send_json_success( $output );
    }
}