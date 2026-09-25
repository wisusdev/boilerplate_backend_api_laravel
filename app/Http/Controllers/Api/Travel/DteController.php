<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\DteContingencia;
use App\Models\DteDocument;
use App\Models\DteInvalidacion;
use App\Models\Invoice;
use App\Services\Dte\DteContingencyService;
use App\Services\Dte\DteDelivery;
use App\Services\Dte\DteException;
use App\Services\Dte\DteInvalidationService;
use App\Services\Dte\DtePendingException;
use App\Services\Dte\DteRepresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Eventos de facturación electrónica: invalidación de un DTE sellado y
 * seguimiento de las contingencias.
 */
class DteController extends Controller
{
    public function __construct(
        private readonly DteInvalidationService $invalidations,
        private readonly DteContingencyService $contingencies,
        private readonly DteRepresentation $representation,
        private readonly DteDelivery $delivery,
    ) {}

    /**
     * GET /invoices/{invoice}/dte/pdf
     * Representación gráfica del DTE vigente de la factura.
     */
    public function pdf(Invoice $invoice)
    {
        return $this->documentPdf($this->currentDocument($invoice));
    }

    /** GET /dte/documents/{dteDocument}/pdf — cualquier DTE (factura, nota…). */
    public function documentPdf(DteDocument $dteDocument)
    {
        return response($this->representation->pdf($dteDocument), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->representation->filename($dteDocument, 'pdf').'"',
        ]);
    }

    /**
     * GET /invoices/{invoice}/dte/json
     * Archivo DTE: el JSON firmado, con la firma y (si lo tiene) el sello.
     */
    public function json(Invoice $invoice)
    {
        return $this->documentJson($this->currentDocument($invoice));
    }

    /** GET /dte/documents/{dteDocument}/json */
    public function documentJson(DteDocument $dteDocument)
    {
        return response($this->representation->json($dteDocument), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$this->representation->filename($dteDocument, 'json').'"',
        ]);
    }

    /**
     * POST /invoices/{invoice}/dte/send
     * Envía el PDF y el JSON al receptor. `to` (opcional) sustituye al correo
     * que declara el DTE. También funciona en ambiente de pruebas: es un envío
     * a mano y deliberado.
     */
    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->documentSend($request, $this->currentDocument($invoice));
    }

    /** POST /dte/documents/{dteDocument}/send */
    public function documentSend(Request $request, DteDocument $dteDocument): JsonResponse
    {
        $request->validate(['data.attributes.to' => ['nullable', 'email', 'max:150']]);

        try {
            $doc = $this->delivery->deliver($dteDocument, $request->input('data.attributes.to'));
        } catch (DteException $e) {
            return response()->json(['errors' => [['title' => 'Error de envío', 'detail' => $e->getMessage()]]], 422);
        }

        return response()->json(['data' => [
            'type' => 'dte-deliveries',
            'id' => (string) $doc->id,
            'attributes' => [
                'entregado_at' => $doc->entregado_at,
                'entregado_a' => $doc->entregado_a,
                'entregado_con_sello' => $doc->entregado_con_sello,
            ],
        ]]);
    }

    /** El DTE vigente de la factura: el último que no fue rechazado. */
    private function currentDocument(Invoice $invoice): DteDocument
    {
        $doc = $invoice->dteDocuments()->where('estado', '!=', DteDocument::REJECTED)->latest('id')->first();
        abort_if(! $doc, 404, 'La factura no tiene un DTE emitido.');

        return $doc;
    }

    /**
     * POST /invoices/{invoice}/invalidate-dte
     * 201 con sello; 202 sin respuesta del MH (se reenvía solo); 422 regla incumplida o rechazo.
     */
    public function invalidate(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->invalidateOwner($request, $invoice);
    }

    /** POST /credit-notes/{creditNote}/invalidate-dte */
    public function invalidateNote(Request $request, CreditNote $creditNote): JsonResponse
    {
        return $this->invalidateOwner($request, $creditNote);
    }

    private function invalidateOwner(Request $request, Invoice|CreditNote $owner): JsonResponse
    {
        $tipos = ['13', '36', '03', '02', '37'];
        $data = $request->validate([
            'data.type' => ['required', 'in:dte-invalidations'],
            'data.attributes.tipo_anulacion' => ['required', 'integer', Rule::in([1, 2, 3])],
            'data.attributes.motivo' => ['nullable', 'string', 'max:200'],
            'data.attributes.codigo_generacion_r' => ['nullable', 'string', 'size:36'],
            'data.attributes.responsable_nombre' => ['nullable', 'string', 'max:100'],
            'data.attributes.responsable_tipo_doc' => ['nullable', Rule::in($tipos)],
            'data.attributes.responsable_num_doc' => ['nullable', 'string', 'max:20'],
            'data.attributes.solicita_nombre' => ['required', 'string', 'max:100'],
            'data.attributes.solicita_tipo_doc' => ['required', Rule::in($tipos)],
            'data.attributes.solicita_num_doc' => ['required', 'string', 'max:20'],
        ])['data']['attributes'];

        try {
            $inv = $this->invalidations->invalidate($owner, $data, $request->user()?->id);
        } catch (DtePendingException $e) {
            return $this->invalidationResponse($this->latestInvalidation($owner), 202, $e->getMessage());
        } catch (DteException $e) {
            return response()->json(['errors' => [['title' => 'Error de invalidación', 'detail' => $e->getMessage()]]], 422);
        }

        return $this->invalidationResponse($inv, 201);
    }

    /**
     * GET /invoices/{invoice}/dte-replacements
     * DTE sellados que pueden reemplazar al de esta factura (tipos 1 y 3).
     */
    public function replacements(Invoice $invoice): JsonResponse
    {
        return $this->replacementsFor($invoice);
    }

    /** GET /credit-notes/{creditNote}/dte-replacements — para invalidar una nota de débito. */
    public function replacementsNote(CreditNote $creditNote): JsonResponse
    {
        return $this->replacementsFor($creditNote);
    }

    private function replacementsFor(Invoice|CreditNote $owner): JsonResponse
    {
        return response()->json(['data' => $this->invalidations->replacementCandidates($owner)->map(fn (DteDocument $d) => [
            'type' => 'dte-documents',
            'id' => (string) $d->id,
            'attributes' => [
                'codigo_generacion' => $d->codigo_generacion,
                'numero_control' => $d->numero_control,
                'invoice_id' => $d->invoice_id,
                'invoice_number' => $d->invoice?->number ?? $d->creditNote?->number,
                'receptor_name' => $d->invoice?->receptor_name,
                'amount' => $d->invoice?->amount,
                'transmitido_at' => $d->transmitido_at,
            ],
        ])->values()]);
    }

    /**
     * GET /dte/contingencias
     * Contingencias con sus plazos: el evento vence 24 h después de que cesó la
     * causa y el lote, 72 h después del sello del evento.
     */
    public function contingencias(): JsonResponse
    {
        $list = DteContingencia::withCount([
            'documentos',
            'documentos as pendientes_count' => fn ($q) => $q->where('estado', DteDocument::CONTINGENCY),
        ])->latest('id')->limit(50)->get();

        return response()->json(['data' => $list->map(fn (DteContingencia $c) => $this->contingenciaData($c))->values()]);
    }

    /**
     * POST /dte/contingencias/{contingencia}/process
     * Avanza ahora (sin esperar al comando programado) o rearma un evento rechazado.
     */
    public function processContingencia(DteContingencia $contingencia): JsonResponse
    {
        try {
            $c = $contingencia->estado === DteContingencia::EVENT_REJECTED
                ? $this->contingencies->rearm($contingencia)
                : $this->contingencies->process($contingencia);
        } catch (DteException $e) {
            return response()->json(['errors' => [['title' => 'Error de contingencia', 'detail' => $e->getMessage()]]], 422);
        }

        $c->loadCount([
            'documentos',
            'documentos as pendientes_count' => fn ($q) => $q->where('estado', DteDocument::CONTINGENCY),
        ]);

        return response()->json(['data' => $this->contingenciaData($c)]);
    }

    private function latestInvalidation(Invoice|CreditNote $owner): ?DteInvalidacion
    {
        return DteInvalidacion::whereIn('dte_document_id', $owner->dteDocuments()->select('id'))->latest('id')->first();
    }

    private function invalidationResponse(?DteInvalidacion $inv, int $status, ?string $message = null): JsonResponse
    {
        return response()->json([
            'data' => $inv ? [
                'type' => 'dte-invalidations',
                'id' => (string) $inv->id,
                'attributes' => [
                    'tipo_anulacion' => $inv->tipo_anulacion,
                    'motivo' => $inv->motivo,
                    'codigo_generacion' => $inv->codigo_generacion,
                    'codigo_generacion_r' => $inv->codigo_generacion_r,
                    'estado' => $inv->estado,
                    'sello_recibido' => $inv->sello_recibido,
                    'ultimo_error' => $inv->ultimo_error,
                    'transmitido_at' => $inv->transmitido_at,
                ],
            ] : null,
            'meta' => array_filter(['message' => $message]),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function contingenciaData(DteContingencia $c): array
    {
        $vence = match ($c->estado) {
            DteContingencia::CLOSED, DteContingencia::EVENT_REJECTED => $c->eventoVenceAt(),
            DteContingencia::EVENT_SENT => $c->loteVenceAt(),
            default => null,
        };

        return [
            'type' => 'dte-contingencias',
            'id' => (string) $c->id,
            'attributes' => [
                'ambiente' => $c->ambiente,
                'tipo_contingencia' => $c->tipo_contingencia,
                'estado' => $c->estado,
                'inicio' => $c->inicio,
                'fin' => $c->fin,
                'documentos' => (int) $c->documentos_count,
                'pendientes' => (int) $c->pendientes_count,
                'codigo_generacion' => $c->codigo_generacion,
                'sello_recibido' => $c->sello_recibido,
                'codigo_lote' => $c->codigo_lote,
                'evento_vence_at' => $c->eventoVenceAt(),
                'lote_vence_at' => $c->loteVenceAt(),
                // El plazo que corre ahora, y si ya venció.
                'proximo_vencimiento' => $vence,
                'vencida' => $vence?->isPast() ?? false,
                'ultimo_error' => $c->ultimo_error,
            ],
        ];
    }
}
