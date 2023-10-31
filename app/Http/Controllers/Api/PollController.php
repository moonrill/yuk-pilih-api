<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePollRequest;
use App\Models\Choice;
use App\Models\Division;
use App\Models\Poll;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function __construct()
    {
        // $this->middleware(['hasVote']);
        $this->middleware(['check:admin'])->only(['create', 'delete']);
        $this->middleware(['check:user'])->only('vote');
    }

    /**
     * Creates a new poll with the given request data.
     *
     * @param CreatePollRequest $request The request data for creating a poll.
     * @throws \Illuminate\Validation\ValidationException The exception that may be thrown.
     * @return JsonResponse The JSON response containing the created poll.
     */
    public function create(CreatePollRequest $request): JsonResponse
    {
        $user = auth()->user();

        $data = $request->validated();
        $choices = $request->choices;

        // Checking array have same value
        $uniqueChoices = array_unique($request->choices);

        if (count($choices) !== count($uniqueChoices)) {
            return response()->json([
                'message' => 'Choices must be unique'
            ], 422);
        }

        // Create Poll
        $poll = Poll::query()->create([
            ...$data,
            'created_by' => $user->id,
        ]);

        // Create choices
        foreach ($request->choices as $choice) {
            Choice::query()->create([
                'choice' => $choice,
                'poll_id' => $poll->id
            ]);
        }

        if (count($request->choices) < 2) {
            return response()->json([
                'message' => 'The given data was invalid.',
            ], 422);
        }

        return response()->json($poll, 200);
    }

    /**
     * Retrieves all the data from the database.
     *
     * @return JsonResponse Returns a JSON response containing the retrieved data.
     */
    public function getAll(): JsonResponse
    {
        $user = auth()->user();
        $result = [];
        if ($user->role == 'admin') {
            $allPolls = Poll::all()->toArray();

            foreach ($allPolls as $poll) {
                $creator = User::firstWhere('id', $poll['created_by']);
                $result =
                    [
                        ...$result,
                        [
                            ...$poll,
                            'creator' => $creator->username,
                            'result' => $this->getResult($poll['id'])
                        ]
                    ];
            }
        }

        if ($user->role == 'user') {
            $expiredPoll = Poll::query()->where('deadline', '<', Carbon::now())->get();

            foreach ($user->votes as $vote) {
                $poll = Poll::where('id', $vote->poll_id)->first();
                $poll['result'] = $this->getResult($vote->poll_id);
                $result['user_votes'] = [...$result, $poll];
            }

            foreach ($expiredPoll as $poll) {
                $poll['result'] = $this->getResult($poll->id);
                $result['expired_polls'][] = $poll;
            }
        }

        return response()->json($result, 200);
    }

    /**
     * Retrieves a poll based on the provided request.
     *
     * @param Request $request The request object containing the poll ID.
     * @throws ModelNotFoundException If the poll with the provided ID is not found.
     * @return JsonResponse The JSON response containing the poll data.
     */
    public function getPoll(Request $request): JsonResponse
    {
        try {
            $poll = Poll::query()->findOrFail($request->id);
            $poll['result'] = $this->getResult($request->id);
            $poll['creator'] = User::firstWhere('id', $poll['created_by'])->username;
            $user = auth()->user();

            if ($user->role == 'user') {
                $hasVoted = false;
                foreach ($user->votes as $vote) {
                    if ($vote->poll_id == $request->id) {
                        $hasVoted = true;
                        break;
                    }
                }

                if (!$hasVoted) {
                    $poll = Poll::where([
                        ['id', $request->id],
                        ['deadline', '<', Carbon::now()]
                    ])->first();
                    $poll['result'] = $this->getResult($request->id);
                }
            }
            return response()->json($poll, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Not Found'
            ], 404);
        }
    }

    /**
     * Deletes a poll.
     *
     * @param Request $request The request object.
     * @return JsonResponse The JSON response.
     */
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


    /**
     * Get the result of a poll.
     *
     * @param int $pollId
     */
    public function getResult(int $pollId)
    {
        $poll = Poll::find($pollId);
        $choices = $poll->choices;

        $choicePoints = [];
        foreach ($choices as $choice) {
            $choicePoints[$choice->choice] = 0;
        }

        $divisions = Division::all();
        foreach ($divisions as $division) {
            $votes = $division->votes;
            $totalVotes = count($votes);

            if ($totalVotes > 0) {
                // Calculate the points for each choice in this division
                foreach ($choices as $choice) {
                    $choiceCount = $votes->where('choice', $choice)->count();
                    $choicePoints[$choice->choice] += $choiceCount / $totalVotes;
                }
            }
        }

        // Calculate the percentage for each choice
        $totalPoints = array_sum($choicePoints);
        $percentageResults = [];
        foreach ($choices as $choice) {
            if ($totalPoints > 0 && $choicePoints[$choice->choice] > 0) {
                $percentage = ($choicePoints[$choice->choice] / $totalPoints) * 100;
                $percentageResults[$choice->choice] = round($percentage, 4);
            } else {
                // Handle the case where either totalPoints or choicePoints is zero for this choice.
                $percentageResults[$choice->choice] = 0;
            }
        }

        return $percentageResults;
    }

    /**
     * Vote for a poll.
     *
     * @param Request $request The HTTP request object.
     * @throws ModelNotFoundException If the poll or choice is not found.
     * @return JsonResponse The JSON response containing the result of the voting.
     */
    public function vote(Request $request): JsonResponse
    {
        $user = auth()->user();
        try {
            $poll = Poll::findOrFail($request->poll_id);
            $choice = Choice::findOrFail($request->choice_id);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'message' => 'Invalid poll_id or choice_id',
            ], 422);
        }

        // Check user already voted
        foreach ($user->votes as $vote) {
            if ($vote->poll_id == $request->poll_id) {
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
            'choice_id' => (int) $request->choice_id,
            'user_id' => $user['id'],
            'poll_id' => (int) $request->poll_id,
            'division_id' => $user->division_id,
        ]);

        return response()->json([
            'message' => 'Voting success',
        ], 200);
    }
}
