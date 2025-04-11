<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Branch;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, get the first branch or create one if none exists
        $branch = Branch::first() ?? Branch::create([
            'name' => 'Main Branch',
            'location' => 'Default Location',
            'contact_number' => 'N/A'
        ]);

        Schema::table('products', function (Blueprint $table) use ($branch) {
            $table->foreignId('branch_id')->after('category_id')->default($branch->id);
        });

        // Now add the foreign key constraint
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
