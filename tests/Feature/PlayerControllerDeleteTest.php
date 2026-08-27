<?php

// /////////////////////////////////////////////////////////////////////////////
// TESTING AREA
// THIS IS AN AREA WHERE YOU CAN TEST YOUR WORK AND WRITE YOUR TESTS
// /////////////////////////////////////////////////////////////////////////////

namespace Tests\Feature;

class PlayerControllerDeleteTest extends PlayerControllerBaseTest
{
    public function test_sample()
    {
        $res = $this->delete(self::REQ_URI . '1');

        $res->assertStatus(401);
        $this->assertNotNull($res);
    }

    public function test_delete_requires_valid_token()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(201);
        $id = $created->json('id');

        $this->delete(self::REQ_URI . $id)
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized');

        $this->assertDatabaseHas('players', ['id' => $id]);
    }

    public function test_delete_rejects_invalid_token()
    {
        $res = $this->delete(self::REQ_URI . '1', [], ['Authorization' => 'Bearer wrong-token']);
        $res->assertStatus(401);
    }

    public function test_delete_player_with_valid_token()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(201);
        $id = $created->json('id');

        $token = config('services.player_api_token');

        $this->delete(self::REQ_URI . $id, [], ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Player deleted successfully');

        $this->assertDatabaseMissing('players', ['id' => $id]);
        $this->assertDatabaseMissing('player_skills', ['player_id' => $id]);
    }

    public function test_delete_missing_player_with_valid_token_returns_404()
    {
        $token = config('services.player_api_token');

        $this->delete(self::REQ_URI . '999', [], ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Player not found');
    }
}
