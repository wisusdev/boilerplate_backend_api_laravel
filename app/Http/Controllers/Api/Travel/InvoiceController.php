<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\DteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly DteService $dteService) {}

    /**
     * GET /api/invoices
     * Lista de facturas con filtros por estado y DTE status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with(['booking.bookable'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('dte_status'), fn ($q) => $q->where('dte_status', $request->string('dte_status')))
            ->when($request->filled('booking_type'), fn ($q) => $q->whereHas('booking', fn ($bq) => $bq->where('bookable_type', Booking::bookableClassFor($request->string('booking_type'))))
            )
            ->latest()
            ->sparseFieldset()
            ->jsonPaginate();

        return InvoiceResource::collection($invoices);
    }

    /**
     * GET /api/invoices/{invoice}
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice->loadMissing(['booking.bookable']);

        return InvoiceResource::make($invoice);
    }

    /**
     * PATCH /api/invoices/{invoice}
     * Actualiza datos del receptor (nombre, documento, email) antes de generar el DTE.
     */
    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        $data = $request->validate([
            'data.attributes.receptor_name' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.attributes.receptor_document' => ['sometimes', 'nullable', 'string', 'max:50'],
            'data.attributes.receptor_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'data.attributes.status' => ['sometimes', 'string', 'in:pending,issued,cancelled'],
        ]);

        $attrs = $data['data']['attributes'] ?? [];
        $invoice->update(array_filter($attrs, fn ($v) => $v !== null));

        return InvoiceResource::make($invoice->fresh());
    }

    /**
     * POST /api/invoices/{invoice}/generate-dte
     * Genera y envía el DTE al Ministerio de Hacienda.
     */
    public function generateDte(Invoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->dteService->processDte($invoice);

            return response()->json([
                'data' => [
                    'type' => 'dte-result',
                    'id' => (string) $invoice->id,
                    'attributes' => [
                        'dte_status' => $invoice->dte_status,
                        'dte_number' => $invoice->dte_number,
                        'dte_generation_code' => $invoice->dte_generation_code,
                        'dte_seal' => $invoice->dte_seal,
                        'dte_accepted_at' => $invoice->dte_accepted_at,
                        'mh_response' => $invoice->mh_response,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => [[
                    'title' => 'Error DTE',
                    'detail' => $e->getMessage(),
                ]],
            ], 422);
        }
    }

    /**
     * GET /api/invoices/{invoice}/preview-dte
     * Devuelve el JSON del DTE sin enviarlo (para revisión previa).
     */
    public function previewDte(Invoice $invoice): JsonResponse
    {
        try {
            $dteJson = $this->dteService->previewDte($invoice);

            return response()->json([
                'data' => [
                    'type' => 'dte-preview',
                    'id' => (string) $invoice->id,
                    'attributes' => $dteJson,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => [['title' => 'Error', 'detail' => $e->getMessage()]],
            ], 422);
        }
    }

    /**
     * POST /api/settings/dte-certificate
     * Sube el certificado .p12 del emisor.
     */
    public function uploadCertificate(Request $request): JsonResponse
    {
        $request->validate([
            'certificate' => ['required', 'file', 'mimes:p12,pfx', 'max:2048'],
            'password' => ['required', 'string'],
        ]);

        try {
            $tempPath = $request->file('certificate')->getRealPath();
            $storagePath = $this->dteService->uploadCertificate($tempPath, $request->input('password'));

            return response()->json([
                'data' => [
                    'type' => 'dte-certificate',
                    'attributes' => [
                        'path' => $storagePath,
                        'message' => 'Certificado cargado y validado correctamente.',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => [['title' => 'Error de certificado', 'detail' => $e->getMessage()]],
            ], 422);
        }
    }
}
