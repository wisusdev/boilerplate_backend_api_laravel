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
        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('contacts_business_id_foreign');
            $table->string('type', 191)->index();
            $table->string('contact_type', 191)->nullable();
            $table->string('supplier_business_name', 191)->nullable();
            $table->string('name', 191)->nullable();
            $table->string('prefix', 191)->nullable();
            $table->string('first_name', 191)->nullable();
            $table->string('middle_name', 191)->nullable();
            $table->string('last_name', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('contact_id', 191)->nullable();
            $table->string('contact_status', 191)->default('active')->index();
            $table->string('tax_number', 191)->nullable();
            $table->string('city', 191)->nullable();
            $table->string('state', 191)->nullable();
            $table->string('country', 191)->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('zip_code', 191)->nullable();
            $table->date('dob')->nullable();
            $table->string('mobile', 191);
            $table->string('landline', 191)->nullable();
            $table->string('alternate_number', 191)->nullable();
            $table->integer('pay_term_number')->nullable();
            $table->enum('pay_term_type', ['days', 'months'])->nullable();
            $table->decimal('credit_limit', 22, 4)->nullable();
            $table->uuid('created_by')->index('contacts_created_by_foreign');
            $table->decimal('balance', 22, 4)->default(0);
            $table->integer('total_rp')->default(0)->comment('rp is the short form of reward points');
            $table->integer('total_rp_used')->default(0)->comment('rp is the short form of reward points');
            $table->integer('total_rp_expired')->default(0)->comment('rp is the short form of reward points');
            $table->boolean('is_default')->default(false);
            $table->integer('customer_group_id')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('position', 191)->nullable();
            $table->longText('shipping_custom_field_details')->nullable();
            $table->boolean('is_export')->default(false);
            $table->string('export_custom_field_1', 191)->nullable();
            $table->string('export_custom_field_2', 191)->nullable();
            $table->string('export_custom_field_3', 191)->nullable();
            $table->string('export_custom_field_4', 191)->nullable();
            $table->string('export_custom_field_5', 191)->nullable();
            $table->string('export_custom_field_6', 191)->nullable();
            $table->string('custom_field1', 191)->nullable();
            $table->string('custom_field2', 191)->nullable();
            $table->string('custom_field3', 191)->nullable();
            $table->string('custom_field4', 191)->nullable();
            $table->string('custom_field5', 191)->nullable();
            $table->string('custom_field6', 191)->nullable();
            $table->string('custom_field7', 191)->nullable();
            $table->string('custom_field8', 191)->nullable();
            $table->string('custom_field9', 191)->nullable();
            $table->string('custom_field10', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
