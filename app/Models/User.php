<?php

namespace App\Models;

use App\Models\Division;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticable;

class User extends Authenticable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $hidden = ['password', 'role'];
    protected $fillable = ['division_id', 'username', 'password'];
    protected $with = ['votes'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function polls()
    {
        return $this->hasMany(Poll::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
