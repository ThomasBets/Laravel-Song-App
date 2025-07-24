<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }
    public function test_it_can_list_all_songs()
    {
        Song::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/songs');

        $response->assertJsonCount(3, 'songs.data');
    }

    public function test_can_create_a_song()
    {
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'title' => 'Viva La Vida',
            'description' => 'A song by Coldplay.',
            'genre' => 'Rock',
            'release_date' => '2008-06-12',
        ];

        $response = $this->postJson('/api/songs', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'song' => ['id', 'title', 'description', 'genre', 'release_date', 'user_id', 'created_at', 'updated_at'],
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'role',
                ]
            ])
            ->assertJsonFragment([
                'title' => 'Viva La Vida',
                'genre' => 'Rock',
                'user_id' => $user->id,  // confirm the song belongs to the authenticated user
            ]);

        // Assert the DB has the song linked to this user
        $this->assertDatabaseHas('songs', [
            'title' => 'Viva La Vida',
            'genre' => 'Rock',
            'user_id' => $user->id,
        ]);
    }

    public function test_can_show_a_song()
    {
        $song = Song::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/songs/{$song->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => $song->title,
                'genre' => $song->genre,
            ]);
    }

    public function test_it_returns_404_if_song_not_found()
    {
        $response = $this->getJson('/api/songs/1065');

        $response->assertStatus(404);
    }

    public function test_can_update_a_song()
    {
        $song = Song::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Old Title',
        ]);
        $update = [
            'title' => 'Updated Title',
            'description' => $song->description,
            'genre' => $song->genre,
            'release_date' => $song->release_date,
        ];

        $response = $this->putJson("/api/songs/{$song->id}", $update);

        $response->assertJson([
            'message' => 'Song updated successfully.',
        ]);

        $this->assertDatabaseHas('songs', ['id' => $song->id, 'title' => 'Updated Title']);
    }

    public function test_can_delete_a_song()
    {
        $song = Song::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/songs/{$song->id}");

        $response->assertStatus(200)
            ->assertSee('Song deleted successfully!');

        $this->assertDatabaseMissing('songs', ['id' => $song->id]);
    }

    public function test_it_validates_required_fields_on_create()
    {
        $response = $this->postJson('/api/songs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'genre']);
    }

    public function test_it_can_filter_songs_by_genre()
    {
        Song::factory()->create(['genre' => 'Rock', 'user_id' => $this->user->id]);
        Song::factory()->create(['genre' => 'Pop', 'user_id' => $this->user->id]);

        $response = $this->getJson('/api/songs?genre=Rock');

        $response->assertStatus(200)
            ->assertJsonFragment(['genre' => 'Rock'])
            ->assertJsonMissing(['genre' => 'Pop']);
    }

    public function test_it_handles_many_songs()
    {
        // Create 500 songs linked to the test user
        Song::factory()->count(500)->create(['user_id' => $this->user->id]);

        // Fetch the first page, assuming 50 per page
        $response = $this->getJson('/api/songs?page=1');

        $response->assertStatus(200)
            ->assertJsonPath('songs.total', 500)    // total songs in pagination meta
            ->assertJsonPath('songs.per_page', 50) // pagination size
            ->assertJsonCount(50, 'songs.data');   // items on current page
    }

    public function test_full_song_workflow()
    {
        // Create a song
        $createPayload = [
            'title' => 'Test Song',
            'description' => 'Initial description',
            'genre' => 'Jazz',
            'release_date' => '2023-01-01',
        ];

        $createResponse = $this->postJson('/api/songs', $createPayload);
        $createResponse->assertStatus(200);
        $songId = $createResponse->json('song.id');

        // Update the song
        $updatePayload = [
            'description' => 'Updated description',
        ];

        $updateResponse = $this->putJson("/api/songs/{$songId}", $updatePayload);
        $updateResponse->assertStatus(200)
            ->assertJson(['message' => 'Song updated successfully.']);

        // Verify the song was updated in DB
        $this->assertDatabaseHas('songs', [
            'id' => $songId,
            'description' => 'Updated description',
        ]);

        // Delete the song
        $deleteResponse = $this->deleteJson("/api/songs/{$songId}");
        $deleteResponse->assertStatus(200)
            ->assertSee('Song deleted successfully!');

        // Verify the song is deleted
        $this->assertDatabaseMissing('songs', ['id' => $songId]);
    }
}
