# CompanyEnrich for EspoCRM

[CompanyEnrich](https://companyenrich.com) provides APIs for company enrichment, company and professional-profile search, similar-company discovery, and workforce research. This installable extension brings those operations into EspoCRM, using your own CompanyEnrich API key. It is a standalone community extension, not an official EspoCRM product.

Use it to qualify accounts, research people at target companies, find similar businesses, or explicitly fill empty Account and Contact fields. People search returns professional profiles, **not email addresses**. Person lookup starts from an email you already have. See the [CompanyEnrich API reference](https://docs.companyenrich.com/reference).

## Installation

Requires EspoCRM 10.x and PHP 8.3+. No additional PHP library is installed in your CRM.

1. Build the ZIP with Node.js 24+ using `npm ci` and `npm run extension`, or download the ZIP artifact from a successful repository Actions run.
2. As an administrator, open **Administration → Extensions**, upload `build/company-enrich-0.1.0.zip`, and install it. The supported CLI alternative is `php command.php extension --file=/path/to/company-enrich-0.1.0.zip`.
3. Open **Administration → Integrations → CompanyEnrich**. Enter your API key and enable the integration. The key is encrypted with EspoCRM's `Crypt` service and masked by its native integration settings. Leave the key blank to preserve it; **Remove stored API key** clears it and disables the integration.
4. Grant **CompanyEnrich** access to the appropriate user roles. Portal users are excluded. The extension adds a CompanyEnrich navigation tab; refresh the browser after installation.
5. Open **CompanyEnrich research**, select an action, edit its example JSON, and click **Run research**. Research does not change CRM records.

The Account and Contact detail views also include **CompanyEnrich: fill empty fields**. This requires record read/edit and field read/edit permissions. The confirmation explains the identifier sent and potential credit use. An Account needs a website; a Contact needs a known email address. Only empty fields are filled: Account `name`/`description`; Contact `firstName`/`lastName`/`description` (professional position). Existing values, emails, phones, relationships, and campaign membership are preserved. Records with no eligible empty fields incur no provider request. Full profiles remain available through research rather than being copied into each CRM record.

The extension performs the paid lookup once, outside the database transaction. It then locks and rereads the record, rechecks permissions, and verifies the original modification time, identifier, and candidate values. Concurrent changes abort the update; a transaction retry never repeats the paid lookup.

## Research operations

| Action | CompanyEnrich endpoint |
| --- | --- |
| Enrich company by domain | `GET /companies/enrich` |
| Enrich company by name/social properties | `POST /companies/enrich` |
| Batch enrichment (1–50 domains) | `POST /companies/enrich/batch` |
| Retrieve company by ID | `GET /companies` |
| Company search | `POST /companies/search` |
| Similar companies | `POST /companies/similar` |
| Search count | `POST /companies/search/count` |
| Similarity count | `POST /companies/similar/count` |
| Workforce | `GET /companies/workforce` |
| Professional-profile search | `POST /people/search` |
| Person lookup by known email | `POST /people/lookup` |
| Retrieve person by ID | `GET /people` |
| Person education | `GET /people/education` |
| Domain autocomplete | `GET /companies/autocomplete` |
| Company search preview (Scale plan) | `POST /companies/search/preview` |
| Similarity preview (Scale plan) | `POST /companies/similar/preview` |

Inputs support the selected public OpenAPI request schemas, including advanced search filters. Unknown properties are rejected before provider access. Search defaults to page 1 with 10 results; request another page explicitly, up to 100 results per page. Numeric person IDs must be strings to preserve all 64 bits. Optional `expand` values (`workforce` or `education`, where supported) remain off by default and cost additional credits. Explicit `waitForEnrichment: false` is preserved; a 404 may mean a new domain is still processing.

Authenticated REST clients can POST to `/api/v1/CompanyEnrich/research`:

```json
{
  "operation": "search-people",
  "input": {
    "body": {
      "domains": ["example.com"],
      "positionQuery": ["Engineer"],
      "page": 1,
      "pageSize": 10
    }
  }
}
```

POST body fields go under `input.body`. GET identifiers and query options such as `expand` stay directly under `input`. Responses contain `success`, full provider `data`, and `creditsConsumed` when reported. Failures return a safe `error`. Arrays, count zero, scores, pagination, and metadata are preserved; unsafe JavaScript integers are returned as decimal strings. No automatic pagination, polling, or retries occur.

Long-running exports, scroll cursors, saved-list administration, and beta email discovery are outside this synchronous extension. Export/list operations need lifecycle management; email discovery is a separate product from profile research. Geography helper endpoints are not separate UI actions; use the documented country codes and location IDs in filters.

## Credits, data handling, and support

Counts and autocomplete are free. Previews are free and require CompanyEnrich's Scale plan. Other operations and expansions consume CompanyEnrich credits; see [current pricing](https://companyenrich.com/pricing) and endpoint documentation. A timeout or a concurrent-edit conflict can occur after a paid request was accepted. Check usage before manually repeating it. This extension adds no recurring jobs or paid background requests.

Requests go only to `https://api.companyenrich.com`, using TLS, a 50-second timeout, no redirects, a 256 KiB request limit, and an 8 MiB response limit. Credentials and raw provider error responses are never sent to clients or logged by this extension. Native EspoCRM API authentication is required; browser requests use EspoCRM Ajax and its authenticated request headers, without adding an anonymous or cookie-only mutation route.

Research sends only the entered filters/identifiers. Record enrichment sends the account website or the contact's known email. Research results remain in the current panel; the extension does not persist response bodies or maintain a separate history. Filled CRM values follow your normal EspoCRM retention/deletion policies. Provider retention is described in the [CompanyEnrich privacy policy](https://companyenrich.com/privacy-policy). Clear the integration key before uninstalling if you do not want EspoCRM to retain its encrypted credential for reinstallation. Uninstall removes the navigation entry and preserves existing CRM data.

For provider questions, use [CompanyEnrich support](https://companyenrich.com/contact). For extension defects, open an issue in this repository. The extension is based on the [official extension template](https://github.com/espocrm/ext-template) and uses EspoCRM's documented [extension format](https://docs.espocrm.com/development/extension-packages/) and [API actions](https://docs.espocrm.com/development/api-action/).

## Development and validation

```sh
npm ci
npm run prepare-test
composer install
vendor/bin/phpunit
npm run test:frontend
vendor/bin/phpstan analyse --no-progress
npm run extension
```

Unit tests use synthetic data and mock the HTTP boundary; they make no CompanyEnrich API calls. The GitHub Actions workflow at `.github/workflows/validate.yml` checks PHP 8.3, 8.4, and 8.5 against EspoCRM's stable source and builds a downloadable extension ZIP. It runs on pull requests and pushes to `main`, with read-only repository permissions and no CompanyEnrich credentials. The source request schemas were checked against CompanyEnrich's public OpenAPI on 2026-11-02.

Local validation: PHP 8.4.27, 47 PHP tests/213 assertions and 3 frontend regression tests, PHPStan level 8, and the official extension package build passed. The ZIP was installed into a disposable EspoCRM 10.0.11 server; authenticated smoke checks verified native Crypt encryption/decryption, blank-key preservation and removal, key masking, validation, disabled integration behavior, no-op record updates, anonymous rejection, and role denial. No real provider credentials or paid API calls were used. A production workspace and paid response path have not been tested.

The npm packages are build tools only and are not shipped as runtime dependencies. The lockfile pins the official template tooling; a patched `adm-zip` override removes its high-severity ZIP advisories. Other moderate advisories remain in the upstream build-tool dependency tree.

## License

GNU Affero General Public License v3 or later. See [LICENSE](LICENSE).
