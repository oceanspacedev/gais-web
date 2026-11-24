<?php

namespace App\Http\Controllers;

use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\Task;
use App\Support\Letterhead;
use App\Support\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    public function downloadAssetTransfer($id)
    {
        $assetTransfer = AssetTransfer::findOrFail($id);
        $this->authorize('view', $assetTransfer);

        $assetTransfer->load('fromUser.jobTitle', 'toUser.jobTitle', 'businessEntity.legalEntity', 'details.asset.category', 'details.asset.brand', 'details.asset.attributes');

        $status = $assetTransfer->documentCode();

        $letterhead = Letterhead::for($assetTransfer->businessEntity);

        $letterNumber = $assetTransfer->letter_number;
        $toUserName = strtolower(str_replace(' ', '_', $assetTransfer->toUser->name));
        $toUserJobTitle = $assetTransfer->toUser->jobTitle ? strtolower(str_replace(' ', '_', $assetTransfer->toUser->jobTitle->title)) : 'no_title';

        $fileName = $this->safeDownloadFilename("{$status}_{$letterNumber}_{$toUserName}_{$toUserJobTitle}.pdf");

        $pdf = Pdf::loadView('pdf.asset-transfer', compact('assetTransfer', 'letterhead'));

        return $pdf->download($fileName);
    }

    public function downloadTaskCompletion($id)
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);

        $task->load('businessEntity.legalEntity');

        $letterhead = Letterhead::for($task->businessEntity);

        $fileName = strtolower(str_replace(' ', '_', $task->name));
        $attachments = $this->taskAttachmentsHtml($task);
        $pdf = Pdf::loadView('pdf.task-completion', compact('task', 'letterhead', 'attachments'));

        return $pdf->download($this->safeDownloadFilename('berita_acara_pengerjaan_'.$fileName.'.pdf'));
    }

    public function previewTaskCompletion($id)
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);

        $task->load('businessEntity.legalEntity');

        $letterhead = Letterhead::for($task->businessEntity);

        $fileName = strtolower(str_replace(' ', '_', $task->name));
        $attachments = $this->taskAttachmentsHtml($task);
        $pdf = Pdf::loadView('pdf.task-completion', compact('task', 'letterhead', 'attachments'));

        return $pdf->stream($this->safeDownloadFilename('berita_acara_pengerjaan_'.$fileName.'.pdf'));
    }

    public function previewLetterhead(BusinessEntity $businessEntity)
    {
        $this->authorize('view', $businessEntity);

        $businessEntity->load('legalEntity');
        $letterhead = Letterhead::for($businessEntity);

        return Pdf::loadView('pdf.letterhead-preview', compact('businessEntity', 'letterhead'))
            ->stream($this->safeDownloadFilename('kop_surat_'.strtolower($businessEntity->name).'.pdf'));
    }

    /**
     * Content-Disposition filenames cannot contain "/" or "\".
     * Letter numbers like "221218.CS/000297" must be sanitized.
     */
    private function safeDownloadFilename(string $fileName): string
    {
        $fileName = str_replace(['/', '\\'], '-', $fileName);
        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) ?: 'document.pdf';

        return $fileName;
    }

    private function taskAttachmentsHtml(Task $task): string
    {
        return collect(json_decode($task->attachment))->map(function ($image) {
            $imagePath = StoredFile::imageDataUri('public', $image);

            return "<img src='{$imagePath}' alt='Lampiran' style='max-width: 100%; height: auto; margin: 10px 0;'>";
        })->implode('');
    }
}
