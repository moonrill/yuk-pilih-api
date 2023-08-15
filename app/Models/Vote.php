<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    protected $table = 'votes';
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
    public function choice()
    {
        return $this->belongsTo(Choice::class);
    }

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
