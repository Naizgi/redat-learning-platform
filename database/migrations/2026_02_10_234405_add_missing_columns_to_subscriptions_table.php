<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToSubscriptionsTable extends Migration
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
            $columnsToAdd = [
                'plan_id' => ['type' => 'foreignId', 'after' => 'user_id', 'nullable' => true],
                'start_date' => ['type' => 'dateTime', 'after' => 'status', 'nullable' => true],
                'end_date' => ['type' => 'dateTime', 'after' => 'start_date', 'nullable' => true],
                'expires_at' => ['type' => 'dateTime', 'after' => 'end_date', 'nullable' => true],
                'auto_renew' => ['type' => 'boolean', 'after' => 'expires_at', 'default' => false],
                'created_by_admin' => ['type' => 'boolean', 'after' => 'auto_renew', 'default' => false],
                'admin_id' => ['type' => 'foreignId', 'after' => 'created_by_admin', 'nullable' => true, 'constrained' => 'users'],
                'notes' => ['type' => 'text', 'after' => 'admin_id', 'nullable' => true],
                'updated_by_admin' => ['type' => 'boolean', 'after' => 'notes', 'default' => false],
                'amount' => ['type' => 'decimal', 'after' => 'updated_by_admin', 'precision' => 10, 'scale' => 2, 'default' => 0.00],
            ];

            foreach ($columnsToAdd as $columnName => $columnConfig) {
                if (!Schema::hasColumn('subscriptions', $columnName)) {
                    if ($columnConfig['type'] === 'foreignId') {
                        $table->foreignId($columnName)
                              ->nullable($columnConfig['nullable'] ?? false)
                              ->after($columnConfig['after']);
                        
                        if (isset($columnConfig['constrained'])) {
                            $table->foreign($columnName)->references('id')->on($columnConfig['constrained']);
                        }
                    } elseif ($columnConfig['type'] === 'dateTime') {
                        $table->dateTime($columnName)
                              ->nullable($columnConfig['nullable'] ?? false)
                              ->after($columnConfig['after']);
                    } elseif ($columnConfig['type'] === 'boolean') {
                        $table->boolean($columnName)
                              ->default($columnConfig['default'] ?? false)
                              ->after($columnConfig['after']);
                    } elseif ($columnConfig['type'] === 'text') {
                        $table->text($columnName)
                              ->nullable($columnConfig['nullable'] ?? false)
                              ->after($columnConfig['after']);
                    } elseif ($columnConfig['type'] === 'decimal') {
                        $table->decimal($columnName, $columnConfig['precision'], $columnConfig['scale'])
                              ->default($columnConfig['default'] ?? 0.00)
                              ->after($columnConfig['after']);
                    }
                }
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
            $columnsToDrop = [
                'plan_id',
                'start_date',
                'end_date',
                'expires_at',
                'auto_renew',
                'created_by_admin',
                'admin_id',
                'notes',
                'updated_by_admin',
                'amount'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}