<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Devuelve los documentos institucionales activos (con el texto extraído de los PDFs)
     * para que el agente de IA en n8n pueda responder dudas de los estudiantes del ITT.
     */
    public function index(Request $request)
    {
        $query = Document::with('department')
            ->where('is_active', true);

        if ($request->filled('department')) {
            $dept = $request->input('department');
            $query->whereHas('department', function ($q) use ($dept) {
                $q->where('name', 'like', '%' . $dept . '%');
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('extracted_text', 'like', '%' . $search . '%');
            });
        }

        $documents = $query->orderBy('department_id')->orderBy('title')->get()->map(function (Document $doc) {
            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'department' => $doc->department?->name ?? 'General ITT',
                'description' => $doc->description,
                'content' => $doc->extracted_text,
                'updated_at' => $doc->updated_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'count' => $documents->count(),
            'documents' => $documents,
        ]);
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'department_id' => 'nullable|integer|exists:departments,id',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'department_id' => $validated['department_id'] ?? $request->user()?->department_id,
            'user_id' => $request->user()?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Documento cargado y procesado correctamente',
            'data' => $document,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $document = Document::with('department')->findOrFail($id);

        return response()->json([
            'id' => $document->id,
            'title' => $document->title,
            'department' => $document->department?->name ?? 'General ITT',
            'description' => $document->description,
            'content' => $document->extracted_text,
        ]);
    }

    /**
     * Download the file securely.
     */
    public function download(string $id)
    {
        $document = Document::findOrFail($id);

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $document->title) . '.pdf';

        return Storage::disk('public')->download($document->file_path, $safeName, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $document = Document::findOrFail($id);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }
}
