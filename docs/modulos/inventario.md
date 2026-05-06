# Inventario

## Estado actual

El modulo de inventario esta implementado para catalogo de productos, bodegas, stock por bodega, movimientos, reservas, transferencias y reportes. Tambien se alineo con el contrato del frontend para resumenes globales, costos unitarios y entrada de mercancia en bulk.

## Rutas principales

| Metodo | Endpoint | Permiso | Uso |
|---|---|---|---|
| GET | `/api/inventory/master` | `inventory.read` | Vista maestra de productos con stock y summary global |
| GET | `/api/inventory/availability` | `inventory.read` | Disponibilidad por producto/bodega |
| GET | `/api/inventory/movements` | `inventory.read` | Historial de movimientos |
| GET | `/api/inventory/movements/summary` | `inventory.read` | Metricas mensuales de movimientos |
| POST | `/api/inventory/stocks/adjust` | `inventory.manage` | Ajuste individual de stock |
| POST | `/api/inventory/stocks/adjust/bulk` | `inventory.manage` | Entrada bulk de mercancia |
| POST | `/api/inventory/movements/transfer` | `inventory.manage` | Transferencia entre bodegas |
| GET/POST/PUT/DELETE | `/api/inventory/products` | `inventory.read/manage` | CRUD de productos |
| GET/POST/PUT/DELETE | `/api/inventory/warehouses` | `inventory.read/manage` | CRUD de bodegas |
| GET | `/api/inventory/warehouses/{uid}/stocks` | `inventory.read` | Stock detallado por bodega |
| GET/POST/PUT/DELETE | `/api/inventory/categories` | `inventory.read/manage` | Categorias |
| POST/DELETE/POST | `/api/inventory/reservations` | `inventory.reserve` | Reservar, liberar y consumir reservas |
| GET | `/api/inventory/report` | `inventory.report` | Reporte operativo |
| GET | `/api/inventory/report/export` | `inventory.report` | Export CSV |

## Contrato relevante

### Master

`GET /api/inventory/master` devuelve:

- `data.filters`
- `data.data[]` con productos y `stocks[]`
- `data.summary` global

Campos clave por producto:

- `uid`, `sku`, `name`, `product`
- `description`
- `category_uid`, `category_name`
- `unit_cost`
- `is_active`
- `reorder_point`
- `stock_physical_total`
- `stock_reserved_total`
- `stock_available_total`
- `stock_state`: `normal`, `low`, `out`
- `stock_indicator`: `green`, `yellow`, `red`
- `stocks[]` con `warehouse`, `physical_stock`, `reserved_stock`, `available_stock`

Campos de summary:

- `products`
- `active_products`
- `out_of_stock_count`
- `total_physical_stock`
- `total_reserved_stock`
- `total_available_stock`

### Bodegas

`GET /api/inventory/warehouses` devuelve `data[]` y `summary` de primer nivel:

- `summary.total_warehouses`
- `summary.active_warehouses`

Cada bodega incluye:

- `summary.sku_count`
- `summary.total_physical`
- `summary.total_reserved`
- `summary.total_available`
- `summary.total_value`

`total_value` se calcula como `physical_stock * product.cost_price`. En API se expone como `unit_cost`.

### Bulk stock

`POST /api/inventory/stocks/adjust/bulk`

```json
{
  "warehouse_uid": "uuid",
  "comment": "Entrada OC-0089",
  "items": [
    { "product_uid": "uuid", "quantity": 50 },
    { "product_uid": "uuid", "quantity": 20 }
  ]
}
```

Internamente ejecuta ajustes `in` por item dentro de una transaccion.

## Archivos de backend

- `routes/api.php`
- `app/Http/Controllers/Api/InventoryController.php`
- `app/Http/Controllers/Api/InventoryProductController.php`
- `app/Http/Controllers/Api/InventoryWarehouseController.php`
- `app/Http/Controllers/Api/InventoryCategoryController.php`
- `app/Services/InventoryService.php`
- `app/Models/InventoryProduct.php`
- `app/Models/InventoryStock.php`
- `app/Models/InventoryMovement.php`
- `app/Models/InventoryReservation.php`
- `app/Models/Warehouse.php`
- `database/migrations/2026_04_08_000008_create_inventory_tables.php`
- `database/migrations/2026_04_08_000010_add_price_books_and_pricing_to_cpq.php`

## Pruebas

- `tests/Feature/InventoryBackendIntegrationTest.php`

Cobertura:

- Summary de master y bodegas.
- `unit_cost` y `quantity` compatibles con frontend.
- Bulk adjust.
- Summary mensual de movimientos.

