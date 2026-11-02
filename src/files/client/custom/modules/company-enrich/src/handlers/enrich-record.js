import ActionHandler from 'action-handler';

export default class extends ActionHandler {
    isVisible() {
        return this.view.getAcl().checkScope('CompanyEnrich') && !this.view.getUser().isPortal();
    }

    async run() {
        if (this.running) return;
        this.running = true;
        this.view.disableMenuItem('companyEnrichFill');
        try {
            try {
                await this.view.confirm('Send this account’s website or contact’s known email to CompanyEnrich and fill empty fields? This may consume CompanyEnrich credits. Existing values are preserved.');
            } catch {
                return;
            }
            const result = await Espo.Ajax.postRequest('CompanyEnrich/enrich-record', {
                entityType: this.view.model.entityType, id: this.view.model.id,
            }, {timeout: 65000});
            if (!result.success) {
                Espo.Ui.error(result.error);
                return;
            }
            await this.view.model.fetch();
            Espo.Ui.success(result.updatedFields.length ? `Updated: ${result.updatedFields.join(', ')}` : 'No empty fields were updated.');
        } catch {
            Espo.Ui.error('Enrichment did not complete. Refresh the record and check provider usage before trying again.');
        } finally {
            this.running = false;
            this.view.enableMenuItem('companyEnrichFill');
        }
    }
}
