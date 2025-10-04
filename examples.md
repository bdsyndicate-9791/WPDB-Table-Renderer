```markdown
# WPDB_Table_Renderer – Ejemplos de uso

Documentación técnica con ejemplos prácticos para implementar tablas administrativas en WordPress usando la clase `WPDB_Table_Renderer`, con soporte para AJAX, paginación, búsqueda y múltiples instancias.

---

## Índice

1. [Listar últimos 15 posts (carga estática)](#listar-últimos-15-posts-carga-estática)
2. [Listar todos los posts con paginación real (SQL directo)](#listar-todos-los-posts-con-paginación-real-sql-directo)
3. [Listar todos los posts con WP_Query](#listar-todos-los-posts-con-wp_query)
4. [Soporte para múltiples tablas en la misma página](#soporte-para-múltiples-tablas-en-la-misma-página)
5. [Callbacks personalizados](#callbacks-personalizados)
6. [Exportación CSV](#exportación-csv)

---

## Listar últimos 15 posts (carga estática)

Este ejemplo carga los últimos 15 posts al renderizar la página. Es ideal para conjuntos pequeños de datos.

### Archivo: `tu-plugin.php`

```php
<?php
// Obtener los últimos 15 posts
function get_latest_15_posts() {
    $posts = get_posts([
        'numberposts' => 15,
        'post_type'   => 'post',
        'post_status' => 'publish',
        'orderby'     => 'date',
        'order'       => 'DESC'
    ]);

    $data = [];
    foreach ($posts as $post) {
        $author = get_userdata($post->post_author);
        $data[] = [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'author'    => $author ? $author->display_name : '—',
            'date'      => get_the_date('Y-m-d H:i', $post->ID),
            'edit_link' => get_edit_post_link($post->ID)
        ];
    }
    return $data;
}

// Registrar filtro para AJAX
add_filter('wpdb_table_ajax_data_posts_table', 'get_latest_15_posts');

// Renderizar la tabla
function render_latest_posts_page() {
    $posts_data = get_latest_15_posts();

    $cell_callbacks = [
        'title' => function($item, $column_name) {
            return '<a href="' . esc_url($item['edit_link']) . '">' . esc_html($item['title']) . '</a>';
        },
        'date' => function($item, $column_name) {
            return '<em>' . esc_html($item['date']) . '</em>';
        }
    ];

    echo '<div class="wrap">';
    echo '<h1>Últimos 15 Posts</h1>';

    $table = new WPDB_Table_Renderer(
        $posts_data,
        ['title', 'author', 'date'],
        true,  // pagination
        true,  // sorting
        true,  // search
        ['title', 'author'], // searchable columns
        false, // filters
        [],
        false, // export
        false, // row actions
        [],
        $cell_callbacks,
        'posts_table'
    );

    $table->render_table();
    echo '</div>';
}
```

### Archivo: `js/posts-table.js`

```javascript
jQuery(document).ready(function($) {
    const $container = $('#table-posts_table');
    if ($container.length === 0) return;

    const $form = $container.find('form');

    function loadTable(params) {
        $.post(ajaxurl, {
            action: 'wpdb_table_load',
            table_id: 'posts_table',
            nonce: wpdbTable.nonce,
            ...params
        }, function(response) {
            if (response.success) {
                const $new = $(response.data.html);
                $form.find('.tablenav.top').replaceWith($new.filter('.tablenav.top'));
                $form.find('tbody').replaceWith($new.filter('tbody'));
                $form.find('.tablenav.bottom').replaceWith($new.filter('.tablenav.bottom'));
            }
        });
    }

    // Búsqueda
    $form.on('submit', function(e) {
        e.preventDefault();
        const s = $form.find('input[name="posts_table_s"]').val();
        loadTable({ s, paged: 1 });
    });

    // Ordenar
    $form.on('click', 'th a', function(e) {
        e.preventDefault();
        const url = new URL(this.href, window.location);
        const orderby = url.searchParams.get('orderby') || 'title';
        const order = url.searchParams.get('order') || 'asc';
        const s = $form.find('input[name="posts_table_s"]').val();
        loadTable({ s, orderby, order, paged: 1 });
    });

    // Paginación
    $form.on('click', '.tablenav .page-numbers', function(e) {
        e.preventDefault();
        const url = new URL(this.href, window.location);
        const paged = url.searchParams.get('paged') || 1;
        const s = $form.find('input[name="posts_table_s"]').val();
        const orderby = $form.find('input[name="posts_table_orderby"]').val() || '';
        const order = $form.find('input[name="posts_table_order"]').val() || 'asc';
        loadTable({ s, orderby, order, paged });
    });
});
```

---

## Listar todos los posts con paginación real (SQL directo)

Este enfoque consulta directamente la base de datos con `LIMIT` y `OFFSET`. Es eficiente incluso con decenas de miles de posts.

### Archivo: `sql-posts-table.php`

```php
<?php
function get_total_published_posts_count() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $wpdb->posts 
         WHERE post_type = 'post' AND post_status = 'publish'"
    );
}

function get_paginated_posts_sql($paged = 1, $per_page = 20, $search = '', $orderby = 'post_date', $order = 'DESC') {
    global $wpdb;

    $offset = ($paged - 1) * $per_page;
    $allowed_orderby = ['post_title', 'post_date', 'post_author'];
    $orderby = in_array($orderby, $allowed_orderby) ? $orderby : 'post_date';
    $order = ('asc' === strtolower($order)) ? 'ASC' : 'DESC';

    $sql = "SELECT ID, post_title, post_date, post_author 
            FROM $wpdb->posts 
            WHERE post_type = 'post' AND post_status = 'publish'";

    if ($search) {
        $search = $wpdb->esc_like($search);
        $sql .= " AND post_title LIKE '%{$search}%'";
    }

    $sql .= " ORDER BY {$orderby} {$order} LIMIT %d, %d";
    $results = $wpdb->get_results($wpdb->prepare($sql, $offset, $per_page), ARRAY_A);

    foreach ($results as &$row) {
        $author = get_userdata($row['post_author']);
        $row['author_name'] = $author ? $author->display_name : '—';
        $row['edit_link'] = get_edit_post_link($row['ID']);
    }
    return $results;
}

function render_sql_posts_table() {
    $table_id = 'sql_posts_table';
    $paged = isset($_GET["{$table_id}_paged"]) ? absint($_GET["{$table_id}_paged"]) : 1;
    $search = isset($_GET["{$table_id}_s"]) ? sanitize_text_field($_GET["{$table_id}_s"]) : '';
    $orderby = isset($_GET["{$table_id}_orderby"]) ? sanitize_text_field($_GET["{$table_id}_orderby"]) : 'post_date';
    $order = isset($_GET["{$table_id}_order"]) ? sanitize_text_field($_GET["{$table_id}_order"]) : 'desc';

    $data = get_paginated_posts_sql($paged, 20, $search, $orderby, $order);
    $total_items = get_total_published_posts_count();

    $cell_callbacks = [
        'post_title' => function($item) {
            return '<a href="' . esc_url($item['edit_link']) . '">' . esc_html($item['post_title']) . '</a>';
        },
        'post_date' => function($item) {
            return get_the_date('Y-m-d', $item['ID']);
        }
    ];

    $table = new WPDB_Table_Renderer(
        $data,
        ['post_title', 'author_name', 'post_date'],
        true, true, true,
        ['post_title', 'author_name'],
        false, [], false, false, [], $cell_callbacks,
        $table_id
    );
    $table->total_items = $total_items;
    $table->render_table();
}

// Filtros para AJAX
add_filter("wpdb_table_ajax_data_sql_posts_table", function() {
    $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
    $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'post_date';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'desc';
    return get_paginated_posts_sql($paged, 20, $search, $orderby, $order);
});

add_filter("wpdb_table_ajax_total_items_sql_posts_table", 'get_total_published_posts_count');
```

---

## Listar todos los posts con WP_Query

Usa la API nativa de WordPress. Más legible, pero ligeramente menos eficiente que SQL directo.

### Archivo: `wpquery-posts-table.php`

```php
<?php
function get_paginated_posts_wpquery($paged = 1, $per_page = 20, $search = '', $orderby = 'date', $order = 'desc') {
    $query = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        's'              => $search,
        'orderby'        => $orderby,
        'order'          => $order,
        'no_found_rows'  => false,
    ]);

    $data = [];
    foreach ($query->posts as $post) {
        $author = get_userdata($post->post_author);
        $data[] = [
            'ID'          => $post->ID,
            'post_title'  => $post->post_title,
            'author_name' => $author ? $author->display_name : '—',
            'post_date'   => $post->post_date,
            'edit_link'   => get_edit_post_link($post->ID)
        ];
    }

    // Guardar total globalmente para usar después
    $GLOBALS['wpquery_total_posts'] = $query->found_posts;
    return $data;
}

function render_wpquery_posts_table() {
    $table_id = 'wpquery_posts_table';
    $paged = isset($_GET["{$table_id}_paged"]) ? absint($_GET["{$table_id}_paged"]) : 1;
    $search = isset($_GET["{$table_id}_s"]) ? sanitize_text_field($_GET["{$table_id}_s"]) : '';
    $orderby = isset($_GET["{$table_id}_orderby"]) ? sanitize_text_field($_GET["{$table_id}_orderby"]) : 'date';
    $order = isset($_GET["{$table_id}_order"]) ? sanitize_text_field($_GET["{$table_id}_order"]) : 'desc';

    $data = get_paginated_posts_wpquery($paged, 20, $search, $orderby, $order);
    $total_items = $GLOBALS['wpquery_total_posts'] ?? count($data);

    $cell_callbacks = [
        'post_title' => function($item) {
            return '<a href="' . esc_url($item['edit_link']) . '">' . esc_html($item['post_title']) . '</a>';
        },
        'post_date' => function($item) {
            return get_the_date('Y-m-d', $item['ID']);
        }
    ];

    $table = new WPDB_Table_Renderer(
        $data,
        ['post_title', 'author_name', 'post_date'],
        true, true, true,
        ['post_title', 'author_name'],
        false, [], false, false, [], $cell_callbacks,
        $table_id
    );
    $table->total_items = $total_items;
    $table->render_table();
}

// Filtros para AJAX
add_filter("wpdb_table_ajax_data_wpquery_posts_table", function() {
    $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
    $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'desc';
    $data = get_paginated_posts_wpquery($paged, 20, $search, $orderby, $order);
    return $data;
});

add_filter("wpdb_table_ajax_total_items_wpquery_posts_table", function() {
    return $GLOBALS['wpquery_total_posts'] ?? 0;
});
```

---

## Soporte para múltiples tablas en la misma página

Puedes instanciar varias tablas sin conflictos gracias al parámetro `table_id`.

### Ejemplo: Dos tablas en una página

```php
function render_multiple_tables_page() {
    // Datos para tabla 1
    $posts_data = get_latest_15_posts();
    $posts_table = new WPDB_Table_Renderer(
        $posts_data,
        ['title', 'author', 'date'],
        true, true, true, ['title', 'author'], false, [], false, false, [], [],
        'posts_table'
    );

    // Datos para tabla 2
    $users = get_users(['number' => 20]);
    $users_data = array_map(function($user) {
        return [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'role'  => implode(', ', $user->roles)
        ];
    }, $users);

    $users_table = new WPDB_Table_Renderer(
        $users_data,
        ['name', 'email', 'role'],
        true, true, true, ['name', 'email'], false, [], false, false, [], [],
        'users_table'
    );

    // Registrar filtros para AJAX
    add_filter('wpdb_table_ajax_data_posts_table', 'get_latest_15_posts');
    add_filter('wpdb_table_ajax_data_users_table', function() use ($users_data) {
        return $users_data;
    });

    echo '<div class="wrap">';
    echo '<h1>Tablas múltiples</h1>';
    $posts_table->render_table();
    echo '<hr style="margin: 40px 0;">';
    $users_table->render_table();
    echo '</div>';
}
```

> **Importante**: Cada tabla debe tener un `table_id` único, y debes registrar los filtros AJAX correspondientes.

---

## Callbacks personalizados

### Callbacks de celda

```php
$cell_callbacks = [
    'email' => function($item, $column_name) {
        return '<a href="mailto:' . esc_attr($item['email']) . '">' . esc_html($item['email']) . '</a>';
    },
    'status' => function($item, $column_name) {
        $class = ($item['status'] === 'activo') ? 'status-active' : 'status-inactive';
        return '<span class="' . $class . '">' . esc_html($item['status']) . '</span>';
    }
];
```

### Acciones por fila

```php
$row_actions_callbacks = [
    'name' => function($item) {
        return [
            'view'   => '<a href="#">Ver</a>',
            'edit'   => '<a href="#">Editar</a>',
            'delete' => '<a href="#" class="delete-item" data-id="' . $item['id'] . '">Eliminar</a>'
        ];
    }
];

// Al instanciar la tabla
$table = new WPDB_Table_Renderer(
    $data,
    $columns,
    true, true, true, [], false, [], false,
    true, // enable_row_actions
    $row_actions_callbacks,
    [],
    'mi_tabla'
);
```

---

## Exportación CSV

Habilita el botón de exportación pasando `true` en el parámetro `$enable_export`.

```php
$table = new WPDB_Table_Renderer(
    $data,
    $columns,
    true, true, true, [], false, [], 
    true, // enable_export
    false, [], [], 'exportable_table'
);
```

- El archivo CSV incluye solo los datos visibles (respetando búsqueda y filtros).
- Requiere la capability `manage_options` por defecto (puedes ajustarlo en `handle_csv_export`).

---

## Registro obligatorio del hook AJAX

En tu archivo principal de plugin, **una sola vez**:

```php
add_action('wp_ajax_wpdb_table_load', ['WPDB_Table_Renderer', 'handle_ajax_request']);
```

> No registres el hook dentro del constructor de la clase para evitar duplicados.

---

## Notas de seguridad

- Todos los parámetros de entrada se sanitizan (`sanitize_text_field`, `absint`, etc.).
- El endpoint AJAX verifica:
  - Nonce: `wp_verify_nonce($_POST['nonce'], 'wpdb_table_nonce')`
  - Capability: `current_user_can('manage_options')` (ajustable)
- Usa `esc_html()`, `esc_attr()`, `esc_url()` en todos los outputs.

---

## Conclusión

- **Para pocos posts (<100)**: Usa el ejemplo de "últimos 15 posts".
- **Para muchos posts (>1,000)**: Usa el enfoque con **SQL directo**.
- **Para legibilidad y mantenimiento**: Usa **WP_Query** si el rendimiento no es crítico.
- **Siempre usa un `table_id` único** al instanciar múltiples tablas.
```