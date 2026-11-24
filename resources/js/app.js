import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('cameraCapture', () => ({
    open: false,
    supported: Boolean(navigator.mediaDevices?.getUserMedia),
    error: null,
    stream: null,
    shot: null,
    deviceId: null,
    facingMode: 'environment',
    devices: [],
    starting: false,

    async openCapture() {
        if (!this.supported) {
            this.error = 'This browser does not support camera capture. Choose a photo file instead.';
            this.open = true;
            return;
        }

        this.open = true;
        this.error = null;
        this.starting = true;

        try {
            const stream = await this.requestStream({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });

            // The modal may have been closed (Escape, backdrop, Cancel) while the
            // permission prompt was open. Do not leave the camera running.
            if (!this.open) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }

            this.stream = stream;
            this.$nextTick(() => this.attach());
            await this.loadDevices();
        } catch (e) {
            this.stream = null;
            this.error = this.describe(e);
        } finally {
            this.starting = false;
        }
    },

    async requestStream(constraints) {
        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (e) {
            // Some webcams reject the environment (rear) facingMode request.
            // Retry without it before treating the capture as a failure.
            if (e?.name === 'OverconstrainedError' && constraints.video?.facingMode) {
                return navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            }

            throw e;
        }
    },

    describe(error) {
        const name = error?.name ?? '';

        if (name === 'NotAllowedError' || name === 'SecurityError') {
            return 'Camera permission was denied. Allow access in your browser, or choose a photo file instead.';
        }

        if (name === 'NotFoundError' || name === 'OverconstrainedError') {
            return 'No camera was found on this device. Choose a photo file instead.';
        }

        if (name === 'NotReadableError') {
            return 'The camera is already in use by another application.';
        }

        if (name === 'NotSupportedError') {
            return 'This browser or device does not support camera capture. Choose a photo file instead.';
        }

        if (name === 'AbortError') {
            return 'The camera could not be started and was interrupted. Close other apps using it, then try again.';
        }

        return `The camera could not be started${name ? ` (${name})` : ''}. Choose a photo file instead.`;
    },

    async loadDevices() {
        try {
            const all = await navigator.mediaDevices.enumerateDevices();
            this.devices = all.filter((device) => device.kind === 'videoinput');
        } catch {
            this.devices = [];
        }
    },

    attach() {
        const video = this.$refs?.video;
        if (video && this.stream) {
            video.srcObject = this.stream;
            video.play().catch(() => {});
        }
    },

    async switchCamera(deviceId = null) {
        if (deviceId && deviceId === this.deviceId) {
            return;
        }

        this.stop();

        let constraints;
        if (deviceId) {
            this.deviceId = deviceId;
            constraints = {
                video: { deviceId: { exact: deviceId } },
                audio: false,
            };
        } else {
            this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
            constraints = {
                video: { facingMode: { exact: this.facingMode } },
                audio: false,
            };
        }

        try {
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            const track = this.stream.getVideoTracks?.()?.[0];
            const settings = track?.getSettings?.();
            if (settings?.deviceId) {
                this.deviceId = settings.deviceId;
            }
            this.$nextTick(() => this.attach());
            await this.loadDevices();
        } catch (e) {
            this.error = this.describe(e);
        }
    },

    capture() {
        const video = this.$refs?.video;

        if (!video || !video.videoWidth) {
            this.error = 'The camera preview is not ready yet. Wait a moment and try again.';
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob((blob) => {
            if (!blob) {
                this.error = 'The photo could not be captured. Try again or choose a photo file.';
                return;
            }

            this.shot = {
                blob,
                url: URL.createObjectURL(blob),
                width: canvas.width,
                height: canvas.height,
            };
            this.$nextTick(() => {
                if (this.$refs?.shot) {
                    this.$refs.shot.src = this.shot.url;
                }
            });
        }, 'image/jpeg', 0.92);
    },

    retake() {
        this.revoke();
        this.shot = null;
        this.$nextTick(() => this.attach());
    },

    usePhoto() {
        if (!this.shot) {
            return;
        }

        const file = new File([this.shot.blob], 'camera-capture.jpg', { type: 'image/jpeg' });

        // The file input lives in the form's scope, so hand the photo back to it
        // through a bubbling DOM event rather than reaching across Alpine scopes.
        this.$root.dispatchEvent(
            new CustomEvent('ordo:camera-photo', { bubbles: true, detail: file })
        );

        this.revoke();
        this.shot = null;
        this.stop();
        this.open = false;
        this.error = null;
    },

    revoke() {
        if (this.shot?.url) {
            URL.revokeObjectURL(this.shot.url);
        }
    },

    stop() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        if (this.$refs?.video) {
            this.$refs.video.srcObject = null;
        }
    },

    close() {
        this.stop();
        this.revoke();
        this.shot = null;
        this.error = null;
        this.starting = false;
        this.open = false;
    },
}));

// Shared row selection for the Unclassified queue and its Bulk Classify modal.
Alpine.store('queueSelection', {
    ids: [],

    count() {
        return this.ids.length;
    },

    has(id) {
        return this.ids.includes(id);
    },

    toggle(id, checked) {
        if (checked && ! this.has(id)) {
            this.ids.push(id);
        }

        if (! checked) {
            this.ids = this.ids.filter((candidate) => candidate !== id);
        }
    },

    set(ids) {
        this.ids = [...ids];
    },

    clear() {
        this.ids = [];
    },
});

/**
 * Index options for a classification, read from the server-provided map.
 */
function indexOptions(indexesByClassification, classificationId) {
    if (! classificationId) {
        return [];
    }

    return (indexesByClassification[classificationId] ?? []).map((index) => ({
        id: String(index.id),
        code: index.code,
        name: index.name,
        label: `${index.code} — ${index.name}`,
    }));
}

Alpine.data('queueTable', (rowIds) => ({
    rowIds: rowIds.map(String),
    selectAll: false,

    isSelected(id) {
        return this.$store.queueSelection.has(String(id));
    },

    toggle(id, checked) {
        this.$store.queueSelection.toggle(String(id), checked);
    },

    toggleAll(checked) {
        this.$store.queueSelection.set(checked ? this.rowIds : []);
    },

    count() {
        return this.$store.queueSelection.count();
    },
}));

/**
 * Inline upload for the Unclassified Records queue.
 *
 * The reference mock drops files straight onto the queue page. This posts to the
 * same intake endpoint the dedicated upload form uses, so there is one
 * server-authoritative set of rules rather than two.
 *
 * On success the page is reloaded rather than patched: the queue also carries
 * the Auto Read status, the file counts and the sidebar badge, all of which the
 * server computes. Reloading is the honest way to show them, and this is an
 * occasional action rather than a per-keystroke one.
 */
/**
 * The file a preview should show, from a record's files.
 *
 * The single definition of that choice in the browser, and the mirror of
 * RecordFile::scopeCurrentMasterFirst(). Both drawers share it so they cannot
 * drift apart, and it exists at all because the server and the browser once
 * disagreed: the server ordered by the file_role *string*, where 'supporting'
 * sorts before 'master', so a record with any attachment previewed the wrong
 * document.
 *
 * Master role first, then the highest version, then the oldest id.
 *
 * @param {Array} files
 */
function currentMaster(files) {
    const rank = (file) => (file.fileRole === 'master' ? 0 : 1);

    // The id tie-break compares the strings directly rather than through
    // localeCompare, so it matches the server's ORDER BY id exactly. The two only
    // differ for characters the collations order differently, and there is no
    // reason to let a sort disagree with the scope it mirrors.
    const byId = (a, b) => (a.id < b.id ? -1 : a.id > b.id ? 1 : 0);

    return [...files].sort((a, b) =>
        rank(a) - rank(b)
        || (b.version ?? 0) - (a.version ?? 0)
        || byId(a, b)
    )[0] ?? null;
}

/**
 * The advanced sections an Index may ask for, in the order the drawer renders
 * them. The keys are the field names the filing form submits, so an
 * advanced_field_config names exactly what it controls.
 */
const ADVANCED_SECTIONS = ['identifiers', 'additional_dates', 'signatories', 'financials'];

Alpine.data('queueUpload', (uploadUrl) => ({
    dragging: false,
    busy: false,
    error: '',
    pending: [],

    uploadUrl,

    human(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = Number(bytes) || 0;
        let unit = 0;

        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit++;
        }

        return `${unit === 0 ? value : value.toFixed(1)} ${units[unit]}`;
    },

    /**
     * Take a FileList from either the picker or a drop and send it.
     *
     * A drop cannot seed the file input, so the dropped files are kept in
     * `pending` and posted from there instead.
     */
    read(fileList) {
        this.error = '';
        this.pending = Array.from(fileList ?? []);

        if (this.pending.length > 0) {
            this.send(this.pending);
        }
    },

    /**
     * Send a photo the camera component captured.
     *
     * The camera lives in its own Alpine root and cannot see this component's
     * state, so it hands the photo back through a bubbling ordo:camera-photo
     * event. There is no file input to seed here — unlike the dedicated intake
     * form, this page builds its FormData by hand — so the photo is posted
     * straight from the event payload.
     *
     * Declared as `camera` rather than left to the count-based inference below,
     * because a single photograph is otherwise indistinguishable from a single
     * file upload.
     */
    acceptPhoto(file) {
        if (! file) {
            return;
        }

        this.error = '';
        this.pending = [file];

        this.send([file], 'camera');
    },

    async send(files, source = null) {
        this.busy = true;
        this.error = '';

        const token = document.querySelector('meta[name="csrf-token"]')?.content
            ?? document.querySelector('input[name="_token"]')?.value;

        const body = new FormData();
        body.append('_token', token);
        // Declared rather than inferred server-side so a multi-file drop is
        // recorded as one multi-file upload rather than N single uploads, and so
        // a camera capture is not recorded as a plain upload.
        body.append('capture_source', source ?? (files.length > 1 ? 'multi_upload' : 'upload'));

        files.forEach((file) => body.append('files[]', file));

        try {
            const response = await fetch(this.uploadUrl, {
                method: 'POST',
                // Accept: application/json turns a rejected file into a 422 with
                // the reason in the body, instead of a 302 back to a form the
                // user is no longer standing on.
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body,
            });

            if (response.status === 422) {
                const payload = await response.json();
                const messages = Object.values(payload.errors ?? {}).flat();

                this.error = messages.length > 0
                    ? messages.join(' ')
                    : 'Those files could not be uploaded.';

                return;
            }

            if (!response.ok) {
                this.error = 'Those files could not be uploaded.';

                return;
            }

            window.location.reload();
        } catch (e) {
            this.error = 'Those files could not be uploaded.';
        } finally {
            this.busy = false;
            this.pending = [];

            // Cleared so re-picking the same file fires a change event; without
            // this the input keeps its old value and the second attempt is a no-op.
            if (this.$refs.input) {
                this.$refs.input.value = '';
            }
        }
    },
}));

Alpine.data('bulkClassify', (indexesByClassification) => ({
    open: false,
    classificationId: '',
    indexId: '',

    get availableIndexes() {
        return indexOptions(indexesByClassification, this.classificationId);
    },

    openModal() {
        this.open = true;
    },

    close() {
        this.open = false;
        this.classificationId = '';
        this.indexId = '';
    },
}));

/**
 * The workspaces offered by the record drawer.
 */
const RECORD_TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'files', label: 'Files' },
    { key: 'extracted', label: 'Extracted Data' },
    { key: 'links', label: 'Linked To' },
    { key: 'integration', label: 'Integration' },
];

/**
 * Flatten a list of strings, or null, into the newline-separated shape the textareas use.
 */
function linesToText(value) {
    return Array.isArray(value) ? value.join('\n') : '';
}

function textToLines(value) {
    return String(value ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

/**
 * Merge the four advanced JSON columns into a single key => value map for display.
 */
function extraDetailMap(record) {
    const groups = {
        Identifiers: record.identifiers,
        'Additional Dates': record.additionalDates,
        Signatories: record.signatories,
        Financials: record.financials,
    };

    return Object.entries(groups).flatMap(([label, values]) => {
        if (!Array.isArray(values) || values.length === 0) {
            return [];
        }

        return values.map((value, index) => [`${label} ${index + 1}`, String(value)]);
    });
}

/**
 * Document re-reading, shared by the record workspace and the classify drawer.
 *
 * Both previews show the same Document Holder, so both offer the same control and
 * both need the same state. Keeping it here means the polling, the progress bar
 * and the wording cannot drift between the two screens.
 *
 * The progress bar is driven by the file's real state rather than by a timer: the
 * server settles the file into a terminal state and the bar reflects where it
 * actually is. Until an extraction engine is installed the honest end state is a
 * finished read that produced no text, and the pane says so in the file's own
 * words instead of showing a spinner that never resolves.
 */
function documentReading() {
    return {
        reReading: false,
        reReadProgress: 0,
        reReadStatus: '',
        reReadMessage: '',
        reReadPoll: null,
        ocrSearchQuery: '',

        escapeHtml(text) {
            return String(text ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        escapeRegex(str) {
            return String(str ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        get highlightedOcrText() {
            const raw = this.master?.ocrText ?? '';
            if (! raw) {
                return '';
            }
            const q = (this.ocrSearchQuery || '').trim();
            if (! q) {
                return this.escapeHtml(raw);
            }
            const regex = new RegExp(`(${this.escapeRegex(q)})`, 'gi');
            return this.escapeHtml(raw).replace(regex, '<mark class="bg-amber-200 text-slate-900 rounded px-0.5 font-bold">$1</mark>');
        },

        get ocrMatchCount() {
            const raw = this.master?.ocrText ?? '';
            const q = (this.ocrSearchQuery || '').trim();
            if (! raw || ! q) {
                return 0;
            }
            const matches = raw.match(new RegExp(this.escapeRegex(q), 'gi'));
            return matches ? matches.length : 0;
        },

        /**
         * The file this pane is showing, or null when there is nothing to read.
         *
         * Overridden by whichever component spreads this in; both already expose a
         * `master` getter, and this reads it rather than assuming a shape.
         */
        get readableFile() {
            return this.master ?? null;
        },

        /**
         * The wording shown when a read finished without producing text.
         *
         * Prefers the reason the server recorded. It is more useful than anything
         * written here and it is the truth: until extraction exists the file
         * genuinely was not read, and saying "no text available" without that
         * would make a working queue look like a fault.
         */
        ocrEmptyMessage() {
            const file = this.readableFile;

            return file?.ocrError
                ? file.ocrError
                : 'No extracted text is available for this document yet.';
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content
                ?? document.querySelector('input[name="_token"]')?.value;
        },

        /**
         * Fill the placeholder the server shipped the route template with.
         *
         * The templates come from route() rather than being hand-built here, so a
         * route that moves breaks loudly on one side only.
         */
        readingUrl(template, fileId) {
            return String(template).replace('__file__', encodeURIComponent(fileId));
        },

        /**
         * Fill a record-scoped route template with the record in view.
         *
         * Used by the Files tab's forms: the drawer is mounted once per page while
         * its forms act on whichever record is open, so the id only exists at
         * runtime.
         */
        recordUrl(template) {
            if (! template || ! this.current?.id) {
                return '#';
            }

            return String(template).replace('__record__', encodeURIComponent(this.current.id));
        },

        readingUrls() {
            const file = this.readableFile;

            if (! file || ! this.reReadUrl || ! this.statusUrl) {
                return null;
            }

            return {
                start: this.readingUrl(this.reReadUrl, file.id),
                status: this.readingUrl(this.statusUrl, file.id),
            };
        },

        /**
         * Re-queue this file and follow it to a terminal state.
         */
        async reRead() {
            if (this.reReading) {
                return;
            }

            const urls = this.readingUrls();

            if (! urls) {
                return;
            }

            this.reReading = true;
            this.reReadProgress = 4;
            this.reReadStatus = 'Queueing document read…';
            this.reReadMessage = '';

            this.revealProgress();

            try {
                const response = await fetch(urls.start, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    this.reReadStatus = 'The document could not be queued.';
                    this.reReadProgress = 0;
                    return;
                }

                await this.followReading(urls.status);
            } catch (e) {
                this.reReadStatus = 'The document could not be queued.';
                this.reReadProgress = 0;
            }
        },

        /**
         * Bring the progress bar into view.
         *
         * It sits below the document, where the reference puts it, so on a tall
         * document it would otherwise appear below the fold and the reader would
         * see nothing happen after pressing Re-read. Scoped to this component's
         * root rather than the document, so a page mounting both drawers cannot
         * scroll the other one's bar into view.
         *
         * `nearest` rather than `center` so a short document is not yanked around.
         */
        revealProgress() {
            const node = this.$el?.querySelector('[data-reread-progress]');

            if (!node) {
                return;
            }

            // The element is x-show'd, so it has to be rendered before it has a
            // box to scroll to.
            this.$nextTick(() => node.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },

        /**
         * Poll the file's real state until it settles.
         *
         * Bounded so a worker that is never running cannot leave the bar turning
         * forever. When the window closes the button simply re-enables; the file's
         * own status is unchanged either way.
         */
        async followReading(statusUrl, attempts = 60) {
            for (let attempt = 0; attempt < attempts; attempt += 1) {
                // eslint-disable-next-line no-await-in-loop
                const state = await this.readingState(statusUrl);

                if (state === null) {
                    this.reReadStatus = 'Reading state could not be confirmed.';
                    this.reReadProgress = 0;
                    return;
                }

                this.applyReadingState(state);

                if (state.ocrTerminal) {
                    return;
                }

                // eslint-disable-next-line no-await-in-loop
                await new Promise((resolve) => setTimeout(resolve, 1000));
            }

            this.reReadStatus = 'Still reading. This will update once the worker settles the file.';
            this.reReadProgress = 90;
        },

        async readingState(statusUrl) {
            try {
                const response = await fetch(statusUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });

                return response.ok ? await response.json() : null;
            } catch (e) {
                return null;
            }
        },

        /**
         * Move the bar and copy onto whatever the server says is true.
         */
        applyReadingState(state) {
            const file = this.readableFile;

            if (file) {
                file.ocrStatus = state.ocrStatus;
                file.ocrStatusLabel = state.ocrStatusLabel;
                file.ocrError = state.ocrError;
                file.ocrText = state.ocrText;
            }

            if (!state.ocrTerminal) {
                this.reReadProgress = state.ocrStatus === 'reading' ? 65 : 35;
                this.reReadStatus = state.ocrStatus === 'reading'
                    ? 'Reading document…'
                    : 'Waiting for the reading worker…';

                return;
            }

            this.reReadProgress = 100;
            this.reReadStatus = state.ocrText
                ? 'Read complete. Extracted text is available.'
                : 'Read complete. No text was extracted.';

            this.reReadMessage = state.ocrText
                ? ''
                : (state.ocrError ?? 'No extracted text is available for this document yet.');

            if (state.ocrText && typeof this.autoDetectFields === 'function' && this.current) {
                if (this.current.files && this.current.files[0]) {
                    this.current.files[0].ocrText = state.ocrText;
                }
                this.autoDetectFields(this.current);
            }
        },

        /**
         * Drop the reading state when the pane goes away.
         *
         * Without this a file left mid-read keeps the bar turning in a drawer
         * that is no longer open, and re-opening it shows a stale run.
         */
        resetReading() {
            this.reReading = false;
            this.reReadProgress = 0;
            this.reReadStatus = '';
            this.reReadMessage = '';
            this.ocrSearchQuery = '';
        },
    };
}

Alpine.data('recordRegister', (rowIds) => ({
    rowIds: rowIds.map(String),

    open(id) {
        if (!id) {
            return;
        }

        window.dispatchEvent(new CustomEvent('ordo:record-open', { detail: { id: String(id) } }));
    },
}));

// Shared so the drawer and its modals read the same workspace payload.
Alpine.store('recordWorkspace', []);

Alpine.data('recordDrawer', (records, pickerUrl, reReadUrl, statusUrl, supportingUrl, versionUrl) => ({
    ...documentReading(),
    open: false,
    pane: 'preview',
    activeTab: 'overview',
    records: records ?? [],
    current: null,
    tabs: RECORD_TABS,
    pickerUrl: pickerUrl ?? '',
    pickerHtml: '',
    pickerError: null,
    reReadUrl: reReadUrl ?? '',
    statusUrl: statusUrl ?? '',
    supportingUrl: supportingUrl ?? '',
    versionUrl: versionUrl ?? '',
    copiedOcr: false,
    copyFailed: false,

    /**
     * Copy the extracted text to the clipboard.
     *
     * The confirmation is the button's own label rather than a toast: the app has
     * no toast, and one was not worth introducing for a single confirmation. It
     * reverts on its own so a second click cannot leave the button reading
     * "Copied" when nothing was copied.
     *
     * Clipboard access is refused outside a secure context, which a plain http
     * development origin is. That case says so rather than silently doing
     * nothing.
     */
    copyOcrText() {
        const text = this.master?.ocrText ?? '';

        if (text === '') {
            return;
        }

        navigator.clipboard?.writeText(text).then(
            () => this.confirmCopy(true),
            () => this.confirmCopy(false),
        );
    },

    confirmCopy(succeeded) {
        this.copiedOcr = succeeded;
        this.copyFailed = ! succeeded;

        setTimeout(() => {
            this.copiedOcr = false;
            this.copyFailed = false;
        }, 2000);
    },

    init() {
        Alpine.store('recordWorkspace', this.records);
    },

    get master() {
        // Mirrors RecordFile::scopeCurrentMasterFirst(): master role first, then
        // the highest version, then the oldest id. The two sides have to agree —
        // while the browser picked the master and the server ordered by the role
        // string, the pane rendered an iframe labelled with the master's name
        // pointed at a supporting file, and showed nothing.
        return currentMaster(this.current?.files ?? []);
    },

    get supportingFiles() {
        return (this.current?.files ?? []).filter((file) => file.fileRole !== 'master');
    },

    get versions() {
        // Same rule as currentMaster(), so a supporting file can never sort above
        // the master and ties fall back to id order the way the server orders them.
        const files = [...(this.current?.files ?? [])].sort((a, b) =>
            (a.fileRole === 'master' ? 0 : 1) - (b.fileRole === 'master' ? 0 : 1)
            || (b.version ?? 0) - (a.version ?? 0)
            || (a.id < b.id ? -1 : a.id > b.id ? 1 : 0)
        );
        const highest = files.reduce((max, file) => Math.max(max, file.version ?? 0), 0);

        return files.map((file) => ({ ...file, isLatest: (file.version ?? 0) === highest }));
    },

    get history() {
        return this.current?.history ?? [];
    },

    get extraDetails() {
        return this.current ? Object.fromEntries(extraDetailMap(this.current)) : {};
    },

    get archived() {
        return Boolean(this.current?.archived);
    },

    get comparison() {
        const record = this.current;

        if (!record) {
            return [];
        }

        const detected = record.detected ?? {};

        return [
            ['Document Title', detected.title, record.title],
            ['Document Date', detected.document_date ?? '', record.documentDate ?? ''],
            ['Source', detected.source ?? '', record.source ?? ''],
            ['External Reference', detected.reference ?? '', record.externalReference ?? ''],
            ['Amount', detected.amount ?? '', this.amount()],
            ['Classification', detected.classification ?? '', record.classificationLabel],
            ['Index', detected.index ?? '', record.indexLabel],
        ].map(([label, found, confirmed]) => ({
            label,
            detected: found === '' || found === null || found === undefined ? '—' : String(found),
            confirmed: confirmed === '' || confirmed === null || confirmed === undefined ? '—' : String(confirmed),
        }));
    },

    get availableIndexes() {
        return [];
    },

    subtitle() {
        const record = this.current;

        if (!record) {
            return '';
        }

        return [
            record.documentReference ? `DOC REF ${record.documentReference}` : null,
            record.id,
            record.indexLabel || null,
        ].filter(Boolean).join(' • ');
    },

    amount() {
        const record = this.current;

        if (!record || record.amount === null || record.amount === undefined) {
            return '—';
        }

        return `${Number(record.amount).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })} ${record.currency ?? ''}`.trim();
    },

    dash(value) {
        return value === null || value === undefined || value === '' ? '—' : String(value);
    },

    initials(value) {
        return String(value ?? '?').slice(0, 2).toUpperCase();
    },

    glyph(file) {
        return file.isImage ? 'IMG' : (file.isPdf ? 'PDF' : 'FILE');
    },

    latestVersion() {
        return this.versions.length > 0 ? this.versions[0].version : 1;
    },

    humanSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let value = bytes ?? 0;
        let unit = 0;

        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }

        return `${unit ? value.toFixed(1) : value} ${units[unit]}`;
    },

    masterMeta() {
        if (! this.master) {
            return 'File information';
        }

        const parts = [this.master.documentType, this.humanSize(this.master.sizeBytes)];

        if (this.master.pageCount) {
            parts.push(`${this.master.pageCount} page(s)`);
        }

        if (this.current?.documentReference) {
            parts.push(`DOC REF ${this.current.documentReference}`);
        }

        return parts.filter(Boolean).join(' • ');
    },

    /**
     * Named `show` rather than `open` on purpose.
     *
     * This component also declares `open` as its visibility state. A duplicate
     * key in an object literal means the later one wins, so the method would have
     * displaced the state; the first `this.open = true` then wrote a boolean over
     * the method, and every later x-on:ordo:record-open call threw
     * "open is not a function". The drawer worked exactly once per page load:
     * open a record, close it, and nothing would open again. Splitting the two
     * names removes the collision rather than documenting it.
     */
    show(id) {
        const record = this.records.find((candidate) => String(candidate.id) === String(id));

        if (! record) {
            return;
        }

        // A previous read on another record must not leave its progress showing
        // against this one.
        this.resetReading();

        this.current = record;
        this.activeTab = 'overview';
        this.pane = 'preview';
        this.pickerError = null;
        this.open = true;
    },

    unlinkAction(linkId) {
        return `/records/links/${linkId}`;
    },

    /**
     * The picker is scoped to the record in view, so its candidate list is fetched
     * on demand rather than shipped for every row on the page.
     */
    async openPicker() {
        if (! this.current || ! this.pickerUrl) {
            return;
        }

        const id = this.current.id;

        this.pickerError = null;
        this.pickerHtml = '';

        try {
            const response = await fetch(this.pickerUrl.replace('__record__', encodeURIComponent(id)), {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (! response.ok) {
                throw new Error('The record picker could not be loaded.');
            }

            this.pickerHtml = await response.text();

            this.$nextTick(() => {
                const modalEl = document.getElementById('recordPickerModal');
                if (modalEl && window.Alpine) {
                    window.Alpine.initTree(modalEl);
                }
                window.dispatchEvent(new CustomEvent('ordo:link-open', { detail: { id: String(id) } }));
            });
        } catch (e) {
            this.pickerHtml = '';
            this.pickerError = e?.message ?? 'The record picker could not be loaded.';
        }
    },

    close() {
        this.open = false;
        this.current = null;
        this.pane = 'preview';
        this.pickerHtml = '';
        this.pickerError = null;
        this.resetReading();
    },
}));

/**
 * The global record picker. Owns only its own form state; the relationship is
 * submitted once and the service writes the inverse on the other record.
 */
/**
 * Global Record Picker — the Integration tab's integration test.
 *
 * The record drawer picks a link target for a record it already has. This starts
 * from the company's records instead, because that is the direction a module
 * integrating with Records actually arrives from, and demonstrating only one
 * direction would not demonstrate the claim.
 *
 * Posts to the same records.links.store the drawer uses. Deliberately not a
 * second implementation of the same write.
 *
 * The list is filtered in the browser over the records the controller shipped,
 * rather than re-fetched per keystroke. The controller caps that list, and the
 * picker says so — a search that silently only covers the most recent page is
 * worse than one that admits its limit.
 */
Alpine.data('globalRecordPicker', (relationships, storeTemplate, records) => ({
    open: false,
    selected: null,
    reference: '',
    query: '',
    relationships: relationships ?? [],
    storeTemplate: storeTemplate ?? '',
    records: records ?? [],

    action() {
        return this.storeTemplate.replace('__record__', encodeURIComponent(this.selected ?? ''));
    },

    get matches() {
        const needle = this.query.trim().toLowerCase();

        if (needle === '') {
            return this.records;
        }

        return this.records.filter((record) =>
            [record.title, record.documentReference, record.recordNumber]
                .some((field) => String(field ?? '').toLowerCase().includes(needle))
        );
    },

    show() {
        this.selected = null;
        this.reference = '';
        this.query = '';
        this.open = true;
    },

    close() {
        this.open = false;
        this.selected = null;
    },

    choose(id, reference) {
        this.selected = id;
        this.reference = reference;
    },

    clear() {
        this.selected = null;
        this.reference = '';
    },
}));

Alpine.data('recordPicker', (payload) => ({
    open: false,
    sourceId: payload?.sourceId ?? null,
    sourceRef: payload?.sourceRef ?? '',
    candidates: payload?.candidates ?? [],
    linkedIds: payload?.linkedIds ?? [],
    mode: 'record',
    query: '',
    target: '',
    linkType: 'related',
    module: '',
    referenceId: '',
    linkedLabel: '',
    notes: '',

    openFor(id) {
        this.open_for(id);
    },

    open_for(id) {
        if (id) {
            this.sourceId = String(id);
        }
        this.open = true;
    },

    visibleCandidates() {
        const needle = this.query.trim().toLowerCase();

        if (needle === '') {
            return this.candidates;
        }

        return this.candidates.filter((candidate) => [candidate.title, candidate.documentReference, candidate.indexLabel]
            .some((field) => String(field ?? '').toLowerCase().includes(needle)));
    },

    isLinked(id) {
        return this.linkedIds.map(String).includes(String(id));
    },

    initials(value) {
        return String(value ?? '?').slice(0, 2).toUpperCase();
    },

    canSubmit() {
        return this.mode === 'record'
            ? this.target !== ''
            : this.module.trim() !== '' && this.referenceId.trim() !== '';
    },

    action() {
        return this.sourceId ? `/records/${this.sourceId}/links` : '#';
    },

    close() {
        this.open = false;
    },
}));

/**
 * Upload as Record, from the Files tab.
 *
 * The one Files action that is not a plain file post: filing means choosing a place
 * in the scheme and consumes a Record Unit, so Classification and Index are
 * required here and optional in the other two actions.
 *
 * Index options are rebuilt rather than filtered in place, so clearing the
 * Classification widens the list back out to every index instead of stranding the
 * reader on an empty selection.
 */
Alpine.data('uploadAsRecord', (action, classifications, indexesByClassification) => ({
    visible: false,
    classificationId: '',
    indexId: '',
    confidential: false,

    action: action ?? '',
    classifications: classifications ?? {},
    indexesByClassification: indexesByClassification ?? {},

    get classificationOptions() {
        return Object.entries(this.classifications).map(([id, name]) => ({
            id: String(id),
            label: `${name}`,
        }));
    },

    get availableIndexes() {
        return indexOptions(this.indexesByClassification, this.classificationId);
    },

    get canSubmit() {
        return this.classificationId !== '' && this.indexId !== '';
    },

    show() {
        this.classificationId = '';
        this.indexId = '';
        this.confidential = false;
        this.visible = true;
    },

    close() {
        this.visible = false;
    },
}));

Alpine.data('recordModals', () => ({
    mode: null,
    recordId: null,
    reason: '',
    form: {
        title: '',
        document_date: '',
        source: '',
        external_reference_number: '',
        amount: '',
        currency: 'PHP',
        notes: '',
        retention_period: '',
        is_confidential: false,
        identifiers: '',
        additional_dates: '',
        signatories: '',
        financials: '',
    },

    find(id) {
        return Alpine.store('recordWorkspace')?.find((record) => String(record.id) === String(id)) ?? null;
    },

    openMetadata(id) {
        const record = this.find(id);

        if (! record) {
            return;
        }

        this.recordId = record.id;

        this.form = {
            title: record.title ?? '',
            document_date: record.documentDate ?? '',
            source: record.source ?? '',
            external_reference_number: record.externalReference ?? '',
            amount: record.amount ?? '',
            currency: record.currency ?? 'PHP',
            notes: record.notes ?? '',
            retention_period: record.retentionPeriod ?? '',
            is_confidential: Boolean(record.isConfidential),
            identifiers: linesToText(record.identifiers),
            additional_dates: linesToText(record.additionalDates),
            signatories: linesToText(record.signatories),
            financials: linesToText(record.financials),
        };

        this.mode = 'edit';
    },

    openArchive(id) {
        const record = this.find(id);

        if (! record) {
            return;
        }

        this.recordId = record.id;
        this.reason = '';
        this.mode = 'archive';
    },

    get archiveRef() {
        return this.find(this.recordId)?.documentReference ?? '—';
    },

    get archiveTitle() {
        return this.find(this.recordId)?.title ?? '';
    },

    editAction() {
        return `/records/${this.recordId}`;
    },

    archiveAction() {
        return `/records/${this.recordId}/archive`;
    },

    close() {
        this.mode = null;
        this.recordId = null;
        this.reason = '';
    },
}));

/**
 * The audit entry properties drawer.
 *
 * Entries ship with the page, so opening one makes no request. The trail itself
 * is append-only; this only reads it.
 */
Alpine.data('auditDrawer', (entries) => ({
    open: false,
    current: null,
    entries: entries ?? [],

    find(id) {
        return this.entries.find((entry) => String(entry.id) === String(id)) ?? null;
    },

    openFor(id) {
        const entry = this.find(id);

        if (! entry) {
            return;
        }

        this.current = entry;
        this.open = true;
    },

    propertyCount() {
        return Object.keys(this.current?.properties ?? {}).length;
    },

    show(value) {
        if (value === null || value === undefined) {
            return '—';
        }

        return typeof value === 'object' ? JSON.stringify(value) : String(value);
    },

    close() {
        this.open = false;
        this.current = null;
    },
}));

Alpine.data('reportBuilder', (indexesByCode, generateUrl, exportUrl, storeRunUrl, historyUrl) => ({
    selected: null,
    busy: false,
    error: '',
    previewHtml: '',
    comments: '',

    // Report History modal state.
    historyOpen: false,
    historyBusy: false,
    historyError: '',
    historyHtml: '',

    indexesByCode: indexesByCode,
    generateUrl: generateUrl,
    exportUrl: exportUrl,
    storeRunUrl: storeRunUrl,
    historyUrl: historyUrl,

    labels: {
        executive: 'Executive Records Report',
        management: 'Management Records Report',
        inventory: 'Records Inventory Report',
        classification: 'Classification Report',
        index: 'Index Report',
        quality: 'Records Quality Report',
    },

    filters: {
        classification: '',
        index: '',
        source: '',
        year: '',
    },

    /**
     * Indexes for the chosen Classification, keyed by Classification code.
     *
     * The Index options are rebuilt rather than filtered in place, because
     * clearing the Classification has to widen them back out to every index
     * rather than leave the list stranded on an empty selection.
     */
    get availableIndexes() {
        if (this.filters.classification === '') {
            return Object.values(this.indexesByCode).flat();
        }

        return this.indexesByCode[this.filters.classification] ?? [];
    },

    label() {
        return this.labels[this.selected] ?? '';
    },

    select(type) {
        this.selected = type;
        this.previewHtml = '';
        this.error = '';
        this.comments = '';

        // Changing report type keeps the filters: the user is usually comparing
        // two reports over the same slice of the register.
    },

    /**
     * The token, read once rather than at each call site.
     */
    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            ?? document.querySelector('input[name="_token"]')?.value;
    },

    /**
     * Ask the server for the preview fragment.
     *
     * The figures are computed server-side rather than in the browser so that the
     * preview and the CSV export are produced by one filter builder and cannot
     * drift apart.
     */
    async generate() {
        this.busy = true;
        this.error = '';

        const token = this.csrfToken();

        const body = new FormData();
        body.append('type', this.selected);

        for (const [key, value] of Object.entries(this.filters)) {
            if (value !== '') {
                body.append(key, value);
            }
        }

        try {
            const response = await fetch(this.generateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                body,
            });

            if (!response.ok) {
                this.error = 'That report could not be generated.';
                return;
            }

            this.previewHtml = await response.text();
        } catch (e) {
            this.error = 'That report could not be generated.';
        } finally {
            this.busy = false;
        }
    },

    /**
     * The export carries the report type and every filter that produced the
     * preview, so the CSV covers exactly what is on screen.
     */
    downloadUrl() {
        const params = new URLSearchParams({ type: this.selected ?? 'inventory' });

        for (const [key, value] of Object.entries(this.filters)) {
            if (value !== '') {
                params.set(key, value);
            }
        }

        return `${this.exportUrl}?${params.toString()}`;
    },

    present() {
        // Recorded before the screen goes fullscreen: requestFullscreen has to
        // run in the same tick as the click, and awaiting the write first would
        // spend that gesture. So the record is fired, fullscreen is entered, and
        // the write finishes on its own.
        this.record('present');

        document.documentElement.requestFullscreen?.();
    },

    /**
     * Download the CSV, recording the report on the way out.
     *
     * Unlike Present this can wait: nothing has to happen inside the click's own
     * gesture, so the write settles before the browser starts the download.
     */
    async download() {
        await this.record('download');

        window.location.href = this.downloadUrl();
    },

    /**
     * Re-fetch an output already in the history list.
     *
     * Deliberately does not record a second entry. It is the act of fetching
     * something already issued, so logging it would fill the history with
     * duplicates of reports the reader has already seen.
     */
    downloadRun(el) {
        const url = el.dataset.downloadUrl;

        if (url) {
            window.location.href = url;
        }
    },

    /**
     * Write this report to the history as an output.
     *
     * Best-effort by design. A reader who has just presented a report should not
     * be stopped short of doing so because the bookkeeping failed, so a rejected
     * write is swallowed rather than surfaced as an error on the report itself.
     * The filters travel rather than the figures: the server rebuilds them with
     * the same filter builder the preview used, so a history entry cannot claim a
     * record count the server did not produce.
     */
    async record(trigger) {
        if (this.selected === null) {
            return;
        }

        const body = new FormData();
        body.append('type', this.selected);
        body.append('trigger', trigger);
        body.append('comments', this.comments ?? '');

        for (const [key, value] of Object.entries(this.filters)) {
            if (value !== '') {
                body.append(key, value);
            }
        }

        try {
            await fetch(this.storeRunUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body,
            });
        } catch (e) {
            // Intentionally ignored; see the note above.
        }
    },

    /**
     * Open the history and fetch its list.
     *
     * Refetched on every open rather than kept from the first: the reader may
     * have presented or downloaded reports since, and a stale list would be
     * missing the very entry they came back for.
     */
    async openHistory() {
        this.historyOpen = true;
        this.historyBusy = true;
        this.historyError = '';

        try {
            const response = await fetch(this.historyUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
            });

            if (!response.ok) {
                this.historyError = 'Report history could not be loaded.';
                return;
            }

            this.historyHtml = await response.text();
        } catch (e) {
            this.historyError = 'Report history could not be loaded.';
        } finally {
            this.historyBusy = false;
        }
    },

    closeHistory() {
        this.historyOpen = false;
    },
}));

Alpine.data('classificationBrowser', (scheme) => ({
    query: '',
    mode: null,
    parent: '',
    scheme,

    // Helper state for Add Classification preset selection
    newClassification: {
        code: '',
        name: '',
        description: '',
    },

    // Edit classification state
    editClassificationData: {
        id: '',
        code: '',
        name: '',
        description: '',
        is_active: true,
    },

    // Edit index state
    editIndexData: {
        id: '',
        classification_code: '',
        code: '',
        name: '',
        description: '',
        is_active: true,
        advanced_field_config: null,
        sections: {
            identifiers: false,
            dates: false,
            signatories: false,
            financials: false,
        },
    },

    /**
     * A block is visible when the term appears anywhere in its text.
     */
    matches(text) {
        if (this.query === '') {
            return true;
        }

        return String(text).toLowerCase().includes(this.query.trim().toLowerCase());
    },

    textFor(code) {
        return (this.scheme ?? []).find((entry) => entry.code === code)?.text ?? '';
    },

    parentName(code) {
        return (this.scheme ?? []).find((entry) => entry.code === code)?.name ?? '';
    },

    get visibleCount() {
        return (this.scheme ?? []).filter((entry) => this.matches(entry.text)).length;
    },

    open(mode, parent = '') {
        this.mode = mode;
        this.parent = parent;
        if (mode === 'classification') {
            this.newClassification = { code: '', name: '', description: '' };
        }
    },

    openEditClassification(data) {
        this.editClassificationData = {
            id: data.id || '',
            code: data.code || '',
            name: data.name || '',
            description: data.description || '',
            is_active: Boolean(data.is_active),
        };
        this.mode = 'edit_classification';
    },

    openEditIndex(data) {
        let sec = { identifiers: false, dates: false, signatories: false, financials: false };
        if (data.advanced_field_config) {
            if (Array.isArray(data.advanced_field_config.sections)) {
                data.advanced_field_config.sections.forEach(s => sec[s] = true);
            } else if (data.advanced_field_config === true) {
                sec = { identifiers: true, dates: true, signatories: true, financials: true };
            }
        }
        this.editIndexData = {
            id: data.id || '',
            classification_code: data.classification_code || '',
            code: data.code || '',
            name: data.name || '',
            description: data.description || '',
            is_active: Boolean(data.is_active),
            sections: sec,
        };
        this.mode = 'edit_index';
    },

    selectStandardScheme(code) {
        const presets = {
            '101': { code: '101', name: 'Client Master File', description: 'Client master file and general client correspondence.' },
            '201': { code: '201', name: 'Human Resources & Personnel', description: 'Employment memos, personnel notices, employee records.' },
            '301': { code: '301', name: 'Entity & Ownership', description: 'Corporate entity documents, incorporation, GIS, minutes, board resolutions.' },
            '401': { code: '401', name: 'BIR & Tax', description: 'BIR registrations, tax returns, assessments, Form 2303, Form 2307.' },
            '601': { code: '601', name: 'Regulatory & Compliance', description: 'Permits, licenses, regulatory registrations and local government compliance.' },
            '701': { code: '701', name: 'Finance', description: 'Financial statements, audits, management accounts, budgets.' },
            '801': { code: '801', name: 'Banking', description: 'Bank statements, signatory cards, loan documents, banking agreements.' },
            '901': { code: '901', name: 'Legal', description: 'Contracts, agreements, litigation records, legal opinions.' },
            '1001': { code: '1001', name: 'Operations', description: 'Operational policies, procedures, SOP records, document control.' },
            '1101': { code: '1101', name: 'Real Property Records', description: 'Property ownership, land titles, technical and cadastral records.' },
            '2001': { code: '2001', name: 'Correspondence', description: 'Official written communications, memoranda, letters.' },
        };
        if (presets[code]) {
            this.newClassification.code = presets[code].code;
            this.newClassification.name = presets[code].name;
            this.newClassification.description = presets[code].description;
        }
    },

    close() {
        this.mode = null;
    },

    indexAction() {
        const base = document.querySelector('base')?.getAttribute('href') ?? '/';
        return `${base.replace(/\/$/, '')}/records/classifications/${encodeURIComponent(this.parent)}/indexes`;
    },

    updateClassificationAction() {
        const base = document.querySelector('base')?.getAttribute('href') ?? '/';
        const target = this.editClassificationData.code || this.editClassificationData.id;
        return `${base.replace(/\/$/, '')}/records/classifications/${encodeURIComponent(target)}/update`;
    },

    updateIndexAction() {
        const base = document.querySelector('base')?.getAttribute('href') ?? '/';
        const parent = this.editIndexData.classification_code;
        const target = this.editIndexData.code || this.editIndexData.id;
        return `${base.replace(/\/$/, '')}/records/classifications/${encodeURIComponent(parent)}/indexes/${encodeURIComponent(target)}/update`;
    },
}));

Alpine.data('discardModal', () => ({
    open: false,
    itemId: null,
    title: null,
    fileCount: 0,
    sizeBytes: 0,

    show(id, title, files, bytes) {
        this.itemId = id;
        this.title = title;
        this.fileCount = files;
        this.sizeBytes = bytes;
        this.open = true;
    },

    get summary() {
        const parts = [`${this.fileCount} file(s)`];

        if (this.sizeBytes > 0) {
            parts.push(this.humanSize(this.sizeBytes));
        }

        return parts.join(' \u00b7 ');
    },

    humanSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let value = bytes ?? 0;
        let unit = 0;

        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }

        return `${unit ? value.toFixed(1) : value} ${units[unit]}`;
    },

    action() {
        return `/records/unclassified/${this.itemId}`;
    },

    close() {
        this.open = false;
        this.itemId = null;
        this.title = null;
        this.fileCount = 0;
        this.sizeBytes = 0;
    },
}));

Alpine.data('classifyDrawer', (items, classifications, indexesByClassification, sources, queueUrl, reReadUrl, statusUrl, initialItemId) => ({
    ...documentReading(),
    open: false,
    pane: 'preview',
    current: null,
    // The item to open on load, when the Integration tab's "Register" sent the
    // reader here with ?open=. Null on an ordinary visit.
    initialItemId: initialItemId ?? null,
    items: items ?? [],
    classifications: classifications ?? {},
    indexesByClassification: indexesByClassification ?? {},
    sources: sources ?? {},
    queueUrl: queueUrl,
    reReadUrl: reReadUrl ?? '',
    statusUrl: statusUrl ?? '',
    form: {
        record_classification_id: '',
        record_index_id: '',
        title: '',
        document_date: '',
        source: '',
        external_reference_number: '',
        retention_period: '',
        identifiers: '',
        additional_dates: '',
        signatories: '',
        financials: '',
        is_confidential: false,
    },

    get master() {
        // Same rule as the record drawer, and the same reason: this used to read
        // files[0], which only agreed with the server by accident because the
        // payload carried no explicit order.
        return currentMaster(this.current?.files ?? []);
    },

    get classificationOptions() {
        return Object.entries(this.classifications).map(([id, name]) => ({
            id: String(id),
            label: `${name}`,
        }));
    },

    get availableIndexes() {
        return indexOptions(this.indexesByClassification, this.form.record_classification_id);
    },

    /**
     * Whether the collapsed panel should open itself, because the Index asks for
     * something in it.
     *
     * Sections an Index merely allows are not required, so the badge refers to the
     * `required` list rather than to the whole config.
     */
    get advancedRequired() {
        const config = this.currentIndexConfig();

        if (config === true) {
            return true;
        }

        if (typeof config !== 'object' || config === null) {
            return false;
        }

        return Array.isArray(config.required) && config.required.length > 0;
    },

    /**
     * Which advanced sections the selected Index actually asks for.
     *
     * AGENTS.md asks for these to be "exposed conditionally based on the selected
     * Index", so an Index that declares nothing shows none of them — the panel
     * still opens by hand and still carries External Reference, Retention Period
     * and Confidential, which are always available.
     *
     * A bare `true` is what this panel already accepted before sections existed and
     * still means all four, so an Index configured the old way keeps working rather
     * than silently losing its fields.
     */
    get advancedSections() {
        const config = this.currentIndexConfig();

        if (config === true) {
            return [...ADVANCED_SECTIONS];
        }

        if (typeof config !== 'object' || config === null || ! Array.isArray(config.sections)) {
            return [];
        }

        // Unknown keys are dropped rather than rendered: a typo in a stored config
        // should show one field too few, not an unlabelled input.
        return config.sections.filter((section) => ADVANCED_SECTIONS.includes(section));
    },

    advancedSectionVisible(section) {
        return this.advancedSections.includes(section);
    },

    currentIndexConfig() {
        const indexId = this.form.record_index_id;

        if (! indexId) {
            return null;
        }

        return Object.values(this.indexesByClassification)
            .flat()
            .find((index) => String(index.id) === String(indexId))?.advanced_field_config ?? null;
    },

    canSubmit() {
        if (! this.current
            || ! this.form.record_classification_id
            || ! this.form.record_index_id
            || this.form.title.trim() === ''
        ) {
            return false;
        }

        if (this.form.source === 'Others' && (! this.form.source_other || this.form.source_other.trim() === '')) {
            return false;
        }

        const config = this.currentIndexConfig();
        if (config && typeof config === 'object' && Array.isArray(config.required)) {
            for (const section of config.required) {
                if (ADVANCED_SECTIONS.includes(section)) {
                    const value = this.form[section];
                    if (! value || String(value).trim() === '') {
                        return false;
                    }
                }
            }
        }

        return true;
    },

    hasNext() {
        return this.nextId() !== null;
    },

    masterMeta() {
        if (! this.master) {
            return 'File information';
        }

        const parts = [this.master.documentType, this.humanSize(this.master.sizeBytes)];

        if (this.master.pageCount) {
            parts.push(`${this.master.pageCount} page(s)`);
        }

        return parts.filter(Boolean).join(' · ');
    },

    humanSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let value = bytes ?? 0;
        let unit = 0;

        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }

        return `${unit ? value.toFixed(1) : value} ${units[unit]}`;
    },

    formAction() {
        return this.current ? `${this.queueUrl}/${this.current.id}` : '#';
    },

    /** Named `show` for the same reason as the record drawer: `open` is this
     *  component's visibility state, and a method of that name would displace it
     *  and then be displaced by it, leaving the drawer openable once per page
     *  load. */
    show(id) {
        const item = this.items.find((candidate) => String(candidate.id) === String(id));

        if (! item) {
            return;
        }

        // A read left running against the previous document must not carry over.
        this.resetReading();

        this.current = item;
        this.pane = 'preview';

        this.form = {
            record_classification_id: item.suggestedClassificationId || '',
            record_index_id: item.suggestedIndexId || '',
            title: item.title || '',
            document_date: item.documentDate || '',
            source: item.source || '',
            external_reference_number: item.externalReference || '',
            retention_period: '',
            identifiers: '',
            additional_dates: '',
            signatories: '',
            financials: '',
            is_confidential: false,
        };

        if (! this.form.record_classification_id || ! this.form.source || ! this.form.document_date) {
            this.autoDetectFields(item);
        }

        if (item.files?.[0] && (item.files[0].ocrStatus === 'queued' || item.files[0].ocrStatus === 'reading')) {
            const urls = this.readingUrls();
            if (urls && urls.status) {
                this.followReading(urls.status);
            }
        }

        this.open = true;
    },

    autoDetectFields(item) {
        const textToAnalyze = [
            item.title || '',
            item.files?.[0]?.name || '',
            item.files?.[0]?.ocrText || '',
        ].join(' ').toLowerCase();

        if (! textToAnalyze.trim()) {
            return;
        }

        const hasWord = (word) => {
            const escaped = word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp(`\\b${escaped}\\b`, 'i').test(textToAnalyze);
        };

        let matchedClassId = '';
        let matchedIndexId = '';

        for (const [classId, indexes] of Object.entries(this.indexesByClassification || {})) {
            for (const idx of (indexes || [])) {
                const idxLabel = String(idx.label || idx.name || '').toLowerCase();
                const idxCode = String(idx.code || '').toLowerCase();

                if (idxCode && hasWord(idxCode)) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if ((idxLabel.includes('2303') || idxLabel.includes('form 2303')) &&
                    (hasWord('2303') || textToAnalyze.includes('certificate of registration'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if ((idxLabel.includes('2307') || idxLabel.includes('form 2307')) &&
                    (hasWord('2307') || textToAnalyze.includes('creditable tax') || textToAnalyze.includes('withholding tax'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('1601') && (hasWord('1601c') || hasWord('1601-c'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('gis') && (hasWord('gis') || textToAnalyze.includes('general information sheet'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('resolution') && (textToAnalyze.includes('board resolution') || textToAnalyze.includes('trustees resolution'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('minutes') && (textToAnalyze.includes('board minutes') || textToAnalyze.includes('minutes of'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if ((idxLabel.includes('articles') || idxLabel.includes('incorporation')) &&
                    (textToAnalyze.includes('articles of incorporation') || textToAnalyze.includes('by-laws') || hasWord('incorporation'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if ((idxLabel.includes('sec cert') || idxLabel.includes('secretary')) &&
                    (textToAnalyze.includes('secretary certificate') || textToAnalyze.includes("secretary's certificate") || hasWord('sec cert'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('bank') && (hasWord('bank') || textToAnalyze.includes('bank statement') || textToAnalyze.includes('bank account'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('contract') && hasWord('contract')) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if (idxLabel.includes('agreement') && hasWord('agreement')) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }

                if ((idxLabel.includes('operation') || idxLabel.includes('delivery')) &&
                    (hasWord('operation') || hasWord('operations') || textToAnalyze.includes('delivery receipt') || textToAnalyze.includes('work order'))) {
                    matchedClassId = String(classId);
                    matchedIndexId = String(idx.id);
                    break;
                }
            }
            if (matchedClassId) break;
        }

        if (! matchedClassId) {
            for (const [classId, className] of Object.entries(this.classifications || {})) {
                const nameLower = String(className).toLowerCase();
                if (hasWord(nameLower) ||
                    (nameLower.includes('tax') && (hasWord('bir') || hasWord('tax') || hasWord('2303') || hasWord('2307'))) ||
                    (nameLower.includes('entity') && (textToAnalyze.includes('board resolution') || textToAnalyze.includes('incorporation') || hasWord('gis'))) ||
                    (nameLower.includes('banking') && hasWord('bank')) ||
                    (nameLower.includes('legal') && (hasWord('contract') || hasWord('agreement')))) {
                    matchedClassId = String(classId);
                    const indexes = this.indexesByClassification[classId] || [];
                    if (indexes.length > 0) {
                        matchedIndexId = String(indexes[0].id);
                    }
                    break;
                }
            }
        }

        if (matchedClassId && ! this.form.record_classification_id) {
            this.form.record_classification_id = matchedClassId;
            if (matchedIndexId && ! this.form.record_index_id) {
                this.form.record_index_id = matchedIndexId;
            }
        }

        if (! this.form.source) {
            let targetSourceName = '';
            if (hasWord('bir') || hasWord('tax') || hasWord('2303') || hasWord('2307') || textToAnalyze.includes('revenue') || textToAnalyze.includes('bureau of internal revenue')) {
                targetSourceName = 'Bureau of Internal Revenue';
            } else if (hasWord('sec') || hasWord('gis') || textToAnalyze.includes('securities and exchange') || textToAnalyze.includes('general information sheet') || textToAnalyze.includes('incorporation')) {
                targetSourceName = 'Securities and Exchange Commission';
            } else if (textToAnalyze.includes('board resolution') || textToAnalyze.includes('board of directors') || hasWord('directors')) {
                targetSourceName = 'Board of Directors';
            } else if (textToAnalyze.includes('corporate secretary') || hasWord('sec cert')) {
                targetSourceName = 'Corporate Secretary';
            } else if (hasWord('rcbc')) {
                targetSourceName = 'RCBC';
            } else if (hasWord('bdo')) {
                targetSourceName = 'BDO';
            } else if (hasWord('operation') || hasWord('operations') || textToAnalyze.includes('delivery')) {
                targetSourceName = 'Management';
            } else if (hasWord('client') || hasWord('customer') || hasWord('supplier')) {
                targetSourceName = 'Supplier';
            }

            if (targetSourceName) {
                const found = Object.entries(this.sources).find(([, name]) => String(name).toLowerCase() === targetSourceName.toLowerCase());
                this.form.source = found ? found[1] : targetSourceName;
            }
        }

        if (! this.form.document_date) {
            const mdy = textToAnalyze.match(/\b(0?[1-9]|1[0-2])[\/\-](0?[1-9]|[12]\d|3[01])[\/\-](\d{4})\b/);
            if (mdy) {
                const [, m, d, y] = mdy;
                this.form.document_date = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            } else {
                const ymd = textToAnalyze.match(/\b(\d{4})[\/\-](0?[1-9]|1[0-2])[\/\-](0?[1-9]|[12]\d|3[01])\b/);
                if (ymd) {
                    const [, y, m, d] = ymd;
                    this.form.document_date = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                } else {
                    const fuzzyOcr = textToAnalyze.match(/(0?[1-9]|1[0-2])[i/\-.](0?[1-9]|[12]\d|3[01])[i/\-.~]([~2\d][\d.]{2,4})/);
                    if (fuzzyOcr) {
                        const [, m, d, rawY] = fuzzyOcr;
                        const cleanY = rawY.replace(/~/g, '2').replace(/[^\d]/g, '');
                        if (cleanY.length === 4) {
                            this.form.document_date = `${cleanY}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                        }
                    }
                }
            }
        }
    },

    nextId() {
        if (! this.current) {
            return null;
        }

        const index = this.items.findIndex((item) => String(item.id) === String(this.current.id));

        if (index === -1 || index + 1 >= this.items.length) {
            return null;
        }

        return this.items[index + 1].id;
    },

    openNext() {
        const next = this.nextId();

        if (next) {
            this.submitThenOpen(next);
        }
    },

    submitThenOpen(nextId) {
        this.$nextTick(() => {
            const form = this.$refs.form ?? document.getElementById('classify-drawer-form');

            if (! form) {
                return;
            }

            if (! form.checkValidity()) {
                form.reportValidity();

                return;
            }

            form.dataset.openNext = nextId;
            form.submit();
        });
    },

    close() {
        this.open = false;
        this.current = null;
        this.pane = 'preview';
        this.resetReading();
    },
}));

/**
 * The Records Management Setup modal.
 *
 * Lives once in the layout, so every page reaches it through the gear in its own
 * page header. Tab content is fetched on open rather than shipped with the page:
 * six tabs of setup data would otherwise be queried and serialised into every
 * Records screen, including the ones nobody opens the modal from.
 *
 * `initialTab` and `initiallyOpen` carry the `setup_tab` flash. A write posted
 * from inside a tab redirects back to the same page, and without those the modal
 * would close on save and the user would lose their place in it.
 */
Alpine.data('recordsSetup', (tabUrl, initialTab, initiallyOpen) => ({
    open: initiallyOpen === true,
    tab: initialTab ?? 'classifications',
    html: '',
    busy: false,
    error: '',

    tabUrl,
    initialTab,

    // The tabs the modal offers, in the order the reference mock presents them.
    // The labels are the mock's, including its singular "Records Classification".
    tabs: [
        { key: 'classifications', label: 'Records Classification' },
        { key: 'sources', label: 'Sources' },
        { key: 'fields', label: 'Record Fields' },
        { key: 'numbering', label: 'Record Numbering' },
        { key: 'usage', label: 'Usage & Storage' },
        { key: 'integration', label: 'Integration' },
    ],

    /**
     * Open the modal, optionally on a named tab.
     *
     * A tab in the event detail is a deliberate jump — the Classifications page
     * uses it to send the user straight to the scheme. Without one the modal keeps
     * whatever tab it was last showing, and on first open loads the default.
     */
    openSetup(tab = null) {
        this.open = true;

        if (tab && tab !== this.tab) {
            this.select(tab);

            return;
        }

        // Opening the modal the first time loads the default tab. Reopening keeps
        // whatever was on screen, so the user does not lose their place after
        // looking at a different tab.
        if (this.html === '') {
            this.select(this.tab);
        }
    },

    close() {
        this.open = false;
    },

    /**
     * Fetch one tab's fragment and show it.
     *
     * The request is guarded by `tab`, the tab asked for: a slow response for a
     * tab the user has already moved on from is discarded rather than painted
     * over the tab now on screen.
     */
    async select(tab) {
        const requested = tab;
        this.tab = requested;
        this.error = '';
        this.busy = true;

        const token = document.querySelector('meta[name="csrf-token"]')?.content
            ?? document.querySelector('input[name="_token"]')?.value;

        try {
            const response = await fetch(this.tabUrl.replace('__TAB__', encodeURIComponent(requested)), {
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
            });

            if (!response.ok) {
                this.error = 'That setup section could not be loaded.';
                return;
            }

            const markup = await response.text();

            if (this.tab !== requested) {
                return;
            }

            this.html = markup;
        } catch (e) {
            this.error = 'That setup section could not be loaded.';
        } finally {
            if (this.tab === requested) {
                this.busy = false;
            }
        }
    },
}));

Alpine.start();
