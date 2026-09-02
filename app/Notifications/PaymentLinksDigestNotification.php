<?php

namespace App\Notifications;

use App\Support\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resumen diario de los enlaces de pago abiertos.
 *
 * Sin API del banco, la única forma de que un enlace no se pierda de vista es
 * que alguien lo revise. Este correo es esa revisión empujada al back-office en
 * vez de depender de que alguien recuerde entrar a mirar la cola.
 *
 * No se envía si no hay nada abierto: ver `PaymentLinksDigest` (el comando).
 */
class PaymentLinksDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, int>  $conteosPorEstado
     * @param  array<int, array<string, mixed>>  $reportados
     * @param  array<int, array<string, mixed>>  $porVencer
     */
    public function __construct(
        private readonly array $conteosPorEstado,
        private readonly array $reportados,
        private readonly array $porVencer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $etiquetas = ['draft' => 'sin enlace', 'active' => 'enviados', 'reported' => 'reportados por el cliente'];

        $mail = (new MailMessage)
            ->subject('Enlaces de pago pendientes — resumen diario')
            ->greeting('Buen día.')
            ->line('Así está la cola de enlaces de pago del banco ahora mismo:');

        foreach ($etiquetas as $clave => $etiqueta) {
            $n = $this->conteosPorEstado[$clave] ?? 0;
            if ($n > 0) {
                $mail->line("**{$n}** {$etiqueta}.");
            }
        }

        if ($this->reportados !== []) {
            $mail->line('');
            $mail->line('**Esperando confirmación (el cliente ya avisó que pagó):**');
            foreach ($this->reportados as $fila) {
                $mail->line('- '.$this->lineaEnlace($fila).($fila['reported_ref'] ? ' · aut. '.$fila['reported_ref'] : ' · sin número de autorización'));
            }
        }

        if ($this->porVencer !== []) {
            $mail->line('');
            $mail->line('**Vencen en menos de 24 horas:**');
            foreach ($this->porVencer as $fila) {
                $mail->line('- '.$this->lineaEnlace($fila));
            }
        }

        return $mail->line('')->line('Revísalos en Pagos → Enlaces de pago.');
    }

    private function lineaEnlace(array $fila): string
    {
        $importe = number_format((float) $fila['amount'], 2, '.', ',').' '.($fila['currency_code'] ?: SiteSettings::currency());

        return $fila['reference'].' · reserva #'.($fila['booking_id'] ?? '?').' · '.$importe
            .($fila['customer_email'] ? ' · '.$fila['customer_email'] : '');
    }
}
