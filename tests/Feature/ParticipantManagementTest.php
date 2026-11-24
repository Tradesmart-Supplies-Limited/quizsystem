<?php

namespace Tests\Feature;

use App\Models\Participant;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParticipantManagementTest extends TestCase
{
    public function test_participant_crud_and_photo_upload(): void
    {
        $this->actingAs($this->facilitator());
        $this->post(route('admin.participants.store'), [
            'full_name' => 'Jane Wanjiku', 'department' => 'Operations', 'is_active' => 1,
            'photo' => UploadedFile::fake()->image('me.png', 200, 200),
        ])->assertRedirect();
        $p = Participant::firstOrFail();
        $this->assertSame('Jane', $p->display_name); // derived from full name
        $this->assertNotNull($p->photo_path);
        $this->assertFileExists(public_path('uploads/'.$p->photo_path));
        $this->assertDoesNotMatchRegularExpression('/me\.png/', $p->photo_path); // random file name

        $this->put(route('admin.participants.update', $p), ['full_name' => 'Jane W.', 'display_name' => 'JW', 'remove_photo' => 1])->assertRedirect();
        $p->refresh();
        $this->assertSame('JW', $p->display_name);
        $this->assertNull($p->photo_path);
        $this->assertFalse($p->is_active);

        $this->delete(route('admin.participants.destroy', $p));
        $this->assertSoftDeleted($p);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->facilitator())->post(route('admin.participants.store'), [
            'full_name' => 'X', 'photo' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');
        $this->assertSame(0, Participant::count());
    }

    public function test_import_and_export(): void
    {
        $csv = "full_name,display_name,department,job_title\nAnn Muthoni,Ann,Operations,Clerk\nPeter Kamau,,Logistics,Driver\n";
        $this->actingAs($this->facilitator())->post(route('admin.participants.import'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])
            ->assertSessionHas('success');
        $this->assertSame(2, Participant::count());
        $this->assertSame('Peter', Participant::where('full_name', 'Peter Kamau')->value('display_name'));
        $this->assertStringContainsString('Ann Muthoni', $this->get(route('admin.participants.export'))->streamedContent());
    }

    public function test_history_page_and_search(): void
    {
        $p = Participant::factory()->create(['full_name' => 'Zed Unique']);
        $this->actingAs($this->facilitator());
        $this->get(route('admin.participants.show', $p))->assertOk()->assertSee('Zed Unique');
        $this->get(route('admin.participants.index', ['q' => 'Unique']))->assertSee('Zed Unique');
    }
}
