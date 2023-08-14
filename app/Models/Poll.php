<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;

    protected $table = 'polls';
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    protected $with = ['choices'];
    protected $hidden = ['updated_at'];
    protected $casts = ['deadline' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function choices()
    {
        return $this->hasMany(Choice::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
