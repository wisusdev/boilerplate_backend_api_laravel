<?php

namespace App\Console\Commands;

use App\Models\DteDocument;
use App\Models\DteInvalidacion;
use App\Services\Dte\DteContingencyService;
use App\Services\Dte\DteException;
use App\Services\Dte\DteInvalidationService;
use App\Services\Dte\DtePendingException;
use App\Services\DteService;
use Illuminate\Console\Command;

/**
 * La parte de la facturación electrónica que no espera a nadie:
 *
 *  - reenvía los DTE que quedaron sin sello (el MH contestó sin sello o el
 *    proceso se interrumpió en pleno envío), preguntando antes al MH si ya
 *    tiene el documento, así que un DTE que sí llegó recupera su sello sin
 *    duplicarse;
 *  - reenvía las invalidaciones sin respuesta;
 *  - hace avanzar cada contingencia: detecta que el MH volvió, concilia, envía
 *    el evento y el lote, y recoge el resultado.
 *
 * Solo toca documentos sin cambios desde hace unos minutos, para no competir
 * con un envío en curso, y deja de insistir tras muchos intentos (el
 * back-office sigue pudiendo reintentar a mano).
 */
class RetryPendingDte extends Command
{
    protected $signature = 'dte:retry {--limit=100 : Documentos por pasada}';

    protected $description = 'Reenvía los DTE e invalidaciones pendientes y hace avanzar las contingencias.';

    /** Minutos sin cambios antes de tocar un documento. */
    private const IDLE_MINUTES = 2;

    /** Un documento que no pasa en tantos intentos necesita a una persona. */
    public const MAX_INTENTOS = 200;

    public function handle(DteService $service, DteInvalidationService $invalidaciones, DteContingencyService $contingencias): int
    {
        $this->retryDocuments($service);
        $this->retryInvalidations($invalidaciones);
        $contingencias->processAll();

        return self::SUCCESS;
    }

    private function retryDocuments(DteService $service): void
    {
        $docs = DteDocument::query()
            ->where('estado', DteDocument::PENDING)
            ->where('updated_at', '<', now()->subMinutes(self::IDLE_MINUTES))
            ->where('intentos', '<', self::MAX_INTENTOS)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($docs->isEmpty()) {
            $this->info('Sin DTE pendientes.');

            return;
        }

        $sellados = 0;
        foreach ($docs as $doc) {
            try {
                $estado = $service->retry($doc)->estado;
                if ($estado === DteDocument::TRANSMITTED) {
                    $sellados++;
                }
                $this->line("  {$doc->numero_control} · {$estado}");
            } catch (DtePendingException $e) {
                $this->line("  {$doc->numero_control} · sigue pendiente");
            } catch (DteException $e) {
                $this->warn("  {$doc->numero_control} · {$e->getMessage()}");
            }
        }

        $this->info("{$sellados} de {$docs->count()} DTE pendiente(s) obtuvieron sello.");
    }

    private function retryInvalidations(DteInvalidationService $service): void
    {
        DteInvalidacion::query()
            ->where('estado', DteInvalidacion::PENDING)
            ->where('updated_at', '<', now()->subMinutes(self::IDLE_MINUTES))
            ->where('intentos', '<', self::MAX_INTENTOS)
            ->orderBy('id')->limit((int) $this->option('limit'))->get()
            ->each(function (DteInvalidacion $inv) use ($service) {
                try {
                    $estado = $service->retry($inv)->estado;
                    $this->line("  invalidación {$inv->codigo_generacion} · {$estado}");
                } catch (DteException $e) {
                    $this->warn("  invalidación {$inv->codigo_generacion} · {$e->getMessage()}");
                }
            });
    }
}
