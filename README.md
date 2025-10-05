# 📊 WPDB Table Renderer

**Transforma cualquier resultado de `wpdb` en una tabla interactiva, profesional y totalmente funcional dentro de WordPress.**

[![WordPress](https://img.shields.io/badge/WordPress-%E2%9C%93-21759B?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://www.php.net)
![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)

---
# `WPDB_Table_Renderer`  
**Clase genérica para tablas administrativas en WordPress con soporte AJAX, múltiples instancias y callbacks personalizados**

---

## 📜 Changelog

### v2.1.0 (2025-04-05)
- ✅ **Soporte para múltiples tablas en la misma página** mediante el parámetro `$table_id`.
- ✅ **Parámetros de búsqueda, orden y paginación aislados** con prefijos únicos (ej. `mi_tabla_s`, `mi_tabla_orderby`).
- ✅ **AJAX diferenciado por tabla** usando filtros dinámicos:  
  `wpdb_table_ajax_data_{table_id}`, `wpdb_table_ajax_columns_{table_id}`, etc.
- ✅ **Corrección de herencia**: `ajax_response()` ya no es estático (respetando `WP_List_Table`).
- ✅ **Wrapper estático seguro**: `handle_ajax_request()` para registrar el hook AJAX.
- ✅ **Mejorada la seguridad**: validación de nonce y capacidades de usuario.

### v2.0.0 (2025-03-20)
- ✅ Reescritura para soporte **AJAX real** (solo actualiza `<tbody>` y paginación).
- ✅ Separación clara entre renderizado completo y parcial.
- ✅ Soporte para **callbacks de celda** y **acciones por fila**.
- ✅ **Exportación CSV** funcional con filtros aplicados.

### v1.0.0 (2024-11-10)
- ✅ Versión inicial basada en `WP_List_Table`.
- ✅ Soporte para búsqueda, ordenación, paginación y filtros básicos.

---

## 🚀 Cómo usar la clase

### 1. Registrar el hook AJAX (una sola vez)

En tu archivo principal del plugin:

```php
add_action( 'wp_ajax_wpdb_table_load', [ 'WPDB_Table_Renderer', 'handle_ajax_request' ] );
```

> ⚠️ **No uses `wp_ajax_nopriv_`** a menos que sea estrictamente necesario (y no lo es en el admin).

---

### 2. Instanciar una tabla básica

```php
$data = [
    [ 'id' => 1, 'nombre' => 'Producto A', 'precio' => 100 ],
    [ 'id' => 2, 'nombre' => 'Producto B', 'precio' => 200 ],
];

$table = new WPDB_Table_Renderer(
    $data,                          // Datos
    [ 'nombre', 'precio' ],         // Columnas a mostrar
    true,                           // Paginación
    true,                           // Ordenación
    true,                           // Búsqueda
    [ 'nombre' ],                   // Columnas buscables
    false,                          // Filtros
    [],                             // Columnas filtrables
    false,                          // Exportar CSV
    false,                          // Acciones por fila
    [],                             // Callbacks de acciones
    [],                             // Callbacks de celdas
    'productos_tabla'               // ID único (¡obligatorio para AJAX!)
);

$table->render_table();
```

---

### 3. Ejemplo: Listar últimos 15 posts

#### Archivo PHP (`tu-plugin.php`)

```php
// Obtener datos
function get_latest_15_posts() {
    $posts = get_posts( [
        'numberposts' => 15,
        'post_type'   => 'post',
        'post_status' => 'publish'
    ] );

    $data = [];
    foreach ( $posts as $post ) {
        $author = get_userdata( $post->post_author );
        $data[] = [
            'id'     => $post->ID,
            'title'  => $post->post_title,
            'author' => $author ? $author->display_name : '—',
            'date'   => get_the_date( 'Y-m-d', $post->ID ),
            'link'   => get_edit_post_link( $post->ID )
        ];
    }
    return $data;
}

// Registrar filtro para AJAX
add_filter( 'wpdb_table_ajax_data_posts_table', 'get_latest_15_posts' );

// Renderizar
$cell_callbacks = [
    'title' => function( $item ) {
        return '<a href="' . esc_url( $item['link'] ) . '">' . esc_html( $item['title'] ) . '</a>';
    }
];

$table = new WPDB_Table_Renderer(
    get_latest_15_posts(),
    [ 'title', 'author', 'date' ],
    true, true, true,
    [ 'title', 'author' ],
    false, [], false, false, [], $cell_callbacks,
    'posts_table' // ← ID único
);
$table->render_table();
```

#### JavaScript (`admin.js`)

```js
jQuery(document).ready(function($) {
    const $form = $('#table-posts_table form');
    
    function loadTable(params) {
        $.post(ajaxurl, {
            action: 'wpdb_table_load',
            table_id: 'posts_table',
            nonce: wpdbTable.nonce,
            ...params
        }, function(response) {
            if (response.success) {
                const $new = $(response.data.html);
                $form.find('.tablenav, tbody').replaceWith(
                    $new.filter('.tablenav, tbody')
                );
            }
        });
    }

    // Búsqueda
    $form.on('submit', e => {
        e.preventDefault();
        loadTable({ s: $('input[name="posts_table_s"]').val(), paged: 1 });
    });

    // Orden y paginación (ver ejemplo completo en documentación)
});
```

---

### 4. Soporte para múltiples tablas

#### En la misma página:

```php
// Tabla 1: Posts
$table1 = new WPDB_Table_Renderer( $posts_data, [...], ..., 'posts_table' );
$table1->render_table();

// Tabla 2: Usuarios
$table2 = new WPDB_Table_Renderer( $users_data, [...], ..., 'users_table' );
$table2->render_table();
```

#### Registra filtros por tabla:

```php
add_filter( 'wpdb_table_ajax_data_posts_table', fn() => $posts_data );
add_filter( 'wpdb_table_ajax_data_users_table', fn() => $users_data );
```

> ✅ Cada tabla tiene su propio espacio de nombres. **No hay conflictos**.

---

### 5. Callbacks personalizados

#### Celdas

```php
$cell_callbacks = [
    'email' => function( $item, $col ) {
        return '<a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>';
    },
    'status' => function( $item, $col ) {
        $class = $item['status'] === 'activo' ? 'success' : 'error';
        return '<span class="status ' . $class . '">' . esc_html( $item['status'] ) . '</span>';
    }
];
```

#### Acciones por fila

```php
$row_actions_callbacks = [
    'nombre' => function( $item ) {
        return [
            'view'   => '<a href="#">Ver</a>',
            'edit'   => '<a href="#">Editar</a>',
            'delete' => '<a href="#" class="delete-item" data-id="' . $item['id'] . '">Eliminar</a>'
        ];
    }
];
```

> ⚠️ Activa `$enable_row_actions = true` para usar acciones.

---

### 6. Exportación CSV

```php
$table = new WPDB_Table_Renderer(
    $data,
    $columns,
    true, true, true, [], false, [], 
    true, // ← Habilitar exportación
    false, [], [], 'mi_tabla'
);
```

- Aparecerá un botón **"Exportar a CSV"**.
- Los datos exportados respetan **búsqueda, orden y filtros aplicados**.

---

## 🔒 Seguridad

- Todas las entradas se **sanean** (`sanitize_text_field`, `absint`, etc.).
- El endpoint AJAX verifica:
  - **Nonce**: `wp_verify_nonce( $_POST['nonce'], 'wpdb_table_nonce' )`
  - **Capacidades**: `current_user_can( 'manage_options' )`
- Usa `esc_html()`, `esc_attr()`, `esc_url()` en todos los outputs.

---

## 📁 Estructura recomendada

```
tu-plugin/
├── tu-plugin.php
├── includes/
│   └── class-wpdb-table-renderer.php
└── js/
    └── admin-tables.js
```

---

## 💡 Consejos

- **Siempre usa un `$table_id` único** al instanciar.
- **Registra los filtros AJAX** (`wpdb_table_ajax_data_{id}`) antes de renderizar.
- **El JavaScript debe enviar `table_id`** en cada petición AJAX.
- **Prueba sin JavaScript**: la tabla debe funcionar igual (gracias al fallback de formularios).

---

> ✨ **Listo para producción**. Compatible con WordPress 6.0+ y PHP 7.4+.  
> Autor: ByteDogsSyndicate | Licencia: APACHE 2.0
## 🙌 Contribuciones

¡Las contribuciones son bienvenidas! Por favor abre un *issue* o envía un *pull request*.


Estos ejemplos incluyen:

- ✅ Consulta paginada a la base de datos (no carga todos los posts en memoria).
- ✅ Soporte para búsqueda, ordenación y AJAX.
- ✅ Compatibilidad con múltiples tablas.
- ✅ Código optimizado y seguro.

---

## 📄 Documento Markdown: Ejemplos con paginación real (todos los posts)

### 🎯 Objetivo
Listar **todos los posts publicados** (con paginación nativa de WordPress), permitiendo:
- Búsqueda por título.
- Ordenación por título, fecha o autor.
- Navegación sin recargar (AJAX).
- Escalabilidad (funciona con 10 o 100,000 posts).

---

## ✅ 1. Archivo PHP: `tu-plugin.php`

```php
<?php
/**
 * Plugin Name: Tabla de Todos los Posts (con paginación real)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/includes/class-wpdb-table-renderer.php';

// Menú de admin
add_action( 'admin_menu', function() {
    add_menu_page( 'Todos los Posts', 'Todos los Posts', 'edit_posts', 'todos-posts', 'render_todos_posts_page' );
});

// Hook AJAX
add_action( 'wp_ajax_wpdb_table_load', [ 'WPDB_Table_Renderer', 'handle_ajax_request' ] );

// Enqueue JS
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'toplevel_page_todos-posts' !== $hook ) return;
    wp_enqueue_script( 'todos-posts-ajax', plugin_dir_url( __FILE__ ) . 'js/todos-posts.js', [ 'jquery' ], '1.0', true );
    wp_localize_script( 'todos-posts-ajax', 'wpdbTable', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wpdb_table_nonce' )
    ] );
});

// ───────────────────────────────
// FUNCIONES PARA OBTENER DATOS PAGINADOS
// ───────────────────────────────

/**
 * Obtiene el total de posts (para paginación).
 */
function get_total_published_posts_count() {
    global $wpdb;
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'" );
}

/**
 * Obtiene un fragmento de posts (con offset y per_page).
 */
function get_paginated_posts( $paged = 1, $per_page = 20, $search = '', $orderby = 'post_date', $order = 'DESC' ) {
    global $wpdb;

    $offset = ( $paged - 1 ) * $per_page;

    // Sanear orderby
    $allowed_orderby = [ 'post_title', 'post_date', 'post_author' ];
    if ( ! in_array( $orderby, $allowed_orderby ) ) {
        $orderby = 'post_date';
    }
    $order = ( 'asc' === strtolower( $order ) ) ? 'ASC' : 'DESC';

    // Consulta base
    $sql = "SELECT ID, post_title, post_date, post_author FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'";

    // Búsqueda
    if ( $search ) {
        $search = $wpdb->esc_like( $search );
        $sql .= " AND (post_title LIKE '%{$search}%')";
    }

    // Orden
    $sql .= " ORDER BY {$orderby} {$order}";

    // Límite
    $sql .= $wpdb->prepare( " LIMIT %d, %d", $offset, $per_page );

    $results = $wpdb->get_results( $sql, ARRAY_A );

    // Enriquecer con datos de autor
    foreach ( $results as &$row ) {
        $author = get_userdata( $row['post_author'] );
        $row['author_name'] = $author ? $author->display_name : '—';
        $row['edit_link']   = get_edit_post_link( $row['ID'] );
    }

    return $results;
}

// ───────────────────────────────
// FILTROS PARA AJAX
// ───────────────────────────────

add_filter( 'wpdb_table_ajax_data_all_posts', function() {
    // Este filtro NO se usa directamente; los datos se obtienen en prepare_items_ajax()
    return []; 
});

// Hook personalizado para inyectar datos en AJAX
add_action( 'wp_ajax_wpdb_table_load', function() {
    // Interceptamos la petición para inyectar datos frescos
    if ( ! isset( $_POST['table_id'] ) || $_POST['table_id'] !== 'all_posts_table' ) {
        return; // Dejamos que la clase maneje otras tablas
    }

    // Verificaciones de seguridad (repetidas por aislamiento)
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wpdb_table_nonce' ) || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Acceso denegado', 403 );
    }

    $search  = sanitize_text_field( $_POST['s'] ?? '' );
    $orderby = sanitize_text_field( $_POST['orderby'] ?? 'post_date' );
    $order   = sanitize_text_field( $_POST['order'] ?? 'desc' );
    $paged   = absint( $_POST['paged'] ?? 1 );
    $per_page = 20;

    $data = get_paginated_posts( $paged, $per_page, $search, $orderby, $order );
    $total_items = get_total_published_posts_count();

    // Renderizar solo el cuerpo de la tabla
    ob_start();
    ?>
    <tbody>
    <?php foreach ( $data as $row ): ?>
        <tr>
            <td><a href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php echo esc_html( $row['post_title'] ); ?></a></td>
            <td><?php echo esc_html( $row['author_name'] ); ?></td>
            <td><?php echo esc_html( get_the_date( 'Y-m-d', $row['ID'] ) ); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <?php
    $tbody_html = ob_get_clean();

    // Generar paginación manual (simplificada)
    $total_pages = ceil( $total_items / $per_page );
    ob_start();
    ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo sprintf( '%d ítems', $total_items ); ?></span>
            <span class="pagination-links">
                <?php if ( $paged > 1 ): ?>
                    <a class="first-page" href="#" data-paged="1">«</a>
                    <a class="prev-page" href="#" data-paged="<?php echo $paged - 1; ?>">‹</a>
                <?php endif; ?>
                <span class="paging-input">
                    <label for="all_posts_table_paged">Página </label>
                    <input class="current-page" id="all_posts_table_paged" type="text" name="paged" value="<?php echo $paged; ?>" size="1">
                    de <span class="total-pages"><?php echo $total_pages; ?></span>
                </span>
                <?php if ( $paged < $total_pages ): ?>
                    <a class="next-page" href="#" data-paged="<?php echo $paged + 1; ?>">›</a>
                    <a class="last-page" href="#" data-paged="<?php echo $total_pages; ?>">»</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php
    $nav_html = ob_get_clean();

    wp_send_json_success( [
        'html'        => $tbody_html . $nav_html,
        'total_items' => $total_items,
        'per_page'    => $per_page,
        'current_page'=> $paged
    ] );
}, 9 ); // Prioridad 9 para ejecutar antes del método por defecto

// ───────────────────────────────
// RENDERIZADO DE LA PÁGINA
// ───────────────────────────────

function render_todos_posts_page() {
    // Obtener datos para la primera carga (no AJAX)
    $paged   = isset( $_GET['all_posts_table_paged'] ) ? absint( $_GET['all_posts_table_paged'] ) : 1;
    $search  = isset( $_GET['all_posts_table_s'] ) ? sanitize_text_field( $_GET['all_posts_table_s'] ) : '';
    $orderby = isset( $_GET['all_posts_table_orderby'] ) ? sanitize_text_field( $_GET['all_posts_table_orderby'] ) : 'post_date';
    $order   = isset( $_GET['all_posts_table_order'] ) ? sanitize_text_field( $_GET['all_posts_table_order'] ) : 'desc';

    $data = get_paginated_posts( $paged, 20, $search, $orderby, $order );
    $total_items = get_total_published_posts_count();

    // Callback para enlazar el título
    $cell_callbacks = [
        'post_title' => function( $item ) {
            return '<a href="' . esc_url( $item['edit_link'] ) . '">' . esc_html( $item['post_title'] ) . '</a>';
        },
        'post_date' => function( $item ) {
            return get_the_date( 'Y-m-d', $item['ID'] );
        }
    ];

    echo '<div class="wrap">';
    echo '<h1>Todos los Posts (' . number_format( $total_items ) . ')</h1>';

    // Instancia de la tabla
    $table = new WPDB_Table_Renderer(
        $data,
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
        $cell_callbacks,
        'all_posts_table' // ID único
    );

    // Inyectar total_items manualmente (porque la clase espera count($data))
    $table->total_items = $total_items;

    $table->render_table();

    echo '</div>';
}
```

---

## ✅ 2. JavaScript: `js/todos-posts.js`

```js
jQuery(document).ready(function ($) {
    const tableId = 'all_posts_table';
    const $container = $('#table-' + tableId);
    if ($container.length === 0) return;

    const $form = $container.find('form');

    function loadTable(params) {
        $.post(wpdbTable.ajax_url, {
            action: 'wpdb_table_load',
            table_id: tableId,
            nonce: wpdbTable.nonce,
            ...params
        }, function (response) {
            if (response.success) {
                // Reemplazar tbody y paginación
                $form.find('tbody').replaceWith($(response.data.html).filter('tbody'));
                $form.find('.tablenav.bottom').replaceWith($(response.data.html).filter('.tablenav.bottom'));
                
                // Re-vincular eventos de paginación
                bindPaginationEvents();
            }
        }).fail(function () {
            alert('Error al cargar los posts.');
        });
    }

    function bindPaginationEvents() {
        $form.find('.pagination-links a').off('click').on('click', function (e) {
            e.preventDefault();
            const paged = $(this).data('paged');
            const s = $form.find(`input[name="${tableId}_s"]`).val();
            const orderby = $form.find(`input[name="${tableId}_orderby"]`).val() || 'post_date';
            const order = $form.find(`input[name="${tableId}_order"]`).val() || 'desc';
            loadTable({ s, orderby, order, paged });
        });

        // Submit al presionar Enter en el campo de página
        $form.find('.current-page').off('keypress').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                const paged = parseInt($(this).val()) || 1;
                const maxPages = parseInt($form.find('.total-pages').text()) || 1;
                loadTable({
                    s: $form.find(`input[name="${tableId}_s"]`).val(),
                    orderby: $form.find(`input[name="${tableId}_orderby"]`).val() || 'post_date',
                    order: $form.find(`input[name="${tableId}_order"]`).val() || 'desc',
                    paged: Math.min(Math.max(1, paged), maxPages)
                });
            }
        });
    }

    // Búsqueda
    $form.on('submit', function (e) {
        e.preventDefault();
        loadTable({
            s: $form.find(`input[name="${tableId}_s"]`).val(),
            paged: 1
        });
    });

    // Ordenar
    $form.on('click', 'th a', function (e) {
        e.preventDefault();
        const url = new URL(this.href, window.location);
        loadTable({
            s: $form.find(`input[name="${tableId}_s"]`).val(),
            orderby: url.searchParams.get('orderby') || 'post_date',
            order: url.searchParams.get('order') || 'desc',
            paged: 1
        });
    });

    // Inicializar eventos
    bindPaginationEvents();
});
```

---

## 🔑 Claves del enfoque

| Característica | Implementación |
|---------------|----------------|
| **Paginación real** | Usa `LIMIT` y `OFFSET` en SQL (no `get_posts()` con `numberposts=-1`). |
| **Rendimiento** | Solo carga los posts necesarios (20 por página). |
| **Total de items** | Consulta separada con `COUNT(*)`. |
| **AJAX optimizado** | Devuelve solo `<tbody>` y paginación (no toda la tabla). |
| **Seguridad** | Sanitización de `orderby`, nonces, y capacidades. |

---

## 💡 Notas importantes

1. **No uses `get_posts( numberposts => -1 )`** en sitios con muchos posts: consume mucha memoria.
2. **El ejemplo usa un hook AJAX personalizado** (`add_action` con prioridad 9) para inyectar lógica específica de posts. Esto evita tener que modificar la clase principal.
3. **La clase `WPDB_Table_Renderer` se usa solo para el renderizado inicial**. El AJAX lo maneja un endpoint especializado.
4. **Para reutilizar**, crea una clase hija como `Posts_Table extends WPDB_Table_Renderer` si necesitas más control.

---

