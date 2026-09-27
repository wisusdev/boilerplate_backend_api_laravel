<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Support\LegalDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Términos, privacidad y cancelación: lectura pública, edición desde el panel.
 */
class LegalDocumentController extends Controller
{
    /** GET /legal: los documentos y la versión vigente que se acepta. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (array $doc) => [
                'type' => 'legal-documents',
                'id' => $doc['slug'],
                'attributes' => $doc,
            ], LegalDocuments::index()),
            'meta' => ['version' => LegalDocuments::version()],
        ]);
    }

    /** GET /legal/{slug} */
    public function show(string $slug): JsonResponse
    {
        abort_unless(LegalDocuments::exists($slug), 404);

        return $this->document(LegalDocuments::get($slug));
    }

    /** PATCH /legal/{slug}: guarda el texto en Markdown. */
    public function update(Request $request, string $slug): JsonResponse
    {
        abort_unless(LegalDocuments::exists($slug), 404);

        $validated = $request->validate([
            'data.attributes.content' => ['required', 'string', 'max:'.LegalDocuments::MAX_LENGTH],
        ]);

        return $this->document(LegalDocuments::save($slug, $validated['data']['attributes']['content']));
    }

    private function document(array $doc): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'legal-documents',
                'id' => $doc['slug'],
                'attributes' => $doc,
            ],
            'meta' => ['version' => LegalDocuments::version()],
        ]);
    }
}
