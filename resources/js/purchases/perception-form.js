/**
 * Estado del modal de percepción de una compra (alta y edición).
 *
 * El importe es automático (base × alícuota) salvo que el usuario escriba el que
 * figura en la factura: ese manda y no se recalcula al cambiar los renglones. La base
 * vacía significa «el neto gravado de la compra», que se muestra como placeholder.
 */
window.perceptionForm = function perceptionForm({ concepts = {}, taxedNet = 0 } = {}) {
    const blank = () => ({
        id: null,
        perception_concept_id: '',
        type: 'otro',
        jurisdiction: '',
        description: '',
        base_amount: '',
        rate: '',
        amount: '',
    });

    return {
        concepts,
        taxedNet,
        form: blank(),

        init() {
            // `editingPerception` vive en el x-data de la página (null = alta); se relee al
            // abrir el modal para que un alta no herede lo tipeado en la anterior.
            this.seed(this.$data.editingPerception);
            window.addEventListener('open-modal', (e) => {
                if (e.detail === 'perception-form') {
                    this.seed(this.$data.editingPerception);
                }
            });
        },

        seed(perception) {
            this.form = perception ? { ...blank(), ...perception } : blank();
        },

        get isEditing() {
            return this.form.id !== null && this.form.id !== undefined && this.form.id !== '';
        },

        get effectiveBase() {
            const base = this.form.base_amount;

            return base === '' || base === null ? this.taxedNet : Number(base) || 0;
        },

        get computedAmount() {
            if (this.form.rate === '' || this.form.rate === null) {
                return 0;
            }

            return Math.round(this.effectiveBase * (Number(this.form.rate) || 0)) / 100;
        },

        pickConcept() {
            const concept = this.concepts[this.form.perception_concept_id];

            if (!concept) {
                return;
            }

            this.form.description = concept.name;
            this.form.type = concept.type;
            this.form.jurisdiction = concept.jurisdiction;

            if (this.form.rate === '' || this.form.rate === null) {
                this.form.rate = concept.default_rate;
            }
        },

        fmt(n) {
            return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
};
