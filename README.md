# 📊 WPDB Table Renderer

**Transforma cualquier resultado de `wpdb` en una tabla interactiva, profesional y totalmente funcional dentro de WordPress.**

[![WordPress](https://img.shields.io/badge/WordPress-%E2%9C%93-21759B?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://www.php.net)
[![License](https://img.shields.io/badge/license-GPLv3-blue.svg)](LICENSE)

---

## 🌟 Características

✅ **Renderizado automático** de resultados de `wpdb`  
✅ **Ordenamiento** por cualquier columna (asc/desc)  
✅ **Búsqueda global** o en columnas específicas  
✅ **Filtros por columna** con dropdowns dinámicos  
✅ **Paginación** configurable  
✅ **Exportación a CSV** con un solo clic (respeta filtros y orden)  
✅ **Acciones por fila**: Ver, Editar, Eliminar, etc.  
✅ **Celdas personalizables** con callbacks  
✅ **Soporte AJAX** nativo  
✅ **Seguro**: sanitización y escaping integrados  
✅ **Ligero**: solo 300 líneas de código limpio

Ideal para plugins, dashboards personalizados, reporting, y más.

---

## 📦 Instalación

1. Descarga o clona este repositorio.
2. Copia `class-wpdb-table-renderer.php` a tu plugin o tema:
   ```
   tu-plugin/inc/class-wpdb-table-renderer.php
   ```
3. Inclúyelo en tu código:
   ```php
   require_once __DIR__ . '/inc/class-wpdb-table-renderer.php';
   ```

¡Listo! No requiere dependencias externas.

---

## 📚 Manual de Uso (Markdown Plano)

# WPDB Table Renderer – Manual de Uso

Librería para WordPress que convierte resultados de `wpdb` en tablas interactivas con `WP_List_Table`.

## 📥 Instalación

1. Guarda el código anterior en un archivo:  
   `your-plugin/includes/class-wpdb-table-renderer.php`

2. Inclúyelo en tu plugin o tema:
   ```php
   require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpdb-table-renderer.php';
   ```

---

## 🧪 Uso Básico

```php
global $wpdb;
$data = $wpdb->get_results( "SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'post' LIMIT 100", ARRAY_A );

$table = new WPDB_Table_Renderer( $data );
$table->render_table();
```

> **Resultado**: Tabla con paginación, ordenamiento y búsqueda automática.

---

## 🔧 Parámetros del Constructor

```php
new WPDB_Table_Renderer(
    $data,                      // array - resultado de wpdb (ARRAY_A)
    $columns = [],              // array - nombres de columnas (opcional)
    $enable_pagination = true,  // bool
    $enable_sorting = true,     // bool
    $enable_search = true,      // bool
    $searchable_columns = [],   // array - columnas en las que buscar
    $enable_filters = false,    // bool
    $filterable_columns = [],   // array - columnas con dropdown
    $enable_export = false,     // bool
    $enable_row_actions = false,// bool
    $row_actions_callbacks = [],// array - callbacks para acciones por fila
    $cell_callbacks = []        // array - callbacks por celda
);
```

---

## 🎯 Ejemplo 1: Tabla de Usuarios con Acciones

```php
global $wpdb;
$users = $wpdb->get_results( "SELECT ID, user_login, user_email, user_status FROM {$wpdb->users} LIMIT 50", ARRAY_A );

// Callback para acciones por fila
$row_actions = function( $user ) {
    return array(
        'view'   => '<a href="' . get_edit_user_link( $user['ID'] ) . '">Ver</a>',
        'edit'   => '<a href="' . admin_url( 'user-edit.php?user_id=' . $user['ID'] ) . '">Editar</a>',
        'delete' => '<a href="#" onclick="return confirm(\'¿Eliminar?\')">Eliminar</a>'
    );
};

// Callback para formatear el email como enlace
$email_callback = function( $user, $col ) {
    return '<a href="mailto:' . esc_attr( $user['user_email'] ) . '">' . esc_html( $user['user_email'] ) . '</a>';
};

$table = new WPDB_Table_Renderer(
    $users,
    array(),                    // columnas inferidas
    true,                       // paginación
    true,                       // ordenamiento
    true,                       // búsqueda
    array( 'user_login', 'user_email' ), // buscable
    true,                       // filtros
    array( 'user_status' ),     // dropdown en user_status
    true,                       // exportar CSV
    true,                       // acciones por fila
    array( 'user_login' => $row_actions ), // acciones en columna "user_login"
    array( 'user_email' => $email_callback ) // celda personalizada
);

$table->render_table();
```

---

## 📊 Ejemplo 2: Tabla de Posts con Estado como Etiqueta

```php
global $wpdb;
$posts = $wpdb->get_results( "
    SELECT ID, post_title, post_status, post_date 
    FROM {$wpdb->posts} 
    WHERE post_type = 'post' AND post_status IN ('publish', 'draft')
    LIMIT 200
", ARRAY_A );

$status_badge = function( $post, $col ) {
    $status = $post['post_status'];
    $label = ( $status === 'publish' ) ? 'Publicado' : 'Borrador';
    $color = ( $status === 'publish' ) ? 'green' : 'orange';
    return "<span style='background:{$color}; color:white; padding:2px 6px; border-radius:3px;'>{$label}</span>";
};

$table = new WPDB_Table_Renderer(
    $posts,
    array(),
    true,  // paginación
    true,  // ordenamiento
    true,  // búsqueda
    array( 'post_title' ), // solo buscar en título
    true,  // filtros
    array( 'post_status' ), // dropdown por estado
    true,  // exportar
    false, // sin acciones por fila
    array(),
    array( 'post_status' => $status_badge ) // celda personalizada
);

$table->render_table();
```

---

## 🔍 Ejemplo 3: Solo Búsqueda y Paginación (Mínimo)

```php
global $wpdb;
$data = $wpdb->get_results( "SELECT name, email, phone FROM custom_table", ARRAY_A );

$table = new WPDB_Table_Renderer( $data, array(), true, true, true );
$table->render_table();
```

---

## 📤 Exportación a CSV

- El botón **"Exportar a CSV"** aparece si `$enable_export = true`.
- Exporta **solo los datos visibles** (con filtros, búsqueda y orden aplicados).
- Requiere capacidad `manage_options` (puedes modificar esto en `handle_csv_export`).

---

## 🧩 Callbacks

### Celdas personalizadas
```php
'cell_callbacks' => array(
    'column_name' => function( $row, $col ) {
        return '<strong>' . esc_html( $row[ $col ] ) . '</strong>';
    }
)
```

### Acciones por fila
```php
'row_actions_callbacks' => array(
    'primary_column' => function( $row ) {
        return array(
            'edit' => '<a href="...">Editar</a>',
            'del'  => '<a href="..." class="delete">Eliminar</a>'
        );
    }
)
```

> La columna primaria es la **primera columna** por defecto. Puedes cambiarla sobrescribiendo `get_primary_column_name()` si lo necesitas.

---

## ⚠️ Notas Importantes

- Siempre usa `ARRAY_A` en tus consultas de `wpdb`.
- La librería está diseñada para el **admin de WordPress**. Para frontend, incluye estilos manualmente.
- Los callbacks reciben el `$row` completo como array asociativo.
- La exportación a CSV respeta todos los filtros actuales.

---

## 🛠️ Personalización Avanzada

¿Necesitas más control? Extiende la clase:

```php
class Mi_Tabla_Especial extends WPDB_Table_Renderer {
    protected function get_primary_column_name() {
        return 'mi_columna_clave';
    }
}
```

---

## 📄 Licencia

Este proyecto está licenciado bajo **GPLv3** – ver [LICENSE](LICENSE) para más detalles.

---

## 🙌 Contribuciones

¡Las contribuciones son bienvenidas! Por favor abre un *issue* o envía un *pull request*.
