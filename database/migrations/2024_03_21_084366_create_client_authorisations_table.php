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
        Schema::create('client_authorisations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id');
            $table->integer('auth_api')->nullable();
            $table->string('oauth_type', 16)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('authorised_at')->useCurrent();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('region', 16)->nullable();
            $table->string('amazon_user_id', 128)->nullable();
            $table->string('name', 128)->nullable();
            $table->string('email', 128)->nullable();
            $table->boolean('active')->default(true);
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
        Schema::dropIfExists('client_authorisations');
    }
};
