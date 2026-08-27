<?php

// /////////////////////////////////////////////////////////////////////////////
// TESTING AREA
// THIS IS AN AREA WHERE YOU CAN TEST YOUR WORK AND WRITE YOUR TESTS
// /////////////////////////////////////////////////////////////////////////////

namespace Tests\Feature;

class PlayerControllerListingTest extends PlayerControllerBaseTest
{
    public function test_sample()
    {
        $res = $this->get(self::REQ_URI);

        $res->assertStatus(200);
        $this->assertNotNull($res);
        $this->assertEquals([], $res->json());
    }

    public function test_lists_created_players_with_skills()
    {
        $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [
                ["skill" => "attack", "value" => 60],
                ["skill" => "speed", "value" => 80]
            ]
        ])->assertStatus(201);

        $res = $this->get(self::REQ_URI);
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertCount(1, $json);
        $this->assertEquals("Alice", $json[0]['name']);
        $this->assertCount(2, $json[0]['playerSkills']);
    }

    public function test_shows_single_player()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "forward",
            "playerSkills" => [["skill" => "attack", "value" => 90]]
        ])->assertStatus(201);

        $id = $created->json('id');

        $this->get(self::REQ_URI . $id)
            ->assertStatus(200)
            ->assertJsonPath('id', $id)
            ->assertJsonPath('name', 'Alice')
            ->assertJsonCount(1, 'playerSkills');
    }

    public function test_shows_missing_player_returns_404()
    {
        $this->get(self::REQ_URI . '999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Player not found');
    }
}
