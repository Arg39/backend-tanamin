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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->longText('about')->nullable();
            $table->longText('vision')->nullable();
            $table->json('mission')->nullable();
            $table->timestamps();
        });

        Schema::create('company_partnerships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('partner_name')->nullable();
            $table->string('logo')->nullable();
            $table->string('website_url')->nullable();
            $table->timestamps();
        });

        Schema::create('company_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->unsignedInteger('value')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();
        });

        Schema::create('company_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('image')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->timestamps();
        });

        Schema::create('company_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->longText('telephone')->nullable();
            $table->longText('email')->nullable();
            $table->longText('address')->nullable();
            $table->json('social_media')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_us_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_us_messages');
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('company_activities');
        Schema::dropIfExists('company_statistics');
        Schema::dropIfExists('company_partnerships');
        Schema::dropIfExists('company_profiles');
    }
};
