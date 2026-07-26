<?php

namespace App\Services\Booking;

use Illuminate\Validation\ValidationException;

interface BookingHandlerInterface
{
    /**
     * Validate business rules for this booking type.
     * Must be called inside a DB transaction with appropriate locks held.
     *
     * @throws ValidationException
     */
    public function validate(array $data): void;

    /**
     * Build the Booking attributes + optional 'details' key for extension tables.
     * Called after validate() within the same transaction.
     */
    public function prepare(array $data): array;
}
