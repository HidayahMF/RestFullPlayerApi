<?php

// /////////////////////////////////////////////////////////////////////////////
// PLEASE DO NOT RENAME OR REMOVE ANY OF THE CODE BELOW. 
// YOU CAN ADD YOUR CODE TO THIS FILE TO EXTEND THE FEATURES TO USE THEM IN YOUR WORK.
// YOU SHOULD NOT CHANGE THE DATABASE STRUCTURE, ADDING NEW FIELDS, RENAMING OR REMOVING THE CURRENT FIELDS MAY RESULT IN A FAILED TEST
// /////////////////////////////////////////////////////////////////////////////

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSkill extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'player_id',
        'skill',
        'value'
    ];

    
    protected $casts = [
        'skill' => \App\Enums\PlayerSkill::class
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
