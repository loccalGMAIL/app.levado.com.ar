if (window.levadoOnboarding) {
    (async () => {
    const [{ default: Shepherd }] = await Promise.all([
        import('shepherd.js'),
        import('shepherd.js/dist/css/shepherd.css'),
    ]);

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

    // ── Dashboard (any incomplete step) ────────────────────────────────────
    if (route === 'dashboard') {
        const stepConfig = [
            { sidebar: '#sidebar-negocio',      url: '/business',     btnLabel: 'Ir a Mi negocio →',    title: 'Paso 1 de 5 — Tu negocio',       text: 'Indicá cuántas horas por mes trabaja tu panadería. Esto nos permite distribuir los gastos fijos en cada receta.' },
            { sidebar: '#sidebar-gastos-fijos',  url: '/fixed-costs',  btnLabel: 'Ir a Gastos Fijos →',  title: 'Paso 2 de 5 — Gastos fijos',     text: 'Registrá tus gastos fijos mensuales: alquiler, servicios, personal. Se distribuyen entre todas tus recetas.' },
            { sidebar: '#sidebar-mano-de-obra',  url: '/labor-types',  btnLabel: 'Ir a Mano de Obra →',  title: 'Paso 3 de 5 — Mano de obra',     text: 'Definí los roles de trabajo y su costo por hora: panadero, ayudante, etc.' },
            { sidebar: '#sidebar-ingredientes',  url: '/ingredients',  btnLabel: 'Ir a Ingredientes →',  title: 'Paso 4 de 5 — Insumos',          text: 'Cargá tus insumos: harina, levadura, manteca… con su precio por unidad.' },
            { sidebar: '#sidebar-recetas',       url: '/recipes',      btnLabel: 'Ir a Recetas →',       title: 'Paso 5 de 5 — Primera receta',   text: 'Creá tu primera receta, agregá los ingredientes y la mano de obra. El costo de producción se calcula solo.' },
        ];
        const config = stepConfig[step];
        const welcomeTitle = step === 0 ? 'Bienvenido a Levado' : '¡Seguimos configurando!';
        const welcomeText  = step === 0
            ? 'En 5 pasos vas a tener tu primera receta con su costo calculado. Empezamos con la capacidad productiva de tu panadería.'
            : 'Falta poco para tener tu primera receta con el costo calculado. Continuemos desde donde lo dejamos.';

        tour.addStep({
            id: 'welcome',
            title: welcomeTitle,
            text: welcomeText,
            buttons: [
                btn('Empezar →', tour.next.bind(tour)),
                btn('Ahora no', tour.cancel.bind(tour), true),
            ],
        });
        tour.addStep({
            id: 'go-next-step',
            title: config.title,
            text: config.text,
            attachTo: { element: config.sidebar, on: 'right' },
            advanceOn: { selector: config.sidebar, event: 'click' },
            buttons: [
                btn(config.btnLabel, goTo(config.url)),
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
    })();
}
