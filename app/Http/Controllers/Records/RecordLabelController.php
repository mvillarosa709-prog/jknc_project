<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Services\Records\RecordQrCode;
use Illuminate\View\View;

/**
 * The printable Record Label — the last step of the core flow.
 *
 * Classify & File ends at a Document Reference on screen; the label is what puts
 * that reference on the folder in front of the person who will open it next, and
 * the QR is what gets them back to the record without typing anything.
 *
 * The code resolves to the record's preview route rather than to a bare id, so
 * scanning it lands on a URL the existing record middleware already scopes to the
 * active company and withholds from a role that may not see a confidential record.
 * Nothing about the disk path or the stored file is exposed by the label.
 *
 * Authorised by can:view,record, so this route is the one place the policy is
 * attached directly rather than through the FormRequest convention the rest of the
 * app uses. It is deliberately the stricter of the two.
 */
class RecordLabelController extends Controller
{
    public function __construct(private readonly RecordQrCode $qr) {}

    public function show(Record $record): View
    {
        $record->loadMissing(['classification', 'index', 'company']);

        return view('records.label', [
            'record' => $record,
            // The destination the QR encodes. The preview route is the one a
            // reader wants: it streams the master file straight into a browser.
            'target' => route('records.preview', ['record' => $record->id]),
            'qr' => $this->qr->svg(route('records.preview', ['record' => $record->id])),
        ]);
    }
}