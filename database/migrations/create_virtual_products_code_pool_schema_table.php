<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = config('lunarphp-virtual-product.code_pool.schema_table');
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->unsigned()->unique();
            $table->json('schema');
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
        $tableName = config('lunarphp-virtual-product.code_pool.schema_table');
        Schema::dropIfExists($tableName);
    }
};
