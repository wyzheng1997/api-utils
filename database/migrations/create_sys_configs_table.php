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
        $sys_config = config('ugly.sys_config');
        Schema::create($sys_config['table'], function (Blueprint $table) {
            $table->string('slug')->primary();
            $table->mediumText('value')->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ugly.sys_config.table'));
    }
};
