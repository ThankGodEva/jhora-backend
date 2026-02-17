<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'This email is already registered. Please sign in or use another email.',
            ], 422);
        }

        return response()->json([
            'message' => 'Email is available',
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'dob' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'dob' => $request->dob ?: null,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Account created successfully',
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error during registration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function registerICreator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'business_name' => 'required|string|max:255',
            'store_name' => 'required|string|max:255',
            'business_type' => 'required|string',
            'business_category' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'dob' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'icreator',
        ]);

        Vendor::create([
            'user_id' => $user->id,
            'shop_name' => $request->store_name,
            'slug' => Str::slug($request->store_name),
            'business_name' => $request->business_name,
            'business_type' => $request->business_type,
            'business_category' => $request->business_category,
            'verification_status' => 'pending',
            'is_active' => false,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'iCreator account created. Please wait for verification.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function setupBusiness(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'icreator') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json(['error' => 'Vendor profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'business_type' => 'required|string',
            'business_category' => 'required|string',
            'store_name' => 'required|string|max:255',
            'store_url' => 'required|string|max:255',
            'business_address' => 'required|string',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update vendor fields
        $vendor->update($request->only([
            'business_type', 'business_category', 'store_name',
            'store_url', 'business_address'
        ]));

        // Handle cover photo upload
        if ($request->hasFile('cover_photo')) {
            $vendor->clearMediaCollection('cover_photo');
            $vendor->addMediaFromRequest('cover_photo')
                ->toMediaCollection('cover_photo');
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $vendor->clearMediaCollection('logo');
            $vendor->addMediaFromRequest('logo')
                ->toMediaCollection('logo');
        }

        return response()->json([
            'message' => 'Business information saved successfully',
            'vendor' => $vendor->refresh(),
        ]);
    }
}