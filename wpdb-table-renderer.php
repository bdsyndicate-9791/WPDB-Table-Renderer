<?php
/**
 * WPDB Table Renderer
 *
 * Clase genérica para renderizar tablas administrativas en WordPress con soporte
 * para búsqueda, ordenación, paginación, exportación CSV, callbacks personalizados
 * y AJAX seguro. Diseñada para soportar múltiples instancias en la misma página.
 *
 * @package     ClientPulse Pro
 * @subpackage  Admin/Tables
 * @author      ByteDogsSyndicate
 * @license     GPL-3.0+
 * @version     2.1.0
 *
 * =============
 * CHANGELOG
 * =============
 *
 * v2.1.0 (2025-04-05)
 * - Añadido soporte para múltiples tablas en la misma página mediante $table_id.
 * - Parámetros de búsqueda, orden y paginación ahora usan prefijos únicos.
 * - AJAX diferenciado por tabla usando filtros dinámicos (wpdb_table_ajax_*_{table_id}).
 * - Corregido error de herencia: ajax_response() ya no es estático.
 * - Añadido wrapper estático handle_ajax_request() para hook AJAX.
 * - Mejorada la seguridad con nonces y verificación de capacidades.
 *
 * v2.0.0 (2025-03-20)
 * - Reescritura completa para soporte AJAX real (solo cuerpo de tabla).
 * - Separación de renderizado completo vs parcial.
 * - Soporte para callbacks de celda y acciones de fila.
 * - Exportación CSV funcional con filtros aplicados.
 *
 * v1.0.0 (2024-11-10)
 * - Versión inicial basada en WP_List_Table.
 * - Soporte para búsqueda, ordenación, paginación y filtros básicos.
 */

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
    protected $table_id = 'default_table';

    /**
     * Constructor.
     *
     * @param array  $data                    Datos en formato array de arrays asociativos.
     * @param array  $columns                 Lista de claves de columnas.
     * @param bool   $enable_pagination       ¿Habilitar paginación?
     * @param bool   $enable_sorting          ¿Habilitar ordenación?
     * @param bool   $enable_search           ¿Habilitar búsqueda?
     * @param array  $searchable_columns      Columnas en las que buscar (si null, todas).
     * @param bool   $enable_filters          ¿Habilitar filtros por columna?
     * @param array  $filterable_columns      Columnas filtrables.
     * @param bool   $enable_export           ¿Mostrar botón de exportar CSV?
     * @param bool   $enable_row_actions      ¿Habilitar acciones por fila?
     * @param array  $row_actions_callbacks   Callbacks para acciones por columna.
     * @param array  $cell_callbacks          Callbacks para renderizado de celdas.
     * @param string $table_id                Identificador único para evitar conflictos (sanitize_key).
     */
    public function __construct( $data = array(), $columns = array(), $enable_pagination = true, $enable_sorting = true, $enable_search = true, $searchable_columns = array(), $enable_filters = false, $filterable_columns = array(), $enable_export = false, $enable_row_actions = false, $row_actions_callbacks = array(), $cell_callbacks = array(), $table_id = 'default_table' ) {
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
        $this->table_id               = sanitize_key( $table_id );
        add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
    }

    // ───────────────────────────────
    // BÚSQUEDA CON PREFIJO ÚNICO
    // ───────────────────────────────

    public function get_search_term() {
        $search_key = $this->table_id . '_s';
        return ! empty( $_REQUEST[ $search_key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $search_key ] ) ) : '';
    }

    public function search_box( $text, $input_id ) {
       /* if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
            return;
        }*/

        $input_id = $this->table_id . '_' . $input_id;
        $search_name = $this->table_id . '_s';

        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $text ) . ':</label>';
        echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $search_name ) . '" value="' . esc_attr( $this->get_search_term() ) . '" />';
        submit_button( $text, '', '', false, array( 'id' => 'search-submit-' . $this->table_id ) );
        echo '</p>';
    }

    // ───────────────────────────────
    // PARÁMETROS DE ORDEN Y PAGINACIÓN CON PREFIJO
    // ───────────────────────────────

    private function get_orderby_param() {
        return $this->table_id . '_orderby';
    }

    private function get_order_param() {
        return $this->table_id . '_order';
    }

    private function get_paged_param() {
        return $this->table_id . '_paged';
    }
    
    protected function get_primary_column_name() {
    // Usa la primera columna como primaria, o permite personalización
    return ! empty( $this->columns ) ? $this->columns[0] : 'id';
}

    public function get_orderby() {
        return ! empty( $_GET[ $this->get_orderby_param() ] ) ? sanitize_text_field( $_GET[ $this->get_orderby_param() ] ) : '';
    }

    public function get_order() {
        return ! empty( $_GET[ $this->get_order_param() ] ) ? sanitize_text_field( $_GET[ $this->get_order_param() ] ) : 'asc';
    }

    public function get_pagenum() {
        $paged = ! empty( $_GET[ $this->get_paged_param() ] ) ? absint( $_GET[ $this->get_paged_param() ] ) : 1;
        return max( 1, $paged );
    }

    // ───────────────────────────────
    // PREPARACIÓN DE ÍTEMS
    // ───────────────────────────────

    public function prepare_items( $args = null ) {
        if ( is_null( $args ) ) {
            $search_term = $this->get_search_term();
            $orderby     = $this->get_orderby();
            $order       = $this->get_order();
            $paged       = $this->get_pagenum();
        } else {
            $search_term = isset( $args['s'] ) ? sanitize_text_field( $args['s'] ) : '';
            $orderby     = isset( $args['orderby'] ) ? sanitize_text_field( $args['orderby'] ) : '';
            $order       = isset( $args['order'] ) ? sanitize_text_field( $args['order'] ) : 'asc';
            $paged       = isset( $args['paged'] ) ? absint( $args['paged'] ) : 1;
        }

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $data = $this->raw_data;
        $data = $this->filter_data_by_search( $data, $search_term );

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
        if ( $this->enable_pagination ) {
            $data = array_slice( $data, ( ( $paged - 1 ) * $per_page ), $per_page );
        }

        $this->items = $data;
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $this->enable_pagination ? ceil( $total_items / $per_page ) : 1,
        ) );
    }

    // ───────────────────────────────
    // RENDERIZADO DE LA TABLA COMPLETA
    // ───────────────────────────────

    public function render_table() {
        echo '<div class="wrap wpdb-table-renderer" id="table-' . esc_attr( $this->table_id ) . '">';

        if ( $this->enable_export ) {
            $export_url = add_query_arg( array(
                'wpdb_export_csv' => '1',
                '_wpnonce'        => wp_create_nonce( 'export_csv' )
            ) );
            foreach ( array( $this->table_id . '_s', $this->table_id . '_orderby', $this->table_id . '_order', $this->table_id . '_paged' ) as $param ) {
                $base_param = str_replace( $this->table_id . '_', '', $param );
                if ( ! empty( $_GET[ $param ] ) ) {
                    $export_url = add_query_arg( $base_param, $_GET[ $param ], $export_url );
                }
            }
            echo '<a href="' . esc_url( $export_url ) . '" class="page-title-action">Exportar a CSV</a>';
        }

        if ( $this->enable_filters ) {
            $this->views();
        }

        echo '<form method="get" id="table-form-' . esc_attr( $this->table_id ) . '">';
        echo '<input type="hidden" name="page" value="' . esc_attr( $_REQUEST['page'] ?? 'admin_page_slug' ) . '" />';

        $current_orderby = $this->get_orderby();
        $current_order   = $this->get_order();
        $current_paged   = $this->get_pagenum();

        if ( $current_orderby ) {
            echo '<input type="hidden" name="' . esc_attr( $this->get_orderby_param() ) . '" value="' . esc_attr( $current_orderby ) . '" />';
            echo '<input type="hidden" name="' . esc_attr( $this->get_order_param() ) . '" value="' . esc_attr( $current_order ) . '" />';
        }
        if ( $current_paged > 1 ) {
            echo '<input type="hidden" name="' . esc_attr( $this->get_paged_param() ) . '" value="' . esc_attr( $current_paged ) . '" />';
        }

        $this->search_box( 'Buscar', 'search' );
        $this->prepare_items();
        $this->display();
        echo '</form>';

        echo '</div>';
    }

    // ───────────────────────────────
    // MÉTODOS AUXILIARES (COLUMNAS, FILTROS, ETC.)
    // ───────────────────────────────

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

    public function column_default( $item, $column_name ) {
        if ( isset( $this->cell_callbacks[ $column_name ] ) && is_callable( $this->cell_callbacks[ $column_name ] ) ) {
            return call_user_func( $this->cell_callbacks[ $column_name ], $item, $column_name );
        }
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    protected function handle_row_actions( $item, $column_name, $primary ) {
    // Siempre devolvemos row_actions en la columna primaria
    // para activar el modo responsive, incluso si están vacíos
    if ( $primary ) {
        $actions = array();
        
        // Solo añadir acciones si están habilitadas
        if ( $this->enable_row_actions ) {
            if ( isset( $this->row_actions_callbacks[ $column_name ] ) && is_callable( $this->row_actions_callbacks[ $column_name ] ) ) {
                $actions = call_user_func( $this->row_actions_callbacks[ $column_name ], $item );
            } else {
                // Acción por defecto mínima (puede ser vacía)
                $actions['view'] = '<span class="screen-reader-text">Ver</span>';
            }
        }

        // Devolver SIEMPRE row_actions en la columna primaria
        return $this->row_actions( $actions );
    }
    return '';
}
 


    public function get_table_body_html() {
        ob_start();
        $this->display_tablenav( 'top' );
        $this->display_rows_or_placeholder();
        $this->display_tablenav( 'bottom' );
        return ob_get_clean();
    }

    // ───────────────────────────────
    // EXPORTACIÓN CSV
    // ───────────────────────────────

    public function handle_csv_export() {
        if ( ! $this->enable_export ) return;
        if ( ! isset( $_GET['wpdb_export_csv'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'export_csv' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acceso denegado.' );

        $data = $this->raw_data;
        $data = $this->filter_data_by_search( $data, $this->get_search_term() );

        $orderby = $this->get_orderby();
        $order   = $this->get_order();
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

    // ───────────────────────────────
    // AJAX (NO ESTÁTICO)
    // ───────────────────────────────

    public function ajax_response() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpdb_table_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce', 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $data                   = apply_filters( "wpdb_table_ajax_data_{$this->table_id}", array() );
        $columns                = apply_filters( "wpdb_table_ajax_columns_{$this->table_id}", array() );
        $searchable_columns     = apply_filters( "wpdb_table_ajax_searchable_columns_{$this->table_id}", array() );
        $filterable_columns     = apply_filters( "wpdb_table_ajax_filterable_columns_{$this->table_id}", array() );
        $cell_callbacks         = apply_filters( "wpdb_table_ajax_cell_callbacks_{$this->table_id}", array() );
        $row_actions_callbacks  = apply_filters( "wpdb_table_ajax_row_actions_callbacks_{$this->table_id}", array() );
        $enable_pagination      = apply_filters( "wpdb_table_ajax_enable_pagination_{$this->table_id}", true );
        $enable_sorting         = apply_filters( "wpdb_table_ajax_enable_sorting_{$this->table_id}", true );
        $enable_search          = apply_filters( "wpdb_table_ajax_enable_search_{$this->table_id}", true );
        $enable_filters         = apply_filters( "wpdb_table_ajax_enable_filters_{$this->table_id}", false );
        $enable_row_actions     = apply_filters( "wpdb_table_ajax_enable_row_actions_{$this->table_id}", false );

        if ( empty( $data ) ) {
            wp_send_json_error( 'No data provided for AJAX table.' );
        }

        $table = new self(
            $data,
            $columns,
            $enable_pagination,
            $enable_sorting,
            $enable_search,
            $searchable_columns,
            $enable_filters,
            $filterable_columns,
            false,
            $enable_row_actions,
            $row_actions_callbacks,
            $cell_callbacks,
            $this->table_id
        );

        $table->prepare_items( $_POST );

        wp_send_json_success( array(
            'html'         => $table->get_table_body_html(),
            'total_items'  => $table->get_pagination_arg( 'total_items' ),
            'per_page'     => $table->get_pagination_arg( 'per_page' ),
            'current_page' => isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1,
        ) );
    }

    // ───────────────────────────────
    // WRAPPER ESTÁTICO PARA HOOK AJAX
    // ───────────────────────────────

    public static function handle_ajax_request() {
        if ( ! isset( $_POST['table_id'] ) ) {
            wp_send_json_error( 'Missing table_id', 400 );
        }

        $table_id = sanitize_key( $_POST['table_id'] );
        $instance = new self( array(), array(), true, true, true, array(), false, array(), false, false, array(), array(), $table_id );
        $instance->ajax_response();
    }
}
