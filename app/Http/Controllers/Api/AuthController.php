<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['login', 'register']]);
    }

    /**
     * Logs in a user.
     *
     * @param Request $request The HTTP request object.
     * @return JsonResponse The JSON response containing the access token, token type, and expiration time.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Username or password do not match or empty"
            ], 401);
        }

        $token = Auth::attempt($request->only(['username', 'password']));

        if (!$token) {
            return response()->json([
                'message' => "Username or password do not match or empty"
            ], 401);
        }

        $payload = auth()->payload();
        $expiresIn = Carbon::parse($payload('exp'))->setTimezone('Asia/Jakarta')->format('H:i:s, d-m-Y');

        if($request->password === 'password123') {
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $expiresIn,
            ], 307);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
        ], 200);
    }

    /**
     * Registers a new user.
     *
     * @param Request $request The request object containing the user details.
     * @return JsonResponse The JSON response containing the status and message.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:191',
            'division_id' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->getMessageBag()
            ], 403);
        }

        $userAlreadyExists = User::where('username', $request->username)->first();

        if ($userAlreadyExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'User already exists',
            ], 403);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'division_id' => $request->division_id,
            'role' => 'user'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => $user
        ]);
    }

    /**
     * Logs out the user and returns a JSON response with a success message.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json([
            'message' => 'Succesfully logged out'
        ], 200);
    }

    /**
     * Retrieves the authenticated user's information.
     *
     * @return JsonResponse The JSON response containing the user's information.
     */
    public function me(): JsonResponse
    {
        return response()->json(auth()->user(), 200);
    }

    /**
     * Reset the user's password.
     *
     * @param Request $request The request object containing the old password and new password.
     * @throws \Illuminate\Validation\ValidationException If the validation fails.
     * @return JsonResponse The JSON response containing the status and message.
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->only(['old_password', 'new_password']), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|max:191'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->getMessageBag()
            ], 403);
        }

        $user = auth()->user();

        // Compare old password with user request password
        if (!Hash::check($request->old_password, $user['password'])) {
            return response()->json([
                'error' => "Old password did not match !"
            ], 422);
        }

        // Check if user request password same with old password
        if (Hash::check($request->new_password, $user['password'])) {
            return response()->json([
                'error' => "New password must not same with old password!"
            ], 403);
        }

        $user->fill([
            'password' => Hash::make($request->new_password)
        ])->save();

        return response()->json([
            'message' => 'Reset success, user logged out'
        ], 200);
    }
}
