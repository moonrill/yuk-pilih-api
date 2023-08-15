<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Choice;
use App\Models\Division;
use App\Models\Poll;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function PHPUnit\Framework\isEmpty;

class PollController extends Controller
{
    public function __construct()
    {
        // $this->middleware(['hasVote']);
       $this->middleware(['check:admin'])->only(['create', 'delete']);
    }

    public function create(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validator = Validator::make($request->only(['title', 'description', 'deadline', 'choices']), [
            'title' => 'required|string|max:191',
            'description' => 'required|string',
            'deadline' => 'required|date_format:Y-m-d H:i:s',
            'choices' => 'required|array|min:2'
        ]);

        // Checking array have same value
        $uniqueArray = array_unique($request->choices);

        if (count($request->choices) !== count($uniqueArray)) {
            return response()->json([
                'error' => 'Array must be unique'
            ], 403);
        }

        if ($validator->fails()) {
            return response()->json([
                'error' => 'The given data was invalid.',
            ], 422);
        }

        // Create Poll
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
        $user = auth()->user();
        $res = [];
        $result = [];
        if ($user->role == 'admin') {
            $allPoll = Poll::all()->toArray();

            foreach ($allPoll as $poll) {
                $creator = User::firstWhere('id', $poll['created_by']);
                $res =
                    [...$res,
                        [...$poll,
                            'creator' => $creator->username,
                            'result' => $this->getResult($poll['id'])
                        ]
                    ];
            }
        }

        if ($user->role == 'user') {
            $expiredPoll = Poll::where('deadline', '<', Carbon::now())->get();

            foreach ($user->votes as $vote) {
                $poll = Poll::where('id', $vote->poll_id)->first();
                $res = ['user_votes' => [...$res, $poll]];
            }

            $res = [...$res, 'expired_poll' => $expiredPoll];
        }

        return response()->json($res, 200);
    }

    public function getPoll(Request $request): JsonResponse
    {
        try {
            $poll = Poll::findOrFail($request->id);
            $user = auth()->user();
            if ($user->role == 'admin') {
                return response()->json($poll, 200);
            }


            if ($user->role == 'user') {
                foreach ($user->votes as $vote) {
                    if ($vote->poll_id == $request->id) {
                        break;
                    }
                    $poll = Poll::where([
                        ['id', $request->id],
                        ['deadline', '<', Carbon::now()]
                    ])->first();
                }

                return response()->json($poll, 200);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Not Found'
            ], 404);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        $poll = Poll::find($request->id);

        if (!$poll) {
            return response()->json([
                'error' => 'Not Found'
            ], 404);
        }

        $poll->delete();

        return response()->json([
            'message' => 'Delete success!'
        ], 200);
    }

    protected function getResult($pollId)
    {
        $choices = Choice::with('votes')->where('poll_id', $pollId)->get(['id', 'choice']);
        $points = [];

        foreach($choices as $choice) {
            $votesByDivision = $choice->votes->groupBy('division.name');

            foreach ($votesByDivision as $division => $votes) {
                $votesCount = count($votes);

                if (!isset($points[$division])) {
                    $points[$division] = [];
                }
                $points[$division][$choice->choice] = $votesCount;

                // TODO: Calculate points of each division
            }            
        }


        return $points;
    }

    public function vote(Request $request): JsonResponse
    {
        $user = auth()->user();
        try {
            $poll = Poll::findOrFail($request->id);
            $choice = Choice::findOrFail($request->choice_id);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'message' => 'Invalid poll_id or choice_id',
            ], 422);
        }

        if ($user['role'] == 'admin') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check user already voted
        foreach ($user->votes as $vote) {
            if ($vote->poll_id == $request->id) {
                return response()->json([
                    'message' => 'Already voted.'
                ], 422);
            }
        }

        // Check Poll deadline
        if ($poll->deadline->isPast()) {
            return response()->json([
                'message' => 'Poll has expired'
            ], 422);
        }

        $vote = Vote::create([
            'choice_id' => (int)$request->choice_id,
            'user_id' => $user['id'],
            'poll_id' => (int)$request->id,
            'division_id' => $user['division_id']
        ]);

        return response()->json([
            'message' => 'Voting success',

        ], 200);
    }
}
