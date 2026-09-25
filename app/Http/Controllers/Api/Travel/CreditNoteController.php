<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\Dte\CreditNoteService;
use App\Services\Dte\DteException;
use App\Services\Dte\DtePendingException;
use App\Services\DteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Notas de crédito (05) y de débito (06) sobre un CCF sellado.
 */
class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteService $notes,
        private readonly DteService $dte,
    ) {}

    /** GET /invoices/{invoice}/credit-notes */
    public function index(Invoice $invoice): AnonymousResourceCollection
    {
        return CreditNoteResource::collection(
            $invoice->creditNotes()->with(['items', 'dteDocuments.invalidaciones'])->latest('id')->get()
        );
    }

    /**
     * POST /invoices/{invoice}/credit-notes
     * Guarda la nota y emite su DTE: 201 con sello; 202 pendiente o en
     * contingencia; 422 regla incumplida o rechazo (la nota queda y se reintenta).
     */
    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'data.type' => ['required', 'in:credit-notes'],
            'data.attributes.kind' => ['required', Rule::in([CreditNote::CREDIT, CreditNote::DEBIT])],
            'data.attributes.motivo' => ['required', 'string', 'max:500'],
            'data.attributes.items' => ['required', 'array', 'min:1', 'max:50'],
            'data.attributes.items.*.description' => ['required', 'string', 'max:250'],
            'data.attributes.items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'data.attributes.items.*.unit_price' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ])['data']['attributes'];

        try {
            $note = $this->notes->create($invoice, $data, $request->user()?->id);
        } catch (DteException $e) {
            return $this->error($e);
        }

        return $this->emit($note, 201);
    }

    /** POST /credit-notes/{creditNote}/transmit — reintenta el DTE de la nota. */
    public function transmit(CreditNote $creditNote): JsonResponse
    {
        return $this->emit($creditNote, 200);
    }

    private function emit(CreditNote $note, int $okStatus): JsonResponse
    {
        try {
            $note = $this->dte->processCreditNote($note);
            $status = $okStatus;
            $message = null;
        } catch (DtePendingException $e) {
            [$status, $message] = [202, $e->getMessage()];
        } catch (DteException $e) {
            [$status, $message] = [422, $e->getMessage()];
        }

        return CreditNoteResource::make($note->fresh(['items', 'dteDocuments.invalidaciones']))
            ->additional(['meta' => array_filter(['message' => $message])])
            ->response()
            ->setStatusCode($status);
    }

    private function error(DteException $e): JsonResponse
    {
        $details = $e->errors ?: [$e->getMessage()];

        return response()->json([
            'errors' => array_map(fn ($d) => ['title' => 'Error de nota', 'detail' => $d], $details),
        ], 422);
    }
}
