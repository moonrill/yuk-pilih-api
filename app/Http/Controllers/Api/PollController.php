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
        $this->middleware(['auth:api', 'check:admin']);
    }

    public function create(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validator = Validator::make($request->only(['title', 'description', 'deadline', 'choices']), [
            'title' => 'required|string|max:191',
            'description' => 'required|string',
            'deadline' => 'required|date_format:Y-m-d H:i:s',
            'choices' => 'required|array'
        ]);

        if ($validator->fails()) {
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

    public function getAll(): JsonResponse
    {
        $allPoll = Poll::all()->toArray();

        $res = [];
        foreach ($allPoll as $poll) {
            $res = [...$res, [...$poll, 'creator' => auth()->user()->username]];
        }

        return response()->json($res, 200);
    }

    public function getPoll(Request $request): JsonResponse
    {
        $poll = Poll::firstWhere('id', $request->id);
        return !$poll ? response()->json(['error'=>'Not Found'], 404): response()->json($poll, 200);
    }

    public function delete(Request $request): JsonResponse
    {
        $poll = Poll::findOrFail($request->id);

        if(!$poll){
            return response()->json([
                'error' => 'Not Found'
            ], 404);
        }

        $poll->delete();

        return response()->json([
            'message' => 'Delete success!'
        ], 200);
    }

}
