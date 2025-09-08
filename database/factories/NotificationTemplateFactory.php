<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class NotificationTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fake()->randomNumber(),
            'template_for' => fake()->text(191),
            'email_body' => fake()->paragraph(),
            'sms_body' => fake()->paragraph(),
            'whatsapp_text' => fake()->paragraph(),
            'subject' => fake()->text(191),
            'cc' => fake()->text(191),
            'bcc' => fake()->text(191),
            'auto_send' => fake()->boolean(),
            'auto_send_sms' => fake()->boolean(),
            'auto_send_wa_notif' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
