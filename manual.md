
---

# Manual de Uso: WPDB Table Renderer

> **Versión**: 2.1.0  
> **Autor**: ByteDogsSyndicate  
> **Licencia**: GPL-3.0+  
> **Paquete**: ClientPulse Pro / Admin/Tables

---

## 📌 Introducción

`WPDB_Table_Renderer` es una clase PHP para WordPress que permite renderizar tablas administrativas avanzadas con soporte para:

- Búsqueda
- Ordenación
- Paginación
- Filtros por columna *(opcional)*
- Exportación a CSV
- Acciones por fila
- Renderizado de celdas personalizado
- Soporte AJAX seguro
- Múltiples instancias en la misma página

Está basada en la clase nativa de WordPress `WP_List_Table`, pero extiende su funcionalidad para casos de uso más complejos y dinámicos.

---

## 🛠️ Instalación

1. Asegúrate de que el archivo que contiene la clase esté incluido en tu plugin o tema.
2. WordPress debe estar cargado (`ABSPATH` definido).
3. La clase se autoincluye si `WP_List_Table` no está disponible.

```php
// Ejemplo de inclusión (si no está ya en tu plugin)
require_once 'ruta/a/WPDB_Table_Renderer.php';
```

---

## 🧱 Constructor

```php
new WPDB_Table_Renderer(
    $data,
    $columns,
    $enable_pagination,
    $enable_sorting,
    $enable_search,
    $searchable_columns,
    $enable_filters,
    $filterable_columns,
    $enable_export,
    $enable_row_actions,
    $row_actions_callbacks,
    $cell_callbacks,
    $table_id
);
```

### Parámetros

| Parámetro | Tipo | Descripción |
|----------|------|-------------|
| `$data` | `array` | Array de filas. Cada fila es un array asociativo con claves coincidentes con las columnas. |
| `$columns` | `array` | Lista de claves de columnas a mostrar. Si vacío, se infiere de la primera fila de `$data`. |
| `$enable_pagination` | `bool` | Habilita/deshabilita paginación. Por defecto: `true`. |
| `$enable_sorting` | `bool` | Habilita/deshabilita ordenación. Por defecto: `true`. |
| `$enable_search` | `bool` | Habilita/deshabilita búsqueda global. Por defecto: `true`. |
| `$searchable_columns` | `array|null` | Columnas en las que buscar. Si `null`, busca en todas. |
| `$enable_filters` | `bool` | Habilita filtros por columna *(no implementado completamente en v2.1.0)*. |
| `$filterable_columns` | `array` | Columnas que pueden filtrarse. |
| `$enable_export` | `bool` | Muestra botón de exportación CSV. |
| `$enable_row_actions` | `bool` | Habilita acciones por fila (como "Editar", "Eliminar", etc.). |
| `$row_actions_callbacks` | `array` | Mapa de callbacks por columna para generar acciones. |
| `$cell_callbacks` | `array` | Mapa de callbacks por columna para renderizar celdas personalizadas. |
| `$table_id` | `string` | Identificador único para evitar conflictos en múltiples tablas. Se sanitiza con `sanitize_key()`. |

> ⚠️ **Importante**: Usa siempre un `$table_id` único si renderizas más de una tabla en la misma página.

---

## 🖥️ Renderizado Básico

### Paso 1: Preparar los datos

```php
$data = [
    ['id' => 1, 'nombre' => 'Producto A', 'precio' => 19.99],
    ['id' => 2, 'nombre' => 'Producto B', 'precio' => 29.99],
];
```

### Paso 2: Instanciar la tabla

```php
$table = new WPDB_Table_Renderer(
    $data,
    ['id', 'nombre', 'precio'],
    true,  // pagination
    true,  // sorting
    true,  // search
    null,  // searchable_columns (todas)
    false, // filters
    [],
    true,  // export CSV
    false, // row actions
    [],
    [],
    'mis_productos' // table_id único
);
```

### Paso 3: Renderizar en la página de administración

```php
add_action('admin_menu', function() {
    add_management_page(
        'Mis Productos',
        'Mis Productos',
        'manage_options',
        'mis-productos',
        'render_mis_productos_page'
    );
});

function render_mis_productos_page() {
    $table = new WPDB_Table_Renderer(/* ... */);
    $table->render_table();
}
```

> ✅ El método `render_table()` imprime toda la interfaz: formulario, búsqueda, botón de exportación y la tabla.

---

## 🎨 Personalización de Celdas

Usa `$cell_callbacks` para transformar el contenido de una celda.

```php
$cell_callbacks = [
    'precio' => function($item, $column_name) {
        return '$' . number_format($item['precio'], 2);
    }
];

$table = new WPDB_Table_Renderer(
    $data,
    ['id', 'nombre', 'precio'],
    // ... otros parámetros ...
    $cell_callbacks: $cell_callbacks,
    $table_id: 'productos_precio'
);
```

---

## 🛠️ Acciones por Fila

Habilita `$enable_row_actions = true` y define callbacks.

```php
$row_actions_callbacks = [
    'id' => function($item) {
        return [
            'editar' => sprintf(
                '<a href="?page=editar-producto&id=%d">Editar</a>',
                $item['id']
            ),
            'eliminar' => sprintf(
                '<a href="?page=eliminar-producto&id=%d" style="color:red;">Eliminar</a>',
                $item['id']
            ),
        ];
    }
];

$table = new WPDB_Table_Renderer(
    $data,
    ['id', 'nombre', 'precio'],
    enable_row_actions: true,
    row_actions_callbacks: $row_actions_callbacks,
    table_id: 'productos_acciones'
);
```

> 🔹 Las acciones solo se muestran en la **columna primaria** (por defecto, la primera).

---

## 📤 Exportación a CSV

Al habilitar `$enable_export = true`, se muestra un botón "Exportar a CSV" que respeta los filtros actuales (búsqueda, ordenación).

- El archivo se descarga con nombre: `export-YYYY-MM-DD-HH-MM-SS.csv`
- Requiere nonce y capacidad `manage_options`.

---

## 🔁 Soporte AJAX (Tablas Dinámicas)

La tabla puede actualizarse mediante AJAX sin recargar la página.

### Configuración del lado del cliente (JavaScript)

```js
jQuery(document).ready(function($) {
    const tableId = 'mis_productos';
    const $form = $('#table-form-' + tableId);
    const $container = $('#table-' + tableId);

    $form.on('submit', function(e) {
        e.preventDefault();

        const formData = $(this).serializeArray();
        const params = {};
        $.each(formData, function(i, field) {
            params[field.name] = field.value;
        });

        $.post(ajaxurl, {
            action: 'wpdb_table_ajax',
            table_id: tableId,
            nonce: wpdbTableNonce, // Debes inyectar este nonce desde PHP
            ...params
        }, function(response) {
            if (response.success) {
                $container.find('.tablenav, tbody').remove();
                $container.find('table.wp-list-table').append(response.data.html);
            }
        });
    });
});
```

### Registro del hook AJAX en PHP

```php
add_action('wp_ajax_wpdb_table_ajax', ['WPDB_Table_Renderer', 'handle_ajax_request']);
```

> ✅ El método estático `handle_ajax_request()` crea una instancia temporal y llama a `ajax_response()`.

### Filtros para proveer datos dinámicos vía AJAX

Define estos filtros para inyectar datos en la solicitud AJAX:

```php
add_filter('wpdb_table_ajax_data_mis_productos', function() {
    return obtener_datos_desde_bd(); // Tu lógica aquí
});

add_filter('wpdb_table_ajax_columns_mis_productos', function() {
    return ['id', 'nombre', 'precio'];
});

// Otros filtros disponibles:
// - wpdb_table_ajax_searchable_columns_{table_id}
// - wpdb_table_ajax_cell_callbacks_{table_id}
// - wpdb_table_ajax_row_actions_callbacks_{table_id}
// - wpdb_table_ajax_enable_pagination_{table_id}
// - etc.
```

---

## 🔒 Seguridad

- Todas las entradas de usuario se sanitizan (`sanitize_text_field`, `absint`, `sanitize_key`).
- El AJAX requiere nonce: `'wpdb_table_nonce'`.
- Solo usuarios con `manage_options` pueden acceder a exportación y AJAX.
- Los parámetros usan prefijos únicos (`{table_id}_s`, `{table_id}_orderby`, etc.) para evitar colisiones.

---

## 🧪 Buenas Prácticas

- Siempre usa un `$table_id` único cuando haya más de una tabla en la página.
- No confíes en `$data` proveniente del cliente; en AJAX, obtén los datos desde tu base de datos usando los filtros.
- Usa `cell_callbacks` para formatear fechas, monedas, enlaces, etc.
- Para grandes volúmenes de datos, considera cargar `$data` solo cuando sea necesario (especialmente en AJAX).

---

## 📜 Changelog Resumido

| Versión | Fecha | Cambios clave |
|--------|--------|---------------|
| **2.1.0** | 2025-04-05 | Soporte multi-tabla, parámetros con prefijo, AJAX diferenciado, seguridad reforzada. |
| **2.0.0** | 2025-03-20 | Reescritura para AJAX parcial, callbacks, exportación CSV funcional. |
| **1.0.0** | 2024-11-10 | Versión inicial basada en `WP_List_Table`. |




¿Preguntas? La clase está diseñada para ser robusta, segura y extensible. Úsala como base para interfaces administrativas profesionales en WordPress.