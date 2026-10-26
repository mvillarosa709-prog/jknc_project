import { PDFDocument } from 'pdf-lib';
import { Unzip, UnzipInflate, UnzipPassThrough } from 'fflate';

const formatUploadSize = (bytes) => {
    if (bytes >= 1024 ** 3) return `${(bytes / (1024 ** 3)).toFixed(1)} GB`;
    if (bytes >= 1024 ** 2) return `${(bytes / (1024 ** 2)).toFixed(0)} MB`;
    return `${(bytes / 1024).toFixed(0)} KB`;
};

const compressUploadFile = async (file, maxBytes) => {
    const extension = file.name.split('.').pop()?.toLowerCase();
    if (extension === 'pdf' || file.type === 'application/pdf') {
        try {
            const document = await PDFDocument.load(await file.arrayBuffer());
            const bytes = await document.save({ useObjectStreams: true, addDefaultPage: false });
            if (bytes.byteLength >= maxBytes || bytes.byteLength >= file.size) {
                throw new Error('The PDF could not be reduced below the maximum size. It may already be compressed.');
            }
            return new File([bytes], file.name, { type: 'application/pdf', lastModified: file.lastModified });
        } catch (error) {
            if (error instanceof Error && error.message.includes('could not be reduced')) throw error;
            throw new Error('This PDF could not be compressed in the browser. Choose a smaller or unencrypted PDF.');
        }
    }

    const isImage = file.type.startsWith('image/') || ['jpg', 'jpeg', 'png', 'tif', 'tiff'].includes(extension);
    if (!isImage) throw new Error('Automatic compression is available for PDF and image files. This file type must be reduced with another tool.');

    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch {
        throw new Error('This image format cannot be compressed by this browser. Choose a smaller file or convert it to JPG or PNG.');
    }

    const keepPng = extension === 'png';
    const outputType = keepPng ? 'image/png' : 'image/jpeg';
    const outputExtension = keepPng ? 'png' : 'jpg';
    const baseName = file.name.replace(/\.[^.]+$/, '');
    let smallestBlob = null;

    try {
        for (const scale of [1, 0.85, 0.7, 0.58, 0.5]) {
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            const context = canvas.getContext('2d');
            if (!context) throw new Error('Image compression is unavailable in this browser.');
            if (outputType === 'image/jpeg') {
                context.fillStyle = '#fff';
                context.fillRect(0, 0, canvas.width, canvas.height);
            }
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

            const qualities = outputType === 'image/jpeg' ? [0.88, 0.78, 0.68, 0.58] : [undefined];
            for (const quality of qualities) {
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, outputType, quality));
                if (!blob) continue;
                if (!smallestBlob || blob.size < smallestBlob.size) smallestBlob = blob;
                if (blob.size < maxBytes && blob.size < file.size) {
                    return new File([blob], `${baseName}.${outputExtension}`, { type: outputType, lastModified: file.lastModified });
                }
            }
        }
    } finally {
        bitmap.close?.();
    }

    if (!smallestBlob || smallestBlob.size >= file.size || smallestBlob.size >= maxBytes) {
        throw new Error('The image could not be reduced below the maximum while keeping it readable.');
    }

    return new File([smallestBlob], `${baseName}.${outputExtension}`, { type: outputType, lastModified: file.lastModified });
};

const extractUploadArchive = async (archive, { maxBytes, maxFiles = 50, maxTotalBytes = maxBytes * 5 } = {}) => {
    if (!archive.name.toLowerCase().endsWith('.zip')) throw new Error('Choose a supported .zip archive.');
    if (archive.size > maxBytes) throw new Error(`The ZIP archive exceeds the ${formatUploadSize(maxBytes)} upload limit.`);
    const supported = new Set(['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'doc', 'docx', 'txt']);
    const mimeTypes = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', tif: 'image/tiff', tiff: 'image/tiff', doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', txt: 'text/plain' };
    const extracted = [];
    const tasks = [];
    let totalBytes = 0;
    let fileCount = 0;
    let failure = null;
    const unzip = new Unzip((entry) => {
        if (failure || entry.name.endsWith('/')) return;
        const name = entry.name.split(/[\\/]/).pop();
        if (!name || name.startsWith('.') || entry.name.split(/[\\/]/).includes('__MACOSX')) return;
        const extension = name.split('.').pop()?.toLowerCase();
        if (!supported.has(extension)) return;
        fileCount += 1;
        if (fileCount > maxFiles) { failure = new Error(`The ZIP contains more than ${maxFiles} supported files. Split it into smaller uploads.`); return; }
        if (!Number.isFinite(entry.originalSize) || entry.originalSize > maxBytes || totalBytes + entry.originalSize > maxTotalBytes) {
            failure = new Error('The uncompressed files exceed the safe upload size limit. Use a smaller ZIP archive.');
            return;
        }
        totalBytes += entry.originalSize;
        let received = 0;
        const chunks = [];
        const task = new Promise((resolve, reject) => {
            entry.ondata = (error, chunk, final) => {
                if (error) { reject(error); return; }
                received += chunk.byteLength;
                if (received > maxBytes || received > entry.originalSize) { reject(new Error(`${name} exceeds the per-file upload limit.`)); return; }
                chunks.push(chunk);
                if (final) resolve(new File(chunks, name, { type: mimeTypes[extension] || 'application/octet-stream', lastModified: Date.now() }));
            };
        });
        tasks.push(task);
        entry.start();
    });
    unzip.register(UnzipInflate);
    unzip.register(UnzipPassThrough);
    try {
        unzip.push(new Uint8Array(await archive.arrayBuffer()), true);
        if (failure) throw failure;
        extracted.push(...await Promise.all(tasks));
    } catch (error) {
        throw new Error(error.message || 'The ZIP archive could not be read.');
    }
    if (!extracted.length) throw new Error('No supported documents were found in the ZIP archive.');
    return extracted;
};

window.ORDOFileTools = { ...(window.ORDOFileTools || {}), extractArchive: extractUploadArchive };

window.ORDOFileTools = { ...(window.ORDOFileTools || {}), compress: compressUploadFile, formatSize: formatUploadSize };

const getUploadSizeControl = (input) => {
    if (input.dataset.uploadSizeControl) return input._uploadSizeControl;

    const limit = Number(input.dataset.uploadMaxBytes || 0);
    if (!limit) return null;
    const container = document.createElement('div');
    container.className = 'upload-size-assist';
    const message = document.createElement('p');
    message.className = 'upload-size-message';
    message.textContent = `Maximum file size: ${formatUploadSize(limit)} per file.`;
    message.setAttribute('role', 'status');
    message.setAttribute('aria-live', 'polite');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'upload-compress-button';
    button.textContent = 'Compress and use';
    button.hidden = true;
    container.append(message, button);
    let archiveInput = null;
    let archiveButton = null;
    if (input.multiple) {
        archiveInput = document.createElement('input');
        archiveInput.type = 'file';
        archiveInput.accept = '.zip,application/zip,application/x-zip-compressed';
        archiveInput.hidden = true;
        archiveButton = document.createElement('button');
        archiveButton.type = 'button';
        archiveButton.className = 'upload-compress-button';
        archiveButton.textContent = 'Upload Compressed File';
        archiveButton.hidden = true;
        container.append(archiveButton, archiveInput);
        archiveButton.addEventListener('click', () => archiveInput.click());
        archiveInput.addEventListener('change', async () => {
            const archive = archiveInput.files?.[0];
            archiveInput.value = '';
            if (!archive) return;
            try {
                message.classList.remove('error');
                message.textContent = `Expanding ${archive.name} in this browser...`;
                const files = await extractUploadArchive(archive, { maxBytes: limit, maxFiles: 50 });
                const transfer = new DataTransfer();
                [...(input.files || [])].filter((file) => file.size <= limit).concat(files).forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                message.textContent = `${files.length} files were extracted and added to the upload selection.`;
            } catch (error) {
                message.classList.add('error');
                message.textContent = error.message || 'The ZIP archive could not be processed.';
            }
        });
    }
    input.insertAdjacentElement('afterend', container);
    input.dataset.uploadSizeControl = 'true';
    input._uploadSizeControl = { limit, container, message, button, archiveButton };

    button.addEventListener('click', async () => {
        const files = [...(input.files || [])];
        const oversized = files.map((file, index) => ({ file, index })).filter(({ file }) => file.size > limit);
        if (!oversized.length) return;

        button.disabled = true;
        message.classList.remove('error');
        message.textContent = `Compressing ${oversized.length} file(s)…`;
        try {
            const replacements = new Map();
            for (const { file, index } of oversized) replacements.set(index, await compressUploadFile(file, limit));
            const transfer = new DataTransfer();
            files.forEach((file, index) => transfer.items.add(replacements.get(index) || file));
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            const saved = oversized.reduce((total, { file, index }) => total + file.size - replacements.get(index).size, 0);
            const current = input._uploadSizeControl;
            current.message.textContent = `Compressed successfully. Reduced by ${formatUploadSize(saved)}; files are ready to upload.`;
            current.message.classList.remove('error');
            current.button.hidden = true;
        } catch (error) {
            message.classList.add('error');
            message.textContent = error.message || 'The selected file could not be compressed.';
        } finally {
            button.disabled = false;
        }
    });

    return input._uploadSizeControl;
};

document.addEventListener('change', (event) => {
    const input = event.target.closest?.('input[type="file"][data-upload-max-bytes]');
    if (!input || ['document-input', 'smart-document-input'].includes(input.id)) return;
    const control = getUploadSizeControl(input);
    if (!control) return;
    const oversized = [...(input.files || [])].filter((file) => file.size > control.limit);
    control.button.hidden = oversized.length === 0;
    if (control.archiveButton) control.archiveButton.hidden = oversized.length === 0;
    control.message.classList.toggle('error', oversized.length > 0);
    control.message.textContent = oversized.length
        ? `Upload Limit Reached. You've reached the maximum upload limit. Please reduce the file size or upload your documents as a supported compressed archive. Choose an option.`
        : `Maximum file size: ${formatUploadSize(control.limit)} per file.`;
});

document.querySelectorAll('#transmittal-form, #smart-upload-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const invalidInput = [...form.querySelectorAll('input[type="file"][data-upload-max-bytes]')]
            .filter((input) => !['document-input', 'smart-document-input'].includes(input.id))
            .find((input) => [...(input.files || [])].some((file) => file.size > Number(input.dataset.uploadMaxBytes)));
        if (!invalidInput) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const control = getUploadSizeControl(invalidInput);
        if (control) {
            control.message.classList.add('error');
            control.message.textContent = `Maximum file size is ${formatUploadSize(control.limit)}. Compress the oversized file or choose a smaller one.`;
            control.button.hidden = false;
        }
        invalidInput.focus({ preventScroll: true });
    }, true);
});

const tabs = document.querySelectorAll('[data-tab]');
const actionPanel = document.querySelector('[data-action-panel]');
const reopenActionPanel = document.querySelector('[data-reopen-action-panel]');
document.querySelectorAll('[data-close-action-panel]').forEach((button) => button.addEventListener('click', () => {
    if (!actionPanel) return;
    actionPanel.dataset.dismissed = 'true';
    actionPanel.hidden = true;
    if (reopenActionPanel) reopenActionPanel.hidden = false;
    actionPanel.closest('.workspace-tab-layout')?.classList.remove('has-action-center');
}));
reopenActionPanel?.addEventListener('click', () => {
    if (!actionPanel) return;
    actionPanel.dataset.dismissed = 'false';
    actionPanel.hidden = false;
    reopenActionPanel.hidden = true;
    actionPanel.closest('.workspace-tab-layout')?.classList.add('has-action-center');
});
document.querySelectorAll('[data-attachment-browser]').forEach((browser) => {
    const frame = browser.querySelector('[data-attachment-frame]');
    const title = browser.querySelector('[data-attachment-preview-title]');
    const category = browser.querySelector('[data-attachment-preview-category]');
    const unavailable = browser.querySelector('[data-attachment-unavailable]');

    browser.querySelectorAll('[data-attachment-select]').forEach((button) => {
        button.addEventListener('click', () => {
            const name = button.dataset.attachmentName || 'Attachment';
            const previewable = button.dataset.attachmentPreviewable === 'true';
            browser.querySelectorAll('[data-attachment-select]').forEach((item) => {
                item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
            });
            title.textContent = name;
            category.textContent = button.dataset.attachmentCategory || '';
            frame.title = name;
            frame.hidden = !previewable;
            frame.src = previewable ? (button.dataset.attachmentFitUrl || button.dataset.attachmentUrl) : '';
            unavailable.hidden = previewable;
        });
    });

    const expandButton = browser.querySelector('[data-expand-attachment-preview]');
    const setExpanded = (expanded) => {
        browser.classList.toggle('is-expanded', expanded);
        expandButton.setAttribute('aria-pressed', String(expanded));
        expandButton.textContent = expanded ? 'Return to Workspace' : 'Expand Preview';
        document.body.classList.toggle('attachment-preview-expanded', expanded);
        window.dispatchEvent(new Event('resize'));
    };

    expandButton?.addEventListener('click', () => {
        setExpanded(!browser.classList.contains('is-expanded'));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && browser.classList.contains('is-expanded')) setExpanded(false);
    });
});
tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        tabs.forEach((item) => item.classList.toggle('active', item === tab));
        document.querySelectorAll('.tab-content').forEach((panel) => {
            panel.classList.toggle('active', panel.id === `tab-${tab.dataset.tab}`);
        });
        const actionCenterVisible = ['transmittal', 'receipt', 'proof'].includes(tab.dataset.tab);
        const dismissed = actionPanel?.dataset.dismissed === 'true';
        if (actionPanel) actionPanel.hidden = !actionCenterVisible || dismissed;
        if (reopenActionPanel) reopenActionPanel.hidden = !actionCenterVisible || !dismissed;
        document.querySelectorAll('.workspace-tab-layout').forEach((layout) => {
            layout.classList.toggle('has-action-center', actionCenterVisible && !dismissed);
        });
    });
});

const proofUploadModal = document.querySelector('[data-proof-modal]');
if (proofUploadModal) document.body.append(proofUploadModal);

document.querySelectorAll('[data-open-proof-action]').forEach((button) => {
    button.addEventListener('click', () => {
        const actionPanel = document.querySelector('[data-action-panel]');
        const proofTab = document.querySelector('[data-tab="proof"]');

        if (proofTab && !proofTab.classList.contains('active')) {
            proofTab.click();
        }

        if (actionPanel) {
            actionPanel.dataset.dismissed = 'false';
            actionPanel.hidden = false;
            if (reopenActionPanel) reopenActionPanel.hidden = true;
            actionPanel.closest('.workspace-tab-layout')?.classList.add('has-action-center');
        }

        const modal = proofUploadModal;
        if (modal) {
            resetProofForm();
            modal.hidden = false;
            document.body.classList.add('proof-modal-open');
            modal.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true });
            return;
        }

        const uploadButton = actionPanel?.querySelector('[data-toggle-proof-form]');
        const feedback = actionPanel?.querySelector('[data-action-feedback-message]');
        if (feedback) {
            feedback.textContent = uploadButton?.dataset.actionFeedback
                || 'Proof upload is unavailable for your account or this transmittal status.';
            feedback.hidden = false;
            feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
});
document.querySelectorAll('[data-toggle-proof-form]').forEach((button) => {
    button.addEventListener('click', () => {
        const panel = button.closest('[data-action-panel]');
        const modal = proofUploadModal;
        if (modal) {
            resetProofForm();
            modal.hidden = false;
            document.body.classList.add('proof-modal-open');
            modal.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true });
        } else if (button.dataset.actionFeedback) {
            const feedback = panel?.querySelector('[data-action-feedback-message]');
            if (feedback) {
                feedback.textContent = button.dataset.actionFeedback;
                feedback.hidden = false;
            }
        }
    });
});
document.querySelectorAll('[data-close-proof-modal]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = button.closest('[data-proof-modal]');
        if (modal) modal.hidden = true;
        resetProofForm();
        document.body.classList.remove('proof-modal-open');
    });
});
document.querySelector('#proof-recipient')?.addEventListener('change', (event) => {
    const selected = event.currentTarget.selectedOptions[0];
    const receiver = document.querySelector('#proof-receiver-name');
    if (receiver && selected?.dataset.recipientName) receiver.value = selected.dataset.recipientName;
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        if (cameraModal && !cameraModal.hidden) stopCamera();
        document.querySelectorAll('[data-proof-modal]:not([hidden])').forEach((modal) => { modal.hidden = true; });
        if (proofViewModal) proofViewModal.hidden = true;
        document.body.classList.remove('proof-modal-open');
    }
});
const cameraModal = document.querySelector('[data-camera-modal]');
const cameraPreview = document.querySelector('#proof-camera-preview');
const cameraCanvas = document.querySelector('#proof-camera-canvas');
const cameraMessage = document.querySelector('#proof-camera-message');
const cameraPhotoButton = document.querySelector('[data-camera-photo]');
const cameraRecordButton = document.querySelector('[data-camera-record]');
const cameraStopButton = document.querySelector('[data-camera-stop]');
const cameraUseButton = document.querySelector('[data-camera-use]');
let cameraStream = null;
let cameraRecorder = null;
let cameraChunks = [];
let cameraFile = null;

const stopCamera = () => {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    if (cameraPreview) cameraPreview.srcObject = null;
    cameraRecorder = null;
    cameraChunks = [];
    if (cameraModal) cameraModal.hidden = true;
    document.body.classList.remove('proof-modal-open');
};

document.querySelectorAll('[data-open-proof-file]').forEach((button) => {
    button.addEventListener('click', async () => {
        const panel = button.closest('[data-action-panel]');
        const form = panel?.querySelector('#proof-upload-form');
        if (!form || !cameraModal || !cameraPreview) {
            const feedback = panel?.querySelector('[data-action-feedback-message]');
            if (feedback && button.dataset.actionFeedback) {
                feedback.textContent = button.dataset.actionFeedback;
                feedback.hidden = false;
            }
            return;
        }

        cameraModal.hidden = false;
        document.body.classList.add('proof-modal-open');
        cameraFile = null;
        cameraUseButton.disabled = true;
        cameraPhotoButton.disabled = true;
        cameraRecordButton.disabled = true;
        cameraMessage.textContent = 'Requesting camera access…';

        if (!navigator.mediaDevices?.getUserMedia) {
            cameraMessage.textContent = 'Camera access is not available in this browser. Choose a photo or video file instead.';
            return;
        }

        try {
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: true });
            } catch {
                cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            }
            cameraPreview.srcObject = cameraStream;
            await cameraPreview.play();
            cameraPhotoButton.disabled = false;
            cameraRecordButton.disabled = !window.MediaRecorder;
            cameraMessage.textContent = 'Capture a photo or record a video, then use it as the proof file.';
        } catch (error) {
            cameraMessage.textContent = error.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access in your browser or choose a file instead.'
                : 'Could not start the camera. Choose a photo or video file instead.';
        }
    });
});

cameraPhotoButton?.addEventListener('click', () => {
    if (!cameraPreview?.videoWidth || !cameraCanvas) return;
    cameraCanvas.width = cameraPreview.videoWidth;
    cameraCanvas.height = cameraPreview.videoHeight;
    cameraCanvas.getContext('2d')?.drawImage(cameraPreview, 0, 0);
    cameraCanvas.toBlob((blob) => {
        if (!blob) {
            cameraMessage.textContent = 'Could not capture the photo. Please try again.';
            return;
        }
        cameraFile = new File([blob], `transmission-proof-${Date.now()}.jpg`, { type: 'image/jpeg' });
        cameraUseButton.disabled = false;
        cameraMessage.textContent = 'Photo captured. Select “Use captured file” to attach it to the proof record.';
    }, 'image/jpeg', 0.92);
});

cameraRecordButton?.addEventListener('click', () => {
    if (!cameraStream || !window.MediaRecorder) return;
    try {
        cameraChunks = [];
        cameraRecorder = new MediaRecorder(cameraStream);
        cameraRecorder.addEventListener('dataavailable', (event) => { if (event.data.size) cameraChunks.push(event.data); });
        cameraRecorder.addEventListener('stop', () => {
            const type = cameraRecorder?.mimeType || 'video/webm';
            const extension = type.includes('mp4') ? 'mp4' : 'webm';
            cameraFile = new File([new Blob(cameraChunks, { type })], `transmission-proof-${Date.now()}.${extension}`, { type });
            cameraUseButton.disabled = false;
            cameraRecordButton.hidden = false;
            cameraStopButton.hidden = true;
            cameraMessage.textContent = 'Video recorded. Select “Use captured file” to attach it to the proof record.';
        });
        cameraRecorder.start();
        cameraRecordButton.hidden = true;
        cameraStopButton.hidden = false;
        cameraUseButton.disabled = true;
        cameraMessage.textContent = 'Recording video… select Stop recording when finished.';
    } catch {
        cameraMessage.textContent = 'Video recording is not supported by this camera/browser. You can still capture a photo or choose a video file.';
    }
});

cameraStopButton?.addEventListener('click', () => {
    if (cameraRecorder?.state === 'recording') cameraRecorder.stop();
});

document.querySelectorAll('[data-close-camera]').forEach((button) => button.addEventListener('click', stopCamera));
document.querySelector('[data-camera-browse]')?.addEventListener('click', () => {
    const panel = cameraModal?.closest('[data-action-panel]');
    stopCamera();
    panel?.querySelector('#proof-file')?.click();
});

cameraUseButton?.addEventListener('click', () => {
    if (!cameraFile || !proofInput) return;
    const transfer = new DataTransfer();
    transfer.items.add(cameraFile);
    proofInput.files = transfer.files;
    proofInput.dispatchEvent(new Event('change', { bubbles: true }));
    const form = proofInput.closest('#proof-upload-form');
    const proofModal = form?.closest('[data-proof-modal]');
    stopCamera();
    if (proofModal) {
        proofModal.hidden = false;
        document.body.classList.add('proof-modal-open');
        proofModal.querySelector('[data-proof-mode="manual"]')?.click();
        const feedback = proofModal.querySelector('[data-proof-file-hint]');
        if (feedback) feedback.textContent = `${cameraFile.name} is attached. Complete the fields and select Save proof record to add it to the table.`;
    }
});
document.querySelectorAll('[data-action-tab]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector(`[data-tab="${button.dataset.actionTab}"]`)?.click();
    });
});

const proofViewModal = document.querySelector('[data-proof-view-modal]');
document.querySelectorAll('[data-view-proof]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!proofViewModal) return;

        let details;
        try {
            details = JSON.parse(button.dataset.proofView || '{}');
        } catch {
            return;
        }

        proofViewModal.querySelectorAll('[data-proof-view-value]').forEach((output) => {
            output.textContent = details[output.dataset.proofViewValue] || '—';
        });
        const evidenceLink = proofViewModal.querySelector('[data-proof-view-evidence]');
        if (evidenceLink) {
            evidenceLink.hidden = !details.source_url;
            if (details.source_url) evidenceLink.href = details.source_url;
        }
        proofViewModal.hidden = false;
        document.body.classList.add('proof-modal-open');
        proofViewModal.querySelector('[data-close-proof-view]')?.focus({ preventScroll: true });
    });
});

document.querySelectorAll('[data-close-proof-view]').forEach((button) => {
    button.addEventListener('click', () => {
        if (proofViewModal) proofViewModal.hidden = true;
        document.body.classList.remove('proof-modal-open');
    });
});

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-cancel-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        const verb = form.hasAttribute('data-archive-confirm') ? 'archiving' : 'cancelling';
        const reason = window.prompt(`Enter the reason for ${verb} this transmittal:`)?.trim();
        if (!reason) {
            event.preventDefault();
            return;
        }
        form.querySelector('[name="reason"]').value = reason;
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.dataset.originalLabel = submitButton.textContent;
            submitButton.textContent = verb === 'archiving' ? 'Archiving…' : 'Cancelling…';
            submitButton.disabled = true;
        }
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-cancel-confirm][data-submitting="true"]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = false;
            if (submitButton.dataset.originalLabel) submitButton.textContent = submitButton.dataset.originalLabel;
        }
    });
});

const drawer = document.querySelector('[data-transmittal-drawer]');
const drawerBackdrop = document.querySelector('.drawer-backdrop');
const openDrawerButtons = document.querySelectorAll('[data-open-drawer]');
const closeDrawerButtons = document.querySelectorAll('[data-close-drawer]');
const mobilePreviewButton = document.querySelector('[data-show-document-preview]');
const closeDocumentPreviewButton = document.querySelector('[data-close-document-preview]');
let lastDrawerTrigger = null;

const closeDrawer = () => {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawerBackdrop?.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('drawer-visible');
    document.body.classList.remove('document-preview-open');
    lastDrawerTrigger?.focus();
};

openDrawerButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (!drawer) return;
        lastDrawerTrigger = button;
        drawer.classList.add('is-open');
        drawerBackdrop?.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('drawer-visible');
        if (button.hasAttribute('data-focus-upload')) {
            drawer.querySelector('#drop-zone')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            drawer.querySelector('.drawer-close')?.focus();
        }
    });
});

closeDrawerButtons.forEach((button) => button.addEventListener('click', closeDrawer));
mobilePreviewButton?.addEventListener('click', () => document.body.classList.add('document-preview-open'));
closeDocumentPreviewButton?.addEventListener('click', () => document.body.classList.remove('document-preview-open'));
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !drawer?.classList.contains('is-open')) return;
    if (document.body.classList.contains('document-preview-open')) {
        document.body.classList.remove('document-preview-open');
        mobilePreviewButton?.focus();
        return;
    }
    closeDrawer();
});

const directionInput = document.querySelector('select[name="direction"]');
if (directionInput) {
    const counterpartyLabel = document.querySelector('[data-counterparty-label]');
    const officeLabel = document.querySelector('[data-office-label]');
    const syncDirectionLabels = () => {
        const receiving = directionInput.value === 'receive';
        if (counterpartyLabel) counterpartyLabel.textContent = receiving ? 'From' : 'To';
        if (officeLabel) officeLabel.textContent = receiving ? 'To (Office)' : 'From (Office)';
    };
    directionInput.addEventListener('change', syncDirectionLabels);
    syncDirectionLabels();
}

const recipientList = document.querySelector('#recipient-list');
const addRecipient = document.querySelector('#add-recipient');
if (recipientList && addRecipient) {
    const syncRecipients = () => {
        [...recipientList.children].forEach((row, index) => {
            row.querySelector('.recipient-number').textContent = String(index + 2).padStart(2, '0');
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/recipients\[\d+]/, `recipients[${index + 1}]`);
            });
        });
    };

    addRecipient.addEventListener('click', () => {
        if (recipientList.children.length >= 19) return;
        const template = document.querySelector('#recipient-row-template');
        if (!template) return;
        const row = template.content.firstElementChild.cloneNode(true);
        recipientList.append(row);
        syncRecipients();
    });

    recipientList.addEventListener('click', (event) => {
        const remove = event.target.closest('.remove-recipient');
        if (!remove) return;
        remove.closest('.recipient-row').remove();
        syncRecipients();
    });
}

const itemList = document.querySelector('#item-list');
const addItem = document.querySelector('#add-item');
if (itemList && addItem) {
    const syncItems = () => {
        [...itemList.querySelectorAll('.item-editor-row')].forEach((row, index) => {
            row.querySelector('legend').firstChild.textContent = `Item ${index + 1}`;
            row.querySelectorAll('[name^="items["]').forEach((input) => {
                input.name = input.name.replace(/items\[\d+]/, `items[${index}]`);
            });
        });
    };

    addItem.addEventListener('click', () => {
        if (itemList.children.length >= 50) return;
        const template = document.querySelector('#item-row-template');
        if (!template) return;
        itemList.append(template.content.firstElementChild.cloneNode(true));
        syncItems();
        syncWorkspacePreview();
    });

    itemList.addEventListener('click', (event) => {
        if (!event.target.closest('.remove-item')) return;
        event.target.closest('.item-editor-row').remove();
        syncItems();
        syncWorkspacePreview();
    });

    itemList.addEventListener('change', (event) => {
        const input = event.target.closest('[data-file-name-target]');
        if (!input) return;
        const output = input.parentElement.querySelector('.file-selection-name');
        if (output) output.textContent = input.files?.[0]?.name || 'No file selected';
    });
}

document.querySelector('[data-supporting-attachments]')?.addEventListener('change', (event) => {
    const output = document.querySelector('[data-supporting-file-names]');
    if (!output) return;
    const files = [...(event.currentTarget.files || [])].map((file) => file.name);
    output.textContent = files.length ? files.join(', ') : 'No file selected';
});

const syncWorkspacePreview = () => {
    document.querySelectorAll('[data-preview]').forEach((input) => {
        const key = input.dataset.preview;
        let value = input.value.trim();
        if (key === 'direction') value = value.toUpperCase();
        if (key === 'confidentiality') value = value.toUpperCase();
        if (key === 'delivery-type') {
            value = value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
        }
        if (key === 'delivery-method') value = input.selectedOptions[0]?.textContent.trim() || value;
        if (key === 'date' && value) {
            const [year, month, day] = value.split('-').map(Number);
            value = `${year}-${month}-${day}`;
        }
        if (key === 'prepared-at' && value) value = new Date(value).toLocaleString();
        document.querySelectorAll(`[data-preview-output="${key}"]`).forEach((output) => {
            output.textContent = value || (key === 'subject' ? 'Transmittal subject' : 'â€”');
        });
        if (key === 'direction') {
            document.querySelectorAll('[data-preview-party="counterparty"]').forEach((output) => {
                output.textContent = value === 'RECEIVE' ? 'FROM' : 'TO';
            });
            document.querySelectorAll('[data-preview-party="office"]').forEach((output) => {
                output.textContent = value === 'RECEIVE' ? 'To (Office)' : 'From (Office)';
            });
        }
    });

    const draftForm = document.querySelector('#transmittal-form');
    if (draftForm) {
        const previewFields = {
            subject: '[name="subject"]',
            confidentiality: '[name="confidentiality"]',
            date: '[name="transmittal_date"]',
            direction: '[name="direction"]',
            address: '[name="recipient_address"]',
            'recipient-email': '[name="recipients[0][email]"]',
            'delivery-type': '[name="delivery_type"]',
            'approved-by': '[name="approved_by_name"]',
            'delivered-by': '[name="delivered_by"]',
            'received-by': '[name="received_by"]',
            'operations-manager': '[name="operations_manager"]',
            'processing-company': '[name="processing_company"]'
        };

        Object.entries(previewFields).forEach(([key, selector]) => {
            const field = draftForm.querySelector(selector);
            if (!field) return;
            let value = field.value.trim();
            if (key === 'confidentiality' || key === 'direction') value = value.toUpperCase();
            if (key === 'delivery-type') value = field.selectedOptions[0]?.textContent.trim() || value;
            document.querySelectorAll(`[data-preview-output="${key}"]`).forEach((output) => {
                output.textContent = value || '—';
            });
        });
    }

    const direction = draftForm?.querySelector('[name="direction"]')?.value || 'send';
    const office = draftForm?.querySelector('[name="origin_office"]')?.value.trim() || '—';
    const recipient = draftForm?.querySelector('[name="recipients[0][name]"]')?.value.trim()
        || draftForm?.querySelector('[name="related_company"]')?.value.trim()
        || '—';
    document.querySelectorAll('[data-preview-output="from"]').forEach((output) => {
        output.textContent = direction === 'receive' ? recipient : office;
    });
    document.querySelectorAll('[data-preview-output="to"]').forEach((output) => {
        output.textContent = direction === 'receive' ? office : recipient;
    });

    const deliveryActions = [...document.querySelectorAll('input[name="delivery_actions[]"]:checked')]
        .map((input) => input.parentElement.textContent.trim())
        .join(', ');
    document.querySelectorAll('[data-preview-output="delivery-actions"]').forEach((output) => {
        output.textContent = deliveryActions || 'â€”';
    });

    const preparedAt = document.querySelector('input[name="prepared_at"]');
    const preparedAtOutput = document.querySelector('.preview-prepared-at');
    if (preparedAt && preparedAtOutput) {
        preparedAtOutput.textContent = preparedAt.value.replace('T', ' ');
    }

    const itemRows = [...(itemList?.querySelectorAll('.item-editor-row') || [])]
        .map((row) => {
            const item = [...row.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea')]
                .reduce((values, field) => {
                const key = field.name.match(/\[([^\]]+)]$/)?.[1];
                    if (key) values[key] = field.value.trim();
                    return values;
                }, {});
            item.attachment = row.querySelector('input[type="file"]')?.files?.[0]?.name
                || row.querySelector('.item-file-existing a')?.textContent.trim()
                || '';
            return item;
        })
        .filter((item) => Object.values(item).some(Boolean));
    const previewItems = document.querySelector('[data-preview-items]');
    if (previewItems) {
        previewItems.replaceChildren();
        if (itemRows.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.textContent = 'Items will appear here as they are added.';
            row.append(cell);
            previewItems.append(row);
        } else {
            itemRows.forEach((item) => {
                const row = document.createElement('tr');
                [item.particular, item.unique_id, item.quantity, [item.description, item.remarks].filter(Boolean).join(' Â· ')].forEach((value) => {
                    const cell = document.createElement('td');
                    cell.textContent = value || 'â€”';
                    row.append(cell);
                });
                previewItems.append(row);
            });
        }
    }
    const formPreviewItems = document.querySelector('[data-preview-form-items]');
    if (formPreviewItems) {
        formPreviewItems.replaceChildren();
        if (itemRows.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.textContent = 'Items will appear here as they are added.';
            row.append(cell);
            formPreviewItems.append(row);
        } else {
            itemRows.forEach((item, index) => {
                const row = document.createElement('tr');
                [String(index + 1), item.particular, item.unique_id, item.quantity, item.description, item.remarks, item.attachment]
                    .forEach((value) => {
                        const cell = document.createElement('td');
                        cell.textContent = value || 'â€”';
                        row.append(cell);
                    });
                formPreviewItems.append(row);
            });
        }
    }
};
document.querySelectorAll('[data-signatory-select]').forEach((select) => {
    const field = document.querySelector(`[name="${select.dataset.signatorySelect}"]`);
    if (!field) return;

    select.addEventListener('change', () => {
        field.value = select.selectedOptions[0]?.dataset.employeeName || '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
    });
    field.addEventListener('input', () => {
        const selectedName = select.selectedOptions[0]?.dataset.employeeName || '';
        if (field.value !== selectedName) select.value = '';
    });
});
document.querySelector('#transmittal-form')?.addEventListener('input', syncWorkspacePreview);
document.querySelector('#transmittal-form')?.addEventListener('change', syncWorkspacePreview);
syncWorkspacePreview();

const documentInput = document.querySelector('#document-input');
const fileList = document.querySelector('#file-list');
const analyzeButton = document.querySelector('#analyze-files');
const ocrMessage = document.querySelector('#ocr-message');
const dropZone = document.querySelector('#drop-zone');
const transmittalForm = document.querySelector('#transmittal-form');

if (documentInput && fileList && analyzeButton && !document.querySelector('.smart-upload-workspace')) {
    let selectedFiles = [];
    const categories = ['document', 'receipt', 'proof', 'attachment'];
    const maxOcrFiles = Number(analyzeButton.dataset.maxFiles || 10);
    const categoryLabel = (value) => value[0].toUpperCase() + value.slice(1);
    const transmittalZipInput = document.querySelector('#transmittal-zip-input');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const uploadChunkBytes = Number(transmittalForm?.dataset.chunkBytes || 5242880);
    const maxUploadBytes = Number(transmittalForm?.dataset.uploadMaxBytes || 52428800);
    const resumeKey = (file) => `ordo-upload:${encodeURIComponent(file.name)}:${file.size}:${file.lastModified}`;
    const savedUpload = (file) => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(resumeKey(file)) || 'null');
            return saved && /^[0-9a-f-]{36}$/i.test(saved.upload_id) ? saved : null;
        } catch {
            return null;
        }
    };
    const saveUpload = (file) => {
        try {
            sessionStorage.setItem(resumeKey(file), JSON.stringify({
                upload_id: file.resumableUploadId,
                chunk_size: file.resumableChunkSize,
                chunk_count: file.resumableChunkCount,
            }));
        } catch {
            return;
        }
    };
    const forgetUpload = (file) => {
        try {
            sessionStorage.removeItem(resumeKey(file));
        } catch {
            // A browser storage failure should not keep a stale upload ID on this file.
        }
        delete file.resumableUploadId;
        delete file.resumableChunkSize;
        delete file.resumableChunkCount;
        delete file.resumableCompleted;
    };
    const uploadResumable = async (file, restart = false) => {
        if (file.resumableCompleted) return file.resumableUploadId;
        const saved = restart ? null : savedUpload(file);
        if (!file.resumableUploadId && saved) Object.assign(file, {
            resumableUploadId: saved.upload_id,
            resumableChunkSize: Number(saved.chunk_size),
            resumableChunkCount: Number(saved.chunk_count),
        });
        let start = {
            upload_id: file.resumableUploadId,
            chunk_size: file.resumableChunkSize,
            chunk_count: file.resumableChunkCount,
        };
        if (!start.upload_id) {
            const startResponse = await fetch(transmittalForm.dataset.uploadStartUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: file.name, size: file.size, type: file.type || 'application/octet-stream' }),
            });
            start = await startResponse.json();
            if (!startResponse.ok) throw new Error(start.message || `Could not start upload for ${file.name}.`);
            file.resumableUploadId = start.upload_id;
            file.resumableChunkSize = Number(start.chunk_size || uploadChunkBytes);
            file.resumableChunkCount = Number(start.chunk_count);
            saveUpload(file);
        }
        const completeUrl = `${transmittalForm.dataset.uploadStartUrl}/${start.upload_id}/complete`;
        if (file.resumableChunkCount) {
            const completionCheck = await fetch(completeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            const current = await completionCheck.json();
            if (completionCheck.ok) {
                file.resumableCompleted = true;
                return current.upload_id;
            }
            if (completionCheck.status !== 409 || current.message !== 'Some upload chunks are missing.') {
                if (completionCheck.status === 404 || completionCheck.status === 409) {
                    forgetUpload(file);
                    return uploadResumable(file, true);
                }
                throw new Error(current.message || `Could not resume ${file.name}.`);
            }
        }
        const chunkCount = Number(start.chunk_count);
        const chunkSize = Number(start.chunk_size || uploadChunkBytes);
        for (let index = 0; index < chunkCount; index++) {
            const chunk = file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize));
            let response;
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    const body = new FormData();
                    body.append('chunk', chunk, `${file.name}.part`);
                    response = await fetch(`${transmittalForm.dataset.uploadStartUrl}/${start.upload_id}/chunks/${index}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                        body,
                    });
                    if (response.ok || (response.status < 500 && response.status !== 429)) break;
                } catch (error) {
                    if (attempt === 2) throw error;
                }
                if (attempt < 2) await new Promise((resolve) => setTimeout(resolve, 300 * (attempt + 1)));
            }
            const result = await response.json();
            if (!response.ok && response.status === 409 && !restart) {
                forgetUpload(file);
                return uploadResumable(file, true);
            }
            if (!response.ok) throw new Error(result.message || `Upload chunk ${index + 1} failed.`);
            if (ocrMessage) ocrMessage.textContent = `Uploading ${file.name}: ${index + 1} of ${chunkCount} chunksâ€¦`;
        }
        const completeResponse = await fetch(completeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        });
        const completed = await completeResponse.json();
        if (!completeResponse.ok) throw new Error(completed.message || `Could not verify ${file.name}.`);
        if (file.resumableUploadId !== completed.upload_id) throw new Error(`The upload identifier for ${file.name} changed unexpectedly.`);
        file.resumableCompleted = true;
        return completed.upload_id;
    };
    const setInputFiles = () => {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        documentInput.files = transfer.files;
    };

    const renderFiles = () => {
        fileList.replaceChildren();
        selectedFiles.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'file-item';
            const extension = file.name.split('.').pop().slice(0, 4).toUpperCase();
            const copy = document.createElement('span');
            copy.className = 'file-copy';
            const name = document.createElement('strong');
            name.textContent = file.name;
            const size = document.createElement('small');
            size.textContent = `${(file.size / 1024).toFixed(1)} KB`;
            copy.append(name, size);
            const detected = Object.entries(selectedFiles[index].analysisFields || {})
                .filter(([, value]) => value)
                .map(([field, value]) => {
                    const metadata = selectedFiles[index].analysisMetadata?.[field] || {};
                    const confidence = metadata.confidence ? ` Â· ${metadata.confidence} confidence` : '';
                    const source = metadata.source ? ` Â· ${metadata.source}` : '';
                    return `${field.replaceAll('_', ' ')}: ${value}${confidence}${source}`;
                });
            if (detected.length) {
                const suggestions = document.createElement('small');
                suggestions.textContent = `OCR suggestions â€” ${detected.join(' Â· ')}`;
                copy.append(suggestions);
            }

            const select = document.createElement('select');
            select.name = 'upload_categories[]';
            select.setAttribute('aria-label', `Category for ${file.name}`);
            categories.forEach((category) => {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = categoryLabel(category);
                select.append(option);
            });
            const storedCategory = selectedFiles[index].ordoCategory;
            if (storedCategory && categories.includes(storedCategory)) select.value = storedCategory;

            const type = document.createElement('span');
            type.className = 'file-type';
            type.textContent = extension || 'FILE';
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'upload_ocr_tokens[]';
            token.value = selectedFiles[index].analysisToken || '';
            row.append(type, copy, select, token);

            if (file.size > maxUploadBytes) {
                const assist = document.createElement('div');
                assist.className = 'upload-size-assist error';
                const message = document.createElement('p');
                message.className = 'upload-size-message error';
                message.textContent = `You've reached the maximum upload limit. Please reduce the file size or upload your documents as a supported compressed archive.`;
                const compress = document.createElement('button');
                compress.type = 'button';
                compress.className = 'upload-compress-button';
                compress.textContent = 'Compress and use';
                compress.addEventListener('click', async () => {
                    compress.disabled = true;
                    message.textContent = `Compressing ${file.name}…`;
                    try {
                        selectedFiles[index] = await compressUploadFile(file, maxUploadBytes);
                        renderFiles();
                        ocrMessage.classList.remove('error');
                        ocrMessage.textContent = `${selectedFiles[index].name} compressed and is ready to upload.`;
                        if (/\.(pdf|jpe?g|png|tiff?)$/i.test(selectedFiles[index].name)) analyzeButton.click();
                    } catch (error) {
                        message.textContent = error.message || 'The selected file could not be compressed.';
                        compress.disabled = false;
                    }
                });
                const archiveButton = document.createElement('button');
                archiveButton.type = 'button';
                archiveButton.className = 'upload-compress-button';
                archiveButton.textContent = 'Upload Compressed File';
                archiveButton.addEventListener('click', () => transmittalZipInput?.click());
                const archiveHint = document.createElement('small');
                archiveHint.className = 'upload-limit-archive-hint';
                archiveHint.textContent = 'Upload supported ZIP archives to combine multiple documents into one upload.';
                const heading = document.createElement('strong');
                heading.className = 'upload-limit-heading';
                heading.textContent = 'Upload Limit Reached';
                const choose = document.createElement('span');
                choose.className = 'upload-limit-choice';
                choose.textContent = 'Choose an option';
                assist.append(heading, message, choose, compress, archiveButton, archiveHint);
                row.append(assist);
            }
            fileList.append(row);
        });
        analyzeButton.disabled = selectedFiles.length === 0;
        setInputFiles();
    };

    const acceptFiles = (files) => {
        const merged = [...selectedFiles];
        [...files].forEach((file) => {
            if (!merged.some((existing) => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified)) {
                merged.push(file);
            }
        });
        selectedFiles = merged.slice(0, maxOcrFiles);
        if (selectedFiles.length < merged.length && ocrMessage) ocrMessage.textContent = `A maximum of ${maxOcrFiles} files can be attached.`;
        renderFiles();

        const oversized = selectedFiles.filter((file) => file.size > maxUploadBytes);
        if (oversized.length && ocrMessage) {
            ocrMessage.classList.add('error');
            ocrMessage.textContent = `${oversized.length} file(s) exceed the ${formatUploadSize(maxUploadBytes)} per-file maximum. Compress the oversized file(s) from the file list before uploading.`;
        }

        // Automatically start OCR after files are selected or dropped.
        // Let renderFiles() finish first so selectedFiles and the input are synchronized.
        const hasOcrFiles = selectedFiles.some((file) => /\.(pdf|jpe?g|png|tiff?)$/i.test(file.name));
        if (hasOcrFiles && !oversized.length && !analyzeButton.disabled) {
            window.setTimeout(() => {
                if (!analyzeButton.disabled && !selectedFiles.some((file) => file.size > maxUploadBytes) && selectedFiles.some((file) => /\.(pdf|jpe?g|png|tiff?)$/i.test(file.name))) {
                    analyzeButton.click();
                }
            }, 0);
        }
    };

    documentInput.addEventListener('change', () => acceptFiles(documentInput.files));
    transmittalZipInput?.addEventListener('change', async () => {
        const archive = transmittalZipInput.files?.[0];
        transmittalZipInput.value = '';
        if (!archive) return;
        try {
            ocrMessage.classList.remove('error');
            ocrMessage.textContent = `Expanding ${archive.name} in this browser...`;
            selectedFiles = selectedFiles.filter((file) => file.size <= maxUploadBytes);
            const files = await extractUploadArchive(archive, { maxBytes: maxUploadBytes, maxFiles: Math.max(0, maxOcrFiles - selectedFiles.length) });
            acceptFiles(files);
            ocrMessage.textContent = `${files.length} files were extracted and added to the upload queue.`;
        } catch (error) {
            ocrMessage.classList.add('error');
            ocrMessage.textContent = error.message || 'The ZIP archive could not be processed.';
        }
    });
    if (dropZone) {
        ['dragenter', 'dragover'].forEach((name) => dropZone.addEventListener(name, (event) => {
            event.preventDefault();
            dropZone.classList.add('dragover');
        }));
        ['dragleave', 'drop'].forEach((name) => dropZone.addEventListener(name, (event) => {
            event.preventDefault();
            dropZone.classList.remove('dragover');
        }));
        dropZone.addEventListener('drop', (event) => acceptFiles(event.dataTransfer.files));
    }

    analyzeButton.addEventListener('click', async () => {
        if (selectedFiles.some((file) => file.size > maxUploadBytes)) {
            ocrMessage.classList.add('error');
            ocrMessage.textContent = `Files must be under ${formatUploadSize(maxUploadBytes)} each. Compress the oversized file(s) before analysis.`;
            return;
        }
        analyzeButton.disabled = true;
        analyzeButton.textContent = 'Analyzingâ€¦';
        ocrMessage.classList.remove('error');
        ocrMessage.textContent = 'Reading files and extracting document detailsâ€¦';
        const ocrFiles = selectedFiles.filter((file) => /\.(pdf|jpe?g|png|tiff?)$/i.test(file.name));
        if (ocrFiles.length === 0) {
            ocrMessage.textContent = 'OCR classification is available for PDF and image files. Other formats can still be attached and categorized manually.';
            analyzeButton.textContent = ' Classify & extract';
            analyzeButton.disabled = selectedFiles.length === 0;
            return;
        }

        try {
            const analyses = [];
            for (const file of ocrFiles.slice(0, maxOcrFiles)) {
                let analysis;
                if (file.size > 20 * 1024 * 1024) {
                    const uploadId = await uploadResumable(file);
                    const response = await fetch(`${transmittalForm.dataset.uploadStartUrl}/${uploadId}/analyze`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                    });
                    analysis = await response.json();
                    if (!response.ok) throw new Error(analysis.message || `OCR analysis failed for ${file.name}.`);
                } else {
                    const data = new FormData();
                    data.append('documents[]', file);
                    const response = await fetch(analyzeButton.dataset.analyzeUrl || '/transmittals/analyze', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                        body: data,
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'OCR analysis failed. Check the configured OCR engine and try again.');
                    analysis = result.documents[0];
                }
                analyses.push(analysis);
                Object.assign(file, {
                    ordoCategory: analysis.category,
                    analysisToken: analysis.analysis_token,
                    analysisFields: analysis.fields,
                    analysisMetadata: analysis.field_metadata,
                });
            }
            renderFiles();
            const autofill = [
                ['#subject-field', 'subject'],
                ['#reference-field', 'reference'],
                ['#company-field', 'company'],
                ['#address-field', 'recipient_address'],
                ['#recipient-name-field', 'recipient_name'],
                ['#recipient-email-field', 'recipient_email'],
            ];
            autofill.forEach(([selector, field]) => {
                const input = document.querySelector(selector);
                const suggestion = analyses.map((document) => document.fields[field]).find(Boolean);
               if (
    input &&
    typeof suggestion === 'string' &&
    suggestion.trim() !== '' &&
    (!input.value.trim() || input.value.trim() === 'Smart Upload Document')
) {
    input.value = suggestion.trim();
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
            });
            const references = analyses.map((document) => document.fields.reference).filter(Boolean);
            const skipped = Math.max(0, ocrFiles.length - maxOcrFiles);
            ocrMessage.textContent = `Analyzed ${analyses.length} file(s). Categories and detected fields are ready for review.${skipped ? ` ${skipped} file(s) over the ${maxOcrFiles}-file OCR limit remain for manual categorization.` : ''}${references.length ? ` Reference: ${references[0]}.` : ''}`;
        } catch (error) {
            ocrMessage.classList.add('error');
            ocrMessage.textContent = error.message;
        } finally {
            analyzeButton.textContent = 'âœ§ Classify & extract';
            analyzeButton.disabled = selectedFiles.length === 0;
        }
    });

    transmittalForm?.addEventListener('submit', async (event) => {
        if (selectedFiles.some((file) => file.size > maxUploadBytes)) {
            event.preventDefault();
            ocrMessage?.classList.add('error');
            if (ocrMessage) ocrMessage.textContent = `Files must be under ${formatUploadSize(maxUploadBytes)} each. Compress the oversized file(s) before uploading.`;
            return;
        }
        if (transmittalForm.dataset.uploadsReady === 'true') return;
        event.preventDefault();
        if (transmittalForm.dataset.submissionPending === 'true') return;
        transmittalForm.dataset.submissionPending = 'true';
        const submitter = event.submitter;
        const intent = submitter?.value === 'submit' ? 'submit' : 'draft';
        const submitButtons = [...transmittalForm.querySelectorAll('button[type="submit"]')];
        submitButtons.forEach((button) => { button.disabled = true; });
        analyzeButton.disabled = true;
        try {
            for (const file of selectedFiles) await uploadResumable(file);
            let intentInput = transmittalForm.querySelector('input[data-submission-intent]');
            if (!intentInput) {
                intentInput = document.createElement('input');
                intentInput.type = 'hidden';
                intentInput.name = 'intent';
                intentInput.dataset.submissionIntent = 'true';
                transmittalForm.append(intentInput);
            }
            intentInput.value = intent;
            selectedFiles.forEach((file) => {
                const id = document.createElement('input');
                id.type = 'hidden';
                id.name = 'upload_ids[]';
                id.value = file.resumableUploadId;
                transmittalForm.append(id);
            });
            documentInput.removeAttribute('name');
            documentInput.required = false;
            transmittalForm.dataset.uploadsReady = 'true';
            transmittalForm.submit();
        } catch (error) {
            ocrMessage?.classList.add('error');
            if (ocrMessage) ocrMessage.textContent = error.message;
            transmittalForm.dataset.submissionPending = 'false';
            submitButtons.forEach((button) => { button.disabled = false; });
            analyzeButton.disabled = selectedFiles.length === 0;
        }
    });
}

const proofForm = document.querySelector('#proof-upload-form');
const proofInput = document.querySelector('#proof-file');
const proofAnalyzeButton = document.querySelector('#analyze-proof');
const proofOcrMessage = document.querySelector('#proof-ocr-message');
const proofOcrToken = document.querySelector('#proof-ocr-token');
let proofEntryMode = 'manual';
const proofFields = document.querySelector('[data-proof-fields]');
const proofReview = document.querySelector('[data-proof-review]');
const proofConfirm = document.querySelector('[data-proof-confirm]');
const proofSave = document.querySelector('#proof-upload-form [type="submit"]');
const proofModeHint = document.querySelector('[data-proof-mode-hint]');
const proofFileHint = document.querySelector('[data-proof-file-hint]');
const proofBaseAction = proofForm?.action;
const resetProofForm = () => {
    if (!proofForm) return;
    proofForm.action = proofBaseAction;
    proofForm.querySelector('[name="_method"]').value = '';
    proofForm.dataset.editing = 'false';
    proofForm.reset();
    if (proofInput) proofInput.required = true;
    proofInput?.closest('label')?.querySelector('.required')?.removeAttribute('hidden');
    const reasonField = proofForm.querySelector('[data-proof-edit-reason]');
    if (reasonField) {
        reasonField.hidden = true;
        reasonField.querySelector('textarea').disabled = true;
        reasonField.querySelector('textarea').required = false;
    }
    const senderName = proofForm.querySelector('#proof-sender-name');
    if (senderName && !senderName.value) senderName.value = document.querySelector('meta[name="auth-user-name"]')?.content || '';
    const method = proofForm.querySelector('[data-proof-mode="ocr"]');
    method?.removeAttribute('disabled');
    proofForm.querySelector('[data-proof-mode="manual"]')?.click();
    if (proofOcrToken) proofOcrToken.value = '';
    if (proofFileHint) proofFileHint.textContent = 'Choose a file to attach. Switch to OCR mode to read its details automatically.';
    if (proofForm.querySelector('[name="return_to"]')) proofForm.querySelector('[name="return_to"]').value = proofForm.dataset.proofReturn || 'show';
};
const setProofFieldsVisible = (visible) => {
    if (proofFields) {
        proofFields.hidden = !visible;
        proofFields.querySelectorAll('input, select, textarea').forEach((field) => { field.disabled = !visible; });
    }
    if (proofConfirm) proofConfirm.hidden = proofEntryMode !== 'ocr' || !visible;
    if (proofConfirm?.querySelector('input')) {
        proofConfirm.querySelector('input').disabled = proofEntryMode !== 'ocr' || !visible;
        proofConfirm.querySelector('input').required = proofEntryMode === 'ocr' && visible;
    }
    if (proofSave) proofSave.hidden = !visible;
    if (proofReview) proofReview.hidden = proofEntryMode !== 'ocr' || !visible;
};
setProofFieldsVisible(true);

document.querySelectorAll('[data-edit-proof]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!proofForm) return;
        resetProofForm();
        const data = JSON.parse(button.dataset.proof || '{}');
        proofForm.action = button.dataset.updateUrl;
        proofForm.querySelector('[name="_method"]').value = 'PUT';
        proofForm.dataset.editing = 'true';
        Object.entries(data).forEach(([name, value]) => {
            const field = proofForm.querySelector(`[name="${name}"]`);
            if (field && value !== undefined) field.value = value ?? '';
        });
        if (proofInput) proofInput.required = false;
        proofInput?.closest('label')?.querySelector('.required')?.setAttribute('hidden', 'hidden');
        if (proofFileHint) proofFileHint.textContent = 'Optional replacement file. Leave blank to keep the current evidence file.';
        const reasonField = proofForm.querySelector('[data-proof-edit-reason]');
        if (reasonField) {
            reasonField.hidden = false;
            reasonField.querySelector('textarea').disabled = false;
            reasonField.querySelector('textarea').required = true;
        }
        proofForm.querySelector('[data-proof-mode="ocr"]')?.setAttribute('disabled', 'disabled');
        const modal = proofForm.closest('[data-proof-modal]');
        if (modal) modal.hidden = false;
        document.body.classList.add('proof-modal-open');
        proofForm.querySelector('#proof-sender-name')?.focus({ preventScroll: true });
    });
});

document.querySelectorAll('[data-proof-mode]').forEach((button) => {
    button.addEventListener('click', () => {
        proofEntryMode = button.dataset.proofMode;
        document.querySelectorAll('[data-proof-mode]').forEach((tab) => {
            const active = tab === button;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (proofModeHint) proofModeHint.textContent = proofEntryMode === 'ocr'
            ? 'Upload the proof file. OCR will fill the record fields for you to review and correct.'
            : 'Enter the proof details yourself and attach the supporting file.';
        if (proofFileHint) proofFileHint.textContent = proofEntryMode === 'ocr'
            ? 'Choose a PDF or image. OCR starts automatically after upload.'
            : 'Choose a file to attach to the proof record.';
        if (proofAnalyzeButton) proofAnalyzeButton.hidden = proofEntryMode !== 'ocr';
        setProofFieldsVisible(proofEntryMode === 'manual' || Boolean(proofOcrToken?.value));
        if (proofEntryMode === 'ocr' && proofInput?.files?.length) {
            setProofFieldsVisible(false);
            proofAnalyzeButton?.click();
        }
    });
});

document.querySelector('#proof-acknowledged-at')?.addEventListener('input', (event) => {
    const status = document.querySelector('#proof-status');
    if (status) status.value = event.currentTarget.value ? 'acknowledged' : 'uploaded';
});

if (proofForm && proofInput && proofAnalyzeButton && proofOcrMessage && proofOcrToken) {
    proofInput.addEventListener('change', () => {
        proofOcrToken.value = '';
        proofOcrMessage.textContent = '';
        proofOcrMessage.classList.remove('error');
        if (proofEntryMode === 'ocr' && proofInput.files?.length) {
            setProofFieldsVisible(false);
            proofAnalyzeButton.click();
        }
    });

    proofAnalyzeButton.addEventListener('click', async () => {
        const file = proofInput.files?.[0];
        if (!file) {
            proofOcrMessage.textContent = 'Select a proof file before running OCR.';
            proofOcrMessage.classList.add('error');
            return;
        }

        if (/\.(mp4|mov|webm)$/i.test(file.name)) {
            proofOcrMessage.textContent = 'Video files do not support OCR. Enter the receiver and reference manually, review them, and confirm below.';
            setProofFieldsVisible(true);
            return;
        }

        proofAnalyzeButton.disabled = true;
        proofAnalyzeButton.textContent = 'Analyzing proofâ€¦';
        proofOcrMessage.classList.remove('error');
        proofOcrMessage.textContent = 'Extracting proof details for reviewâ€¦';

        try {
            const data = new FormData();
            data.append('documents[]', file);
            const response = await fetch(proofAnalyzeButton.dataset.analyzeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    Accept: 'application/json',
                },
                body: data,
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Proof OCR analysis failed.');
            const analysis = result.documents?.[0];
            if (!analysis?.analysis_token) throw new Error('OCR did not return verifiable analysis for this proof file.');

            proofOcrToken.value = analysis.analysis_token;
            setProofFieldsVisible(true);
            const suggestions = analysis.fields || {};
            const fields = [
                ['recipient_name', '#proof-receiver-name'],
                ['recipient_email', '#proof-receiver-email'],
                ['acknowledged_by', '#proof-acknowledged-by'],
                ['reference', '#proof-reference-number'],
                ['description', '#proof-notes'],
                ['proof_type', '[name="proof_type"]'],
            ];
            fields.forEach(([key, selector]) => {
                const input = document.querySelector(selector);
                if (input && suggestions[key]) input.value = suggestions[key];
            });
            if (suggestions.date) {
                const dateField = document.querySelector('#proof-date');
                const dateValue = suggestions.date.trim();
                const isoDate = dateValue.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
                const localDate = dateValue.match(/^(\d{1,2})[/. -](\d{1,2})[/. -](\d{2,4})$/);
                let normalizedDate = '';
                if (isoDate) normalizedDate = `${isoDate[1]}-${isoDate[2].padStart(2, '0')}-${isoDate[3].padStart(2, '0')}`;
                else if (localDate) {
                    const year = localDate[3].length === 2 ? `20${localDate[3]}` : localDate[3];
                    normalizedDate = `${year}-${localDate[2].padStart(2, '0')}-${localDate[1].padStart(2, '0')}`;
                }
                if (dateField && normalizedDate) dateField.value = normalizedDate;
            }
            if (suggestions.acknowledged_date) {
                const acknowledgedAt = document.querySelector('#proof-acknowledged-at');
                const dateValue = suggestions.acknowledged_date.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
                const localDateValue = suggestions.acknowledged_date.match(/^(\d{1,2})[/. -](\d{1,2})[/. -](\d{2,4})$/);
                if (acknowledgedAt && (dateValue || localDateValue)) {
                    const normalizedDate = dateValue
                        ? `${dateValue[1]}-${dateValue[2].padStart(2, '0')}-${dateValue[3].padStart(2, '0')}`
                        : `${localDateValue[3].length === 2 ? `20${localDateValue[3]}` : localDateValue[3]}-${localDateValue[2].padStart(2, '0')}-${localDateValue[1].padStart(2, '0')}`;
                    acknowledgedAt.value = `${normalizedDate}T00:00`;
                    document.querySelector('#proof-status').value = 'acknowledged';
                }
            }
            if (suggestions.acknowledged_by && !document.querySelector('#proof-acknowledged-by')?.value) {
                document.querySelector('#proof-acknowledged-by').value = suggestions.acknowledged_by;
            }
            if (suggestions.method) {
                const method = suggestions.method.toLowerCase().replaceAll(' ', '_');
                const methodField = document.querySelector('[name="channel"]');
                if (methodField && [...methodField.options].some((option) => option.value === method)) methodField.value = method;
            }
            if (suggestions.recipient_name) {
                const recipientField = document.querySelector('#proof-recipient');
                const match = [...(recipientField?.options || [])].find((option) =>
                    option.dataset.recipientName?.toLowerCase() === suggestions.recipient_name.toLowerCase()
                    || option.textContent.trim().toLowerCase() === suggestions.recipient_email?.toLowerCase()
                );
                if (match) recipientField.value = match.value;
            }

            const entries = Object.entries(suggestions).filter(([field, value]) => value && !['location', 'device'].includes(field));
            proofOcrMessage.replaceChildren();
            const summary = document.createElement('span');
            summary.textContent = entries.length
                ? 'OCR filled the available details. Review or correct them, confirm below, then select Save proof record to add this to the table. Suggestions: '
                : 'OCR completed but found no supported details. Fill in the fields, confirm below, then select Save proof record to add this to the table.';
            proofOcrMessage.append(summary);
            if (entries.length) {
                const list = document.createElement('ul');
                entries.forEach(([field, value]) => {
                    const metadata = analysis.field_metadata?.[field] || {};
                    const item = document.createElement('li');
                    item.textContent = `${field.replaceAll('_', ' ')}: ${value} (${metadata.source || 'OCR'}; ${metadata.confidence || 'unrated'} confidence)`;
                    list.append(item);
                });
                proofOcrMessage.append(list);
            }
        } catch (error) {
            proofOcrMessage.classList.add('error');
            proofOcrMessage.textContent = `${error.message} You can still complete the fields manually and save.`;
            setProofFieldsVisible(true);
        } finally {
            proofAnalyzeButton.disabled = false;
            proofAnalyzeButton.textContent = 'Analyze proof with OCR';
        }
    });
}
