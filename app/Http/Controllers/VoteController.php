<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteController extends Controller
{

    public function __construct()
    {
        $this->middleware(['check:user']);
    }

    public function vote(Request $request): JsonResponse
    {

    }
}
