import Shepherd from 'shepherd.js';
import 'shepherd.js/dist/css/shepherd.css';

if (window.levadoOnboarding) {
    const { step, route } = window.levadoOnboarding;

    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            cancelIcon: { enabled: true },
            scrollTo: { behavior: 'smooth', block: 'center' },
            modalOverlayOpeningPadding: 8,
            modalOverlayOpeningRadius: 6,
        },
    });

    const btn = (text, action, secondary = false) => ({
        text,
        action,
        classes: secondary ? 'shepherd-button-secondary' : '',
    });

    const goTo = (url) => () => { window.location.href = url; };

    // ── Step 0, dashboard ───────────────────────────────────────────────────
    if (step === 0 && route === 'dashboard') {
        tour.addStep({
            id: 'welcome',
            title: 'Bienvenido a Levado',
            text: 'En 5 pasos vas a tener tu primera receta con su costo calculado. Empezamos con la capacidad productiva de tu panadería.',
            buttons: [
                btn('Empezar →', tour.next.bind(tour)),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
        tour.addStep({
            id: 'go-business',
            title: 'Paso 1 de 5 — Tu negocio',
            text: 'Indicá cuántas horas por mes trabaja tu panadería. Esto nos permite distribuir los gastos fijos en cada receta.',
            attachTo: { element: '#sidebar-negocio', on: 'right' },
            advanceOn: { selector: '#sidebar-negocio', event: 'click' },
            buttons: [
                btn('Ir a Mi negocio →', goTo('/business')),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 0, business.edit ───────────────────────────────────────────────
    if (step === 0 && route === 'business.edit') {
        tour.addStep({
            id: 'set-hours',
            title: 'Paso 1 de 5 — Horas productivas',
            text: 'Ingresá cuántas horas por mes trabaja tu panadería y guardá los cambios. Con este dato calculamos el costo por hora de producción.',
            attachTo: { element: '#field-horas-productivas', on: 'bottom' },
            buttons: [
                btn('Entendido', tour.cancel.bind(tour)),
            ],
        });
    }

    // ── Step 1, business.edit ───────────────────────────────────────────────
    if (step === 1 && route === 'business.edit') {
        tour.addStep({
            id: 'hours-done',
            title: '¡Capacidad configurada!',
            text: 'Ahora registrá tus gastos fijos mensuales: alquiler, servicios, personal. Se distribuyen entre todas tus recetas.',
            attachTo: { element: '#sidebar-gastos-fijos', on: 'right' },
            advanceOn: { selector: '#sidebar-gastos-fijos', event: 'click' },
            buttons: [
                btn('Ir a Gastos Fijos →', goTo('/fixed-costs')),
                btn('Cerrar', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 1, fixed-costs.index ───────────────────────────────────────────
    if (step === 1 && route === 'fixed-costs.index') {
        tour.addStep({
            id: 'create-fixed-cost',
            title: 'Paso 2 de 5 — Gastos fijos',
            text: 'Agregá al menos un gasto fijo mensual: alquiler, luz, gas, internet… Estos costos se reparten automáticamente entre tus recetas.',
            attachTo: { element: '#btn-nuevo-gasto', on: 'bottom' },
            buttons: [
                btn('+ Nuevo gasto', () => {
                    document.getElementById('btn-nuevo-gasto')?.click();
                    tour.cancel();
                }),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 2, fixed-costs.index ───────────────────────────────────────────
    if (step === 2 && route === 'fixed-costs.index') {
        tour.addStep({
            id: 'fixed-cost-done',
            title: '¡Gasto registrado!',
            text: 'Ahora definí los roles de trabajo y su costo por hora: panadero, ayudante, etc.',
            attachTo: { element: '#sidebar-mano-de-obra', on: 'right' },
            advanceOn: { selector: '#sidebar-mano-de-obra', event: 'click' },
            buttons: [
                btn('Ir a Mano de Obra →', goTo('/labor-types')),
                btn('Cerrar', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 2, labor-types.index ───────────────────────────────────────────
    if (step === 2 && route === 'labor-types.index') {
        tour.addStep({
            id: 'create-labor-type',
            title: 'Paso 3 de 5 — Mano de obra',
            text: 'Agregá al menos un tipo de trabajo con su tarifa por hora. Podés empezar con "Estándar" y ajustarlo después.',
            attachTo: { element: '#btn-nuevo-tipo-labor', on: 'bottom' },
            buttons: [
                btn('+ Nuevo tipo', () => {
                    document.getElementById('btn-nuevo-tipo-labor')?.click();
                    tour.cancel();
                }),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 3, labor-types.index ───────────────────────────────────────────
    if (step === 3 && route === 'labor-types.index') {
        tour.addStep({
            id: 'labor-done',
            title: '¡Mano de obra lista!',
            text: 'Ahora cargá tus insumos: harina, levadura, manteca… con su precio por unidad.',
            attachTo: { element: '#sidebar-ingredientes', on: 'right' },
            advanceOn: { selector: '#sidebar-ingredientes', event: 'click' },
            buttons: [
                btn('Ir a Ingredientes →', goTo('/ingredients')),
                btn('Cerrar', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 3, ingredients.index ───────────────────────────────────────────
    if (step === 3 && route === 'ingredients.index') {
        tour.addStep({
            id: 'create-ingredient',
            title: 'Paso 4 de 5 — Insumos',
            text: 'Ingresá el nombre, unidad de medida y costo por unidad de tu primer ingrediente. Podés indicar el proveedor si ya lo tenés.',
            attachTo: { element: '#btn-nuevo-ingrediente', on: 'bottom' },
            buttons: [
                btn('+ Nuevo ingrediente', () => {
                    document.getElementById('btn-nuevo-ingrediente')?.click();
                    tour.cancel();
                }),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 4, ingredients.index ───────────────────────────────────────────
    if (step === 4 && route === 'ingredients.index') {
        tour.addStep({
            id: 'ingredient-done',
            title: '¡Ingrediente cargado!',
            text: 'Ya tenés todo listo para crear tu primera receta. Levado calculará el costo de producción automáticamente.',
            attachTo: { element: '#sidebar-recetas', on: 'right' },
            advanceOn: { selector: '#sidebar-recetas', event: 'click' },
            buttons: [
                btn('Ir a Recetas →', goTo('/recipes')),
                btn('Cerrar', tour.cancel.bind(tour), true),
            ],
        });
    }

    // ── Step 4, recipes.index ───────────────────────────────────────────────
    if (step === 4 && route === 'recipes.index') {
        tour.addStep({
            id: 'create-recipe',
            title: 'Paso 5 de 5 — Primera receta',
            text: 'Creá tu primera receta, agregá los ingredientes y la mano de obra. El costo de producción se calcula solo.',
            attachTo: { element: '#btn-nueva-receta', on: 'bottom' },
            buttons: [
                btn('+ Nueva receta', () => {
                    document.getElementById('btn-nueva-receta')?.click();
                    tour.cancel();
                }),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
    }

    if (tour.steps.length > 0) {
        tour.start();
    }
}
