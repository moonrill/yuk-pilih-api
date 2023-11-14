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
        $allPolls = Poll::query()->orderByDesc('created_at')->get()->toArray();
        $result = [];
        
        if ($user->role == 'admin') {
            foreach ($allPolls as $poll) {
                $creator = User::firstWhere('id', $poll['created_by']);
                $result[] = [
                                ...$poll,
                                'creator' => $creator->username,
                                'result' => $this->getResult($poll['id'])
                            ];
            }
        }

        if ($user->role == 'user') {
            $expiredPolls = Poll::query()->where('deadline', '<', Carbon::now())->orderBy('deadline')->get();

            // Get polls that user has voted
            foreach ($user->votes as $vote) {
                $poll = Poll::where('id', $vote->poll_id)->first();
                $poll['result'] = $this->getResult($vote->poll_id);
                $result['user_votes'][] = $poll;
            }
            
            // Get expired polls
            foreach ($expiredPolls as $poll) {
                $poll['result'] = $this->getResult($poll->id);
                $result['expired_polls'][] = $poll;
            }

            // Get available polls
            $skip = false;
            foreach($allPolls as $poll) {
                foreach ($user->votes as $vote) {
                    if($poll['id'] == $vote->poll_id) {
                        $skip = true;
                        break;
                    }
                }
                
                foreach ($expiredPolls as $expPoll) {
                    if($poll['id'] == $expPoll->id) {
                        $skip = true;
                        break;
                    }
                }

                if($skip) {
                    $skip = false;
                    continue;
                }

                $result['available_polls'][] = $poll;
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
        
        // Get all votes from all division that vote for this poll
        $divisions = Division::query()->with(['votes' => function($query) use($pollId) {
            return $query->where('poll_id', $pollId);
        }])->whereHas('votes', function($query) use($pollId) {
            return $query->where('poll_id', $pollId);
        })->get();
        
        // Assign the initial points to each choice
        $totalChoicePoints = [];
        foreach ($choices as $choice) {
            $totalChoicePoints[$choice->choice] = 0;
        }

        $totalDivisions = $divisions->count();

        if($totalDivisions === 0) {
            return $totalChoicePoints;
        }
        
        // Calculate the points for each choice in each division
        foreach ($divisions as $division) {
            $votes = $division->votes;
            $choicesCount = [];

            // Calculate the points for each choice in this division
            foreach ($choices as $choice) {
                $choicesCount[$choice->choice] = $votes->where('choice_id', $choice->id)->count();
            }

            $highestCount = max($choicesCount);
            $winners = array_keys($choicesCount, $highestCount);

            if (count($winners) === 1) {
                $totalChoicePoints[$winners[0]] += 1;
            } else {
                $pointsPerWinner = 1 / count($winners);
                foreach ($winners as $winner) {
                    $totalChoicePoints[$winner] += $pointsPerWinner;
                }
            }
        }

        // Calculate the percentage for each choice
        $percentageResults = [];
        foreach ($choices as $choice) {
            $percentage = ($totalChoicePoints[$choice->choice] / $totalDivisions) * 100;
            $percentageResults[$choice->choice] = round($percentage, 2);
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
