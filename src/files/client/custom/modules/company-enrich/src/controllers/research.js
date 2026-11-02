import Controller from 'controller';

export default class extends Controller {
    defaultAction = 'index';

    checkAccessGlobal() {
        return this.getAcl().checkScope('CompanyEnrich') && !this.getUser().isPortal();
    }

    actionIndex() {
        this.main('company-enrich:views/research', {}, view => view.render());
    }
}
