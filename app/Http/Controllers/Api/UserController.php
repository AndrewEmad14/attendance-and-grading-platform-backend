<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'UserController index method']);
    }
    public function show($id)
    {
        return response()->json(['message' => "UserController show method for user with id: $id"]);
    }
    public function store(Request $request)
    {
        return response()->json(['message' => 'UserController store method']);
    }
    public function update(Request $request, $id)
    {
        return response()->json(['message' => "UserController update method for user with id: $id"]);
    }
    public function destroy($id)
    {
        return response()->json(['message' => "UserController destroy method for user with id: $id"]);
    }
}
