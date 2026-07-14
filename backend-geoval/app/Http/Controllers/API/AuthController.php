<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * User registration.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|in:admin,analyste,chercheur'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        // Simple token format for API integration
        $token = Base64_encode(Str::random(40));

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * User Login.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides'
            ], 401);
        }

        $token = base64_encode($user->email . '|' . Str::random(20));

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * User Profile check.
     */
    public function me(Request $request)
    {
        // For simple demo/stateless purposes we can read the token or request headers
        // If header Authorization: Bearer <base64> exists, decode email and fetch
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $decoded = base64_decode($matches[1]);
            $parts = explode('|', $decoded);
            if (count($parts) > 0) {
                $user = User::where('email', $parts[0])->first();
                if ($user) {
                    return response()->json(['success' => true, 'user' => $user]);
                }
            }
        }
        
        return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
    }

    /**
     * Logout.
     */
    public function logout()
    {
        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }
}
