<div class="page-header"><h2>CompanyEnrich research</h2></div>
<div class="panel panel-default"><div class="panel-body">
    <p>CompanyEnrich provides company enrichment, business and professional-profile search, similar-company discovery, and workforce research.</p>
    <p>Research returns data without changing CRM records. People search returns professional profiles, not email addresses. Lookup starts from an email you already know.</p>
    <div class="form-group">
        <label for="companyenrich-operation">Action</label>
        <select class="form-control" id="companyenrich-operation" name="operation">
            {{#each operations}}<option value="{{name}}">{{label}}</option>{{/each}}
        </select>
    </div>
    <div class="form-group">
        <label for="companyenrich-input">Request input (JSON)</label>
        <textarea class="form-control" rows="12" id="companyenrich-input" name="input" spellcheck="false"></textarea>
    </div>
    <p>Counts and autocomplete are free. Previews are free and require Scale. Other actions and expansions consume your CompanyEnrich credits. Requests are not automatically retried.</p>
    <p><a href="https://docs.companyenrich.com/reference" target="_blank" rel="noopener noreferrer">API filters and credit costs</a> · <a href="https://companyenrich.com/privacy-policy" target="_blank" rel="noopener noreferrer">Provider privacy policy</a></p>
    <button class="btn btn-primary" type="button" data-action="runResearch">Run research</button>
    <h3>Results</h3>
    <pre data-name="result" aria-live="polite" style="white-space: pre-wrap; overflow-wrap: anywhere;"></pre>
</div></div>
