<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnSubscriptionTypeIntoCourseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_courses', function (Blueprint $table) {
            $table->string('course_id')->nullable()->change();
            $table->string('subscription_type')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchased_course_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_courses', function (Blueprint $table) {
            $table->dropColumn('subscription_type');
            $table->string('course_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchased_course_id')->nullable(false)->change();
        });
    }
}
