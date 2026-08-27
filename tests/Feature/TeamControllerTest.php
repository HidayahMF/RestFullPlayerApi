<?php

// /////////////////////////////////////////////////////////////////////////////
// TESTING AREA
// THIS IS AN AREA WHERE YOU CAN TEST YOUR WORK AND WRITE YOUR TESTS
// /////////////////////////////////////////////////////////////////////////////

namespace Tests\Feature;

class TeamControllerTest extends PlayerControllerBaseTest
{
    public function test_sample()
    {
        $requirements = [
            'position' => "defender",
            'mainSkill' => "speed",
            'numberOfPlayers' => 1
        ];

        $res = $this->postJson(self::REQ_TEAM_URI, $requirements);
        $res->assertStatus(400);
        $this->assertNotNull($res);
    }

    public function test_selects_best_players_for_position_and_main_skill()
    {
        $this->createPlayer('Defender1', 'defender', ['speed' => 70, 'defense' => 80]);
        $this->createPlayer('Defender2', 'defender', ['speed' => 95, 'defense' => 70]);
        $this->createPlayer('Midfielder1', 'midfielder', ['speed' => 60, 'attack' => 70]);

        $res = $this->postJson(self::REQ_TEAM_URI, [
            ['position' => 'defender', 'mainSkill' => 'speed', 'numberOfPlayers' => 1]
        ]);

        $res->assertStatus(200);
        $team = $res->json();
        $this->assertCount(1, $team);
        $this->assertEquals('Defender2', $team[0]['name']);
        $this->assertEquals('defender', $team[0]['position']);
    }

    public function test_selects_multiple_players_without_reusing_them()
    {
        $this->createPlayer('Def1', 'defender', ['speed' => 90]);
        $this->createPlayer('Def2', 'defender', ['speed' => 80]);
        $this->createPlayer('Def3', 'defender', ['speed' => 70]);

        $res = $this->postJson(self::REQ_TEAM_URI, [
            ['position' => 'defender', 'mainSkill' => 'speed', 'numberOfPlayers' => 2],
            ['position' => 'defender', 'mainSkill' => 'defense', 'numberOfPlayers' => 1]
        ]);

        $res->assertStatus(200);
        $team = $res->json();
        $names = collect($team)->pluck('name')->all();
        $this->assertCount(3, $team);
        $this->assertContains('Def1', $names);
        $this->assertContains('Def2', $names);
        $this->assertContains('Def3', $names);
    }

    public function test_returns_400_when_insufficient_players()
    {
        $this->createPlayer('Def1', 'defender', ['speed' => 90]);

        $this->postJson(self::REQ_TEAM_URI, [
            ['position' => 'defender', 'mainSkill' => 'speed', 'numberOfPlayers' => 5]
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Insufficient number of players for position: defender');
    }

    public function test_returns_400_on_duplicate_position_skill_combination()
    {
        $this->postJson(self::REQ_TEAM_URI, [
            ['position' => 'defender', 'mainSkill' => 'speed', 'numberOfPlayers' => 1],
            ['position' => 'defender', 'mainSkill' => 'speed', 'numberOfPlayers' => 1]
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Duplicate position + mainSkill combination: defender + speed');
    }

    public function test_returns_400_on_empty_requirements()
    {
        $this->postJson(self::REQ_TEAM_URI, [])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid request format or empty requirements');
    }

    public function test_falls_back_to_highest_skill_when_main_skill_missing()
    {
        // No player has the 'speed' skill; fallback to highest skill value.
        $this->createPlayer('StrengthGuy', 'forward', ['strength' => 99, 'attack' => 40]);

        $res = $this->postJson(self::REQ_TEAM_URI, [
            ['position' => 'forward', 'mainSkill' => 'speed', 'numberOfPlayers' => 1]
        ]);

        $res->assertStatus(200);
        $team = $res->json();
        $this->assertCount(1, $team);
        $this->assertEquals('StrengthGuy', $team[0]['name']);
    }

    protected function createPlayer(string $name, string $position, array $skills)
    {
        $payload = [
            'name' => $name,
            'position' => $position,
            'playerSkills' => collect($skills)->map(function ($value, $skill) {
                return ['skill' => $skill, 'value' => $value];
            })->values()->all(),
        ];

        return $this->postJson(self::REQ_URI, $payload)->assertStatus(201);
    }
}
