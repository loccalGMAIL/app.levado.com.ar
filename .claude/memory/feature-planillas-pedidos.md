---
name: feature-planillas-pedidos
description: Pedidos como planillas semanales (lunes–domingo) por negocio, tras retirar producción y los pedidos viejos (v0.23.0). Decisiones validadas y qué viene después.
metadata:
  type: project
---

Rama `v0.23.0-planillas-pedidos` (desde `master`, 2026-11-18). El usuario decidió **quitar todo lo de producción y
la capa vieja de pedidos** (órdenes → pedidos por destino → líneas, recurrentes) y **arrancar de cero desde los pedidos**.
Las ramas `v0.22.0-ciclo-produccion-bandejas` y `v0.22.0-produccion-preproduccion-horneado` quedan sin mergear, como
referencia (preproducción en bandejas, cámara de leudado, horneado por tandas). **Producción se retoma después**, sobre
pedidos ya resueltos; ver [[feature-articulos-produccion]] para lo que existió hasta v0.22.0.

## Cómo trabajan (dicho por el usuario)
Un negocio como Orfano maneja **4 tipos de pedido**: secos, diarios (pan, facturas, criollos), tortas y reparto. Cada
uno es una **planilla tabulada de lunes a domingo** donde se cargan los productos y lo que se va a elaborar cada día.
La planilla semanal a veces se repite y a veces no: tiene que ser **editable**, con las semanas siguientes copiadas de la
anterior, y pueden planificar varias semanas adelante.

## Decisiones validadas (no volver a discutir)
- **Las planillas las configura cada negocio** (`order_sheets`: nombre, orden, activa), no son un enum fijo.
- **Fila = solo artículo**, celda = cantidad del día (`order_sheet_lines.day_1..day_7`, 1 = lunes). Sin cliente por fila.
- **Lista de artículos propia por planilla**, mantenida de semana a semana (no por categoría).
- **Semana nueva = copia de la última existente** de esa planilla; cada semana se edita sola. Una semana **pasada** sin
  datos no se crea al mirarla (se muestra vacía y nace al guardar). `OrderSheetWeekService` es el dueño de esa lógica.
- Producción real y pedidos viejos **sin datos en dev ni en el servidor real**: se borraron las tablas, sin migrar nada.

## Permisos
Ver `/pedidos` lo ve cualquier rol del negocio; editar y configurar planillas es owner/admin (`manage-costs`).

## Bandejas y latas (v0.23.1, rama `v0.23.1-pedidos-bandejas`)
Las panaderías piden mucho por bandejas o latas. Decisiones del usuario (no re-litigar):
- El rinde vive **en la receta** (`recipes.container_type` bandeja|lata + `units_per_container`, en la unidad de
  rendimiento). Primero se hizo en el artículo; el usuario lo pasó a la receta (2026-11-18) porque es un dato de la masa,
  lo comparten el base y sus presentaciones (`Product::unitsPerContainer()` = rinde ÷ `recipe_quantity`) y es lo que va a
  usar producción cuando se retome. **Requiere `recipe` cargada** (preventLazyLoading): las queries de productos de
  pedidos hacen `with('recipe')`.
- UI: en «Editar receta», misma fila que Rendimiento y Unidad, etiqueta **«Unidad prod.»** (pedido del usuario: corta,
  sin texto de ayuda) + «Cuánto entra en una». El alta de receta no lo trae.
- **Cada receta elige** bandeja o lata. **Automático**: con rinde cargado se pide en recipientes en cualquier planilla;
  sin switch por planilla (las tortas simplemente no tienen rinde).
- **Se guardan las bandejas tipeadas** (`order_sheet_lines.container_type` = medida de la fila). Si el rinde cambia, las
  bandejas se respetan y las unidades se recalculan. De a cuartos (`App\Rules\TrayQuantity`).
- `OrderSheetWeekService::presentLine()` muestra cada fila en la medida ACTUAL del artículo: si ganó rinde convierte
  unidades→recipientes (redondeo a cuartos); si lo perdió no puede convertir y avisa (al guardar se toma como unidades).
- El usuario pidió **sacar el orden de las planillas** (ni número ni flechas): quedan por orden de creación; `position`
  sigue en la base y se llena sola, por si se retoma con otra UI.

## Siguiente etapa (anotada por el usuario, no empezada)
**Pedidos de clientes dentro de la sección Clientes** (Reparto): lo que pide cada cliente, separado de las planillas de
producción; después puede alimentar la planilla de Reparto. Clientes y repartidores quedaron intactos para eso.

## Gotchas de la limpieza
- `MobileShortcut::ProductionOrders` pasó a `OrderSheets` (`order_sheets`, ruta `pedidos.index`); tenants con el valor
  viejo guardado caen a los defaults por `tryFrom`.
- El morph map quedó solo con `CatalogItemType`; `StockMovementType::Production` se retiró.
- La migración de borrado es **irreversible** y aborta si hay `stock_movements.reference_type = 'production'`.
- La BD de dev tenía tablas huérfanas de las ramas sin mergear (bakings, preproductions, waste_reasons…); se borraron a
  mano junto con sus filas de `migrations`. Si otra máquina las tiene, `migrate` falla con 3730 hasta limpiarlas igual.
- `ThemeTokensTest` (2 casos) falla en Windows por rutas con `/` y `\` mezclados; no tiene que ver con este cambio.
