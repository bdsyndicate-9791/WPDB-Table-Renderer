```markdown
# Ejemplo: Dos tablas con AJAX en la misma página

Muestra **dos tablas independientes** en una única página de administración de WordPress:
- **Tabla 1**: Lista de posts publicados.
- **Tabla 2**: Lista de usuarios registrados.

Ambas admiten **búsqueda, ordenación y paginación mediante AJAX**, sin recargar la página.

---

## Archivo PHP: `ejemplo-dos-tablas.php`

```php
<?php
/**
 * Plugin Name: Ejemplo - Dos Tablas con AJAX (Posts y Usuarios)
 * Description: Muestra dos tablas en la misma página: una de posts y otra de usuarios, con AJAX, ordenación y búsqueda.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Asegúrate de que la clase WPDB_Table_Renderer esté cargada
require_once __DIR__ . '/includes/class-wpdb-table-renderer.php';

// Registrar página de administración
add_action( 'admin_menu', 'ejemplo_dos_tablas_menu' );
function ejemplo_dos_tablas_menu() {
    add_menu_page(
        'Dos Tablas con AJAX',
        'Dos Tablas',
        'edit_posts',
        'ejemplo-dos-tablas',
        'ejemplo_dos_tablas_render'
    );
}

// Registrar el hook AJAX (una sola vez)
add_action( 'wp_ajax_wpdb_table_load', [ 'WPDB_Table_Renderer', 'handle_ajax_request' ] );

// Enqueue scripts
add_action( 'admin_enqueue_scripts', 'ejemplo_dos_tablas_enqueue_scripts' );
function ejemplo_dos_tablas_enqueue_scripts( $hook ) {
    if ( 'toplevel_page_ejemplo-dos-tablas' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'ejemplo-dos-tablas-ajax',
        plugin_dir_url( __FILE__ ) . 'js/dos-tablas-ajax.js',
        [ 'jquery' ],
        '1.0',
        true
    );

    wp_localize_script( 'ejemplo-dos-tablas-ajax', 'wpdbTable', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wpdb_table_nonce' )
    ] );
}

// ───────────────────────────────────────
// FUNCIÓN PARA OBTENER POSTS PAGINADOS
// ───────────────────────────────────────

function ejemplo_get_paginated_posts( $paged = 1, $per_page = 10, $search = '', $orderby = 'post_date', $order = 'DESC' ) {
    $allowed_orderby = [ 'post_title', 'post_date', 'post_author' ];
    $orderby = in_array( $orderby, $allowed_orderby ) ? $orderby : 'post_date';
    $order   = ( 'asc' === strtolower( $order ) ) ? 'ASC' : 'DESC';

    $query = new WP_Query( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        's'              => $search,
        'orderby'        => $orderby,
        'order'          => $order,
        'no_found_rows'  => false,
    ] );

    $data = [];
    foreach ( $query->posts as $post ) {
        $author = get_userdata( $post->post_author );
        $data[] = [
            'ID'           => $post->ID,
            'post_title'   => $post->post_title,
            'author_name'  => $author ? $author->display_name : '—',
            'post_date'    => $post->post_date,
            'edit_link'    => get_edit_post_link( $post->ID )
        ];
    }

    $GLOBALS['ejemplo_posts_total'] = $query->found_posts;
    return $data;
}

// ───────────────────────────────────────
// FUNCIÓN PARA OBTENER USUARIOS PAGINADOS
// ───────────────────────────────────────

function ejemplo_get_paginated_users( $paged = 1, $per_page = 10, $search = '', $orderby = 'user_login', $order = 'ASC' ) {
    $allowed_orderby = [ 'user_login', 'user_email', 'display_name' ];
    $orderby = in_array( $orderby, $allowed_orderby ) ? $orderby : 'user_login';
    $order   = ( 'asc' === strtolower( $order ) ) ? 'ASC' : 'DESC';

    $offset = ( $paged - 1 ) * $per_page;

    $args = [
        'number' => $per_page,
        'offset' => $offset,
        'orderby' => $orderby,
        'order'   => $order,
        'search'  => $search ? '*' . $search . '*' : '',
        'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
        'count_total' => true,
    ];

    $user_query = new WP_User_Query( $args );
    $users = $user_query->get_results();

    $data = [];
    foreach ( $users as $user ) {
        $data[] = [
            'ID'           => $user->ID,
            'user_login'   => $user->user_login,
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'roles'        => implode( ', ', $user->roles ),
            'edit_link'    => get_edit_user_link( $user->ID )
        ];
    }

    $GLOBALS['ejemplo_users_total'] = $user_query->get_total();
    return $data;
}

// ───────────────────────────────────────
// FILTROS PARA AJAX - POSTS
// ───────────────────────────────────────

add_filter( 'wpdb_table_ajax_data_posts_table', function() {
    $paged   = isset( $_POST['paged'] )   ? absint( $_POST['paged'] )   : 1;
    $search  = isset( $_POST['s'] )       ? sanitize_text_field( $_POST['s'] ) : '';
    $orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'post_date';
    $order   = isset( $_POST['order'] )   ? sanitize_text_field( $_POST['order'] )   : 'desc';
    return ejemplo_get_paginated_posts( $paged, 10, $search, $orderby, $order );
} );

add_filter( 'wpdb_table_ajax_columns_posts_table', function() {
    return [ 'post_title', 'author_name', 'post_date' ];
} );

add_filter( 'wpdb_table_ajax_searchable_columns_posts_table', function() {
    return [ 'post_title', 'author_name' ];
} );

add_filter( 'wpdb_table_ajax_enable_pagination_posts_table', '__return_true' );
add_filter( 'wpdb_table_ajax_enable_sorting_posts_table', '__return_true' );
add_filter( 'wpdb_table_ajax_enable_search_posts_table', '__return_true' );

// Callbacks para posts
add_filter( 'wpdb_table_ajax_cell_callbacks_posts_table', function() {
    return [
        'post_title' => function( $item ) {
            return '<a href="' . esc_url( $item['edit_link'] ) . '">' . esc_html( $item['post_title'] ) . '</a>';
        },
        'post_date' => function( $item ) {
            return get_the_date( 'Y-m-d', $item['ID'] );
        }
    ];
} );

// ───────────────────────────────────────
// FILTROS PARA AJAX - USUARIOS
// ───────────────────────────────────────

add_filter( 'wpdb_table_ajax_data_users_table', function() {
    $paged   = isset( $_POST['paged'] )   ? absint( $_POST['paged'] )   : 1;
    $search  = isset( $_POST['s'] )       ? sanitize_text_field( $_POST['s'] ) : '';
    $orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'user_login';
    $order   = isset( $_POST['order'] )   ? sanitize_text_field( $_POST['order'] )   : 'asc';
    return ejemplo_get_paginated_users( $paged, 10, $search, $orderby, $order );
} );

add_filter( 'wpdb_table_ajax_columns_users_table', function() {
    return [ 'user_login', 'display_name', 'user_email', 'roles' ];
} );

add_filter( 'wpdb_table_ajax_searchable_columns_users_table', function() {
    return [ 'user_login', 'display_name', 'user_email' ];
} );

add_filter( 'wpdb_table_ajax_enable_pagination_users_table', '__return_true' );
add_filter( 'wpdb_table_ajax_enable_sorting_users_table', '__return_true' );
add_filter( 'wpdb_table_ajax_enable_search_users_table', '__return_true' );

// Callbacks para usuarios
add_filter( 'wpdb_table_ajax_cell_callbacks_users_table', function() {
    return [
        'user_login' => function( $item ) {
            return '<a href="' . esc_url( $item['edit_link'] ) . '">' . esc_html( $item['user_login'] ) . '</a>';
        }
    ];
} );

// ───────────────────────────────────────
// RENDERIZAR LA PÁGINA CON DOS TABLAS
// ───────────────────────────────────────

function ejemplo_dos_tablas_render() {
    // Obtener datos para la carga inicial (no AJAX)
    $posts_paged   = isset( $_GET['posts_table_paged'] )   ? absint( $_GET['posts_table_paged'] )   : 1;
    $posts_search  = isset( $_GET['posts_table_s'] )       ? sanitize_text_field( $_GET['posts_table_s'] ) : '';
    $posts_orderby = isset( $_GET['posts_table_orderby'] ) ? sanitize_text_field( $_GET['posts_table_orderby'] ) : 'post_date';
    $posts_order   = isset( $_GET['posts_table_order'] )   ? sanitize_text_field( $_GET['posts_table_order'] )   : 'desc';

    $users_paged   = isset( $_GET['users_table_paged'] )   ? absint( $_GET['users_table_paged'] )   : 1;
    $users_search  = isset( $_GET['users_table_s'] )       ? sanitize_text_field( $_GET['users_table_s'] ) : '';
    $users_orderby = isset( $_GET['users_table_orderby'] ) ? sanitize_text_field( $_GET['users_table_orderby'] ) : 'user_login';
    $users_order   = isset( $_GET['users_table_order'] )   ? sanitize_text_field( $_GET['users_table_order'] )   : 'asc';

    // Datos iniciales
    $posts_data = ejemplo_get_paginated_posts( $posts_paged, 10, $posts_search, $posts_orderby, $posts_order );
    $users_data = ejemplo_get_paginated_users( $users_paged, 10, $users_search, $users_orderby, $users_order );

    // Callbacks para renderizado inicial
    $posts_cell_callbacks = [
        'post_title' => function( $item ) {
            return '<a href="' . esc_url( $item['edit_link'] ) . '">' . esc_html( $item['post_title'] ) . '</a>';
        },
        'post_date' => function( $item ) {
            return get_the_date( 'Y-m-d', $item['ID'] );
        }
    ];

    $users_cell_callbacks = [
        'user_login' => function( $item ) {
            return '<a href="' . esc_url( $item['edit_link'] ) . '">' . esc_html( $item['user_login'] ) . '</a>';
        }
    ];

    echo '<div class="wrap">';
    echo '<h1>Dos Tablas con AJAX</h1>';

    // Tabla 1: Posts
    $posts_table = new WPDB_Table_Renderer(
        $posts_data,
        [ 'post_title', 'author_name', 'post_date' ],
        true,  // pagination
        true,  // sorting
        true,  // search
        [ 'post_title', 'author_name' ], // searchable
        false, // filters
        [],
        false, // export
        false, // row actions
        [],
        $posts_cell_callbacks,
        'posts_table'
    );
    $posts_table->total_items = $GLOBALS['ejemplo_posts_total'] ?? count( $posts_data );
    $posts_table->render_table();

    echo '<hr style="margin: 40px 0; border: 1px solid #ddd;">';

    // Tabla 2: Usuarios
    $users_table = new WPDB_Table_Renderer(
        $users_data,
        [ 'user_login', 'display_name', 'user_email', 'roles' ],
        true,  // pagination
        true,  // sorting
        true,  // search
        [ 'user_login', 'display_name', 'user_email' ], // searchable
        false, // filters
        [],
        false, // export
        false, // row actions
        [],
        $users_cell_callbacks,
        'users_table'
    );
    $users_table->total_items = $GLOBALS['ejemplo_users_total'] ?? count( $users_data );
    $users_table->render_table();

    echo '</div>';
}
```

---

## Archivo JavaScript: `js/dos-tablas-ajax.js`

```javascript
jQuery(document).ready(function($) {
    // Inicializar cada tabla
    $('.wpdb-table-renderer').each(function() {
        const $container = $(this);
        const tableId = $container.attr('id').replace('table-', '');
        const $form = $container.find('form');

        // Función para cargar tabla vía AJAX
        function loadTableAjax(params) {
            $.post(wpdbTable.ajax_url, {
                action: 'wpdb_table_load',
                table_id: tableId,
                nonce: wpdbTable.nonce,
                ...params
            }, function(response) {
                if (response.success) {
                    const $newContent = $(response.data.html);
                    $form.find('.tablenav.top').replaceWith($newContent.filter('.tablenav.top'));
                    $form.find('tbody').replaceWith($newContent.filter('tbody'));
                    $form.find('.tablenav.bottom').replaceWith($newContent.filter('.tablenav.bottom'));
                } else {
                    alert('Error al cargar los datos.');
                }
            }).fail(function() {
                alert('Error de conexión.');
            });
        }

        // Búsqueda
        $form.on('submit', function(e) {
            e.preventDefault();
            const searchVal = $form.find(`input[name="${tableId}_s"]`).val();
            loadTableAjax({ s: searchVal, paged: 1 });
        });

        // Ordenar columnas
        $form.on('click', 'th a', function(e) {
            e.preventDefault();
            const url = new URL(this.href, window.location);
            const orderby = url.searchParams.get('orderby') || (tableId === 'posts_table' ? 'post_date' : 'user_login');
            const order = url.searchParams.get('order') || 'asc';
            loadTableAjax({ 
                s: $form.find(`input[name="${tableId}_s"]`).val(), 
                orderby: orderby, 
                order: order, 
                paged: 1 
            });
        });

        // Paginación
        $form.on('click', '.tablenav .page-numbers', function(e) {
            e.preventDefault();
            const url = new URL(this.href, window.location);
            const paged = url.searchParams.get('paged') || 1;
            const currentSearch = $form.find(`input[name="${tableId}_s"]`).val();
            const currentOrderby = $form.find(`input[name="${tableId}_orderby"]`).val() || '';
            const currentOrder = $form.find(`input[name="${tableId}_order"]`).val() || 'asc';

            loadTableAjax({
                s: currentSearch,
                orderby: currentOrderby,
                order: currentOrder,
                paged: paged
            });
        });
    });
});
```

---

## Características clave

- **Aislamiento total**: Cada tabla tiene su propio espacio de nombres gracias al parámetro `table_id`.
- **Parámetros únicos**: Los campos de búsqueda, orden y paginación usan prefijos (`posts_table_s`, `users_table_orderby`, etc.).
- **AJAX diferenciado**: El endpoint identifica qué tabla actualizar mediante `table_id`.
- **Rendimiento**: Ambas tablas usan paginación nativa de WordPress (`WP_Query` y `WP_User_Query`).
- **Seguridad**: Validación de nonce y capacidades de usuario en todas las peticiones AJAX.
- **Compatibilidad**: Funciona con o sin JavaScript (fallback a formularios tradicionales).

> **Nota**: Asegúrate de que la clase `WPDB_Table_Renderer` esté disponible en la ruta `/includes/class-wpdb-table-renderer.php` o ajusta la ruta según tu estructura de plugin.
```
