<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request) : JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required'
        ]);

        if ($validator->errors()->isNotEmpty()) {
            return response()->json([
                'error' => $validator->getMessageBag()
            ],401);
        }

        $token = Auth::attempt($request->only(['username', 'password']));

        if(!$token) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        $payload = auth()->payload();
        $user = Auth::user();
        $status = 200;
//        if ($user['created_at'] == $user['updated_at']){
//            $status = 307;
//        }

        return response()->json([
            'access_token' =>  $token,
            'token_type' => 'bearer',
            'expires_in' => Carbon::parse($payload('exp'))->setTimezone('Asia/Jakarta')->format('H:i:s, d-m-Y'),
        ], $status);
    }

    public function register(Request $request) : JsonResponse
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

        if($userAlreadyExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'User already exists',
            ], 403);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'divison_id' => $request->division_id,
            'role' => 'user'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => $user
        ]);
    }

    public function logout() : JsonResponse
    {
        auth()->logout();

        return response()->json([
            'message' => 'Succesfully logged out'
        ],200);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth()->user(),200);
    }

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
        ],200);
    }
}
