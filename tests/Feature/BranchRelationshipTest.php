<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_branch_can_be_assigned_with_pivot_attributes(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create([
            'code' => 'JKT-01',
            'name' => 'JOOAL Jakarta',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $user->branches()->attach($branch, [
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($user->branches->contains($branch));
        $this->assertTrue($branch->users->contains($user));
        $this->assertTrue((bool) $user->branches->first()->pivot->is_default);
    }

    public function test_branch_display_names_use_business_names_without_internal_codes(): void
    {
        $this->assertSame('Cabang Utama', (new Branch(['code' => 'JOOAL-001', 'name' => 'Cabang Utama']))->display_name);
        $this->assertSame('Gelato', (new Branch(['code' => 'JOOAL-002', 'name' => 'Gelato']))->display_name);
        $this->assertSame('Coffee', (new Branch(['code' => 'JOOAL-003', 'name' => 'Coffee']))->display_name);
        $this->assertSame('JOOAL Jakarta', (new Branch(['code' => 'JKT-01', 'name' => 'JOOAL Jakarta']))->display_name);
    }
}
