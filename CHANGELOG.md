# Changelog — Levado

Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/).
Versiones siguiendo [Semantic Versioning](https://semver.org/lang/es/).

---

## [0.12.6] — 2026-07-30

### Los montos grandes se salían de las cards del dashboard

#### Corregido

- **Un importe de 8 o 9 dígitos se salía de la card del dashboard y arrastraba scroll horizontal a toda la página.** `$ 123.456.789,00` mide 230px a la tipografía del KPI y la card en un celular de 375px deja 113px de interior: el número se pasaba **117px** del borde. Ahora las dos cards de importe —Gastos fijos y Overhead por hora— toman el ancho completo en mobile y la cifra entra entera, sin recortes ni scroll lateral.
- **No era sólo mobile.** El mismo monto se salía en **todos los anchos por debajo de ~1440px**, porque el espacio disponible no crece con la pantalla: a 1024px las cuatro columnas dejan **136px** de interior por card, *menos* que un celular de 390px. Se salía 94px a 1024px, 30px a 1280px y 86px a 640px. Verificado ahora en 10 anchos entre 320px y 1920px, sin desborde en ninguno.
- **La misma cifra pasaba en el kardex de existencias** (Existencias → historial): la Valuación se salía 55px a 320px y 28px a 375px. Ahora toma las dos columnas mientras el grid sea de dos, y el 4-up arranca en `lg` en vez de `md` porque a 768px cuatro columnas dejan 87px por card, donde no entra ninguna valuación de 8 dígitos.
- **El total de la factura en Compras → Ver detalle** estaba anclado a la derecha con `shrink-0`: no cedía ancho y empujaba el monto fuera de la card. En mobile baja a su propia fila con el ancho completo.
- **El recuadro de Overhead / hora en Mi negocio** se dimensionaba por su contenido, así que un overhead largo se pasaba del borde de la card. Pasa a ancho completo en mobile.
- **Las cards de mobile de los listados** (Insumos, Envases, Gastos fijos, Gastos variables, Mano de obra, Compras, Listas de precios, Existencias) no dejaban que la columna del nombre cediera ancho: un nombre largo o un importe largo empujaba la badge de estado fuera de la card. Ahora la columna cede y el texto envuelve.

#### Cambio visible

- **Los dos KPI de importe del dashboard ya no muestran centavos**: `$ 123.456.789` en vez de `$ 123.456.789,00`. En un total mensual los centavos no aportan y son 3 caracteres que a ese tamaño no sobran. **El valor exacto sigue disponible al pasar el mouse** por la cifra, y no se toca en ningún otro lado: tablas, detalle de receta, Resumen operativo y Mi negocio siguen con centavos.
- **En mobile las cards quedan en tres filas** en vez de dos: Recetas activas y Utilidad promedio comparten la primera, y las dos de importe van una debajo de la otra. Es una fila más de scroll; a cambio el monto entra completo, que a media pantalla no pasa a ningún tamaño legible.
- La píldora `↑ activas` no se muestra en mobile —no dice nada que no diga el rótulo de abajo y no entra al lado del ícono— y `↑ obj. 38%` se acorta a `↑ 38%`.

#### Técnico

- El tamaño de la cifra se mide **contra el ancho de la card y no contra el del viewport**, con un container query: `.kpi-card` declara `container-type: inline-size` y `.kpi-figure` resuelve `clamp(var(--kpi-figure-min), 10cqi, var(--kpi-figure-max))`. Es la única forma limpia de resolverlo, porque el ancho disponible **no es monótono** respecto del viewport: una escala por breakpoints tendría que ir 2xl → lg → 2xl → base → 2xl entre mobile y desktop.
- `10cqi` no es arbitrario: cada glifo de JetBrains Mono avanza 0,6em, así que 16 glifos —`$ 123.456.789,00` completo— ocupan 9,6em y entran con el ancho de la card dividido 10.
- Los límites se ajustan por card con `[--kpi-figure-max:…]` / `[--kpi-figure-min:…]`, y las reglas van en `@layer components` **justo por eso**: en la capa de utilities, Tailwind emite el override de la custom property *antes* que `.kpi-figure` y, con la misma especificidad, ganaba el valor por defecto.
- `.kpi-card` sólo se aplica a items de grid. `container-type: inline-size` implica `contain: inline-size`, que **colapsa a 0 cualquier elemento cuyo ancho venga de su contenido** — un flex item con `shrink-0`, por ejemplo. Ahí el arreglo va por breakpoints (`text-lg sm:text-xl`) en vez de container query.
- `overflow-wrap: anywhere` en la cifra como última red: si algún día aparece un monto más largo que lo previsto, parte en dos líneas en vez de salirse. Las filas de ícono + píldora llevan `flex-wrap` por lo mismo, que además hace el arreglo independiente de las métricas exactas de Inter.

#### Verificado en la aplicación

Se midió con Playwright sobre el marcado y el CSS compilado reales, en **320, 375, 390, 414, 640, 768, 1024, 1280, 1440 y 1920px**, replicando la geometría de producción (sidebar `w-52` a partir de `sm`, `px-6 lg:px-8`). Para cada cifra se comparó el ancho que necesita el texto contra el borde interior de su card.

- **Antes:** desborde en los 10 anchos y scroll horizontal del documento de 99px a 320px, 72px a 375px y 52px a 414px.
- **Después:** **0px de desborde y 0px de scroll horizontal en los 10 anchos**, con la cifra entre 16px y 24px según lo que permita la card. Un monto de 10 dígitos (mil millones por hora) envuelve en dos líneas a 1024px en vez de salirse.
- 4 tests nuevos que fijan el marcado del que depende el arreglo —el utilitario en la cifra, el `col-span` de las cards de importe y el `title` con el valor exacto—, verificados contra un revert de la corrección: fallan. **523 tests, todos verdes.**

#### Al deployar

No hay migraciones ni corrección de datos.

1. `php artisan optimize:clear`
2. `npm run build` — el arreglo vive en el CSS compilado.

---

## [0.12.5] — 2026-07-28

### Los descartables no tomaban la subdivisión al vincular una compra

#### Corregido

- **Un descartable con subdivisión asociado a mano desde la pantalla de vincular guardaba el precio del bulto entero como costo de la sub-unidad.** `Sobre Kraft N°4A` (100 sobres por caja) quedó en **$2.000 por sobre** cuando el sobre vale **$20**: cien veces más caro. La pantalla trataba a los descartables como un caso especial que cortaba antes de dividir por las subdivisiones, mientras que los insumos sí lo hacían. Ahora recorren exactamente el mismo camino que un insumo. El camino automático —aplicar una sugerencia sin tocar el renglón— siempre dividió bien, así que sólo se ensuciaron los renglones asociados uno por uno.
- **El stock del descartable también entraba mal**: ingresaban bultos donde tenían que ingresar sub-unidades. Se corrige por el mismo lado, sin tocar el cálculo de existencias.
- **El descartable ahora se ve como un insumo en la pantalla de vincular**: el select muestra «(100 sobres / envase)», el costo aparece por sobre y no por caja, y al lado se lee «1 envase = 100 sobres». El renglón ya aplicado también muestra la subdivisión, que antes sólo salía para insumos.
- **La columna «Por presentación» del listado mostraba un precio viejo.** Al imputar un costo escrito a mano, el precio por envase no se recalculaba y quedaba el de la compra anterior. Afectaba a insumos y descartables por igual.
- **El lápiz de la tabla abría el modal de edición con el campo de costo vacío.** El botón del nombre y la tarjeta del celular mandaban el precio por envase, el lápiz no; el modal se quedaba sin valor y había que reescribirlo. Pasaba en Insumos y en Envases.

#### Técnico

- El catálogo que `match.js` consulta pasa a indexarse por **`"tipo:id"`** en vez de por id pelado, e incluye los descartables con una unidad `'u'` sintética. No es cosmético: **los 68 ids de `packagings` existen también en `ingredients`**, así que un catálogo con ambos tipos indexado por id devolvía el ítem equivocado. La clave es la misma cadena que ya manda el `<select>`, así que el `split(':')` del cliente desaparece.
- Con eso, la rama `packaging` de `recalc()` deja de ser un caso especial: cae en el flujo común y hereda `subdivisionFactor`, `displayUnit` y el hint de subdivisión. Sobrevive un único guard —los descartables sólo se compran por unidad—, que sigue mostrando el error de unidad incompatible.
- `PurchaseLineRecorder::applyWithCost()` deriva el precio del envase como `costo × subdivisiones` en vez de reescribir el valor que ya tenía el modelo. Queda coherente **aunque el usuario sobrescriba el costo a mano**, que es el escape para el proveedor que factura sub-unidades sueltas en vez del bulto. El guard replica el de `apply()`: un insumo con subdivisiones pero medido en kg no lleva precio por envase.
- `syncStockFromExplicitCost()` **no se tocó**. Deriva las unidades por bulto como `precio_del_renglón / costo_unitario`, y con el costo ya corregido da bien en los dos casos: bulto ($2.000 / $20 = 100 sobres) y suelto ($80 / $80 = 1).
- `ingredients:fix-subdivision-costs` detectaba sólo los ítems sin precio por envase y **ya no matcheaba ninguno de los rotos**, que sí lo tienen pero incoherente. Ahora clasifica en dos correcciones **opuestas**, evaluadas en ese orden porque el caso A también dispararía el B: **A ·** el mismo valor quedó en las dos columnas → se divide por las subdivisiones; **B ·** el costo por sub-unidad es correcto y el precio por envase quedó viejo → se recalcula el envase. La tabla de preview dice cuál se aplica a cada ítem y sigue pidiendo confirmación antes de escribir.
- El umbral del caso B es **1% de desvío relativo**. Separa limpio los dos grupos que hay en los datos: redondeos del usuario al cargar el costo a mano (≤ 0,09%; p. ej. 133,30 contra 133,3333) contra desincronizaciones reales (≥ 5,5%). Los redondeos no se tocan.
- 15 tests nuevos: 6 sobre la ruta HTTP de vinculación —que no tenía **ninguna** cobertura, porque los tests de stock invocaban el servicio en directo—, 8 sobre la clasificación del comando de reparación y 1 de CRUD. Los 4 tests de subdivisión de envases verificaban las etiquetas pero **ningún costo**; ahora asertan las dos columnas. **519 tests, todos verdes.**

#### Verificado en la aplicación

La división ocurre en el navegador y no hay runner de JS en el proyecto, así que se verificó contra la app corriendo, sobre el tenant Confitería Orfano.

- Renglón `5 CAJAS SERVILLETAS` a **$60.500,00 /u** asociado a `Servilletas 33×30`: el costo calculado pasa a **$60,50 / Servilletas** con el hint «1 envase = 1000 Servilletas», y el campo que se postea viaja como `unit_cost = 60.5000`. Antes iba **60500**, el precio de la caja entera.
- Se ejercitó además el bundle compilado con un catálogo que **colisiona el id 6 entre un insumo y un descartable**: resuelve cada uno por separado. Sin la clave compuesta, ese caso devolvía el ítem del tipo equivocado.
- Sin regresiones en los caminos vecinos: descartable sin subdivisión no divide, descartable comprado en kg sigue mostrando el error de unidad incompatible, e insumo con subdivisión sigue dando lo mismo que antes.

#### Corrección de datos aplicada

El comando encontró y corrigió **6 ítems**:

| Ítem | Sub. | Motivo | Costo / sub-unidad | Precio por envase |
|---|---|---|---|---|
| Sobre Kraft N°4A | 100 | A · nunca dividido | **$ 2.000,00 → $ 20,00** | $ 2.000,00 |
| Vasos de Polipapel 8oz | 50 | B · envase viejo | $ 55,00 | $ 4.000,00 → $ 2.750,00 |
| Pan de Miga | 24 | B · envase viejo | $ 395,83 | $ 9.000,00 → $ 9.499,92 |
| Ketchup Individual | 192 | B · envase viejo | $ 74,04 | $ 87,80 → $ 14.215,68 |
| Barrita Chocolate de Taza | 24 | B · envase viejo | $ 672,80 | $ 1.153,50 → $ 16.147,20 |
| Té Negro | 15 | B · envase viejo | $ 162,00 | $ 3.442,17 → $ 2.430,00 |

**Ninguna receta cambió de costo.** El único ítem cuyo costo por sub-unidad se movió es `Sobre Kraft N°4A`, que no está en ninguna receta; los cinco casos B sólo tocan el precio por envase, que es informativo y no entra en el cálculo. Por lo mismo se generó **un solo** registro de historial de precios: el historial es append-only y no se anota lo que no cambió. Una segunda corrida del comando ya no encuentra nada.

#### Al deployar

No hay migraciones. **La corrección de datos no es opcional**: los ítems ya cargados no se arreglan solos, y el comando es el único que los toca.

1. `php artisan optimize:clear`
2. `npm run build` — el arreglo del cálculo vive en `match.js` y viaja en el bundle.
3. `php artisan ingredients:fix-subdivision-costs` — revisar la tabla de preview y confirmar. Es idempotente: si ya se corrió, no encuentra nada.

---

## [0.12.4] — 2026-07-27

### Compras personales en la factura del proveedor + descripción en gastos variables

Las dos cosas salieron del mismo feedback de Confitería Orfano.

#### Agregado

- **Un renglón de compra ahora puede marcarse como «consumo personal».** El dueño mete compras personales (una gaseosa, el asado del domingo) en la misma factura del proveedor de insumos. Hasta ahora esos renglones sólo podían quedar en «— sin asociar —», que **es** el estado pendiente: la factura nunca llegaba a verde, el ícono del índice quedaba ámbar para siempre y el indicador de completitud dejaba de significar nada. Ahora hay un tercer estado explícito — resuelto, pero no es del negocio — que no imputa costo al catálogo ni genera movimiento de stock. En la pantalla de vincular aparece como una opción aparte del catálogo («Consumo personal — no es del negocio»), con una nota opcional para acordarse de qué fue, y se puede volver atrás en cualquier momento eligiendo «— sin asociar —» o asociándolo a un insumo.
- **El total de la factura no cambia**: sigue siendo la suma de todos los renglones, porque tiene que cerrar contra el papel. Debajo del total aparece, sólo si corresponde, una línea informativa con cuánto de esa factura fue consumo personal.
- **Los gastos variables tienen descripción.** El campo `name` cumplía doble función: etiqueta corta del listado y explicación de qué fue el gasto, así que o quedaba un nombre ilegible de tres renglones o se perdía el detalle. Ahora son dos campos: un nombre corto y una descripción opcional. La búsqueda del índice cubre los dos, y en el listado la descripción se muestra truncada como segunda línea, sin ensanchar la tabla.
- **El lector de comprobantes con IA devuelve los dos campos**: un nombre corto («Compra de ferretería») y el detalle de los ítems («Tornillos, cinta aisladora y silicona»). Como siempre, la IA propone y el usuario confirma: los dos campos quedan editables en el modal de alta.

#### Cambiado

- **El indicador de completitud de una compra pasó de contar renglones *aplicados* a contar renglones *resueltos*** (aplicados **+** consumo personal). Es el cambio que da sentido a todo lo demás: sin él, una factura con un renglón personal quedaría pendiente para siempre. Afecta al contador de la pantalla de vincular (ahora «N/M renglones resueltos», verde cuando no queda nada por resolver), al ícono ámbar/verde del índice de compras y al banner del detalle de la compra.
- La alerta «Compra sin imputar» del centro de alertas dejó de contar los renglones de consumo personal: una factura totalmente resuelta ya no genera aviso.

#### Técnico

- **`purchase_lines.excluded_at` + `exclusion_note`** (timestamp y string(255), ambos nullable). Migración aditiva, sin backfill: las filas existentes quedan idénticas a hoy. `PurchaseLine` suma `isExcluded()`, `isResolved()` e `isPending()`. **Los tres estados son mutuamente excluyentes**: un renglón excluido nunca conserva `purchaseable_id` ni `cost_applied_at`, y `matchLine()` escribe los tres campos juntos en un único `update()` para que la invariante no dependa del orden de las ramas.
- El select manda un **valor centinela** `excluded`, interceptado antes del `explode(':')`. No puede colisionar con un match real, que siempre viaja como `tipo:id`.
- **Marcar como personal un renglón ya aplicado revierte su entrada de stock con un contramovimiento**, igual que hace «— sin asociar —»; `stock_movements` sigue siendo append-only y todo pasa por `StockService`. **El costo del insumo no se revierte**, también igual que hoy al desasociar: el historial de precios es append-only y revertirlo sería reescribir el pasado.
- `applied_count` del índice se renombró a **`resolved_count`**, con el `orWhere` del `withCount` envuelto en closure. Mismo motivo por el que la búsqueda de gastos variables ahora también envuelve su `where`/`orWhere`: un `orWhere` suelto se agrupa con los filtros de categoría, proveedor y fechas y hace que la búsqueda los ignore. Hay un test que ancla exactamente ese caso.
- El badge de los renglones personales es **neutro** (`bg-miga`/`text-masa-madre`), deliberadamente **no ámbar**: el ámbar significa «pendiente» en todo el módulo y esa semántica había que preservarla.
- `variable_expenses.description` (text nullable, aditiva). El prompt de `ExpenseReceiptExtractor` pasa a pedir dos campos y `normalize()` los normaliza igual que el resto; se mantuvo la regla de no usar el nombre del proveedor como etiqueta, porque `supplier_name` ya existe y se matchea contra el catálogo de proveedores.
- 19 tests nuevos (11 de exclusión de renglones, 2 de stock, 6 de gastos variables), sin modificar ninguno de los existentes. **504 tests, todos verdes.**

#### Al deployar

Las dos migraciones son columnas nullable aditivas: sin migración de datos, sin backfill, sin riesgo de downtime.

1. `php artisan migrate`
2. `php artisan optimize:clear`

**Ojo:** si al momento del deploy **v0.12.0 todavía no está en producción**, ese release requiere además `php artisan recipes:refresh-costs`. No es parte de este cambio, pero si van juntos no hay que saltearlo.

---

## [0.12.3] — 2026-07-24

### Compras: número de factura inventado por la IA

#### Corregido

- **El lector de facturas inventaba el punto de venta cuando no lo leía con nitidez.** Detectado en producción con una factura real de Del Campo Distribuidora (`FACTURA 0006-00008298`): se escaneó dos veces y las dos veces el modelo devolvió `0001` en vez de `0006` — la primera vez además con el total mal leído (`$150.724,70` en vez de `$159.724,70`), la segunda con un dígito de más en el número de comprobante. Como el número de factura salió distinto en cada intento, el índice único `(tenant_id, supplier_id, invoice_number)` no detectó el duplicado y quedaron dos compras cargadas para el mismo papel.

#### Técnico

- **El prompt ahora pide transcribir el Punto de Venta dígito por dígito y devolver `null` en vez de adivinar** cuando no se lee con certeza, con el caso real como ejemplo — mismo patrón que ya se usó para el bug de fechas en v0.10.1. Es una red de seguridad de prompt, no una garantía: no hay forma de confirmarla contra el modelo real sin volver a mandarle esa foto, y el mismo tipo de error ("inventar un valor típico" en vez de leer) puede repetirse con otro campo.
- El total mal leído en el primer intento y la limpieza de las dos compras duplicadas quedan fuera de este fix — es dato a corregir a mano en producción.

---

## [0.12.2] — 2026-07-19

### Centro de Alertas + limpieza del dashboard

#### Agregado

- **Centro de alertas del negocio**: feed persistido de avisos accionables con 4 tipos — 📦 **stock bajo o negativo** (insumo/descartable bajo su mínimo), 📈 **salto de costo en compra** (una compra sube el costo de un ítem por encima de un umbral configurable), 🕒 **costo desactualizado** (un insumo activo no actualiza su costo hace más de N días) y 🧾 **compra sin imputar** (renglones pendientes de asociar o imputar). Aparecen en el panel de inicio (franja "Alertas") y en una página propia con filtro leídas/todas y acciones de marcar leída, descartar y marcar todas.
- **Configuración en Administración → Alertas** (solo owner): prender/apagar cada tipo y ajustar el umbral de aumento (%) y los días para costo desactualizado. Apagar un tipo resuelve sus alertas vivas.
- **Dashboard**: se quitaron los botones "Ingrediente" y "Lista de precios" de las acciones rápidas (quedan "Nueva receta" y "Compra"); "RESUMEN OPERATIVO" pasó a llamarse "Alertas" y la card inferior de insights se renombró "Resumen operativo".
- **Tipografía de las KPI cards unificada**: las cuatro cifras grandes (recetas activas, gastos fijos, overhead/hora, utilidad promedio) usan el mismo tamaño (`text-2xl`) y la misma fuente (JetBrains Mono), en lugar de mezclar tamaños y sans/mono.
- **La tabla de recetas conserva la posición al buscar/ordenar/filtrar/paginar**: el formulario, los encabezados ordenables y la paginación anclan a `#tabla-recetas`, así el navegador recarga directo en la sección de la tabla en vez de arriba de todo (sin JS extra).

#### Técnico

- **Tabla `notifications`** (tenant-scoped, único escritor `NotificationService`) con `dedupe_key` para idempotencia. **Dos naturalezas de alerta:** las de evento (salto de costo) se capturan en el instante de la compra vía un hook en `PurchaseLineRecorder` (compara costo viejo vs nuevo; el primer costo no dispara); las de estado (stock, costo desactualizado, compras sin imputar) se **reconcilian al leer** el dashboard/feed (se crean y se auto-resuelven), sin depender de un worker de colas o cron que producción no tiene garantizado.
- Config guardada en `tenant_settings` (`alerts.*`), reutilizando `getSetting`/`setSetting`. El feed es accesible a todos los roles con tenant; la config queda acotada a owner (`edit-settings`).
- **`NotificationAlertsTest` (14 tests)**: umbrales, idempotencia y resolución de las de estado, toggles, permisos (viewer sin config pero con feed) y aislamiento entre tenants. **485 tests, todos verdes.**

#### Al deployar

1. `php artisan migrate` (tabla `notifications`)
2. `php artisan optimize:clear` (vistas Blade)

## [0.12.1] — 2026-07-19

### Rediseño gráfico del dashboard

#### Agregado

- **Nuevo dashboard visual** con KPIs (recetas activas, gastos fijos, overhead/hora, utilidad promedio), un gauge de rentabilidad promedio, un bar chart de las top recetas por margen y una dona de distribución de costos (ingredientes / mano de obra / gastos fijos / descartables), sobre **ApexCharts**. Tipografía Inter, escala de color `brown`, filtro rápido de margen alta/baja en la tabla.

#### Técnico

- **ApexCharts self-hosted** vía npm/Vite (`resources/js/dashboard-charts.js`) en lugar del CDN, coherente con la PWA offline; los datos viajan por un `<script type="application/json">` y no queda JS inline.
- **Fix del filtro de margen**: `marginPctSql` hacía división entera en SQLite (margen 0 para todas las recetas → el filtro no filtraba); se fuerza aritmética real con `* 100.0` antes de dividir (idéntico en MySQL). Mejora también el orden por margen %.
- **Fix de compilación Blade**: `@json([...])` con array literal multilínea se mal-parseaba (500 en toda la pantalla); se arma el array en `@php` y se pasa la variable a `@json`.
- 4 tests nuevos (filtro de margen alta/baja, datos de los gráficos, estado vacío). **471 tests, todos verdes.**

## [0.12.0] — 2026-07-18

### Mediano plazo de la auditoría — 1er lote: rendimiento y dominio

#### Rendimiento

- **El dashboard de rentabilidad dejó de recalcular todas las recetas en cada carga.** Antes cargaba todas las recetas activas con sus líneas, calculaba costos en PHP, ordenaba en memoria y paginaba a mano; ahora lee los caches `unit_cost` y la nueva columna `labor_hours` (mantenida por `RecipeCostPropagator`), y ordena y pagina en SQL — incluidos margen y margen % con los sin-precio al final. Con cientos de recetas el dashboard pasa de O(recetas × líneas) a una única query paginada.
- La actualización inline de precio y la matriz de listas de precios también leen los caches en vez de recalcular.
- **Nuevo comando `recipes:refresh-costs`**: rellena/repara los caches de todas las recetas (pasadas sucesivas hasta converger sub-recetas anidadas). **Correrlo una vez tras el deploy.**

#### Dominio y calidad

- **Fix de compras (deuda DD2, regla del proveedor inactivo):** el select de asociación de `purchases/match` ahora lista todos los ingredientes y descartables marcando `(inactivo)` — antes, un renglón asociado a un ítem dado de baja caía en «— sin asociar —» y guardarlo revertía stock y costo en silencio. En recetas la trampa no existe (sus catálogos solo alimentan modales de alta). Se eliminaron además dos queries muertas en `purchases/show`.
- **Nuevo enum `CatalogItemType`** (hallazgo D2): dueño único de los discriminadores `'ingredient'`/`'packaging'` que viajan en `purchase_lines.purchaseable_type`, `stock_*.stockable_type` y las URLs de `/stock` — estaban repetidos como strings en 10 archivos y un typo creaba filas huérfanas silenciosas. Valores persistidos sin cambios.
- **Factories faltantes** (hallazgo D3): `Purchase` (proveedor hereda el tenant de la compra), `PurchaseLine` (pendiente por defecto, estados `matchedTo*`), `Invitation` (`expired()`/`accepted()`) y `MailTemplate`. `StockMovement` queda sin factory a propósito: el ledger solo se escribe vía `StockService`.
- `RecipeCostCalculator` y `UnitConverter` pasan a inyección por constructor (hallazgo A3); eliminado el `Gate::define('super-admin')` sin consumidores.
- Tests: helper `propagateRecipeCosts()` en Pest para seeds manuales; 8 tests nuevos (cache del dashboard, orden SQL por margen, backfill, match con ítem inactivo, humo de factories). **465 tests, todos verdes.**

#### Mantenibilidad (2º lote)

- **`RecipeController` partido** (hallazgo A2): pasa de 565 a 236 líneas. El CRUD de los 4 tipos de líneas (12 acciones) se mudó a `RecipeLineController` y el armado de los datos de `/recipes/{id}` (~120 líneas de transformación para Alpine) a `RecipeShowViewModel`. Mismas rutas, nombres y contratos — la suite pasó sin tocar un test.
- **Nuevos componentes de tablas** (hallazgo F2): `x-sortable-th` (encabezado ordenable: arma la URL preservando filtros, alterna asc/desc, muestra la flecha) y `x-responsive-table` (el patrón cards-mobile/tabla-desktop con el toggle «Ver tabla completa ↓ / ← Volver a cards» y slot de paginación). **Migradas las 9 vistas de índice cuyo markup coincidía exactamente** (dashboard, recetas, ingredientes, descartables, gastos fijos, gastos variables, mano de obra, listas de precios y stock): se eliminaron 9 copias del bloque `$sortUrl`/`$sortIcon` (25 encabezados) y 9 copias del wrapper cards/tabla. `purchases/index` y `price-lists/matrix` tienen variantes propias y quedan para una migración manual posterior.
- 2 tests ancla de los componentes (dirección alternada preservando filtros; cards + tabla + toggle presentes). **467 tests, todos verdes.**

#### Al deployar

1. `php artisan migrate` (columna `recipes.labor_hours`)
2. `php artisan recipes:refresh-costs` (backfill de los caches)

## [0.11.2] — 2026-07-17

### Páginas de error con branding

#### Agregado

- **Páginas de error propias** (404, 403, 419, 500 y 503) con el branding de Levado, en castellano, reemplazando las páginas por defecto de Laravel. El 404 aclara que la página "no existe o el registro no pertenece a tu negocio" (es la respuesta que ahora dan los accesos cross-tenant tras v0.11.1); el 403 muestra el mensaje del `abort()` cuando es propio (ej. "Tenant inactivo.") y un texto genérico sobre roles cuando es el default en inglés de Laravel; el 419 explica que expiró la sesión con botón de reintento; el 500 da el mail de soporte; el 503 avisa mantenimiento.

#### Técnico

- Layout `errors/minimal.blade.php` **autónomo a propósito**: sin `@vite`, sin componentes Blade, sin fuentes externas — si el error es un 500, la página de error no puede depender de nada que pueda estar roto. Paleta y tipografías inline.
- 3 tests nuevos (`ErrorPagesTest`): 404 amigable, mismo 404 para recursos de otro tenant, 403 en español sin filtrar el default en inglés. **457 tests, todos verdes.**

## [0.11.1] — 2026-07-17

### Aislamiento estructural entre tenants (mediano plazo de la auditoría, prioridad #1)

Hasta ahora el aislamiento entre negocios dependía de que cada controller se acordara de scopear (`$tenant->relación()`, policies, reglas `exists` por tenant) — y ya había fallado una vez (el `supplier_id` corregido en v0.10.0). Esta versión lo vuelve **estructural**: aunque un controller futuro se olvide de scopear, los datos de otro tenant son invisibles.

#### Técnico

- **Nuevo trait `App\Models\Concerns\BelongsToTenant`**, aplicado a los 15 modelos de dominio con `tenant_id` (Ingredient, Supplier, Packaging, FixedCost, FixedCostCategory, VariableExpense, VariableExpenseCategory, LaborType, Recipe, PriceList, Purchase, Location, StockMovement, StockLevel, Invitation). Cuando hay un tenant resuelto en el container: **global scope** que acota toda query Eloquent al tenant, y **auto-fill de `tenant_id`** en los creates que no lo traen. Sin tenant resuelto (backoffice `/admin`, artisan, tests sin request) el scope no aplica: esos contextos son cross-tenant por diseño.
- **Quedan fuera a propósito** (documentado en el trait): `TenantUser` (el middleware lo consulta *antes* de resolver el tenant, y `isSuperAdmin()` debe ver la membresía en Levado HQ durante la impersonación), `TenantSetting` (siempre accedido vía la relación del tenant) y `AdminAuditLog` (registro del backoffice, cross-tenant).
- **`SetTenantContext` se adelantó en la prioridad de middleware** (antes de `SubstituteBindings`, en `bootstrap/app.php`): el tenant queda resuelto antes del route-model binding, así el scope aplica también al binding y **un recurso de otro tenant responde 404 directo** — ya no revela ni su existencia. Antes respondía 403 vía policy.
- Las policies y las reglas `exists` scopeadas se mantienen como segunda capa. La convención de escribir queries con `$tenant->relación()` sigue vigente: el trait es la red, no el reemplazo.
- **Efecto lateral que mitiga el hallazgo S3 de la auditoría:** `Gate::before` saltea las policies para super admins, pero el scope estructural aplica igual — impersonando al tenant A, los recursos del tenant B dan 404 también para el super admin. Anclado con test.
- **Cambio de contrato:** los accesos cross-tenant pasan de 403 a **404** en todos los recursos con binding. Los 16 tests de aislamiento existentes se actualizaron; el test "viewer no puede eliminar gastos variables" ahora crea el gasto en el tenant del viewer (probaba rol y aislamiento a la vez; el 404 del binding enmascaraba el 403 del rol).
- 6 tests ancla nuevos (`BelongsToTenantTest`): scope activo con tenant resuelto, sin scope en contexto admin/consola, auto-fill en create, el auto-fill no pisa un `tenant_id` explícito, binding acotado sin scopear a mano, e impersonación sin acceso cruzado.
- **454 tests, todos verdes.**

---

## [0.11.0] — 2026-07-17

> **Al deployar:** `php artisan migrate`. Los comprobantes de gastos nacen en el disco privado, así que `invoices:relocate` no los necesita (es sólo para las facturas de compras anteriores a 0.10.1).
>
> **Limpieza de datos previa (17/07/2026), ya aplicada en producción:** el escáner de facturas venía creando compras duplicadas —el aviso del front era sólo informativo y no había constraint— y el índice único de 0.10.1 no podía crearse hasta resolverlas. Se revisaron las 11 duplicaciones **contra la factura escaneada, una por una**: 14 bajas y 2 totales corregidos (una compra de $38.000 estaba cargada como $3.800 y una de $105.000 como $105; en ambas el renglón estaba bien, así que los costos de los insumos nunca se vieron afectados). Sin movimientos de stock involucrados.

### Comprobantes en Gastos Variables

#### Agregado

- **Los gastos variables ahora guardan su comprobante.** Casi todo gasto ocasional —un flete, una reparación, una boleta de luz, una compra de ferretería— viene con un papel que hasta ahora no tenía dónde ir: el gasto quedaba registrado sin respaldo. Se adjunta desde el mismo formulario de alta, sacando una foto con el teléfono (se abre la cámara trasera directamente) o subiendo una imagen o PDF.
- **Botón "Leer con IA", opcional.** Con el comprobante adjunto, un botón lo lee y completa solo la descripción, el monto, la fecha, el proveedor y la categoría. Los campos quedan editables: la IA propone, el usuario confirma antes de guardar. **Si no se toca el botón, el comprobante se guarda igual** — el mismo criterio que Compras adoptó en v0.9.1 para los tickets manuscritos, que el modelo de visión interpreta mal.
- **Un comprobante de varios ítems se resume en una descripción.** Un gasto es un monto único, no una factura con renglones: un ticket de ferretería con cinco cosas se convierte en "Ferretería: tornillos, cinta y silicona" y el total del ticket, en vez de cinco gastos sueltos.
- **El proveedor se busca solo entre los tuyos.** El nombre que la IA lee del comprobante se cruza contra el listado de proveedores; si no encuentra ninguno parecido, el campo queda vacío y está el "+ Nuevo proveedor" de siempre. La categoría se sugiere sólo dentro de tu catálogo: la IA no puede inventar una.
- **Clip en el listado** en la fila (escritorio) y en la card (móvil) de los gastos que tienen comprobante, que lo abre en una pestaña nueva. Desde la edición se puede ver el comprobante actual o reemplazarlo por otro.
- Los gastos fijos no se tocaron: son montos mensuales recurrentes sin fecha propia, así que un comprobante ahí no tendría a qué referirse.

#### Técnico

- **El escaneo vive dentro del modal de alta, no en una pantalla aparte.** Compras necesita su página de revisión porque una factura son 30 renglones que hay que asociar uno por uno con el catálogo; un gasto son 4 campos. El modal ya es la pantalla de revisión, así que el scan sube el archivo por `fetch` y prellena los campos ahí mismo — sin las rutas `scan/create` ni `scan/review`, sin controller de confirmación y sin el paso intermedio para el usuario.
- **Compras no se tocó.** `storeInvoiceImage()` sigue duplicado en `PurchaseController` y `PurchaseScanController`, como decidió v0.9.1: refactorizar el módulo más crítico del sistema dentro de una feature de Gastos habría mezclado un cambio de comportamiento con uno estructural. Lo nuevo (`ReceiptStorer`) sólo lo consume el código de gastos, e `InvoiceImagePreparer` y `window.compressInvoiceImage` se reusaron tal cual. El precio asumido: `ExpenseReceiptExtractor` duplica ~40 líneas de la mecánica HTTP de `InvoiceExtractor`.
- **Los comprobantes de gastos nacen en el disco privado** (`storage/app/private`), igual que los de compras desde v0.10.1: son datos fiscales y el symlink `/storage` los dejaría accesibles sin login. Se sirven sólo por la ruta autenticada `variable-expenses.receipt`. A diferencia de compras **no hay fallback al disco público**, porque no existen archivos anteriores que relocalizar: `invoices:relocate` no los necesita. Dos tests fijan que el archivo quede en el privado y que el público siga vacío.
- **`throttle:10,1` en el escaneo de gastos**, el mismo que v0.10.1 le puso al de facturas y por lo mismo: cada lectura es una llamada paga a la API de Anthropic que bloquea un worker hasta 60 s.
- Nuevos: `ExpenseReceiptExtractor` (prompt propio, sin catálogo de insumos ni renglones ni IVA; descarta la `category_id` sugerida si no es del tenant), `ReceiptStorer` (`store`/`storeContents`/`delete`/`safePath`), `SupplierMatcher`, `VariableExpenseScanController@scan`, el componente `x-receipt-link` y `resources/js/expenses/receipt-scan.js`. Migración `add_receipt_image_path_to_variable_expenses_table`: una sola columna `string` nullable, como `purchases.invoice_image_path` — el mime se infiere del sufijo del archivo.
- **`ReceiptStorer::safePath()` es el guard de seguridad de la feature.** Tras el scan el archivo ya está en disco y el formulario lo referencia por path en un hidden input, o sea que el valor es controlable por el cliente: sin el guard, un path armado a mano apuntaría un gasto propio al comprobante de otro negocio. Sólo se acepta un path bajo el prefijo del tenant y que exista en disco. Cubierto por dos tests.
- **El archivo se guarda antes de llamar a la IA y se borra si la lectura falla**, así un error de la API no deja comprobantes huérfanos ni le hace perder la foto al usuario. La ruta de scan devuelve 422 con el mensaje ya redactado en español, que el modal muestra inline.
- **`SupplierMatcher` ahora pliega los acentos** (`Str::ascii`) antes de comparar. Los comprobantes se imprimen en mayúsculas y sin tildes ("PANIFICACION GUEMES S.A.") mientras que el proveedor se carga como se escribe ("Panificación Güemes"): sin plegarlos, esos dos nunca matcheaban. **Corrige también el matcheo de proveedores al escanear facturas en Compras**, que arrastraba la misma limitación desde v0.8.x.
- El botón "Leer con IA" limpia el `<input type="file">` tras el scan y pasa a referenciar el path, y el controller usa `hasFile()` / `elseif filled(path)`: es lo que evita que el mismo comprobante se guarde dos veces.
- Un `<input type="file">` no se puede repopular tras un error de validación (restricción del navegador). El path del scan sí sobrevive vía `old()`; para el adjunto manual el modal avisa que hay que volver a adjuntarlo. `$errorsInCreate` ahora incluye `receipt`, así un mime rechazado reabre el modal en vez de perderse.
- 25 tests nuevos: `VariableExpenseReceiptTest` (13: alta con y sin comprobante, mime inválido, downscale, reemplazo que borra el anterior, borrado en cascada, la ruta que sirve el archivo, 404 y aislamiento entre negocios) y `VariableExpenseScanTest` (12: campos leídos, resumen de varios ítems, formato numérico argentino, matcheo con y sin tildes, categoría de otro negocio descartada, fallo de la API sin huérfanos, falta de API key, rol sin permiso y los dos casos de `safePath`). `PurchaseScanTest` y `PurchaseCrudTest` pasan sin modificarse.
- Versión `0.11.0` en `config/app.php`.

---

## [0.10.1] — 2026-07-17

### Quick wins de la auditoría técnica (corto plazo del plan aprobado)

Ver `AUDITORIA_DEUDA_TECNICA.md` para el diagnóstico completo. Solo diagnóstico → implementación de la etapa de corto plazo: seguridad y quick wins, sin cambios de comportamiento funcional visibles salvo los indicados.

#### Seguridad

- **Los comprobantes de compras pasan al disco privado** (`storage/app/private`). Antes se guardaban en el disco `public`: aunque la app los sirve por una ruta autenticada (`purchases.invoice`), el symlink `/storage` los dejaba accesibles **sin login** a quien conociera la URL — y son datos fiscales (CUIT, precios de proveedores). Los archivos nuevos van al disco privado; los existentes se sirven por fallback hasta correr el nuevo comando `php artisan invoices:relocate` (con `--dry-run` para previsualizar). Los logos de negocio siguen en el disco público (son branding).
- **Rate limiting en el escaneo de facturas con IA** (`throttle:10,1`): cada escaneo dispara una llamada paga a la API de Anthropic y bloquea un worker hasta 60 s; sin límite, un usuario podía quemar crédito sin tope.
- **Resolución de tenant determinista:** si un usuario perteneciera a más de un tenant, el middleware elegía uno según el plan de ejecución de MySQL (sin `ORDER BY`). Ahora resuelve siempre el de menor ID, hasta que exista el selector de tenant.
- **Facturas duplicadas bloqueadas en base de datos:** índice único `(tenant_id, supplier_id, invoice_number)` en `purchases` + regla de validación con mensaje claro en alta manual, edición y escaneo. El chequeo previo del front era solo advisory: dos submits simultáneos o un usuario que ignorara el aviso duplicaban costos y stock. Las compras sin número de factura no se limitan. La migración aborta con detalle si encuentra duplicados preexistentes que resolver a mano.
- `.env.example` con checklist de producción (APP_DEBUG=false, SESSION_SECURE_COOKIE=true, timezone/locale argentinos, relocación de comprobantes).

#### Técnico

- **`Tenant::totalFixedCosts()` y `Tenant::overheadPerHour()`** como único dueño de la fórmula de overhead, que estaba duplicada en 4 controllers (`Dashboard`, `RecipePrice`, `Recipe`, `Business`) — la deuda DD3 documentada en v0.10.0. Cualquier cambio futuro a la fórmula va a un solo lugar.
- **Scoped bindings en las rutas anidadas de líneas** (recetas y compras): Laravel resuelve la línea dentro de la relación del padre (404 si no le pertenece), eliminando los 12 chequeos manuales `abort_unless($line->recipe_id === $recipe->id)`. Cambio de contrato menor: una línea ajena ahora responde 404 en vez de 403 (mejor: no revela existencia). Los parámetros de ruta de recetas se renombraron (`{line}` → `{ingredientLine}`, etc.) para que el binding encuentre la relación; las URLs no cambian.
- `Tenant::getSetting()` memoiza los settings por instancia (una query por request en vez de una por llamada); `setSetting()` invalida el cache.
- `RecipeController::copy()` ahora corre en transacción: una falla a mitad de la copia ya no deja una receta parcial.
- Se quitaron los logs de depuración del flujo de aceptación de invitaciones.
- 4 tests nuevos (duplicados de factura ×3, scoped binding de líneas) y actualización de los tests de storage al disco privado. **400 tests, todos verdes.**

### Orientación de los comprobantes: giro manual y fotos de costado

#### Agregado

- **Botones para girar la foto, en los 5 lugares donde se captura un comprobante:** el alta y la edición de gastos variables, y las tres pantallas de Compras (el escaneo de facturas, el alta manual y la edición). Aparecen apenas se elige una imagen, giran de a 90° para cada lado, y lo que se guarda o se manda a leer es la imagen ya derecha. Una factura derecha además se lee mejor.
- Los controles no aparecen para PDFs, que no se giran en el navegador.

#### Corregido

- **Una foto sacada de costado ya no queda acostada para siempre, y ya no falsea los datos.** Si el navegador no llegaba a procesar la foto antes de subirla —scripts deshabilitados, o el compresor del navegador dándose por vencido, que es justo lo que pasa en los celulares con poca memoria— la foto viajaba tal cual salió de la cámara. El servidor la achicaba **ignorando la orientación y descartándola en el proceso**, así que quedaba acostada y ya no había forma de enderezarla: el dato que decía cómo mostrarla se había perdido. Y una factura de costado la IA la lee mal.

  **No es hipotético: pasó, y quedó medido sobre datos reales.** En la base de producción había **42 compras guardadas de costado**, todas anteriores al 28/06/2026 —el día que llegó la compresión del navegador, que tapaba el problema sin arreglarlo—. La factura `0004-00084240` (que en el papel dice `02/06/2026`) se leyó dos veces: la copia acostada quedó cargada como **6 de febrero** y la derecha como **2 de junio**. Con esa misma foto, el A/B es contundente: **acostada la IA devuelve `2026-02-16`** —una fecha que no existe en el papel— **y derecha devuelve `2026-06-02`**, la correcta.

#### Técnico

- **`InvoiceImagePreparer` ahora aplica la orientación EXIF antes de re-encodear.** El teléfono no rota los píxeles: los deja como salieron del sensor y anota en el EXIF cómo mostrarlos. Como `imagejpeg()` descarta el EXIF, hay que hornear el giro en los píxeles **antes**; si no, la orientación se pierde de forma irreversible. Se lee con `exif_read_data()` sobre un stream en memoria y se aplica con `imagerotate`/`imageflip`, cubriendo los 8 valores del estándar (incluidos los espejados). Se re-encodea también cuando la foto no necesita achicarse, porque antes un early-return la dejaba pasar de largo. Degrada al archivo original si falta la extensión `exif` o si algo falla, igual que el resto de la clase. **Era el escenario que el propio docblock de la clase decía venir a cubrir** ("defence in depth for uploads that bypass the JS"): el único camino donde la clase importa era justo donde corrompía la orientación.
- 16 tests nuevos (`InvoiceImagePreparerTest`), con un helper que inyecta un bloque APP1 con el campo Orientation porque `UploadedFile::fake()->image()` no genera EXIF. Las 8 orientaciones se verifican por la esquina donde queda una marca asimétrica: el tamaño no alcanza para detectar un giro de 180° ni un espejado.
- **`imageOrientation: 'from-image'` declarado explícitamente** al decodificar. Los teléfonos guardan la orientación en el EXIF y no en los píxeles, y `canvas.toBlob()` la descarta al re-encodear: si el decode no la aplica, la foto se sube acostada aunque en la galería se vea derecha. El valor por defecto cambió a lo largo de las versiones del estándar, así que dependía del navegador. **Es blindaje, no un arreglo de un bug observado:** se verificó contra Chrome 148 que el default ya aplicaba el EXIF correctamente, y el pipeline entero (foto horizontal + EXIF=6 → JPEG vertical legible) se probó end-to-end. Queda declarado para no depender del navegador ni de su versión. Aplica también al escaneo de facturas en Compras, que usa el mismo helper.
- `rotateInvoiceImage(file, degrees)` en `resources/js/image-compress.js`, expuesto como global igual que `compressInvoiceImage` (el proyecto no usa `Alpine.data()`: la lógica pesada va en helpers de `resources/js/` y el `x-data` queda fino). Degrada al archivo original ante cualquier error, como el resto del helper.
- **Cada giro re-encodea desde el archivo original, no desde el resultado anterior**, acumulando el ángulo en el estado. Girar 4 veces sobre el resultado previo apilaría 4 generaciones de pérdida JPEG.
- El giro re-inyecta el archivo en el `<input type="file">` vía `DataTransfer`, el mismo mecanismo que ya usaba la compresión para subir el JPEG chico en vez del original.
- En el alta de gastos, girar después de haber leído con IA resetea el estado de lectura y limpia el `receipt_image_path`: el archivo que quedó en disco no está girado, así que el rotado vuelve a viajar con el submit. El botón vuelve a decir "Leer con IA".
- Componente `x-rotate-button` con props `direction` y `method`, para no repetir el SVG y las etiquetas accesibles en 10 botones. La lógica de `x-data` sí queda duplicada en las 5 vistas: es la misma decisión que ya tomó v0.9.1 con estos pickers, y unificarlas exigía renombrar el estado de las 3 vistas de Compras (`invoiceFileName` vs `receiptName`), que no tienen cobertura de browser que lo respalde.
- 4 tests nuevos (`ReceiptRotationTest`) que fijan que los controles se rendericen en las 5 pantallas: el giro pasa entero en el navegador, pero el helper es compartido y un componente roto los rompe a todos a la vez.

### Lector con IA: qué lee bien y qué no

#### Cambiado

- **Los comprobantes manuscritos no se leen más con IA: se cargan a mano y la foto queda como respaldo.** Decisión tomada con la evidencia de los presupuestos reales del talonario en la mano: el modelo no los lee, inventa. Leyó **"105,000" como 105** —una compra de ciento cinco mil pesos anotada como ciento cinco— y una fecha escrita `10|6|26` como **2027-12-16**. Al releerlos con el pipeline ya arreglado **empeoraban**, así que no es cuestión de orientación ni de prompt: el modelo no da para manuscrito. La lectura con IA queda para comprobantes impresos, que es donde funciona bien.

#### Técnico

- **El error de fechas era la orientación, no el prompt.** Se había atribuido a que el prompt no aclaraba el formato argentino; revisando las facturas reales una por una quedó claro que la causa es que la foto llegaba de costado (ver la sección anterior). Las reglas de prompt se conservan igual como red de seguridad, pero **el arreglo de fondo es el EXIF**.
- **Releer en masa las facturas viejas para "corregirlas" haría más daño que bien, y se midió:** sobre las 7 compras cuya relectura difería de lo cargado, mirar el papel mostró que la relectura **arreglaba 2 y rompía 5**. El lector es ruidoso con fotos reales. De 154 compras, sólo 2 tenían la fecha mal y se corrigieron a mano.
- **Los dos prompts (`InvoiceExtractor` y `ExpenseReceiptExtractor`) llevan ahora la regla DÍA/MES/AÑO y el control del total**, más "la descripción es qué se compró, no el proveedor". Son red de seguridad: con la foto derecha el modelo ya acertaba, así que ninguno es el arreglo de fondo — pero los comprobantes reales son bastante más sucios que un fixture y las reglas no cuestan nada. El de Compras además explicita el control de que el total no pierda dígitos, porque en producción una factura quedó con 3.800 en vez de 38.000.
- **Un comprobante generado por código no sirve para medir un lector de visión: da falsos verdes.** Contra un JPEG hecho con GD el prompt acertaba 5/5 en fecha y monto, y de ahí se concluyó dos veces —mal— que no había nada que arreglar. Las facturas reales lo desmintieron en los dos casos. Para medir esto hacen falta fotos de verdad.

---

## [0.10.0] — 2026-07-15

### Gastos Variables

#### Agregado

- **Nueva pestaña "Gastos Variables" en la pantalla de Gastos:** hasta ahora el módulo solo registraba gastos fijos (costos operativos mensuales que se reparten sobre las recetas vía overhead por hora). Ahora se pueden registrar también gastos ocasionales o imprevistos —una reparación, una compra puntual— que **no forman parte de los costos de producción y no intervienen en el costo de recetas, márgenes ni ningún cálculo productivo**. Quedan como registro administrativo, preparados para los reportes y el análisis financiero que vienen más adelante.
- **Un gasto variable es un evento puntual:** nombre, categoría, fecha en que ocurrió, monto único y, opcionalmente, **proveedor**. A diferencia de un gasto fijo, no tiene monto mensual, ni interruptor activo/inactivo, ni historial de precios — nada de eso aplica a un gasto que pasó una sola vez.
- **Proveedor opcional, tomado del listado de proveedores ya existente:** permite registrar a quién se le pagó ("Bolsas → Papeluca") y dejar el dato listo para cruzar gasto por proveedor en los reportes. Es opcional a propósito: una multa, un gasto bancario o una reparación informal no tienen proveedor. Incluye el mismo "+ Nuevo proveedor" inline que ya usan Compras e Ingredientes, que ahora se abre **encima** del formulario sin perder lo que se venía cargando.
- **Catálogo de categorías propio:** las categorías de gastos variables se administran por separado de las de gastos fijos, así "Alquiler" no aparece al cargar una reparación ni "Reparaciones" al cargar un gasto fijo. Incluye el mismo "+ Nueva categoría" inline que ya tenían los fijos.
- **Filtros pensados para el análisis financiero:** búsqueda por nombre, filtro por categoría, por proveedor y por rango de fechas (Desde/Hasta), con un "Total del período" que respeta los filtros aplicados. Permite responder "cuánto le pagué a X este mes" sin esperar a los reportes. Sin filtro de fecha por defecto, para que un gasto recién cargado nunca quede oculto.
- **Los gastos variables se pueden eliminar** (los fijos no, porque borrarlos alteraría costos históricos). Como son registros puntuales sin nada que dependa de ellos, equivocarse de pestaña se corrige borrando y recargando.
- Los gastos fijos siguen funcionando exactamente igual: mismo listado, mismo alta/edición, mismo toggle activo/inactivo, mismo "Total mensual activo".

#### Técnico

- **Tabla separada `variable_expenses` en vez de una columna `type` en `fixed_costs`.** El overhead se calcula con `$tenant->fixedCosts()->active()->sum('monthly_amount')` duplicado en 5 lugares (`DashboardController`, `RecipePriceController`, `RecipeController`, `BusinessController` y un getter Alpine en `recipes/show.blade.php`). Con una columna `type`, los 5 pasaban a necesitar `->where('type','fixed')` y olvidar uno habría inflado en silencio el costo de todas las recetas, sin que ningún test fallara. Con tabla separada la garantía es estructural —la relación no puede ver la tabla nueva— y esos 5 call sites no se tocaron. Como beneficio secundario, no hubo migración de datos: las filas existentes ya eran gastos fijos en la tabla de gastos fijos.
- **La pestaña es el selector de tipo.** Al tener campos divergentes entre ambos tipos (`monthly_amount`+`active`+`valid_from` vs `amount`+`expense_date`), un selector dentro del formulario tendría que mutar el formulario entero al cambiar; la pestaña activa define el tipo sin estado oculto. Como consecuencia, un gasto no se puede convertir de un tipo al otro.
- Nuevos: `VariableExpense` (casts `decimal:2`/`date`, scope `between()` para el filtro de período y los reportes futuros), `VariableExpenseCategory`, `VariableExpenseController` (`index`/`store`/`update`/`destroy`), `VariableExpenseCategoryController`, `Store`/`UpdateVariableExpenseRequest` (regla `exists` scopeada por tenant), `VariableExpensePolicy` (auto-discovery) y 2 factories. 7 rutas nuevas: `index` en el grupo de lectura, las 6 mutaciones tras `role:super_admin,owner,admin`.
- Migraciones `create_variable_expense_categories_table` y `create_variable_expenses_table`, con índices `[tenant_id, expense_date]` y `[tenant_id, supplier_id]` para los reportes financieros futuros. La URL `/fixed-costs` no se renombró.
- **`supplier_id` nullable + `nullOnDelete`.** El `<select>` de proveedor evita el bug del proveedor dado de baja (ver *Corregido*): el controller pasa **todos** los proveedores en una sola query y la vista filtra según el uso — sólo activos en el alta, todos (marcando `(inactivo)`) en la edición, y todos en el filtro para poder acotar gastos históricos de un proveedor ya dado de baja. Cubierto por test de regresión.
- El `supplier_id` se valida con la misma regla `exists` scopeada por tenant que la categoría, en vez del `abort_unless` que usa `PurchaseController:81`.
- 2 componentes Blade nuevos: `x-expense-tabs` (data-driven; un tipo nuevo es una línea en el array) y `x-expense-categories-modal`, extracción parametrizada del modal de categorías que ahora comparten ambas pestañas — único punto donde se tocó una vista de gastos fijos, con markup idéntico.
- El onboarding no se modificó: al vivir en otra tabla, `$tenant->fixedCosts()->count() === 0` (`AppServiceProvider:31`) sigue exigiendo un gasto fijo real. Test agregado para fijarlo.
- 2 componentes Blade reutilizados de otros módulos: el modal compartido `suppliers/modals/quick-create.blade.php` (que despacha `supplier-created`) se incluye tal cual; el "+ Nuevo proveedor" **apila** el quick-create con `:z="60"` como hace `purchases/modals/create.blade.php:72`, en vez de cerrar y reabrir el modal padre como hace el de ingredientes, que hace perder el formulario.
- 35 tests nuevos: `VariableExpenseCrudTest` (33: CRUD por rol, validación, filtros, categorías, proveedor, aislamiento entre tenants, y las ausencias deliberadas de `toggle-active` e historial de precios) + 2 anclas de la invariante central: cargar gastos variables no mueve `overheadPerHour` ni el margen del dashboard, y un gasto variable no completa el paso de gastos fijos del onboarding.
- Versión `0.10.0` en `config/app.php`.

#### Corregido

- **Un proveedor dado de baja ya no rompe la edición de ingredientes, descartables y compras.** Los `<select>` de proveedor listaban sólo proveedores activos, así que cuando el proveedor de un registro se daba de baja su opción desaparecía y el select caía en la opción vacía. En **Ingredientes** y **Descartables** eso **borraba el proveedor en silencio** al guardar cualquier otro campo (por ejemplo, al corregir un costo). En **Compras** el select es obligatorio, así que el efecto era el opuesto pero igual de molesto: **no se podía editar nada** de una compra vieja —ni la fecha— sin reasignarle antes otro proveedor. Ahora el select de edición incluye también los proveedores dados de baja, marcados como `(inactivo)`, mientras que el de alta sigue ofreciendo sólo los activos. Casos reales que corrige en datos existentes: la compra #48 (proveedor "Vendix Rollos de Film") y los ingredientes "Azucar" y "Limon esc" (proveedor "Ariel azucar").
- **Aislamiento entre negocios en el proveedor de ingredientes y descartables.** `Store`/`UpdateIngredientRequest` y `Store`/`UpdatePackagingRequest` validaban `supplier_id` con `exists:suppliers,id` sin acotar por tenant, y sus controllers no tenían el chequeo que sí hace `PurchaseController:81`. Como los IDs de proveedor son seriales globales, un usuario podía asignarle a un ingrediente propio un proveedor de otro negocio y ver su nombre en su propio listado, pudiendo así enumerar nombres de proveedores ajenos probando IDs. Ahora usan la regla `exists` acotada por tenant. Verificado con un test que falla sin el arreglo.

#### Técnico (correcciones)

- `IngredientController:41`, `PackagingController:41` y `PurchaseController:176` pasan `$tenant->suppliers()` sin `->active()`; el filtrado por estado se hace en la vista (`$suppliers->where('active', true)` en los modales de alta), porque el mismo `$suppliers` alimenta alta y edición. Cada uno lleva un comentario explicando el porqué, para que no se "optimice" de vuelta.
- **No se tocaron** `PurchaseController:71` (index: sólo alimenta el filtro y el modal de alta) ni `PurchaseScanController:76` (alta pura desde escaneo, donde no ofrecer inactivos es lo correcto).
- 9 tests nuevos (396 en total) en `IngredientCrudTest`, `PackagingCrudTest` y `PurchaseCrudTest`: el proveedor inactivo sobrevive a la edición, el alta no lo ofrece, y el aislamiento por tenant. Verificados reintroduciendo el bug a propósito para confirmar que fallan.

---

## [0.9.3] — 2026-07-14

### Recetas: editar unidad de una línea de ingrediente

#### Agregado

- **Edición de unidad en líneas de ingrediente ya cargadas:** hasta ahora, una vez agregado un ingrediente a una receta, solo se podía editar su cantidad — para cambiar la unidad había que borrar la línea y volver a cargarla. Ahora el selector de unidad (ya existente al agregar) también aparece al editar, filtrado a las unidades compatibles con la del ingrediente (peso: gr/kg — volumen: ml/L/cc — unidad: u), y el costo de la línea y de la receta se recalculan automáticamente vía `UnitConverter` sin recargar la página.
- **Conversión automática de la cantidad al cambiar unidad:** al elegir una nueva unidad en una línea existente, la cantidad se recalcula al vuelo para mantener la misma proporción real (ej: `2 kg` → `2000 gr`), en vez de dejar el número tal cual y obligar a recalcularlo a mano.
- Si el ingrediente solo tiene una unidad compatible (ítems por `u`, incluyendo los que usan subdivisiones), se mantiene el badge de solo lectura tal como estaba, evitando un selector sin opciones reales.
- **Abreviaturas en todos los selectores de unidad:** los `<select>` que ofrecen unidades (agregar/editar ingrediente en receta, unidad de rendimiento de receta, sub-recetas, alta/edición de ingrediente, unidad de compra) mostraban el nombre completo ("Kilogramo (kg)", "Centímetro cúbico (cc)"); ahora muestran solo la abreviatura (`kg`, `cc`), consistente con cómo ya se mostraba la unidad en badges de solo lectura en el resto de la app.

#### Técnico

- `RecipeController::updateIngredientLine()` valida `unit` igual que `storeIngredientLine()` y devuelve `unitLabel`/`costPerLineUnit` recalculados en la respuesta JSON.
- Fix de un bug de Alpine.js: `x-model` en un `<select>` con opciones generadas por `x-for` no reflejaba el valor inicial (mostraba siempre la primera opción); se resolvió con `:selected` explícito por opción y un handler `@change` propio en vez de `x-model` en el `<select>`.
- Conversión de cantidad al cambiar unidad implementada en el cliente (Alpine.js, misma tabla de factores que `UnitConverter`: ×1000 para kg/L) — el servidor sigue siendo la fuente de verdad final vía la validación de compatibilidad ya existente.
- Reemplazo de `Unit::label()` por `Unit::short()` en las opciones de los `<select>` de: `recipes/modals/add-ingredient.blade.php`, `recipes/show.blade.php`, `recipes/modals/edit-info.blade.php`, `recipes/modals/create.blade.php`, `recipes/modals/add-subrecipe.blade.php`, `ingredients/modals/create.blade.php`, `ingredients/modals/edit.blade.php`, `purchases/modals/add-line.blade.php`, `purchases/modals/edit-line.blade.php`.
- 2 tests nuevos en `RecipeCostTest` (actualización con unidad compatible, rechazo de unidad incompatible).
- Versión `0.9.3` en `config/app.php`.

---

## [0.9.2] — 2026-07-14

### Existencias: columnas ordenables

#### Agregado

- **Ordenamiento en el listado de Existencias:** las columnas Nombre, Stock actual y Mínimo ahora son ordenables (ascendente/descendente) desde `/stock`, con el mismo patrón de `sort`/`dir` ya usado en el resto de las tablas del sistema. Se preservan búsqueda, filtro de pestaña (insumos/descartables) y paginación.

### Eliminación de "Merma"

#### Quitado

- **Función "Merma" eliminada del módulo de Stock:** quedaba redundante con "Ajuste" (mismo mecanismo de entrada/salida con motivo). Se quitaron el botón, la ruta, el controlador, el form request, el modal y el caso `Waste` del enum `StockMovementType`. La tabla `stock_movements` y el resto de las acciones (Ajuste, Recuento, Compra) no se modificaron — no había registros históricos de tipo merma.

### Dashboard: badge "Semi"

#### Agregado

- **Badge "semi" en el Dashboard:** las recetas semielaboradas ahora muestran la misma badge "semi" que ya existía en el listado de Recetas, junto al nombre en la tabla de rentabilidad. Se extrajo a un componente reutilizable (`x-semi-badge`), eliminando la duplicación que existía entre la vista de cards y la de tabla en `/recipes`.

### Paginación en castellano

#### Corregido

- **Textos de paginación en inglés:** la vista de paginación de Laravel (usada en las 15 tablas del sistema) mostraba "Showing X to Y of Z results" y "Pagination Navigation" sin traducir, a pesar de que "Anterior"/"Siguiente" ya estaban en español. Se agregó `lang/es.json` con las traducciones faltantes.

#### Técnico

- `StockController::index()`: `LEFT JOIN` a `stock_levels` para permitir ordenar por columnas que no viven en la tabla paginada (Ingredient/Packaging), preservando la unicidad `(tenant_id, stockable_type, stockable_id, location_id)` para evitar duplicar filas.
- 6 tests nuevos (5 de ordenamiento en `StockCrudTest`, 1 de la badge en `DashboardRentabilidadTest`) + 1 test de regresión de paginación en castellano.
- Versión `0.9.2` en `config/app.php`.

---

## [0.9.1] — 2026-07-13

### Compras: comprobante en la carga manual

#### Agregado

- **Comprobante opcional en compra manual:** el modal "+ Nueva compra" ahora permite adjuntar una foto o PDF del ticket/factura, sin pasar por la lectura con IA. Pensado para comprobantes manuscritos que la IA lee mal — los renglones se cargan a mano (como ya era posible) y la imagen queda guardada como respaldo, visible desde "Ver factura original" en el detalle de la compra.
- **Reemplazo del comprobante al editar:** el modal de edición de compra permite adjuntar el comprobante si no se cargó al crear, o reemplazarlo por uno nuevo (borra el archivo anterior del storage).

#### Técnico

- `StorePurchaseRequest`/`UpdatePurchaseRequest`: regla `invoice` opcional (mismos mimetypes y límite de 10 MB que el escaneo).
- `PurchaseController`: nuevo helper `storeInvoiceImage()` (reutiliza `InvoiceImagePreparer` para downscale) compartido entre alta y edición.
- 5 tests nuevos (`PurchaseCrudTest`) — creación con/sin comprobante, formato inválido rechazado, reemplazo y conservación del archivo existente.
- Versión `0.9.1` en `config/app.php`.

---

## [0.9.0] — 2026-07-10

### Existencias (stock de insumos y descartables)

#### Agregado

- **Módulo de existencias:** nueva pantalla `/stock` con tabs de insumos y descartables, stock actual, mínimo, valuación (al último costo de cada ítem) y alertas visuales de stock negativo o bajo mínimo. Kardex por ítem (`/stock/{tipo}/{id}`) con el historial completo de movimientos.
- **Ledger inmutable:** cada movimiento queda registrado en `stock_movements` (compra, ajuste, merma, recuento) con costo snapshot; el saldo se cachea en `stock_levels`. Toda corrección es un contramovimiento — el historial nunca se edita ni se borra. El stock negativo está permitido (la alerta es solo visual).
- **Integración con Compras:** al imputar el costo de un renglón se registra la entrada de stock automáticamente (convertida a la unidad del ítem, con soporte de subdivisiones). Editar o eliminar renglones/compras revierte el stock con contramovimientos exactos.
- **Acciones manuales:** modales de ajuste (entrada/salida con motivo), merma, recuento físico (con delta en vivo) y stock mínimo.
- **Columna Stock en los catálogos:** las tablas de `/ingredients` y `/packaging` muestran el stock actual con edición inline (mismo patrón que el costo de descartables): al guardar el valor absoluto se registra la diferencia como recuento, conservando el historial. Ícono de historial junto al valor que lleva al kardex; en las cards mobile se muestra el stock con link al historial. El mismo ícono de historial se agregó al listado de stock.
- **Menú lateral en grupos colapsables:** el sidebar se reorganizó en grupos temáticos — Producción (Recetas), Existencias (Compras, Proveedores, Stock), Costos (Ingredientes, Descartables, Mano de Obra, Gastos, Listas de Precios) y Administración (Mi negocio, Sucursales, Mi equipo). Cada grupo se pliega/despliega con un clic, recuerda su estado (localStorage) y se auto-expande cuando contiene la página activa. El ítem "Existencias" pasó a llamarse "Stock" (el grupo se llama Existencias). Breadcrumbs y drawer mobile "Más" alineados a los grupos nuevos; se agregó el breadcrumb faltante de Listas de Precios.
- **Sucursal default:** los movimientos se registran en la sucursal "Casa Central" del tenant (creada en forma lazy), dejando la costura lista para multi-sucursal.

#### Técnico

- `StockService` como único punto de escritura del stock (lock pesimista por ítem/sucursal para escrituras concurrentes).
- Endpoint JSON `PATCH /stock/{tipo}/{id}/level` para la edición inline desde los catálogos (reutiliza el recuento).
- 51 tests nuevos (servicio, integración con compras, HTTP, permisos y aislamiento multi-tenant).
- Versión `0.9.0` en `config/app.php`.

---

## [0.8.15] — 2026-07-07

### Cambiado

- **Banner PWA con un único botón "Instalar":** el banner ya no muestra instrucciones de entrada. Siempre ofrece el botón "Instalar"; si el navegador permite la instalación directa (`beforeinstallprompt`) la ejecuta con un toque, y solo si no la permite (iOS, o Android sin el evento) al tocar el botón se muestran los pasos según la plataforma.

### Corregido

- **Banner PWA invisible en Android:** el banner solo aparecía si Chrome disparaba `beforeinstallprompt`; si no lo hacía (app ya conocida por el navegador, criterios de instalabilidad no cumplidos, navegador sin soporte), no se mostraba nada. Ahora, en Android sin el evento, tras 3 segundos aparece un fallback con instrucciones manuales ("Abrí el menú ⋮ → Agregar a la pantalla principal / Instalar app").
- **Íconos `icon-192.png` y `apple-touch-icon.png` en blanco:** se generaron sin la tipografía cargada (archivos de ~500 bytes con solo el fondo). Regenerados correctamente — el banner ahora muestra el ícono "lvd".
- **Tipo MIME de `manifest.webmanifest`:** agregado `AddType application/manifest+json .webmanifest` en `public/.htaccess` para hostings compartidos que no mapean la extensión (necesario para la instalabilidad en Android).

---

## [0.8.14] — 2026-07-07

### Corregido

- **IVA en detalle de compra cuando la factura no tiene IVA:** si todos los renglones tienen alícuota 0, ya no se muestra el "Total factura (con IVA)" del encabezado (dato que viene del escaneo y podía ser incorrecto) ni montos "$ 0,00" en la columna IVA. La columna "IVA $" y su total en el pie ahora muestran "—" cuando el monto es cero (mismo patrón que la columna Percepción), y las etiquetas se ajustan: "Total renglones" sin el sufijo "(sin IVA)" y "Total" en lugar de "Total con IVA" en el resumen mobile.
## [0.8.13] — 2026-07-07

### Estado de tablas preservado, PWA instalable y selects ordenados

#### Agregado

- **App instalable (PWA):** Levado ahora se puede instalar en el teléfono como una app. Se agregaron `manifest.webmanifest`, service worker (`sw.js`) con soporte offline básico — assets estáticos con cache-first, navegaciones siempre por red con página "Sin conexión" de cortesía (nunca se cachea HTML con datos) — e íconos PNG (192, 512, maskable y apple-touch) generados desde el favicon SVG con la tipografía Lora original.
- **Banner de instalación mobile:** componente `<x-pwa-install-banner />` en el layout de la app. En Android/Chrome captura `beforeinstallprompt` y muestra el botón "Instalar" (prompt nativo); en iOS muestra las instrucciones "Compartir → Agregar a inicio" con íconos. Se puede descartar por 30 días (`localStorage`).
- **Restauración de posición de scroll:** al volver a un listado (tras guardar un modal o regresar de un detalle) la página se restaura en la fila donde estaba el usuario (`scroll-restore.js`, `sessionStorage` por URL, TTL 5 minutos).
- **Parámetro `volver` en detalles:** los links del índice al detalle de recetas y compras llevan la query string del listado; el botón "Volver" y el breadcrumb la reinyectan para regresar a la misma página/búsqueda/filtros.

#### Corregido

- **Se perdía el contexto del listado al guardar:** los `store`/`update` de los CRUD con modal (ingredientes, envases, gastos fijos, mano de obra, listas de precios, proveedores, sucursales, categorías de gastos y admin→usuarios) redirigían al índice "limpio", perdiendo `page`, `search`, `status`, `sort` y `dir`. Ahora usan `back()` con fallback al índice — el mismo patrón que ya usaban los toggles de activo. Las acciones de líneas del detalle de receta también, por lo que conservan la lista de precios seleccionada y el parámetro `volver`.
- **Select de sub-recetas sin ordenar:** `RecipeController::availableSemiElaborates()` devolvía las semi-elaboraciones en orden de creación; ahora ordena por nombre. Era el único select de datos sin orden alfabético — los selects de opciones fijas (unidades, roles, condición IVA) mantienen su orden lógico intencionalmente.

#### Técnico

- Metas PWA (`manifest`, `theme-color`, `apple-touch-icon`, `apple-mobile-web-app-*`) en los tres layouts (`app`, `guest`, `admin`); el layout `guest` además ahora referencia el favicon.
- Registro del service worker y captura global de `beforeinstallprompt` en `resources/js/app.js`.
- Nuevo test en `IngredientCrudTest`: editar un ingrediente redirige a la misma URL del listado con filtros y página (287 tests en total, todos verdes).
- Versión `0.8.13` en `config/app.php`.

---

## [0.8.12] — 2026-07-01

### Corregido

- **Invitación de usuario al equipo:** al invitar un miembro se mostraba el error "The selected role is invalid." para cualquier rol seleccionado. La causa era que `Rule::enum()->only()` requiere instancias del enum pero recibía strings (`.value`). Corregido usando `->except([TenantUserRole::SuperAdmin])` con instancias del enum.
- **Mensajes de validación en inglés:** creados `lang/es/validation.php`, `lang/es/auth.php` y `lang/es/passwords.php` con todas las traducciones estándar de Laravel al español. El locale ya era `es` en `.env` pero faltaban los archivos. Actualizado también el default en `config/app.php`.
- **Mail de invitación no se enviaba:** el envío de mails fue cambiado a `queue()` sin queue worker configurado en producción, por lo que los mails quedaban acumulados sin procesarse. Revertido a `send()` sincrónico. Incluye logging en el flujo de aceptación y null guard en `Invitation::isExpired()`.

---

## [0.8.11] — 2026-06-27

### Mejoras generales de UI — decimales, orden de columnas y navegación

#### Agregado

- **Buscador en índice de compras:** campo de texto que filtra por número de factura o nombre de proveedor en tiempo real (server-side). El filtro se combina con los filtros de proveedor y rango de fechas existentes.
- **Columnas ordenables en índice de compras:** click en encabezado de Fecha, N° Factura, Proveedor, Ítems o Total alterna entre ascendente/descendente. El orden se preserva al paginar o filtrar.
- **Columnas ordenables en detalle de receta:** las 4 secciones (Ingredientes, Mano de obra, Envases y Sub-recetas) tienen encabezados clicables que ordenan client-side con Alpine.js sin recargar la página.
- **N° Factura como link en índice de compras:** hacer click en el número de factura lleva directamente al detalle de la compra.
- **Volver al listado desde detalle de receta:** encabezado sticky con link "← Recetas" para volver al índice sin usar el botón atrás del navegador.
- **Paginación en español:** creado `lang/es/pagination.php` — los botones de paginación ahora muestran "Anterior" y "Siguiente" en lugar de las claves crudas `pagination.previous` / `pagination.next`.
- **Reorden de columnas en índice de compras:** el orden es ahora Fecha → N° Factura → Proveedor (antes Fecha → Proveedor → N° Factura).

#### Corregido

- **4 decimales en campos de costo y precio:** `step="0.0001"` en los inputs `type="number"` hacía que Chrome rellenara valores como `133.33` como `133.3300`. Cambiado a `step="0.01"` en todos los modales de ingredientes, envases y compras, y en la edición inline del detalle de compra. Los valores en pantalla (no editables) ya usaban `number_format(..., 2)`.

---

## [0.8.10] — 2026-06-27

### Compras — Mejoras al detalle de factura + Responsive mobile en todas las vistas

#### Agregado

- **Modal de edición de cabecera de compra:** desde la vista detalle de compra se puede editar el proveedor, fecha, número de factura, notas, alícuota IVA por defecto y percepción por defecto sin salir de la pantalla (`UpdatePurchaseRequest` + `PATCH /purchases/{id}`).
- **Banner de estado de vinculación:** en el detalle de compra aparece un banner con el progreso de renglones vinculados (ej. "2 de 5 renglones vinculados") y link directo a la pantalla de match cuando quedan pendientes.
- **Columna "Costo" y badge de estado por renglón:** cada renglón muestra el costo total calculado y un badge de estado — Aplicado (verde), Pendiente (ámbar) o Sin vincular (gris).
- **`tfoot` con totales de factura:** la tabla de renglones del detalle de compra muestra subtotal neto, IVA total, percepción total y total de la factura.
- **Formulario "Agregar renglón" en modal:** el formulario de alta de renglón se movió a un modal dedicado siguiendo el patrón CRUD del proyecto (antes era un formulario inline).
- **Tarjetas mobile en detalle de compra (`purchases/show`):** en mobile la tabla de renglones se reemplaza por tarjetas con descripción, cant./unidad unificadas ("2,5 kg"), precio unitario, total y badge de vinculación. Botones Editar/Eliminar debajo de cada tarjeta. Botón "Ver tabla completa" para expandir.
- **Tarjetas mobile en todas las vistas con tablas:** el patrón de tarjetas se extendió a las 8 vistas restantes de usuario — Tipos de mano de obra, Gastos fijos, Listas de precios, Mi equipo, Compras (índice), Ingredientes, Envases/Descartables, Recetas (índice) y Recetas (detalle, las 4 secciones de líneas). En mobile muestra cards con Alpine.js `mobileExpanded`; en desktop la tabla completa. Las cards de Recetas (detalle) conservan los inputs de cantidad/horas editables en tiempo real.

#### Corregido

- **Error 500 al aplicar sugerencias de IA en bloque:** `LazyLoadingViolationException` porque la relación `purchase` de cada `PurchaseLine` no estaba pre-cargada. Se resuelve asignando la relación directamente desde el objeto `$purchase` disponible en el controlador.
- **`checkDuplicate` excluye la compra actual al editar:** evita falsos positivos de factura duplicada cuando se guarda sin cambiar el número de factura.

---

## [0.8.9] — 2026-06-22

### Subdivisiones — corrección de cálculo de costo y doble precio en tabla

#### Corregido

- **Cálculo de costo con subdivisiones en creación/edición manual:** al crear o editar un ingrediente/envase con subdivisiones, el campo "Costo" que ingresa el usuario ahora se interpreta como el precio del envase completo y se divide por la cantidad de sub-unidades antes de guardarse en `cost_per_unit`. Antes, `cost_per_unit` almacenaba el precio del envase entero, haciendo que las recetas calcularan precios muy inflados (ej.: $240 × 6 galletas = $1.440 en vez de $60).

#### Agregado

- **Columna "Por envase" en tablas de ingredientes y envases:** las tablas en `/ingredients` y `/packaging` muestran ahora dos columnas de precio — "Por envase" (precio del pack completo) y "Costo / sub-unidad" (precio que usan las recetas). Para ítems sin subdivisiones se muestra un guión.
- **Hint dinámico en modales crear/editar:** cuando un ingrediente o envase tiene subdivisiones configuradas, el campo de costo muestra la etiqueta "Costo por envase" y un hint reactivo "≈ $X / [sub-unidad]" calculado en tiempo real con Alpine.js.
- **Comando `php artisan ingredients:fix-subdivision-costs`:** permite corregir ingredientes y envases existentes que tenían subdivisiones configuradas antes de este fix. Muestra una tabla de previsualización con el precio actual y el precio calculado por sub-unidad, pide confirmación global y propaga los cambios de costo a las recetas afectadas.

#### Técnico

- Migración: `add_cost_per_package_to_ingredients_and_packagings_tables` — agrega `cost_per_package` (DECIMAL 10,4 nullable) a `ingredients` y `packagings`.
- `IngredientController` y `PackagingController`: en `store()` y `update()`, si el ítem tiene subdivisiones, guarda `cost_per_package = precio_ingresado` y `cost_per_unit = precio_ingresado / subdivisions`.
- `PurchaseLineRecorder::apply()`: ídem al aplicar una compra — guarda `cost_per_package` antes de dividir.
- 4 nuevos tests en `IngredientCrudTest` cubriendo la división correcta, limpieza de `cost_per_package` al quitar subdivisiones y no-efectos en ingredientes sin subdivisiones.

---

## [0.8.8] — 2026-06-18

### Subdivisiones para envases e ingredientes por unidad

#### Agregado

- **Subdivisiones en ingredientes con unidad `u`:** al registrar un ingrediente vendido por unidad (ej.: paquete de galletas), se puede indicar cuántas unidades trae la presentación ("Unidades por envase") y cómo se llama la sub-unidad ("galleta", "rebanada"). El campo `cost_per_unit` almacena el precio por sub-unidad.
- **Subdivisiones en envases/descartables:** los envases soportan ahora el mismo mecanismo. Al registrar un envase que viene en pack (ej.: caja de 100 bolsas kraft), se indica la cantidad por presentación y el nombre de la sub-unidad ("bolsa", "etiqueta"). La tabla de envases muestra "100 bolsa / presentación" debajo del nombre cuando tiene subdivisiones configuradas.
- **Vista de matcheo de compras:** la columna de ingredientes en `/purchases/{id}/match` muestra la info de subdivisión al vincular un renglón de factura, para que sea visible cuántas sub-unidades trae cada presentación.

#### Corregido

- **`cost_per_unit` almacena precio por sub-unidad:** el campo refleja el costo de una sola sub-unidad (ej.: $1 por galleta), no el precio del paquete completo.
- **Modal de crear/editar envase — proveedor nuevo:** el botón "+ Nuevo" ya no cierra el modal del envase antes de abrir el quick-create de proveedor. Ahora abre el quick-create encima (z-index superior), y al crear el proveedor el select se actualiza automáticamente con la nueva opción seleccionada, replicando el comportamiento del modal de ingredientes.

#### Técnico

- Migraciones: `add_subdivisions_to_ingredients_table` y `add_subdivisions_to_packagings_table` (`subdivisions` UNSIGNED INT nullable, `subdivision_label` VARCHAR 50 nullable).
- `StoreIngredientRequest`, `UpdateIngredientRequest`, `StorePackagingRequest` y `UpdatePackagingRequest` validados con `nullable|integer|min:2` y `nullable|string|max:50`.
- 4 nuevos tests en `PackagingCrudTest`: crear con subdivisiones, crear sin subdivisiones (null), editar agregando subdivisiones y validar mínimo de 2.

---

## [0.8.7] — 2026-06-18

### Compras — Control de IVA y percepciones por factura

#### Agregado

- **Control de IVA a nivel de factura:** al registrar una compra (modal de nueva compra y revisión de factura escaneada) se puede establecer la alícuota IVA de toda la factura — Sin IVA / 10,5% / 21%. El valor se guarda en `purchases.default_iva_rate` y pre-rellena el selector de IVA en cada línea que se agregue manualmente desde el detalle de la compra.
- **Percepciones por línea:** cuando la factura tiene IVA, se puede activar una percepción de porcentaje libre (ej. 3,5%). Se almacena en `purchase_lines.percepcion_rate` (decimal %) y `purchases.default_percepcion_rate`. El cálculo es `percepcion_$ = subtotal_neto × percepcion% / 100`.
- **"Aplicar a todos los renglones" en el escaneo:** el panel de IVA/percepciones de la vista de revisión de factura escaneada incluye un botón que distribuye los valores seleccionados a todas las filas de la tabla vía Alpine.js (`@apply-tax-defaults`). Cada fila también es editable individualmente.
- **Columna Percepción $ en tablas:** la tabla de renglones en el detalle de compra (`purchases/show`) y en la revisión de escaneo ahora muestran columnas separadas para IVA $, Percepción $ y Total; reemplazando la anterior columna "Subtotal c/IVA".
- **Modal editar línea:** incluye el campo Percepción % con cálculo reactivo en tiempo real (muestra IVA $, Percepción $ y Total c/IVA+Perc.).

#### Técnico

- Migraciones: `add_percepcion_rate_to_purchase_lines_table` (DECIMAL 5,2) y `add_tax_defaults_to_purchases_table` (`default_iva_rate` DECIMAL 5,4, `default_percepcion_rate` DECIMAL 5,2).
- `PurchaseLineRecorder::storePending()` y `recompute()` persisten `percepcion_rate`.
- `StorePurchaseRequest`, `StorePurchaseLineRequest`, `UpdatePurchaseLineRequest` y `StoreScannedPurchaseRequest` validados con las nuevas reglas.

---

## [0.8.6] — 2026-06-18

### UX — Mejoras de interfaz: feedback visual, íconos y tooltips

#### Agregado

- **Flash messages en el layout:** los mensajes de éxito y error aparecen como toast fijo en la esquina superior derecha (`fixed top-4 right-4 z-50`) y se auto-cierran a los 4 segundos con transición de opacidad. Se eliminaron los bloques flash inline de cada vista; ahora se usa el componente `<x-flash-messages>` en el layout.
- **Loading state en modales:** todos los botones "Guardar" de los modales de create/edit tienen `data-loading="Guardando…"` que activa el handler global de `app.js` para prevenir doble-submit y mostrar feedback durante el POST.
- **Íconos en acciones de tablas:** las acciones de cada fila (editar, activar/desactivar) se migraron a botones icon-only con SVG Heroicons, `aria-label` y `title` para tooltips nativos.
- **Componentes `<x-status-badge>` y `<x-empty-state>`:** eliminan duplicación de los patrones de badge activo/inactivo y mensaje de lista vacía en las vistas index.
- **Favicon:** se agregaron `favicon.ico` y `favicon.svg` con la identidad de Levado, referenciados en ambos layouts (`app.blade.php` y `admin.blade.php`).

#### Corregido

- **Ícono "Desactivar":** reemplaza el eye-slash (asociado a "ocultar") por el símbolo de prohibición ⊘ (`no-symbol` de Heroicons) — significado más claro para desactivar un registro.
- **Color del botón desactivar:** ahora es `text-red-500` siempre visible (antes solo aparecía en hover).
- **Ícono "Ver detalle" en Recetas y Compras:** reemplaza el ojo por el ícono de lápiz para coherencia con el resto de las vistas.
- **Tooltips en todos los botones icon-only:** atributo `title` nativo en todos los botones de acción (se eligió `title` nativo sobre CSS para evitar recorte por `overflow-x-auto`).

#### Técnico / Refactor

- **Auditoría best-practices Laravel (fases 1–3):** corrección de N+1 queries, casts Eloquent, índices en migraciones, extracción de JS a módulo Vite (`price-editor.js`), `LazilyRefreshDatabase` en tests de feature.
- **Policies y scopes de autorización:** clases Policy para los modelos principales; scope `scopeActive()`; reemplazo de `abort_unless()` por autorización de policy.

---

## [0.8.5] — 2026-06-15

### Fix — Compras: botón aplicar sugerencias, decimales y dropdown de producto

#### Corregido

- **Botón "Aplicar sugerencias de IA":** el bulk apply fallaba silenciosamente cuando los renglones sugeridos tenían unidad de compra `u` e ingrediente en `kg`/`gr`. Ahora `PurchaseLineRecorder::apply()` intenta parsear la descripción del renglón (patrón "X 25 Kg", "x5lts", etc.) para derivar la cantidad por unidad, replicando la lógica del frontend. Solo requiere intervención manual cuando la descripción tampoco tiene cantidad. El mensaje de feedback distingue entre líneas aplicadas, salteadas por unidades y errores inesperados.
- **Decimales del cálculo de costo unitario:** la división entre precio y factor de conversión producía floats con 12+ decimales en el input visible. Los tres puntos de asignación de `unitCost` en el JS ahora redondean a 2 decimales (`Math.round(x * 100) / 100`). El input usa `step="0.01"` y `data-maxdecimals="2"`.
- **Dropdown de producto atrapado en scroll:** TomSelect se inicializa con `dropdownParent: 'body'`, evitando que el `overflow-x-auto` del contenedor de la tabla recorte el dropdown cuando hay pocos productos.

#### UX

- Menú lateral y mobile nav: "Gastos Fijos" renombrado a "Gastos".

---

## [0.8.4] — 2026-06-15

### Recetas — Selector de lista de precios en el detalle de receta

#### Agregado

- **Selector de lista de precios en el sidebar de `/recipes/{id}`:** cuando el tenant tiene más de una lista activa, aparece un dropdown junto al título "Precio de venta" que permite cambiar de lista sin salir de la receta. Al cambiar la lista, el campo de precio y la barra de margen se actualizan reactivamente con el precio guardado en esa lista.
- **Guardado AJAX por lista:** el botón "Guardar precio" (antes submit de formulario HTML) ahora llama a `PATCH /recipes/{id}/prices/{priceList}` y muestra estado "Guardando…" mientras espera. No hay recarga de página.

#### Técnico
- `RecipeController::show()` carga todas las listas activas y los precios de la receta en todas ellas (`$priceLists`, `$allPrices`).
- `$defaultPrice` se deriva de `$allPrices` en lugar de una query extra.
- Alpine.js: nuevos datos `priceLists`, `allPrices`, `selectedListId`, `savingPrice`; nuevos métodos `changeList()` y `savePrice()`.

---

## [0.8.3] — 2026-06-15

### Fix — Inconsistencia en costo/u entre dashboard y detalle de receta

#### Corregido

- **`cost_per_unit` sin overhead en el dashboard y endpoint de precios:** el dashboard de rentabilidad y la respuesta AJAX de edición inline de precios calculaban el costo por unidad usando solo costos directos (ingredientes + mano de obra + packaging + subrecetas), omitiendo los gastos fijos prorrateados por horas. La vista de detalle de receta sí los incluía, generando valores distintos entre las dos pantallas.
- **Solución:** `RecipeCostCalculator::calculate()` ahora devuelve también `total_labor_hours`. `DashboardController` y `RecipePriceController` suman `fixedCost = labor_hours × overhead_per_hour` antes de derivar `cost_per_unit` y márgenes, igualando el criterio de la vista de receta.

---

## [0.8.2] — 2026-06-15

### Refactor — Calidad interna del módulo de listas de precios

#### Mejorado

- **`RecipePriceWriter::set()` sin lazy load innecesario:** el log de precio usaba `$recipePrice->recipe->priceLogs()` cargando la relación desde el modelo intermedio cuando `$recipe` ya estaba disponible como parámetro. Ahora usa `$recipe->priceLogs()` directamente, eliminando 1 SELECT por receta en operaciones bulk.
- **Transacciones en aplicación masiva de sugerencias:** `applySuggestions()` y `applyAllSuggestions()` envuelven sus loops de escritura en `DB::transaction()`. Si alguna escritura falla a mitad de camino, ningún precio queda persistido parcialmente.

---

## [0.8.1] — 2026-06-15

### Listas de precios — Aplicación masiva de sugerencias + selector en recetas

#### Agregado

- **Botón "Aplicar sugerencias" por lista (`/price-lists`):** en la columna de acciones de cada lista no-base con % de ajuste activa, aplica de una sola vez el precio sugerido a todas las recetas que todavía no tienen precio en esa lista. No sobreescribe precios ya cargados.
- **Botón "Aplicar sugerencias" en la matriz (`/price-lists/matrix`):** aplica las sugerencias pendientes en todas las listas con % de ajuste en un único click. Redirige con el conteo de precios aplicados.
- **Selector de lista de precios en el listado de recetas (`/recipes`):** dropdown en el formulario de filtros (visible cuando hay más de una lista activa) que recarga la tabla mostrando los precios de la lista elegida. La columna de precio cambia de nombre, el ordenamiento y la edición inline operan sobre la lista seleccionada. El selector se preserva al buscar y filtrar.

#### Técnico
- Suite completa: **270 tests**, todos verdes

---

## [0.8.0] — 2026-06-12

### Listas de precios

#### Agregado

- **Listas de precios por tenant (`/price-lists`):** cada negocio define sus propias listas (ej. Mostrador, Mayorista, Cafeterías) con CRUD en modales. Cada lista puede tener un **% de ajuste opcional** sobre la lista base que pre-llena precios sugeridos.
- **Lista "General" (base):** se crea automáticamente por tenant (lazy vía `Tenant::defaultPriceList()`) y absorbe los `selling_price` existentes en la migración de datos. No se puede desactivar y no acepta % de ajuste.
- **Precios por receta y lista (`recipe_prices`):** un monto fijo por receta en cada lista, con histórico de cambios en `recipe_price_logs` (mismo patrón que los price logs de insumos).
- **Matriz de precios (`/price-lists/matrix`):** vista comparativa receta × lista con costo/u de referencia, edición inline por celda, margen % con semáforo y precios sugeridos en gris para celdas vacías (se confirman con un click + Enter).
- **Selector de lista en el dashboard de rentabilidad:** permite ver márgenes y semáforo según cualquier lista activa; por defecto muestra la lista base.
- **Endpoint `PATCH /recipes/{recipe}/prices/{priceList}`:** reemplaza a `PATCH /recipes/{recipe}/selling-price` y devuelve el mismo shape JSON (precio + margen formateado + color).

#### Cambiado

- **`recipes.selling_price` eliminada:** `recipe_prices` es la única fuente de verdad del precio de venta. Los formularios de receta (crear, editar, precio en detalle) siguen mostrando el campo, que ahora escribe en la lista base. Copiar una receta duplica sus precios en todas las listas.

---

## [0.7.3] — 2026-06-09

### Compras — Selects con buscador y límite de decimales en inputs

#### Agregado

- **Tom Select en selects de compras:** todos los selects del módulo de compras cuentan ahora con campo de búsqueda integrado para agilizar la carga:
  - Selector de proveedor en el modal "Nueva compra" y en la pantalla de revisión de factura escaneada.
  - Selector de insumo/descartable en la vista de vinculación (`/purchases/{id}/match`), donde la lista puede tener cientos de opciones.
- **Límite de 4 decimales en inputs de precio y cantidad:** todos los inputs numéricos de los formularios de compra (alta de renglón, edición de renglón, revisión de factura escaneada y campo de costo unitario en la vista match) limitan la entrada a un máximo de 4 lugares decimales en tiempo real. `step` también actualizado a `0.0001` para mantener consistencia.

#### Cambiado

- **Vista revisión de factura (`scan/review`):** el select de proveedor migró de renderizado dinámico con Alpine `x-for` a opciones server-side (`@foreach`), lo que permite inicializar Tom Select correctamente. La integración con el evento `supplier-created` (alta rápida de proveedor) se mantiene vía la API de Tom Select (`addOption` + `setValue`).
- **`resources/css/app.css`:** importa el CSS de Tom Select con overrides de estilos para que los dropdowns de búsqueda sean coherentes con el diseño Tailwind (colores `corteza`, `horno`, `miga`).
- **`resources/js/app.js`:** importa y expone `TomSelect` como `window.TomSelect`; inicializa automáticamente los selects con `data-searchable`; registra listener global para limitar decimales en inputs con `data-maxdecimals="4"`.

---

## [0.7.2] — 2026-06-09

### Compras — Vinculación de renglones con catálogo (Fase 2)

#### Agregado

- **Vista dedicada `/purchases/{id}/match`:** pantalla exclusiva para vincular los renglones de una factura con insumos o descartables del catálogo. Separada del detalle de compra para mantener cada vista enfocada en una sola tarea.
- **Botón de vinculación en el índice de compras:** ícono de cadena (link) por fila, visible solo cuando la compra tiene renglones. Ámbar cuando hay renglones pendientes de vincular; verde cuando todos están aplicados. El `title` muestra cuántos renglones quedan pendientes.
- **Cálculo reactivo del costo unitario (Alpine.js):** al seleccionar un insumo en la vista de match, se calcula y muestra el costo por unidad del catálogo antes de aplicar.
  - Unidades compatibles (kg↔gr, L↔ml, etc.): conversión automática, sin campo adicional.
  - Unidades incompatibles (u → kg/gr/L): aparece campo divisor editable ("÷ N unidad/u").
  - El campo costo por unidad es siempre editable antes de confirmar.
- **Parser de descripción para cantidad de paquete:** al seleccionar un insumo con unidades incompatibles, el sistema analiza la descripción del renglón buscando patrones como "X 25 Kg", "x5lts", "× 200 ml". Si detecta una cantidad compatible con el insumo, pre-llena el divisor automáticamente con marca ✦ (ámbar) para que el usuario lo verifique antes de aplicar.
- **`PurchaseLineRecorder::applyWithCost()`:** nuevo método que aplica un costo unitario explícito (provisto por el usuario) sin pasar por la conversión de unidades. Permite imputar compras donde la unidad de la factura (unidad/bolsa) es incompatible con la unidad del insumo (kg/gr).
- **`applied_count` en el índice de compras:** el query de listado ahora incluye el conteo de renglones ya aplicados vía `withCount`, usado para determinar el color del botón de vinculación sin queries adicionales.

#### Cambiado

- **`PurchaseController::matchLine()`:** acepta campo opcional `unit_cost` (decimal). Si está presente, usa `applyWithCost()` en lugar de `apply()`, permitiendo aplicar costos calculados por el usuario para unidades incompatibles.
- **Vista de match:** columna "Cantidad (factura)" eliminada. El foco es la descripción del producto, el precio unitario de la factura y el insumo sugerido con su costo editable.
- **Select de insumos en vista de match:** muestra la unidad del catálogo entre paréntesis (ej: "Harina 000 (kg)") para que el usuario pueda identificar rápidamente la unidad de medida antes de vincular.

---

## [0.7.1] — 2026-06-09

### Compras — IVA por renglón, iconos de acción y fixes

#### Agregado

- **Columna `iva_rate` en `purchase_lines`:** alícuota de IVA (0%, 10,5% ó 21%) almacenada por renglón. Se persiste en todos los flujos: escaneo de factura, alta manual y edición.
- **Discriminación de IVA en vista detalle de compra (`/purchases/{id}`):** columnas "IVA $" y "Subtotal c/IVA" calculadas server-side desde el `iva_rate` guardado. El selector de alícuota está disponible en el modal de edición y en el formulario de alta manual.
- **Selector de alícuota IVA en revisión de factura escaneada:** la pantalla de revisión muestra el select de alícuota por renglón; el valor elegido se guarda junto con la compra.
- **Iconos de acción en tablas de compras:** reemplaza los textos "Editar", "Eliminar" y "Ver detalle →" por iconos SVG (lápiz, papelera, ojo) con `title` para tooltip y área de click con `p-1`.
- **Botón "Eliminar compra" en el índice:** ícono de papelera con confirmación; borra la compra, sus renglones (cascade FK) y la imagen de factura del storage.
- **Modal quick-create de proveedor en revisión de factura:** cuando la IA detecta un proveedor que no existe en el catálogo, aparece el botón "creálo acá" que abre el modal sin salir del formulario. Al crear el proveedor, el select se actualiza y lo selecciona automáticamente vía evento `supplier-created`.

#### Corregido

- **Overflow en `subtotal` y `unit_price` de `purchase_lines`:** `decimal(10,4)` (máx. $999.999) reemplazado por `decimal(14,4)` para soportar subtotales superiores a $1.000.000 (ej.: 200 bolsas × $13.891,40 = $2.778.280).

---

## [0.7.0] — 2026-06-08

### Módulo de Compras — Registro de facturas de proveedores

#### Agregado

**Módulo de compras**
- Tabla `purchases`: cabecera de factura (tenant, proveedor, N° de factura opcional, fecha, notas)
- Tabla `purchase_lines`: líneas de compra con tipo (ingrediente / envase), insumo, cantidad, unidad de compra, precio unitario y subtotal calculado
- `PurchaseController`: index con filtros (proveedor, rango de fechas), show con líneas, store, destroy (solo si sin líneas), storeLine, updateLine, destroyLine
- 3 Form Requests con validación (`StorePurchaseRequest`, `StorePurchaseLineRequest`, `UpdatePurchaseLineRequest`)
- Modelo `Purchase` con relaciones a Tenant, Supplier y PurchaseLine; helper `totalAmount()`
- Modelo `PurchaseLine` con helpers `isIngredient()` / `isPackaging()` y relaciones a Ingredient y Packaging

**Actualización automática de costos al cargar una factura**
- Al agregar o editar una línea de compra, el costo del ingrediente/envase se actualiza inmediatamente
- Conversión automática de unidades: si comprás 1 kg a $500/kg y el ingrediente se mide en gr, el sistema calcula y persiste $0.50/gr usando `UnitConverter`
- Se crea automáticamente una entrada en `ingredient_price_logs` / `packaging_price_logs`
- `RecipeCostPropagator` dispara recálculo en cascada de todas las recetas que usan el insumo actualizado
- Acción registrada en `AdminAuditLog`

**Vistas**
- `purchases/index.blade.php`: listado paginado con filtros por proveedor y rango de fechas
- `purchases/show.blade.php`: detalle con header de compra, tabla de ítems, formulario inline para agregar ítems (con selects dinámicos ingrediente/envase vía Alpine.js), modal de edición de línea
- Creación rápida de proveedor inline desde el modal de nueva compra (patrón quick-create existente)
- Botón "← Volver a compras" visible solo en mobile en la vista de detalle

**Navegación**
- "Compras" agregado al sidebar desktop (sección Costos, debajo de Mano de Obra)
- Barra inferior mobile: "Gastos" reemplazado por "Compras" (ícono carrito)
- "Gastos Fijos" movido al drawer "Más"
- Breadcrumbs actualizados para `purchases.*` y `purchases.show`

---

## [0.6.5] — 2026-06-08

### Mobile — Barra de navegación inferior + fixes responsive

#### Agregado

- **Bottom navigation bar en mobile:** barra fija en el fondo de pantalla con los accesos directos a Inicio, Recetas, Ingredientes y Gastos Fijos. Reemplaza el menú hamburguesa del top nav en pantallas < 640px, dando una experiencia de app nativa.
- **Drawer "Más":** panel deslizable desde abajo que agrupa el resto de la navegación (Envases, Mano de Obra, Proveedores, Negocio, Mi equipo, Sucursales) con overlay oscuro y cierre al tocar afuera. Respeta los permisos `@can` del sidebar.

#### Corregido

- **Tablas en Mi equipo sin scroll horizontal:** ambas tablas (invitaciones pendientes y miembros) envueltas con `overflow-x-auto` para no romper el layout en mobile.
- **Formulario de invitación:** cambia de `flex-row` a `flex-col` en mobile para que los campos no queden comprimidos.
- **Modal sin ancho completo en mobile:** el componente `modal.blade.php` ahora aplica `w-full` en mobile (antes solo lo hacía en `sm:`).
- **Bloque capacidad productiva en Mi negocio:** colapsa a columna vertical en mobile (`flex-col sm:flex-row`).
- **Botones del header de receta:** texto adaptativo con `whitespace-nowrap` para que no se deformen. En mobile muestran etiquetas cortas ("precio", "Desact.", "Editar") y el nombre de la receta se trunca con `truncate` cediendo espacio a los botones.

---

## [0.6.4] — 2026-06-05

### UX — Ordenamiento completo en tablas + edición inline de precio de venta

#### Agregado

- **Ordenamiento asc/desc en todas las datatables:** Ingredientes, Envases, Gastos fijos, Mano de obra, Recetas y Proveedores. Las columnas sortables se marcan con ↑/↓ y preservan el filtro/búsqueda activos.
- **Búsqueda en el dashboard:** el listado de rentabilidad acepta `?search=` por nombre de receta, consistente con el resto de los módulos.
- **Ordenamiento de columnas calculadas en el dashboard:** Costo/u, Margen y Margen % ahora son ordenables. Al ser valores calculados en PHP (no columnas SQL), el controlador carga todas las recetas activas, computa los costos, ordena la colección en memoria (nulls siempre al final) y pagina manualmente con `LengthAwarePaginator`.
- **Precio de venta editable inline en el dashboard:** clic sobre el precio abre un input; Enter o blur guardan vía `PATCH /recipes/{id}/selling-price`. El margen y margen % de la fila se actualizan reactivamente sin recargar la página.
- **Precio de venta editable inline en el listado de recetas:** misma UX que el dashboard; columna "Precio venta / u" visible y editable directamente desde el índice.
- **Recetas inactivas ocultas del dashboard:** solo recetas con `active = true` aparecen en la tabla de rentabilidad; el índice de recetas las sigue mostrando al final.

#### Corregido

- **Super admin bloqueado al impersonar:** `CheckTenantRole` ahora cortocircuita el chequeo de rol cuando el usuario es super admin con sesión de impersonación activa, permitiendo acceder a rutas de owner (`/business`, etc.) sin necesitar entrada en `tenant_users`.
- **Input de precio quedaba en blanco sin cambios:** eliminado `x-model` del input numérico (bug conocido de Alpine.js v3 con `type="number"`). Ahora se usa `x-ref` + lectura imperativa del DOM; `isDirty` evita enviar la petición si el usuario no modificó el valor.

---

## [0.6.3] — 2026-05-28

### UX — Tablas con búsqueda, paginación y edición mejorada

#### Agregado

- **Búsqueda y filtro de estado** en todas las secciones del módulo de costos: Recetas, Ingredientes, Mano de obra, Envases, Gastos fijos y Proveedores. Cada listado acepta `?search=` (nombre) y `?status=active|inactive`.
- **Paginación de 20 ítems** con `withQueryString()` en los seis módulos; el pie muestra el total real con `->total()`.
- **Botón "Copiar" en recetas:** clona la receta (nombre + `(copia)`, inactiva por defecto) con todos sus renglones (ingredientes, mano de obra, envases y sub-recetas) y recalcula el costo. Disponible en el listado y en el detalle.
- **Header sticky en detalle de receta:** el bloque con nombre, Desactivar/Activar, Copiar, Editar info y Guardar precio queda fijo al hacer scroll.
- **Botón "Guardar precio" en el header** de la receta, asociado al formulario del sidebar vía atributo HTML `form=`; no requiere JavaScript adicional.
- **Nombre clickeable** en las tablas de Ingredientes, Envases, Mano de obra, Gastos fijos y Proveedores: abre el modal de edición directamente.

#### Corregido

- **Super admin sin permisos al impersonar:** `Gate::before()` ahora devuelve `true` para usuarios con `isSuperAdmin()`, permitiendo que el admin vea y use todos los controles de edición al operar como cualquier tenant.
- **"Volver al admin" en banner de impersonación:** eliminado. El banner muestra únicamente "Salir de impersonación →".
- **Scroll horizontal en detalle de receta:** las tarjetas de Ingredientes, Mano de obra, Envases y Sub-recetas cambian de `overflow-hidden` a `overflow-x-auto`; el header usa `flex-wrap` para no desbordarse en móvil.

---

## [0.6.2] — 2026-05-27

### Fix — Impersonación y acceso al backoffice

#### Corregido

- **Impersonación desde el detalle de tenant:** el botón "Operar como este tenant" redirigía correctamente pero la ruta de stop-impersonate estaba mal ordenada respecto al middleware `super-admin`, resultando en 403 intermitente. Corregido con reordenamiento y botón de acceso rápido en el listado de tenants.

---

## [0.6.1] — 2026-05-26

### Seguridad — Correcciones identificadas en code review

#### Corregido

**Críticos**
- **Escalada de privilegios vía invitación de equipo:** la validación del campo `role` aceptaba `super_admin`, permitiendo que un owner o admin creara un super admin del sistema invitando a un usuario con ese rol. Ahora solo se permiten `owner`, `admin` y `viewer`.
- **Doble hashing de contraseña al aceptar invitación:** se usaba `bcrypt()` explícito junto al cast `hashed` del modelo `User`, generando un doble hash que impedía el login a todo usuario nuevo registrado por invitación.

**Altos**
- **IDOR en cancelación de invitaciones:** `InvitationController::destroy` no verificaba que la invitación perteneciera al tenant actual, permitiendo que un admin de otro tenant la eliminara por ID. Corregido con `abort_unless` de pertenencia.
- **Rutas de gestión de equipo siempre retornaban 404:** `TenantUser` tenía `$primaryKey = null` sin columna `id`, lo que rompía el route model binding silenciosamente. Se agrega columna `id` autoincremental mediante migración de recreación de tabla.
- **Falta de verificación de tenant en `TeamController`:** los métodos `updateRole`, `deactivate` y `activate` aceptaban un `TenantUser` de cualquier tenant vía route binding sin verificar pertenencia. Corregido con `abort_unless` en los tres métodos.

**Medios**
- **Sin rate limiting en reset de contraseña:** las rutas `POST /forgot-password` y `POST /reset-password` carecían de throttle, exponiéndolas a flooding de emails. Se agrega `throttle:6,1` en ambas.
- **`AcceptInvitationRequest` pedía name/password incondicionalmente:** los usuarios existentes que aceptaban una invitación recibían errores de validación porque el formulario no mostraba esos campos para ellos. La validación ahora es condicional según si el usuario ya tiene cuenta.

**Bajos**
- **Wildcard injection en búsquedas de backoffice:** los términos `%` y `_` en el campo de búsqueda de tenants y usuarios pasaban sin escapar a la cláusula `LIKE`, permitiendo queries más costosas. Los wildcards ahora se escapan antes del binding.
- **Filtros de fecha sin validar en logs de auditoría:** los parámetros `from` y `to` se pasaban directamente a `whereDate()` sin verificar formato, pudiendo generar errores de base de datos con valores inválidos.

#### Técnico
- Suite completa: **216 tests**, todos verdes

---

## [0.6.0] — 2026-05-26

### Semi-elaboraciones — Recetas como ingrediente de otras recetas

#### Agregado

**Semi-elaboraciones**
- Flag `is_semi_elaborate` en recetas: al marcarlo, la receta puede usarse como componente de costo dentro de otras recetas
- Tabla `recipe_subrecipe_lines`: `recipe_id` (padre), `child_recipe_id` (semi-elaboración), `quantity_used`, `unit`, `cost_calculated`
- Sección "Sub-recetas" en el editor de receta con la misma UX que ingredientes: selector, cantidad inline editable, costo calculado en tiempo real
- Modal "Agregar sub-receta" con filtro de unidades compatible con el rendimiento de la semi (Alpine.js)
- Badge "semi" visible en el listado de recetas
- Checkbox "Es una semi-elaboración" en los modales de crear y editar receta

**Motor de costos (4.º término)**
- `RecipeCostCalculator` suma un cuarto término: `Σ convert(quantity_used → child.yield_unit) × child.unit_cost`
- El padre lee el `unit_cost` ya persistido del hijo — sin recursión

**Propagación de costos (síncrona)**
- `recipes.unit_cost` (decimal 10,4): costo unitario cacheado, escrito automáticamente por el propagador
- Nuevo servicio `RecipeCostPropagator`: al mutar cualquier línea de una receta, recalcula su `unit_cost` y luego BFS hacia arriba propagando a todas las recetas padres en orden topológico
- Triggers en `IngredientController`, `PackagingController` y `LaborTypeController`: cambio de precio → propaga a todas las recetas que usan ese recurso y sus cadenas de padres

**Validaciones e integridad**
- Detección de ciclos: `isAncestor()` hace BFS hacia arriba antes de insertar una nueva sub-receta; bloquea con error de validación si generaría un ciclo (DAG garantizado)
- Solo recetas con `is_semi_elaborate = true` y activas pueden usarse como sub-receta (validado server-side y filtrado en el dropdown)
- Baja lógica bloqueada: no se puede desactivar una semi-elaboración que está siendo usada por recetas activas; el error lista los nombres de las recetas bloqueantes

#### Técnico
- 29 nuevos tests en 4 archivos: `RecipeSubrecipeLineTest`, `RecipeToggleActiveGuardTest`, `RecipeCostPropagatorTest`, `RecipeCostCalculatorSubrecipeTest`
- Suite completa: **216 tests**, todos verdes

---

## [0.5.1] — 2026-05-26

### Fix — Unidad incompatible al agregar ingrediente en receta

#### Corregido

- **Root cause**: al seleccionar un ingrediente con unidad de volumen (ej. agua en ml) y enviar el formulario con una unidad de peso (kg), el backend lanzaba `abort_unless()` con código 422, mostrando la pantalla de error técnica de Laravel en lugar de un mensaje amigable.
- `storeIngredientLine()` reemplaza `abort_unless()` por `ValidationException::withMessages()`: el error ahora aparece debajo del campo "Unidad" dentro del mismo modal.
- El dropdown de unidades se filtra dinámicamente con Alpine.js al seleccionar un ingrediente: solo muestra las unidades del mismo grupo de compatibilidad (peso: gr/kg · volumen: ml/L/cc · unidad: u).

---

## [0.5.0] — 2026-05-19

### Onboarding tour + Receta rediseñada + UX

#### Agregado

**Onboarding tour (Shepherd.js)**
- Tour guiado de 5 pasos para tenants nuevos: Mi negocio → Gastos fijos → Mano de obra → Insumos → Primera receta
- `onboarding_completed_at` en `tenants`; se marca automáticamente al crear la primera receta
- `ViewComposer` calcula el paso activo desde el estado de la base de datos (sin wizard bloqueante)
- `window.levadoOnboarding` inyectado en `<head>` cuando el tour está activo; Alpine.js queda libre
- 12 tests de step computation, tracking de completion y aislamiento

**Detalle de receta rediseñado**
- Layout dos columnas: tablas de líneas (ingredientes / mano de obra / envases) + sidebar de costos fijo (`sticky top-4`)
- Edición de cantidades inline (spinner) con guardado automático vía PATCH (`/recipes/{id}/…-lines/{line}`)
- Cálculo de costos en tiempo real con Alpine.js: ingredientes, mano de obra, envases, gastos fijos proporcionales
- Simulador de margen: slider 0–80 % con precio sugerido y barra de color (verde / ámbar / rojo)
- Endpoints PATCH para `ingredient-lines`, `packaging-lines` y `labor-lines`

#### Mejorado

- **Modales apilados:** el modal de proveedor (quick-create) aparece sobre el modal de ingrediente sin cerrarlo; z-index vía `style` inline para evitar limitaciones de purge de Tailwind
- **Gastos fijos:** creación de categoría inline dentro del modal de nuevo/editar gasto, sin salir del flujo
- **Mi negocio:** sección "Capacidad productiva" reubicada debajo de los datos del negocio y fiscales
- **Navbar:** logo SVG de Levado como fallback cuando el tenant no tiene logo subido; logo ocupa todo el bloque de marca

#### Técnico
- `FixedCostCategoryController::store()` y `SupplierController::store()` retornan JSON cuando `Accept: application/json`
- `modal.blade.php` y `crud-modal.blade.php` reciben prop `z` (int) para z-index configurable
- Suite completa: **173 tests**, todos verdes

---

## [0.4.1] — 2026-05-22

### Fix — Onboarding tour no arrancaba en el dashboard

#### Corregido

- **Root cause**: `productive_hours_month` tenía `default(160)` en la BD, por lo que el backend calculaba siempre `step ≥ 1` y el bloque JS del dashboard (que solo escuchaba `step === 0`) nunca disparaba el tour.
- `productive_hours_month` pasa a nullable sin default; los tenants nuevos arrancan con `null` y caen en step 0. Migración incluida. Tenants existentes conservan su valor.
- `AppServiceProvider`: condición `=== 0` reemplazada por `!$productive_hours_month` (cubre `null` y `0`).
- `onboarding-tour.js`: el bloque del dashboard ahora cubre cualquier step pendiente (0–4) con título, texto y sidebar de destino apropiados para cada uno.
- Admin panel (crear tenant): campo de horas productivas pasa a ser opcional; el tenant lo completa durante el onboarding.

---

## [0.4.0] — 2026-05-18

### Etapas 2.4–2.7 completas — Módulo de Costos (cierre) + Recetas + Dashboard

#### Agregado

**Etapa 2.4 — Tipos de Mano de Obra**
- Tabla `labor_types` por tenant: nombre y costo por hora
- CRUD completo con toggle active; Gate `manage-costs` (owner+admin escriben, viewer lee)
- 10 tests de roles, validación y aislamiento

**Etapa 2.5 — UnitConverter**
- Servicio `App\Services\UnitConverter` para conversión entre unidades compatibles
- Dimensiones: peso (gr / kg), volumen (ml / L / cc), unidad (u)
- `convert()` retorna `null` para unidades incompatibles; `compatible()` para validar
- 16 tests unitarios

**Etapa 2.6 — Recetas**
- 4 tablas: `recipes`, `recipe_ingredient_lines` (cantidad + unidad), `recipe_packaging_lines`, `recipe_labor_lines`
- `RecipeController`: index, store, show, update, toggleActive + 6 métodos de líneas (store/destroy × 3 tipos)
- Cálculo de costo en tiempo real: UnitConverter convierte unidades de ingredientes al agregar líneas
- Vista detalle: resumen de costos (ingredientes / envases / mano de obra / total), desglose por sección, botones para agregar/quitar líneas
- `selling_price` (nullable) en recetas; campo en modales de crear y editar
- Sección "Recetas" en sidebar context-aware y top nav
- 22 tests de CRUD, roles, líneas, conversión de unidades y aislamiento

**Etapa 2.7 — Dashboard de Rentabilidad**
- `RecipeCostCalculator`: servicio reutilizable que encapsula el cálculo de costos por receta
- Dashboard principal reemplaza el placeholder: tabla de recetas activas con costo/u, precio de venta, margen $ y margen % (semáforo: verde ≥ 30 % / amarillo 15–29 % / rojo < 15 %)
- Tarjetas de resumen: recetas activas, total gastos fijos/mes, horas productivas/mes, overhead/hora
- Link directo a configurar horas productivas cuando están en cero
- 11 tests del dashboard (costo, margen, gastos fijos, overhead, aislamiento)

#### Técnico
- Suite completa: **161 tests**, todos verdes
- `RecipeCostCalculator` inyectado como dependencia en `DashboardController` y `RecipeController`

---

## [0.3.0] — 2026-05-18

### Backoffice B.1 + Frontend + Módulo de Costos (Etapa 2, parcial)

#### Agregado

**Backoffice de administración (B.1)**
- Panel `/admin` exclusivo para super admins (middleware `EnsureSuperAdmin`)
- Dashboard con widgets: tenants activos/inactivos, usuarios totales, actividad reciente
- Gestión completa de tenants: listado, alta, edición, activar/desactivar
- Vista de usuarios: listado global con tenants, roles y estado por usuario
- Impersonación de tenant: sesión como cualquier tenant con banner visible y botón de salida
- Logs de auditoría con filtros por acción, tenant y fecha
- Layout admin separado (`AdminLayout`) con navbar corteza y logo SVG adaptable

**Frontend — reestructuración de layout**
- Layout dos columnas: top nav fijo + sidebar izquierdo context-aware siempre visible
- Top nav: bloque de marca alineado al sidebar (logo o nombre del tenant), secciones, dropdown de usuario
- Sidebar: expande "Mi negocio" o "Costos" según la sección activa; oculto en móvil
- Componente `<x-crud-modal>` reutilizable para todos los módulos

**Modelo de datos base**
- Enum `CondicionIva` (RI/MO/EX/CF/NR) y campos fiscales en `tenants` (`razon_social`, `cuit`, `condicion_iva`)
- Vista "Mi negocio" reestructurada en dos columnas: datos del negocio + datos fiscales
- Módulo de sucursales (`locations`): CRUD completo para owner/super_admin con activar/desactivar

**Etapa 2.1 — Ingredientes**
- Tabla `ingredients` con enum `Unit` (gr/kg/ml/L/cc/u), costo por unidad y estado activo/inactivo
- Tabla `ingredient_price_logs`: historial inmutable de precios (sin timestamps), generado automáticamente al crear y al cambiar el costo
- Gate `manage-costs`: owner/admin escriben, viewer solo lee
- Vista CRUD con modales Alpine (crear, editar, activar/desactivar)

**Etapa 2.1b — Proveedores**
- Tabla `suppliers` (nombre, teléfono, email, notas, activo) vinculada a ingredientes y packaging
- Campo `brand` en ingredientes y packaging
- Modal "quick-create" de proveedor accesible desde los modales de ingrediente y packaging sin salir de la pantalla

**Etapa 2.2 — Packaging (Envases)**
- Tabla `packagings` con costo por unidad (decimal 10,4), marca y proveedor opcional
- Tabla `packaging_price_logs`: mismo patrón de historial inmutable que ingredientes
- Vista CRUD con modales Alpine

**Etapa 2.3 — Gastos Fijos**
- Tabla `fixed_costs` con monto mensual y estado activo/inactivo
- Tabla `fixed_cost_categories` per-tenant: categorías gestionables desde un modal inline (crear, renombrar, eliminar con guard si tiene gastos asignados)
- Tabla `fixed_cost_logs`: historial con `valid_from` editable por el usuario — permite cargar datos históricos retroactivos
- Total mensual activo calculado al pie del listado
- Modal de categorías reabre automáticamente tras cada operación vía session flag

#### Métricas
- Tests: 106 (todos verdes) — cubre CRUD, roles, aislamiento de tenants y trazabilidad de precios
- Nuevas tablas: `admin_audit_logs`, `locations`, `ingredients`, `ingredient_price_logs`, `suppliers`, `packagings`, `packaging_price_logs`, `fixed_costs`, `fixed_cost_logs`, `fixed_cost_categories`

---

## [0.2.0] — 2026-05-13

### Etapa 1 completa — Fundación Web

#### Agregado
- **Auth (Breeze):** login, logout, recuperación de contraseña, verificación de email
- **Roles y permisos:** enum `TenantUserRole` (super_admin, owner, admin, viewer), Gates por rol, middleware `CheckTenantRole`
- **Multi-tenancy:** middleware `SetTenantContext` resuelve tenant desde el usuario autenticado; solo TenantUsers activos son considerados
- **Mi equipo:** invitaciones por email con token, listado de miembros, edición de rol, baja lógica (activar/desactivar)
- **Mi negocio:** edición de nombre, país, moneda, horas productivas mensuales y logo (upload a storage)
- **Mi perfil:** edición de nombre, email y contraseña
- **Branding Levado:** paleta Tailwind (masa-madre, corteza, harina, miga, horno, membrillo), tipografías Inter/Lora/JetBrains Mono, logo SVG wordmark
- **Layouts:** `app.blade.php` (tenant) y `guest.blade.php` (auth) con branding completo
- **Navegación:** links condicionales por rol (`@can`), menú de usuario con perfil y cerrar sesión
- **Vistas en español:** todas las vistas de auth y perfil hardcodeadas en español rioplatense
- **Registro bloqueado:** ruta `/register` eliminada; usuarios solo entran por invitación
- **Seeder demo:** tenant "Levado HQ" con `admin@levado.com` (super_admin) y tenant "Panadería Demo" con `owner@demo.com` (owner); password `password`
- **Factory:** `TenantFactory` con estado `inactive()`
- **Tests:** suite completa de 35 tests — auth, perfil, aislamiento de tenants por rol y entre tenants, usuario inactivo

#### Corregido
- `SetTenantContext` redirige al login (en vez de abort 404) cuando no hay tenant activo
- Dashboard requiere middleware `tenant` (antes era accesible sin tenant)
- `TenantUser.active = false` impide resolución del tenant (antes se ignoraba el estado del vínculo)

---

## [0.1.2] — 2026-05-11

### Etapas 1.1 y 1.2 — Setup y Multi-tenancy

#### Agregado
- Inicialización del proyecto Laravel 13 en Herd local
- Base de datos MySQL con migraciones `tenants` y `tenant_settings`
- Modelos `Tenant` y `TenantSetting` con helper `getSetting/setSetting`
- Middleware `SetTenantContext` (estructura base)
- Repositorio Git con ramas `master` y `develop`
- Versionado en `config/app.php` (`config('app.version')`)
