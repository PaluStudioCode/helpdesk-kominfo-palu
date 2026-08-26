<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseAndModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_models_are_seeded_correctly(): void
    {
        $this->assertGreaterThanOrEqual(2, Department::count());
        $this->assertGreaterThanOrEqual(10, TicketCategory::count());
        $this->assertGreaterThanOrEqual(4, User::count());
    }

    public function test_department_user_relationship(): void
    {
        $dinkes = Department::where('code', 'DINKES')->first();
        $operator = User::where('email', 'operator@dinkes.palukota.go.id')->first();

        $this->assertTrue($dinkes->users->contains($operator));
        $this->assertEquals($operator->department->id, $dinkes->id);
    }

    public function test_ticket_creation_and_relationships(): void
    {
        $dinkes = Department::where('code', 'DINKES')->first();
        $operator = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        $category = TicketCategory::where('network_type', 'fiber_optic')->first();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-20260818-0001',
            'department_id' => $dinkes->id,
            'reporter_id' => $operator->id,
            'category_id' => $category->id,
            'network_type' => $category->network_type,
            'title' => 'Internet Dinas Kesehatan Mati',
            'location_details' => 'Ruang Pelayanan',
            'description' => 'Koneksi FO terputus sejak pagi.',
            'priority' => 'high',
            'status' => 'open',
            'due_at' => now()->addHours($category->sla_hours),
        ]);

        $this->assertDatabaseHas('tickets', [
            'ticket_number' => 'TKT-20260818-0001',
            'network_type' => 'fiber_optic',
        ]);

        $this->assertEquals($dinkes->id, $ticket->department->id);
        $this->assertEquals($operator->id, $ticket->reporter->id);
        $this->assertEquals($category->id, $ticket->category->id);
    }

    public function test_soft_delete_functionality(): void
    {
        $disdik = Department::where('code', 'DISDIK')->first();
        $disdik->delete();

        $this->assertSoftDeleted('departments', [
            'code' => 'DISDIK'
        ]);
        
        $this->assertNull(Department::where('code', 'DISDIK')->first());
        $this->assertNotNull(Department::withTrashed()->where('code', 'DISDIK')->first());
    }
}
