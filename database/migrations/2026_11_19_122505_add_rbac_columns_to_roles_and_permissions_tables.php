<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->after('name');
            $table->text('description')->nullable()->after('display_name');
            $table->boolean('is_system')->default(false)->after('description');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module', 100)->after('name');
            $table->string('description')->nullable()->after('module');

            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_system']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropColumn(['module', 'description']);
        });
    }
};
