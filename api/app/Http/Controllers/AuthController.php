<?php

namespace App\Http\Controllers;

use Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Str;



/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints for user authentication (login, register, logout)"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/login",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticates a user and returns an API token along with the user details",
     *     @OA\RequestBody(
     *         required=true,
     *         description="User credentials",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="token_example_here"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid credentials")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        $user->tokens()->delete();
        $token = $user->createToken('filerouge')->plainTextToken;
        return response()->json(['token' => $token, "user" => $user]);
    }
    /**
     * @OA\Post(
     *     path="/register",
     *     tags={"Authentication"},
     *     summary="User registration",
     *     description="Registers a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         description="User registration details",
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="user was registered successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function register(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $encodedName = urlencode($request->username);
        // Create the new user
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'avatar' => "https://ui-avatars.com/api/?name={$encodedName}&size=128&background=random&color=fff",
        ]);
        return response()->json(['message' => 'user was registered successfully', 'user' => $user], 201);
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     tags={"Authentication"},
     *     summary="Log the user out",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::updateOrCreate(
                ['google_id' => $googleUser->id],
                [
                    'username' => $googleUser->name,
                    'email' => $googleUser->email,
                    'avatar' => $googleUser->avatar,
                    'password' => Str::random(12) // Str::password(12) is incorrect
                ]
            );

            // Delete existing tokens before creating a new one
            $user->tokens()->delete();
            $token = $user->createToken('filerouge')->plainTextToken;

            return redirect("http://localhost:3000/oauth-success/$token");


        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return response()->json(['error' => 'Invalid Google authentication state.'], 401);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Database error occurred.'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }


}
