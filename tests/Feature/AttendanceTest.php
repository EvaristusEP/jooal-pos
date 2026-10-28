<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_and_check_out_use_server_timestamps_and_record_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = $this->branch('ATT-1');
        $user->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $this->travelTo(Carbon::parse('2026-10-23 08:15:20'));

        $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])
            ->post('/attendance/check-in', ['branch_id' => 999, 'check_in_at' => '2000-01-01 00:00:00'])
            ->assertRedirect(route('attendance.index'));
        $attendance = Attendance::query()->firstOrFail();
        $this->assertSame($branch->id, $attendance->branch_id);
        $this->assertSame('2026-10-23 08:15:20', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(Attendance::STATUS_CHECKED_IN, $attendance->status);

        $this->travelTo(Carbon::parse('2026-10-23 17:42:10'));
        $this->post('/attendance/check-out', ['check_out_at' => '2000-01-01 00:00:00'])->assertRedirect(route('attendance.index'));
        $attendance->refresh();
        $this->assertSame('2026-10-23 17:42:10', $attendance->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(Attendance::STATUS_CHECKED_OUT, $attendance->status);
    }

    public function test_duplicate_check_in_and_check_out_without_open_session_are_rejected(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $branch = $this->branch('ATT-2');
        $user->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])->post('/attendance/check-out')
            ->assertSessionHasErrors('attendance');
        $this->post('/attendance/check-in')->assertRedirect();
        $this->post('/attendance/check-in')->assertSessionHasErrors('attendance');
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_attendance_page_only_shows_the_signed_in_users_own_sessions(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Current Cashier']);
        $other = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Other Cashier Secret']);
        $branch = $this->branch('ATT-3');
        $viewer->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        Attendance::create(['user_id' => $viewer->id, 'branch_id' => $branch->id, 'check_in_at' => '2026-10-23 08:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);
        Attendance::create(['user_id' => $other->id, 'branch_id' => $branch->id, 'check_in_at' => '2026-10-23 09:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);

        $this->actingAs($viewer)->withSession(['active_branch_id' => $branch->id])
            ->get('/attendance')->assertOk()->assertDontSee('09:00:00')->assertSee('08:00:00');
    }

    public function test_manager_check_in_uses_assigned_active_branch_and_cannot_widen_report_scope(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assigned = $this->branch('ATT-4');
        $other = $this->branch('ATT-5');
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        $this->travelTo(Carbon::parse('2026-10-23 08:15:20'));

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->post('/attendance/check-in', ['branch_id' => $other->id])->assertRedirect();
        $this->assertDatabaseHas('attendances', ['user_id' => $manager->id, 'branch_id' => $assigned->id]);
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$other->id)->assertForbidden();
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23')
            ->assertOk()->assertViewHas('totalSessions', 1)->assertViewHas('openSessions', 1);
    }

    public function test_owner_can_filter_attendance_across_branches_and_dates(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $first = $this->branch('ATT-6');
        $second = $this->branch('ATT-7');
        $firstUser = User::factory()->create(['name' => 'First Employee']);
        $secondUser = User::factory()->create(['name' => 'Second Employee']);
        Attendance::create(['user_id' => $firstUser->id, 'branch_id' => $first->id, 'check_in_at' => '2026-10-22 08:00:00', 'check_out_at' => '2026-10-22 16:00:00', 'status' => Attendance::STATUS_CHECKED_OUT]);
        Attendance::create(['user_id' => $secondUser->id, 'branch_id' => $second->id, 'check_in_at' => '2026-10-23 09:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);

        $this->actingAs($owner)->get('/reports/attendance?date_from=2026-10-22&date_to=2026-10-23')
            ->assertOk()->assertViewHas('totalSessions', 2)->assertViewHas('openSessions', 1)
            ->assertSee('First Employee')->assertSee('Second Employee');
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$first->id)
            ->assertOk()->assertViewHas('totalSessions', 0);
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$second->id)
            ->assertOk()->assertViewHas('totalSessions', 1)->assertViewHas('openSessions', 1);
        $this->get('/reports/attendance?date_from=2026-10-24&date_to=2026-10-23')->assertSessionHasErrors('date_to');
    }

    public function test_inventory_role_can_manage_own_attendance_but_not_attendance_report(): void
    {
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $branch = $this->branch('ATT-8');
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->get('/attendance')->assertOk();
        $this->post('/attendance/check-in')->assertRedirect();
        $unassigned = $this->branch('ATT-9');
        $this->withSession(['active_branch_id' => $unassigned->id])
            ->post('/attendance/check-in', ['branch_id' => $unassigned->id])->assertRedirect();
        $this->assertDatabaseMissing('attendances', ['user_id' => $inventory->id, 'branch_id' => $unassigned->id]);
        $this->get('/reports/attendance')->assertForbidden();
    }

    private function branch(string $code): Branch
    {
        return Branch::create(['code' => $code, 'name' => 'Branch '.$code, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }
}
