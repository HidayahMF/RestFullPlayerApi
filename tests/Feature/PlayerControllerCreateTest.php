<?php

// /////////////////////////////////////////////////////////////////////////////
// TESTING AREA
// THIS IS AN AREA WHERE YOU CAN TEST YOUR WORK AND WRITE YOUR TESTS
// /////////////////////////////////////////////////////////////////////////////

namespace Tests\Feature;

class PlayerControllerCreateTest extends PlayerControllerBaseTest
{
    public function test_sample()
    {
        $data = [
            "name" => "test",
            "position" => "defender",
            "playerSkills" => [
                ["skill" => "attack", "value" => 60],
                ["skill" => "speed", "value" => 80]
            ]
        ];

        $res = $this->postJson(self::REQ_URI, $data);
        $res->assertStatus(201);

        $json = $res->json();
        $this->assertNotNull($res);
        $this->assertEquals("test", $json['name']);
        $this->assertEquals("defender", $json['position']);
        $this->assertCount(2, $json['playerSkills']);
        $this->assertEquals("attack", $json['playerSkills'][0]['skill']);
        $this->assertEquals(60, $json['playerSkills'][0]['value']);
    }

    public function test_player_created_with_skills_in_database()
    {
        $data = [
            "name" => "Alice",
            "position" => "midfielder",
            "playerSkills" => [
                ["skill" => "stamina", "value" => 90],
                ["skill" => "speed", "value" => 70]
            ]
        ];

        $this->postJson(self::REQ_URI, $data)->assertStatus(201);

        $this->assertDatabaseHas('players', ['name' => 'Alice', 'position' => 'midfielder']);
        $this->assertDatabaseHas('player_skills', ['skill' => 'stamina', 'value' => 90]);
        $this->assertDatabaseHas('player_skills', ['skill' => 'speed', 'value' => 70]);
    }

    public function test_it_rejects_missing_name()
    {
        $data = [
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ];

        $this->postJson(self::REQ_URI, $data)->assertStatus(400);
    }

    public function test_it_rejects_invalid_position()
    {
        $data = [
            "name" => "Bob",
            "position" => "goalkeeper",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ];

        $this->postJson(self::REQ_URI, $data)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid value for position:goalkeeper');
    }

    public function test_it_rejects_invalid_skill()
    {
        $data = [
            "name" => "Bob",
            "position" => "defender",
            "playerSkills" => [["skill" => "cooking", "value" => 60]]
        ];

        $this->postJson(self::REQ_URI, $data)->assertStatus(400);
    }

    public function test_it_requires_at_least_one_skill()
    {
        $data = [
            "name" => "Bob",
            "position" => "defender",
            "playerSkills" => []
        ];

        $this->postJson(self::REQ_URI, $data)->assertStatus(400);
    }

    public function test_it_rejects_duplicate_skills()
    {
        $data = [
            "name" => "Bob",
            "position" => "forward",
            "playerSkills" => [
                ["skill" => "attack", "value" => 70],
                ["skill" => "attack", "value" => 80]
            ]
        ];

        $this->postJson(self::REQ_URI, $data)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Duplicate skill detected: attack');
    }
}
