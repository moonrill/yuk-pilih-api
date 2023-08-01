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
    protected $hidden = ['password'];
    protected $fillable = ['division_id', 'username', 'password'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function polls()
    {
        return $this->hasMany(Poll::class);
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
