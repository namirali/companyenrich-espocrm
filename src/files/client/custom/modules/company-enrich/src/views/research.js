import View from 'view';
import operations from 'company-enrich:operations';

export default class extends View {
    template = 'company-enrich:research';

    data() {
        return {operations};
    }

    setup() {
        this.addActionHandler('runResearch', () => this.runResearch());
        this.addHandler('change', '[name="operation"]', () => this.setExample());
    }

    afterRender() {
        this.setExample();
    }

    setExample() {
        const name = this.$el.find('[name="operation"]').val();
        const operation = operations.find(item => item.name === name);
        this.$el.find('[name="input"]').val(JSON.stringify(operation?.example ?? {}, null, 2));
        this.$el.find('[data-name="result"]').text('');
    }

    async runResearch() {
        if (this.running) return;
        let input;
        try {
            input = JSON.parse(this.$el.find('[name="input"]').val());
        } catch {
            Espo.Ui.error('Enter valid JSON.');
            return;
        }
        if (!input || typeof input !== 'object' || Array.isArray(input)) {
            Espo.Ui.error('Input must be a JSON object.');
            return;
        }
        this.running = true;
        this.$el.find('[data-action="runResearch"]').prop('disabled', true);
        this.$el.find('[data-name="result"]').text('Running…');
        try {
            const result = await Espo.Ajax.postRequest('CompanyEnrich/research', {
                operation: this.$el.find('[name="operation"]').val(), input,
            }, {timeout: 65000});
            this.$el.find('[data-name="result"]').text(JSON.stringify(result, null, 2));
        } catch {
            this.$el.find('[data-name="result"]').text('Request failed. Check permissions, connection, and provider usage before trying again.');
        } finally {
            this.running = false;
            this.$el.find('[data-action="runResearch"]').prop('disabled', false);
        }
    }
}
