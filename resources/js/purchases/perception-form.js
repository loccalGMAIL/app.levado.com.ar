/**
 * Modales de percepciones de una compra.
 *
 * - `perceptionForm`: alta y edición de la percepción de una compra.
 * - `perceptionConceptForm`: submodal «Nueva percepción» (alta de un concepto del catálogo).
 * - `perceptionConceptManager`: submodal «Administrar» (editar, activar/desactivar y borrar).
 *
 * Los dos submodales se abren desde el modal de la percepción y, al cerrarse (guardando,
 * cancelando o con la X), vuelven a él sin perder lo cargado: se hablan por eventos de window
 * (`open-perception-submodal`, `perception-concept-created|updated|deleted`).
 */

const csrfToken = () => document.querySelector('meta[name=csrf-token]').content;

/** 3 → «3», 1.5 → «1,5». Igual que el formato que arma el servidor en el select. */
const formatRate = (rate) => Number(rate).toLocaleString('es-AR', { maximumFractionDigits: 4 });

/** Llamada JSON al CRUD de conceptos; devuelve { ok, status, data }. */
async function conceptRequest(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    let data = null;

    try {
        data = await response.json();
    } catch (e) {
        data = null;
    }

    return { ok: response.ok, status: response.status, data };
}

/** Un submodal recuerda desde qué modal se abrió y vuelve a él al cerrarse. */
const returnToPreviousModal = (component, closedName, ownName) => {
    if (closedName !== ownName || !component.returnTo) {
        return;
    }

    const target = component.returnTo;
    component.returnTo = null;
    component.$nextTick(() => component.$dispatch('open-modal', target));
};

window.perceptionForm = function perceptionForm({ concepts = {}, bases = {}, baseLabels = {} } = {}) {
    const blank = () => ({
        id: null,
        perception_concept_id: '',
        type: 'otro',
        jurisdiction: '',
        description: '',
        base_type: 'net_taxed',
        rate: '',
        amount: '',
    });

    return {
        concepts,
        bases,
        baseLabels,
        form: blank(),
        // Al volver de un submodal no hay que re-sembrar el formulario: se perdería lo que
        // el usuario ya había cargado.
        resuming: false,

        init() {
            // `editingPerception` vive en el x-data de la página (null = alta); se relee al
            // abrir el modal para que un alta no herede lo tipeado en la anterior.
            this.seed(this.$data.editingPerception);
            window.addEventListener('open-modal', (e) => {
                if (e.detail !== 'perception-form') {
                    return;
                }

                if (this.resuming) {
                    this.resuming = false;

                    return;
                }

                this.seed(this.$data.editingPerception);
            });
        },

        seed(perception) {
            this.form = perception ? { ...blank(), ...perception } : blank();
        },

        /** Con concepto del catálogo, descripción y base son los del concepto. */
        get hasConcept() {
            return this.form.perception_concept_id !== '' && this.form.perception_concept_id !== null && this.form.perception_concept_id !== undefined;
        },

        get isEditing() {
            return this.form.id !== null && this.form.id !== undefined && this.form.id !== '';
        },

        get autoBase() {
            return this.bases[this.form.base_type] ?? 0;
        },

        get computedAmount() {
            if (this.form.rate === '' || this.form.rate === null) {
                return 0;
            }

            return Math.round(this.autoBase * (Number(this.form.rate) || 0)) / 100;
        },

        pickConcept() {
            const concept = this.concepts[this.form.perception_concept_id];

            if (!concept) {
                return;
            }

            this.form.description = concept.name;
            this.form.type = concept.type;
            this.form.jurisdiction = concept.jurisdiction;
            this.form.base_type = concept.base_type || 'net_taxed';

            if (this.form.rate === '' || this.form.rate === null) {
                this.form.rate = concept.default_rate;
            }
        },

        /** Texto de la opción: «Perc. IIBB Buenos Aires — 3% · Neto gravado». */
        optionLabel(concept) {
            const rate = concept.default_rate !== null && concept.default_rate !== '' && concept.default_rate !== undefined
                ? ` — ${formatRate(concept.default_rate)}%`
                : '';

            return `${concept.name}${rate} · ${this.baseLabels[concept.base_type] ?? ''}`;
        },

        optionFor(id) {
            return [...this.$refs.conceptSelect.options].find((option) => option.value === String(id));
        },

        /** Abre un submodal («perception-concepts» o «perception-concepts-manage») y recuerda volver. */
        openSubmodal(name) {
            this.resuming = true;
            window.dispatchEvent(new CustomEvent('open-perception-submodal', { detail: { modal: name, returnTo: 'perception-form' } }));
            this.$dispatch('close-modal', 'perception-form');
            this.$dispatch('open-modal', name);
        },

        /** Suma al select el concepto recién creado y lo deja elegido. */
        addConcept(concept) {
            this.concepts[concept.id] = concept;
            this.$refs.conceptSelect.add(new Option(this.optionLabel(concept), concept.id));

            this.$nextTick(() => {
                this.form.perception_concept_id = String(concept.id);
                this.form.rate = '';
                this.pickConcept();
            });
        },

        /** Un concepto editado o (des)activado en «Administrar»: refresca su opción. */
        updateConcept(concept) {
            const selected = String(this.form.perception_concept_id) === String(concept.id);

            // Uno desactivado deja de ofrecerse, salvo que sea el elegido en este momento.
            if (!concept.is_active && !selected) {
                this.removeConcept(concept.id);

                return;
            }

            this.concepts[concept.id] = concept;
            const option = this.optionFor(concept.id);

            if (option) {
                option.text = this.optionLabel(concept);
            } else {
                this.$refs.conceptSelect.add(new Option(this.optionLabel(concept), concept.id));
            }

            if (selected) {
                this.form.description = concept.name;
                this.form.base_type = concept.base_type;
            }
        },

        /** Un concepto borrado: sale del select; si estaba elegido la percepción queda libre. */
        removeConcept(id) {
            delete this.concepts[id];
            this.optionFor(id)?.remove();

            if (String(this.form.perception_concept_id) === String(id)) {
                this.form.perception_concept_id = '';
            }
        },

        fmt(n) {
            return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
};

window.perceptionConceptForm = function perceptionConceptForm({ url }) {
    const initial = () => ({ type: 'iibb', jurisdiction: '', name: '', base_type: 'net_taxed', default_rate: '' });

    return {
        form: initial(),
        errors: {},
        saving: false,
        returnTo: null,

        get requiresJurisdiction() {
            return this.form.type === 'iibb';
        },

        onOpen(detail) {
            if (detail?.modal === 'perception-concepts') {
                this.returnTo = detail.returnTo ?? null;
            }
        },

        async submit() {
            if (this.saving) {
                return;
            }

            this.saving = true;
            this.errors = {};

            try {
                const { ok, status, data } = await conceptRequest('POST', url, {
                    ...this.form,
                    jurisdiction: this.requiresJurisdiction ? this.form.jurisdiction : '',
                });

                if (status === 422) {
                    this.errors = data?.errors ?? {};

                    return;
                }

                if (!ok) {
                    this.errors = { name: ['No se pudo guardar. Probá de nuevo.'] };

                    return;
                }

                window.dispatchEvent(new CustomEvent('perception-concept-created', { detail: data }));
                this.form = initial();
                this.$dispatch('close-modal', 'perception-concepts');
            } finally {
                this.saving = false;
            }
        },

        onClosed(name) {
            returnToPreviousModal(this, name, 'perception-concepts');
        },
    };
};

window.perceptionConceptManager = function perceptionConceptManager({ concepts = [], baseLabels = {}, updateUrl, destroyUrl }) {
    const byName = (a, b) => a.name.localeCompare(b.name, 'es');

    return {
        items: concepts,
        baseLabels,
        editing: null,
        errors: {},
        saving: false,
        returnTo: null,
        // Al pasar a «Nueva percepción» este modal se cierra, pero no es un cierre «de verdad»:
        // no debe disparar el regreso al modal previo (se vuelve acá, y recién al cerrar esta
        // lista se regresa al de la percepción).
        navigating: false,

        init() {
            // Un concepto creado desde el submodal «Nueva percepción» aparece en la lista.
            window.addEventListener('perception-concept-created', (e) => {
                this.items.push(e.detail);
                this.items.sort(byName);
            });
        },

        onOpen(detail) {
            if (detail?.modal === 'perception-concepts-manage') {
                this.returnTo = detail.returnTo ?? null;
                this.editing = null;
            }
        },

        onClosed(name) {
            if (name === 'perception-concepts-manage' && this.navigating) {
                this.navigating = false;

                return;
            }

            returnToPreviousModal(this, name, 'perception-concepts-manage');
        },

        /** «Nueva percepción» desde acá vuelve a esta lista al cerrarse. */
        openNew() {
            this.navigating = true;
            window.dispatchEvent(new CustomEvent('open-perception-submodal', { detail: { modal: 'perception-concepts', returnTo: 'perception-concepts-manage' } }));
            this.$dispatch('close-modal', 'perception-concepts-manage');
            this.$dispatch('open-modal', 'perception-concepts');
        },

        summary(concept) {
            const rate = concept.default_rate !== null && concept.default_rate !== undefined
                ? `${formatRate(concept.default_rate)}%`
                : 'sin alícuota';

            return [concept.type_label, concept.jurisdiction_label, rate, `sobre ${(this.baseLabels[concept.base_type] ?? '').toLowerCase()}`]
                .filter(Boolean)
                .join(' · ');
        },

        startEdit(concept) {
            this.errors = {};
            this.editing = {
                id: concept.id,
                name: concept.name,
                default_rate: concept.default_rate ?? '',
                base_type: concept.base_type,
            };
        },

        cancelEdit() {
            this.editing = null;
            this.errors = {};
        },

        /** El tipo y la provincia no se editan: se reenvían tal cual (el request los exige). */
        async save(concept, changes) {
            if (this.saving) {
                return false;
            }

            this.saving = true;
            this.errors = {};

            try {
                const { ok, status, data } = await conceptRequest('PUT', updateUrl.replace('__ID__', concept.id), {
                    type: concept.type,
                    jurisdiction: concept.jurisdiction ?? '',
                    name: concept.name,
                    default_rate: concept.default_rate ?? '',
                    base_type: concept.base_type,
                    is_active: concept.is_active,
                    ...changes,
                });

                if (status === 422) {
                    this.errors = data?.errors ?? {};

                    return false;
                }

                if (!ok) {
                    this.errors = { name: ['No se pudo guardar. Probá de nuevo.'] };

                    return false;
                }

                const index = this.items.findIndex((item) => item.id === concept.id);
                this.items.splice(index, 1, data);
                this.items.sort(byName);
                window.dispatchEvent(new CustomEvent('perception-concept-updated', { detail: data }));

                return true;
            } finally {
                this.saving = false;
            }
        },

        async saveEdit(concept) {
            const saved = await this.save(concept, {
                name: this.editing.name,
                default_rate: this.editing.default_rate,
                base_type: this.editing.base_type,
            });

            if (saved) {
                this.editing = null;
            }
        },

        toggleActive(concept) {
            return this.save(concept, { is_active: !concept.is_active });
        },

        async remove(concept) {
            if (!window.confirm(`¿Eliminar «${concept.name}»? Las compras ya cargadas conservan sus datos.`)) {
                return;
            }

            const { ok } = await conceptRequest('DELETE', destroyUrl.replace('__ID__', concept.id));

            if (!ok) {
                this.errors = { name: ['No se pudo eliminar. Probá de nuevo.'] };

                return;
            }

            this.items = this.items.filter((item) => item.id !== concept.id);
            window.dispatchEvent(new CustomEvent('perception-concept-deleted', { detail: { id: concept.id } }));
        },
    };
};
