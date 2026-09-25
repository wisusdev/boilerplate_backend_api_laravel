<?php

namespace App\Console\Commands;

use App\Models\DteDocument;
use App\Services\Dte\DteException;
use App\Services\Dte\DtePendingException;
use App\Services\DteService;
use Illuminate\Console\Command;

/**
 * Reenvía los DTE que quedaron sin sello: el MH no respondió o el proceso se
 * interrumpió en pleno envío. Antes de reenviar, `DteService` pregunta al MH
 * si ya tiene el documento, así que un DTE que sí llegó recupera su sello sin
 * duplicarse.
 *
 * Solo toca documentos sin cambios desde hace unos minutos, para no competir
 * con un envío en curso, y deja de insistir tras muchos intentos (el
 * back-office sigue pudiendo reintentar a mano).
 */
class RetryPendingDte extends Command
{
    protected $signature = 'dte:retry {--limit=100 : Documentos por pasada}';

    protected $description = 'Reenvía al Ministerio de Hacienda los DTE pendientes de sello.';

    /** Minutos sin cambios antes de tocar un documento. */
    private const IDLE_MINUTES = 2;

    /** Un documento que no pasa en tantos intentos necesita a una persona. */
    public const MAX_INTENTOS = 200;

    public function handle(DteService $service): int
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

            return self::SUCCESS;
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

        return self::SUCCESS;
    }
}
