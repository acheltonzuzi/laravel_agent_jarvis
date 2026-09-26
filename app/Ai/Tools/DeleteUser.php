<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteUser implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'A description of the tool.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $user = User::find($validated['id']);

        if ($user === null) {
            return json_encode(['error' => 'No user found with the given ID.']);
        }

        if (Auth::user()?->is($user)) {
            return json_encode(['error' => 'You cannot delete the account you are signed in with.']);
        }

        $deleted = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];

        $user->delete();

        return json_encode(['deleted' => $deleted]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the user to delete.')
                ->required(),
        ];
    }
}
