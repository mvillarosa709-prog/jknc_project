const fs = require('fs');
const assert = require('assert/strict');
const path = require('path');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../resources/views/activities/index.blade.php'),
    'utf8'
);

function extractMethod(name) {
    const pattern = new RegExp(
        '^        (async )?' +
        name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') +
        '\\(([^)]*)\\) \\{\\r?\\n([\\s\\S]*?)^        \\},',
        'm'
    );

    const match = source.match(pattern);
    assert(match, `actual ${name} method exists`);

    return new Function(
        'return ' +
        (match[1] || '') +
        'function(' + match[2] + '){' + match[3] + '}'
    )();
}

const addMessageEntry = extractMethod('addMessageEntry');
const messageDraftAttachments = extractMethod('messageDraftAttachments');
const messageDraftHasSingleMessengerImage =
    extractMethod('messageDraftHasSingleMessengerImage');
const messageDraftCanSendExternally =
    extractMethod('messageDraftCanSendExternally');

global.alert = () => {};

function state({
    available = true,
    ids = [],
    attachments = []
} = {}) {
    return {
        messageSaving: false,
        messageAttachmentUploading: false,
        messageDraftSession: 1,

        selectedMessageDetails: {
            id: 2,
            channel: 'Messenger',
            messenger_outbound_available: available
        },

        messageDraftAttachmentIds: [...ids],
        messageAttachments: [...attachments],

        newMessageEntry: {
            body: 'Mock reply'
        },

        messageSendRequest: null,

        isCurrentMessageDraft: () => true,
        refreshMessageConversation: async () => {},
        fetchActivities: async () => {},

        messageDraftAttachments,
        messageDraftHasSingleMessengerImage,
        messageDraftCanSendExternally
    };
}

async function captureRequest(s) {
    let request = null;

    s.messageApiRequest = async (url, options) => {
        request = JSON.parse(options.body);
    };

    await addMessageEntry.call(s);

    return request;
}

(async () => {
    // Text-only durable retry.
    const text = state();
    const requests = [];

    text.messageApiRequest = async (url, options) => {
        requests.push(JSON.parse(options.body));

        if (requests.length === 1) {
            throw new Error('Simulated lost response');
        }
    };

    await addMessageEntry.call(text);
    assert.equal(text.newMessageEntry.body, 'Mock reply');

    await addMessageEntry.call(text);

    assert.equal(requests.length, 2);
    assert.equal(
        requests[0].client_request_id,
        requests[1].client_request_id
    );
    assert.equal(requests[0].send_external, true);
    assert.equal(text.messageSendRequest, null);
    assert.equal(text.newMessageEntry.body, '');

    // Messenger unavailable -> local only.
    const unavailable = state({ available: false });
    const unavailableRequest = await captureRequest(unavailable);

    assert.equal(unavailableRequest.send_external, false);

    // Exactly one image -> external.
    const image = state({
        ids: [6],
        attachments: [
            { id: 6, mime_type: 'image/jpeg' }
        ]
    });

    const imageRequest = await captureRequest(image);

    assert.equal(imageRequest.send_external, true);
    assert.deepEqual(imageRequest.attachment_ids, [6]);
    assert.ok(imageRequest.client_request_id);

    // Unsupported PDF -> local only.
    const pdf = state({
        ids: [7],
        attachments: [
            { id: 7, mime_type: 'application/pdf' }
        ]
    });

    const pdfRequest = await captureRequest(pdf);

    assert.equal(pdfRequest.send_external, false);
    assert.deepEqual(pdfRequest.attachment_ids, [7]);
    assert.equal(pdfRequest.client_request_id, null);

    // Multiple images -> local only.
    const multiple = state({
        ids: [8, 9],
        attachments: [
            { id: 8, mime_type: 'image/jpeg' },
            { id: 9, mime_type: 'image/png' }
        ]
    });

    const multipleRequest = await captureRequest(multiple);

    assert.equal(multipleRequest.send_external, false);
    assert.deepEqual(multipleRequest.attachment_ids, [8, 9]);

    // Attachment change after lost response -> new UUID.
    const changed = state({
        ids: [10],
        attachments: [
            { id: 10, mime_type: 'image/jpeg' },
            { id: 11, mime_type: 'image/jpeg' }
        ]
    });

    const changedRequests = [];

    changed.messageApiRequest = async (url, options) => {
        changedRequests.push(JSON.parse(options.body));

        if (changedRequests.length === 1) {
            throw new Error('Simulated lost image response');
        }
    };

    await addMessageEntry.call(changed);

    const firstUuid =
        changedRequests[0].client_request_id;

    changed.messageDraftAttachmentIds = [11];

    await addMessageEntry.call(changed);

    assert.equal(changedRequests.length, 2);
    assert.notEqual(
        changedRequests[1].client_request_id,
        firstUuid
    );

    // Double-submit guard.
    const duplicate = state();
    let finish;
    let calls = 0;

    duplicate.messageApiRequest = () => {
        calls++;

        return new Promise(resolve => {
            finish = resolve;
        });
    };

    const first = addMessageEntry.call(duplicate);
    await addMessageEntry.call(duplicate);

    assert.equal(calls, 1);

    finish();
    await first;

    console.log(
        'PASS: text retry, single-image external send, unsupported local mode, multiple local mode, attachment-aware UUID, and double-submit guard.'
    );
})().catch(error => {
    console.error(error);
    process.exit(1);
});
