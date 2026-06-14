<?php

namespace App\Services;

use App\Models\CustomInquiry;
use App\Models\Setting;
use App\Notifications\AdminAlertNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

class CustomInquiryService
{
    public function create(array $data, ?User $user = null): CustomInquiry
    {
        $inquiry = CustomInquiry::create([
            'user_id' => $user?->id,
            'preferred_destinations' => $data['preferred_destinations'],
            'travel_start_date' => $data['travel_start_date'] ?? null,
            'travel_end_date' => $data['travel_end_date'] ?? null,
            'budget_min' => $data['budget_min'] ?? null,
            'budget_max' => $data['budget_max'] ?? null,
            'travelers_count' => $data['travelers_count'] ?? null,
            'currency_code' => $data['currency_code'] ?? config('app.currency', 'USD'),
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        foreach ($this->resolveAdminEmails() as $email) {
            Notification::route('mail', $email)->notify(new AdminAlertNotification(
                'New custom tour inquiry received',
                'A new personalized trip inquiry has been submitted.',
                [
                    'inquiry_id' => $inquiry->id,
                    'destinations' => $inquiry->preferred_destinations,
                ]
            ));
        }

        return $inquiry;
    }

    private function resolveAdminEmails(): array
    {
        $configEmails = config('services.notifications.admin_emails', []);
        $settingsEmails = [];

        $appSetting = Setting::query()->where('key', 'app')->first();
        if ($appSetting && is_string($appSetting->value)) {
            $decoded = json_decode($appSetting->value, true);
            $contactEmail = $decoded['contact_email'] ?? null;

            if (is_string($contactEmail) && $contactEmail !== '') {
                $settingsEmails = preg_split('/[,;\s]+/', $contactEmail) ?: [];
            }
        }

        $emails = array_values(array_unique(array_filter(array_merge($settingsEmails, $configEmails), fn ($email) =>
            is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
        )));

        return $emails;
    }
}