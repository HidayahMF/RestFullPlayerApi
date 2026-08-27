<?php

// /////////////////////////////////////////////////////////////////////////////
// TESTING AREA
// THIS IS AN AREA WHERE YOU CAN TEST YOUR WORK AND WRITE YOUR TESTS
// /////////////////////////////////////////////////////////////////////////////

namespace Tests\Feature;

class PlayerControllerUpdateTest extends PlayerControllerBaseTest
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

        $res = $this->putJson(self::REQ_URI . '1', $data);

        $res->assertStatus(404);
        $this->assertNotNull($res);
    }

    public function test_updates_existing_player()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(201);
        $id = $created->json('id');

        $this->putJson(self::REQ_URI . $id, [
            "name" => "Alice Updated",
            "position" => "midfielder",
            "playerSkills" => [
                ["skill" => "speed", "value" => 85],
                ["skill" => "stamina", "value" => 70]
            ]
        ])
            ->assertStatus(200)
            ->assertJsonPath('id', $id)
            ->assertJsonPath('name', 'Alice Updated')
            ->assertJsonPath('position', 'midfielder');

        $this->assertDatabaseHas('players', ['id' => $id, 'name' => 'Alice Updated', 'position' => 'midfielder']);
    }

    public function test_update_missing_player_returns_404()
    {
        $this->putJson(self::REQ_URI . '999', [
            "name" => "Nobody",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(404);
    }

    public function test_update_replaces_skills_in_response()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(201);
        $id = $created->json('id');

        $res = $this->putJson(self::REQ_URI . $id, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [
                ["skill" => "speed", "value" => 85],
                ["skill" => "stamina", "value" => 70]
            ]
        ]);

        $res->assertStatus(200);
        $json = $res->json();
        $this->assertCount(2, $json['playerSkills']);
        $this->assertEquals('speed', $json['playerSkills'][0]['skill']);
        $this->assertEquals(85, $json['playerSkills'][0]['value']);
        $this->assertEquals('stamina', $json['playerSkills'][1]['skill']);
        $this->assertEquals(70, $json['playerSkills'][1]['value']);
    }

    public function test_update_rejects_invalid_position()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(201);
        $id = $created->json('id');

        $this->putJson(self::REQ_URI . $id, [
            "name" => "Alice",
            "position" => "kicker",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(400);
    }

    public function test_update_rejects_duplicate_skills()
    {
        $created = $this->postJson(self::REQ_URI, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [["skill" => "attack", "value" => 60]]
        ])->assertStatus(201);
        $id = $created->json('id');

        $this->putJson(self::REQ_URI . $id, [
            "name" => "Alice",
            "position" => "defender",
            "playerSkills" => [
                ["skill" => "attack", "value" => 60],
                ["skill" => "attack", "value" => 70]
            ]
        ])->assertStatus(400);
    }
}
