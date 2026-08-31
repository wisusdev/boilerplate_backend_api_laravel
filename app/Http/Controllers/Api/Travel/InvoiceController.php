<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\DteService;
use App\Services\InvoiceBuilder;
use App\Support\InvoiceDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly DteService $dteService,
        private readonly InvoiceBuilder $builder,
    ) {}

    /**
     * GET /api/invoices
     * Lista de facturas con filtros por estado y DTE status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with(['booking.bookable', 'items'])
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
        $invoice->loadMissing(['booking.bookable', 'items']);

        return InvoiceResource::make($invoice);
    }

    /**
     * POST /api/invoices
     * Emite una factura manual, con conceptos del catálogo o líneas libres.
     */
    public function store(InvoiceRequest $request): JsonResponse
    {
        $invoice = $this->builder->create($request->validated());

        return InvoiceResource::make($invoice->load(['items']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/invoices/{invoice}
     * Solo se borran facturas manuales sin DTE: una factura ligada a una reserva
     * o ya declarada al Ministerio de Hacienda no puede desaparecer.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        if (! $invoice->isManual()) {
            return response()->json(['errors' => [[
                'status' => '409',
                'title' => 'invoice.linkedToBooking',
                'detail' => 'Esta factura pertenece a una reserva y no puede eliminarse.',
            ]]], 409);
        }

        if ($invoice->dte_status !== Invoice::DTE_NOT_GENERATED) {
            return response()->json(['errors' => [[
                'status' => '409',
                'title' => 'invoice.dteIssued',
                'detail' => 'Esta factura ya tiene un DTE y no puede eliminarse.',
            ]]], 409);
        }

        $invoice->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/invoices/{invoice}/pdf
     * Descarga la factura con el diseño configurado. `template` permite
     * previsualizar otro sin cambiar el ajuste.
     */
    public function pdf(Request $request, Invoice $invoice)
    {
        $request->validate([
            'template' => ['sometimes', 'string', Rule::in(array_keys(InvoiceDocument::TEMPLATES))],
        ]);

        return response(
            InvoiceDocument::pdf($invoice, $request->string('template')->toString() ?: null),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.InvoiceDocument::filename($invoice).'"',
            ]
        );
    }

    /**
     * PATCH /api/invoices/{invoice}
     * Actualiza datos del receptor (nombre, documento, email) antes de generar el DTE.
     */
    public function update(InvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->builder->update($invoice, $request->validated());

        return InvoiceResource::make($invoice->load(['items']));
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
