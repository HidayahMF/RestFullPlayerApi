<?php

// /////////////////////////////////////////////////////////////////////////////
// PLEASE DO NOT RENAME OR REMOVE ANY OF THE CODE BELOW. 
// YOU CAN ADD YOUR CODE TO THIS FILE TO EXTEND THE FEATURES TO USE THEM IN YOUR WORK.
// /////////////////////////////////////////////////////////////////////////////

namespace App\Http\Controllers;

use App\Enums\PlayerPosition;
use App\Enums\PlayerSkill;
use App\Models\Player;
use App\Models\PlayerSkill as PlayerSkillModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::with('skills')->get();
        $formattedPlayers = $players->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'position' => $player->position,
                'playerSkills' => $player->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'skill' => $skill->skill,
                        'value' => $skill->value,
                        'playerId' => $skill->player_id,
                    ];
                }),
            ];
        });

        return response()->json($formattedPlayers, 200);
    }


    public function show(string $id)
    {
        $player = Player::with('skills')->find($id);

        if (!$player) {
            return response()->json([
                'message' => 'Player not found',
            ], 404);
        }

        $formattedPlayer = [
            'id' => $player->id,
            'name' => $player->name,
            'position' => $player->position,
            'playerSkills' => $player->skills->map(function ($skill) {
                return [
                    'id' => $skill->id,
                    'skill' => $skill->skill,
                    'value' => $skill->value,
                    'playerId' => $skill->player_id,
                ];
            }),
        ];

        return response()->json($formattedPlayer, 200);
    }


 public function store(Request $request)
{
    $data = $request->all();

    
    $validateData = Validator::make($data, [
        "name" => "required",
        "position" => "required|in:" . implode(',', array_column(PlayerPosition::cases(), 'value')),
        'playerSkills' => 'required|array|min:1',
        "playerSkills.*.skill" => "required|in:" . implode(',', array_column(PlayerSkill::cases(), 'value')),
        'playerSkills.*.value' => 'required',
    ], [
        "position.in" => 'Invalid value for position:' . $request->position,
        "playerSkills" => 'Player must have at least one skill',
        "playerSkills.*.skill.in" => 'Invalid value for skill: ' . collect($request->playerSkills)->pluck('skill')->implode(', ', ''),
    ]);

   
    if (isset($request->playerSkills) && is_array($request->playerSkills)) {
        $skills = collect($request->playerSkills)->pluck('skill');
        if ($skills->count() !== $skills->unique()->count()) {
      
            $duplicateSkill = $skills->duplicates()->first();
            return response()->json([
                'message' => "Duplicate skill detected: {$duplicateSkill}"
            ], 400);
        }
    }

    
    if ($validateData->fails()) {
        return response()->json([
            "message" => $validateData->errors()->first(),
        ], 400);
    }

   
    $player = Player::create($data);


    foreach ($request->playerSkills as $skill) {
        PlayerSkillModel::create([
            'player_id' => $player->id,
            'skill' => $skill['skill'],
            'value' => $skill['value']
        ]);
    }

    
    $formattedPlayer = [
        'id' => $player->id,
        'name' => $player->name,
        'position' => $player->position,
        'playerSkills' => $player->skills->map(function ($skill) {
            return [
                'id' => $skill->id,
                'skill' => $skill->skill,
                'value' => $skill->value,
                'playerId' => $skill->player_id,
            ];
        }),
    ];

    return response()->json($formattedPlayer, 201);
}



   public function update(Request $request, $id)
{
    $player = Player::find($id);

    if (!$player) {
        return response()->json([
            'message' => 'Player not found'
        ], 404);
    }

    $data = $request->all();

    
    $validator = Validator::make($data, [
        "name" => "required",
        "position" => "required|in:" . implode(',', array_column(PlayerPosition::cases(), 'value')),
        'playerSkills' => 'required|array|min:1',
        "playerSkills.*.skill" => "required|in:" . implode(',', array_column(PlayerSkill::cases(), 'value')),
        'playerSkills.*.value' => 'required',
    ], [
        "position.in" => 'Invalid value for position:' . $request->position,
        "playerSkills" => 'Player must have at least one skill',
        "playerSkills.*.skill.in" => 'Invalid value for skill: ' . collect($request->playerSkills)->pluck('skill')->implode(', ', ''),
    ]);

   
    if (isset($request->playerSkills) && is_array($request->playerSkills)) {
        $skills = collect($request->playerSkills)->pluck('skill');
        if ($skills->count() !== $skills->unique()->count()) {
            $duplicateSkill = $skills->duplicates()->first();
            return response()->json([
                'message' => "Duplicate skill detected: {$duplicateSkill}"
            ], 400);
        }
    }

    
    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first()
        ], 400);
    }

    
    $player->update($data);

    
    if ($request->has('playerSkills')) {
        PlayerSkillModel::where('player_id', $player->id)->delete();

        foreach ($request->playerSkills as $skill) {
            PlayerSkillModel::create([
                'player_id' => $player->id,
                'skill' => $skill['skill'],
                'value' => $skill['value'],
            ]);
        }
    }

  
    $formattedPlayer = [
        'id' => $player->id,
        'name' => $player->name,
        'position' => $player->position,
        'playerSkills' => $player->skills->map(function ($skill) {
            return [
                'id' => $skill->id,
                'skill' => $skill->skill,
                'value' => $skill->value,
                'playerId' => $skill->player_id,
            ];
        }),
    ];

    return response()->json($formattedPlayer, 200);
}




    public function destroy(Request $request, $id)
    {
        $token = $request->bearerToken();
        $expectedToken = 'SkFabTZibXE1aE14ckpQUUxHc2dnQ2RzdlFRTTM2NFE2cGI4d3RQNjZmdEFITmdBQkE=';

        if ($token !== $expectedToken) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $player = Player::find($id);
        if (!$player) {
            return response()->json([
                'message' => 'Player not found'
            ], 404);
        }
        PlayerSkillModel::where('player_id', $player->id)->delete();
        $player->delete();

        return response()->json([
            'message' => 'Player deleted successfully'
        ], 200);
    }

    public function processTeam(Request $request)
    {
        $requirements = $request->all();

        if (empty($requirements)) {
            return response()->json([
                'message' => 'Invalid request format or empty requirements'
            ], 400);
        }


        $seenRequirements = [];
        foreach ($requirements as $req) {
            $position = $req['position'] ?? null;
            $mainSkill = $req['mainSkill'] ?? null;
            $key = $position . '-' . $mainSkill;
            if (isset($seenRequirements[$key])) {
                return response()->json([
                    'message' => "Duplicate position + mainSkill combination: {$position} + {$mainSkill}"
                ], 400);
            }
            $seenRequirements[$key] = true;
        }

        $selectedPlayerIds = [];
        $team = [];

        foreach ($requirements as $req) {
            $position = $req['position'] ?? null;
            $mainSkill = $req['mainSkill'] ?? null;
            $numberOfPlayers = $req['numberOfPlayers'] ?? 0;

            $players = Player::where('position', $position)
                ->whereNotIn('id', $selectedPlayerIds)
                ->with('skills')
                ->get();

            if ($players->count() < $numberOfPlayers) {
                return response()->json([
                    'message' => "Insufficient number of players for position: {$position}"
                ], 400);
            }

            $players = $players->sortByDesc(function ($player) use ($mainSkill) {
                $skill = $player->skills->firstWhere('skill', $mainSkill);
                if ($skill) {
                    return $skill->value;
                }
                return $player->skills->max('value') ?? 0;
            });

            $chosenPlayers = $players->take($numberOfPlayers);

            foreach ($chosenPlayers as $player) {
                $selectedPlayerIds[] = $player->id;

                $mainSkillObj = $player->skills->firstWhere('skill', $mainSkill);
                if ($mainSkillObj) {
                    $skillsToShow = [$mainSkillObj];
                } else {
                    $maxValue = $player->skills->max('value');
                    $skillsToShow = $player->skills->filter(fn($s) => $s->value == $maxValue)->values();
                }

                $team[] = [
                    'id' => $player->id,
                    'name' => $player->name,
                    'position' => $player->position,
                    'playerSkills' => $skillsToShow->map(function ($skill) {
                        return [
                            'id' => $skill->id,
                            'skill' => $skill->skill,
                            'value' => $skill->value,
                            'playerId' => $skill->player_id,
                        ];
                    })
                ];
            }
        }


        $resultPlayer = Player::whereIn('id', collect($team)->pluck("id"))->get();
        $formattedResultPlayer = $resultPlayer->map(function ($player) {
            return [
                "name" => $player->name,
                "position" => $player->position,
                "playerSkills" => $player->skills->map(function ($skill) {
                    return [
                        "skill" => $skill->skill,
                        "value" => $skill->value
                    ];
                }),
            ];
        });

        return response()->json($formattedResultPlayer, 200);
    }
}
