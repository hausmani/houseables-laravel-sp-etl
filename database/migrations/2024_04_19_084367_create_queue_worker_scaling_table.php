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
        Schema::create('queue_worker_scaling', function (Blueprint $table) {
            $table->id();
            $table->string('project', 128)->nullable();
            $table->string('cluster', 128)->nullable();
            $table->string('service', 128)->nullable();
            $table->string('task_definition', 128)->nullable();
            $table->string('queue', 128)->nullable();
            $table->integer('max_workers')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_authorization_attempts');
    }
};
