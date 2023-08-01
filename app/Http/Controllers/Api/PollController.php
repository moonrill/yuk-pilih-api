<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Choice;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function create(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user['role'] == 'user') {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->only(['title', 'description', 'deadline', 'choices']), [
            'title' => 'required|string|max:191',
            'description' => 'required|string',
            'deadline' => 'required',
            'choices' => 'required|array'
        ]);

        if ($validator->fails()){
            return response()->json([
                'error' => 'The given data was invalid.',
            ], 422);
        }

        $poll = Poll::create([
            'title' => $request->title,
            'description' => $request->description,
            'deadline' => $request->deadline,
            'created_by' => $user["id"]
        ]);

        foreach ($request->choices as $choice) {
            Choice::create([
                'choice' => $choice,
                'poll_id' => $poll['id']
            ]);
        }

        if (count($request->choices) < 2) {
            return response()->json([
                'error' => 'The given data was invalid.',
            ], 422);
        }

        return response()->json($poll, 200);
    }
}
