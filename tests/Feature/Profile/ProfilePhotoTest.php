<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->profile->profile_photo_path);
        Storage::disk('public')->assertExists($user->fresh()->profile->profile_photo_path);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        // 6MB file, max is 5MB (5120KB)
        $file = UploadedFile::fake()->image('avatar.jpg')->size(6000);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_user_can_delete_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $file->store('profile-photos', 'public');
        
        $user->profile()->create(['profile_photo_path' => $path]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/profile/photo');

        $response->assertStatus(200);

        $this->assertNull($user->fresh()->profile->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_unauthenticated_user_cannot_upload_photo(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(401);
    }
}
