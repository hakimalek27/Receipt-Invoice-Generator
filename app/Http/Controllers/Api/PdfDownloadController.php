<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\PdfRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfDownloadController extends Controller
{
    public function __construct(
        private readonly PdfRenderService $pdfRender,
    ) {}

    public function show(Request $request, int $document)
    {
        $document = Document::with('pdfRenders')->findOrFail($document);
        if ($document->company_id !== \App\Services\ActiveCompanyResolver::resolve($request->user(), $request) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Company scope violation');
        }

        $paper = $request->query('paper', 'a4') === '60mm' ? '60mm' : 'A4';
        $version = $request->query('version');

        $render = $version
            ? $document->pdfRenders()->where('version', (int) $version)->firstOrFail()
            : $document->pdfRenders()->where('paper_size', $paper)->where('is_current', true)->latest()->first();

        if (! $render) {
            $render = $this->pdfRender->render($document, $paper);
        }

        abort_unless(Storage::disk('local')->exists($render->file_path), 404);

        $downloadName = ($document->official_number ?? 'document-'.$document->id).'.pdf';

        // Default: inline preview in browser. ?download=1 forces a save-as.
        if ($request->boolean('download')) {
            return Storage::disk('local')->download(
                $render->file_path,
                $downloadName,
                ['Content-Type' => 'application/pdf']
            );
        }

        // nginx already emits "X-Frame-Options: SAMEORIGIN" at the server
        // level (with `always`), so emitting it here as well produces a
        // duplicate header. Chrome treats duplicate XFO as invalid and
        // falls back to DENY, which makes the inline preview iframe
        // appear blank. Let nginx be the single source of truth.
        return Storage::disk('local')->response(
            $render->file_path,
            $downloadName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            ]
        );
    }
}
