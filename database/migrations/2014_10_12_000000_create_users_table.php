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
        Schema::create('users', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->string('user_type', 191)->default('user')->index();
			$table->unsignedInteger('business_id')->nullable()->index('users_business_id_foreign');
			$table->unsignedInteger('crm_contact_id')->nullable()->index('users_crm_contact_id_foreign');
			$table->string('first_name');
			$table->string('last_name');
			$table->string('username')->unique();
			$table->string('email');
			$table->timestamp('email_verified_at')->nullable();
			$table->string('password');
			$table->string('avatar')->nullable();
			$table->char('language', 7)->default('en');
			$table->char('contact_no', 15)->nullable();
			$table->text('address')->nullable();
			$table->decimal('max_sales_discount_percent', 5)->nullable();
			$table->boolean('allow_login')->default(true);
			$table->integer('essentials_department_id')->nullable()->index();
			$table->integer('essentials_designation_id')->nullable()->index();
			$table->decimal('essentials_salary', 22, 4)->nullable();
			$table->string('essentials_pay_period', 191)->nullable();
			$table->string('essentials_pay_cycle', 191)->nullable();
			$table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
			$table->boolean('is_cmmsn_agnt')->default(false);
			$table->decimal('cmmsn_percent', 4)->default(0);
			$table->boolean('selected_contacts')->default(false);
			$table->date('dob')->nullable();
			$table->string('gender', 191)->nullable();
			$table->enum('marital_status', ['married', 'unmarried', 'divorced'])->nullable();
			$table->char('blood_group', 10)->nullable();
			$table->char('contact_number', 20)->nullable();
			$table->string('alt_number', 191)->nullable();
			$table->string('family_number', 191)->nullable();
			$table->string('fb_link', 191)->nullable();
			$table->string('twitter_link', 191)->nullable();
			$table->string('social_media_1', 191)->nullable();
			$table->string('social_media_2', 191)->nullable();
			$table->text('permanent_address')->nullable();
			$table->text('current_address')->nullable();
			$table->string('guardian_name', 191)->nullable();
			$table->string('custom_field_1', 191)->nullable();
			$table->string('custom_field_2', 191)->nullable();
			$table->string('custom_field_3', 191)->nullable();
			$table->string('custom_field_4', 191)->nullable();
			$table->longText('bank_details')->nullable();
			$table->string('id_proof_name', 191)->nullable();
			$table->string('id_proof_number', 191)->nullable();
			$table->integer('location_id')->nullable()->comment('user primary work location');
			$table->boolean('is_enable_service_staff_pin')->default(false);
			$table->text('service_staff_pin')->nullable();
			$table->dateTime('available_at')->nullable()->comment('Service staff avilable at. Calculated from product preparation_time_in_minutes');
			$table->dateTime('paused_at')->nullable()->comment('Service staff available time paused at, Will be nulled on resume.');
			$table->rememberToken();
			$table->softDeletes();
			$table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
