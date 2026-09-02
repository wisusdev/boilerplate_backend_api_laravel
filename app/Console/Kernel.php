<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Todo lo que necesita un cron en producción vive aquí, no en la crontab: la
     * VM solo necesita UNA línea (`* * * * * php artisan schedule:run`) y este
     * archivo es la fuente de verdad de qué corre y cuándo. `php artisan
     * schedule:list` lo confirma sin tener que entrar por SSH a leer el cron.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Enlaces de pago del banco (BAC) — ver PAGO-ENLACE-BAC.md.
        // Cada hora: la caducidad es informativa, no hace falta más frecuencia
        // que la que un cliente tardaría en notar que "ya no puede pagar".
        $schedule->command('payment-links:expire')->hourly();

        // A las 8am hora de El Salvador, no UTC: el servidor corre en UTC y
        // programar "8:00" sin zona lo mandaría de madrugada para el negocio.
        $schedule->command('payment-links:digest')
            ->dailyAt('08:00')
            ->timezone('America/El_Salvador');

        // Red de seguridad para relaciones polimórficas sin FK (ver la clase).
        // Antes vivía como línea de cron manual en el runbook de despliegue;
        // ahora es una línea más de este mismo archivo.
        $schedule->command('integrity:scan --fix')->weeklyOn(1, '03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
