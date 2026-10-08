<?php

namespace App\Http\Controllers\Api\Admin\User;

use App\Http\Controllers\Controller;
use App\Services\Admin\User\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}


    public function index(Request $request)
    {
        $users = $this->userService->getUsers(
            $request->all()
        );

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function show(int $id)
    {
        $user = $this->userService->getUser($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255',],
            'email' => ['required','email','max:255',],
            'phone' => ['nullable','string','max:50',],
            'gender_id' => ['nullable','integer',],
            'status_id' => ['nullable','integer',],
            'password' => ['nullable','string','min:8','confirmed',],
        ]);

        $user = $this->userService->update($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user,
        ]);
    }

    public function destroy(int $id)
    {
        $this->userService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    public function is_blocked(Request $request, int $id)
    {
        $data = $request->validate([
            'is_blocked' => ['required', 'integer', 'in:0,1'],
        ]);

        $this->userService->is_blocked(
            $id,
            (int) $data['is_blocked']
        );

        return response()->json([
            'success' => true,
            'message' => $data['is_blocked']
                ? 'User blocked successfully.'
                : 'User unblocked successfully.',
        ]);
    }


}
