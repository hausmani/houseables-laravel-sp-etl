<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_profile_info', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable();
            $table->bigInteger('client_authorisation_id')->nullable();
            $table->string('sellerName', 255)->nullable();
            $table->string('profile_type', 16)->nullable();
            $table->bigInteger('profileId')->nullable();
            $table->string('sellerId', 64)->nullable();
            $table->bigInteger('advertiser_id')->nullable();
            $table->string('marketplaceId', 32)->nullable();
            $table->string('countryCode', 8)->nullable();
            $table->string('currencyCode', 8)->nullable();
            $table->string('id_timezone', 24)->nullable();
            $table->boolean('active')->default(true);
            $table->jsonb('report_types')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_profile_info');
    }
};
