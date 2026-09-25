<?php

namespace App\Mail;

use App\Models\DteDocument;
use App\Services\Dte\DteRepresentation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * El DTE para su receptor: la representación gráfica (PDF) y el archivo DTE
 * (JSON firmado, con sello cuando lo tiene), que es el que tiene validez.
 */
class DteDocumentMail extends Mailable
{
    private const NOMBRES = [
        '01' => 'Factura electrónica',
        '03' => 'Comprobante de crédito fiscal electrónico',
        '05' => 'Nota de crédito electrónica',
        '06' => 'Nota de débito electrónica',
    ];

    public function __construct(public readonly DteDocument $doc) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf(
            '%s %s — %s',
            $this->nombreDocumento(),
            $this->doc->numero_control,
            $this->doc->document()['emisor']['nombre'] ?? config('app.name'),
        ));
    }

    public function content(): Content
    {
        $json = $this->doc->document();

        return new Content(view: 'mail.dte_documento', with: [
            'emisor' => $json['emisor']['nombre'] ?? config('app.name'),
            'documento' => $this->nombreDocumento(),
            'numeroControl' => $this->doc->numero_control,
            'codigoGeneracion' => $this->doc->codigo_generacion,
            'total' => number_format((float) ($json['resumen']['totalPagar'] ?? 0), 2, '.', ','),
            'sello' => $this->doc->sello_recibido,
            'contingencia' => $this->doc->estado === DteDocument::CONTINGENCY,
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $r = app(DteRepresentation::class);

        return [
            Attachment::fromData(fn () => $r->pdf($this->doc), $r->filename($this->doc, 'pdf'))->withMime('application/pdf'),
            Attachment::fromData(fn () => $r->json($this->doc), $r->filename($this->doc, 'json'))->withMime('application/json'),
        ];
    }

    private function nombreDocumento(): string
    {
        return self::NOMBRES[$this->doc->tipo_dte] ?? 'Documento tributario electrónico';
    }
}
