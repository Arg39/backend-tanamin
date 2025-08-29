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
            $table->longText('about');
            $table->longText('vision');
            $table->json('mission');
            $table->timestamps();
        });

        Schema::create('company_partnerships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('partner_name');
            $table->string('logo')->nullable();
            $table->string('website_url')->nullable();
            $table->timestamps();
        });

        Schema::create('company_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->unsignedInteger('value');
            $table->string('unit')->nullable();
            $table->timestamps();
        });

        Schema::create('company_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('image');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->timestamps();
        });

        Schema::create('company_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->longText('telephone');
            $table->longText('email');
            $table->longText('address');
            $table->json('social_media')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('company_activities');
        Schema::dropIfExists('company_statistics');
        Schema::dropIfExists('company_partnerships');
        Schema::dropIfExists('company_profiles');
    }
};
