import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL(
    '../../src/files/client/custom/modules/company-enrich/src/handlers/enrich-record.js',
    import.meta.url,
), 'utf8');

// Load the actual handler with the framework boundary supplied locally. No browser,
// Espo installation, provider credentials or network requests are needed.
const handlerBody = source
    .replace("import ActionHandler from 'action-handler';", '')
    .replace('export default class extends ActionHandler', 'return class extends ActionHandler');

function fixture(postRequest = async () => ({success: true, updatedFields: ['description']})) {
    const confirmations = [];
    const requests = [];
    const messages = [];
    const controls = [];
    const view = {
        confirm: () => new Promise((resolve, reject) => confirmations.push({resolve, reject})),
        disableMenuItem: name => controls.push(['disable', name]),
        enableMenuItem: name => controls.push(['enable', name]),
        model: {entityType: 'Account', id: 'fixture-record', fetch: async () => {}},
    };
    const espo = {
        Ajax: {
            postRequest: async (...args) => {
                requests.push(args);
                return postRequest(...args);
            },
        },
        Ui: {
            success: value => messages.push(['success', value]),
            error: value => messages.push(['error', value]),
        },
    };
    const Handler = new Function('ActionHandler', 'Espo', handlerBody)(
        class { constructor(view) { this.view = view; } },
        espo,
    );
    return {handler: new Handler(view), confirmations, requests, messages, controls};
}

test('concurrent invocations while confirming submit only one paid request', async () => {
    const f = fixture();
    const first = f.handler.run();
    const duplicate = f.handler.run();
    assert.equal(f.confirmations.length, 1);
    assert.equal(f.requests.length, 0);
    assert.deepEqual(f.controls, [['disable', 'companyEnrichFill']]);
    await duplicate;

    f.confirmations[0].resolve();
    await first;
    assert.equal(f.requests.length, 1);
    assert.equal(f.requests[0][0], 'CompanyEnrich/enrich-record');
    assert.deepEqual(f.requests[0][1], {entityType: 'Account', id: 'fixture-record'});
    assert.deepEqual(f.controls.at(-1), ['enable', 'companyEnrichFill']);
    assert.equal(f.handler.running, false);
});

test('cancelling spends nothing, shows no failure and permits a later invocation', async () => {
    const f = fixture();
    const cancelled = f.handler.run();
    f.confirmations[0].reject(new Error('User cancelled'));
    await cancelled;
    assert.equal(f.requests.length, 0);
    assert.deepEqual(f.messages, []);
    assert.equal(f.handler.running, false);
    assert.deepEqual(f.controls.at(-1), ['enable', 'companyEnrichFill']);

    const later = f.handler.run();
    assert.equal(f.confirmations.length, 2);
    f.confirmations[1].resolve();
    await later;
    assert.equal(f.requests.length, 1);
});

test('a failed request is not retried and releases the action guard', async () => {
    const f = fixture(async () => { throw new Error('Synthetic network failure'); });
    const pending = f.handler.run();
    f.confirmations[0].resolve();
    await pending;
    assert.equal(f.requests.length, 1);
    assert.equal(f.messages.length, 1);
    assert.equal(f.messages[0][0], 'error');
    assert.match(f.messages[0][1], /check provider usage/i);
    assert.equal(f.handler.running, false);
    assert.deepEqual(f.controls.at(-1), ['enable', 'companyEnrichFill']);
});
