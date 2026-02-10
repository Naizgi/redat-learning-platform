<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSubscriptionsTableAddMissingFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('subscriptions', 'start_date')) {
                $table->dateTime('start_date')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('subscriptions', 'end_date')) {
                $table->dateTime('end_date')->nullable()->after('start_date');
            }
            
            if (!Schema::hasColumn('subscriptions', 'plan_id')) {
                $table->foreignId('plan_id')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('end_date');
            }
            
            if (!Schema::hasColumn('subscriptions', 'created_by_admin')) {
                $table->boolean('created_by_admin')->default(false)->after('auto_renew');
            }
            
            if (!Schema::hasColumn('subscriptions', 'admin_id')) {
                $table->foreignId('admin_id')->nullable()->constrained('users')->after('created_by_admin');
            }
            
            if (!Schema::hasColumn('subscriptions', 'notes')) {
                $table->text('notes')->nullable()->after('admin_id');
            }
            
            if (!Schema::hasColumn('subscriptions', 'updated_by_admin')) {
                $table->boolean('updated_by_admin')->default(false)->after('notes');
            }
            
            // Make expires_at nullable if it's not already
            if (Schema::hasColumn('subscriptions', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Remove the columns if they exist
            $columns = [
                'start_date',
                'end_date',
                'plan_id',
                'auto_renew',
                'created_by_admin',
                'admin_id',
                'notes',
                'updated_by_admin'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}