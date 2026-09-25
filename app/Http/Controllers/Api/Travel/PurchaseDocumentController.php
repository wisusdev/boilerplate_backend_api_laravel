<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseDocumentResource;
use App\Models\PurchaseDocument;
use App\Services\Dte\DteException;
use App\Services\Dte\DtePendingException;
use App\Services\Dte\PurchaseDocumentService;
use App\Services\DteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Documentos que vamosPues emite al comprar: factura de sujeto excluido (14)
 * y comprobante de retención (07).
 */
class PurchaseDocumentController extends Controller
{
    public function __construct(
        private readonly PurchaseDocumentService $documents,
        private readonly DteService $dte,
    ) {}

    /** GET /purchase-documents?kind=fse|cr&dte_status= */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PurchaseDocumentResource::collection(
            PurchaseDocument::query()
                ->with('dteDocuments.invalidaciones')
                ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
                ->when($request->filled('dte_status'), fn ($q) => $q->where('dte_status', $request->string('dte_status')))
                ->latest('id')
                ->jsonPaginate()
        );
    }

    public function show(PurchaseDocument $purchaseDocument): PurchaseDocumentResource
    {
        return PurchaseDocumentResource::make($purchaseDocument->load('dteDocuments.invalidaciones'));
    }

    /**
     * POST /purchase-documents
     * Guarda el documento y emite su DTE: 201 con sello; 202 pendiente o en
     * contingencia; 422 datos incompletos (todos a la vez) o rechazo.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'data.type' => ['required', 'in:purchase-documents'],
            'data.attributes.kind' => ['required', Rule::in([PurchaseDocument::FSE, PurchaseDocument::CR])],
            'data.attributes.expense_id' => ['nullable', 'integer', 'exists:expenses,id'],
            'data.attributes.proveedor_nombre' => ['nullable', 'string', 'max:250'],
            'data.attributes.proveedor_tipo_documento' => ['nullable', 'string', 'max:2'],
            'data.attributes.proveedor_num_documento' => ['nullable', 'string', 'max:20'],
            'data.attributes.proveedor_nrc' => ['nullable', 'string', 'max:10'],
            'data.attributes.proveedor_cod_actividad' => ['nullable', 'string', 'max:6'],
            'data.attributes.proveedor_nombre_comercial' => ['nullable', 'string', 'max:150'],
            'data.attributes.proveedor_departamento' => ['nullable', 'string', 'max:2'],
            'data.attributes.proveedor_municipio' => ['nullable', 'string', 'max:2'],
            'data.attributes.proveedor_distrito' => ['nullable', 'string', 'max:2'],
            'data.attributes.proveedor_direccion' => ['nullable', 'string', 'max:200'],
            'data.attributes.proveedor_telefono' => ['nullable', 'string', 'max:30'],
            'data.attributes.proveedor_correo' => ['nullable', 'string', 'max:100'],
            'data.attributes.retener_renta' => ['sometimes', 'boolean'],
            'data.attributes.condicion_operacion' => ['sometimes', 'integer'],
            'data.attributes.forma_pago' => ['nullable', Rule::in(['01', '02', '03', '04', '05', '08', '09', '11', '12', '13', '14', '99'])],
            'data.attributes.observaciones' => ['nullable', 'string', 'max:3000'],
            'data.attributes.items' => ['required', 'array', 'min:1', 'max:500'],
        ])['data']['attributes'];
        $data['items'] = array_values((array) $request->input('data.attributes.items', []));

        try {
            $doc = $this->documents->create($data['kind'], $data, $request->user()?->id);
        } catch (DteException $e) {
            return response()->json([
                'errors' => array_map(fn ($d) => ['title' => 'Error de documento', 'detail' => $d], $e->errors ?: [$e->getMessage()]),
            ], 422);
        }

        return $this->emit($doc, 201);
    }

    /** POST /purchase-documents/{purchaseDocument}/transmit — reintenta su DTE. */
    public function transmit(PurchaseDocument $purchaseDocument): JsonResponse
    {
        return $this->emit($purchaseDocument, 200);
    }

    private function emit(PurchaseDocument $doc, int $okStatus): JsonResponse
    {
        try {
            $doc = $this->dte->processPurchaseDocument($doc);
            [$status, $message] = [$okStatus, null];
        } catch (DtePendingException $e) {
            [$status, $message] = [202, $e->getMessage()];
        } catch (DteException $e) {
            [$status, $message] = [422, $e->getMessage()];
        }

        return PurchaseDocumentResource::make($doc->fresh('dteDocuments.invalidaciones'))
            ->additional(['meta' => array_filter(['message' => $message])])
            ->response()
            ->setStatusCode($status);
    }
}
