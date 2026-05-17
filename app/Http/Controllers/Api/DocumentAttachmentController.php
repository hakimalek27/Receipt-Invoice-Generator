<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentAttachmentController extends Controller
{
    public function index(Request $request, int $document): JsonResponse
    {
        $document = $this->scopedDocument($request, $document);

        return response()->json($document->attachments);
    }

    public function store(Request $request, int $document): JsonResponse
    {
        $document = $this->scopedDocument($request, $document);
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
            'caption' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'include_in_pdf' => 'nullable|boolean',
        ]);

        $file = $data['file'];
        $originalName = str_replace('\\', '/', $file->getClientOriginalName());
        if (str_contains($originalName, '/') || str_contains($originalName, '..')) {
            throw ValidationException::withMessages([
                'file' => 'Attachment filename must not contain path segments.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Attachment type is not allowed.',
            ]);
        }

        $safeName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs(
            "documents/{$document->company_id}/{$document->id}/attachments",
            $safeName,
            'local'
        );

        $attachment = DocumentAttachment::create([
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'original_name' => $originalName,
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'caption' => $data['caption'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($document->attachments()->max('sort_order') + 1),
            'include_in_pdf' => $data['include_in_pdf'] ?? true,
        ]);

        return response()->json($attachment, 201);
    }

    public function reorder(Request $request, int $document): JsonResponse
    {
        $document = $this->scopedDocument($request, $document);
        $data = $request->validate([
            'attachments' => 'required|array',
            'attachments.*.id' => 'required|integer',
            'attachments.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($data['attachments'] as $item) {
            DocumentAttachment::where('company_id', $document->company_id)
                ->where('document_id', $document->id)
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json($document->fresh('attachments')->attachments);
    }

    public function destroy(Request $request, int $document, int $attachment): JsonResponse
    {
        $document = $this->scopedDocument($request, $document);
        $attachment = DocumentAttachment::where('company_id', $document->company_id)
            ->where('document_id', $document->id)
            ->findOrFail($attachment);

        Storage::disk('local')->delete($attachment->storage_path);
        $attachment->delete();

        return response()->json(['deleted' => true]);
    }

    private function scopedDocument(Request $request, int $documentId): Document
    {
        $document = Document::findOrFail($documentId);
        if ($document->company_id !== $request->user()->company_id && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Company scope violation');
        }

        return $document;
    }
}
