<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        // CREATE TABLE is atomic on the project's MySQL/InnoDB target. If the
        // table was created but Laravel could not record the migration, accept
        // it only when the complete expected structure is already present.
        if (Schema::hasTable('coupons')) {
            $this->assertExistingCouponTableIsComplete();

            return;
        }

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        // This intentionally removes coupon definitions and usage counts.
        // Roll back only on a database that has been backed up or is disposable.
        Schema::dropIfExists('coupons');
    }

    private function assertExistingCouponTableIsComplete(): void
    {
        $requiredColumns = [
            'id', 'code', 'discount_type', 'discount_value', 'starts_at', 'ends_at',
            'usage_limit', 'used_count', 'is_active', 'created_at', 'updated_at',
        ];
        $missing = array_diff($requiredColumns, Schema::getColumnListing('coupons'));
        if ($missing) {
            throw new RuntimeException('Existing coupons table is incomplete; migration was not marked complete. Missing: '.implode(', ', $missing));
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $engine = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['coupons']
            );
            if (! $engine || strtoupper((string) $engine->ENGINE) !== 'INNODB') {
                throw new RuntimeException('Existing coupons table must use InnoDB before this migration can be considered complete.');
            }

            $expectedColumns = [
                'id' => ['bigint unsigned', 'NO'],
                'code' => ['varchar(40)', 'NO'],
                'discount_type' => ['varchar(20)', 'NO'],
                'discount_value' => ['decimal(10,2)', 'NO'],
                'starts_at' => ['timestamp', 'NO'],
                'ends_at' => ['timestamp', 'NO'],
                'usage_limit' => ['int unsigned', 'YES'],
                'used_count' => ['int unsigned', 'NO'],
                'is_active' => ['tinyint(1)', 'NO'],
                'created_at' => ['timestamp', 'YES'],
                'updated_at' => ['timestamp', 'YES'],
            ];
            $existingColumns = DB::select(
                'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['coupons']
            );
            $columnMap = [];
            foreach ($existingColumns as $column) {
                $columnMap[$column->COLUMN_NAME] = [strtolower($column->COLUMN_TYPE), $column->IS_NULLABLE];
            }
            foreach ($expectedColumns as $name => $expected) {
                if (($columnMap[$name] ?? null) !== $expected) {
                    throw new RuntimeException('Existing coupons.'.$name.' does not match the migration definition; no existing schema was changed.');
                }
            }

            $uniqueCode = DB::selectOne(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = ?',
                ['coupons', 'code']
            );
            if (! $uniqueCode) {
                throw new RuntimeException('Existing coupons table is missing a single-column unique index on code.');
            }

            return;
        }

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('coupons')") as $index) {
                if ((int) $index->unique !== 1) {
                    continue;
                }
                $quotedIndex = '"'.str_replace('"', '""', $index->name).'"';
                $columns = DB::select('PRAGMA index_info('.$quotedIndex.')');
                if (count($columns) === 1 && strtolower((string) $columns[0]->name) === 'code') {
                    return;
                }
            }

            throw new RuntimeException('Existing coupons table is missing a single-column unique index on code.');
        }

        throw new RuntimeException('Cannot verify an existing coupons table on database driver '.$driver.'.');
    }
};
