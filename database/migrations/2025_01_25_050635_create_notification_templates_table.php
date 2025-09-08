<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id');
            $table->string('template_for', 191);
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->text('whatsapp_text')->nullable();
            $table->string('subject', 191)->nullable();
            $table->string('cc', 191)->nullable();
            $table->string('bcc', 191)->nullable();
            $table->boolean('auto_send')->default(false);
            $table->boolean('auto_send_sms')->default(false);
            $table->boolean('auto_send_wa_notif')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
